<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Tag;
use App\Support\DriveLibraryNames as Names;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Strips pack codes and version tags from asset titles, so
 * "Pack20 50 Cone V5" becomes "Cone" (standing rule: an asset is named
 * after what it shows). The new slug follows the new title and the old
 * slug is kept in metadata->old_slug, so existing links still redirect.
 * Junk tags left by the old names ("pack20", "v5", "50") are detached.
 *
 *   php artisan iconors:clean-titles --dry-run
 *   php artisan iconors:clean-titles
 */
class CleanAssetTitles extends Command
{
    protected $signature = 'iconors:clean-titles {--dry-run : Show what would change without writing anything}';

    protected $description = 'Remove pack codes and version tags from asset titles (keeps old links working)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = 0;
        $tagsRemoved = 0;

        $junkTag = fn (string $name) => (bool) preg_match('/^((pack|set)\s?\d+|v\d+(\.\d+)*|\d+)$/i', trim($name));

        Asset::withTrashed()
            ->where(function ($q) {
                $q->where('title', 'like', '%pack%')
                    ->orWhere('title', 'like', '%set%')
                    ->orWhere('title', 'like', '% v%');
            })
            ->orderBy('id')
            ->chunkById(200, function ($assets) use ($dry, $junkTag, &$changed, &$tagsRemoved) {
                foreach ($assets as $asset) {
                    $clean = Names::cleanTitle((string) $asset->title);
                    if ($clean === $asset->title) {
                        continue;
                    }
                    $changed++;

                    if ($dry) {
                        $this->line(sprintf('  #%d  %-34s -> %s', $asset->id, Str::limit($asset->title, 32), $clean));

                        continue;
                    }

                    DB::transaction(function () use ($asset, $clean, $junkTag, &$tagsRemoved) {
                        $meta = (array) ($asset->metadata ?? []);
                        // Keep the very first old slug if one is already stored
                        // (earlier renames); otherwise remember the current one.
                        if (empty($meta['old_slug'])) {
                            $meta['old_slug'] = $asset->slug;
                        }
                        $meta['title_before_clean'] = $asset->title;

                        $asset->forceFill([
                            'title' => $clean,
                            'slug' => $this->uniqueSlug($clean, $asset->id),
                            'metadata' => $meta,
                        ])->save();

                        $junk = $asset->tags()->get()->filter(fn ($t) => $junkTag((string) $t->name))->pluck('id');
                        if ($junk->isNotEmpty()) {
                            $asset->tags()->detach($junk->all());
                            $tagsRemoved += $junk->count();
                        }
                        // Make sure the clean name itself is searchable.
                        foreach (array_unique(array_filter([strtolower($clean)])) as $name) {
                            $tag = Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
                            $asset->tags()->syncWithoutDetaching([$tag->id]);
                        }
                    });
                }
            });

        $this->newLine();
        if ($dry) {
            $this->info("{$changed} titles would change. Re-run without --dry-run to apply.");
        } else {
            $this->info("Cleaned {$changed} titles, removed {$tagsRemoved} junk tags.");
        }

        return self::SUCCESS;
    }

    private function uniqueSlug(string $title, int $ignoreId): string
    {
        $base = Str::slug($title) ?: 'asset';
        $slug = $base;
        $i = 1;
        while (Asset::withTrashed()->where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
