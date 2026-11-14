<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetPreview;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Contributor;
use App\Models\Pack;
use App\Models\Tag;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Bulk-imports Lottie packs from a prepared archive.
 *
 * The archive (storage/app/lottie-import/lottie-import.zip) holds a
 * manifest.json plus one JSON file per animation. The manifest carries the
 * pack, category, title and tags for every file, so this command only has
 * to create the rows — it mirrors what `iconors:import` does for a single
 * Lottie file (private source file marked clean, public copy as the
 * `lottie` preview, published).
 *
 * Built for shared hosting with no SSH: it is safe to run from cron every
 * minute. Each run takes a lock, works for a bounded number of seconds,
 * and skips anything already imported, so it resumes where it stopped.
 *
 *   php artisan iconors:import-packs --dry-run
 *   php artisan iconors:import-packs --limit=5
 *   php artisan iconors:import-packs
 *   php artisan iconors:import-packs --control     (cron: obey control.json)
 */
class ImportLottiePacks extends Command
{
    protected $signature = 'iconors:import-packs
        {--dry-run : Verify the archive and report, without writing anything}
        {--limit=0 : Maximum assets to import in this run (0 = no limit)}
        {--seconds=40 : Time budget for this run}
        {--contributor= : Contributor id or slug that will own the assets}
        {--control : Take mode/limit from storage/app/lottie-import/control.json}';

    protected $description = 'Import Lottie packs from storage/app/lottie-import/lottie-import.zip';

    private const SOURCE = 'drive-lottie-packs';

    private string $dir;

    private string $logFile;

    public function handle(): int
    {
        $this->dir = storage_path('app/lottie-import');
        $this->logFile = storage_path('logs/lottie-import.log');

        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $contributorOption = $this->option('contributor');
        $mode = 'import';
        $control = null;

        if ($this->option('control')) {
            $control = $this->readControl();
            $mode = (string) ($control['mode'] ?? 'idle');

            if (! in_array($mode, ['dry-run', 'import', 'colours'], true)) {
                return self::SUCCESS; // idle: nothing to do, stay silent
            }

            $dryRun = $mode === 'dry-run';
            $limit = (int) ($control['limit'] ?? 0);
            $contributorOption = $control['contributor'] ?? $contributorOption;
        }

        $lock = @fopen($this->dir.'/import.lock', 'c');

        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS; // a previous run is still working
        }

        try {
            if ($mode === 'colours') {
                return $this->runColours($control);
            }

            return $this->runImport($dryRun, $limit, $contributorOption, $control);
        } catch (\Throwable $e) {
            $this->log('FATAL '.get_class($e).': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());

            if ($control !== null) {
                $this->writeControl(['mode' => 'idle', 'stopped' => 'fatal error, see lottie-import.log']);
            }

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function runImport(bool $dryRun, int $limit, mixed $contributorOption, ?array $control): int
    {
        $started = microtime(true);
        $budget = max(5, (int) $this->option('seconds'));

        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP zip extension is not available.');
        }

        $zipPath = $this->dir.'/lottie-import.zip';
        $zip = new ZipArchive();

        if (! is_file($zipPath) || $zip->open($zipPath) !== true) {
            throw new \RuntimeException('Cannot open '.$zipPath);
        }

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);

        if (! is_array($manifest) || empty($manifest['packs'])) {
            throw new \RuntimeException('manifest.json is missing or unreadable inside the archive.');
        }

        $contributor = $this->resolveContributor($contributorOption);

        // Everything this importer has already created, including
        // soft-deleted rows, so a re-run never duplicates an asset.
        $done = [];

        foreach (DB::table('assets')->where('metadata->import_source', self::SOURCE)->pluck('metadata') as $raw) {
            $meta = is_string($raw) ? json_decode($raw, true) : (array) $raw;

            if (! empty($meta['import_key'])) {
                $done[$meta['import_key']] = true;
            }
        }

        $total = 0;

        foreach ($manifest['packs'] as $pack) {
            $total += count($pack['items']);
        }

        if ($dryRun) {
            return $this->dryRun($zip, $manifest, $contributor, $total, count($done), $control);
        }

        $assetType = AssetType::firstOrCreate(
            ['slug' => 'lottie-animation'],
            [
                'name' => 'Lottie Animation',
                'allowed_extensions' => ['json', 'lottie'],
                'allowed_mimes' => ['application/json'],
                'icon' => 'play-circle',
                'is_active' => true,
            ],
        );

        $categories = [];

        foreach ($manifest['categories'] as $row) {
            $categories[$row['name']] = Category::firstOrCreate(
                ['slug' => Str::slug($row['name'])],
                ['name' => $row['name'], 'is_active' => true, 'sort_order' => (int) $row['sort']],
            );
        }

        // Newest-first is the default marketplace sort. Publishing times
        // step back one second per asset in the manifest's interleaved
        // order, so the first pages show a mix of packs rather than 36
        // tiles from the same one.
        $base = Carbon::parse($manifest['generated_at']);

        $imported = 0;
        $failed = 0;
        $stopped = 'complete';

        foreach ($manifest['packs'] as $packRow) {
            $pending = array_filter($packRow['items'], fn ($item) => ! isset($done[$item['key']]));

            if ($pending === []) {
                continue;
            }

            $pack = Pack::firstOrCreate(
                ['slug' => Str::slug($packRow['title'])],
                [
                    'contributor_id' => $contributor->id,
                    'title' => $packRow['title'],
                    'description' => $packRow['description'] ?? null,
                    'is_premium' => false,
                    'price' => 0,
                    'status' => 'published',
                    'published_at' => now(),
                ],
            );

            $category = $categories[$packRow['category']] ?? null;

            foreach ($pending as $item) {
                if ($limit > 0 && $imported >= $limit) {
                    $stopped = 'limit';
                    break 2;
                }

                if (microtime(true) - $started > $budget) {
                    $stopped = 'time';
                    break 2;
                }

                try {
                    $body = $zip->getFromName($item['entry']);

                    if ($body === false || hash('sha256', $body) !== $item['sha']) {
                        throw new \RuntimeException('archive entry missing or corrupt');
                    }

                    $data = json_decode($body, true);

                    if (! is_array($data) || ! isset($data['v'], $data['layers'])) {
                        throw new \RuntimeException('not a Lottie file');
                    }

                    $this->importOne($item, $body, $packRow['title'], $pack, $category, $assetType, $contributor, $base);
                    $done[$item['key']] = true;
                    $imported++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->log("  FAILED {$item['file']}: ".$e->getMessage());

                    if ($failed >= 15) {
                        $stopped = 'too many failures';
                        break 2;
                    }
                }
            }
        }

        $zip->close();
        $this->refreshCounters();
        Cache::forget('home.sections');

        $remaining = $total - count($done);
        $this->log(sprintf(
            'run: imported %d, failed %d, done %d/%d, remaining %d, stopped: %s, %.1fs',
            $imported, $failed, count($done), $total, $remaining, $stopped, microtime(true) - $started,
        ));

        if ($control !== null) {
            if ($stopped === 'too many failures') {
                $this->writeControl(['mode' => 'idle', 'stopped' => 'too many failures']);
            } elseif ($stopped === 'limit') {
                $this->writeControl(['mode' => 'idle', 'stopped' => 'limit reached']);
            } elseif ($remaining <= 0 || ($stopped === 'complete' && $failed > 0)) {
                // Import finished: hand over to the colour analysis that
                // the homepage showcase depends on.
                $this->log('IMPORT COMPLETE: '.count($done).' of '.$total.' assets in place.');
                $this->writeControl(['mode' => 'colours']);
            }
        }

        return self::SUCCESS;
    }

