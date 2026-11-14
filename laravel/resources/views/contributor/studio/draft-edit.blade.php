@extends('layouts.studio')
@section('title', 'My Drafts — '.$type['label'])

@php
    $state = [
        'type' => $type['key'],
        'optional' => $type['optional'],
        'minTags' => $minTags,
        'maxTags' => $maxTags,
        'mockupAccept' => $type['key'] === 'lottie' ? 'video/mp4,video/webm,image/png,image/jpeg,image/webp' : 'image/png,image/jpeg,image/webp',
        'urls' => [
            'save' => route('contributor.drafts.save', [$type['key'], $draft]),
            'submit' => route('contributor.drafts.submit', [$type['key'], $draft]),
            'destroy' => route('contributor.drafts.destroy', [$type['key'], $draft]),
            'mockup' => route('contributor.drafts.mockups.store', [$type['key'], $draft]),
            'mockupDelete' => route('contributor.drafts.mockups.destroy', [$type['key'], $draft, '__ID__']),
            'item' => route('contributor.drafts.items.destroy', [$type['key'], $draft, '__ID__']),
            'format' => route('contributor.drafts.items.format', [$type['key'], $draft, '__ID__']),
            'bundles' => route('contributor.bundles.index', $type['key']),
            'bundleStore' => route('contributor.bundles.store', $type['key']),
            'suggest' => route('contributor.tags.suggest'),
        ],
        'draft' => [
            'pack_title' => $draft->pack_title,
            'description' => $draft->description,
            'is_premium' => $draft->is_premium,
            'price' => (float) $draft->price,
            'license_id' => $draft->license_id,
            'bundle_id' => $draft->bundle_id,
            'bundle_name' => $bundle?->name,
            'category_id' => $draft->category_id,
            'preview_ids' => array_map('intval', (array) $draft->preview_asset_ids),
        ],
        'items' => $items->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'description' => $a->description,
            'tags' => $a->tags->pluck('name')->values(),
            'formats' => array_values((array) ($a->metadata['formats'] ?? [])),
        ])->values(),
    ];
    $categoryName = $categories->firstWhere('id', $draft->category_id)?->name;
@endphp

@section('content')
<div class="cs-bar" style="align-items:flex-end">
    <div>
        <a class="cs-back" href="{{ route('contributor.type', $type['key']) }}"><i class="bi bi-chevron-left"></i> My {{ $type['label'] }}</a>
        <h1 class="cs-bar__title">My Drafts <small class="cs-sub ms-2" style="font-size:.95rem;font-weight:400" data-d-count>{{ $items->count() }} {{ $type['unit'] }}</small></h1>
    </div>
    <div class="d-flex gap-2 flex-wrap ms-auto">
        <button class="cs-btn cs-btn--sm" type="button" data-d-save>Save Draft</button>
        <button class="cs-btn cs-btn--sm" type="button" data-d-delete>Delete Draft</button>
        <a class="cs-btn cs-btn--sm" href="{{ route('contributor.type.upload', [$type['key'], 'draft' => $draft->id]) }}" data-d-more>Upload more</a>
        <button class="cs-btn cs-btn--sm" type="button" data-d-adv-toggle>Advanced Options</button>
        <button class="cs-btn cs-btn--sm cs-btn--primary" type="button" data-d-submit>Submit</button>
    </div>
</div>

<div class="cs-alert cs-alert--err" data-d-error hidden></div>
<div class="cs-alert cs-alert--ok" data-d-ok hidden></div>

