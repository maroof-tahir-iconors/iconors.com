@extends('layouts.app')

@section('title', $contributor->display_name.' — Iconors')
@section('meta_description', Str::limit($contributor->display_name.' on Iconors. '.($contributor->biography ?? 'Free icons, illustrations, 3D icons and Lottie animations.'), 155))

@php
    $avatar = $contributor->avatar ? Storage::disk('public')->url($contributor->avatar) : null;
    $social = (array) ($contributor->social_links ?? []);
    $icons = ['dribbble' => 'dribbble', 'facebook' => 'facebook', 'behance' => 'behance', 'twitter' => 'twitter-x', 'instagram' => 'instagram', 'linkedin' => 'linkedin'];
@endphp

@include('partials.pack-styles')

@section('content')
<div class="container-ico px-3 py-4">
    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item">Contributors</li>
            <li class="breadcrumb-item active" aria-current="page">{{ $contributor->display_name }}</li>
        </ol>
    </nav>

    <section class="ico-card p-4 p-md-5 mb-4 d-flex flex-wrap gap-4 align-items-center">
        <div style="width:118px;height:118px;border-radius:50%;overflow:hidden;flex:none;display:grid;place-items:center;font-size:3rem;font-weight:800;background:var(--ico-accent);color:#fff">
            @if ($avatar)<img src="{{ $avatar }}" alt="{{ $contributor->display_name }}" style="width:100%;height:100%;object-fit:cover">@else{{ Str::upper(Str::substr($contributor->display_name, 0, 1)) }}@endif
        </div>
        <div class="flex-grow-1" style="min-width:240px">
            <h1 class="h3 mb-1">{{ $contributor->display_name }}
                @if ($contributor->is_verified)<i class="bi bi-patch-check-fill ms-1" style="color:var(--ico-primary);font-size:1.1rem" title="Verified"></i>@endif
            </h1>
            @if ($contributor->biography)<p class="mb-2" style="color:var(--ico-muted)">{{ Str::limit($contributor->biography, 300) }}</p>@endif
            <p class="small mb-2" style="color:var(--ico-muted)">
                @if ($contributor->country)<i class="bi bi-geo-alt me-1"></i>{{ $contributor->country }} · @endif
                <i class="bi bi-heart-fill me-1" style="color:#ef4444"></i>Since {{ ($contributor->approved_at ?? $contributor->created_at)->format('F j, Y') }}
            </p>
            <div class="d-flex gap-2 flex-wrap">
                @if ($contributor->website)<a class="btn btn-ico-outline btn-sm" href="{{ $contributor->website }}" target="_blank" rel="noopener nofollow"><i class="bi bi-globe"></i></a>@endif
                @foreach ($icons as $k => $bi)
                    @if (! empty($social[$k]))<a class="btn btn-ico-outline btn-sm" href="{{ $social[$k] }}" target="_blank" rel="noopener nofollow" aria-label="{{ Str::headline($k) }}"><i class="bi bi-{{ $bi }}"></i></a>@endif
                @endforeach
                @if ($isOwner)<a class="btn btn-ico-primary btn-sm" href="{{ route('contributor.shop') }}">Edit Profile</a>@endif
            </div>
        </div>
    </section>

    @if (empty($types))
        <div class="ico-card p-5 text-center" style="color:var(--ico-muted)">No published assets yet.</div>
    @else
        <div class="d-flex flex-wrap gap-2 align-items-center mb-3" style="border-bottom:1px solid var(--ico-border)">
            @foreach ($types as $key => $t)
                <a href="{{ request()->fullUrlWithQuery(['type' => $key, 'page' => null]) }}" class="px-3 py-2 fw-semibold"
                   style="border-bottom:2px solid {{ $tab === $key ? 'var(--ico-primary)' : 'transparent' }};color:{{ $tab === $key ? 'var(--ico-heading)' : 'var(--ico-muted)' }}">
                    {{ $t['label'] }} <span class="small">{{ number_format($t['count']) }}</span>
                </a>
            @endforeach
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div class="btn-group">
                <a class="btn btn-sm {{ $view === 'packs' ? 'btn-ico-primary' : 'btn-ico-outline' }}" href="{{ request()->fullUrlWithQuery(['view' => null, 'page' => null]) }}">View as a Pack</a>
                <a class="btn btn-sm {{ $view === 'individuals' ? 'btn-ico-primary' : 'btn-ico-outline' }}" href="{{ request()->fullUrlWithQuery(['view' => 'individuals', 'page' => null]) }}">Individual assets</a>
            </div>
            <div class="btn-group">
                <a class="btn btn-sm {{ $sort === 'recent' ? 'btn-ico-primary' : 'btn-ico-outline' }}" href="{{ request()->fullUrlWithQuery(['sort' => null]) }}">Recent</a>
                <a class="btn btn-sm {{ $sort === 'popular' ? 'btn-ico-primary' : 'btn-ico-outline' }}" href="{{ request()->fullUrlWithQuery(['sort' => 'popular']) }}">Popular</a>
            </div>
        </div>

        @if ($view === 'packs')
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
                @foreach ($packs as $pack)<div class="col"><x-pack-card :pack="$pack" /></div>@endforeach
            </div>
            <div class="mt-4">{{ $packs->links() }}</div>
        @else
            <div class="row row-cols-2 row-cols-md-4 row-cols-xl-6 g-3">
                @foreach ($assets as $asset)<div class="col"><x-asset-card :asset="$asset" /></div>@endforeach
            </div>
            <div class="mt-4">{{ $assets->links() }}</div>
        @endif
    @endif
</div>
@endsection
