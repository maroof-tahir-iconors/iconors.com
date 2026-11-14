<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Contributor;
use App\Services\Catalogue\PackShowcase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    /** How many packs the per-type rail shows, and each category rail. */
    private const TYPE_RAIL_SIZE = 8;

    private const CATEGORY_RAIL_SIZE = 4;

    private const CATEGORY_RAILS = 4;

    /**
     * Homepage.
     *
     * Nothing here is hard-coded (brief §42) — every section reads from
     * the database. The whole payload is cached for 5 minutes because the
     * homepage is the single most-requested route and none of this data
     * is user-specific.
     *
     * The homepage shows PACKS, never loose assets: every card is a complete
     * set that opens its own page listing everything inside it.
     */
    public function __invoke(PackShowcase $showcase)
    {
        $data = Cache::get('home.sections');

        // A payload cached by an older version of this controller has no
        // pack rails. Rebuild instead of serving it, so a deploy can never
        // leave the homepage reading keys that are not there.
        if (! is_array($data) || ! array_key_exists('packRails', $data)) {
            $data = $this->sections($showcase);

            Cache::put('home.sections', $data, 300);
        }

        return view('home', $data + ['navTypes' => $data['assetTypes']->take(6)]);
    }

    private function sections(PackShowcase $showcase): array
    {
        return [
            'packRails' => $this->packRails($showcase),

            'categories' => Category::whereNull('parent_id')
                ->where('is_active', true)
                ->orderByDesc('is_featured')->orderBy('sort_order')
                ->limit(12)->get(),

            'assetTypes' => AssetType::where('is_active', true)
                ->orderBy('sort_order')->get(),

            'contributors' => Contributor::where('status', 'approved')
                ->orderByDesc('downloads_count')->limit(6)->get(),

            /*
             * Scrolling text tickers.
             *
             * Real categories and tags rather than icon tiles: the strip
             * doubles as navigation and tells a visitor at a glance what
             * the catalogue actually covers, which a row of anonymous
             * glyphs cannot.
             */
            'marqueeTop' => DB::table('categories')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('assets_count', '>', 0)
                ->orderByDesc('assets_count')
                ->limit(16)
                ->get(['name', 'slug', 'assets_count'])
                ->map(fn ($c) => [
                    'label' => $c->name,
                    'count' => $c->assets_count,
                    'icon' => 'folder2',
                    'url' => route('assets.index', ['category' => $c->slug]),
                ])->all(),

            'marqueeBottom' => DB::table('tags')
                ->where('usage_count', '>', 0)
                ->orderByDesc('usage_count')
                ->limit(18)
                ->get(['name', 'slug'])
                ->map(fn ($t) => [
                    'label' => Str::title($t->name),
                    'icon' => 'hash',
                    'url' => route('assets.index', ['q' => $t->name]),
                ])->all(),

            'floating' => Asset::published()
                ->where('metadata->is_colourful', true)
                ->whereHas('assetType', fn ($q) => $q->where('slug', 'svg-icon'))
                ->whereDoesntHave('previews', fn ($q) => $q->where('path', 'like', 'demo/%'))
                ->whereHas('previews')
                ->with('previews')
                ->inRandomOrder()
                ->limit(6)
                ->get(),

            'stats' => [
                'assets' => Asset::published()->count(),
                'contributors' => Contributor::where('status', 'approved')->count(),
                'downloads' => (int) Asset::published()->sum('downloads_count'),
            ],
        ];
    }

    /**
     * The pack rails, top to bottom:
     *
     *  1. one rail per asset type that has packs ("Lottie Animations", and
     *     "Icons" as soon as icon packs exist — nothing to switch on);
     *  2. a rail for each of the largest categories.
     *
     * A pack appears once on the page: the category rails skip anything a
     * rail above them already showed, so the homepage surfaces as many
     * different packs as it has room for.
     */
    private function packRails(PackShowcase $showcase): array
    {
        $rails = [];
        $shown = [];

        $types = AssetType::where('is_active', true)->orderBy('sort_order')->get();

        foreach ($types as $type) {
            $inType = fn ($q) => $q->published()->where('asset_type_id', $type->id);

            $packs = $showcase
                ->applyShowcaseOrder($showcase->query()->whereHas('assets', $inType))
                ->limit(self::TYPE_RAIL_SIZE)
                ->get();

            if ($packs->isEmpty()) {
                continue;
            }

            $assetCount = Asset::published()
                ->where('asset_type_id', $type->id)
                ->whereNotNull('pack_id')
                ->count();

            $rails[] = [
                'title' => Str::plural($type->name),
                'meta' => number_format($assetCount).' '.Str::plural('asset', $assetCount),
                'blurb' => 'Complete packs, ready to drop into a project.',
                'url' => route('packs.index', ['type' => $type->slug]),
                'packs' => $packs,
            ];

            $shown = array_merge($shown, $packs->pluck('id')->all());
        }

        $categories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->where('assets_count', '>', 0)
            ->orderByDesc('assets_count')
            ->limit(12)
            ->get();

        $categoryRails = 0;

        foreach ($categories as $category) {
            if ($categoryRails >= self::CATEGORY_RAILS) {
                break;
            }

            $inCategory = fn ($q) => $q->published()->where('category_id', $category->id);

            $packs = $showcase
                ->applyShowcaseOrder(
                    $showcase->query()
                        ->whereHas('assets', $inCategory)
                        ->whereNotIn('packs.id', $shown)
                )
                ->limit(self::CATEGORY_RAIL_SIZE)
                ->get();

            // A rail with a single card looks like a mistake; skip the
            // category and let the next one have the slot.
            if ($packs->count() < 2) {
                continue;
            }

            $packCount = $showcase->query()->whereHas('assets', $inCategory)->count();

            $rails[] = [
                'title' => $category->name.' packs',
                'meta' => number_format($packCount).' '.Str::plural('pack', $packCount),
                'blurb' => 'Every asset in a set, in one place.',
                'url' => route('packs.index', ['category' => $category->slug]),
                'packs' => $packs,
            ];

            $shown = array_merge($shown, $packs->pluck('id')->all());
            $categoryRails++;
        }

        $allPacks = [];

        foreach ($rails as $rail) {
            foreach ($rail['packs'] as $pack) {
                $allPacks[] = $pack;
            }
        }

        $showcase->attachCovers($allPacks);

        return $rails;
    }
}
