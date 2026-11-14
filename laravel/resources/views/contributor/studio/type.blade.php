@extends('layouts.studio')
@section('title', 'My '.$type['label'])

@section('content')
<div class="cs-bar">
    <div>
        <span class="cs-eyebrow">My workspace</span>
        <h1 class="cs-bar__title">My {{ $type['label'] }}</h1>
    </div>
    <div class="d-flex gap-2 ms-auto flex-wrap">
        <a class="cs-btn" href="{{ route('contributor.drafts', $type['key']) }}"><i class="bi bi-pencil-square"></i> My Drafts</a>
        <a class="cs-btn cs-btn--primary" href="{{ route('contributor.type.upload', $type['key']) }}"><i class="bi bi-cloud-arrow-up"></i> Upload New</a>
    </div>
</div>

<div class="cs-status-grid">
    <a class="cs-card cs-status-card cs-upload-card" href="{{ route('contributor.type.upload', $type['key']) }}">
        <i class="bi bi-plus-lg"></i>
        <strong>Upload New</strong>
    </a>
    @foreach ($statuses as $key => $s)
        <a class="cs-card cs-status-card" href="{{ route('contributor.type.status', [$type['key'], $key]) }}" style="--c:{{ $s['color'] }}">
            <i class="bi bi-{{ $s['icon'] }}"></i>
            <strong>{{ $s['label'] }}</strong>
            <small>{{ number_format($counts[$key]) }} {{ $type['unit'] }}</small>
        </a>
    @endforeach
</div>
@endsection
