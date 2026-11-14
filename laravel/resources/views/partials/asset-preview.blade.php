{{--
    One asset preview, for thumbnails outside the main asset tile
    (dashboard, downloads, collections, contributor uploads, review queue).

    Lottie previews are JSON, which an <img> cannot show — it rendered as a
    broken-image icon. They get the same player hook as the asset tile
    instead; app.js finds [data-ico-lottie] and plays it.

    Expects: $preview (AssetPreview), $alt (string, '' when decorative),
    $size (fallback width/height in px).
--}}
@php
    $previewUrl = Storage::disk($preview->disk)->url($preview->path);
    $previewIsLottie = $preview->type === 'lottie' || str_ends_with(strtolower((string) $preview->path), '.json');
    $previewAlt = (string) ($alt ?? '');
@endphp

@if ($previewIsLottie)
    <span class="ico-lottie ico-lottie--thumb" data-ico-lottie="{{ $previewUrl }}"
          @if ($previewAlt !== '') role="img" aria-label="{{ $previewAlt }} animation" @else aria-hidden="true" @endif>
        <span class="ico-lottie__poster" aria-hidden="true"><span class="ico-lottie__spinner"></span></span>
    </span>
@else
    <img src="{{ $previewUrl }}" alt="{{ $previewAlt }}" loading="lazy" decoding="async"
         width="{{ $preview->width ?: ($size ?? 240) }}" height="{{ $preview->height ?: ($size ?? 240) }}">
@endif
