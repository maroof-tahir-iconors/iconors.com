@extends('layouts.studio')
@section('title', 'Learning Center')

@php
    $sectionIcons = ['icon' => 'bullseye', 'illustr' => 'bounding-box', '3d' => 'box', 'lottie' => 'slash-square', 'anim' => 'slash-square'];
    $iconFor = function ($section) use ($sectionIcons) {
        $s = Str::lower($section);
        foreach ($sectionIcons as $k => $i) { if (str_contains($s, $k)) { return $i; } }
        return 'mortarboard';
    };
@endphp

@section('content')
<div class="cs-learn-hero">
    <h1>Learn from industry experts</h1>
    <p class="cs-sub m-0" style="max-width:640px">Hand-picked courses, guides and documentation to help you design better icons, illustrations, 3D and Lottie animations — and get them approved faster.</p>
</div>

@forelse ($sections as $section => $courses)
    <section class="cs-learn-section">
        <h2>{{ $section }}</h2>
        <div class="cs-courses">
            @foreach ($courses as $c)
                <a class="cs-card cs-course" href="{{ $c->url }}" target="_blank" rel="noopener">
                    <div class="cs-course__img" style="background:linear-gradient(135deg, {{ $c->color ?: '#5a01f0' }}, #0b0a1a)">
                        @if ($c->thumbnail)
                            <img src="{{ Storage::disk('public')->url($c->thumbnail) }}" alt="" loading="lazy">
                        @else
                            <i class="bi bi-{{ $iconFor($section) }}"></i>
                        @endif
                    </div>
                    <div class="cs-course__body">
                        <div class="cs-course__title">{{ $c->title }}</div>
                        @if ($c->author)<div class="cs-course__author">by {{ $c->author }}</div>@endif
                        <div class="cs-course__go">Open course <i class="bi bi-box-arrow-up-right"></i></div>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@empty
    <div class="cs-card cs-empty">Courses are coming soon</div>
@endforelse
@endsection
