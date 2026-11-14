@inject('settings', 'App\Services\Settings\SettingsService')
@php
    $contributor = auth()->user()->contributor;
    $studioAccess = \App\Services\Contributor\StudioTypes::accessFor($contributor);
    $studioTypes = \App\Services\Contributor\StudioTypes::all();
    $avatarUrl = $contributor?->avatar ? Storage::disk('public')->url($contributor->avatar) : null;
    $initial = Str::upper(Str::substr($contributor?->display_name ?? auth()->user()->name, 0, 1));

    $typeIcons = ['icons' => 'bullseye', 'illustrations' => 'bounding-box', '3d' => 'box', 'lottie' => 'slash-square'];

    $nav = [
        ['label' => 'Home', 'icon' => 'house', 'route' => 'contributor.dashboard', 'match' => ['contributor.dashboard']],
        ['label' => 'Learning Center', 'icon' => 'book', 'route' => 'contributor.learn', 'match' => ['contributor.learn']],
        ['label' => 'Keyword Insights', 'icon' => 'file-earmark-text', 'route' => 'contributor.keywords', 'match' => ['contributor.keywords']],
        ['sep' => true],
    ];
    foreach ($studioTypes as $key => $t) {
        $nav[] = ['label' => $t['label'], 'icon' => $typeIcons[$key], 'type' => $key, 'locked' => ($studioAccess[$key] ?? 'none') !== 'approved'];
    }
    $nav = array_merge($nav, [
        ['sep' => true],
        ['label' => 'Statistics', 'icon' => 'bar-chart', 'route' => 'contributor.sales.activities', 'match' => ['contributor.sales.*']],
        ['label' => 'Payout', 'icon' => 'cash-coin', 'route' => 'contributor.cashout', 'match' => ['contributor.cashout', 'contributor.earnings']],
        ['label' => 'Achievements', 'icon' => 'award', 'route' => 'contributor.achievements', 'match' => ['contributor.achievements']],
        ['label' => 'Shop Details', 'icon' => 'shop', 'route' => 'contributor.shop', 'match' => ['contributor.shop', 'contributor.profile']],
        ['label' => 'My Liked Assets', 'icon' => 'heart', 'route' => 'contributor.likes', 'match' => ['contributor.likes']],
        ['sep' => true],
        ['label' => 'Store Profile', 'icon' => 'person-square', 'url' => route('contributors.show', $contributor->slug), 'external' => true],
        ['label' => 'Support', 'icon' => 'question-circle', 'route' => 'contributor.help', 'match' => ['contributor.help']],
    ]);
    $currentType = request()->route('type');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Contributor Dashboard') — {{ $settings->get('site_name', 'Iconors') }}</title>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @if ($brandCss = $settings->brandCss())
        <style id="ico-brand-tokens">{!! $brandCss !!}</style>
    @endif
    <script>
        (function () {
            try {
                // The studio keeps its own theme (light by default) so it never
                // changes the public site's dark/light choice.
                document.documentElement.setAttribute('data-bs-theme', localStorage.getItem('cs-theme') || 'light');
                if (localStorage.getItem('cs-collapsed') === '1') { document.documentElement.classList.add('cs-pre-collapsed'); }
            } catch (e) {}
        })();
    </script>
    @include('partials.ext-head')
    @php $csAsset = fn ($f) => asset('ico-ext/'.$f).'?v='.(is_file(public_path('ico-ext/'.$f)) ? filemtime(public_path('ico-ext/'.$f)) : 1); @endphp
    <link rel="stylesheet" href="{{ $csAsset('studio.css') }}">
    <style>.cs-pre-collapsed .cs-side{width:76px}</style>
    @stack('meta')
    @stack('head')
