<?php

namespace App\Http\Controllers\Contributor;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\ContributorTypeAccess;
use App\Models\Pack;
use App\Services\Contributor\EarningsService;
use App\Services\Contributor\StudioAchievements;
use App\Services\Contributor\StudioTypes;
use App\Services\Contributor\StudioUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Contributor Studio — the IconScout-style contributor dashboard.
 *
 * Home, per-type workspaces (status cards, Live in Store, uploads),
 * Keyword Insights, Statistics, Payout, Achievements, Shop Details,
 * Like Activities and Help. Earnings and payout rules come from the
 * existing EarningsService, so nothing about money changed.
 */
class StudioController extends Controller
{
    public function __construct(
        private readonly EarningsService $earnings,
        private readonly StudioAchievements $achievements,
    ) {}

    /* ============================================================ home */

    public function home(Request $request)
    {
        $c = $request->user()->contributor;

        return view('contributor.studio.home', [
            'contributor' => $c,
            'types' => StudioTypes::all(),
            'access' => StudioTypes::accessFor($c),
            'liveCounts' => $this->liveCountsByType($c->id),
            'summary' => $this->earnings->summaryFor($c),
            'badges' => $this->achievements->for($c),
        ]);
    }

    /* ======================================================= type pages */

    public function type(Request $request, string $type)
    {
        $c = $request->user()->contributor;
        $t = $this->typeOrFail($type);
        if (! StudioTypes::hasAccess($c, $type)) {
            return redirect()->route('contributor.dashboard')->with('status', "Request access to upload {$t['label']} first.");
        }

        $counts = [];
        $base = $this->typeQuery($c->id, $t);
        foreach (StudioTypes::STATUSES as $key => $s) {
            $counts[$key] = $key === 'deleted'
                ? (clone $base)->onlyTrashed()->count()
                : (clone $base)->whereIn('status', $s['db'])->count();
        }

        return view('contributor.studio.type', ['type' => $t, 'counts' => $counts, 'statuses' => StudioTypes::STATUSES]);
    }

    public function status(Request $request, string $type, string $status)
    {
        $c = $request->user()->contributor;
        $t = $this->typeOrFail($type);
        abort_unless(isset(StudioTypes::STATUSES[$status]), 404);
        abort_unless(StudioTypes::hasAccess($c, $type), 403);
        if ($status === 'draft') {
            return redirect()->route('contributor.drafts', $type);
        }

        $view = $request->query('view', 'packs') === 'individuals' ? 'individuals' : 'packs';
        $sort = (string) $request->query('sort', 'recent');
        $q = trim((string) $request->query('q'));

        $query = $this->typeQuery($c->id, $t)->with('previews');
        $status === 'deleted' ? $query->onlyTrashed() : $query->whereIn('status', StudioTypes::STATUSES[$status]['db']);
        if ($q !== '') {
            $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%');
        }

        $packs = collect();
        if ($status === 'live' && $view === 'packs') {
            $packIds = (clone $query)->whereNotNull('pack_id')->distinct()->pluck('pack_id');
            $packs = Pack::whereIn('id', $packIds)
                ->withCount(['assets as published_assets_count' => fn ($a) => $a->where('status', 'published')])
                ->when($sort === 'title', fn ($p) => $p->orderBy('title'), fn ($p) => $p->orderByDesc('updated_at'))
                ->paginate(24)->withQueryString();
            $packs->getCollection()->each(function ($pack) {
                $pack->setRelation('coverAssets', $pack->assets()->where('status', 'published')
                    ->with(['previews', 'assetType'])->orderBy('id')->limit(4)->get());
            });
        }

        match ($sort) {
            'title' => $query->orderBy('title'),
            'downloads' => $query->orderByDesc('downloads_count'),
            default => $query->orderByDesc('updated_at'),
        };

        return view('contributor.studio.status', [
            'type' => $t,
            'status' => $status,
            'statusMeta' => StudioTypes::STATUSES[$status],
            'view' => $view,
            'packs' => $packs,
            'assets' => ($status === 'live' && $view === 'packs') ? null : $query->paginate(48)->withQueryString(),
            'sort' => $sort,
            'q' => $q,
        ]);
    }

    public function upload(Request $request, string $type)
    {
        $t = $this->typeOrFail($type);
        abort_unless(StudioTypes::hasAccess($request->user()->contributor, $type), 403);
        $this->authorize('create', Asset::class);

        $draft = $request->filled('draft')
            ? \App\Models\StudioDraft::where('id', $request->integer('draft'))->where('contributor_id', $request->user()->contributor->id)
                ->where('type_key', $type)->where('status', 'open')->first()
            : null;

        return view('contributor.studio.upload', ['type' => $t, 'draft' => $draft]);
    }

