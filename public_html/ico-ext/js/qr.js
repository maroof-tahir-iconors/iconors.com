/*
 * Iconors — QR Code Generator.
 *
 * Codes are drawn in the page by qr-code-styling (MIT). They are static:
 * the content is written into the pattern itself, so there is no redirect
 * through Iconors, nothing to expire and nothing sent to a server — which
 * matters for the Wi-Fi password field in particular.
 */
(function () {
  'use strict';

  var T = window.IcoTools;
  var root = document.querySelector('[data-qr]');
  if (!T || !root) return;

  var PREVIEW_PX = 600;                // drawn large, scaled down by CSS, so it stays crisp on dense screens
  var LOGO_MAX_BYTES = 2 * 1024 * 1024;
  var LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

  var ui = {
    kinds: T.$$('input[name="qr-kind"]', root),
    panes: T.$$('[data-kind]', root),
    fields: {},
    textCount: T.$('[data-text-count]', root),
    error: T.$('[data-error]', root),
    fg: T.$('[data-fg]', root), fgText: T.$('[data-fg-text]', root),
    bg: T.$('[data-bg]', root), bgText: T.$('[data-bg-text]', root),
    fg2: T.$('[data-fg2]', root), fg2Text: T.$('[data-fg2-text]', root),
    swatches: T.$$('[data-swatch]', root),
    transparent: T.$('[data-transparent]', root),
    gradient: T.$('[data-gradient]', root),
    gradientField: T.$('[data-gradient-field]', root),
    contrast: T.$('[data-contrast]', root),
    dots: T.$('[data-dots]', root),
    corners: T.$('[data-corners]', root),
    logoPick: T.$('[data-logo-pick]', root),
    logoLabel: T.$('[data-logo-label]', root),
    logoRemove: T.$('[data-logo-remove]', root),
    logoInput: T.$('[data-logo-input]', root),
    logoHelp: T.$('[data-logo-help]', root),
    ecc: T.$('[data-ecc]', root),
    eccHelp: T.$('[data-ecc-help]', root),
    preview: T.$('[data-preview]', root),
    status: T.$('[data-status]', root),
    size: T.$('[data-size]', root),
    sizeOut: T.$('[data-size-out]', root),
    downloads: T.$$('[data-dl]', root),
    copy: T.$('[data-copy]', root)
  };

  T.$$('[data-f]', root).forEach(function (el) { ui.fields[el.dataset.f] = el; });

  var state = {
    kind: 'url',
    fg: '#040408',
    fg2: '#5a01f0',
    bg: '#ffffff',
    transparent: false,
    gradient: false,
    dots: 'rounded',
    corners: 'extra-rounded',
    ecc: 'M',
    logo: null,       // data: URL
    size: 1024,
    data: '',         // the encoded payload, '' while the form is invalid
    touched: false    // do not show "required" errors before the visitor has typed anything
  };

  var LOGO_HELP = ui.logoHelp.textContent;
  var ECC_HELP = ui.eccHelp.textContent;
  var STATUS_READY = ui.status.textContent;

  /* ----------------------------------------------------------------
   * Content → payload
   * -------------------------------------------------------------- */

  /** Characters with meaning in the WIFI: format must be backslash-escaped. */
  function wifiEscape(value) {
    return String(value).replace(/([\\;,:"])/g, '\\$1');
  }

  /**
   * Returns { data } when the form is valid, or { error } / { empty }.
   * `empty` means "nothing entered yet" — shown as a neutral hint rather
   * than an error until the visitor has interacted.
   */
  function buildPayload() {
    var f = ui.fields;

    if (state.kind === 'url') {
      var url = f.url.value.trim();
      if (!url) return { empty: 'Enter a website address to make a code.' };

      // People type "example.com"; a scanner needs a scheme to open it.
      if (!/^[a-z][a-z0-9+.-]*:/i.test(url)) url = 'https://' + url;

      try {
        var parsed = new URL(url);
        var web = parsed.protocol === 'http:' || parsed.protocol === 'https:';
        if (web && !/^[^\s.]+(\.[^\s.]+)+$|^localhost$/i.test(parsed.hostname)) throw new Error('host');
      } catch (e) {
        return { error: 'That does not look like a web address. Try something like https://example.com.' };
      }

      if (/\s/.test(url)) return { error: 'A web address cannot contain spaces.' };
      return { data: url };
    }

    if (state.kind === 'text') {
      var text = f.text.value;
      if (!text.trim()) return { empty: 'Type some text to make a code.' };
      return { data: text };
    }

    if (state.kind === 'email') {
      var email = f.email.value.trim();
      if (!email) return { empty: 'Enter an email address to make a code.' };
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return { error: 'That does not look like an email address.' };

      var query = [];
      if (f.subject.value.trim()) query.push('subject=' + encodeURIComponent(f.subject.value.trim()));
      if (f.body.value.trim()) query.push('body=' + encodeURIComponent(f.body.value.trim()));

      return { data: 'mailto:' + email + (query.length ? '?' + query.join('&') : '') };
    }

    // Wi-Fi
    var ssid = f.ssid.value;
    var enc = f.enc.value;
    var pass = f.pass.value;

    if (!ssid.trim()) return { empty: 'Enter the network name to make a code.' };
    if (enc !== 'nopass' && !pass) return { empty: 'Enter the Wi-Fi password, or set security to "None" for an open network.' };
    if (enc === 'WPA' && pass.length < 8) return { error: 'WPA passwords are at least 8 characters long.' };

    return {
      data: 'WIFI:T:' + enc + ';S:' + wifiEscape(ssid) + ';' +
        (enc !== 'nopass' ? 'P:' + wifiEscape(pass) + ';' : '') +
        (f.hidden.checked ? 'H:true;' : '') + ';'
    };
  }

  /* ----------------------------------------------------------------
   * Rendering
   * -------------------------------------------------------------- */

  /**
   * qr-code-styling hands the string to its encoder one UTF-16 unit at a
   * time and keeps only the low byte of each, which mangles anything
   * outside Latin-1 (Arabic, Urdu, emoji…). Converting to a UTF-8 byte
   * string first means the bytes that land in the code are real UTF-8,
   * which is what phone scanners expect.
   */
  function toUtf8Bytes(text) {
    return unescape(encodeURIComponent(text));
  }

  function options(px, type) {
    var colour = state.gradient
      ? { gradient: { type: 'linear', rotation: Math.PI / 4, colorStops: [{ offset: 0, color: state.fg }, { offset: 1, color: state.fg2 }] } }
      : { color: state.fg };

    var opts = {
      width: px,
      height: px,
      type: type,
      data: toUtf8Bytes(state.data),
      margin: Math.round(px * 0.045),
      qrOptions: { errorCorrectionLevel: state.ecc },
      dotsOptions: Object.assign({ type: state.dots }, colour),
      cornersSquareOptions: Object.assign({ type: state.corners }, colour),
      cornersDotOptions: Object.assign({ type: state.corners === 'square' ? 'square' : 'dot' }, colour),
      backgroundOptions: { color: state.transparent ? 'transparent' : state.bg }
    };

    if (state.logo) {
      opts.image = state.logo;
      opts.imageOptions = { hideBackgroundDots: true, imageSize: 0.36, margin: Math.round(px * 0.012) };
    }

    return opts;
  }

  function setReady(ready, message, isError) {
    ui.preview.classList.toggle('is-stale', !ready);
    ui.downloads.forEach(function (btn) { btn.disabled = !ready; });
    ui.copy.disabled = !ready;

    ui.error.hidden = !isError;
    ui.error.textContent = isError ? message : '';
    ui.status.textContent = ready ? STATUS_READY : (isError ? 'Fix the highlighted problem to update the code.' : message);
  }

  function render() {
    var payload = buildPayload();

    if (!payload.data) {
      state.data = '';
      setReady(false, payload.error || payload.empty, !!payload.error);
      return;
    }

    state.data = payload.data;

    if (!window.QRCodeStyling) {
      setReady(false, 'The QR library did not load. Check your connection and reload the page.', true);
      return;
    }

    try {
      // A fresh instance each time: the library deep-merges updates, so a
      // gradient or logo that has been switched off would otherwise linger.
      var holder = document.createElement('div');
      new window.QRCodeStyling(options(PREVIEW_PX, 'svg')).append(holder);

      ui.preview.replaceChildren.apply(ui.preview, Array.prototype.slice.call(holder.childNodes));
      setReady(true);
    } catch (err) {
      // The encoder throws when the content will not fit at this level.
      setReady(false, 'That is too much content for one QR code at this reliability level. Shorten it, or choose a lower scan reliability.', true);
    }
  }

  var renderSoon = T.debounce(render, 140);

  /* --- Contrast guard ---------------------------------------------- */
  function luminance(hex) {
    var c = [1, 3, 5].map(function (i) {
      var v = parseInt(hex.slice(i, i + 2), 16) / 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });

    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }

  function contrast(a, b) {
    var la = luminance(a), lb = luminance(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  }

  function checkContrast() {
    // A transparent code usually ends up on white paper or a white page.
    var bg = state.transparent ? '#ffffff' : state.bg;
    var colours = state.gradient ? [state.fg, state.fg2] : [state.fg];
    var worst = Math.min.apply(null, colours.map(function (c) { return contrast(c, bg); }));
    var inverted = colours.some(function (c) { return luminance(c) > luminance(bg); });
    var message = '';

    if (worst < 3) {
      message = 'These colours are too close together, so many phones will not be able to scan the code. Use a darker code colour or a lighter background.';
    } else if (inverted) {
      message = 'A light code on a dark background does not scan in every app. A dark code on a light background is the safe choice.';
    } else if (worst < 4.5) {
      message = 'The contrast is on the low side. Test the code on a couple of phones before you print it.';
    }

    ui.contrast.hidden = !message;
    ui.contrast.textContent = (state.transparent && message ? 'On a white surface: ' : '') + message;
  }

  /* ----------------------------------------------------------------
   * Export
   * -------------------------------------------------------------- */
  function exportBlob(kind) {
    if (!state.data) return Promise.reject(new Error('There is nothing to download yet.'));

    var svg = kind === 'svg';
    var qr = new window.QRCodeStyling(options(svg ? 1024 : state.size, svg ? 'svg' : 'canvas'));

    return qr.getRawData(svg ? 'svg' : 'png').then(function (blob) {
      if (!blob) throw new Error('The code could not be exported. Try a smaller size.');
      // Some builds return a plain Buffer-like object; normalise to a Blob.
      return blob instanceof Blob ? blob : new Blob([blob], { type: svg ? 'image/svg+xml' : 'image/png' });
    });
  }

  function busy(button, on) {
    button.disabled = on;
    button.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  /* ----------------------------------------------------------------
   * Wiring
   * -------------------------------------------------------------- */
  ui.kinds.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      state.kind = radio.value;
      ui.panes.forEach(function (pane) { pane.hidden = pane.dataset.kind !== state.kind; });
      render();
    });
  });

  Object.keys(ui.fields).forEach(function (key) {
    var field = ui.fields[key];
    field.addEventListener(field.tagName === 'SELECT' || field.type === 'checkbox' ? 'change' : 'input', function () {
      state.touched = true;

      if (key === 'text') ui.textCount.textContent = field.value.length + ' / ' + field.maxLength;
      if (key === 'enc') ui.fields.pass.disabled = field.value === 'nopass';

      renderSoon();
    });
  });

  var setFg = T.bindColor(ui.fg, ui.fgText, function (hex) { state.fg = hex; checkContrast(); renderSoon(); });
  T.bindColor(ui.bg, ui.bgText, function (hex) { state.bg = hex; checkContrast(); renderSoon(); });
  T.bindColor(ui.fg2, ui.fg2Text, function (hex) { state.fg2 = hex; checkContrast(); renderSoon(); });

  ui.swatches.forEach(function (swatch) {
    swatch.addEventListener('click', function () { setFg(swatch.dataset.swatch); });
  });

  ui.transparent.addEventListener('change', function () {
    state.transparent = ui.transparent.checked;
    ui.bg.disabled = ui.bgText.disabled = state.transparent;
    checkContrast();
    render();
  });

  ui.gradient.addEventListener('change', function () {
    state.gradient = ui.gradient.checked;
    ui.gradientField.hidden = !state.gradient;
    checkContrast();
    render();
  });

  ui.dots.addEventListener('change', function () { state.dots = ui.dots.value; render(); });
  ui.corners.addEventListener('change', function () { state.corners = ui.corners.value; render(); });

  ui.ecc.addEventListener('change', function () {
    state.ecc = ui.ecc.value;
    ui.eccHelp.textContent = state.logo && (state.ecc === 'L' || state.ecc === 'M')
      ? 'A logo covers part of the code. At this level it may not scan — "Highest" is the safe choice with a logo.'
      : ECC_HELP;
    ui.eccHelp.classList.toggle('tl-help--warn', state.logo && (state.ecc === 'L' || state.ecc === 'M'));
    render();
  });

  // Logo
  ui.logoPick.addEventListener('click', function () { ui.logoInput.click(); });

  ui.logoInput.addEventListener('change', function () {
    var file = ui.logoInput.files && ui.logoInput.files[0];
    ui.logoInput.value = '';
    if (!file) return;

    var fail = function (message) {
      ui.logoHelp.textContent = message;
      ui.logoHelp.classList.add('tl-help--error');
      T.toast(message, 'error');
    };

    if (LOGO_TYPES.indexOf(T.typeOf(file)) === -1) return fail('That file type cannot be used as a logo. Choose a PNG, JPG, WebP or SVG.');
    if (file.size > LOGO_MAX_BYTES) return fail('That logo is ' + T.formatBytes(file.size) + '. Choose one under 2 MB.');

    var reader = new FileReader();

    reader.onerror = function () { fail('The logo could not be read. Try a different file.'); };

    reader.onload = function () {
      // Confirm it really decodes before putting it in the code.
      var probe = new Image();

      probe.onerror = function () { fail('The logo could not be opened. The file may be damaged.'); };

      probe.onload = function () {
        state.logo = reader.result;
        ui.logoLabel.textContent = 'Change logo';
        ui.logoRemove.hidden = false;
        ui.logoHelp.classList.remove('tl-help--error');

        // A logo hides part of the pattern, which only the higher
        // correction levels can recover from.
        if (state.ecc === 'L' || state.ecc === 'M') {
          state.ecc = ui.ecc.value = 'H';
          ui.logoHelp.textContent = 'Logo added. Scan reliability was raised to "Highest" so the code still reads with the logo on top.';
        } else {
          ui.logoHelp.textContent = 'Logo added: ' + file.name;
        }

        ui.eccHelp.textContent = ECC_HELP;
        ui.eccHelp.classList.remove('tl-help--warn');
        render();
      };

      probe.src = reader.result;
    };

    reader.readAsDataURL(file);
  });

  ui.logoRemove.addEventListener('click', function () {
    state.logo = null;
    ui.logoLabel.textContent = 'Add a logo';
    ui.logoRemove.hidden = true;
    ui.logoHelp.textContent = LOGO_HELP;
    ui.logoHelp.classList.remove('tl-help--error');
    ui.eccHelp.textContent = ECC_HELP;
    ui.eccHelp.classList.remove('tl-help--warn');
    render();
    ui.logoPick.focus();
  });

  // Size
  T.bindRange(ui.size);
  ui.size.addEventListener('input', function () {
    state.size = parseInt(ui.size.value, 10);
    ui.sizeOut.textContent = state.size + ' px';
  });

  // Downloads
  ui.downloads.forEach(function (button) {
    button.addEventListener('click', function () {
      var kind = button.dataset.dl;
      busy(button, true);

      exportBlob(kind).then(function (blob) {
        T.download(blob, 'iconors-qr-code.' + kind);
        T.toast('Your QR code is downloading as ' + kind.toUpperCase() + '.', 'success');
      }, function (err) {
        T.toast((err && err.message) || 'The code could not be exported.', 'error');
      }).then(function () {
        busy(button, false);
        button.disabled = !state.data;
      });
    });
  });

  // Copy to clipboard — only where the browser can put an image there.
  if (!(navigator.clipboard && window.ClipboardItem && navigator.clipboard.write)) {
    ui.copy.hidden = true;
    ui.copy.parentNode.style.gridTemplateColumns = '1fr';
  } else {
    ui.copy.addEventListener('click', function () {
      busy(ui.copy, true);

      // The promise goes straight into ClipboardItem: Safari only allows
      // a clipboard write that starts inside the click itself.
      var item;
      try {
        item = new window.ClipboardItem({ 'image/png': exportBlob('png') });
      } catch (e) {
        item = null;
      }

      var write = item
        ? navigator.clipboard.write([item])
        : exportBlob('png').then(function (blob) { return navigator.clipboard.write([new window.ClipboardItem({ 'image/png': blob })]); });

      write.then(function () {
        T.toast('QR code copied. Paste it into a document or chat.', 'success');
      }, function () {
        T.toast('Your browser blocked the copy. Use Download PNG instead.', 'error');
      }).then(function () {
        busy(ui.copy, false);
        ui.copy.disabled = !state.data;
      });
    });
  }

  checkContrast();
  render();
})();
