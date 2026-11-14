<div class="cs-note mb-3">You'll get earnings for every "Pro Download" (subscription download) and sale. Earnings become available to withdraw after the holding period.</div>
<div class="cs-seg cs-seg--wide mb-4">
    <a class="{{ request()->routeIs('contributor.sales.activities') ? 'is-active' : '' }}" href="{{ route('contributor.sales.activities') }}">Sales Activities</a>
    <a class="{{ request()->routeIs('contributor.sales.overview') ? 'is-active' : '' }}" href="{{ route('contributor.sales.overview') }}">Sales Overview</a>
</div>
