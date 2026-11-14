@inject('settings', 'App\Services\Settings\SettingsService')
@inject('navTabs', 'App\Services\Catalogue\NavTabs')
@inject('toolRegistry', 'App\Services\Tools\ToolRegistry')

@php
    // Catalogue tabs and tools come from one place each, so the desktop
    // bar and the mobile menu below can never disagree.
    $icoTabs = $navTabs->all();
    $icoTools = $toolRegistry->all();
    $icoOnTools = request()->routeIs('tools.*');
    $icoOnFree = request()->routeIs('assets.index') && request('price') === 'free';
@endphp

{{--
    Site header.

    Left to right on desktop: logo · catalogue tabs + Tools menu · search ·
    Free / Pricing · theme and account. Below 1200px the tabs and links
    move into the off-canvas menu and the search takes the freed space;
    below 768px the search moves in there too.

    Styles live in public/ico-ext/ico-ext.css (the server has no Node
    toolchain, so they cannot go through the SCSS build).
--}}
<header class="ico-header ico-hx" data-ico-header>
    <div class="container-ico">
        <div class="ico-hx__bar">

            {{-- Brand. Uses the admin-uploaded logo when one exists. --}}
            <x-brand-logo variant="main" />

            {{-- Catalogue tabs + Tools --}}
            <nav class="ico-hx__nav" aria-label="Primary">
                <ul class="ico-nav" role="list">
                    @foreach ($icoTabs as $tab)
                        <li>
                            <a href="{{ $tab['url'] }}"
                               @if ($tab['active']) aria-current="page" @endif>{{ $tab['label'] }}</a>
                        </li>
                    @endforeach

                    <li class="dropdown ico-hx__tools" data-ico-tools>
                        <button class="ico-hx__tools-btn {{ $icoOnTools ? 'is-active' : '' }}" type="button"
                                id="icoToolsMenu" data-bs-toggle="dropdown" data-bs-display="static"
                                aria-expanded="false">
                            Tools
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </button>

                        <div class="dropdown-menu ico-tools-menu" aria-labelledby="icoToolsMenu">
                            <p class="ico-tools-menu__eyebrow">Free tools</p>

                            @foreach ($icoTools as $tool)
                                <a class="dropdown-item ico-tools-menu__item" href="{{ $tool['url'] }}"
                                   @if (request()->route('tool') === $tool['key']) aria-current="page" @endif>
                                    <span class="ico-tool-chip ico-tool-chip--{{ $tool['tone'] }}" aria-hidden="true">
                                        <i class="bi {{ $tool['icon'] }}"></i>
                                    </span>
                                    <span class="ico-tools-menu__text">
                                        <strong>{{ $tool['nav'] }}</strong>
                                        <span>{{ $tool['tagline'] }}</span>
                                    </span>
                                    <i class="bi bi-arrow-right-short ico-tools-menu__arrow" aria-hidden="true"></i>
                                </a>
                            @endforeach

                            <div class="ico-tools-menu__foot">
                                <span><i class="bi bi-shield-check" aria-hidden="true"></i> Files never leave your device</span>
                                <a href="{{ route('tools.index') }}">All tools <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </li>
                </ul>
            </nav>

            {{-- Search. Keeps the data-ico-search-* hooks app.js binds the
                 Ctrl/Cmd+K shortcut and the typeahead to. --}}
            <form class="ico-search ico-hx__search" action="{{ route('assets.index') }}" method="GET" role="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label for="ico-search-input" class="visually-hidden">Search assets</label>
                <input id="ico-search-input" type="search" name="q"
                       value="{{ request('q') }}"
                       placeholder="Search assets…"
                       autocomplete="off" data-ico-search-input>
                <kbd aria-hidden="true">Ctrl K</kbd>
                <button class="visually-hidden" type="submit" tabindex="-1">Search</button>

                <div class="ico-card ico-hx__suggest" hidden data-ico-search-results
                     role="listbox" aria-label="Search suggestions"></div>
            </form>

            {{-- Free / Pricing --}}
            <ul class="ico-nav ico-hx__links" role="list">
                <li>
                    <a href="{{ route('assets.index', ['price' => 'free']) }}"
                       @if ($icoOnFree) aria-current="page" @endif>Free</a>
                </li>
                <li>
                    <a href="{{ route('pricing') }}"
                       @if (request()->routeIs('pricing')) aria-current="page" @endif>Pricing</a>
                </li>
            </ul>

            {{-- Theme + account --}}
            <div class="ico-hx__actions">
                <button class="btn btn-ico-ghost btn-sm ico-hx__icon-btn d-md-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#icoMobileNav"
                        aria-controls="icoMobileNav" aria-label="Search" data-ico-mobile-search>
                    <i class="bi bi-search" aria-hidden="true"></i>
                </button>

                <button class="btn btn-ico-ghost btn-sm ico-hx__icon-btn d-none d-md-inline-flex" type="button"
                        data-ico-theme-toggle aria-pressed="false" aria-label="Toggle dark mode">
                    <i class="bi bi-circle-half" aria-hidden="true"></i>
                </button>

                @auth
                    @php
                        $icoUser = auth()->user();
                        $icoPlan = $icoUser->currentPlan();
                        $icoAvatar = $icoUser->avatarUrl();
                    @endphp

                    <div class="dropdown ico-account">
                        <button class="ico-account__trigger" type="button"
                                id="icoAccountMenu" data-bs-toggle="dropdown"
                                data-bs-display="static" aria-expanded="false">
                            <span class="ico-account__avatar" aria-hidden="true">
                                @if ($icoAvatar)
                                    <img src="{{ $icoAvatar }}" alt="" width="34" height="34"
                                         loading="lazy" decoding="async">
                                @else
                                    {{ $icoUser->initials() }}
                                @endif
                            </span>
                            <span class="visually-hidden">Account menu for {{ $icoUser->name }}</span>
                            <i class="bi bi-chevron-down ico-account__caret d-none d-md-inline"
                               aria-hidden="true"></i>
                        </button>

                        <div class="dropdown-menu dropdown-menu-end ico-account__menu"
                             aria-labelledby="icoAccountMenu">

                            <div class="ico-account__identity">
                                <span class="ico-account__avatar ico-account__avatar--lg" aria-hidden="true">
                                    @if ($icoAvatar)
                                        <img src="{{ $icoAvatar }}" alt="" width="44" height="44"
                                             loading="lazy" decoding="async">
                                    @else
                                        {{ $icoUser->initials() }}
                                    @endif
                                </span>
                                <span class="ico-account__identity-text">
                                    <strong>{{ $icoUser->name }}</strong>
                                    <span>{{ $icoPlan?->name ?? 'Free' }} Plan</span>
                                </span>
                            </div>

                            <hr class="dropdown-divider">

                            <a class="dropdown-item" href="{{ route('dashboard') }}">
                                <i class="bi bi-grid" aria-hidden="true"></i> Go to Dashboard
                            </a>
                            <a class="dropdown-item" href="{{ route('account.downloads') }}">
                                <i class="bi bi-download" aria-hidden="true"></i> My Downloads
                            </a>
                            <a class="dropdown-item" href="{{ route('account.collections') }}">
                                <i class="bi bi-collection" aria-hidden="true"></i> My Collections
                            </a>
                            <a class="dropdown-item" href="{{ route('account.following') }}">
                                <i class="bi bi-people" aria-hidden="true"></i> My Following
                            </a>
                            <a class="dropdown-item" href="{{ route('account.members') }}">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> Invite Members
                            </a>

                            @if ($icoUser->contributor && $icoUser->contributor->status === 'approved')
                                <hr class="dropdown-divider">
                                <a class="dropdown-item" href="{{ route('contributor.dashboard') }}">
                                    <i class="bi bi-easel" aria-hidden="true"></i> Contributor studio
                                </a>
                            @endif

                            @can('settings.manage')
                                <hr class="dropdown-divider">
                                <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                                    <i class="bi bi-shield-lock" aria-hidden="true"></i> Admin dashboard
                                </a>
                            @endcan

                            <hr class="dropdown-divider">

                            {{-- POST, not a link: logging out via GET is CSRF-able
                                 and can be triggered by a prefetching browser. --}}
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item ico-account__logout">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a class="btn btn-ico-ghost btn-sm d-none d-md-inline-flex"
                       href="{{ route('login') }}">Log in</a>
                    <a class="btn btn-ico-primary btn-sm d-none d-md-inline-flex"
                       href="{{ route('register') }}">Sign up</a>
                @endauth

                <button class="btn btn-ico-ghost btn-sm ico-hx__icon-btn d-xl-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#icoMobileNav"
                        aria-controls="icoMobileNav" aria-label="Open menu">
                    <i class="bi bi-list fs-5" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</header>

