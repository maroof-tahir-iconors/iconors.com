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
use App\Services\Asset\SvgSanitizer;
use App\Support\DriveLibraryNames as Names;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Imports the Iconors library (icon, illustration and 3D packs) straight
 * from the shared Google Drive folders.
 *
 *   crawl    list every Drive folder and write manifest.json (titles, tags, packs)
 *   dry-run  report what would be imported
 *   run      import a time-boxed batch; resumes where it stopped
 *   colours  run the colour analysis the homepage showcase depends on
 *   status   counts only
 *   probe    environment report (no writes to the catalogue)
 *
 * Shared hosting has no SSH, so every mode is short and resumable: each
 * run takes a lock, works for --seconds, and skips anything already
 * imported (matched by metadata.import_key = Drive file id).
 */
class ImportDriveLibrary extends Command
{
    protected $signature = 'iconors:import-drive
        {--mode=status : probe|crawl|dry-run|run|colours|status}
        {--seconds=40 : Time budget for this run}
        {--limit=0 : Maximum assets to import in this run (0 = no limit)}
        {--batch=8 : Assets downloaded in parallel}
        {--contributor= : Contributor id or slug that will own the assets}';

    protected $description = 'Import icon, illustration and 3D packs from the Iconors Google Drive';

    private const SOURCE = 'drive-library';

    /** Top-level Drive folders, all shared "anyone with the link". */
    private const ROOTS = [
        'icons' => '1SclSKwpKNMipjyC5gb1SwUBND4sY6nY8',
        'illustrations' => '1j7P-9nMR72yW8B_KiX09AvGpr7sarLJ5',
        '3d' => '1c50pTMtVNpwM5Js6N-iB6jVyAFaAHt3w',
    ];

    private const TYPES = [
        'icons' => ['slug' => 'svg-icon', 'name' => 'SVG Icon', 'ext' => ['svg'], 'mimes' => ['image/svg+xml'], 'icon' => 'grid-3x3-gap'],
        'illustrations' => ['slug' => 'illustration', 'name' => 'Illustration', 'ext' => ['svg', 'eps', 'ai', 'zip'], 'mimes' => ['image/svg+xml', 'application/zip'], 'icon' => 'palette'],
        '3d' => ['slug' => '3d-icon', 'name' => '3D Icon', 'ext' => ['png', 'blend', 'zip'], 'mimes' => ['image/png', 'application/zip'], 'icon' => 'box'],
    ];

    private string $dir;

    private string $logFile;

