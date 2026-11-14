@extends('layouts.studio')
@section('title', 'Keyword Insights')

@php
    $modes = ['none' => 'No Results', 'top' => 'Top Searches', 'trending' => 'Trending'];
    $intro = [
        'none' => 'People searched for these and found nothing. Make assets for these keywords — there is no competition yet.',
        'top' => 'According to user search analytics, these are the most popular search keywords. You can make creative assets based on "Top Searches" keywords.',
        'trending' => 'These keywords are being searched more this week than last week.',
    ];
@endphp

@section('content')
<div class="text-center mb-4">
    <h1 class="cs-center-title">Keyword Insights</h1>
    <p class="cs-sub">Curated keywords from user search analytics.</p>
    <div class="cs-seg mt-2">
        <a class="{{ $tab === 'all' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['asset' => null]) }}">All</a>
        @foreach ($types as $key => $t)
            <a class="{{ $tab === $key ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['asset' => $key]) }}">{{ $t['label'] }}</a>
        @endforeach
    </div>
</div>

<div class="cs-card" style="max-width:1240px;margin:0 auto;overflow:hidden">
    <div class="cs-kw-tabs">
        @foreach ($modes as $k => $label)
            <a class="{{ $mode === $k ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['mode' => $k === 'top' ? null : $k]) }}">{{ $label }}</a>
        @endforeach
    </div>
    <p class="text-center p-3 mb-0" style="color:var(--ico-heading);font-weight:600"><span style="font-size:1.3rem">👉</span> {{ $intro[$mode] }}</p>
    @if ($keywords->isEmpty())
        <div class="cs-empty">Not enough search data yet — this fills up as people search Iconors.</div>
    @else
        <div class="cs-kw-grid px-3 pb-3">
            @foreach ($keywords as $k)
                <a href="{{ route('assets.index', ['q' => $k->term]) }}" target="_blank" rel="noopener" title="{{ Str::title($k->term) }}">{{ Str::title($k->term) }}</a>
            @endforeach
        </div>
    @endif
</div>
@endsection
