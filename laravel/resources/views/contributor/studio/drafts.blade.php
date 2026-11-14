@extends('layouts.studio')
@section('title', 'My Drafts — '.$type['label'])

@section('content')
<div class="cs-bar">
    <div>
        <a class="cs-back" href="{{ route('contributor.type', $type['key']) }}"><i class="bi bi-chevron-left"></i> My {{ $type['label'] }}</a>
        <h1 class="cs-bar__title">My Drafts</h1>
    </div>
    <a class="cs-btn cs-btn--primary ms-auto" href="{{ route('contributor.type.upload', $type['key']) }}"><i class="bi bi-cloud-arrow-up"></i> Upload New {{ $type['label'] }}</a>
</div>
<p class="cs-sub mb-4">Each upload session is saved as a draft. Open one to add the pack details, names, descriptions and tags, then submit it for review.</p>

@if ($drafts->isEmpty())
    <div class="cs-card cs-empty">No drafts yet</div>
@else
    <div class="cs-drafts">
        @foreach ($drafts as $d)
            <a class="cs-card cs-draft-card" href="{{ route('contributor.drafts.edit', [$type['key'], $d]) }}">
                <div class="cs-draft-card__grid">
                    @for ($i = 0; $i < 4; $i++)
                        @php $a = $d->items[$i] ?? null; $p = $a ? ($a->previews->firstWhere('is_primary', true) ?? $a->previews->first()) : null; @endphp
                        <div>@if ($a)@include('contributor.studio.partials.thumb', ['preview' => $p, 'alt' => $a->title])@endif</div>
                    @endfor
                </div>
                <div class="cs-draft-card__body">
                    <div class="cs-draft-card__title">{{ $d->pack_title ?: 'Untitled draft' }}</div>
                    <div class="cs-draft-card__meta">{{ $d->items_count }} {{ $type['unit'] }} · updated {{ $d->updated_at->diffForHumans() }}</div>
                    <div class="cs-draft-card__cta">Continue editing <i class="bi bi-arrow-right"></i></div>
                </div>
            </a>
        @endforeach
    </div>
@endif
@endsection
