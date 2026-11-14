@extends('layouts.studio')
@section('title', 'Contributor Dashboard')

@php
    $typeColors = ['icons' => '#6366f1', 'illustrations' => '#ec4899', '3d' => '#f59e0b', 'lottie' => '#10b981'];
    $firstOpen = collect($types)->keys()->first(fn ($k) => ($access[$k] ?? 'none') === 'approved');
    $totalLive = array_sum($liveCounts);
@endphp

@section('content')
<section class="cs-hero">
    <div>
        <span class="cs-eyebrow">Contributor Studio</span>
        <h1>Welcome back, {{ $contributor->display_name }}</h1>
        <p>Upload new work, finish your drafts and track how your assets are doing.</p>
    </div>
    <div class="cs-hero__actions">
        @if ($firstOpen)
            <a class="cs-btn cs-btn--primary" href="{{ route('contributor.type.upload', $firstOpen) }}"><i class="bi bi-cloud-arrow-up"></i> Upload {{ $types[$firstOpen]['label'] }}</a>
        @endif
        <a class="cs-btn" href="{{ route('contributor.learn') }}"><i class="bi bi-book"></i> Learning Center</a>
    </div>
</section>

<div class="cs-kpis">
    <div class="cs-card cs-kpi"><span class="cs-kpi__icon" style="--c:#10b981"><i class="bi bi-wallet2"></i></span><div><small>Available to cash out</small><b>${{ number_format($summary['available'], 2) }}</b></div></div>
    <div class="cs-card cs-kpi"><span class="cs-kpi__icon" style="--c:#6366f1"><i class="bi bi-shop"></i></span><div><small>Live in store</small><b>{{ number_format($totalLive) }}</b></div></div>
    <a class="cs-card cs-kpi" href="{{ route('contributor.achievements') }}"><span class="cs-kpi__icon" style="--c:#f59e0b"><i class="bi bi-award"></i></span><div><small>Achievements</small><b>{{ $badges['unlocked'] }}<em>/{{ $badges['total'] }}</em></b></div></a>
</div>

<div class="cs-home">
    <div>
        <div class="cs-section-head"><h2>Your asset types</h2><span class="cs-sub">Open a type to upload or manage it</span></div>
        <div class="cs-type-grid">
            @foreach ($types as $key => $t)
                @php $state = $access[$key] ?? 'none'; $ok = $state === 'approved'; $c = $typeColors[$key] ?? '#10b981'; @endphp
                @if ($ok)
                    <a class="cs-card cs-type-card" href="{{ route('contributor.type', $key) }}" style="--c:{{ $c }}">
                        <span class="cs-type-card__icon"><i class="bi bi-{{ $t['icon'] }}"></i></span>
                        <span class="cs-type-card__title">{{ $t['label'] }}</span>
                        <span class="cs-type-card__count">{{ number_format($liveCounts[$key]) }} live in store</span>
                        <span class="cs-type-card__state is-ok"><i class="bi bi-check-circle-fill"></i> Upload open</span>
                    </a>
                @else
                    <div class="cs-card cs-type-card is-locked" tabindex="0" role="button" style="--c:{{ $c }}"
                         @if ($state !== 'pending') data-cs-request data-label="{{ $t['label'] }}" data-action="{{ route('contributor.access.request', $key) }}" @endif
                         aria-label="{{ $state === 'pending' ? $t['label'].' — request pending' : 'Request to upload '.$t['label'] }}">
                        <span class="cs-type-card__icon"><i class="bi bi-{{ $t['icon'] }}"></i></span>
                        <span class="cs-type-card__title">{{ $t['label'] }}</span>
                        <span class="cs-type-card__count">{{ number_format($liveCounts[$key]) }} live in store</span>
                        @if ($state === 'pending')
                            <span class="cs-type-card__state is-wait"><i class="bi bi-hourglass-split"></i> Request pending</span>
                        @elseif ($state === 'rejected')
                            <span class="cs-type-card__state is-no"><i class="bi bi-x-circle-fill"></i> Not approved</span>
                        @else
                            <span class="cs-type-card__state"><i class="bi bi-lock-fill"></i> Locked</span>
                        @endif
                        <span class="cs-type-card__veil"><span>{{ $state === 'pending' ? 'Waiting for approval' : ($state === 'rejected' ? 'Request again' : 'Request to Upload') }}</span></span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <aside class="cs-home__side">
        <div class="cs-earn">
            <span class="cs-earn__label">My earning</span>
            <span class="cs-earn__amount">${{ number_format($summary['available'], 2) }}</span>
            <span class="cs-earn__hint">Combined sales and subscription downloads</span>
            <div><a class="cs-btn cs-btn--white" href="{{ route('contributor.cashout') }}">Request Cashout <i class="bi bi-arrow-right"></i></a></div>
        </div>

        <a class="cs-card cs-ach-mini d-block" href="{{ route('contributor.achievements') }}">
            <div class="cs-ach-mini__head"><span>Achievements</span><span>{{ $badges['unlocked'] }}/{{ $badges['total'] }} <i class="bi bi-arrow-right"></i></span></div>
            <div class="cs-ach-mini__grid">
                @foreach (array_slice($badges['all'], 0, 6) as $b)
                    <span class="{{ $b['unlocked'] ? '' : 'is-locked' }}" title="{{ $b['title'] }}">{!! \App\Services\Contributor\StudioAchievements::svg($b, 74) !!}</span>
                @endforeach
            </div>
        </a>
    </aside>
</div>
@endsection
