@extends('layouts.studio')
@section('title', 'Sales Overview')

@section('content')
@include('contributor.studio.partials.stats-tabs')

<div class="cs-bar">
    <h1 class="cs-bar__title">Sales Overview</h1>
    <div class="ms-auto">@include('contributor.studio.partials.range')</div>
</div>

<div class="cs-card mb-4">
    <div class="cs-stat-row">
        <div class="cs-stat"><b style="color:#38bdf8">${{ number_format($cards['direct']->total, 2) }}</b><span>Direct Sales</span><em class="cs-chip">{{ number_format($cards['direct']->items) }} items</em></div>
        <div class="cs-stat"><b style="color:#eab308">${{ number_format($cards['subscription']->total, 2) }}</b><span>Subscription Sales</span><em class="cs-chip">{{ number_format($cards['subscription']->items) }} items</em></div>
        <div class="cs-stat"><b style="color:#94a3b8">${{ number_format($cards['api']->total, 2) }}</b><span>API Sales</span><em class="cs-chip">{{ number_format($cards['api']->items) }} items</em></div>
        <div class="cs-stat"><b style="color:var(--ico-primary)">${{ number_format($grand, 2) }}</b><span>Total sales</span><em class="cs-chip">{{ number_format($downloads->sum()) }} downloads</em></div>
    </div>
</div>

<div class="cs-card" style="overflow:hidden">
    <h2 class="h6 text-center py-3 m-0" style="font-weight:700">Monthly Sales</h2>
    <div class="cs-table-wrap">
        <table class="cs-table">
            <thead><tr><th>Date</th><th>Direct Sales</th><th>Subscription Sales</th><th>API Sales</th><th>Total Earnings</th></tr></thead>
            <tbody>
            @forelse ($monthly as $m)
                <tr>
                    <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $m->ym)->format('M, Y') }}</td>
                    <td>${{ number_format($m->direct, 2) }}<br><small class="cs-sub">{{ $m->direct_n }} items</small></td>
                    <td>${{ number_format($m->sub, 2) }}<br><small class="cs-sub">{{ $m->sub_n }} items</small></td>
                    <td>${{ number_format($m->api, 2) }}<br><small class="cs-sub">{{ $m->api_n }} items</small></td>
                    <td style="color:var(--ico-heading);font-weight:600">${{ number_format($m->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="cs-empty">No earnings in this period yet</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