    private function importOne(
        array $item,
        string $body,
        string $packTitle,
        Pack $pack,
        ?Category $category,
        AssetType $assetType,
        Contributor $contributor,
        Carbon $base,
    ): void {
        DB::transaction(function () use ($item, $body, $packTitle, $pack, $category, $assetType, $contributor, $base) {
            $title = $item['title'];

            $asset = Asset::create([
                'contributor_id' => $contributor->id,
                'asset_type_id' => $assetType->id,
                'category_id' => $category?->id,
                'pack_id' => $pack->id,
                'title' => $title,
                'slug' => $this->uniqueSlug($title),
                'description' => "{$title} — Lottie JSON animation from the {$packTitle} pack, ready to drop into web and app projects.",
                'is_premium' => false,
                'price' => 0,
                'is_animated' => true,
                'is_3d' => false,
                'status' => 'published',
                'published_at' => $base->copy()->subSeconds((int) $item['order']),
                'metadata' => [
                    'import_source' => self::SOURCE,
                    'import_key' => $item['key'],
                    'pack' => $packTitle,
                    'source_file' => $item['file'],
                    'imported_at' => now()->toIso8601String(),
                ],
            ]);

            $path = "imported/{$asset->id}/source.json";
            Storage::disk('private')->put($path, $body);

            AssetFile::create([
                'asset_id' => $asset->id,
                'disk' => 'private',
                'path' => $path,
                'original_name' => basename($item['file']),
                'extension' => 'json',
                'mime_type' => 'application/json',
                'size_bytes' => strlen($body),
                'checksum' => $item['sha'],
                'is_source' => true,
                // Lottie JSON is inert data — parsed by the player, never
                // executed — and was validated above.
                'scan_status' => 'clean',
                'scanned_at' => now(),
            ]);

            $previewPath = "imported/previews/{$asset->id}.json";
            Storage::disk('public')->put($previewPath, $body);

            AssetPreview::create([
                'asset_id' => $asset->id,
                'type' => 'lottie',
                'disk' => 'public',
                'path' => $previewPath,
                'width' => 240,
                'height' => 240,
                'size_bytes' => strlen($body),
                'is_primary' => true,
            ]);

            $tagIds = [];

            foreach (array_slice((array) ($item['tags'] ?? []), 0, 20) as $name) {
                $name = Str::lower(trim((string) $name));

                if ($name === '' || Str::slug($name) === '') {
                    continue;
                }

                $tag = Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
                $tagIds[$tag->id] = true;
            }

            $asset->tags()->sync(array_keys($tagIds));

            DB::table('pack_asset')->insertOrIgnore([
                'pack_id' => $pack->id,
                'asset_id' => $asset->id,
                'sort_order' => (int) $item['sort'],
            ]);
        });
    }

