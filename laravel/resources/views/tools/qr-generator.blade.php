@extends('layouts.app')

@section('title', 'Free QR Code Generator — Custom Colours, Logo, PNG and SVG — Iconors')
@section('meta_description', 'Make a QR code for a link, text, email or Wi-Fi network. Choose colours and shapes, add your logo and download as PNG or SVG. Free, no sign-up, and the codes never expire.')

@section('content')
@include('tools.partials.assets', ['scripts' => ['vendor/qr-code-styling.min.js', 'js/qr.js']])

@include('tools.partials.hero', [
    'heading' => 'QR Code Generator',
    'lead' => 'Make a QR code for a link, some text, an email or your Wi-Fi. Style it to match your brand and download it as PNG or SVG.',
    'perk' => 'Codes never expire',
])

<section class="tl-work">
    <div class="container-ico">
        <noscript><p class="tl-noscript">This tool needs JavaScript, because the QR code is drawn in your browser instead of on a server.</p></noscript>

        <div class="tl-layout tl-layout--wide-side" data-qr>
            <div class="d-grid gap-3">
                {{-- Content --}}
                <div class="ico-card tl-panel">
                    <h2 class="tl-h">Content</h2>

                    <div class="tl-field">
                        <div class="tl-seg tl-seg--4" role="radiogroup" aria-label="What the QR code contains">
                            <label><input type="radio" name="qr-kind" value="url" checked><span><i class="bi bi-link-45deg" aria-hidden="true"></i> Link</span></label>
                            <label><input type="radio" name="qr-kind" value="text"><span><i class="bi bi-card-text" aria-hidden="true"></i> Text</span></label>
                            <label><input type="radio" name="qr-kind" value="email"><span><i class="bi bi-envelope" aria-hidden="true"></i> Email</span></label>
                            <label><input type="radio" name="qr-kind" value="wifi"><span><i class="bi bi-wifi" aria-hidden="true"></i> Wi-Fi</span></label>
                        </div>
                    </div>

                    <div data-kind="url">
                        <div class="tl-field">
                            <label class="tl-label" for="qr-url">Website address</label>
                            <input class="form-control" id="qr-url" type="url" inputmode="url" autocomplete="off" spellcheck="false"
                                   placeholder="https://example.com" value="https://iconors.com" data-f="url">
                        </div>
                    </div>

                    <div data-kind="text" hidden>
                        <div class="tl-field">
                            <label class="tl-label" for="qr-text">Text <span class="tl-label__value" data-text-count>0 / 1000</span></label>
                            <textarea class="form-control" id="qr-text" rows="4" maxlength="1000" placeholder="Anything you want people to read when they scan" data-f="text"></textarea>
                        </div>
                    </div>

                    <div data-kind="email" hidden>
                        <div class="tl-field">
                            <label class="tl-label" for="qr-email">Send to</label>
                            <input class="form-control" id="qr-email" type="email" inputmode="email" autocomplete="off" placeholder="hello@example.com" data-f="email">
                        </div>
                        <div class="tl-field">
                            <label class="tl-label" for="qr-subject">Subject <span class="tl-label__value">optional</span></label>
                            <input class="form-control" id="qr-subject" type="text" maxlength="150" data-f="subject">
                        </div>
                        <div class="tl-field">
                            <label class="tl-label" for="qr-body">Message <span class="tl-label__value">optional</span></label>
                            <textarea class="form-control" id="qr-body" rows="3" maxlength="500" data-f="body"></textarea>
                        </div>
                    </div>

                    <div data-kind="wifi" hidden>
                        <div class="tl-field">
                            <label class="tl-label" for="qr-ssid">Network name</label>
                            <input class="form-control" id="qr-ssid" type="text" maxlength="32" autocomplete="off" spellcheck="false" data-f="ssid">
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-5">
                                <div class="tl-field">
                                    <label class="tl-label" for="qr-enc">Security</label>
                                    <select class="form-select" id="qr-enc" data-f="enc">
                                        <option value="WPA" selected>WPA / WPA2 / WPA3</option>
                                        <option value="WEP">WEP</option>
                                        <option value="nopass">None (open network)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-7">
                                <div class="tl-field">
                                    <label class="tl-label" for="qr-pass">Password</label>
                                    <input class="form-control" id="qr-pass" type="text" maxlength="63" autocomplete="off" spellcheck="false" data-f="pass">
                                </div>
                            </div>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="qr-hidden" data-f="hidden">
                            <label class="form-check-label small" for="qr-hidden">This is a hidden network</label>
                        </div>
                        <p class="tl-help">The password is only written into the QR code itself. It is never sent anywhere.</p>
                    </div>

                    <p class="tl-help tl-help--error" role="alert" hidden data-error></p>
                </div>

                {{-- Design --}}
                <div class="ico-card tl-panel">
                    <h2 class="tl-h">Design</h2>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="tl-field">
                                <label class="tl-label" for="qr-fg-text">Code colour</label>
                                <div class="tl-color">
                                    <input type="color" value="#040408" aria-label="Code colour" data-fg>
                                    <input class="form-control" id="qr-fg-text" type="text" value="#040408" maxlength="7" spellcheck="false" autocomplete="off" data-fg-text>
                                </div>
                                <div class="tl-swatches" data-swatches aria-label="Colour presets">
                                    @foreach (['#040408' => 'Black', '#1E0046' => 'Deep purple', '#5A01F0' => 'Violet', '#0B5ED7' => 'Blue', '#14875F' => 'Green', '#C9245A' => 'Rose'] as $hex => $name)
                                        <button class="tl-swatch" type="button" style="background:{{ $hex }}" data-swatch="{{ $hex }}"
                                                aria-label="{{ $name }}" title="{{ $name }}"></button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="tl-field">
                                <label class="tl-label" for="qr-bg-text">Background</label>
                                <div class="tl-color">
                                    <input type="color" value="#ffffff" aria-label="Background colour" data-bg>
                                    <input class="form-control" id="qr-bg-text" type="text" value="#FFFFFF" maxlength="7" spellcheck="false" autocomplete="off" data-bg-text>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="qr-transparent" data-transparent>
                                    <label class="form-check-label small" for="qr-transparent">Transparent background</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="qr-gradient" data-gradient>
                        <label class="form-check-label small" for="qr-gradient">Blend the code into a second colour</label>
                    </div>

                    <div class="tl-field" hidden data-gradient-field>
                        <label class="tl-label" for="qr-fg2-text">Second colour</label>
                        <div class="tl-color">
                            <input type="color" value="#5a01f0" aria-label="Second colour" data-fg2>
                            <input class="form-control" id="qr-fg2-text" type="text" value="#5A01F0" maxlength="7" spellcheck="false" autocomplete="off" data-fg2-text>
                        </div>
                    </div>

                    <p class="tl-help tl-help--warn" hidden data-contrast></p>

                    <div class="row g-3 mt-0 mb-3">
                        <div class="col-sm-6">
                            <div class="tl-field">
                                <label class="tl-label" for="qr-dots">Dot shape</label>
                                <select class="form-select" id="qr-dots" data-dots>
                                    <option value="square">Square</option>
                                    <option value="rounded" selected>Rounded</option>
                                    <option value="dots">Dots</option>
                                    <option value="extra-rounded">Extra rounded</option>
                                    <option value="classy">Classy</option>
                                    <option value="classy-rounded">Classy rounded</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="tl-field">
                                <label class="tl-label" for="qr-corners">Corner shape</label>
                                <select class="form-select" id="qr-corners" data-corners>
                                    <option value="square">Square</option>
                                    <option value="extra-rounded" selected>Rounded</option>
                                    <option value="dot">Circle</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="tl-field">
                        <span class="tl-label">Logo in the middle <span class="tl-label__value">optional</span></span>
                        <div class="tl-actions">
                            <button class="btn btn-ico-outline btn-sm" type="button" data-logo-pick>
                                <i class="bi bi-image" aria-hidden="true"></i> <span data-logo-label>Add a logo</span>
                            </button>
                            <button class="btn btn-ico-ghost btn-sm" type="button" hidden data-logo-remove>
                                <i class="bi bi-x-lg" aria-hidden="true"></i> Remove
                            </button>
                        </div>
                        <input type="file" hidden accept="image/png,image/jpeg,image/webp,image/svg+xml,.png,.jpg,.jpeg,.webp,.svg" data-logo-input>
                        <p class="tl-help" data-logo-help>PNG, JPG, WebP or SVG, up to 2 MB. A simple square logo scans best.</p>
                    </div>

                    <div class="tl-field">
                        <label class="tl-label" for="qr-ecc">Scan reliability</label>
                        <select class="form-select" id="qr-ecc" data-ecc>
                            <option value="L">Low — simplest code</option>
                            <option value="M" selected>Medium — a good default</option>
                            <option value="Q">High — for print</option>
                            <option value="H">Highest — best with a logo</option>
                        </select>
                        <p class="tl-help" data-ecc-help>Higher levels still scan when the code is scratched or partly covered, but make it denser.</p>
                    </div>
                </div>
            </div>

            {{-- Preview + download --}}
            <aside class="tl-layout__side">
                <div class="ico-card tl-panel">
                    <h2 class="tl-h">Preview</h2>

                    <div class="tl-qr-preview">
                        <div class="tl-qr-preview__code" data-preview role="img" aria-label="QR code preview"></div>
                    </div>
                    <p class="tl-help text-center" data-status aria-live="polite">Scan it with your phone camera to test.</p>

                    <div class="tl-field mt-3">
                        <label class="tl-label" for="qr-size">Download size <output data-size-out>1024 px</output></label>
                        <input class="tl-range" id="qr-size" type="range" min="256" max="2048" step="64" value="1024" data-size>
                        <p class="tl-help">Applies to PNG. SVG is a vector and stays sharp at any size.</p>
                    </div>

                    <div class="tl-actions tl-actions--stack">
                        <button class="btn btn-ico-primary" type="button" data-dl="png">
                            <i class="bi bi-download" aria-hidden="true"></i> Download PNG
                        </button>
                        <div class="d-grid gap-2" style="grid-template-columns:1fr 1fr">
                            <button class="btn btn-ico-outline btn-sm" type="button" data-dl="svg">
                                <i class="bi bi-vector-pen" aria-hidden="true"></i> SVG
                            </button>
                            <button class="btn btn-ico-outline btn-sm" type="button" data-copy>
                                <i class="bi bi-clipboard" aria-hidden="true"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

