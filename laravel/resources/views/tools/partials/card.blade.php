{{-- One tool as a card. The stretched link makes the whole card clickable
     while keeping a single, sensibly named link for screen readers. --}}
@php $level = $level ?? 'h2'; @endphp
<article class="ico-card tl-card tl-tone--{{ $item['tone'] }}">
    <span class="ico-tool-chip ico-tool-chip--{{ $item['tone'] }}" aria-hidden="true">
        <i class="bi {{ $item['icon'] }}"></i>
    </span>
    <{{ $level }}>
        <a class="tl-card__link" href="{{ $item['url'] }}" style="color:inherit">{{ $item['name'] }}</a>
    </{{ $level }}>
    <p>{{ $item['description'] }}</p>
    <span class="tl-card__go" aria-hidden="true">Open tool <i class="bi bi-arrow-right"></i></span>
</article>
