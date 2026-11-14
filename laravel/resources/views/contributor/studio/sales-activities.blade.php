@extends('layouts.studio')
@section('title', 'Sales Activities')

@php
    $labels = ['subscription' => 'Pro download', 'purchase' => 'Sale', 'free' => 'Free download', 'api' => 'API download', 'team' => 'Team download', 'admin' => 'Staff download'];
@endphp

@section('content')
@include('contributor.studio.partials.stats-tabs')

<div class="cs-bar">
    <h1 class="cs-bar__title">Sales Activities</h1>
    <div class="mx-auto">@include('contributor.studio.partials.price-tabs')</div>
    @include('contributor.studio.partials.range')
</div>

<div class="cs-card" style="overflow:hidden">
    <div class="cs-table-wrap">
        <table class="cs-table">
            <thead><tr><th>Image</th><th>Date &amp; Time</th><th>Earning</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        <div class="cs-table__asset">
                            <span class="cs-table__thumb">@include('contributor.studio.partials.thumb', ['preview' => $previews[$row->asset_id] ?? null, 'alt' => $row->title])</span>
                            <span><span style="color:var(--ico-heading)">{{ $row->title }}</span><br><a href="{{ route('assets.show', $row->slug) }}" target="_blank" rel="noopener">View</a></span>
                        </div>
                    </td>
                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $labels[$row->access_type] ?? Str::headline($row->access_type) }}
                        <i class="bi bi-question-circle ms-1" title="{{ $row->access_type === 'subscription' ? 'Downloaded by a subscriber — earns subscription royalties.' : 'Free downloads do not earn royalties.' }}"></i></td>
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
