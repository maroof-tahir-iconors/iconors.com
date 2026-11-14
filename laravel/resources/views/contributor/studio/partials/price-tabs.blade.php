<div class="cs-seg cs-seg--dark">
    @foreach (['all' => 'All', 'free' => 'Free', 'premium' => 'Premium'] as $k => $label)
        <a class="{{ $price === $k ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['price' => $k === 'all' ? null : $k, 'page' => null]) }}">{{ $label }}</a>
    @endforeach
</div>
