@extends('layouts.studio')
@section('title', 'Cash out')

@php
    $colors = ['completed' => '#22c55e', 'requested' => '#94a3b8', 'under_review' => '#3b82f6', 'approved' => '#3b82f6', 'processing' => '#3b82f6', 'rejected' => '#ef4444', 'failed' => '#ef4444', 'cancelled' => '#94a3b8'];
    $c = $summary['currency'];
@endphp

@section('content')
<div style="max-width:1240px;margin:0 auto">
    <h1 class="cs-page-title">Cash out</h1>

    <div class="cs-earn mb-4" style="min-height:0">
        <div class="d-flex justify-content-between flex-wrap gap-2" style="position:relative;z-index:1">
            <div>
                <span style="font-size:1.35rem;font-weight:700">Earning Balance</span>
                <span class="ms-2" style="opacity:.85;font-size:.9rem">Lifetime {{ $c }} {{ number_format($summary['lifetime'], 2) }}</span>
            </div>
            <span style="font-size:1.4rem;font-weight:700">${{ number_format($summary['available'], 2) }}</span>
        </div>
        <p class="mb-0 mt-2" style="position:relative;z-index:1;opacity:.92;font-size:.92rem;line-height:1.65">
            Amount you earned from sales and subscription downloads. Earnings become available to withdraw
            <b>{{ $summary['holdingDays'] }} days</b> after they are earned, which protects against payouts on sales that are later refunded.
            Pending right now: <b>${{ number_format($summary['pending'], 2) }}</b>.
        </p>
    </div>

    <div class="cs-card p-4 mb-4">
        <h2 class="h5 mb-2">Request Cashout</h2>
        @if ($summary['canWithdraw'])
            <form method="POST" action="{{ route('contributor.payout.request') }}" class="d-flex flex-wrap gap-2 align-items-center">
                @csrf
                @if ($methods->isNotEmpty())
                    <select class="cs-select" name="payout_method_id" aria-label="Payout account">
                        @foreach ($methods as $m)
                            <option value="{{ $m->id }}" @selected($m->is_default)>{{ Str::headline($m->type) }} {{ $m->display_hint }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="cs-btn cs-btn--primary" type="submit">Request ${{ number_format($summary['available'], 2) }} cashout</button>
            </form>
        @else
            <p class="mb-0 cs-sub">
                Your earning balance is below ${{ number_format($summary['minimum'], 2) }}.
                You need to make another ${{ number_format($summary['shortfall'], 2) }} in sales to request cashout. Upload more assets to earn more.
            </p>
        @endif
    </div>

    <div class="cs-card" style="overflow:hidden">
        <div class="d-flex justify-content-between align-items-center p-4 flex-wrap gap-2">
            <h2 class="h5 m-0">Cashout History</h2>
            <span style="color:var(--ico-heading)">Total earnings received: ${{ number_format($received, 2) }}</span>
        </div>
        <div class="cs-table-wrap">
            <table class="cs-table">
                <thead><tr><th>Date</th><th>Account</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($history as $h)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($h->created_at)->format('F j, Y') }}</td>
                        <td>{{ $h->method_type ? Str::headline($h->method_type).' '.$h->display_hint : 'Default account' }}</td>
                        <td>${{ number_format($h->net_amount, 2) }}</td>
                        <td><span class="cs-pill-status" style="background:{{ $colors[$h->status] ?? '#94a3b8' }}22;color:{{ $colors[$h->status] ?? '#94a3b8' }}">{{ str_replace('_', ' ', $h->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="cs-empty">No cashouts yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
