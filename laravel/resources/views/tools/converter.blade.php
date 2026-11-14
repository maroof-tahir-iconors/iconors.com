@extends('layouts.app')

@section('title', 'Free Image Converter — PNG, JPG, WebP, ICO, PDF — Iconors')
@section('meta_description', 'Convert images between PNG, JPG, WebP, BMP, ICO and PDF for free. Works in your browser: no upload, no sign-up, no watermark. Batch convert and download as a ZIP.')

@section('content')
@include('tools.partials.assets', ['scripts' => ['vendor/zip.min.js', 'js/converter.js']])

@include('tools.partials.hero', [
    'heading' => 'Image Converter',
    'lead' => 'Convert PNG, JPG, WebP, SVG, GIF, BMP, AVIF and ICO files to the format you need — one image or a whole batch.',
    'perk' => 'Batch convert to ZIP',
])

<section class="tl-work">
    <div class="container-ico">
        <noscript><p class="tl-noscript">This tool needs JavaScript, because the conversion runs in your browser instead of on a server.</p></noscript>

        <div class="tl-layout" data-converter>
            <div class="ico-card tl-panel">
                <button class="tl-drop" type="button" data-drop aria-describedby="cv-drop-hint">
                    <span class="tl-drop__icon" aria-hidden="true"><i class="bi bi-cloud-arrow-up"></i></span>
                    <span class="tl-drop__title">Drop images here or <u>browse</u></span>
                    <span class="tl-drop__hint" id="cv-drop-hint">PNG, JPG, WebP, SVG, GIF, BMP, AVIF or ICO · up to 30 files, 40 MB each · you can also paste</span>
                </button>
                <input type="file" hidden multiple data-input
                       accept="image/png,image/jpeg,image/webp,image/gif,image/bmp,image/svg+xml,image/avif,image/x-icon,image/vnd.microsoft.icon,.png,.jpg,.jpeg,.webp,.gif,.bmp,.svg,.avif,.ico">

                <div class="tl-progress" hidden data-progress><span></span></div>

                <ul class="tl-files" data-list aria-label="Files to convert"></ul>
            </div>

            <aside class="tl-layout__side">
                <div class="ico-card tl-panel">
                    <h2 class="tl-h">Convert to</h2>

                    <div class="tl-field">
                        <div class="tl-seg tl-seg--3" role="radiogroup" aria-label="Output format" data-formats>
                            @foreach (['image/png' => 'PNG', 'image/jpeg' => 'JPG', 'image/webp' => 'WebP', 'image/bmp' => 'BMP', 'image/x-icon' => 'ICO', 'application/pdf' => 'PDF'] as $mime => $label)
                                <label>
                                    <input type="radio" name="cv-format" value="{{ $mime }}" @checked($loop->first)>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="tl-help" data-format-note></p>
                    </div>

                    <div class="tl-field" data-quality-field hidden>
                        <label class="tl-label" for="cv-quality">Quality <output data-quality-out>90%</output></label>
                        <input class="tl-range" id="cv-quality" type="range" min="10" max="100" step="1" value="90" data-quality>
                        <p class="tl-help">Higher keeps more detail and makes a bigger file.</p>
                    </div>

                    <div class="tl-field" data-bg-field hidden>
                        <label class="tl-label" for="cv-bg-text">Background</label>
                        <div class="tl-color">
                            <input type="color" value="#ffffff" data-bg aria-label="Background colour">
                            <input class="form-control" id="cv-bg-text" type="text" value="#FFFFFF" maxlength="7" spellcheck="false" autocomplete="off" data-bg-text>
                        </div>
                        <p class="tl-help">This format has no transparency, so see-through areas are filled with this colour.</p>
                    </div>

                    <div class="tl-field">
                        <label class="tl-label" for="cv-width">Width <span class="tl-label__value">optional</span></label>
                        <div class="input-group">
                            <input class="form-control" id="cv-width" type="number" inputmode="numeric" min="1" max="12000" step="1" placeholder="Original size" data-width>
                            <span class="input-group-text">px</span>
                        </div>
                        <p class="tl-help" data-width-help>Leave empty to keep each image's size. Height follows automatically.</p>
                    </div>

                    <div class="tl-actions tl-actions--stack">
                        <button class="btn btn-ico-primary" type="button" data-convert disabled>
                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i> <span data-convert-label>Convert</span>
                        </button>
                        <button class="btn btn-ico-outline" type="button" data-download-all hidden>
                            <i class="bi bi-download" aria-hidden="true"></i> <span data-download-label>Download all</span>
                        </button>
                        <button class="btn btn-ico-ghost btn-sm" type="button" data-clear hidden>
                            <i class="bi bi-trash3" aria-hidden="true"></i> Clear all
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

@include('tools.partials.more', [
    'steps' => [
        ['Add your images', 'Drop them on the page, pick them from your device, or paste from the clipboard. Mix formats freely.'],
        ['Choose a format', 'Pick PNG, JPG, WebP, BMP, ICO or PDF. Set the quality, a background colour or a new width if you need to.'],
        ['Convert and download', 'Save files one by one, or grab the whole batch as a single ZIP.'],
    ],
    'faqs' => [
        ['Are my images uploaded anywhere?', 'No. The conversion runs inside your browser using your own device. Your files are never sent to Iconors or to anyone else, which is also why it works without an account.'],
        ['Which formats can I convert?', 'You can open PNG, JPG, WebP, SVG, GIF, BMP, AVIF and ICO files, and save them as PNG, JPG, WebP, BMP, ICO or PDF. Animated GIFs are converted from their first frame.'],
        ['How do I turn an SVG into a PNG?', 'Add the SVG, choose PNG, and type the width you want in pixels. Because SVG is a vector format it stays sharp at any size you choose.'],
        ['Why did my transparent background turn white?', 'JPG, BMP and PDF cannot store transparency, so see-through areas are filled with a background colour. White is the default and you can change it. Choose PNG, WebP or ICO to keep transparency.'],
        ['What is inside the ICO file?', 'A multi-size icon containing 16, 32, 48, 64, 128 and 256 pixel versions, which is what Windows and browser favicons expect.'],
        ['Is there a limit?', 'Up to 30 files at a time, 40 MB each. There is no daily limit and nothing is watermarked.'],
    ],
])
@endsection