{{-- Advanced options: apply one name / description / tag set to all items --}}
<div class="cs-card p-4 mb-4" data-d-adv hidden>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 m-0">Advanced options</h2>
        <button class="cs-icon-btn" type="button" data-d-adv-toggle aria-label="Close advanced options"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="cs-draft-adv">
        <div>
            <label class="cs-lbl" for="advName">Asset Name</label>
            <input id="advName" class="cs-input w-100" data-adv-name placeholder="Name for all assets">
            <label class="cs-lbl mt-3" for="advDesc">Asset Description</label>
            <input id="advDesc" class="cs-input w-100" data-adv-desc placeholder="Description for all assets">
        </div>
        <div class="cs-tagbox" data-adv-tags></div>
    </div>
    <div class="d-flex justify-content-between align-items-center gap-3 mt-3 flex-wrap">
        <p class="cs-sub small m-0" style="max-width:640px">Advanced option is used to apply the same name, description and tags to all items. Only fill in the details you want to apply.</p>
        <button class="cs-btn cs-btn--primary" type="button" data-adv-apply>Apply to all items</button>
    </div>
</div>

<div class="cs-draft-head">
    {{-- Mockups --}}
    <div>
        <div class="cs-mock-drop" data-d-mock-drop tabindex="0" role="button" aria-label="Upload pack mockup">
            <i class="bi bi-cloud-arrow-up" style="font-size:3.6rem;color:var(--ico-heading)"></i>
            <div class="cs-sub">format: {{ $type['key'] === 'lottie' ? 'MP4 / WebM video (or image)' : 'JPG / PNG / WebP' }}</div>
            <div class="cs-sub">size: <b style="color:var(--ico-heading)">1668 × 1112</b> · max 2 MB</div>
            <input type="file" hidden data-d-mock-input accept="{{ $state['mockupAccept'] }}" multiple>
        </div>
        <p class="cs-sub small mt-2">You can upload up to 8 different mockups. {{ $type['key'] === 'lottie' ? 'Animations use a video thumbnail.' : 'Static assets use an image thumbnail.' }}</p>
        <div class="cs-mock-list" data-d-mocks>
            @foreach ($mockups as $m)
                <div class="cs-mock" data-mock-id="{{ $m->id }}">
                    @if ($m->isVideo())<video src="{{ $m->url() }}" muted loop autoplay playsinline></video>@else<img src="{{ $m->url() }}" alt="">@endif
                    <button type="button" data-mock-del aria-label="Remove mockup">&times;</button>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Pack fields --}}
    <div class="cs-draft-fields">
        <div>
            <label class="cs-lbl" for="dTitle">Pack Title</label>
            <input id="dTitle" class="cs-input w-100" data-f="pack_title" maxlength="200" placeholder="Name of {{ Str::lower($type['singular']) }} pack" value="{{ $draft->pack_title }}">
        </div>
        <div>
            <label class="cs-lbl">Price</label>
            <div class="cs-price" data-d-price>
                <button type="button" data-v="0" class="{{ $draft->is_premium ? '' : 'is-active' }}">Free</button>
                <button type="button" data-v="1" class="{{ $draft->is_premium ? 'is-active' : '' }}">Premium</button>
            </div>
        </div>
        <div>
            <label class="cs-lbl" for="dDesc">Description</label>
            <textarea id="dDesc" class="cs-input w-100" rows="3" data-f="description" maxlength="2000" placeholder="Add description">{{ $draft->description }}</textarea>
        </div>
        <div>
            <div data-d-free @if($draft->is_premium) hidden @endif>
                <label class="cs-lbl" for="dLic">Select License</label>
                <select id="dLic" class="cs-select w-100" data-f="license_id">
                    @foreach ($freeLicenses as $l)<option value="{{ $l->id }}" @selected($draft->license_id == $l->id)>{{ $l->name }}</option>@endforeach
                </select>
            </div>
            <div data-d-premium @if(! $draft->is_premium) hidden @endif>
                <label class="cs-lbl">License</label>
                <div class="d-flex gap-2">
                    <input class="cs-input flex-grow-1" value="{{ $premiumLicense?->name ?? 'Iconors Standard License' }}" disabled>
                    <input class="cs-input" style="width:120px" type="number" step="0.01" min="0" data-f="price" value="{{ $draft->price > 0 ? $draft->price : '' }}" placeholder="Price $" aria-label="Price in USD">
                </div>
                <p class="cs-sub small mt-1 mb-0">Checkout isn't live yet — premium assets stay listed as Premium and become purchasable when it launches.</p>
            </div>
        </div>
        <div class="cs-span2">
            <label class="cs-lbl">Bundle</label>
            <button type="button" class="cs-input w-100 text-start d-flex justify-content-between align-items-center" data-d-bundle-open data-err-key="bundle_id">
                <span data-d-bundle-label style="font-weight:600">{{ $bundle?->name ?? 'Choose Bundle' }}</span><i class="bi bi-chevron-right"></i>
            </button>
            <p class="cs-sub small mt-1 mb-0">Make sure you choose the right bundle for your {{ Str::lower($type['singular']) }} pack.</p>
        </div>
        <div class="cs-span2 position-relative">
            <label class="cs-lbl">Category</label>
            <button type="button" class="cs-input w-100 text-start d-flex justify-content-between align-items-center" data-d-cat-open data-err-key="category_id">
                <span data-d-cat-label style="font-weight:600">{{ $categoryName ?? 'Select Category' }}</span><i class="bi bi-chevron-down"></i>
            </button>
            <div class="cs-cat-menu" data-d-cat-menu hidden>
                <input class="cs-input w-100 mb-2" type="search" placeholder="Search…" data-d-cat-search aria-label="Search categories">
                <div class="cs-cat-list">
                    @foreach ($categories as $cat)
                        <label><input type="radio" name="d_category" value="{{ $cat->id }}" @checked($draft->category_id == $cat->id)> <span>{{ $cat->name }}</span></label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Items --}}
