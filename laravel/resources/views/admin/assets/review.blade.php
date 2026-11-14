@extends('layouts.admin')

@section('title', 'Asset review')
@section('subtitle', 'Review contributor submissions before they go live.')

@php
    $statusTabs = [
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'changes_requested' => 'Changes requested',
        'published' => 'Published',
        'rejected' => 'Rejected',
    ];
    $tabLabel = $statusTabs[$status] ?? ucfirst(str_replace('_', ' ', $status));
    $canApprove = $status !== 'published';
    $canReject = $status !== 'rejected';
    $packLabel = $pack === 'none' ? 'Items without a pack' : ($currentPack?->title ?? 'Pack #'.$pack);
@endphp

@section('content')

<nav class="mb-3" aria-label="Filter submissions by status">
    <ul class="nav nav-pills flex-wrap gap-1">
        @foreach ($statusTabs as $value => $label)
            <li class="nav-item">
                <a class="nav-link {{ $status === $value ? 'active' : '' }}"
                   href="{{ route('admin.assets.review', ['status' => $value, 'view' => $view === 'packs' ? 'packs' : null]) }}">
                    {{ $label }} <span class="badge bg-secondary bg-opacity-50 ms-1">{{ $counts[$value] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>

{{-- View switch: single items or grouped by pack --}}
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="View">
        <a class="btn {{ $view === 'items' && $pack === null ? 'btn-ico-primary' : 'btn-ico-outline' }}" href="{{ route('admin.assets.review', ['status' => $status]) }}"><i class="bi bi-grid-3x3-gap me-1"></i> Items</a>
        <a class="btn {{ $view === 'packs' || $pack !== null ? 'btn-ico-primary' : 'btn-ico-outline' }}" href="{{ route('admin.assets.review', ['status' => $status, 'view' => 'packs']) }}"><i class="bi bi-collection me-1"></i> By pack</a>
    </div>
    @if ($pack !== null)
        <span class="small text-muted ms-1">
            <a href="{{ route('admin.assets.review', ['status' => $status, 'view' => 'packs']) }}"><i class="bi bi-chevron-left"></i> All packs</a>
            &middot; <strong style="color:var(--ico-heading)">{{ $packLabel }}</strong> &middot; {{ number_format($matching) }} {{ strtolower($tabLabel) }}
        </span>
    @endif
</div>

{{-- One hidden form drives every bulk action on this page. --}}
<form id="bulkForm" method="POST" action="{{ route('admin.assets.bulk') }}" class="d-none">
    @csrf
    <input type="hidden" name="action" value="approve" data-bulk-action>
    <input type="hidden" name="scope" value="selected" data-bulk-scope>
    <input type="hidden" name="status" value="{{ $status }}" data-bulk-status>
    <input type="hidden" name="pack" value="{{ $pack ?? '' }}" data-bulk-pack>
    <input type="hidden" name="reason" value="" data-bulk-reason>
</form>

@if ($view === 'packs')
    {{-- ===================================================== By pack ====== --}}
    @if ($packs->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
            <h2 class="h6">Nothing here</h2>
            <p class="text-muted small mb-0">There are no submissions with this status.</p>
        </div>
    @else
        <p class="small text-muted mb-3">{{ number_format($matching) }} {{ strtolower($tabLabel) }} items in {{ number_format($packs->total()) }} {{ Str::plural('group', $packs->total()) }}. Publishing a pack publishes every {{ strtolower($tabLabel) }} item in it — the pack goes live with them.</p>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
            @foreach ($packs as $row)
                @php
                    $key = $row->pack_id ?: 'none';
                    $name = $row->pack_id ? ($row->pack_model?->title ?? 'Pack #'.$row->pack_id) : 'Items without a pack';
                    $cover = $row->first_asset?->previews->first();
                @endphp
                <div class="col">
                    <article class="ico-card h-100 d-flex flex-column">
                        <a class="asset-tile__media" href="{{ route('admin.assets.review', ['status' => $status, 'pack' => $key]) }}" aria-label="Open {{ $name }}">
                            @if ($cover)
                                @include('partials.asset-preview', ['preview' => $cover, 'alt' => $name, 'size' => 240])
                            @else
                                <div class="d-grid place-items-center text-muted" aria-hidden="true"><i class="bi bi-collection fs-1 opacity-25"></i></div>
                            @endif
                            <span class="position-absolute bottom-0 start-0 m-2 badge-free">{{ $row->total }} {{ Str::plural('item', $row->total) }}</span>
                        </a>
                        <div class="asset-tile__body flex-grow-1 d-flex flex-column">
                            <h3 class="asset-tile__title">{{ $name }}</h3>
                            <p class="asset-tile__meta mb-3">{{ $row->total }} {{ strtolower($tabLabel) }} &middot; <a href="{{ route('admin.assets.review', ['status' => $status, 'pack' => $key]) }}">View items</a></p>
                            <div class="mt-auto d-flex flex-wrap gap-1">
                                @if ($canApprove)
                                    <button type="button" class="btn btn-ico-primary btn-sm" data-bulk-go="approve" data-scope="all" data-pack="{{ $key }}" data-count="{{ $row->total }}" data-name="{{ $name }}">Publish pack</button>
                                @endif
                                @if ($canReject)
                                    <button type="button" class="btn btn-ico-outline btn-sm" data-bulk-go="reject" data-scope="all" data-pack="{{ $key }}" data-count="{{ $row->total }}" data-name="{{ $name }}">Reject pack</button>
                                @endif
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $packs->links() }}</div>
    @endif
@else
    {{-- ===================================================== Items ======== --}}
    @if ($assets->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
            <h2 class="h6">Nothing awaiting review</h2>
            <p class="text-muted small mb-0">There are no submissions with this status.</p>
        </div>
    @else
        @if ($canApprove || $canReject)
            <div class="ico-card px-3 py-2 mb-3 d-flex flex-wrap align-items-center gap-2" data-bulk-bar
                 style="position:sticky;top:.75rem;z-index:20">
                <label class="d-inline-flex align-items-center gap-2 m-0 fw-semibold small" style="cursor:pointer">
                    <input class="form-check-input m-0" type="checkbox" data-pick-all aria-label="Select all on this page"> Select all
                </label>
                <span class="small text-muted" data-pick-count>0 selected</span>
                @if ($matching > $assets->count())
                    <button type="button" class="btn btn-link btn-sm p-0 small" data-pick-everything hidden>
                        Select all {{ number_format($matching) }} in {{ $pack !== null ? $packLabel : $tabLabel }}
                    </button>
                    <span class="small fw-semibold" data-everything-note hidden style="color:var(--ico-primary)">
                        All {{ number_format($matching) }} selected &middot; <button type="button" class="btn btn-link btn-sm p-0 small align-baseline" data-pick-clear>Clear</button>
                    </span>
                @endif
                <div class="ms-auto d-flex gap-1">
                    @if ($canApprove)
                        <button type="button" class="btn btn-ico-primary btn-sm" data-bulk-go="approve" data-scope="selected" disabled><i class="bi bi-check2-all me-1"></i> Publish selected</button>
                    @endif
                    @if ($canReject)
                        <button type="button" class="btn btn-ico-outline btn-sm" data-bulk-go="reject" data-scope="selected" disabled><i class="bi bi-x-lg me-1"></i> Reject selected</button>
                    @endif
                </div>
            </div>
        @endif

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
            @foreach ($assets as $asset)
                <div class="col">
                    <article class="ico-card h-100 d-flex flex-column" data-pick-card>
                        <div class="asset-tile__media">
                            @php $preview = $asset->previews->first(); @endphp
                            @if ($preview)
                                @include('partials.asset-preview', ['preview' => $preview, 'alt' => $asset->title, 'size' => 240])
                            @else
                                <div class="d-grid place-items-center text-muted" aria-hidden="true">
                                    <i class="bi bi-image fs-1 opacity-25"></i>
                                </div>
                            @endif

                            @if ($canApprove || $canReject)
                                <label class="position-absolute top-0 start-0 m-2 d-grid place-items-center"
                                       style="width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.92);box-shadow:0 2px 8px rgba(0,0,0,.18);cursor:pointer"
                                       title="Select">
                                    <input class="form-check-input m-0" type="checkbox" name="ids[]" value="{{ $asset->id }}" form="bulkForm" data-pick
                                           style="width:18px;height:18px" aria-label="Select {{ $asset->title }}">
                                </label>
                            @endif

                            <span class="position-absolute bottom-0 start-0 m-2">
                                @if ($asset->is_premium)
                                    <span class="badge-premium">Premium &middot; ${{ number_format((float) $asset->price, 2) }}</span>
                                @else
                                    <span class="badge-free">Free</span>
                                @endif
                            </span>
                        </div>

                        <div class="asset-tile__body flex-grow-1 d-flex flex-column">
                            <h3 class="asset-tile__title">{{ $asset->title }}</h3>
                            <p class="asset-tile__meta mb-3">
                                {{ $asset->contributor?->display_name ?? 'Unknown contributor' }}
                                &middot; {{ $asset->assetType?->name ?? 'Unknown type' }}
                            </p>

                            <div class="mt-auto d-flex flex-wrap gap-1">
                                @if ($canApprove)
                                    <form method="POST" action="{{ route('admin.assets.approve', $asset) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-ico-primary btn-sm">Approve &amp; publish</button>
                                    </form>
                                @endif

                                <button type="button" class="btn btn-ico-outline btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#changes-asset-{{ $asset->id }}">
                                    Request changes
                                </button>

                                @if ($canReject)
                                    <button type="button" class="btn btn-ico-outline btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#reject-asset-{{ $asset->id }}">
                                        Reject
                                    </button>
                                @endif
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $assets->links() }}
        </div>

        {{-- Request-changes / reject modals, one pair per asset. --}}
        @foreach ($assets as $asset)
            <div class="modal fade" id="changes-asset-{{ $asset->id }}" tabindex="-1"
                 aria-labelledby="changes-asset-{{ $asset->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.assets.changes', $asset) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="changes-asset-{{ $asset->id }}-label">
                                    Request changes to &ldquo;{{ $asset->title }}&rdquo;
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label for="changes-feedback-{{ $asset->id }}" class="form-label small fw-semibold">Feedback</label>
                                <textarea id="changes-feedback-{{ $asset->id }}" name="feedback" rows="4"
                                          class="form-control" required minlength="10"
                                          aria-describedby="changes-feedback-{{ $asset->id }}-help"></textarea>
                                <div id="changes-feedback-{{ $asset->id }}-help" class="form-text small">
                                    Shown to the contributor. At least 10 characters.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-ico-primary btn-sm">Request changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="reject-asset-{{ $asset->id }}" tabindex="-1"
                 aria-labelledby="reject-asset-{{ $asset->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.assets.reject', $asset) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="reject-asset-{{ $asset->id }}-label">
                                    Reject &ldquo;{{ $asset->title }}&rdquo;
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <label for="reject-reason-{{ $asset->id }}" class="form-label small fw-semibold">Reason</label>
                                <textarea id="reject-reason-{{ $asset->id }}" name="reason" rows="4"
                                          class="form-control" required minlength="10"
                                          aria-describedby="reject-reason-{{ $asset->id }}-help"></textarea>
                                <div id="reject-reason-{{ $asset->id }}-help" class="form-text small">
                                    Shown to the contributor. At least 10 characters.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endif

