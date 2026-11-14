@extends('layouts.studio')
@section('title', 'Like Activities')

@section('content')
<div class="cs-bar">
    <h1 class="cs-bar__title">Like Activities</h1>
    @include('contributor.studio.partials.price-tabs')
    <div class="cs-seg mx-auto">
        <a class="{{ $mode === 'item' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['type' => null, 'page' => null]) }}">Item</a>
        <a class="{{ $mode === 'pack' ? 'is-active' : '' }}" href="{{ request()->fullUrlWithQuery(['type' => 'pack', 'page' => null]) }}">Pack</a>
    </div>
    @include('contributor.studio.partials.range')
</div>

<div class="cs-card" style="overflow:hidden">
    <div class="cs-table-wrap">
        <table class="cs-table">
            <thead><tr><th>{{ $mode === 'pack' ? 'Pack' : 'Image' }}</th>@if($mode === 'pack')<th>Likes</th>@endif<th>Date &amp; Time</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    @if ($mode === 'pack')
                        <td><a href="{{ route('packs.show', $row->slug) }}" target="_blank" rel="noopener" style="color:var(--ico-heading)">{{ $row->title }}</a></td>
                        <td>{{ number_format($row->likes) }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->last_at)->format('Y-m-d H:i:s') }}</td>
                    @else
                        <td>
                            <div class="cs-table__asset">
                                <span class="cs-table__thumb">@include('contributor.studio.partials.thumb', ['preview' => $previews[$row->asset_id] ?? null, 'alt' => $row->title])</span>
                                <span><span style="color:var(--ico-heading)">{{ $row->title }}</span><br><a href="{{ route('assets.show', $row->slug) }}" target="_blank" rel="noopener">View</a></span>
                            </div>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y-m-d H:i:s') }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="3" class="cs-empty">No result found</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="cs-pager"><span>PAGE {{ $rows->currentPage() }} OF {{ max(1, $rows->lastPage()) }}</span>{{ $rows->links() }}</div>
</div>
@endsection
