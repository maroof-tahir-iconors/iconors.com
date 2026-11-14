<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ContributorApplication;
use App\Models\Pack;
use App\Services\Asset\AssetReviewService;
use App\Services\Contributor\ApplicationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Admin moderation queues — brief §25.
 *
 * Every action here re-checks authorisation server-side. Hiding a button
 * in Blade is presentation, not security (brief §26: "never depend only
 * on hiding buttons").
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly AssetReviewService $assets,
    ) {}

    /* ---------------- Contributor applications ---------------- */

    public function applications(Request $request)
    {
        $this->authorizePermission($request, 'contributors.view');

        return view('admin.applications.index', [
            'applications' => ContributorApplication::with('user')
                ->when($request->query('status', 'pending'), fn ($q, $s) => $q->where('status', $s))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'counts' => ContributorApplication::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approveApplication(Request $request, ContributorApplication $application)
    {
        $this->authorizePermission($request, 'contributors.approve');

        if ($application->status === 'approved') {
            return back()->with('status', 'That application was already approved.');
        }

        $contributor = $this->applications->approve(
            $application,
            $request->user(),
            $request->input('notes'),
        );

        return back()->with('status', "{$contributor->display_name} is now an approved contributor.");
    }

    public function rejectApplication(Request $request, ContributorApplication $application)
    {
        $this->authorizePermission($request, 'contributors.reject');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            // A rejection with no explanation generates a support ticket
            // every time, so the reason is mandatory.
            'reason.required' => 'Please give the applicant a reason.',
        ]);

        $this->applications->reject($application, $request->user(), $validated['reason']);

        return back()->with('status', 'Application rejected.');
    }

    public function requestApplicationInfo(Request $request, ContributorApplication $application)
    {
        $this->authorizePermission($request, 'contributors.approve');

        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->applications->requestMoreInfo($application, $request->user(), $validated['notes']);

        return back()->with('status', 'More information requested.');
    }

    /* ---------------- Asset submissions ---------------- */

    public function assets(Request $request)
    {
        // assets.approve, not assets.view: contributors hold assets.view for
        // their own work, and this queue exposes everyone's unpublished
        // submissions.
        $this->authorizePermission($request, 'assets.approve');

        $status = (string) $request->query('status', 'submitted');
        $pack = $this->packParam($request->query('pack'));
        $view = $request->query('view') === 'packs' && $pack === null ? 'packs' : 'items';

        $base = $this->scopeQuery($status, $pack);

        $packs = null;
        $assets = null;

        if ($view === 'packs') {
            // One row per pack: how many of its items sit in this tab, plus
            // the first item as the cover.
            $packs = (clone $base)
                ->selectRaw('pack_id, COUNT(*) as total, MIN(id) as first_id')
                ->groupBy('pack_id')
                ->orderByDesc('total')
                ->paginate(24)
                ->withQueryString();

            $models = Pack::whereIn('id', $packs->pluck('pack_id')->filter())->get()->keyBy('id');
            $firsts = Asset::with('previews')->whereIn('id', $packs->pluck('first_id'))->get()->keyBy('id');
            foreach ($packs as $row) {
                $row->pack_model = $row->pack_id ? $models->get($row->pack_id) : null;
                $row->first_asset = $firsts->get($row->first_id);
            }
        } else {
            $assets = (clone $base)
                ->with(['contributor', 'assetType', 'previews'])
                ->latest()
                ->paginate(24)
                ->withQueryString();
        }

        return view('admin.assets.review', [
            'assets' => $assets,
            'packs' => $packs,
            'view' => $view,
            'status' => $status,
            'pack' => $pack,
            'currentPack' => is_int($pack) ? Pack::find($pack) : null,
            'matching' => (clone $base)->count(),
            'counts' => Asset::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approveAsset(Request $request, Asset $asset)
    {
        // Policy, not just permission: it also blocks a reviewer from
        // approving their own submission.
        $this->authorize('review', $asset);

        $this->assets->approve($asset, $request->user(), publishNow: true);

        return back()->with('status', "“{$asset->title}” is now published.");
    }

    public function requestAssetChanges(Request $request, Asset $asset)
    {
        $this->authorize('review', $asset);

        $validated = $request->validate([
            'feedback' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->assets->requestChanges($asset, $request->user(), $validated['feedback']);

        return back()->with('status', 'Changes requested.');
    }

    public function rejectAsset(Request $request, Asset $asset)
    {
        $this->authorize('review', $asset);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->assets->reject($asset, $request->user(), $validated['reason']);

        return back()->with('status', 'Asset rejected.');
    }

    /**
     * Bulk approve / reject. Three scopes:
     *   selected — the ticked ids;
     *   all      — everything in the current tab (optionally one pack).
     * Each asset still goes through the same policy check and the same
     * review service as a single click, so nothing bypasses moderation.
     */
    public function bulkAssets(Request $request)
    {
        $this->authorizePermission($request, 'assets.approve');

        $v = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'scope' => ['required', 'in:selected,all'],
            'ids' => ['required_if:scope,selected', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'status' => ['required_if:scope,all', 'nullable', 'string', 'max:40'],
            'pack' => ['nullable', 'string', 'max:20'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'min:10', 'max:1000'],
        ], [
            'ids.required_if' => 'Tick at least one item first.',
            'reason.required_if' => 'Please give the contributor a reason (at least 10 characters).',
        ]);

        $query = $v['scope'] === 'selected'
            ? Asset::query()->whereIn('id', $v['ids'])
            : $this->scopeQuery($v['status'], $this->packParam($v['pack'] ?? null));

        @set_time_limit(600);
        $user = $request->user();
        $done = 0;
        $already = 0;
        $own = 0;
        $myContributor = (int) ($user->contributor?->id ?? 0);

        $query->orderBy('id')->chunkById(100, function ($chunk) use ($v, $user, $myContributor, &$done, &$already, &$own) {
            foreach ($chunk as $asset) {
                if ($v['action'] === 'approve' ? $asset->status === 'published' : $asset->status === 'rejected') {
                    $already++;

                    continue;
                }
                if (! $user->can('review', $asset)) {
                    // The policy forbids reviewing your own uploads.
                    if ($myContributor && $myContributor === (int) $asset->contributor_id) {
                        $own++;
                    } else {
                        $already++;
                    }

                    continue;
                }
                $v['action'] === 'approve'
                    ? $this->assets->approve($asset, $user, publishNow: true)
                    : $this->assets->reject($asset, $user, $v['reason']);
                $done++;
            }
        });

        $verb = $v['action'] === 'approve' ? 'published' : 'rejected';
        $msg = $done === 1 ? "1 asset {$verb}." : "{$done} assets {$verb}.";
        if ($already) {
            $msg .= " {$already} skipped (already {$verb} or not allowed).";
        }
        if ($own) {
            $msg .= " {$own} skipped because they are your own uploads — another admin has to review those.";
        }

        return back()->with('status', $msg);
    }

    /* ---------------- helpers ---------------- */

    /** "none" = assets with no pack, a number = that pack, anything else = no filter. */
    private function packParam($value): int|string|null
    {
        if ($value === 'none') {
            return 'none';
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function scopeQuery(string $status, int|string|null $pack): Builder
    {
        return Asset::query()
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($pack === 'none', fn ($q) => $q->whereNull('pack_id'))
            ->when(is_int($pack), fn ($q) => $q->where('pack_id', $pack));
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can($permission), 403);
    }
}
