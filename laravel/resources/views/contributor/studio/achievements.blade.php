@extends('layouts.studio')
@section('title', 'Achievements')

@php
    $list = $period === 'monthly' ? $badges['monthly'] : $badges['all'];
    $share = urlencode($profileUrl);
@endphp

@section('content')
<div style="max-width:1240px;margin:0 auto">
    <h1 class="cs-page-title mb-2">Achievements</h1>
    <p class="mb-1">The more you upload and users download, the more badges you unlock!</p>
    <p class="cs-sub">Refer to the <a href="{{ route('contributor.help') }}" style="color:var(--ico-primary)">Contributor Guide</a> for more information.</p>
    <hr style="border-color:var(--ico-border)">

    <div class="text-center my-4">
        <div class="cs-seg">
            <a class="{{ $period === 'all' ? 'is-active' : '' }}" href="{{ route('contributor.achievements') }}">All Time</a>
            <a class="{{ $period === 'monthly' ? 'is-active' : '' }}" href="{{ route('contributor.achievements', ['period' => 'monthly']) }}">Monthly</a>
        </div>
    </div>

    <div class="cs-badges">
        @foreach ($list as $b)
            <div class="cs-card cs-badge {{ $b['unlocked'] ? '' : 'is-locked' }}">
                {!! \App\Services\Contributor\StudioAchievements::svg($b, 104) !!}
                <strong>{{ $b['title'] }}</strong>
                @if ($b['date'])<small>{{ $b['date'] }}</small>@endif
                @if (isset($b['target']))
                    <small>{{ number_format($b['current']) }} of {{ number_format($b['target']) }}</small>
                    <div class="cs-progress"><span style="width:{{ $b['progress'] }}%"></span></div>
                @endif
                @if ($b['unlocked'])
                    <div class="cs-badge__share">
                        <span>Share via:
                            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $share }}" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="bi bi-facebook"></i></a>
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $share }}" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><i class="bi bi-linkedin"></i></a>
                            <a href="https://twitter.com/intent/tweet?url={{ $share }}&text={{ urlencode('I unlocked "'.$b['title'].'" on Iconors') }}" target="_blank" rel="noopener" aria-label="Share on X"><i class="bi bi-twitter-x"></i></a>
                        </span>
                        <a href="{{ route('contributor.achievements.badge', $b['key']) }}" title="Download Badge" aria-label="Download badge"><i class="bi bi-download"></i></a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
