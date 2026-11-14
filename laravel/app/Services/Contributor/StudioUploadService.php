<?php

namespace App\Services\Contributor;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\AssetPreview;
use App\Models\AssetType;
use App\Models\Contributor;
use App\Models\Tag;
use App\Services\Asset\SvgSanitizer;
use App\Support\DriveLibraryNames as Names;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Bulk uploads from the Contributor Studio.
 *
 * One call = one asset: the files of a group share a file name (e.g.
 * rocket.svg + rocket.eps). The required format becomes the preview, the
 * group is stored privately (as a ZIP when there is more than one file)
 * and the asset goes straight to the review queue.
 *
 * Every file is checked by its real content, not its extension: SVG goes
 * through SvgSanitizer, Lottie JSON is parsed, and binary sources must
 * carry the signature of the format they claim to be.
 */
class StudioUploadService
{
    public function __construct(
        private readonly SvgSanitizer $sanitizer,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function storeGroup(Contributor $contributor, string $typeKey, array $files, ?int $draftId = null): Asset
    {
        $type = StudioTypes::get($typeKey);
        $assetType = AssetType::where('slug', $type['asset_type'])->first();
        if (! $assetType) {
            throw new RuntimeException("{$type['label']} uploads are not configured yet.");
        }

        $allowed = array_merge($type['required'], $type['optional']);
        $byExt = [];
        $stem = null;
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                throw new RuntimeException('A file did not upload completely. Please try again.');
            }
            $name = $file->getClientOriginalName();
            $ext = strtolower($file->getClientOriginalExtension());
            if (! in_array($ext, $allowed, true)) {
                throw new RuntimeException("{$name}: {$type['label']} accept only ".strtoupper(implode(', ', $allowed)).'.');
            }
            if ($file->getSize() > $type['max_mb'] * 1048576) {
                throw new RuntimeException("{$name} is larger than {$type['max_mb']} MB.");
            }
            if (isset($byExt[$ext])) {
                throw new RuntimeException("Two .{$ext} files share the name “{$name}”.");
            }
            $byExt[$ext] = $file;
            $stem ??= pathinfo($name, PATHINFO_FILENAME);
        }

        $primaryExt = $type['required'][0];
        if (! isset($byExt[$primaryExt])) {
            throw new RuntimeException("“{$stem}” needs a .{$primaryExt} file — it is required for every {$type['singular']}.");
        }

        // ---- validate content + build the preview
        $primary = (string) file_get_contents($byExt[$primaryExt]->getRealPath());
        $previewBody = null;
        $previewExt = $primaryExt;
        $previewType = 'medium';
        $notes = [];

        if ($primaryExt === 'svg') {
            $result = $this->sanitizer->sanitize($primary);
            if ($result['svg'] === '') {
                throw new RuntimeException("{$stem}.svg could not be read as an SVG.");
            }
            $notes = $result['removals'];
            $primary = '<?xml version="1.0" encoding="UTF-8"?>'."\n".$result['svg'];
            $previewBody = $primary;
        } elseif ($primaryExt === 'json') {
            $data = json_decode($primary, true);
            if (! is_array($data) || ! isset($data['v'], $data['layers'])) {
                throw new RuntimeException("{$stem}.json is not a Lottie animation.");
            }
            foreach ((array) ($data['assets'] ?? []) as $a) {
                if (! empty($a['p']) && (str_starts_with((string) $a['p'], 'data:image') || preg_match('/\.(png|jpe?g|webp|gif)$/i', (string) $a['p']))) {
                    throw new RuntimeException("{$stem}.json contains images. Please upload a Lottie without embedded images.");
                }
            }
            $frames = (float) ($data['op'] ?? 0) - (float) ($data['ip'] ?? 0);
            if ($frames > 500) {
                throw new RuntimeException("{$stem}.json has ".(int) $frames.' frames — the limit is 500.');
            }
            $previewBody = $primary;
            $previewType = 'lottie';
        } elseif ($primaryExt === 'png') {
            if (! str_starts_with($primary, "\x89PNG\r\n\x1a\n")) {
                throw new RuntimeException("{$stem}.png is not a PNG image.");
            }
            [$previewBody, $previewExt] = $this->pngPreview($byExt['png']->getRealPath());
        }

        foreach ($byExt as $ext => $file) {
            if ($ext !== $primaryExt) {
                $this->checkSignature($file, $ext);
            }
        }

