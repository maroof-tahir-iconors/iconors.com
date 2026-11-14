@props(['pack'])

{{--
    Pack tile: one large preview, three small ones, then the pack name and
    how many assets it holds. The whole card is a single link to the pack
    page.

    Expects "coverAssets" to have been attached by PackShowcase. A pack with
    fewer than four assets simply leaves the spare tiles blank.

    The previews are decorative — the title link is the accessible name — so
    the mosaic is hidden from assistive tech instead of announcing four
    unlabelled animations per card.
--}}
@php
    $covers = $pack->relationLoaded('coverAssets') ? $pack->coverAssets : collect();
    $count = (int) ($pack->published_assets_count ?? $pack->assets_count);
    $unit = Str::plural($covers->first()?->assetType?->name ?? 'asset', $count);

    $cells = [];

    for ($i = 0; $i < 4; $i++) {
        $cover = $covers->get($i);
        $preview = $cover
            ? ($cover->previews->firstWhere('is_primary', true) ?? $cover->previews->first())
            : null;

        $cells[] = $preview ? [
            'url' => Storage::disk($preview->disk)->url($preview->path),
            'lottie' => $preview->type === 'lottie' || str_ends_with($preview->path, '.json'),
        ] : null;
    }
@endphp

<article class="ico-card pack-card">
    @if ($pack->is_premium)
        <span class="pack-card__badge badge-premium">Premium</span>
    @endif

    <div class="pack-card__mosaic" aria-hidden="true">
        @foreach ($cells as $index => $cell)
            @if ($index === 1)
                <div class="pack-card__side">
            @endif

            <div class="pack-card__cell {{ $index === 0 ? 'pack-card__cell--lead' : '' }}">
                @if ($cell && $cell['lottie'])
                    {{-- Same player hook as the asset tile: lazy-loaded,
                         plays in view, pauses out of view. --}}
                    <div class="ico-lottie" data-ico-lottie="{{ $cell['url'] }}">
                        <span class="ico-lottie__poster">
                            <span class="ico-lottie__spinner"></span>
                        </span>
                    </div>
                @elseif ($cell)
                    <img src="{{ $cell['url'] }}" alt="" loading="lazy" decoding="async"
                         width="160" height="160">
                @elseif ($index === 0)
                    <i class="bi bi-collection"></i>
                @endif
            </div>

            @if ($index === 3)
                </div>
            @endif
        @endforeach
    </div>

    <div class="pack-card__body">
        <h3 class="pack-card__title">
            <a class="stretched-link text-reset" href="{{ route('packs.show', $pack->slug) }}"
               title="{{ $pack->title }}">{{ $pack->title }}</a>
        </h3>
        <span class="pack-card__count">{{ number_format($count) }} {{ $unit }}</span>
    </div>
</article>