<div class="cs-items" data-d-items>
    @foreach ($items as $a)
        @php
            $p = $a->previews->firstWhere('is_primary', true) ?? $a->previews->first();
            $formats = array_values((array) ($a->metadata['formats'] ?? []));
        @endphp
        <div class="cs-item" data-item="{{ $a->id }}" data-err-key="item_{{ $a->id }}">
            <div class="cs-item__media is-checker" data-item-media>
                @include('contributor.studio.partials.thumb', ['preview' => $p, 'alt' => $a->title])
                <div class="cs-item__bg" role="group" aria-label="Preview background">
                    <button type="button" data-bg="white" aria-label="White background"></button>
                    <button type="button" data-bg="dark" aria-label="Dark background"></button>
                    <button type="button" data-bg="checker" class="is-active" aria-label="Transparent background"></button>
                </div>
                <button type="button" class="cs-item__del" data-item-del title="Remove from draft" aria-label="Remove {{ $a->title }}"><i class="bi bi-trash3"></i></button>
            </div>
            <input class="cs-item__title" data-item-title value="{{ $a->title }}" maxlength="200" aria-label="Asset name">
            <textarea class="cs-item__desc" data-item-desc rows="1" maxlength="2000" placeholder="Description" aria-label="Asset description">{{ $a->description }}</textarea>
            <div class="cs-item__tags">
                <div class="cs-item__tagcol">
                    <div class="cs-item__taghead">
                        <span data-tag-count></span>
                        <span class="cs-links"><button type="button" data-tag-clear>Clear</button> • <button type="button" data-tag-copy>Copy</button> • <button type="button" data-tag-paste>Paste</button></span>
                    </div>
                    <div class="cs-chips" data-tag-list></div>
                    <input class="cs-tag-input" data-tag-input placeholder="Add tag and press Enter" aria-label="Add tag">
                </div>
                <div class="cs-item__tagcol cs-item__tagcol--sug">
                    <div class="cs-item__taghead"><span>Suggested Tags</span><span class="cs-links"><button type="button" data-tag-addall>Add all to Tags</button></span></div>
                    <div class="cs-chips" data-tag-sug><span class="cs-sub small">Loading…</span></div>
                </div>
            </div>
            <div class="cs-item__foot">
                <div class="cs-formats-row" data-formats>
                    @foreach (array_merge($type['required'], $type['optional']) as $ext)
                        @if (in_array($ext, $formats, true))
                            <span class="cs-fmt is-on"><i class="bi bi-check2"></i> {{ strtoupper($ext) }}</span>
                        @elseif (in_array($ext, $type['optional'], true))
                            <button type="button" class="cs-fmt" data-fmt-add="{{ $ext }}">+ {{ strtoupper($ext) }}</button>
                        @endif
                    @endforeach
                    <input type="file" hidden data-fmt-input>
                </div>
                <label class="cs-check-lbl"><input type="checkbox" data-item-preview @checked(in_array($a->id, array_map('intval', (array) $draft->preview_asset_ids)))> Pack Preview</label>
            </div>
        </div>
    @endforeach