    /** One asset per request (a file group sharing one name). JSON for the uploader. */
    public function store(Request $request, string $type, StudioUploadService $uploads)
    {
        $t = $this->typeOrFail($type);
        $c = $request->user()->contributor;
        if (! StudioTypes::hasAccess($c, $type) || ! $request->user()->can('create', Asset::class)) {
            return response()->json(['ok' => false, 'message' => 'You do not have upload access for '.$t['label'].'.'], 403);
        }

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:6'],
            'files.*' => ['required', 'file', 'max:'.($t['max_mb'] * 1024)],
            'draft_id' => ['nullable', 'integer'],
        ]);

        $draftId = null;
        if ($request->filled('draft_id')) {
            $draft = \App\Models\StudioDraft::where('id', $request->integer('draft_id'))
                ->where('contributor_id', $c->id)->where('type_key', $type)->where('status', 'open')->first();
            if (! $draft) {
                return response()->json(['ok' => false, 'message' => 'This draft no longer exists. Reload the page.'], 422);
            }
            $draftId = $draft->id;
            $draft->touch();
        }

        try {
            $asset = $uploads->storeGroup($c, $type, $request->file('files'), $draftId);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'title' => $asset->title, 'id' => $asset->id]);
    }

    public function destroy(Request $request, Asset $asset)
    {
        $this->authorize('delete', $asset);
        $asset->delete();

        return back()->with('status', "“{$asset->title}” was deleted.");
    }

    public function requestAccess(Request $request, string $type)
    {
        $t = $this->typeOrFail($type);
        $c = $request->user()->contributor;
        $validated = $request->validate(['portfolio_url' => ['required', 'url', 'max:255']]);

        if (StudioTypes::hasAccess($c, $type)) {
            return back()->with('status', "You already have access to {$t['label']}.");
        }

        ContributorTypeAccess::updateOrCreate(
            ['contributor_id' => $c->id, 'type_key' => $type],
            ['status' => 'pending', 'portfolio_url' => $validated['portfolio_url'], 'reviewer_notes' => null, 'reviewed_by' => null, 'reviewed_at' => null],
        );

        return back()->with('status', "Request sent. You'll be able to upload {$t['label']} once an admin approves your portfolio.");
    }

    /* ================================================= keyword insights */

    public function keywords(Request $request)
    {
        $tab = (string) $request->query('asset', 'all');
        $mode = in_array($request->query('mode'), ['none', 'trending'], true) ? $request->query('mode') : 'top';
        $types = StudioTypes::all();
        $typeSlug = $types[$tab]['asset_type'] ?? null;

        $base = fn () => DB::table('search_queries')
            ->when($typeSlug, fn ($q) => $q->where(function ($w) use ($typeSlug) {
                $w->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(filters, '$.type')) = ?", [$typeSlug]);
            }))
            ->whereRaw('CHAR_LENGTH(query) BETWEEN 2 AND 60');

        $keywords = match ($mode) {
            'none' => $base()->where('is_zero_result', true)->where('created_at', '>=', now()->subDays(90))
                ->selectRaw('LOWER(TRIM(query)) AS term, COUNT(*) AS n')->groupBy('term')->orderByDesc('n')->limit(72)->get(),
            'trending' => DB::query()->fromSub(
                $base()->where('created_at', '>=', now()->subDays(14))
                    ->selectRaw('LOWER(TRIM(query)) AS term, SUM(created_at >= ?) AS recent, SUM(created_at < ?) AS prior', [now()->subDays(7), now()->subDays(7)])
                    ->groupBy('term'), 'k')
                ->where('recent', '>=', 2)->orderByRaw('(recent - prior) DESC')->orderByDesc('recent')->limit(72)->get(),
            default => $base()->where('is_zero_result', false)->where('created_at', '>=', now()->subDays(90))
                ->selectRaw('LOWER(TRIM(query)) AS term, COUNT(*) AS n')->groupBy('term')->orderByDesc('n')->limit(72)->get(),
        };

        return view('contributor.studio.keywords', compact('tab', 'mode', 'types', 'keywords'));
    }

    /* ======================================================= statistics */

    public function salesActivities(Request $request)
    {
        $c = $request->user()->contributor;
        [$from, $to] = $this->range($request, now()->startOfMonth());
        $price = $this->priceFilter($request);

        $rows = DB::table('downloads')
            ->join('assets', 'assets.id', '=', 'downloads.asset_id')
            ->where('downloads.contributor_id', $c->id)
            ->whereBetween('downloads.created_at', [$from, $to])
            ->when($price === 'free', fn ($q) => $q->where('assets.is_premium', false))
            ->when($price === 'premium', fn ($q) => $q->where('assets.is_premium', true))
            ->orderByDesc('downloads.id')
            ->paginate(25, ['downloads.id', 'downloads.access_type', 'downloads.created_at', 'assets.id AS asset_id', 'assets.title', 'assets.slug'])
            ->withQueryString();

        $previews = DB::table('asset_previews')->whereIn('asset_id', $rows->pluck('asset_id'))->where('is_primary', true)->get()->keyBy('asset_id');

        return view('contributor.studio.sales-activities', compact('rows', 'previews', 'from', 'to', 'price'));
    }

    public function salesOverview(Request $request)
    {
        $c = $request->user()->contributor;
        [$from, $to] = $this->range($request, now()->subMonths(5)->startOfMonth());

        $sum = fn (array $sources) => DB::table('contributor_earnings')
            ->where('contributor_id', $c->id)->where('status', '!=', 'reversed')
            ->whereIn('source', $sources)->whereBetween('created_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(net_amount),0) AS total, COUNT(*) AS items')->first();

        $cards = [
            'direct' => $sum(['individual_sale', 'pack_sale']),
            'subscription' => $sum(['subscription_download']),
            'api' => $sum(['api_download']),
        ];
        $grand = DB::table('contributor_earnings')->where('contributor_id', $c->id)->where('status', '!=', 'reversed')
            ->whereBetween('created_at', [$from, $to])->sum('net_amount');

        $monthly = DB::table('contributor_earnings')
            ->where('contributor_id', $c->id)->where('status', '!=', 'reversed')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') AS ym,
                SUM(CASE WHEN source IN ('individual_sale','pack_sale') THEN net_amount ELSE 0 END) AS direct,
                SUM(CASE WHEN source IN ('individual_sale','pack_sale') THEN 1 ELSE 0 END) AS direct_n,
                SUM(CASE WHEN source = 'subscription_download' THEN net_amount ELSE 0 END) AS sub,
                SUM(CASE WHEN source = 'subscription_download' THEN 1 ELSE 0 END) AS sub_n,
                SUM(CASE WHEN source = 'api_download' THEN net_amount ELSE 0 END) AS api,
                SUM(CASE WHEN source = 'api_download' THEN 1 ELSE 0 END) AS api_n,
                SUM(net_amount) AS total")
            ->groupBy('ym')->orderByDesc('ym')->get();

        $downloads = DB::table('downloads')->where('contributor_id', $c->id)->whereBetween('created_at', [$from, $to])
            ->selectRaw('access_type, COUNT(*) AS n')->groupBy('access_type')->pluck('n', 'access_type');

        return view('contributor.studio.sales-overview', compact('cards', 'grand', 'monthly', 'downloads', 'from', 'to'));
    }

    /* =========================================================== payout */

    public function cashout(Request $request)
    {
        $c = $request->user()->contributor;

        return view('contributor.studio.cashout', [
            'summary' => $this->earnings->summaryFor($c),
            'methods' => DB::table('payout_methods')->where('contributor_id', $c->id)->whereNull('deleted_at')->get(),
            'history' => DB::table('payout_requests')
                ->leftJoin('payout_methods', 'payout_methods.id', '=', 'payout_requests.payout_method_id')
                ->where('payout_requests.contributor_id', $c->id)
                ->orderByDesc('payout_requests.id')
                ->get(['payout_requests.*', 'payout_methods.type AS method_type', 'payout_methods.display_hint']),
            'received' => (float) DB::table('payout_requests')->where('contributor_id', $c->id)->where('status', 'completed')->sum('net_amount'),
        ]);
    }

    /* ===================================================== achievements */

    public function achievements(Request $request)
    {
        $c = $request->user()->contributor;

        return view('contributor.studio.achievements', [
            'badges' => $this->achievements->for($c),
            'period' => $request->query('period') === 'monthly' ? 'monthly' : 'all',
            'profileUrl' => route('contributors.show', $c->slug),
        ]);
    }

    public function badge(Request $request, string $key)
    {
        $b = $this->achievements->find($request->user()->contributor, $key);
        abort_unless($b && $b['unlocked'], 404);

        return response(StudioAchievements::svg($b, 512), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="iconors-badge-'.$key.'.svg"',
        ]);
    }

    /* ===================================================== shop details */

    public function shop(Request $request)
    {
        return view('contributor.studio.shop', ['contributor' => $request->user()->contributor]);
    }

    public function updateShop(Request $request)
    {
        $c = $request->user()->contributor;
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'biography' => ['nullable', 'string', 'max:300'],
            'country' => ['nullable', 'string', 'size:2'],
            'website' => ['nullable', 'url', 'max:255'],
            'social' => ['nullable', 'array'],
            'social.*' => ['nullable', 'url', 'max:255'],
            'ai_training_consent' => ['required', 'in:yes,no'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $social = (array) ($c->social_links ?? []);
        foreach (['dribbble', 'facebook', 'behance', 'twitter', 'instagram', 'linkedin'] as $net) {
            $social[$net] = $validated['social'][$net] ?? null;
        }
        $social['ai_training_consent'] = $validated['ai_training_consent'];

        $data = [
            'display_name' => $validated['display_name'],
            'biography' => $validated['biography'] ?? null,
            'country' => isset($validated['country']) ? strtoupper($validated['country']) : null,
            'website' => $validated['website'] ?? null,
            'social_links' => array_filter($social, fn ($v) => $v !== null && $v !== ''),
        ];

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('contributors/avatars', 'public');
            if ($c->avatar && str_starts_with($c->avatar, 'contributors/avatars/')) {
                Storage::disk('public')->delete($c->avatar);
            }
            $data['avatar'] = $path;
        }

        $c->update($data);

        return back()->with('status', 'Shop details saved.');
    }

    /* ===================================================== likes + help */

    public function likes(Request $request)
    {
        $c = $request->user()->contributor;
        [$from, $to] = $this->range($request, now()->subDays(7)->startOfDay());
        $price = $this->priceFilter($request);
        $mode = $request->query('type') === 'pack' ? 'pack' : 'item';

        $base = DB::table('likes')
            ->join('assets', 'assets.id', '=', 'likes.asset_id')
            ->where('assets.contributor_id', $c->id)
            ->whereBetween('likes.created_at', [$from, $to])
            ->when($price === 'free', fn ($q) => $q->where('assets.is_premium', false))
            ->when($price === 'premium', fn ($q) => $q->where('assets.is_premium', true));

        if ($mode === 'pack') {
            $rows = $base->join('packs', 'packs.id', '=', 'assets.pack_id')
                ->selectRaw('packs.id, packs.title, packs.slug, COUNT(*) AS likes, MAX(likes.created_at) AS last_at')
                ->groupBy('packs.id', 'packs.title', 'packs.slug')->orderByDesc('last_at')
                ->paginate(25)->withQueryString();
            $previews = collect();
        } else {
            $rows = $base->orderByDesc('likes.id')
                ->paginate(25, ['likes.id', 'likes.created_at', 'assets.id AS asset_id', 'assets.title', 'assets.slug'])
                ->withQueryString();
            $previews = DB::table('asset_previews')->whereIn('asset_id', $rows->pluck('asset_id'))->where('is_primary', true)->get()->keyBy('asset_id');
        }

        return view('contributor.studio.likes', compact('rows', 'previews', 'from', 'to', 'price', 'mode'));
    }

    public function learn()
    {
        return view('contributor.studio.learn', [
            'sections' => \App\Models\LearningCourse::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()->groupBy('section'),
        ]);
    }

    public function help()
    {
        return view('contributor.studio.help');
    }

    /* ========================================================= helpers */

    private function typeOrFail(string $type): array
    {
        abort_unless(StudioTypes::exists($type), 404);

        return StudioTypes::get($type);
    }

    private function typeQuery(int $contributorId, array $t)
    {
        $typeId = AssetType::where('slug', $t['asset_type'])->value('id') ?? 0;

        return Asset::where('contributor_id', $contributorId)->where('asset_type_id', $typeId);
    }

    private function liveCountsByType(int $contributorId): array
    {
        $rows = DB::table('assets')->join('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->where('assets.contributor_id', $contributorId)->where('assets.status', 'published')->whereNull('assets.deleted_at')
            ->selectRaw('asset_types.slug, COUNT(*) AS n')->groupBy('asset_types.slug')->pluck('n', 'slug');
        $out = [];
        foreach (StudioTypes::TYPES as $key => $t) {
            $out[$key] = (int) ($rows[$t['asset_type']] ?? 0);
        }

        return $out;
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function range(Request $request, Carbon $defaultFrom): array
    {
        try {
            $from = $request->filled('from') ? Carbon::parse($request->query('from'))->startOfDay() : $defaultFrom;
            $to = $request->filled('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            [$from, $to] = [$defaultFrom, now()->endOfDay()];
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function priceFilter(Request $request): string
    {
        return in_array($request->query('price'), ['free', 'premium'], true) ? $request->query('price') : 'all';
    }
}