        $title = Names::assetTitle('', $stem.'.'.$primaryExt);

        $written = [];
        try {
            return DB::transaction(function () use ($contributor, $assetType, $typeKey, $type, $byExt, $primaryExt, $primary, $previewBody, $previewExt, $previewType, $notes, $title, $stem, $draftId, &$written) {
                $asset = Asset::create([
                    'contributor_id' => $contributor->id,
                    'asset_type_id' => $assetType->id,
                    'title' => $title,
                    'slug' => $this->uniqueSlug($title),
                    'description' => null,
                    'is_premium' => false,
                    'price' => 0,
                    'is_animated' => $typeKey === 'lottie',
                    'is_3d' => $typeKey === '3d',
                    // Uploads land in a draft (pack details are added before
                    // Submit); contributors can never self-publish.
                    'status' => $draftId ? 'draft' : 'submitted',
                    'metadata' => array_filter([
                        'upload_source' => 'studio',
                        'studio_draft_id' => $draftId,
                        'original_name' => $stem,
                        'formats' => array_keys($byExt),
                        'sanitisation' => $notes ?: null,
                    ]),
                ]);

                $slug = $asset->slug;
                $private = Storage::disk('private');

                if (count($byExt) === 1) {
                    $path = "assets/{$asset->id}/{$slug}.{$primaryExt}";
                    $private->put($path, $primary);
                    $written[] = ['private', $path];
                    $source = ['path' => $path, 'name' => "{$slug}.{$primaryExt}", 'ext' => $primaryExt,
                        'mime' => match ($primaryExt) { 'svg' => 'image/svg+xml', 'json' => 'application/json', default => 'image/png' },
                        'size' => strlen($primary), 'sha' => hash('sha256', $primary)];
                } else {
                    $tmp = tempnam(sys_get_temp_dir(), 'ico');
                    $zip = new ZipArchive();
                    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                        throw new RuntimeException('Could not package the files.');
                    }
                    foreach ($byExt as $ext => $file) {
                        $ext === $primaryExt && $primaryExt === 'svg'
                            ? $zip->addFromString("{$slug}.svg", $primary)
                            : $zip->addFile($file->getRealPath(), "{$slug}.{$ext}");
                    }
                    $zip->close();
                    $path = "assets/{$asset->id}/{$slug}.zip";
                    $stream = fopen($tmp, 'rb');
                    $private->writeStream($path, $stream);
                    is_resource($stream) && fclose($stream);
                    $written[] = ['private', $path];
                    $source = ['path' => $path, 'name' => "{$slug}.zip", 'ext' => 'zip', 'mime' => 'application/zip',
                        'size' => filesize($tmp), 'sha' => hash_file('sha256', $tmp)];
                    @unlink($tmp);
                }

                AssetFile::create([
                    'asset_id' => $asset->id,
                    'disk' => 'private',
                    'path' => $source['path'],
                    'original_name' => $source['name'],
                    'extension' => $source['ext'],
                    'mime_type' => $source['mime'],
                    'size_bytes' => $source['size'],
                    'checksum' => $source['sha'],
                    'is_source' => true,
                    // Every file was content-checked above: SVG sanitised,
                    // Lottie parsed, binaries matched to their signature.
                    'scan_status' => 'clean',
                    'scanned_at' => now(),
                ]);

                $previewPath = "previews/{$asset->id}/preview.{$previewExt}";
                Storage::disk('public')->put($previewPath, $previewBody);
                $written[] = ['public', $previewPath];
                AssetPreview::create([
                    'asset_id' => $asset->id,
                    'type' => $previewType,
                    'disk' => 'public',
                    'path' => $previewPath,
                    'width' => $typeKey === '3d' ? 512 : 240,
                    'height' => $typeKey === '3d' ? 512 : 240,
                    'size_bytes' => strlen($previewBody),
                    'is_primary' => true,
                ]);

                $this->syncTags($asset, $typeKey, $title);

                return $asset;
            });
        } catch (\Throwable $e) {
            foreach ($written as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }
    }

    /**
     * Adds an optional source format to an existing draft asset, e.g. the
     * AEP next to a Lottie JSON. The private source becomes a ZIP of all formats.
     */
    public function addFormat(Asset $asset, string $typeKey, UploadedFile $file): array
    {
        $type = StudioTypes::get($typeKey);
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $type['optional'], true)) {
            throw new RuntimeException('Only '.strtoupper(implode(', ', $type['optional'])).' can be added here.');
        }
        if ($file->getSize() > $type['max_mb'] * 1048576) {
            throw new RuntimeException("File is larger than {$type['max_mb']} MB.");
        }
        $this->checkSignature($file, $ext);

        $formats = (array) ($asset->metadata['formats'] ?? []);
        if (in_array($ext, $formats, true)) {
            throw new RuntimeException(strtoupper($ext).' is already attached.');
        }

        $source = AssetFile::where('asset_id', $asset->id)->where('is_source', true)->orderBy('id')->first();
        if (! $source) {
            throw new RuntimeException('The original file is missing.');
        }
        $private = Storage::disk('private');
        $slug = $asset->slug;
        $tmp = tempnam(sys_get_temp_dir(), 'ico');
        $zip = new ZipArchive();
        if ($source->extension === 'zip') {
            file_put_contents($tmp, $private->get($source->path));
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('Could not open the existing package.');
            }
        } else {
            $zip->open($tmp, ZipArchive::OVERWRITE);
            $zip->addFromString("{$slug}.{$source->extension}", (string) $private->get($source->path));
        }
        $zip->addFile($file->getRealPath(), "{$slug}.{$ext}");
        $zip->close();

        $path = "assets/{$asset->id}/{$slug}.zip";
        $stream = fopen($tmp, 'rb');
        $private->writeStream($path, $stream);
        is_resource($stream) && fclose($stream);
        if ($source->path !== $path) {
            $private->delete($source->path);
        }
        $source->update([
            'path' => $path, 'original_name' => "{$slug}.zip", 'extension' => 'zip', 'mime_type' => 'application/zip',
            'size_bytes' => filesize($tmp), 'checksum' => hash_file('sha256', $tmp), 'scan_status' => 'clean', 'scanned_at' => now(),
        ]);
        @unlink($tmp);

        $meta = (array) $asset->metadata;
        $meta['formats'] = array_values(array_unique(array_merge($formats, [$ext])));
        $asset->update(['metadata' => $meta]);

        return $meta['formats'];
    }

    /** Binary source files must start with the signature of their format. */
    public function checkSignature(UploadedFile $file, string $ext): void
    {
        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 64);
        $ok = match ($ext) {
            'eps' => str_starts_with($head, '%!PS') || str_starts_with($head, "\xC5\xD0\xD3\xC6"),
            'ai', 'pdf' => str_starts_with($head, '%PDF') || str_starts_with($head, '%!PS'),
            'blend' => str_starts_with($head, 'BLENDER'),
            'glb' => str_starts_with($head, 'glTF'),
            'fbx' => str_starts_with($head, 'Kaydara FBX Binary') || str_contains($head, 'FBX'),
            'obj' => (bool) preg_match('/^[\x09\x0A\x0D\x20-\x7E]+$/', $head),
            'aep' => str_starts_with($head, 'RIFX'),
            'lottie', 'zip' => str_starts_with($head, "PK\x03\x04"),
            default => false,
        };
        if (! $ok) {
            throw new RuntimeException($file->getClientOriginalName().' does not look like a real .'.$ext.' file.');
        }
    }

    /** @return array{0:string,1:string} body + extension of a 512px preview */
    private function pngPreview(string $path): array
    {
        $src = function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false;
        if (! $src) {
            return [(string) file_get_contents($path), 'png'];
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, 512 / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        $ext = function_exists('imagewebp') ? 'webp' : 'png';
        $ext === 'webp' ? imagewebp($dst, null, 84) : imagepng($dst, null, 7);
        $body = (string) ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return [$body, $ext];
    }

    private function syncTags(Asset $asset, string $typeKey, string $title): void
    {
        $names = $typeKey === 'lottie'
            ? array_merge(array_filter(preg_split('/[\s\-]+/', strtolower($title)), fn ($w) => strlen($w) >= 2), ['lottie', 'animation', 'animated'])
            : Names::tags($typeKey, $title, '');

        $ids = [];
        foreach (array_slice(array_unique($names), 0, 15) as $name) {
            $name = Str::lower(trim((string) $name));
            if ($name === '' || Str::slug($name) === '') {
                continue;
            }
            $ids[] = Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
        }
        $asset->tags()->sync(array_unique($ids));
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
}