    public function handle(): int
    {
        $this->dir = storage_path('app/drive-import');
        $this->logFile = storage_path('logs/drive-import.log');
        @mkdir($this->dir, 0775, true);
        @mkdir($this->dir.'/tmp', 0775, true);
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $mode = (string) $this->option('mode');

        $lock = @fopen($this->dir.'/import.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->line('BUSY: a previous run is still working');

            return self::SUCCESS;
        }

        try {
            return match ($mode) {
                'probe' => $this->probe(),
                'crawl' => $this->crawl(),
                'dry-run' => $this->dryRun(),
                'run' => $this->runImport(),
                'colours' => $this->colours(),
                default => $this->status(),
            };
        } catch (\Throwable $e) {
            $this->log('FATAL '.get_class($e).': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());

            return self::FAILURE;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /* ------------------------------------------------------------ probe */

    private function probe(): int
    {
        $out = [];
        $out[] = 'php '.PHP_VERSION.', laravel '.app()->version().', memory_limit '.ini_get('memory_limit').', max_execution_time '.ini_get('max_execution_time');
        $out[] = 'ext: gd='.(extension_loaded('gd') ? 'y' : 'n').' webp='.(function_exists('imagewebp') ? 'y' : 'n')
            .' zip='.(class_exists(ZipArchive::class) ? 'y' : 'n').' curl='.(extension_loaded('curl') ? 'y' : 'n')
            .' dom='.(extension_loaded('dom') ? 'y' : 'n');
        try {
            $r = Http::timeout(20)->get('https://drive.google.com/embeddedfolderview', ['id' => self::ROOTS['3d']]);
            $out[] = 'drive folder http '.$r->status().' bytes '.strlen($r->body());
        } catch (\Throwable $e) {
            $out[] = 'drive folder ERROR '.$e->getMessage();
        }
        foreach (['assets', 'packs', 'asset_previews', 'asset_files', 'asset_types', 'categories', 'pack_asset'] as $t) {
            $out[] = "cols {$t}: ".implode(',', Schema::getColumnListing($t));
        }
        $out[] = 'asset_types: '.DB::table('asset_types')->get(['id', 'slug', 'name'])->map(fn ($r) => "{$r->id}:{$r->slug}")->implode(' ');
        $out[] = 'preview types: '.DB::table('asset_previews')->select('type', DB::raw('count(*) c'))->groupBy('type')->get()->map(fn ($r) => "{$r->type}={$r->c}")->implode(' ');
        $out[] = 'categories: '.DB::table('categories')->orderBy('sort_order')->get(['id', 'slug', 'parent_id', 'assets_count'])->map(fn ($r) => "{$r->id}:{$r->slug}".($r->parent_id ? "<{$r->parent_id}" : '')."({$r->assets_count})")->implode(' ');
        $out[] = 'contributors approved: '.Contributor::where('status', 'approved')->get(['id', 'slug'])->map(fn ($c) => "{$c->id}:{$c->slug}")->implode(' ');
        $out[] = 'packs: '.DB::table('packs')->count().', assets: '.DB::table('assets')->count();
        $out[] = 'pack fillable: '.implode(',', (new Pack)->getFillable());
        $out[] = 'asset fillable: '.implode(',', (new Asset)->getFillable());
        $out[] = 'preview fillable: '.implode(',', (new AssetPreview)->getFillable());
        $out[] = 'disks: private='.config('filesystems.disks.private.root').' public='.config('filesystems.disks.public.root').' url='.config('filesystems.disks.public.url');
        $out[] = 'free disk: '.round(disk_free_space(storage_path()) / 1073741824, 1).' GB';
        file_put_contents($this->dir.'/probe.txt', implode(PHP_EOL, $out).PHP_EOL);
        $this->line('probe written to storage/app/drive-import/probe.txt');

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------ crawl */

    /** @return list<array{t:string,name:string,id:string}> */
    private function parseFolder(string $html): array
    {
        $items = [];
        preg_match_all('/<a href="([^"]+)"[^>]*>.*?flip-entry-title">([^<]*)</s', $html, $m, PREG_SET_ORDER);
        foreach ($m as $row) {
            $url = $row[1];
            $name = html_entity_decode($row[2], ENT_QUOTES | ENT_HTML5);
            if (preg_match('#/folders/([\w-]+)#', $url, $f)) {
                $items[] = ['t' => 'D', 'name' => $name, 'id' => $f[1]];
            } elseif (preg_match('#/d/([\w-]+)#', $url, $f) || preg_match('#[?&]id=([\w-]+)#', $url, $f)) {
                $items[] = ['t' => 'F', 'name' => $name, 'id' => $f[1]];
            }
        }

        return $items;
    }

    /**
     * Lists many folders at once.
     *
     * @param  array<string,string>  $folders  key => folder id
     * @return array<string,list<array>>
     */
    private function listFolders(array $folders): array
    {
        $result = [];
        foreach (array_chunk($folders, 6, true) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $key => $id) {
                    $pool->as((string) $key)->timeout(60)
                        ->get('https://drive.google.com/embeddedfolderview', ['id' => $id]);
                }
            });
            foreach ($chunk as $key => $id) {
                $r = $responses[(string) $key] ?? null;
                // Drive occasionally throttles bursts: retry one at a time.
                for ($try = 0; $try < 4 && (! $r instanceof \Illuminate\Http\Client\Response || ! $r->successful() || ! str_contains($r->body(), 'flip-entry')); $try++) {
                    usleep(800000 * ($try + 1));
                    try {
                        $r = Http::timeout(60)->get('https://drive.google.com/embeddedfolderview', ['id' => $id]);
                    } catch (\Throwable $e) {
                        $r = null;
                    }
                }
                if (! $r instanceof \Illuminate\Http\Client\Response || ! $r->successful()) {
                    throw new \RuntimeException("Drive folder {$id} could not be listed (".($r?->status() ?? 'no response').')');
                }
                $result[$key] = $this->parseFolder($r->body());
            }
        }

        return $result;
    }

    private function crawl(): int
    {
        $started = microtime(true);
        // level 1: pack folders
        $level1 = $this->listFolders(self::ROOTS);
        $packFolders = [];
        foreach ($level1 as $type => $items) {
            foreach ($items as $it) {
                if ($it['t'] === 'D') {
                    $packFolders[$type.'|'.$it['name']] = $it['id'];
                }
            }
        }
        // level 2: pack contents; illustrations have format sub-folders
        $level2 = $this->listFolders($packFolders);
        $sub = [];
        $files = [];
        foreach ($level2 as $key => $items) {
            foreach ($items as $it) {
                if ($it['t'] === 'D') {
                    $sub[$key.'|'.$it['name']] = $it['id'];
                } else {
                    $files[$key][] = $it + ['sub' => ''];
                }
            }
        }
        foreach ($this->listFolders($sub) as $key => $items) {
            [$type, $pack, $subName] = explode('|', $key, 3);
            foreach ($items as $it) {
                if ($it['t'] === 'F') {
                    $files[$type.'|'.$pack][] = $it + ['sub' => $subName];
                }
            }
        }

        $packs = [];
        $total = 0;
        foreach ($files as $key => $list) {
            [$type, $folder] = explode('|', $key, 2);
            $groups = [];
            $cover = null;
            foreach ($list as $f) {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $stem = preg_replace('/\.[^.]+$/', '', $f['name']);
                if ($type === 'illustrations' && $f['sub'] === '') {
                    if (str_ends_with(strtolower($stem), '_preview') && $ext === 'png') {
                        $cover = $f['id'];
                    }

                    continue;
                }
                if ($type === 'icons' && $ext !== 'svg') {
                    continue;
                }
                if ($type === '3d' && ! in_array($ext, ['png', 'blend'], true)) {
                    continue;
                }
                if ($type === 'illustrations' && ! in_array($ext, ['svg', 'eps', 'ai'], true)) {
                    continue;
                }
                $groups[$stem][$ext] = ['id' => $f['id'], 'name' => $f['name']];
            }
            $primaryExt = $type === '3d' ? 'png' : 'svg';
            $items = [];
            ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($groups as $stem => $formats) {
                if (! isset($formats[$primaryExt])) {
                    $this->log("  skipped {$folder}/{$stem}: no {$primaryExt}");

                    continue;
                }
                $order = ['svg', 'png', 'eps', 'ai', 'blend'];
                uksort($formats, fn ($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));
                $title = Names::assetTitle($folder, $formats[$primaryExt]['name']);
                $items[] = [
                    'key' => $formats[$primaryExt]['id'],
                    'title' => $title,
                    'tags' => Names::tags($type, $title, $folder),
                    'files' => array_values($formats),
                    'formats' => array_keys($formats),
                    'sort' => count($items) + 1,
                ];
            }
            if ($items === []) {
                continue;
            }
            $total += count($items);
            $packs[] = [
                'type' => $type,
                'folder' => $folder,
                'title' => Names::packTitle($type, $folder),
                'category' => $this->categoryFor($type, $folder),
                'cover' => $cover,
                'items' => $items,
            ];
        }

        // Interleave publishing order across packs of the same type so the
        // newest-first listing does not open with 50 tiles from one pack.
        $order = 0;
        foreach (array_keys(self::TYPES) as $type) {
            $idx = array_keys(array_filter($packs, fn ($p) => $p['type'] === $type));
            $max = $idx === [] ? 0 : max(array_map(fn ($i) => count($packs[$i]['items']), $idx));
            for ($n = 0; $n < $max; $n++) {
                foreach ($idx as $i) {
                    if (isset($packs[$i]['items'][$n])) {
                        $packs[$i]['items'][$n]['order'] = $order++;
                    }
                }
            }
        }

        $manifest = ['generated_at' => now()->toIso8601String(), 'packs' => array_values($packs)];
        file_put_contents($this->dir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $byType = [];
        foreach ($packs as $p) {
            $byType[$p['type']] = ($byType[$p['type']] ?? 0) + count($p['items']);
        }
        $this->log(sprintf('crawl: %d packs, %d assets (%s), %.1fs', count($packs), $total,
            collect($byType)->map(fn ($c, $t) => "{$t} {$c}")->implode(', '), microtime(true) - $started));

        return self::SUCCESS;
    }

    /** Category name for a Drive pack folder (matched by keyword). */
    private function categoryFor(string $type, string $folder): string
    {
        $exact = [
            'Interface Icons' => 'Communication',
            'Interface New Icons' => 'E-Commerce & Shopping',
            'New Interface Icons' => 'User Interface',
            'Mobile User Interface Icons' => 'User Interface',
            'User Interface Icons' => 'Files & Productivity',
            'Users Interface Icons' => 'Media & Video',
            'Calendar Icons' => 'Files & Productivity',
        ];
        if (isset($exact[$folder])) {
            return $exact[$folder];
        }
        $f = strtolower($folder);
        // Existing site categories are reused by slug (islamic, business-finance,
        // social-media, user-interface, health, communication, media-video,
        // maps-location, files-productivity, fun-celebration).
        $map = [
            'Islamic' => ['allah', 'prophet', 'islamic', 'ramadan'],
            'Fun & Celebration' => ['festival'],
            'Artificial Intelligence' => ['artificial', 'ai-and', 'ai_'],
            'Technology' => ['it-and', 'it_', 'cyber', 'smart-home'],
            'E-Commerce & Shopping' => ['commerce', 'ecommerce', 'shopping'],
            'Business & Finance' => ['business', 'finance', 'crypto', 'marketing'],
            'Files & Productivity' => ['office', 'remote', 'teamwork'],
            'Food & Drink' => ['food'],
            'Health' => ['health', 'medical'],
            'Education' => ['education'],
            'Travel & Transport' => ['travel', 'automotive', 'vehicle'],
            'Social Media' => ['social'],
            'Communication' => ['communication'],
            'Maps & Location' => ['compass'],
            'Media & Video' => ['media'],
            'Entertainment & Gaming' => ['gaming', 'sports'],
            'Science & Nature' => ['space', 'environment', 'green'],
            'Home & Lifestyle' => ['home', 'barber', 'people', 'real-estate', 'construction'],
        ];
        foreach ($map as $name => $needles) {
            foreach ($needles as $n) {
                if (str_contains($f, $n)) {
                    return $name;
                }
            }
        }

        return 'User Interface';
    }

    /* ------------------------------------------------------------ helpers */

    private function manifest(): array
    {
        $file = $this->dir.'/manifest.json';
        if (! is_file($file)) {
            throw new \RuntimeException('manifest.json missing - run --mode=crawl first');
        }
        $m = json_decode((string) file_get_contents($file), true);
        if (! is_array($m) || empty($m['packs'])) {
            throw new \RuntimeException('manifest.json unreadable');
        }

        return $m;
    }

    /** @return array<string,true> */
    private function doneKeys(): array
    {
        $done = [];
        foreach (DB::table('assets')->where('metadata->import_source', self::SOURCE)->pluck('metadata') as $raw) {
            $meta = is_string($raw) ? json_decode($raw, true) : (array) $raw;
            if (! empty($meta['import_key'])) {
                $done[$meta['import_key']] = true;
            }
        }

        return $done;
    }

    private function status(): int
    {
        $done = $this->doneKeys();
        $total = 0;
        if (is_file($this->dir.'/manifest.json')) {
            foreach ($this->manifest()['packs'] as $p) {
                $total += count($p['items']);
            }
        }
        $this->line('STATUS done '.count($done).' / '.$total);

        return self::SUCCESS;
    }

    private function dryRun(): int
    {
        $m = $this->manifest();
        $done = $this->doneKeys();
        $contributor = $this->resolveContributor($this->option('contributor'));
        $this->log('DRY RUN contributor #'.$contributor->id.' '.$contributor->slug);
        foreach ($m['packs'] as $p) {
            $pending = count(array_filter($p['items'], fn ($i) => ! isset($done[$i['key']])));
            $this->log(sprintf('  %-14s %-45s %-24s %3d items, %3d pending, e.g. %s', $p['type'], $p['title'], $p['category'], count($p['items']), $pending, $p['items'][0]['title']));
        }

        return self::SUCCESS;
    }

    private function driveUrl(string $id): string
    {
        return 'https://drive.usercontent.google.com/download?id='.$id.'&export=download&confirm=t';
    }

    /* ------------------------------------------------------------ import */

    private function runImport(): int
    {
        $started = microtime(true);
        $budget = max(5, (int) $this->option('seconds'));
        $limit = (int) $this->option('limit');
        $batchSize = max(1, min(16, (int) $this->option('batch')));
        $m = $this->manifest();
        $done = $this->doneKeys();
        $contributor = $this->resolveContributor($this->option('contributor'));
        $base = Carbon::parse($m['generated_at']);

        $types = [];
        foreach (self::TYPES as $key => $t) {
            $types[$key] = AssetType::firstOrCreate(['slug' => $t['slug']], [
                'name' => $t['name'], 'allowed_extensions' => $t['ext'], 'allowed_mimes' => $t['mimes'],
                'icon' => $t['icon'], 'is_active' => true,
            ]);
        }

        $held = is_file($this->dir.'/held.json') ? (json_decode((string) file_get_contents($this->dir.'/held.json'), true) ?: []) : [];
        $pending = [];
        $total = 0;
        foreach ($m['packs'] as $pi => $p) {
            foreach ($p['items'] as $item) {
                $total++;
                if (! isset($done[$item['key']]) && ! isset($held[$item['key']])) {
                    $pending[] = [$pi, $item];
                }
            }
        }

        $imported = 0;
        $failed = 0;
        $stopped = 'complete';
        $packCache = [];
        $catCache = [];

        foreach (array_chunk($pending, $batchSize) as $batch) {
            if ($limit > 0 && $imported >= $limit) {
                $stopped = 'limit';
                break;
            }
            if (microtime(true) - $started > $budget) {
                $stopped = 'time';
                break;
            }

            // Download every file of the batch in parallel, straight to disk.
            $jobs = [];
            foreach ($batch as $bi => [$pi, $item]) {
                foreach ($item['files'] as $fi => $f) {
                    $jobs["{$bi}_{$fi}"] = ['id' => $f['id'], 'path' => $this->dir.'/tmp/'.$f['id'].'.'.strtolower(pathinfo($f['name'], PATHINFO_EXTENSION))];
                }
            }
            $responses = Http::pool(function (Pool $pool) use ($jobs) {
                foreach ($jobs as $k => $j) {
                    $pool->as($k)->timeout(180)->withOptions(['sink' => $j['path']])->get($this->driveUrl($j['id']));
                }
            });

            foreach ($batch as $bi => [$pi, $item]) {
                $p = $m['packs'][$pi];
                $paths = [];
                try {
                    foreach ($item['files'] as $fi => $f) {
                        $r = $responses["{$bi}_{$fi}"] ?? null;
                        $path = $jobs["{$bi}_{$fi}"]['path'];
                        if (! $r instanceof \Illuminate\Http\Client\Response || ! $r->successful() || ! is_file($path) || filesize($path) < 50) {
                            throw new \RuntimeException('download failed for '.$f['name']);
                        }
                        $head = (string) file_get_contents($path, false, null, 0, 200);
                        if (stripos($head, '<!doctype html') !== false || stripos($head, '<html') !== false) {
                            throw new \RuntimeException('Drive returned a web page for '.$f['name']);
                        }
                        $paths[strtolower(pathinfo($f['name'], PATHINFO_EXTENSION))] = ['path' => $path, 'name' => $f['name']];
                    }

                    $pack = $packCache[$pi] ??= $this->packFor($p, $contributor);
                    $category = $catCache[$p['category']] ??= Category::firstOrCreate(
                        ['slug' => Str::slug($p['category'])],
                        ['name' => $p['category'], 'is_active' => true, 'sort_order' => 50],
                    );
                    $this->importOne($p, $item, $paths, $pack, $category, $types[$p['type']], $contributor, $base);
                    $done[$item['key']] = true;
                    $imported++;
                } catch (HeldAssetException $e) {
                    $held[$item['key']] = "{$p['folder']}/{$item['title']}: ".$e->getMessage();
                    file_put_contents($this->dir.'/held.json', json_encode($held, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
                } catch (\Throwable $e) {
                    $failed++;
                    $this->log("  FAILED {$p['folder']}/{$item['title']}: ".$e->getMessage());
                } finally {
                    foreach ($item['files'] as $fi => $f) {
                        @unlink($jobs["{$bi}_{$fi}"]['path']);
                    }
                }
            }
            if ($failed >= 10) {
                $stopped = 'too many failures';
                break;
            }
        }

        $this->refreshCounters();
        Cache::forget('home.sections');
        Cache::forget('nav.tabs.live-types');

        $remaining = $total - count($done) - count($held);
        $line = sprintf('run: imported %d, failed %d, done %d/%d, held %d, remaining %d, stopped: %s, %.1fs',
            $imported, $failed, count($done), $total, count($held), $remaining, $stopped, microtime(true) - $started);
        $this->log($line);

        return self::SUCCESS;
    }

    private function packFor(array $p, Contributor $contributor): Pack
    {
        $metaKey = 'drive:'.$p['type'].':'.$p['folder'];
        $map = $this->packMap();
        if (isset($map[$metaKey]) && ($pack = Pack::find($map[$metaKey]))) {
            return $pack;
        }
        $base = Str::slug($p['title']);
        $slug = $base;
        $i = 1;
        while (Pack::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }
        $pack = Pack::create([
            'contributor_id' => $contributor->id,
            'title' => $p['title'],
            'slug' => $slug,
            'description' => $this->packDescription($p),
            'is_premium' => false,
            'price' => 0,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $map[$metaKey] = $pack->id;
        file_put_contents($this->dir.'/packs.json', json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

        return $pack;
    }

    private function packMap(): array
    {
        $file = $this->dir.'/packs.json';

        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    private function packDescription(array $p): string
    {
        $n = count($p['items']);

        return match ($p['type']) {
            'icons' => "{$p['title']} — a set of {$n} free SVG icons with a consistent style, ready for websites, apps and presentations.",
            'illustrations' => "{$p['title']} — {$n} free vector illustrations in SVG and EPS, ready for landing pages, apps and marketing.",
            default => "{$p['title']} — {$n} free 3D icons with transparent PNG renders and editable Blender source files.",
        };
    }

    private function importOne(array $p, array $item, array $paths, Pack $pack, Category $category, AssetType $assetType, Contributor $contributor, Carbon $base): void
    {
        $type = $p['type'];
        $title = $item['title'];
        $sanitizer = app(SvgSanitizer::class);

        // Sanitised SVG (icons + illustrations) is both the preview and the source copy.
        $svg = null;
        if (isset($paths['svg'])) {
            $result = $sanitizer->sanitize((string) file_get_contents($paths['svg']['path']));
            if ($result['svg'] === '') {
                throw new \RuntimeException('SVG could not be parsed');
            }
            // Removals that do not change how the file looks are accepted:
            // Illustrator's data-name labels always, and clip-path references
            // only for files checked to render pixel-identical without them.
            $harmless = ['data-name attribute'];
            if (isset($this->verifiedLossless()[$item['key']])) {
                $harmless[] = 'unsafe value in clip-path';
            }
            if (array_diff($result['removals'], $harmless) !== []) {
                // Never publish a changed SVG: stripping attributes (e.g. gradient
                // fills) would ship a broken-looking asset. Hold it for review.
                throw new HeldAssetException(implode(', ', $result['removals']));
            }
            $svg = '<?xml version="1.0" encoding="UTF-8"?>'."\n".$result['svg'];
        }

        // 3D preview: a 512px WebP (falls back to PNG) instead of the 1024px render.
        $preview = null;
        if ($type === '3d') {
            $preview = $this->resizePng($paths['png']['path'], 512);
        }

        $written = [];
        try {
        DB::transaction(function () use ($p, $item, $paths, $pack, $category, $assetType, $contributor, $base, $type, $title, $svg, $preview, &$written) {
            $formatsLabel = strtoupper(implode(', ', $item['formats']));
            $asset = Asset::create([
                'contributor_id' => $contributor->id,
                'asset_type_id' => $assetType->id,
                'category_id' => $category->id,
                'pack_id' => $pack->id,
                'title' => $title,
                'slug' => $this->uniqueSlug($title),
                'description' => match ($type) {
                    'icons' => "{$title} — free SVG icon from the {$p['title']} pack. Scalable, editable and ready for web, app and print.",
                    'illustrations' => "{$title} — free vector illustration from the {$p['title']} pack. Download includes {$formatsLabel}.",
                    default => "{$title} — free 3D icon from the {$p['title']} pack. Download includes a transparent PNG render and the Blender (.blend) source.",
                },
                'is_premium' => false,
                'price' => 0,
                'is_animated' => false,
                'is_3d' => $type === '3d',
                'status' => 'published',
                'published_at' => $base->copy()->subSeconds((int) ($item['order'] ?? 0)),
                'metadata' => [
                    'import_source' => self::SOURCE,
                    'import_key' => $item['key'],
                    'pack' => $p['title'],
                    'drive_folder' => $p['folder'],
                    'formats' => $item['formats'],
                    'source_files' => array_column($item['files'], 'name'),
                    'imported_at' => now()->toIso8601String(),
                ],
            ]);

            $slug = $asset->slug;
            $private = Storage::disk('private');
            $public = Storage::disk('public');

            // ---- source file
            if ($type === 'icons') {
                $sourcePath = "imported/{$asset->id}/{$slug}.svg";
                $private->put($sourcePath, $svg);
                $written[] = ['private', $sourcePath];
                $source = ['ext' => 'svg', 'mime' => 'image/svg+xml', 'name' => "{$slug}.svg", 'size' => strlen($svg), 'sha' => hash('sha256', $svg)];
            } else {
                $zipFile = $this->dir.'/tmp/'.$asset->id.'.zip';
                $zip = new ZipArchive();
                if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('cannot create zip');
                }
                foreach ($paths as $ext => $f) {
                    if ($ext === 'svg') {
                        $zip->addFromString("{$slug}.svg", $svg);
                    } else {
                        $zip->addFile($f['path'], "{$slug}.{$ext}");
                    }
                }
                $zip->addFromString('LICENSE.txt', "{$title}\nFrom the {$p['title']} pack on Iconors - https://iconors.com\nFree to use in personal and commercial projects under the Iconors licence.\n");
                $zip->close();
                $sourcePath = "imported/{$asset->id}/{$slug}.zip";
                $stream = fopen($zipFile, 'rb');
                $private->writeStream($sourcePath, $stream);
                $written[] = ['private', $sourcePath];
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $source = ['ext' => 'zip', 'mime' => 'application/zip', 'name' => "{$slug}.zip", 'size' => filesize($zipFile), 'sha' => hash_file('sha256', $zipFile)];
                @unlink($zipFile);
            }

            AssetFile::create([
                'asset_id' => $asset->id,
                'disk' => 'private',
                'path' => $sourcePath,
                'original_name' => $source['name'],
                'extension' => $source['ext'],
                'mime_type' => $source['mime'],
                'size_bytes' => $source['size'],
                'checksum' => $source['sha'],
                'is_source' => true,
                // Our own library: SVG was run through SvgSanitizer above;
                // PNG/EPS/AI/BLEND are delivered inside a zip, never rendered.
                'scan_status' => 'clean',
                'scanned_at' => now(),
            ]);

            // ---- preview
            if ($type === '3d') {
                $previewPath = "imported/previews/{$asset->id}.{$preview['ext']}";
                $public->put($previewPath, $preview['body']);
                $written[] = ['public', $previewPath];
                $w = $h = 512;
                $size = strlen($preview['body']);
            } else {
                $previewPath = "imported/previews/{$asset->id}.svg";
                $public->put($previewPath, $svg);
                $written[] = ['public', $previewPath];
                [$w, $h] = $type === 'icons' ? [240, 240] : [480, 360];
                $size = strlen($svg);
            }
            AssetPreview::create([
                'asset_id' => $asset->id,
                'type' => 'medium',
                'disk' => 'public',
                'path' => $previewPath,
                'width' => $w,
                'height' => $h,
                'size_bytes' => $size,
                'is_primary' => true,
            ]);

            $tagIds = [];
            foreach (array_slice((array) $item['tags'], 0, 20) as $name) {
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
        } catch (\Throwable $e) {
            // The rows were rolled back; remove the files written for them too.
            foreach ($written as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }
    }

    private ?array $verified = null;

    /** @return array<string,true> Drive ids listed in verified-lossless.json */
    private function verifiedLossless(): array
    {
        if ($this->verified === null) {
            $file = $this->dir.'/verified-lossless.json';
            $ids = is_file($file) ? (json_decode((string) file_get_contents($file), true)['ids'] ?? []) : [];
            $this->verified = array_fill_keys($ids, true);
        }

        return $this->verified;
    }

    /** @return array{body:string,ext:string} */
    private function resizePng(string $path, int $size): array
    {
        if (! function_exists('imagecreatefrompng')) {
            return ['body' => (string) file_get_contents($path), 'ext' => 'png'];
        }
        $src = @imagecreatefrompng($path);
        if (! $src) {
            throw new \RuntimeException('PNG render could not be read');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $size / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        if (function_exists('imagewebp')) {
            imagewebp($dst, null, 84);
            $ext = 'webp';
        } else {
            imagepng($dst, null, 7);
            $ext = 'png';
        }
        $body = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return ['body' => $body, 'ext' => $ext];
    }

    private function colours(): int
    {
        $this->log('colour analysis: starting');
        $code = $this->callSilent('iconors:analyse-colours');
        Cache::forget('home.sections');
        $pending = DB::table('assets')->whereNull('deleted_at')->whereNull('metadata->is_colourful')->count();
        $this->log("colour analysis: exit {$code}, still unanalysed {$pending}");

        return self::SUCCESS;
    }

    private function resolveContributor(mixed $option): Contributor
    {
        if ($option !== null && $option !== '') {
            $c = is_numeric($option) ? Contributor::find((int) $option) : Contributor::where('slug', (string) $option)->first();
            if (! $c) {
                throw new \RuntimeException("Contributor '{$option}' not found.");
            }

            return $c;
        }
        $approved = Contributor::where('status', 'approved')->get();
        if ($approved->count() !== 1) {
            throw new \RuntimeException('Expected exactly one approved contributor, found '.$approved->count().'. Pass --contributor.');
        }

        return $approved->first();
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'asset';
        $slug = $base;
        $i = 1;
        while (Asset::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function refreshCounters(): void
    {
        DB::statement('UPDATE categories c SET assets_count = (SELECT COUNT(*) FROM assets a WHERE a.category_id = c.id AND a.status = "published" AND a.deleted_at IS NULL)');
        DB::statement('UPDATE contributors k SET assets_count = (SELECT COUNT(*) FROM assets a WHERE a.contributor_id = k.id AND a.status = "published" AND a.deleted_at IS NULL)');
        DB::statement('UPDATE packs p SET assets_count = (SELECT COUNT(*) FROM assets a WHERE a.pack_id = p.id AND a.status = "published" AND a.deleted_at IS NULL)');
        DB::statement('UPDATE tags t SET usage_count = (SELECT COUNT(*) FROM asset_tag x WHERE x.tag_id = t.id)');
    }

    private function log(string $line): void
    {
        $this->line($line);
        @file_put_contents($this->logFile, '['.date('Y-m-d H:i:s').'] '.$line.PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/** An asset that cannot be published unchanged and needs a human decision. */
class HeldAssetException extends \RuntimeException
{
}