@include('tools.partials.more', [
    'steps' => [
        ['Enter the content', 'Choose a link, text, an email or a Wi-Fi network and fill in the details. The code updates as you type.'],
        ['Make it yours', 'Pick colours and shapes and drop your logo in the middle. A warning appears if a colour choice would be hard to scan.'],
        ['Download and test', 'Save as PNG for screens or SVG for print, then scan it with your phone before you publish it.'],
    ],
    'faqs' => [
        ['Do these QR codes expire?', 'No. They are static codes: the link or text is written directly into the pattern, so the code works for as long as the destination exists. There is no redirect through Iconors and nothing to renew.'],
        ['Can I use the QR codes commercially?', 'Yes. The codes you generate are yours to use on packaging, posters, menus, business cards or anywhere else, free of charge.'],
        ['Should I download PNG or SVG?', 'Use PNG for websites, social posts and documents. Use SVG for anything that will be printed or scaled up, such as posters and packaging, because it stays sharp at any size.'],
        ['Why will my QR code not scan?', 'The most common cause is low contrast: the code needs to be clearly darker than its background. Very dense codes printed small, and logos that cover too much of the pattern, also cause trouble. Shorten the content or raise the scan reliability.'],
        ['Is the content I type sent to a server?', 'No. The code is generated in your browser. Links, messages and Wi-Fi passwords you enter never leave your device.'],
        ['How does the Wi-Fi QR code work?', 'Scanning it with a phone camera offers to join the network without typing the password. It works on current iPhone and Android phones.'],
    ],
])
@endsection
