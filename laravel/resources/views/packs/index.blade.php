@extends('layouts.app')

@php
    $heading = match (true) {
        (bool) $activeCategory => $activeCategory->name.' packs',
        (bool) $activeType => $activeType->name.' packs',
        default => 'All packs',
    };

    $searchTerm = is_string(request('q')) ? trim(request('q')) : '';
    $currentSort = is_string(request('sort')) ? request('sort') : 'popular';
    $currentCategory = is_string(request('category')) ? request('category') : '';
@endphp

@section('title', ($searchTerm !== '' ? "Packs matching “{$searchTerm}”" : $heading).' — Iconors')
@section('meta_description', 'Browse complete packs of animations and icons. Every pack opens to show each asset inside it.')

@section('content')
@include('partials.pack-styles')

<div class="container-ico px-3 py-4">

    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            @if ($activeCategory || $activeType || $searchTerm !== '')
                <li class="breadcrumb-item"><a href="{{ route('packs.index') }}">Packs</a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    {{ $searchTerm !== '' ? 'Search' : $heading }}
                </li>
            @else
                <li class="breadcrumb-item active" aria-current="page">Packs</li>
            @endif
        </ol>
    </nav>

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">
                @if ($searchTerm !== '')
                    Packs matching “{{ $searchTerm }}”
                @else
                    {{ $heading }}
                @endif
            </h1>
            <p class="text-muted small mb-0">
                {{ number_format($packs->total()) }} {{ Str::plural('pack', $packs->total()) }}
            </p>
        </div>

        <form method="GET" class="d-flex align-items-center gap-2">
            {{-- Carry existing filters so changing sort does not reset them. --}}
            @foreach (request()->except(['sort', 'page']) as $key => $value)
                @continue(! is_string($value))
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <label for="pack-sort" class="small text-muted mb-0">Sort</label>
            <select id="pack-sort" name="sort" class="form-select form-select-sm w-auto"
                    onchange="this.form.submit()">
                @foreach ([
                    'popular' => 'Popular',
                    'newest' => 'Newest',
                    'largest' => 'Most assets',
                    'az' => 'Name: A to Z',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($currentSort === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- Category filter. A horizontal strip rather than a sidebar: packs are
         wide cards, and a sidebar would cost a whole column of them. --}}
    @if ($categories->isNotEmpty())
        <nav class="pack-chips mb-4" aria-label="Filter packs by category">
            <a class="btn btn-ico-outline btn-sm {{ $currentCategory === '' ? 'is-active' : '' }}"
               @if ($currentCategory === '') aria-current="true" @endif
               href="{{ route('packs.index', request()->except(['category', 'page'])) }}">All</a>
            @foreach ($categories as $item)
                <a class="btn btn-ico-outline btn-sm {{ $currentCategory === $item->slug ? 'is-active' : '' }}"
                   @if ($currentCategory === $item->slug) aria-current="true" @endif
                   href="{{ route('packs.index', array_merge(request()->except('page'), ['category' => $item->slug])) }}">
                    {{ $item->name }}
                </a>
            @endforeach
        </nav>
    @endif

    @if ($packs->isEmpty())
        {{-- Empty state: always offer a way out. --}}
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-collection" aria-hidden="true"></i></div>
            <h2 class="h5">No packs found</h2>
            <p class="text-muted small mb-3">
                @if ($searchTerm !== '')
                    Nothing matched “{{ $searchTerm }}”. Try a broader term or clear your filters.
                @else
                    No packs match these filters yet.
                @endif
            </p>
            <a class="btn btn-ico-primary btn-sm" href="{{ route('packs.index') }}">Show all packs</a>
        </div>
    @else
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
            @foreach ($packs as $pack)
                <div class="col">
                    <x-pack-card :pack="$pack" />
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $packs->links() }}
        </div>
    @endif
</div>
@endsection