{{-- Mobile off-canvas (brief §6, §35) --}}
<div class="offcanvas offcanvas-end ico-hx-drawer" tabindex="-1" id="icoMobileNav" aria-labelledby="icoMobileNavLabel">
    <div class="offcanvas-header border-bottom">
        <h2 class="h6 mb-0" id="icoMobileNavLabel">{{ $settings->get('site_name', 'Iconors') }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form class="ico-search mb-3" action="{{ route('assets.index') }}" method="GET" role="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <label for="ico-search-mobile" class="visually-hidden">Search assets</label>
            <input id="ico-search-mobile" type="search" name="q" value="{{ request('q') }}"
                   placeholder="Search assets…">
        </form>

        <p class="ico-hx-drawer__heading">Browse</p>
        <ul class="list-unstyled d-grid gap-1 mb-3" role="list">
            @foreach ($icoTabs as $tab)
                <li>
                    <a class="ico-hx-drawer__link" href="{{ $tab['url'] }}"
                       @if ($tab['active']) aria-current="page" @endif>
                        <i class="bi {{ $tab['icon'] }}" aria-hidden="true"></i>
                        <span>{{ $tab['label'] }}</span>
                        @unless ($tab['ready'])
                            <span class="ico-hx-drawer__soon">Soon</span>
                        @endunless
                    </a>
                </li>
            @endforeach
        </ul>

        <p class="ico-hx-drawer__heading">Free tools</p>
        <ul class="list-unstyled d-grid gap-1 mb-3" role="list">
            @foreach ($icoTools as $tool)
                <li>
                    <a class="ico-tools-menu__item" href="{{ $tool['url'] }}"
                       @if (request()->route('tool') === $tool['key']) aria-current="page" @endif>
                        <span class="ico-tool-chip ico-tool-chip--{{ $tool['tone'] }}" aria-hidden="true">
                            <i class="bi {{ $tool['icon'] }}"></i>
                        </span>
                        <span class="ico-tools-menu__text">
                            <strong>{{ $tool['nav'] }}</strong>
                            <span>{{ $tool['tagline'] }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <ul class="list-unstyled d-grid gap-1 mb-3" role="list">
            <li><hr class="my-2" style="border-color:var(--ico-border)"></li>
            <li><a class="filter-link" href="{{ route('assets.index', ['price' => 'free']) }}">Free assets</a></li>
            <li><a class="filter-link" href="{{ route('assets.index', ['sort' => 'trending']) }}">Trending</a></li>
            <li><a class="filter-link" href="{{ route('pricing') }}">Pricing</a></li>
            <li><a class="filter-link" href="{{ route('blog') }}">Blog</a></li>
            <li><a class="filter-link" href="{{ route('about') }}">About</a></li>
            <li><a class="filter-link" href="{{ route('contributor.apply') }}">Become a contributor</a></li>
        </ul>

        @auth
            <ul class="list-unstyled d-grid gap-1 mb-3" role="list">
                <li><a class="filter-link" href="{{ route('account.collections') }}">My Collections</a></li>
                <li><a class="filter-link" href="{{ route('account.downloads') }}">My Downloads</a></li>
                <li><a class="filter-link" href="{{ route('account.following') }}">My Following</a></li>
                <li><a class="filter-link" href="{{ route('account.details') }}">Account Details</a></li>
                <li><a class="filter-link" href="{{ route('account.subscription') }}">Subscription</a></li>
            </ul>
        @endauth

        <div class="d-grid gap-2">
            @auth
                <a class="btn btn-ico-primary" href="{{ route('dashboard') }}">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}" class="d-grid">
                    @csrf
                    <button type="submit" class="btn btn-ico-outline">Logout</button>
                </form>
            @else
                <a class="btn btn-ico-outline" href="{{ route('login') }}">Log in</a>
                <a class="btn btn-ico-primary" href="{{ route('register') }}">Create free account</a>
            @endauth
            <button class="btn btn-ico-ghost" type="button" data-ico-theme-toggle aria-pressed="false">
                <i class="bi bi-circle-half me-1" aria-hidden="true"></i> Toggle theme
            </button>
        </div>
    </div>
</div>