</head>
<body class="cs-body">
<header class="cs-top">
    <button class="cs-icon-btn cs-burger" type="button" data-cs-burger aria-label="Open menu"><i class="bi bi-list"></i></button>
    <div class="cs-top__brand">
        <a class="ico-logo d-inline-flex align-items-center" href="{{ route('home') }}" aria-label="{{ $settings->get('site_name', 'Iconors') }} home">
            @include('partials.brand-lockup', ['brandName' => $settings->get('site_name', 'Iconors')])
        </a>
        <span class="cs-top__label">Contributor Studio</span>
    </div>
    <div class="cs-top__right">
        <a class="cs-top__link cs-top__terms" href="{{ route('contributor.help') }}#terms">Contributor Terms</a>
        <button class="cs-icon-btn" type="button" data-cs-theme-toggle aria-label="Switch to dark mode" title="Switch theme"><i class="bi bi-moon-stars" data-cs-theme-icon></i></button>
        <a class="cs-avatar" href="{{ route('contributor.shop') }}" title="{{ $contributor->display_name }}">
            @if ($avatarUrl)<img src="{{ $avatarUrl }}" alt="">@else{{ $initial }}@endif
        </a>
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button class="cs-icon-btn" type="submit" title="Log out" aria-label="Log out"><i class="bi bi-box-arrow-right"></i></button>
        </form>
    </div>
</header>

<div class="cs-shell" data-cs-shell>
    <aside class="cs-side" aria-label="Contributor navigation">
        <nav class="cs-nav">
            @foreach ($nav as $item)
                @if (! empty($item['sep']))
                    <div class="cs-nav__sep"></div>
                    @continue
                @endif
                @if (isset($item['type']))
                    @php $active = $currentType === $item['type']; @endphp
                    @if ($item['locked'])
                        <button type="button" class="cs-nav__link" title="{{ $item['label'] }}"
                                data-cs-request data-label="{{ $item['label'] }}"
                                data-action="{{ route('contributor.access.request', $item['type']) }}">
                            <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                            <i class="bi bi-lock-fill cs-nav__lock"></i>
                        </button>
                    @else
                        <a class="cs-nav__link {{ $active ? 'is-active' : '' }}" href="{{ route('contributor.type', $item['type']) }}" title="{{ $item['label'] }}">
                            <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                    @continue
                @endif
                @php $active = isset($item['match']) && request()->routeIs(...$item['match']); @endphp
                <a class="cs-nav__link {{ $active ? 'is-active' : '' }}" title="{{ $item['label'] }}"
                   href="{{ $item['url'] ?? route($item['route']) }}" @if(! empty($item['external'])) target="_blank" rel="noopener" @endif
                   @if($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
        <div class="cs-side__foot">
            <button class="cs-collapse" type="button" data-cs-collapse aria-label="Hide menu labels">&laquo;</button>
        </div>
    </aside>

    <main class="cs-main" id="contributor-main">
        @if (session('status'))
            <div class="cs-alert cs-alert--ok"><i class="bi bi-check-circle-fill me-1"></i> {{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="cs-alert cs-alert--err">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        @yield('content')
    </main>
</div>

{{-- "Request to Upload" — portfolio URL for a locked asset type --}}
<div class="cs-modal" id="csRequestModal" role="dialog" aria-modal="true" aria-labelledby="csRequestTitle">
    <div class="cs-modal__box">
        <div class="cs-modal__head">
            <h3 id="csRequestTitle">Fill up form for required details for apply</h3>
            <button class="cs-modal__close" type="button" data-cs-close aria-label="Close">&times;</button>
        </div>
        <form method="POST" action="#">
            @csrf
            <label for="csPortfolio">Portfolio URL</label>
            <input id="csPortfolio" type="url" name="portfolio_url" placeholder="Portfolio URL" required maxlength="255">
            <label for="csType">Asset Type</label>
            <input id="csType" type="text" data-cs-type-label readonly>
            <button class="cs-btn cs-btn--primary" type="submit">Send request</button>
        </form>
    </div>
</div>

<script>document.documentElement.classList.remove('cs-pre-collapsed');</script>
<script src="{{ $csAsset('studio.js') }}"></script>
@stack('scripts')
</body>
</html>
