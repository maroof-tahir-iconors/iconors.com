@extends('layouts.app')

@section('title', 'Free Online Image Editor — Crop, Resize, Filters, Text — Iconors')
@section('meta_description', 'Edit images for free in your browser: crop, rotate, resize, adjust colours, apply filters, draw and add text, then export as PNG, JPG or WebP. No upload and no sign-up.')

@section('content')
@include('tools.partials.assets', ['scripts' => ['js/editor.js']])

@include('tools.partials.hero', [
    'heading' => 'Image Editor',
    'lead' => 'Crop, rotate and resize a picture, tune its colours, add a filter, draw on it or drop in some text — then save it as PNG, JPG or WebP.',
    'perk' => 'Undo and redo',
])

<section class="tl-work">
    <div class="container-ico">
        <noscript><p class="tl-noscript">This tool needs JavaScript, because the editing happens in your browser instead of on a server.</p></noscript>

        <div data-editor>
            {{-- Start: pick an image --}}
            <div class="ico-card tl-panel" data-start>
                <button class="tl-drop" type="button" data-drop aria-describedby="ed-drop-hint">
                    <span class="tl-drop__icon" aria-hidden="true"><i class="bi bi-image"></i></span>
                    <span class="tl-drop__title">Drop an image here or <u>browse</u></span>
                    <span class="tl-drop__hint" id="ed-drop-hint">PNG, JPG, WebP, GIF, BMP, AVIF or SVG · up to 40 MB · you can also paste</span>
                </button>
                <input type="file" hidden data-input
                       accept="image/png,image/jpeg,image/webp,image/gif,image/bmp,image/avif,image/svg+xml,.png,.jpg,.jpeg,.webp,.gif,.bmp,.avif,.svg">
            </div>

            {{-- Workspace --}}
            <div class="ed-shell" hidden data-shell>
                <div class="ico-card">
                    <div class="ed-bar">
                        <div class="ed-bar__group">
                            <button class="tl-iconbtn" type="button" data-undo disabled aria-label="Undo" title="Undo (Ctrl+Z)"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                            <button class="tl-iconbtn" type="button" data-redo disabled aria-label="Redo" title="Redo (Ctrl+Shift+Z)"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                            <button class="btn btn-ico-ghost btn-sm" type="button" data-reset disabled>Reset</button>
                        </div>
                        <span class="ed-bar__info" data-info aria-live="polite"></span>
                        <div class="ed-bar__group">
                            <button class="btn btn-ico-ghost btn-sm" type="button" data-compare title="Hold to see the original">
                                <i class="bi bi-eye" aria-hidden="true"></i> <span class="d-none d-sm-inline">Original</span>
                            </button>
                            <button class="btn btn-ico-ghost btn-sm" type="button" data-new>
                                <i class="bi bi-folder2-open" aria-hidden="true"></i> <span class="d-none d-sm-inline">New image</span>
                            </button>
                        </div>
                    </div>

                    <div class="ed-stage" data-stage>
                        <div class="ed-canvas-wrap" data-wrap>
                            <canvas data-view aria-label="Image being edited"></canvas>
                            <canvas class="ed-overlay" data-ink hidden></canvas>
                            <div class="ed-crop" hidden data-crop>
                                <div class="ed-crop__box" data-crop-box>
                                    @foreach (['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'] as $h)
                                        <span class="ed-crop__h" data-h="{{ $h }}"></span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ico-card">
                    <div class="ed-tabs" role="tablist" aria-label="Editing tools">
                        @foreach ([
                            'crop' => ['bi-crop', 'Crop'],
                            'rotate' => ['bi-arrow-repeat', 'Rotate'],
                            'resize' => ['bi-aspect-ratio', 'Resize'],
                            'adjust' => ['bi-sliders', 'Adjust'],
                            'filters' => ['bi-magic', 'Filters'],
                            'draw' => ['bi-brush', 'Draw'],
                        ] as $key => [$icon, $label])
                            <button class="ed-tab" type="button" role="tab" id="ed-tab-{{ $key }}"
                                    aria-controls="ed-pane-{{ $key }}" aria-selected="{{ $key === 'adjust' ? 'true' : 'false' }}"
                                    data-tab="{{ $key }}">
                                <i class="bi {{ $icon }}" aria-hidden="true"></i>{{ $label }}
                            </button>
                        @endforeach
                    </div>

                    {{-- Crop --}}
                    <div class="ed-pane" role="tabpanel" id="ed-pane-crop" aria-labelledby="ed-tab-crop" hidden data-pane="crop">
                        <span class="tl-label" id="ed-ratio-label">Shape</span>
                        <div class="tl-seg tl-seg--3 mb-3" role="radiogroup" aria-labelledby="ed-ratio-label">
                            @foreach (['free' => 'Free', '1:1' => '1:1', '4:3' => '4:3', '16:9' => '16:9', '3:2' => '3:2', '9:16' => '9:16'] as $value => $label)
                                <label><input type="radio" name="ed-ratio" value="{{ $value }}" @checked($loop->first)><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                        <p class="tl-help mb-3" data-crop-size></p>
                        <div class="tl-actions tl-actions--stack">
                            <button class="btn btn-ico-primary" type="button" data-crop-apply><i class="bi bi-check-lg" aria-hidden="true"></i> Apply crop</button>
                        </div>
                        <p class="tl-help">Drag the box or its handles. Arrow keys nudge it when the box is focused.</p>
                    </div>

                    {{-- Rotate --}}
                    <div class="ed-pane" role="tabpanel" id="ed-pane-rotate" aria-labelledby="ed-tab-rotate" hidden data-pane="rotate">
                        <div class="d-grid gap-2" style="grid-template-columns:1fr 1fr">
                            <button class="btn btn-ico-outline btn-sm" type="button" data-rot="-90"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Left 90°</button>
                            <button class="btn btn-ico-outline btn-sm" type="button" data-rot="90"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Right 90°</button>
                            <button class="btn btn-ico-outline btn-sm" type="button" data-flip="h"><i class="bi bi-symmetry-vertical" aria-hidden="true"></i> Flip sideways</button>
                            <button class="btn btn-ico-outline btn-sm" type="button" data-flip="v"><i class="bi bi-symmetry-horizontal" aria-hidden="true"></i> Flip upside down</button>
                        </div>
                    </div>

                    {{-- Resize --}}
                    <div class="ed-pane" role="tabpanel" id="ed-pane-resize" aria-labelledby="ed-tab-resize" hidden data-pane="resize">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="tl-label" for="ed-w">Width</label>
                                <div class="input-group"><input class="form-control" id="ed-w" type="number" inputmode="numeric" min="1" max="8000" step="1" data-rw><span class="input-group-text">px</span></div>
                            </div>
                            <div class="col-6">
                                <label class="tl-label" for="ed-h">Height</label>
                                <div class="input-group"><input class="form-control" id="ed-h" type="number" inputmode="numeric" min="1" max="8000" step="1" data-rh><span class="input-group-text">px</span></div>
                            </div>
                        </div>
                        <div class="form-check my-3">
                            <input class="form-check-input" type="checkbox" id="ed-lock" checked data-lock>
                            <label class="form-check-label small" for="ed-lock">Keep proportions</label>
                        </div>
                        <p class="tl-help tl-help--error" role="alert" hidden data-resize-error></p>
                        <div class="tl-actions tl-actions--stack">
                            <button class="btn btn-ico-primary" type="button" data-resize-apply><i class="bi bi-check-lg" aria-hidden="true"></i> Apply size</button>
                        </div>
                    </div>

                    {{-- Adjust --}}
                    <div class="ed-pane" role="tabpanel" id="ed-pane-adjust" aria-labelledby="ed-tab-adjust" data-pane="adjust">
                        @foreach ([
                            'brightness' => ['Brightness', -100, 100],
                            'contrast' => ['Contrast', -100, 100],
                            'saturation' => ['Saturation', -100, 100],
                            'warmth' => ['Warmth', -100, 100],
                            'hue' => ['Hue', -180, 180],
                            'blur' => ['Blur', 0, 20],
                        ] as $key => [$label, $min, $max])
                            <div class="tl-field">
                                <label class="tl-label" for="ed-{{ $key }}">{{ $label }} <output data-out="{{ $key }}">0</output></label>
                                <input class="tl-range" id="ed-{{ $key }}" type="range" min="{{ $min }}" max="{{ $max }}" step="1" value="0" data-adj="{{ $key }}">
                            </div>
                        @endforeach
                        <button class="btn btn-ico-ghost btn-sm" type="button" data-adj-reset>Reset adjustments</button>
                    </div>

                    {{-- Filters --}}
                    <div class="ed-pane" role="tabpanel" id="ed-pane-filters" aria-labelledby="ed-tab-filters" hidden data-pane="filters">
                        <div class="ed-presets" data-presets></div>
                    </div>

                    {{-- Draw + text --}}
                    <div class="ed-pane" role="tabpanel" id="ed-pane-draw" aria-labelledby="ed-tab-draw" hidden data-pane="draw">
                        <span class="tl-label" id="ed-mode-label">Tool</span>
                        <div class="tl-seg tl-seg--2 mb-3" role="radiogroup" aria-labelledby="ed-mode-label">
                            <label><input type="radio" name="ed-mode" value="brush" checked><span><i class="bi bi-brush" aria-hidden="true"></i> Brush</span></label>
                            <label><input type="radio" name="ed-mode" value="text"><span><i class="bi bi-type" aria-hidden="true"></i> Text</span></label>
                        </div>

                        <div class="tl-field">
                            <label class="tl-label" for="ed-ink-text">Colour</label>
                            <div class="tl-color">
                                <input type="color" value="#e6174b" aria-label="Colour" data-ink-color>
                                <input class="form-control" id="ed-ink-text" type="text" value="#E6174B" maxlength="7" spellcheck="false" autocomplete="off" data-ink-text>
                            </div>
                        </div>

                        <div class="tl-field">
                            <label class="tl-label" for="ed-ink-size"><span data-ink-size-label>Brush size</span> <output data-ink-size-out>12</output></label>
                            <input class="tl-range" id="ed-ink-size" type="range" min="1" max="100" step="1" value="12" data-ink-size>
                        </div>

                        <div data-text-fields hidden>
                            <div class="tl-field">
                                <label class="tl-label" for="ed-text">Text</label>
                                <input class="form-control" id="ed-text" type="text" maxlength="120" placeholder="Type here" data-text>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="ed-bold" checked data-bold>
                                <label class="form-check-label small" for="ed-bold">Bold</label>
                            </div>
                            <div class="tl-actions tl-actions--stack">
                                <button class="btn btn-ico-primary" type="button" data-text-apply disabled><i class="bi bi-check-lg" aria-hidden="true"></i> Place text</button>
                            </div>
                            <p class="tl-help">Type, then drag the text on the image to where you want it.</p>
                        </div>
                        <p class="tl-help" data-brush-help>Draw straight onto the image with your mouse or finger.</p>
                    </div>

                    {{-- Export --}}
                    <div class="ed-export">
                        <h2 class="tl-h">Save</h2>
                        <div class="tl-seg tl-seg--3 mb-3" role="radiogroup" aria-label="File format">
                            <label><input type="radio" name="ed-format" value="image/png" checked><span>PNG</span></label>
                            <label><input type="radio" name="ed-format" value="image/jpeg"><span>JPG</span></label>
                            <label><input type="radio" name="ed-format" value="image/webp"><span>WebP</span></label>
                        </div>
                        <div class="tl-field" hidden data-eq-field>
                            <label class="tl-label" for="ed-quality">Quality <output data-eq-out>90%</output></label>
                            <input class="tl-range" id="ed-quality" type="range" min="10" max="100" step="1" value="90" data-eq>
                        </div>
                        <div class="tl-actions tl-actions--stack">
                            <button class="btn btn-ico-primary" type="button" data-save>
                                <i class="bi bi-download" aria-hidden="true"></i> Download image
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@include('tools.partials.more', [
    'steps' => [
        ['Open an image', 'Drop it on the page, choose it from your device, or paste a screenshot from your clipboard.'],
        ['Make your edits', 'Crop, rotate, resize, adjust the colours, try a filter, draw or add text. Undo any step you do not like.'],
        ['Download', 'Pick PNG, JPG or WebP and save the result. Your original file is never changed.'],
    ],
    'faqs' => [
        ['Is my image uploaded anywhere?', 'No. The editor works inside your browser using your own device. Your image is never sent to Iconors or to anyone else.'],
        ['What can I do with this editor?', 'Crop to any shape or a fixed ratio, rotate and flip, resize to exact pixel dimensions, adjust brightness, contrast, saturation, warmth, hue and blur, apply one-click filters, draw freehand and add text.'],
        ['Can I undo a change?', 'Yes. Use the undo and redo buttons, or Ctrl+Z and Ctrl+Shift+Z on a keyboard. Reset takes you back to the image as you opened it.'],
        ['Which format should I save in?', 'PNG keeps transparency and is best for logos, icons and screenshots. JPG makes small files for photos. WebP is smaller than both and works in every modern browser.'],
        ['Is there a size limit?', 'Files up to 40 MB can be opened. Very large pictures are scaled to 4096 pixels on their longest side so that editing stays smooth.'],
        ['Does it work on a phone?', 'Yes. Cropping, drawing and placing text all work with touch.'],
    ],
])
@endsection
