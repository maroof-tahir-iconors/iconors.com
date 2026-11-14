@extends('layouts.admin')

@section('title', 'Upload access requests')
@section('subtitle', 'Contributors asking to upload Icons, Illustrations or 3D. Check the portfolio, then approve or reject.')

@section('content')
<ul class="nav nav-pills mb-3 gap-1">
    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $label)
        <li class="nav-item">
            <a class="nav-link {{ $status === $k ? 'active' : '' }}" href="{{ route('admin.access.index', ['status' => $k]) }}">
                {{ $label }} <span class="badge bg-secondary ms-1">{{ $counts[$k] ?? 0 }}</span>
            </a>
        </li>
    @endforeach
</ul>

<div class="ico-card p-3">
    @if ($requests->isEmpty())
        <p class="mb-0" style="color:var(--ico-muted)">No {{ $status }} requests.</p>
    @else
        <div class="table-responsive">
            <table class="admin-table">
                <thead><tr><th>Contributor</th><th>Asset type</th><th>Portfolio</th><th>Requested</th><th>{{ $status === 'pending' ? 'Action' : 'Reviewed' }}</th></tr></thead>
                <tbody>
                @foreach ($requests as $r)
                    <tr>
                        <td>
                            <strong>{{ $r->contributor?->display_name }}</strong><br>
                            <small style="color:var(--ico-muted)">{{ $r->contributor?->user?->email }}</small>
                        </td>
                        <td>{{ $types[$r->type_key]['label'] ?? $r->type_key }}</td>
                        <td><a href="{{ $r->portfolio_url }}" target="_blank" rel="noopener nofollow">{{ Str::limit($r->portfolio_url, 50) }} <i class="bi bi-box-arrow-up-right"></i></a></td>
                        <td style="color:var(--ico-muted)">{{ $r->created_at->format('j M Y') }}</td>
                        <td>
                            @if ($status === 'pending')
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('admin.access.approve', $r) }}">@csrf
                                        <button class="btn btn-ico-primary btn-sm" type="submit">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.access.reject', $r) }}" class="d-flex gap-1">@csrf
                                        <input class="form-control form-control-sm" name="notes" placeholder="Reason" required minlength="5" style="width:180px">
                                        <button class="btn btn-ico-outline btn-sm" type="submit">Reject</button>
                                    </form>
                                </div>
                            @else
                                <small>{{ $r->reviewer?->name ?? '—' }} · {{ $r->reviewed_at?->format('j M Y') }}</small>
                                @if ($r->reviewer_notes)<br><small style="color:var(--ico-muted)">{{ $r->reviewer_notes }}</small>@endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
