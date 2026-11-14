<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContributorTypeAccess;
use App\Services\Contributor\StudioTypes;
use Illuminate\Http\Request;

/**
 * Upload-access requests: a contributor asks to upload Icons,
 * Illustrations or 3D by sending a portfolio URL; staff approve or reject.
 */
class AccessRequestController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('contributors.view'), 403);
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';

        return view('admin.access.index', [
            'requests' => ContributorTypeAccess::with(['contributor.user', 'reviewer'])
                ->where('status', $status)->latest('updated_at')->paginate(25)->withQueryString(),
            'counts' => ContributorTypeAccess::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'status' => $status,
            'types' => StudioTypes::all(),
        ]);
    }

    public function approve(Request $request, ContributorTypeAccess $access)
    {
        abort_unless($request->user()?->can('contributors.approve'), 403);
        $access->update(['status' => 'approved', 'reviewer_notes' => $request->input('notes'), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('status', ($access->contributor?->display_name ?? 'Contributor').' can now upload '.(StudioTypes::TYPES[$access->type_key]['label'] ?? $access->type_key).'.');
    }

    public function reject(Request $request, ContributorTypeAccess $access)
    {
        abort_unless($request->user()?->can('contributors.reject'), 403);
        $validated = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:1000']], ['notes.required' => 'Please give the contributor a reason.']);
        $access->update(['status' => 'rejected', 'reviewer_notes' => $validated['notes'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('status', 'Request rejected.');
    }
}
