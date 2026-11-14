@extends('layouts.app')

@php
    $total = $assets->total();
    $unit = Str::plural($assetType?->name ?? 'asset', $total);
    $summary = number_format($total).' '.$unit;

    $about = trim((string) $pack->description) !== ''
        ? $pack->description
        : "{$pack->title} is a pack of {$summary}.";
@endphp

@section('title', "{$pack->title} — {$summary} — Iconors")
@section('meta_description', Str::limit(strip_tags($about), 155))

@section('content')
@include('partials.pack-styles')

<div class="container-ico px-3 py-4">

    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('packs.index') }}">Packs</a></li>
            @if ($category)
                <li class="breadcrumb-item">
                    <a href="{{ route('packs.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ $pack->title }}</li>
        </ol>
    </nav>

    <header class="ico-card pack-hero mb-4">
        <div>
            <h1 class="pack-hero__title">{{ $pack->title }}</h1>
            <p class="pack-hero__meta">
                @if ($pack->is_premium)
                    <span class="badge-premium">Premium</span>
                @else
                    <span class="badge-free">Free</span>
                @endif
                <span>{{ $summary }}</span>
            </p>
            <p class="pack-hero__text">{{ $about }}</p>
        </div>

        @if ($pack->is_premium && $pack->price > 0)
            <div class="h3 mb-0">{{ '$'.number_format((float) $pack->price, 2) }}</div>
        @endif
    </header>

    <section aria-label="Assets in this pack">
        <div class="section-head">
            <div>
                <h2>
                    In this pack
                    <span class="pack-rail__meta">{{ $summary }}</span>
                </h2>
            </div>
        </div>

        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 g-3">
            @foreach ($assets as $asset)
                {{-- Computed outside the tag: a comparison operator inside a
                     component attribute breaks the component parser. --}}
                @php
                    $eager = $loop->index < 6;
                @endphp
                <div class="col">
                    <x-asset-card :asset="$asset" :eager="$eager" />
                </div>
            @endforeach
        </div>

        @if ($assets->hasPages())
            <div class="mt-4">
                {{ $assets->links() }}
            </div>
        @endif
    </section>

    @if ($related->isNotEmpty())
        <section class="mt-5" data-ico-reveal>
            <div class="section-head">
                <div>
                    <h2>More {{ $category?->name }} packs</h2>
                </div>
                @if ($category)
                    <a class="section-head__link"
                       href="{{ route('packs.index', ['category' => $category->slug]) }}">
                        View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
            </div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
                @foreach ($related as $item)
                    <div class="col">
                        <x-pack-card :pack="$item" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
