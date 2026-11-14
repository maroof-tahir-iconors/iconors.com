{{-- Date range picker (GET). Keeps other query params. --}}
<form method="GET" class="cs-range">
    @foreach (request()->except(['from', 'to', 'page']) as $k => $v)
        @if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <input class="cs-input" type="date" name="from" value="{{ $from->toDateString() }}" aria-label="From date" onchange="this.form.submit()">
    <span class="cs-sub">To</span>
    <input class="cs-input" type="date" name="to" value="{{ $to->toDateString() }}" aria-label="To date" onchange="this.form.submit()">
</form>
