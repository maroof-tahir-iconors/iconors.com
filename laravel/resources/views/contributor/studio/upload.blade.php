@extends('layouts.studio')
@section('title', 'Upload '.$type['label'])

@php
    $req = $type['required']; $opt = $type['optional'];
    $sample = 'My_'.Str::slug($type['singular'], '_');
@endphp

@section('content')
<div class="cs-bar">
    <div>
        <a class="cs-back" href="{{ route('contributor.type', $type['key']) }}"><i class="bi bi-chevron-left"></i> My {{ $type['label'] }}</a>
        <h1 class="cs-bar__title">Upload {{ $type['singular'] }}</h1>
        @if ($draft)<div class="cs-sub small mt-1">Adding to your draft{{ $draft->pack_title ? ' “'.$draft->pack_title.'”' : '' }} · <a href="{{ route('contributor.drafts.edit', [$type['key'], $draft]) }}" style="color:var(--ico-primary)">back to draft</a></div>@endif
    </div>
    <div class="cs-warn ms-lg-3 flex-grow-1"><i class="bi bi-exclamation-triangle"></i> To sell assets that were not created by you, you must have the ownership/licence of those assets.</div>
</div>

<div class="cs-card cs-upload">
    <div class="cs-upload__info">
        <h2 class="h5 mb-1">File type required</h2>
        <p class="cs-sub mb-0">We require the following file types for each {{ Str::lower($type['singular']) }}</p>
        <div class="cs-formats">
            <div class="cs-formats__group cs-formats__group--req">
                <div class="cs-formats__chips">@foreach ($req as $e)<b>{{ strtoupper($e) }}</b>@endforeach</div>
                <small>Required</small>
            </div>
            @if ($opt)
                <div class="cs-formats__group cs-formats__group--opt">
                    <div class="cs-formats__chips">@foreach ($opt as $e)<b>{{ strtoupper($e) }}</b>@endforeach</div>
                    <small>Optional</small>
                </div>
            @endif
        </div>

        <div class="cs-notes">
            <strong style="color:var(--ico-heading)">Notes:</strong>
            <ul>
                @foreach ($type['notes'] as $n)<li>{{ $n }}</li>@endforeach
                <li>Maximum {{ $type['max_mb'] }} MB per file. Uploads are saved to a draft first — there you choose the bundle and category, edit names, descriptions and tags, then submit the pack for review.</li>
                @if ($opt)<li><b>File naming:</b> give every file of one asset the same name so they are grouped together during upload:</li>@endif
            </ul>
            @if ($opt)
                <div class="cs-names">
                    @foreach (array_merge($req, $opt) as $e)
                        <div><i class="bi bi-file-earmark"></i>{{ $sample }}.{{ $e }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="cs-upload__drop-wrap"
         data-cs-uploader
         data-endpoint="{{ route('contributor.type.store', $type['key']) }}"
         data-required="{{ implode(',', $req) }}"
         data-allowed="{{ implode(',', array_merge($req, $opt)) }}"
         data-max-mb="{{ $type['max_mb'] }}"
         data-review-url="{{ route('contributor.type.status', [$type['key'], 'review']) }}"
         data-draft-new="{{ route('contributor.drafts.create', $type['key']) }}"
         @if ($draft) data-draft-id="{{ $draft->id }}" data-after-url="{{ route('contributor.drafts.edit', [$type['key'], $draft]) }}" @endif>
        <input type="file" multiple hidden accept="{{ implode(',', array_map(fn ($e) => '.'.$e, array_merge($req, $opt))) }}">
        <div class="cs-drop" data-cs-drop tabindex="0" role="button" aria-label="Click or drag and drop files to upload">
            <div data-cs-idle>
                <div class="cs-drop__plus"><i class="bi bi-plus-lg"></i></div>
                <div>Click or Drag and Drop to<br>upload {{ $type['label'] }}</div>
            </div>
            <div data-cs-busy hidden style="width:100%">
                <div style="display:flex;flex-direction:column;align-items:center;gap:.8rem">
                    <i class="bi bi-cloud-arrow-up-fill" style="font-size:2.4rem;color:#3b82f6"></i>
                    <div data-cs-busy-text>Uploading…</div>
                    <div class="cs-drop__bar"><span data-cs-bar>0%</span></div>
                </div>
            </div>
        </div>
        <div class="cs-results" data-cs-results hidden aria-live="polite"></div>
    </div>
</div>
@endsection
