@extends('layouts.app')

@section('title', 'Free Image Compressor — Shrink JPG, PNG and WebP — Iconors')
@section('meta_description', 'Compress JPG, PNG and WebP images for free. Adjust the quality, compare before and after, and see exactly how much smaller each file gets. Runs in your browser — nothing is uploaded.')

@section('content')
@include('tools.partials.assets', ['scripts' => ['vendor/zip.min.js', 'js/compressor.js']])

@include('tools.partials.hero', [
    'heading' => 'Image Compressor',
    'lead' => 'Make JPG, PNG and WebP files smaller without making them look worse. Slide the quality, compare the result, download.',
    'perk' => 'Live before and after',
])

<section class="tl-work">
    <div class="container-ico">
        <noscript><p class="tl-noscript">This tool needs JavaScript, because the compression runs in your browser instead of on a server.</p></noscript>

        {{-- The worker URL is resolved here so the script never has to guess
             where its own files live. --}}
        <div class="tl-layout" data-compressor
             data-worker="{{ \App\Services\Tools\ToolRegistry::asset('js/compress-worker.js') }}"
             data-upng="{{ \App\Services\Tools\ToolRegistry::asset('vendor/upng.min.js') }}">
            <div>
                <div class="ico-card tl-panel">
                    <button class="tl-drop" type="button" data-drop aria-describedby="cp-drop-hint">
                        <span class="tl-drop__icon" aria-hidden="true"><i class="bi bi-cloud-arrow-up"></i></span>
                        <span class="tl-drop__title">Drop images here or <u>browse</u></span>
                        <span class="tl-drop__hint" id="cp-drop-hint">JPG, PNG or WebP · up to 30 files, 40 MB each · you can also paste</span>
                    </button>
                    <input type="file" hidden multiple data-input
                           accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">

                    <div class="tl-summary" hidden data-summary>
                        <p class="tl-summary__text" data-summary-text aria-live="polite"></p>
                        <div class="tl-actions">
                            <button class="btn btn-ico-primary btn-sm" type="button" data-download-all>
                                <i class="bi bi-download" aria-hidden="true"></i> <span data-download-label>Download all</span>
                            </button>
                            <button class="btn btn-ico-ghost btn-sm" type="button" data-clear>
                                <i class="bi bi-trash3" aria-hidden="true"></i> Clear
                            </button>
                        </div>
                    </div>

                    <ul class="tl-files" data-list aria-label="Images"></ul>
                </div>

                <div class="ico-card tl-panel mt-3" hidden data-preview>
                    <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
                        <h2 class="tl-h mb-0">Before and after</h2>
                        <p class="tl-help m-0 text-truncate" data-preview-name></p>
                    </div>

                    <div class="tl-compare" data-compare>
                        <div class="tl-compare__stage">
                            <img alt="Original image" data-before>
                            <img class="tl-compare__after" alt="Compressed image" data-after>
                            <span class="tl-compare__divider" aria-hidden="true"></span>
                            <span class="tl-compare__tag tl-compare__tag--before" data-before-tag>Original</span>
                            <span class="tl-compare__tag tl-compare__tag--after" data-after-tag>Compressed</span>
                            <span class="tl-compare__busy" hidden data-compare-busy><span class="tl-spin"></span> Compressing</span>
                            <input class="tl-compare__range" type="range" min="0" max="100" step="0.5" value="50"
                                   aria-label="Comparison slider: left shows the original, right shows the compressed image" data-split>
                        </div>
                    </div>
                    <p class="tl-help">Drag the handle to compare. Left is the original, right is the compressed image.</p>
                </div>
            </div>

            <aside class="tl-layout__side">
                <div class="ico-card tl-panel">
                    <h2 class="tl-h">Settings</h2>

                    <div class="tl-field">
                        <label class="tl-label" for="cp-quality">Quality <output data-quality-out>75%</output></label>
                        <input class="tl-range" id="cp-quality" type="range" min="10" max="100" step="1" value="75" data-quality>
                        <div class="d-flex justify-content-between tl-help"><span>Smaller file</span><span>Better quality</span></div>
                    </div>

                    <div class="tl-field">
                        <span class="tl-label" id="cp-format-label">Save as</span>
                        <div class="tl-seg tl-seg--3" role="radiogroup" aria-labelledby="cp-format-label">
                            <label><input type="radio" name="cp-format" value="keep" checked><span>Same</span></label>
                            <label><input type="radio" name="cp-format" value="image/jpeg"><span>JPG</span></label>
                            <label><input type="radio" name="cp-format" value="image/webp"><span>WebP</span></label>
                        </div>
                        <p class="tl-help" data-format-note>Each file keeps its own format.</p>
                    </div>

                    <div class="tl-field">
                        <label class="tl-label" for="cp-max">Longest side</label>
                        <select class="form-select" id="cp-max" data-max>
                            <option value="0" selected>Keep original size</option>
                            <option value="3840">Up to 3840 px (4K)</option>
                            <option value="2560">Up to 2560 px</option>
                            <option value="1920">Up to 1920 px (Full HD)</option>
                            <option value="1280">Up to 1280 px</option>
                            <option value="800">Up to 800 px</option>
                        </select>
                        <p class="tl-help">Scaling down a large photo is the biggest saving of all. Smaller images are left alone.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

@include('tools.partials.more', [
    'steps' => [
        ['Add your images', 'Drop in JPG, PNG or WebP files. Compression starts straight away with a sensible default.'],
        ['Tune the quality', 'Move the slider and watch the file size and the preview update. Drag the comparison handle to check the detail.'],
        ['Download', 'Save a single image, or every image in one ZIP. The original files on your device are not changed.'],
    ],
    'faqs' => [
        ['Are my images uploaded anywhere?', 'No. Compression runs inside your browser using your own device. Your images are never sent to Iconors or to anyone else.'],
        ['How much smaller will my images get?', 'It depends on the image. Photos saved straight from a camera or phone often shrink by 50 to 80 percent at the default quality with no visible difference. Files that were already optimised shrink less.'],
        ['Which quality setting should I use?', 'Around 70 to 80 percent suits most photos on the web. Go lower for thumbnails and backgrounds, higher for product shots and anything people will zoom into. Use the before and after slider to check.'],
        ['How are PNG files compressed?', 'PNGs are reduced to a carefully chosen palette of up to 256 colours, the same idea used by tools such as pngquant. Transparency is kept. At 100 percent the PNG is re-saved without losing any colour.'],
        ['What if the compressed file is not smaller?', 'Then the original is kept and the file is marked as already optimised, so you never download something bigger than you started with.'],
        ['Does it keep transparency?', 'Yes for PNG and WebP. JPG cannot store transparency, so see-through areas become white if you save as JPG.'],
    ],
])
@endsection
