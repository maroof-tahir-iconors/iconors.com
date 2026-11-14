<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Services\Catalogue\PackShowcase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Packs — complete sets of animations or icons.
 *
 * The homepage only ever shows packs; these two pages are where its cards
 * lead: a browsable list of every pack, and the pack itself with every
 * asset it contains.
 */
class PackController extends Controller
{
    public function __construct(
        private readonly PackShowcase $showcase,
    ) {}

    /** Browse all packs. Filters live in the query string, so any view is shareable. */
    public function index(Request $request)
    {
        $query = $this->showcase->query();

        $term = $request->query('q');
        $type = $request->query('type');
        $category = $request->query('category');
        $sort = $request->query('sort');

        // Query-string values can arrive as arrays (?type[]=x). Anything that
        // is not a plain string is ignored rather than passed to the builder.
        $term = is_string($term) ? trim($term) : '';
        $type = is_string($type) ? $type : '';
        $category = is_string($category) ? $category : '';
        $sort = is_string($sort) ? $sort : 'popular';

        if ($term !== '') {
            $escaped = addcslashes($term, '%_\\');

            // Grouped so the OR cannot escape the published constraints.
            $query->where(function ($q) use ($escaped) {
                $q->where('packs.title', 'like', "%{$escaped}%")
                    ->orWhere('packs.description', 'like', "%{$escaped}%");
            });
        }

        $activeType = null;

        if ($type !== '') {
            $activeType = AssetType::where('slug', $type)->first();

            $query->whereHas('assets', fn ($q) => $q
                ->published()
                ->whereHas('assetType', fn ($t) => $t->where('slug', $type)));
        }

        $activeCategory = null;

        if ($category !== '') {
            $activeCategory = Category::where('slug', $category)->first();

            $query->whereHas('assets', fn ($q) => $q
                ->published()
                ->whereHas('category', fn ($c) => $c->where('slug', $category)));
        }

        $this->showcase->applySort($query, $sort);

        $packs = $query->paginate(24)->withQueryString();

        $this->showcase->attachCovers($packs->getCollection());

        return view('packs.index', [
            'packs' => $packs,
            'activeType' => $activeType,
            'activeCategory' => $activeCategory,
            'categories' => Category::whereNull('parent_id')
                ->where('is_active', true)
                ->where('assets_count', '>', 0)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'navTypes' => $this->navTypes(),
        ]);
    }

    /** One pack, with every asset inside it. */
    public function show(string $slug)
    {
        $pack = $this->showcase->query()
            ->with('contributor:id,display_name,slug')
            ->where('packs.slug', $slug)
            ->firstOrFail();

        // The pack's own running order where one was recorded (pack_asset),
        // otherwise the order the assets were added in.
        $assets = Asset::published()
            ->select('assets.*')
            ->with(['contributor:id,display_name,slug', 'previews'])
            ->where('assets.pack_id', $pack->id)
            ->leftJoin('pack_asset', function ($join) use ($pack) {
                $join->on('pack_asset.asset_id', '=', 'assets.id')
                    ->where('pack_asset.pack_id', '=', $pack->id);
            })
            ->orderByRaw('CASE WHEN pack_asset.sort_order IS NULL THEN 1 ELSE 0 END')
            ->orderBy('pack_asset.sort_order')
            ->orderBy('assets.id')
            ->paginate(150);

        // A pack is one kind of asset in one category, so the first asset
        // tells us both.
        $lead = $assets->first();
        $lead?->loadMissing(['assetType:id,name,slug', 'category:id,name,slug']);

        $related = new Collection();

        if ($lead?->category_id) {
            $related = $this->showcase->query()
                ->where('packs.id', '!=', $pack->id)
                ->whereHas('assets', fn ($q) => $q
                    ->published()
                    ->where('category_id', $lead->category_id))
                ->inRandomOrder()
                ->limit(4)
                ->get();

            $this->showcase->attachCovers($related);
        }

        return view('packs.show', [
            'pack' => $pack,
            'assets' => $assets,
            'assetType' => $lead?->assetType,
            'category' => $lead?->category,
            'related' => $related,
            'navTypes' => $this->navTypes(),
        ]);
    }

    private function navTypes()
    {
        return AssetType::where('is_active', true)->orderBy('sort_order')->limit(6)->get();
    }
}
