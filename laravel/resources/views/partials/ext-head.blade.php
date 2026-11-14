{{--
    Hand-written additions that sit outside the Vite build: the Inter font,
    the header styles and the header's small script.

    Plain files under public/ico-ext, because the server has no Node
    toolchain — a rule added to resources/scss would not reach visitors
    until the next local build. filemtime() busts the browser cache
    whenever a file is replaced, so there is no version number to bump.

    Included by every layout AFTER @vite and the branding <style> block:
    ico-ext.css appends a fallback stack to whatever font the admin chose,
    which only works if it is the last word on font-family.
--}}
@inject('icoSettings', 'App\Services\Settings\SettingsService')

@php
    $icoExt = function (string $file): string {
        $path = public_path('ico-ext/'.$file);

        return asset('ico-ext/'.$file).'?v='.(is_file($path) ? filemtime($path) : '1');
    };
@endphp

<link rel="preload" href="{{ asset('ico-ext/fonts/inter-latin-wght-normal.woff2') }}"
      as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $icoExt('ico-ext.css') }}">
<script defer src="{{ $icoExt('ico-ext.js') }}"></script>

{{-- Favicon and home-screen icon: the Iconors app icon. Skipped when an
     admin has uploaded their own favicon under Admin → Settings → Branding,
     which the layout prints instead. --}}
@unless ($icoSettings->get('favicon'))
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ $icoExt('brand/iconors-app-icon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
@endunless


{{-- Google AdSense verification --}}
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-2349226238644218"
     crossorigin="anonymous"></script>
