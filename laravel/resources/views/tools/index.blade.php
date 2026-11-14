@extends('layouts.app')

@section('title', 'Free Design Tools — Converter, Compressor, QR Codes, Editor — Iconors')
@section('meta_description', 'Free browser tools from Iconors: convert and compress images, generate custom QR codes and edit pictures. No sign-up, no upload, no watermark.')

@section('content')
@include('tools.partials.assets')

<section class="ico-hero text-center" data-ico-hero>
    <div class="ico-hero__blob" aria-hidden="true" data-ico-parallax
         style="width:min(40vw,420px);aspect-ratio:1;background:var(--ico-accent);top:-30%;left:-6%"></div>
    <div class="ico-hero__blob" aria-hidden="true" data-ico-parallax
         style="width:min(34vw,360px);aspect-ratio:1;background:var(--ico-primary);bottom:-40%;right:-4%"></div>

    <div class="container-ico position-relative">
        <span class="ico-hero__label" data-ico-hero-item>Free tools</span>
        <h1 class="ico-hero__title" data-ico-hero-item style="font-size:clamp(2rem,5vw,3.4rem)">
            Everyday design tools,<br>
            <span class="ico-hero__gradient-text">right in your browser</span>
        </h1>
        <p class="ico-hero__lead mb-0" data-ico-hero-item>
            Convert, compress and edit images or make a QR code in seconds. Free, with no sign-up —
            and your files never leave your device.
        </p>
    </div>
</section>

<section class="section--tight">
    <div class="container-ico">
        <div class="tl-cards tl-cards--lg">
            @foreach ($tools as $item)
                @include('tools.partials.card', ['item' => $item, 'level' => 'h2'])
            @endforeach
        </div>
    </div>
</section>

<section class="section" data-ico-reveal>
    <div class="container-ico">
        <div class="ico-card p-4 p-md-5">
            <div class="row g-4 g-lg-5 align-items-start">
                <div class="col-lg-5">
                    <h2 class="h4 mb-2">Private by design</h2>
                    <p class="small mb-0" style="color:var(--ico-muted)">
                        Most online tools upload your file to a server, process it there and send it back.
                        These do the work on your own device instead.
                    </p>
                </div>
                <div class="col-lg-7">
                    <ol class="step-list list-unstyled mb-0">
                        <li class="step-list__item">
                            <div>
                                <p class="step-list__title mb-0">Nothing is uploaded</p>
                                <p class="step-list__text">Your images are opened and saved by your browser. They are never sent to Iconors or anyone else.</p>
                            </div>
                        </li>
                        <li class="step-list__item">
                            <div>
                                <p class="step-list__title mb-0">No account, no limits</p>
                                <p class="step-list__text">Use every tool as often as you like without signing up. Nothing is watermarked.</p>
                            </div>
                        </li>
                        <li class="step-list__item">
                            <div>
                                <p class="step-list__title mb-0">Fast, even on big batches</p>
                                <p class="step-list__text">There is no upload or download wait — a batch is ready as quickly as your device can process it.</p>
                            </div>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section pt-0" data-ico-reveal>
    <div class="container-ico">
        <div class="ico-cta">
            <h2>Need assets to go with them?</h2>
            <p>Browse the Iconors library of animations and icon packs — free and premium.</p>
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <a class="btn btn-ico-primary" href="{{ route('packs.index') }}">Browse packs</a>
                <a class="btn btn-light" href="{{ route('assets.index', ['price' => 'free']) }}">Free assets</a>
            </div>
        </div>
    </div>
</section>
@endsection
