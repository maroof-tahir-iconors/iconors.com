@extends('layouts.app')

@section('title', $tab['label'].' are coming soon — Iconors')
@section('meta_description', $tab['label'].' are on their way to Iconors. '.$tab['pitch'])

{{-- A placeholder page has nothing for a search engine to index yet. The
     links on it are still worth following. --}}
@section('robots', 'noindex, follow')

@section('content')
@include('tools.partials.assets')

<section class="cs-hero">
    <div class="ico-hero__blob" aria-hidden="true"
         style="width:min(40vw,420px);aspect-ratio:1;background:var(--ico-accent);top:-30%;left:-6%"></div>
    <div class="ico-hero__blob" aria-hidden="true"
         style="width:min(34vw,360px);aspect-ratio:1;background:var(--ico-primary);bottom:-45%;right:-4%"></div>

    <div class="container-ico position-relative">
        <div class="cs-hero__icon" aria-hidden="true"><i class="bi {{ $tab['icon'] }}"></i></div>
        <span class="ico-hero__label">Coming soon</span>
        <h1 class="cs-hero__title">
            {{ $tab['label'] }} are
            <span class="ico-hero__gradient-text">on the way</span>
        </h1>
        <p class="cs-hero__lead">
            {{ $tab['pitch'] }} We are preparing the first collection now — this page will turn into
            the full library as soon as it is published.
        </p>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            @forelse ($readyTabs as $ready)
                <a class="btn {{ $loop->first ? 'btn-ico-primary' : 'btn-ico-outline' }}" href="{{ $ready['url'] }}">
                    <i class="bi {{ $ready['icon'] }} me-1" aria-hidden="true"></i> Browse {{ Str::lower($ready['label']) }}
                </a>
            @empty
                <a class="btn btn-ico-primary" href="{{ route('packs.index') }}">Browse all packs</a>
            @endforelse
            <a class="btn btn-ico-outline" href="{{ route('assets.index', ['price' => 'free']) }}">Free assets</a>
        </div>
    </div>
</section>

<section class="section--tight">
    <div class="container-ico">
        <div class="section-head">
            <div>
                <h2>While you wait: free tools</h2>
                <p>They run in your browser. No sign-up, and your files never leave your device.</p>
            </div>
            <a class="section-head__link" href="{{ route('tools.index') }}">
                All tools <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>

        <div class="tl-cards">
            @foreach ($tools as $item)
                @include('tools.partials.card', ['item' => $item, 'level' => 'h3'])
            @endforeach
        </div>
    </div>
</section>

<section class="section">
    <div class="container-ico">
        <div class="ico-cta">
            <h2>Make {{ Str::lower($tab['label']) }} yourself?</h2>
            <p>Contributors earn a royalty on every download. Apply and be among the first in this category.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-ico-primary" href="{{ route('contributor.apply') }}">Become a contributor</a>
            </div>
        </div>
    </div>
</section>
@endsection
