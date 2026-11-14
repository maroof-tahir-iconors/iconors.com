{{--
    Tool page header: breadcrumb, icon, title, one-line pitch and the
    three reassurances every tool shares.

    Kept short on purpose — the tool itself must start above the fold.
--}}
<section class="tl-hero tl-tone--{{ $tool['tone'] }}">
    <div class="tl-hero__glow" aria-hidden="true"></div>

    <div class="container-ico">
        <nav aria-label="Breadcrumb">
            <ol class="tl-crumbs">
                <li><a href="{{ route('home') }}">Home</a></li>
                <li><a href="{{ route('tools.index') }}">Tools</a></li>
                <li><span aria-current="page">{{ $tool['nav'] }}</span></li>
            </ol>
        </nav>

        <div class="tl-hero__head">
            <span class="ico-tool-chip ico-tool-chip--{{ $tool['tone'] }} tl-hero__chip" aria-hidden="true">
                <i class="bi {{ $tool['icon'] }}"></i>
            </span>
            <div>
                <h1 class="tl-hero__title">{{ $heading }}</h1>
                <p class="tl-hero__lead">{{ $lead }}</p>
            </div>
        </div>

        <ul class="tl-pills" role="list">
            <li><i class="bi bi-gift" aria-hidden="true"></i> Free, no sign-up</li>
            <li><i class="bi bi-shield-check" aria-hidden="true"></i> Files stay on your device</li>
            <li><i class="bi bi-lightning-charge" aria-hidden="true"></i> {{ $perk ?? 'No watermark' }}</li>
        </ul>
    </div>
</section>
