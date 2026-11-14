<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Contributor;
use App\Models\Pack;
use App\Services\Contributor\StudioTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public contributor store page: /contributors/{slug}.
 *
 * Shows the contributor's published work, one tab per asset type, either
 * as packs (default) or as individual assets.
 */
class ContributorProfileController extends Controller
{
    public function __invoke(Request $request, string $slug)
    {
        $contributor = Contributor::where('slug', $slug)->where('status', 'approved')->firstOrFail();

        $counts = DB::table('assets')->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->where('assets.contributor_id', $contributor->id)->where('assets.status', 'published')->whereNull('assets.deleted_at')
            ->selectRaw('asset_types.slug, COUNT(*) AS n')->groupBy('asset_types.slug')->pluck('n', 'slug');

        $types = [];
        foreach (StudioTypes::all() as $key => $t) {
            if (($counts[$t['asset_type']] ?? 0) > 0) {
                $types[$key] = $t + ['count' => (int) $counts[$t['asset_type']]];
            }
        }

        $tab = $request->query('type');
        if (! isset($types[$tab])) {
            $tab = array_key_first($types);
        }
        $view = $request->query('view') === 'individuals' ? 'individuals' : 'packs';
        $sort = $request->query('sort') === 'popular' ? 'popular' : 'recent';

        $assets = null;
        $packs = null;
        if ($tab) {
            $typeId = DB::table('asset_types')->where('slug', $types[$tab]['asset_type'])->value('id');
            $query = Asset::published()->where('contributor_id', $contributor->id)->where('asset_type_id', $typeId);

            if ($view === 'packs') {
                $packs = Pack::whereIn('id', (clone $query)->whereNotNull('pack_id')->distinct()->pluck('pack_id'))
                    ->where('status', 'published')
                    ->withCount(['assets as published_assets_count' => fn ($a) => $a->where('status', 'published')])
                    ->when($sort === 'popular', fn ($p) => $p->orderByDesc('downloads_count'), fn ($p) => $p->orderByDesc('published_at'))
                    ->paginate(24)->withQueryString();
                $packs->getCollection()->each(fn ($pack) => $pack->setRelation('coverAssets',
                    $pack->assets()->where('status', 'published')->with(['previews', 'assetType'])->orderBy('id')->limit(4)->get()));
            } else {
                $assets = $query->with('previews')
                    ->when($sort === 'popular', fn ($q) => $q->orderByDesc('downloads_count'), fn ($q) => $q->orderByDesc('published_at'))
                    ->paginate(48)->withQueryString();
            }
        }

        return view('contributors.show', [
            'contributor' => $contributor,
            'types' => $types,
            'tab' => $tab,
            'view' => $view,
            'sort' => $sort,
            'packs' => $packs,
            'assets' => $assets,
            'isOwner' => $request->user()?->contributor?->id === $contributor->id,
        ]);
    }
}
