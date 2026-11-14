{{-- Preview thumbnail. Expects $preview (row or model with disk/path/type) and $alt. --}}
@if ($preview)
    @php $u = Storage::disk($preview->disk)->url($preview->path); @endphp
    @if ($preview->type === 'lottie' || str_ends_with($preview->path, '.json'))
        <div class="ico-lottie" data-ico-lottie="{{ $u }}" role="img" aria-label="{{ $alt }}"><span class="ico-lottie__poster"><span class="ico-lottie__spinner"></span></span></div>
    @else
        <img src="{{ $u }}" alt="{{ $alt }}" loading="lazy" decoding="async">
    @endif
@else
    <i class="bi bi-image" style="font-size:1.6rem;opacity:.35;color:#64748b"></i>
@endif