    private function dryRun(ZipArchive $zip, array $manifest, Contributor $contributor, int $total, int $already, ?array $control): int
    {
        $bad = 0;
        $bytes = 0;
        $samples = [];

        foreach ($manifest['packs'] as $pack) {
            foreach ($pack['items'] as $i => $item) {
                $body = $zip->getFromName($item['entry']);

                if ($body === false || hash('sha256', $body) !== $item['sha']) {
                    $bad++;

                    continue;
                }

                $bytes += strlen($body);

                if ($i === 0 && count($samples) < 6) {
                    $samples[] = $pack['title'].' -> '.$item['title'];
                }
            }
        }

        $zip->close();

        $this->log('DRY RUN (nothing written)');
        $this->log('  php '.PHP_VERSION.', laravel '.app()->version().', db '.DB::connection()->getDriverName());
        $this->log('  contributor: #'.$contributor->id.' '.$contributor->display_name.' ('.$contributor->slug.')');
        $this->log('  packs: '.count($manifest['packs']).', categories: '.count($manifest['categories']).', assets: '.$total);
        $this->log('  archive entries verified: '.($total - $bad).' ok, '.$bad.' bad, '.round($bytes / 1048576, 1).' MB');
        $this->log('  already imported: '.$already.', would import: '.($total - $already));
        $this->log('  existing rows: asset_types '.DB::table('asset_types')->count().', categories '.DB::table('categories')->count()
            .', packs '.DB::table('packs')->count().', assets '.DB::table('assets')->count());
        $this->log('  private disk writable: '.(is_writable(storage_path('app/assets-private')) ? 'yes' : 'NO')
            .', public disk writable: '.(is_writable(storage_path('app/public')) ? 'yes' : 'NO'));

        foreach ($samples as $line) {
            $this->log('  e.g. '.$line);
        }

        if ($control !== null) {
            $this->writeControl(['mode' => 'idle', 'stopped' => 'dry run finished']);
        }

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** Runs the existing colour analysis so imported work can appear on the homepage. */
    private function runColours(?array $control): int
    {
        $this->log('colour analysis: starting');

        // Flip to idle first: the analysis resumes on its own if it is cut
        // short, but it must never be started twice by overlapping crons.
        if ($control !== null) {
            $this->writeControl(['mode' => 'idle', 'stopped' => 'colour analysis started']);
        }

        $code = $this->callSilent('iconors:analyse-colours');
        Cache::forget('home.sections');

        $pending = DB::table('assets')->whereNull('deleted_at')->whereNull('metadata->is_colourful')->count();
        $colourful = DB::table('assets')->whereNull('deleted_at')->where('metadata->is_colourful', true)->count();
        $this->log("colour analysis: exit {$code}, colourful {$colourful}, still unanalysed {$pending}");

        if ($control !== null) {
            $this->writeControl(['mode' => 'idle', 'stopped' => 'all done']);
        }

        return self::SUCCESS;
    }

    private function resolveContributor(mixed $option): Contributor
    {
        if ($option !== null && $option !== '') {
            $contributor = is_numeric($option)
                ? Contributor::find((int) $option)
                : Contributor::where('slug', (string) $option)->first();

            if (! $contributor) {
                throw new \RuntimeException("Contributor '{$option}' not found.");
            }

            return $contributor;
        }

        $approved = Contributor::where('status', 'approved')->get();

        if ($approved->count() !== 1) {
            throw new \RuntimeException(
                'Expected exactly one approved contributor, found '.$approved->count().'. Pass --contributor.'
            );
        }

        return $approved->first();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'animation';
        $slug = $base;
        $i = 1;

        while (Asset::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function refreshCounters(): void
    {
        DB::statement('UPDATE categories c SET assets_count = (
            SELECT COUNT(*) FROM assets a
            WHERE a.category_id = c.id AND a.status = "published" AND a.deleted_at IS NULL
        )');

        DB::statement('UPDATE contributors k SET assets_count = (
            SELECT COUNT(*) FROM assets a
            WHERE a.contributor_id = k.id AND a.status = "published" AND a.deleted_at IS NULL
        )');

        DB::statement('UPDATE packs p SET assets_count = (
            SELECT COUNT(*) FROM assets a
            WHERE a.pack_id = p.id AND a.status = "published" AND a.deleted_at IS NULL
        )');

        DB::statement('UPDATE tags t SET usage_count = (
            SELECT COUNT(*) FROM asset_tag x WHERE x.tag_id = t.id
        )');
    }

    private function readControl(): ?array
    {
        $file = $this->dir.'/control.json';

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    private function writeControl(array $data): void
    {
        $data['updated_at'] = now()->toIso8601String();
        file_put_contents($this->dir.'/control.json', json_encode($data), LOCK_EX);
    }

    private function log(string $line): void
    {
        $this->line($line);
        @file_put_contents($this->logFile, '['.date('Y-m-d H:i:s').'] '.$line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