</div>

{{-- Bundle picker --}}
<div class="cs-modal" data-d-bundle-modal role="dialog" aria-modal="true" aria-label="My Bundles">
    <div class="cs-modal__box" style="max-width:900px;padding:0;overflow:hidden">
        <div class="d-flex justify-content-between align-items-center gap-3 p-4">
            <h3>My Bundles</h3>
            <div class="d-flex gap-2 align-items-center">
                <input type="search" placeholder="Enter bundle name" data-d-bundle-search style="border-color:#e2e8f0;width:220px">
                <button class="cs-modal__close" type="button" data-d-bundle-close aria-label="Close">&times;</button>
            </div>
        </div>
        <div class="cs-bundle-grid" data-d-bundle-grid><div class="text-center p-4" style="color:#64748b">Loading…</div></div>
        <div class="d-flex justify-content-between align-items-center gap-2 p-4" style="border-top:1px solid #e2e8f0">
            <div class="d-flex gap-2 align-items-center" data-d-bundle-new-wrap>
                <button class="cs-btn" type="button" style="color:#0b0a1a !important;border-color:#cbd5e1" data-d-bundle-new>New Bundle</button>
                <input type="text" placeholder="New bundle name" data-d-bundle-new-name hidden style="border-color:#e2e8f0;width:220px">
                <button class="cs-btn" type="button" data-d-bundle-create hidden style="background:#0f172a;color:#fff !important;border-color:#0f172a">Create</button>
            </div>
            <button class="cs-btn cs-btn--primary" type="button" data-d-bundle-continue disabled style="width:auto;margin:0">Continue</button>
        </div>
    </div>
</div>

{{-- Delete confirm --}}
<div class="cs-modal" data-d-del-modal role="dialog" aria-modal="true" aria-label="Delete draft">
    <div class="cs-modal__box text-center" style="max-width:500px">
        <div style="width:96px;height:96px;border-radius:50%;background:#fee2e2;display:grid;place-items:center;margin:0 auto 1rem"><i class="bi bi-trash3" style="font-size:2.6rem;color:#f87171"></i></div>
        <h3 class="mb-2">Are you sure want to delete?</h3>
        <p style="color:#475569;font-size:.9rem">You can't undo once you delete the pack. Be sure before taking action.</p>
        <div class="d-flex gap-2 justify-content-center">
            <button class="cs-btn" type="button" data-d-del-no style="background:#3b82f6;border-color:#3b82f6">No, Keep it</button>
            <button class="cs-btn" type="button" data-d-del-yes style="color:#0b0a1a !important;border-color:#cbd5e1">Yes, Delete it</button>
        </div>
    </div>
</div>

<script type="application/json" id="csDraftState">{!! json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection

@push('scripts')
    <script src="{{ asset('ico-ext/studio-draft.js') }}?v={{ is_file(public_path('ico-ext/studio-draft.js')) ? filemtime(public_path('ico-ext/studio-draft.js')) : 1 }}"></script>
@endpush