{{-- Bulk confirm (publish or reject) --}}
<div class="modal fade" id="bulkModal" tabindex="-1" aria-labelledby="bulkModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="bulkModalTitle" data-bulk-title>Publish</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" data-bulk-text></p>
                <div data-bulk-reason-wrap hidden>
                    <label for="bulkReason" class="form-label small fw-semibold">Reason (sent to the contributor for every item)</label>
                    <textarea id="bulkReason" rows="4" class="form-control" minlength="10" maxlength="1000"></textarea>
                    <div class="form-text small">At least 10 characters.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm" data-bulk-confirm>Confirm</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('bulkForm');
    if (!form) { return; }
    var picks = Array.prototype.slice.call(document.querySelectorAll('[data-pick]'));
    var all = document.querySelector('[data-pick-all]');
    var countEl = document.querySelector('[data-pick-count]');
    var everyBtn = document.querySelector('[data-pick-everything]');
    var everyNote = document.querySelector('[data-everything-note]');
    var clearBtn = document.querySelector('[data-pick-clear]');
    var selButtons = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-go][data-scope="selected"]'));
    var matching = {{ (int) $matching }};
    var everything = false;
    var modalEl = document.getElementById('bulkModal');
    var pending = null;

    function picked() { return picks.filter(function (p) { return p.checked; }); }
    function refresh() {
        var n = picked().length;
        if (all) { all.checked = n > 0 && n === picks.length; all.indeterminate = n > 0 && n < picks.length; }
        if (everything && n !== picks.length) { everything = false; }
        if (countEl) { countEl.textContent = (everything ? matching : n) + ' selected'; }
        if (everyBtn) { everyBtn.hidden = everything || n !== picks.length || n === 0; }
        if (everyNote) { everyNote.hidden = !everything; }
        selButtons.forEach(function (b) { b.disabled = n === 0; });
        picks.forEach(function (p) {
            var card = p.closest('[data-pick-card]');
            if (card) { card.style.outline = p.checked ? '2px solid var(--ico-primary, #3cf7a2)' : ''; card.style.outlineOffset = '-2px'; }
        });
    }
    picks.forEach(function (p) { p.addEventListener('change', refresh); });
    if (all) { all.addEventListener('change', function () { picks.forEach(function (p) { p.checked = all.checked; }); everything = false; refresh(); }); }
    if (everyBtn) { everyBtn.addEventListener('click', function () { everything = true; refresh(); }); }
    if (clearBtn) { clearBtn.addEventListener('click', function () { everything = false; picks.forEach(function (p) { p.checked = false; }); refresh(); }); }

    function openModal(opts) {
        pending = opts;
        var approve = opts.action === 'approve';
        modalEl.querySelector('[data-bulk-title]').textContent = approve ? 'Publish ' + opts.count + ' ' + (opts.count === 1 ? 'item' : 'items') : 'Reject ' + opts.count + ' ' + (opts.count === 1 ? 'item' : 'items');
        modalEl.querySelector('[data-bulk-text]').textContent = approve
            ? (opts.name ? 'Publish every item in “' + opts.name + '”? They go live in the store straight away.' : 'Publish the selected items? They go live in the store straight away.')
            : (opts.name ? 'Reject every item in “' + opts.name + '”?' : 'Reject the selected items?');
        modalEl.querySelector('[data-bulk-reason-wrap]').hidden = approve;
        var go = modalEl.querySelector('[data-bulk-confirm]');
        go.className = 'btn btn-sm ' + (approve ? 'btn-ico-primary' : 'btn-danger');
        go.textContent = approve ? 'Publish' : 'Reject';
        go.disabled = false;
        if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance(modalEl).show(); }
        else if (approve ? confirm(modalEl.querySelector('[data-bulk-text]').textContent) : false) { submit(); }
    }
    function submit() {
        var o = pending; if (!o) { return; }
        if (o.action === 'reject') {
            var r = document.getElementById('bulkReason').value.trim();
            if (r.length < 10) { document.getElementById('bulkReason').classList.add('is-invalid'); document.getElementById('bulkReason').focus(); return; }
            form.querySelector('[data-bulk-reason]').value = r;
        }
        form.querySelector('[data-bulk-action]').value = o.action;
        form.querySelector('[data-bulk-scope]').value = o.scope;
        if (o.pack !== undefined) { form.querySelector('[data-bulk-pack]').value = o.pack; }
        if (o.scope === 'all') { picks.forEach(function (p) { p.disabled = true; }); }
        modalEl.querySelector('[data-bulk-confirm]').disabled = true;
        modalEl.querySelector('[data-bulk-confirm]').textContent = 'Working…';
        form.submit();
    }
    modalEl.querySelector('[data-bulk-confirm]').addEventListener('click', submit);
    document.getElementById('bulkReason').addEventListener('input', function () { this.classList.remove('is-invalid'); });

    document.querySelectorAll('[data-bulk-go]').forEach(function (b) {
        b.addEventListener('click', function () {
            var scope = b.getAttribute('data-scope');
            var opts = { action: b.getAttribute('data-bulk-go'), scope: scope, name: b.getAttribute('data-name') || '' };
            if (scope === 'all') {
                // A whole pack from the "By pack" view.
                opts.pack = b.getAttribute('data-pack');
                opts.count = parseInt(b.getAttribute('data-count'), 10) || 0;
            } else if (everything) {
                // "Select all N in this tab/pack": let the server pick them.
                opts.scope = 'all';
                opts.count = matching;
            } else {
                opts.count = picked().length;
                if (!opts.count) { return; }
            }
            openModal(opts);
        });
    });
    refresh();
})();
</script>
@endpush
