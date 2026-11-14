@extends('layouts.studio')
@section('title', $statusMeta['label'].' — '.$type['label'])

@include('partials.pack-styles')

@section('content')
<a class="cs-back" href="{{ route('contributor.type', $type['key']) }}"><i class="bi bi-chevron-left"></i> My {{ $type['label'] }}</a>

<div class="cs-bar">
    <h1 class="cs-bar__title">{{ $statusMeta['label'] }}</h1>
    @if ($status === 'live')
        <div class="cs-seg">
            <a class="{{ $view === 'packs' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => null, 'page' => null]) }}">Packs</a>
            <a class="{{ $view === 'individuals' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['view' => 'individuals', 'page' => null]) }}">Individuals</a>
        </div>
    @endif
    <form method="GET" class="d-flex gap-2 ms-auto flex-wrap">
        @if ($view === 'individuals')<input type="hidden" name="view" value="individuals">@endif
        <input class="cs-input" type="search" name="q" value="{{ $q }}" placeholder="Search in profile" aria-label="Search in profile">
        <select class="cs-select" name="sort" onchange="this.form.submit()" aria-label="Sort">
            <option value="recent" @selected($sort === 'recent')>Recently Updated</option>
            <option value="downloads" @selected($sort === 'downloads')>Most Downloaded</option>
            <option value="title" @selected($sort === 'title')>Name A–Z</option>
        </select>
    </form>
</div>

@if ($status === 'live')
    <div class="cs-note mb-4">This option allows you to see how many of your assets are live on Iconors.</div>
@elseif ($status === 'review')
    <div class="cs-note mb-4">Our team reviews every upload before it goes live. You'll find it under Live in Store once it is approved.</div>
@elseif ($status === 'improve')
    <div class="cs-note mb-4">The reviewer asked for changes on these. Read the note on each one, fix the file and upload it again.</div>
@endif

@if ($status === 'live' && $view === 'packs')
    @if ($packs->isEmpty())
        <div class="cs-card cs-empty">No packs yet — switch to Individuals to see single assets.</div>
    @else
        <div class="cs-packs">
            @foreach ($packs as $pack)
                <x-pack-card :pack="$pack" />
            @endforeach
        </div>
        <div class="mt-4">{{ $packs->links() }}</div>
    @endif
@else
    @if ($assets->isEmpty())
        <div class="cs-card cs-empty">No result found</div>
    @else
        <div class="cs-grid">
            @foreach ($assets as $asset)
                @php $pv = $asset->previews->firstWhere('is_primary', true) ?? $asset->previews->first(); @endphp
                <div class="cs-card cs-tile">
                    <div class="cs-tile__media">@include('contributor.studio.partials.thumb', ['preview' => $pv, 'alt' => $asset->title])</div>
                    <div class="cs-tile__body">
                        <div class="cs-tile__title" title="{{ $asset->title }}">
                            @if ($status === 'live')<a href="{{ route('assets.show', $asset->slug) }}" target="_blank" rel="noopener">{{ $asset->title }}</a>@else{{ $asset->title }}@endif
                        </div>
                        <div class="cs-tile__meta">
                            @if ($status === 'live'){{ number_format($asset->downloads_count) }} downloads@else Updated {{ $asset->updated_at?->diffForHumans() }}@endif
                        </div>
                        @if ($asset->status_reason && in_array($status, ['improve', 'rejected'], true))
                            <div class="cs-tile__reason">{{ Str::limit($asset->status_reason, 140) }}</div>
                        @endif
                        @can('delete', $asset)
                            @if ($status !== 'deleted')
                                <form method="POST" action="{{ route('contributor.studio.assets.destroy', $asset) }}" class="mt-2" onsubmit="return confirm('Delete “{{ addslashes($asset->title) }}”?')">
                                    @csrf @method('DELETE')
                                    <button class="cs-btn cs-btn--sm cs-btn--danger" type="submit"><i class="bi bi-trash3"></i> Delete</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $assets->links() }}</div>
    @endif
@endif
@endsection
