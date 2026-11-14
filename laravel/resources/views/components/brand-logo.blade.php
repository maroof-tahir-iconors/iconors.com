@props(['variant' => 'main', 'showName' => true])

@inject('settings', 'App\Services\Settings\SettingsService')

@php
    // An admin-uploaded logo (Admin → Settings → Branding) still wins, with
    // the variant falling back to the main upload. With no upload, the
    // built-in Iconors logo is used.
    $key = 'logo_'.$variant;
    $path = $settings->get($key) ?: $settings->get('logo_main');
    $name = $settings->get('site_name', 'Iconors');
@endphp

<a {{ $attributes->merge(['class' => 'ico-logo', 'href' => route('home')]) }}
   aria-label="{{ $name }} home">
    @if ($path)
        {{-- Uploaded logo. Height-constrained so any aspect ratio fits the
             header without pushing its height around. --}}
        <img src="{{ Storage::disk('public')->url($path) }}"
             alt="{{ $name }}"
             style="height:34px; width:auto; max-width:180px; object-fit:contain">
    @elseif ($showName)
        @include('partials.brand-lockup', ['brandName' => $name])
    @else
        <img class="ico-logo__icon" src="{{ asset('ico-ext/brand/iconors-mark.svg') }}"
             alt="" width="20" height="38" decoding="async">
    @endif
</a>
