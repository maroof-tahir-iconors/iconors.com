/*
 * Iconors — Image Editor.
 *
 * How it hangs together:
 *
 *   base      the committed picture at working resolution (a canvas).
 *             Crop, rotate, flip, resize, brush strokes and text all
 *             replace it with a new canvas, never draw into it, so every
 *             history entry can keep its own untouched copy.
 *   adjust    brightness / contrast / … plus the chosen filter. These are
 *             NOT baked into base; they are one colour matrix (and a
 *             blur) applied on the way to the screen and again, at full
 *             resolution, on export. Sliders stay live and reversible.
 *   view      what is on screen: base scaled to fit, then adjusted.
 *
 * Nothing here talks to a server. The image is decoded, edited and
 * re-encoded in this tab.
 */
(function () {
  'use strict';

  var T = window.IcoTools;
  var root = document.querySelector('[data-editor]');
  if (!T || !root) return;

  var INPUT_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/bmp', 'image/avif', 'image/svg+xml'];
  var INPUT_LABEL = 'PNG, JPG, WebP, GIF, BMP, AVIF or SVG';
  var MAX_WORK_SIDE = 4096;                 // larger pictures are scaled to this on open
  var MAX_VIEW_SIDE = 1600;                 // backing-store cap for the on-screen canvas
  var HISTORY_BUDGET = 320 * 1024 * 1024;   // bytes of pixel data kept for undo

  var q = function (sel) { return T.$(sel, root); };
  var qa = function (sel) { return T.$$(sel, root); };

  var ui = {
    start: q('[data-start]'), drop: q('[data-drop]'), input: q('[data-input]'),
    shell: q('[data-shell]'), stage: q('[data-stage]'), wrap: q('[data-wrap]'),
    view: q('[data-view]'), ink: q('[data-ink]'), crop: q('[data-crop]'), cropBox: q('[data-crop-box]'),
    undo: q('[data-undo]'), redo: q('[data-redo]'), reset: q('[data-reset]'),
    info: q('[data-info]'), compare: q('[data-compare]'), fresh: q('[data-new]'),
    tabs: qa('[data-tab]'), panes: qa('[data-pane]'),
    ratios: qa('input[name="ed-ratio"]'), cropSize: q('[data-crop-size]'), cropApply: q('[data-crop-apply]'),
    rw: q('[data-rw]'), rh: q('[data-rh]'), lock: q('[data-lock]'), resizeError: q('[data-resize-error]'), resizeApply: q('[data-resize-apply]'),
    adj: qa('[data-adj]'), adjReset: q('[data-adj-reset]'), presets: q('[data-presets]'),
    modes: qa('input[name="ed-mode"]'), inkColor: q('[data-ink-color]'), inkText: q('[data-ink-text]'),
    inkSize: q('[data-ink-size]'), inkSizeOut: q('[data-ink-size-out]'), inkSizeLabel: q('[data-ink-size-label]'),
    textFields: q('[data-text-fields]'), text: q('[data-text]'), bold: q('[data-bold]'), textApply: q('[data-text-apply]'), brushHelp: q('[data-brush-help]'),
    formats: qa('input[name="ed-format"]'), eqField: q('[data-eq-field]'), eq: q('[data-eq]'), eqOut: q('[data-eq-out]'), save: q('[data-save]')
  };

  var ZERO = { brightness: 0, contrast: 0, saturation: 0, warmth: 0, hue: 0, blur: 0, filter: 'none' };

  var state = {
    name: 'image',
    base: null,
    original: null,
    adjust: Object.assign({}, ZERO),
    history: [],
    cursor: -1,
    tab: 'adjust',
    scale: 1,            // CSS px per base px
    crop: null,          // {x, y, w, h} in base px
    ratio: 0,            // w/h, 0 = free
    mode: 'brush',
    ink: '#e6174b',
    inkSize: 12,
    textPos: null,       // {x, y} in CSS px inside the wrap
    format: 'image/png',
    quality: 90,
    busy: false
  };

  var cropShade = T.el('div', { class: 'ed-crop__shade', 'aria-hidden': 'true' });
  ui.crop.insertBefore(cropShade, ui.cropBox);

  var viewCtx = ui.view.getContext('2d');
  var inkCtx = ui.ink.getContext('2d');
  var cache = { key: null, pixels: null }; // base drawn at view size, unadjusted
  var textNode = null;

  /* ----------------------------------------------------------------
   * Colour maths
   * -------------------------------------------------------------- */

  // 3×4 matrices, row-major: [r→r, g→r, b→r, +r,  …g…,  …b…]. Offsets in 0–255.
  var IDENTITY = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0];

  /** a ∘ b: apply b first, then a. */
  function mul(a, b) {
    var out = new Array(12);

    for (var r = 0; r < 3; r++) {
      for (var c = 0; c < 4; c++) {
        out[r * 4 + c] = a[r * 4] * b[c] + a[r * 4 + 1] * b[4 + c] + a[r * 4 + 2] * b[8 + c] + (c === 3 ? a[r * 4 + 3] : 0);
      }
    }

    return out;
  }

  function saturationMatrix(s) {
    var lr = 0.2126, lg = 0.7152, lb = 0.0722, i = 1 - s;
    return [lr * i + s, lg * i, lb * i, 0, lr * i, lg * i + s, lb * i, 0, lr * i, lg * i, lb * i + s, 0];
  }

  function hueMatrix(deg) {
    var a = deg * Math.PI / 180, c = Math.cos(a), s = Math.sin(a);

    return [
      0.213 + c * 0.787 - s * 0.213, 0.715 - c * 0.715 - s * 0.715, 0.072 - c * 0.072 + s * 0.928, 0,
      0.213 - c * 0.213 + s * 0.143, 0.715 + c * 0.285 + s * 0.140, 0.072 - c * 0.072 - s * 0.283, 0,
      0.213 - c * 0.213 - s * 0.787, 0.715 - c * 0.715 + s * 0.715, 0.072 + c * 0.928 + s * 0.072, 0
    ];
  }

  function contrastMatrix(f) {
    var o = 128 * (1 - f);
    return [f, 0, 0, o, 0, f, 0, o, 0, 0, f, o];
  }

  function scaleMatrix(f) { return [f, 0, 0, 0, 0, f, 0, 0, 0, 0, f, 0]; }

  var FILTERS = {
    none: { label: 'None', matrix: IDENTITY },
    vivid: { label: 'Vivid', matrix: mul(contrastMatrix(1.12), saturationMatrix(1.45)) },
    mono: { label: 'Mono', matrix: saturationMatrix(0) },
    noir: { label: 'Noir', matrix: mul(contrastMatrix(1.45), saturationMatrix(0)) },
    sepia: { label: 'Sepia', matrix: [0.393, 0.769, 0.189, 0, 0.349, 0.686, 0.168, 0, 0.272, 0.534, 0.131, 0] },
    warm: { label: 'Warm', matrix: [1.08, 0, 0, 12, 0, 1.01, 0, 4, 0, 0, 0.9, -10] },
    cool: { label: 'Cool', matrix: [0.9, 0, 0, -8, 0, 1, 0, 2, 0, 0, 1.1, 14] },
    fade: { label: 'Fade', matrix: mul([1, 0, 0, 26, 0, 1, 0, 26, 0, 0, 1, 30], mul(contrastMatrix(0.82), saturationMatrix(0.8))) },
    invert: { label: 'Invert', matrix: [-1, 0, 0, 255, 0, -1, 0, 255, 0, 0, -1, 255] }
  };

  /** One matrix for the filter plus every slider. */
  function matrixFor(adjust) {
    var m = (FILTERS[adjust.filter] || FILTERS.none).matrix;

    if (adjust.warmth) { var w = adjust.warmth * 0.32; m = mul([1, 0, 0, w, 0, 1, 0, w * 0.12, 0, 0, 1, -w], m); }
    if (adjust.brightness) m = mul(scaleMatrix(1 + adjust.brightness / 100 * (adjust.brightness > 0 ? 0.8 : 0.7)), m);
    if (adjust.contrast) m = mul(contrastMatrix(1 + adjust.contrast / 100 * (adjust.contrast > 0 ? 0.9 : 0.7)), m);
    if (adjust.saturation) m = mul(saturationMatrix(1 + adjust.saturation / 100), m);
    if (adjust.hue) m = mul(hueMatrix(adjust.hue), m);

    return m;
  }

  function isIdentity(m) {
    for (var i = 0; i < 12; i++) if (Math.abs(m[i] - IDENTITY[i]) > 1e-6) return false;
    return true;
  }

  /** Apply a matrix in place. Uint8ClampedArray clamps for us. */
  function applyMatrix(data, m) {
    if (isIdentity(m)) return;

    for (var i = 0, n = data.length; i < n; i += 4) {
      var r = data[i], g = data[i + 1], b = data[i + 2];
      data[i] = m[0] * r + m[1] * g + m[2] * b + m[3];
      data[i + 1] = m[4] * r + m[5] * g + m[6] * b + m[7];
      data[i + 2] = m[8] * r + m[9] * g + m[10] * b + m[11];
    }
  }

  /**
   * Three box-blur passes — visually a Gaussian, and done on the pixel
   * array rather than with ctx.filter so that every browser gives the
   * same result and the edges are clamped instead of fading to clear.
   */
  function blur(imageData, radius) {
    radius = Math.round(radius);
    if (radius < 1) return;

    var w = imageData.width, h = imageData.height;
    var src = imageData.data;
    var tmp = new Uint8ClampedArray(src.length);

    var pass = function (from, to, horizontal) {
      var outer = horizontal ? h : w, inner = horizontal ? w : h;
      var step = horizontal ? 4 : w * 4;
      var window = radius * 2 + 1;

      for (var o = 0; o < outer; o++) {
        var start = horizontal ? o * w * 4 : o * 4;

        for (var ch = 0; ch < 4; ch++) {
          var sum = 0;

          for (var k = -radius; k <= radius; k++) sum += from[start + T.clamp(k, 0, inner - 1) * step + ch];

          for (var i = 0; i < inner; i++) {
            to[start + i * step + ch] = sum / window;
            sum += from[start + Math.min(inner - 1, i + radius + 1) * step + ch] - from[start + Math.max(0, i - radius) * step + ch];
          }
        }
      }
    };

    for (var n = 0; n < 3; n++) {
      pass(src, tmp, true);
      pass(tmp, src, false);
    }
  }

  /** Blur is stored as 0–20 and means "this fraction of the picture",
      so preview and export agree whatever their pixel sizes. */
  function blurRadius(amount, width, height) {
    return amount ? Math.max(1, amount * Math.max(width, height) / 1400) : 0;
  }

  /* ----------------------------------------------------------------
   * Canvas helpers
   * -------------------------------------------------------------- */
  function makeCanvas(w, h) {
    var c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w));
    c.height = Math.max(1, Math.round(h));
    return c;
  }

  function context(canvas) {
    var ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('The image is too large for this browser to edit.');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    return ctx;
  }

  function clone(canvas) {
    var copy = makeCanvas(canvas.width, canvas.height);
    context(copy).drawImage(canvas, 0, 0);
    return copy;
  }

  /* ----------------------------------------------------------------
   * History
   * -------------------------------------------------------------- */
  function snapshot() {
    return { base: state.base, adjust: Object.assign({}, state.adjust) };
  }

  /** Record the current state as a new step, dropping any redo tail. */
  function commit() {
    state.history = state.history.slice(0, state.cursor + 1);
    state.history.push(snapshot());

    // Trim the oldest steps once the distinct canvases they hold exceed
    // the budget. Slider-only steps share a canvas and cost nothing.
    var trim = function () {
      var seen = [], bytes = 0;
      state.history.forEach(function (step) {
        if (seen.indexOf(step.base) === -1) { seen.push(step.base); bytes += step.base.width * step.base.height * 4; }
      });
      return bytes;
    };

    while (state.history.length > 2 && (trim() > HISTORY_BUDGET || state.history.length > 40)) state.history.shift();

    state.cursor = state.history.length - 1;
    syncButtons();
  }

  function restore(step) {
    state.base = step.base;
    state.adjust = Object.assign({}, step.adjust);
    cache.key = null;

    syncSliders();
    layout();
    refreshPresets();
    if (state.tab === 'crop') startCrop();
    syncResizeFields();
    syncButtons();
  }

  function undo() { if (state.cursor > 0) restore(state.history[--state.cursor]); }
  function redo() { if (state.cursor < state.history.length - 1) restore(state.history[++state.cursor]); }

  function syncButtons() {
    ui.undo.disabled = state.cursor <= 0;
    ui.redo.disabled = state.cursor >= state.history.length - 1;
    ui.reset.disabled = state.history.length <= 1 && state.cursor <= 0;
    ui.info.textContent = state.base ? state.base.width + ' × ' + state.base.height + ' px' : '';
  }

  /* ----------------------------------------------------------------
   * Layout + drawing
   * -------------------------------------------------------------- */
  function layout() {
    if (!state.base) return;

    var availW = Math.max(120, ui.stage.clientWidth - 32);
    var availH = Math.max(160, ui.stage.clientHeight - 32);
    var w = state.base.width, h = state.base.height;

    // Fit inside the stage. Small pictures are shown larger, but capped
    // so a 16 px icon does not become a blurry poster.
    state.scale = Math.min(availW / w, availH / h, 4);

    var cssW = Math.max(1, Math.round(w * state.scale));
    var cssH = Math.max(1, Math.round(h * state.scale));
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var density = Math.min(dpr, MAX_VIEW_SIDE / Math.max(cssW, cssH), Math.max(w / cssW, 1));

    ui.wrap.style.inlineSize = cssW + 'px';
    ui.wrap.style.blockSize = cssH + 'px';

    [ui.view, ui.ink].forEach(function (canvas) {
      canvas.width = Math.max(1, Math.round(cssW * density));
      canvas.height = Math.max(1, Math.round(cssH * density));
      canvas.style.inlineSize = cssW + 'px';
      canvas.style.blockSize = cssH + 'px';
    });

    cache.key = null;
    draw();
    if (state.tab === 'crop' && state.crop) paintCrop();
    if (textNode) placeTextNode();
  }

  function draw() {
    if (!state.base) return;

    var w = ui.view.width, h = ui.view.height;
    var key = w + 'x' + h;

    if (cache.key !== key || cache.base !== state.base) {
      var scratch = makeCanvas(w, h);
      var sctx = context(scratch);
      // Upscaled pixel art should stay crisp, not smear.
      sctx.imageSmoothingEnabled = state.scale <= 1.5;
      sctx.drawImage(state.base, 0, 0, w, h);
      cache.pixels = sctx.getImageData(0, 0, w, h);
      cache.key = key;
      cache.base = state.base;
    }

    var frame = new ImageData(new Uint8ClampedArray(cache.pixels.data), w, h);
    applyMatrix(frame.data, matrixFor(state.adjust));
    blur(frame, blurRadius(state.adjust.blur, w, h));
    viewCtx.putImageData(frame, 0, 0);
  }

  // Slider drags fire far faster than the screen refreshes.
  var drawQueued = false;
  function drawSoon() {
    if (drawQueued) return;
    drawQueued = true;
    window.requestAnimationFrame(function () { drawQueued = false; draw(); });
  }

  /** Full-resolution result: base, adjusted. */
  function render() {
    var out = clone(state.base);
    var matrix = matrixFor(state.adjust);
    var radius = blurRadius(state.adjust.blur, out.width, out.height);

    if (!isIdentity(matrix) || radius) {
      var ctx = context(out);
      var pixels = ctx.getImageData(0, 0, out.width, out.height);
      applyMatrix(pixels.data, matrix);
      blur(pixels, radius);
      ctx.putImageData(pixels, 0, 0);
    }

    return out;
  }

  /* ----------------------------------------------------------------
   * Opening an image
   * -------------------------------------------------------------- */
  function open(fileList) {
    var screened = T.screenFiles(fileList, { types: INPUT_TYPES, label: INPUT_LABEL, room: 1 });

    if (!screened.accepted.length) {
      screened.errors.forEach(function (message) { T.toast(message, 'error'); });
      return;
    }

    if (fileList.length > 1) T.toast('The editor works on one image at a time, so only the first was opened.', 'info');

    var file = screened.accepted[0];
    ui.drop.disabled = true;

    T.loadImage(file).then(function (image) {
      var w = image.width, h = image.height;
      var longest = Math.max(w, h);
      var scaled = false;

      if (longest > MAX_WORK_SIDE) {
        var f = MAX_WORK_SIDE / longest;
        w = Math.round(w * f); h = Math.round(h * f); scaled = true;
      } else if (image.vector && longest < 1024) {
        // A vector has no native pixels; give it enough to work with.
        var up = 1024 / longest;
        w = Math.round(w * up); h = Math.round(h * up);
      }

      var canvas;
      try { canvas = T.toCanvas(image, w, h, null); } finally { image.release(); }

      state.name = T.baseName(file.name);
      state.base = canvas;
      state.original = canvas;
      state.adjust = Object.assign({}, ZERO);
      state.history = [];
      state.cursor = -1;
      commit();

      ui.start.hidden = true;
      ui.shell.hidden = false;

      syncSliders();
      selectTab('adjust');
      layout();
      refreshPresets();
      syncResizeFields();

      if (scaled) T.toast('That image was very large, so it was scaled to ' + w + ' × ' + h + ' px to keep editing smooth.', 'info', 6000);
    }).catch(function (err) {
      T.toast((err && err.message) || 'That image could not be opened.', 'error');
    }).then(function () {
      ui.drop.disabled = false;
    });
  }

  function closeImage() {
    cancelText();
    state.base = state.original = null;
    state.history = [];
    state.cursor = -1;
    cache.key = null;
    ui.shell.hidden = true;
    ui.start.hidden = false;
    ui.drop.focus();
  }

  /* ----------------------------------------------------------------
   * Tabs
   * -------------------------------------------------------------- */
  function selectTab(name) {
    if (state.tab === 'draw' && name !== 'draw') cancelText();

    state.tab = name;

    ui.tabs.forEach(function (tab) {
      var on = tab.dataset.tab === name;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
    });

    ui.panes.forEach(function (pane) { pane.hidden = pane.dataset.pane !== name; });

    ui.crop.hidden = name !== 'crop';
    ui.ink.hidden = name !== 'draw';
    ui.wrap.classList.toggle('is-draw', name === 'draw' && state.mode === 'brush');
    ui.wrap.classList.toggle('is-text', name === 'draw' && state.mode === 'text');

    if (name === 'crop') startCrop();
    if (name === 'resize') syncResizeFields();
    if (name === 'draw') syncMode();
  }

  /* ----------------------------------------------------------------
   * Geometry: rotate, flip, resize, crop
   * -------------------------------------------------------------- */
  function replaceBase(canvas) {
    state.base = canvas;
    commit();
    layout();
    refreshPresets();
    syncResizeFields();
  }

  function rotate(deg) {
    var src = state.base;
    var out = makeCanvas(src.height, src.width);
    var ctx = context(out);

    ctx.translate(out.width / 2, out.height / 2);
    ctx.rotate(deg * Math.PI / 180);
    ctx.drawImage(src, -src.width / 2, -src.height / 2);
    replaceBase(out);
  }

  function flip(axis) {
    var src = state.base;
    var out = makeCanvas(src.width, src.height);
    var ctx = context(out);

    ctx.translate(axis === 'h' ? out.width : 0, axis === 'v' ? out.height : 0);
    ctx.scale(axis === 'h' ? -1 : 1, axis === 'v' ? -1 : 1);
    ctx.drawImage(src, 0, 0);
    replaceBase(out);
  }

  function syncResizeFields() {
    if (!state.base) return;
    ui.rw.value = state.base.width;
    ui.rh.value = state.base.height;
    ui.resizeError.hidden = true;
    ui.rw.classList.remove('is-invalid');
    ui.rh.classList.remove('is-invalid');
  }

  function readSize(input) {
    var raw = input.value.trim();
    var n = parseInt(raw, 10);
    return /^\d+$/.test(raw) && n >= 1 && n <= 8000 ? n : null;
  }

  function applyResize() {
    var w = readSize(ui.rw), h = readSize(ui.rh);

    ui.rw.classList.toggle('is-invalid', w === null);
    ui.rh.classList.toggle('is-invalid', h === null);

    if (w === null || h === null) {
      ui.resizeError.textContent = 'Enter whole numbers between 1 and 8000 for both width and height.';
      ui.resizeError.hidden = false;
      return;
    }

    ui.resizeError.hidden = true;

    if (w === state.base.width && h === state.base.height) {
      T.toast('The image is already ' + w + ' × ' + h + ' px.', 'info');
      return;
    }

    var out = makeCanvas(w, h);
    context(out).drawImage(state.base, 0, 0, w, h);
    replaceBase(out);
    T.toast('Resized to ' + w + ' × ' + h + ' px.', 'success');
  }

  // --- Crop ---
  function startCrop() {
    var W = state.base.width, H = state.base.height;
    var w = W, h = H;

    if (state.ratio) {
      if (W / H > state.ratio) w = H * state.ratio; else h = W / state.ratio;
      // A slightly inset box makes it obvious that there is a box.
      w *= 0.9; h *= 0.9;
    } else {
      w *= 0.86; h *= 0.86;
    }

    state.crop = { x: (W - w) / 2, y: (H - h) / 2, w: w, h: h };
    paintCrop();
  }

  function paintCrop() {
    var c = state.crop, W = state.base.width, H = state.base.height;

    var l = c.x / W * 100, t = c.y / H * 100, r = (c.x + c.w) / W * 100, b = (c.y + c.h) / H * 100;

    // Dim everything outside the selection: a full rectangle with the
    // selection punched out of it (even-odd fill).
    cropShade.style.clipPath = 'polygon(evenodd, 0 0, 100% 0, 100% 100%, 0 100%, 0 0, ' +
      l + '% ' + t + '%, ' + r + '% ' + t + '%, ' + r + '% ' + b + '%, ' + l + '% ' + b + '%, ' + l + '% ' + t + '%)';

    ui.cropBox.style.left = (c.x / W * 100) + '%';
    ui.cropBox.style.top = (c.y / H * 100) + '%';
    ui.cropBox.style.width = (c.w / W * 100) + '%';
    ui.cropBox.style.height = (c.h / H * 100) + '%';
    ui.cropSize.textContent = 'Selection: ' + Math.round(c.w) + ' × ' + Math.round(c.h) + ' px';
  }

  function dragCrop(handle, start, dx, dy) {
    var W = state.base.width, H = state.base.height;
    var min = Math.min(24, W, H);
    var c = { x: start.x, y: start.y, w: start.w, h: start.h };

    if (handle === 'move') {
      c.x = T.clamp(start.x + dx, 0, W - c.w);
      c.y = T.clamp(start.y + dy, 0, H - c.h);
      state.crop = c;
      return;
    }

    var left = start.x, top = start.y, right = start.x + start.w, bottom = start.y + start.h;

    if (handle.indexOf('w') !== -1) left = T.clamp(left + dx, 0, right - min);
    if (handle.indexOf('e') !== -1) right = T.clamp(right + dx, left + min, W);
    if (handle.indexOf('n') !== -1) top = T.clamp(top + dy, 0, bottom - min);
    if (handle.indexOf('s') !== -1) bottom = T.clamp(bottom + dy, top + min, H);

    if (state.ratio) {
      var w = right - left, h = bottom - top;

      // Let whichever edge is being dragged drive; derive the other side
      // and keep the opposite edge/corner pinned.
      if (handle === 'n' || handle === 's') w = h * state.ratio; else h = w / state.ratio;

      var anchorX = handle.indexOf('w') !== -1 ? right : left;
      var anchorY = handle.indexOf('n') !== -1 ? bottom : top;
      var maxW = handle.indexOf('w') !== -1 ? anchorX : W - anchorX;
      var maxH = handle.indexOf('n') !== -1 ? anchorY : H - anchorY;

      if (handle === 'n' || handle === 's') { anchorX = start.x + start.w / 2 - w / 2; maxW = W; }
      if (handle === 'e' || handle === 'w') { anchorY = start.y + start.h / 2 - h / 2; maxH = H; }

      var fit = Math.min(1, maxW / w, maxH / h);
      w *= fit; h *= fit;

      left = handle.indexOf('w') !== -1 ? anchorX - w : anchorX;
      top = handle.indexOf('n') !== -1 ? anchorY - h : anchorY;
      left = T.clamp(left, 0, W - w);
      top = T.clamp(top, 0, H - h);
      right = left + w; bottom = top + h;
    }

    state.crop = { x: left, y: top, w: right - left, h: bottom - top };
  }

  function applyCrop() {
    var c = state.crop;
    var x = Math.round(c.x), y = Math.round(c.y);
    var w = Math.max(1, Math.round(c.w)), h = Math.max(1, Math.round(c.h));

    if (w >= state.base.width && h >= state.base.height) {
      T.toast('The selection covers the whole image, so there is nothing to crop.', 'info');
      return;
    }

    var out = makeCanvas(w, h);
    context(out).drawImage(state.base, x, y, w, h, 0, 0, w, h);
    replaceBase(out);
    startCrop();
    T.toast('Cropped to ' + w + ' × ' + h + ' px.', 'success');
  }

  /* ----------------------------------------------------------------
   * Brush + text
   * -------------------------------------------------------------- */
  function fontPx() { return 10 + state.inkSize * 1.5; } // CSS px on screen

  function syncMode() {
    var text = state.mode === 'text';

    ui.textFields.hidden = !text;
    ui.brushHelp.hidden = text;
    ui.inkSizeLabel.textContent = text ? 'Text size' : 'Brush size';
    ui.wrap.classList.toggle('is-draw', state.tab === 'draw' && !text);
    ui.wrap.classList.toggle('is-text', state.tab === 'draw' && text);

    if (!text) cancelText(); else syncText();
  }

  function pointIn(event) {
    var rect = ui.wrap.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
  }

  function strokePath(ctx, points, k, width) {
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = ctx.fillStyle = state.ink;
    ctx.lineWidth = width;

    if (points.length === 1) {
      // A click with no movement leaves a dot.
      ctx.beginPath();
      ctx.arc(points[0].x * k, points[0].y * k, width / 2, 0, Math.PI * 2);
      ctx.fill();
      return;
    }

    ctx.beginPath();
    ctx.moveTo(points[0].x * k, points[0].y * k);

    // Quadratic curves through the midpoints smooth out the corners a
    // fast mouse leaves between samples.
    for (var i = 1; i < points.length - 1; i++) {
      var mx = (points[i].x + points[i + 1].x) / 2, my = (points[i].y + points[i + 1].y) / 2;
      ctx.quadraticCurveTo(points[i].x * k, points[i].y * k, mx * k, my * k);
    }

    var last = points[points.length - 1];
    ctx.lineTo(last.x * k, last.y * k);
    ctx.stroke();
  }

  var stroke = null;

  ui.ink.addEventListener('pointerdown', function (e) {
    if (state.tab !== 'draw' || state.mode !== 'brush' || e.button > 0) return;

    e.preventDefault();
    ui.ink.setPointerCapture(e.pointerId);
    stroke = [pointIn(e)];
    inkCtx.clearRect(0, 0, ui.ink.width, ui.ink.height);
    strokePath(inkCtx, stroke, ui.ink.width / ui.wrap.clientWidth, state.inkSize * ui.ink.width / ui.wrap.clientWidth);
  });

  ui.ink.addEventListener('pointermove', function (e) {
    if (!stroke) return;

    stroke.push(pointIn(e));
    inkCtx.clearRect(0, 0, ui.ink.width, ui.ink.height);
    strokePath(inkCtx, stroke, ui.ink.width / ui.wrap.clientWidth, state.inkSize * ui.ink.width / ui.wrap.clientWidth);
  });

  var endStroke = function () {
    if (!stroke) return;

    // Stamp onto a copy of the picture at full resolution.
    var out = clone(state.base);
    var k = 1 / state.scale;
    strokePath(context(out), stroke, k, state.inkSize * k);

    stroke = null;
    inkCtx.clearRect(0, 0, ui.ink.width, ui.ink.height);
    state.base = out;
    commit();
    cache.key = null;
    draw();
    refreshPresets();
  };

  ui.ink.addEventListener('pointerup', endStroke);
  ui.ink.addEventListener('pointercancel', endStroke);

  // --- Text ---
  function syncText() {
    var value = ui.text.value;
    ui.textApply.disabled = !value.trim();

    if (!value.trim() || state.mode !== 'text' || state.tab !== 'draw') {
      cancelText();
      return;
    }

    if (!textNode) {
      textNode = T.el('div', { class: 'ed-text', role: 'img' });
      ui.wrap.appendChild(textNode);
      state.textPos = null;
      bindTextDrag(textNode);
    }

    textNode.textContent = value;
    textNode.setAttribute('aria-label', 'Text to place: ' + value);
    textNode.style.color = state.ink;
    textNode.style.fontSize = fontPx() + 'px';
    textNode.style.fontWeight = ui.bold.checked ? '700' : '400';
    textNode.style.fontFamily = 'Inter, system-ui, -apple-system, "Segoe UI", sans-serif';
    placeTextNode();
  }

  function placeTextNode() {
    if (!textNode) return;

    var maxX = Math.max(0, ui.wrap.clientWidth - textNode.offsetWidth);
    var maxY = Math.max(0, ui.wrap.clientHeight - textNode.offsetHeight);

    if (!state.textPos) state.textPos = { x: maxX / 2, y: maxY / 2 };

    // Allowed to hang off the edge a little, never to vanish entirely.
    state.textPos.x = T.clamp(state.textPos.x, -textNode.offsetWidth + 24, ui.wrap.clientWidth - 24);
    state.textPos.y = T.clamp(state.textPos.y, -textNode.offsetHeight + 16, ui.wrap.clientHeight - 16);

    textNode.style.left = state.textPos.x + 'px';
    textNode.style.top = state.textPos.y + 'px';
  }

  function bindTextDrag(node) {
    var grab = null;

    node.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      node.setPointerCapture(e.pointerId);
      grab = { px: e.clientX, py: e.clientY, x: state.textPos.x, y: state.textPos.y };
    });

    node.addEventListener('pointermove', function (e) {
      if (!grab) return;
      state.textPos = { x: grab.x + e.clientX - grab.px, y: grab.y + e.clientY - grab.py };
      placeTextNode();
    });

    var drop = function () { grab = null; };
    node.addEventListener('pointerup', drop);
    node.addEventListener('pointercancel', drop);
  }

  function cancelText() {
    if (textNode) textNode.remove();
    textNode = null;
    state.textPos = null;
  }

  function applyText() {
    if (!textNode) return;

    var k = 1 / state.scale;
    var out = clone(state.base);
    var ctx = context(out);

    ctx.fillStyle = state.ink;
    ctx.textBaseline = 'top';
    ctx.font = (ui.bold.checked ? '700 ' : '400 ') + (fontPx() * k) + 'px Inter, system-ui, -apple-system, "Segoe UI", sans-serif';

    // The on-screen box has a border and padding, and its line box is
    // taller than the glyphs; land the text where the preview showed it.
    var padX = 5.5, padY = 3.5 + fontPx() * 0.075;
    ctx.fillText(textNode.textContent, (state.textPos.x + padX) * k, (state.textPos.y + padY) * k);

    cancelText();
    ui.text.value = '';
    ui.textApply.disabled = true;

    state.base = out;
    commit();
    cache.key = null;
    draw();
    refreshPresets();
    T.toast('Text added. Use undo if you want to move it.', 'success');
  }

  /* ----------------------------------------------------------------
   * Adjustments + filters
   * -------------------------------------------------------------- */
  var rangePainters = {};

  function syncSliders() {
    ui.adj.forEach(function (input) {
      var key = input.dataset.adj;
      input.value = state.adjust[key];
      q('[data-out="' + key + '"]').textContent = state.adjust[key];
      if (rangePainters[key]) rangePainters[key]();
    });

    T.$$('.ed-preset', ui.presets).forEach(function (btn) {
      btn.setAttribute('aria-pressed', btn.dataset.filter === state.adjust.filter ? 'true' : 'false');
    });
  }

  function refreshPresets() {
    if (!state.base) return;

    // One small square thumbnail of the picture, re-tinted per filter.
    var size = 96;
    var thumb = makeCanvas(size, size);
    var crop = Math.min(state.base.width, state.base.height);
    context(thumb).drawImage(state.base, (state.base.width - crop) / 2, (state.base.height - crop) / 2, crop, crop, 0, 0, size, size);
    var pixels = context(thumb).getImageData(0, 0, size, size);

    if (!ui.presets.children.length) {
      Object.keys(FILTERS).forEach(function (key) {
        var canvas = makeCanvas(size, size);

        ui.presets.appendChild(T.el('button', {
          class: 'ed-preset', type: 'button', 'data-filter': key, 'aria-pressed': 'false',
          onclick: function () {
            state.adjust.filter = key;
            syncSliders();
            draw();
            commit();
          }
        }, [canvas, T.el('span', { text: FILTERS[key].label })]));
      });
    }

    T.$$('.ed-preset', ui.presets).forEach(function (btn) {
      var frame = new ImageData(new Uint8ClampedArray(pixels.data), size, size);
      applyMatrix(frame.data, FILTERS[btn.dataset.filter].matrix);
      btn.firstChild.getContext('2d').putImageData(frame, 0, 0);
      btn.setAttribute('aria-pressed', btn.dataset.filter === state.adjust.filter ? 'true' : 'false');
    });
  }

  /* ----------------------------------------------------------------
   * Export
   * -------------------------------------------------------------- */
  function save() {
    if (state.busy || !state.base) return;

    state.busy = true;
    ui.save.disabled = true;
    var label = ui.save.innerHTML;
    ui.save.replaceChildren(T.el('span', { class: 'tl-spin' }), ' Preparing…');

    // Let the spinner paint before the (blocking) pixel work starts.
    setTimeout(function () {
      var done = function () {
        state.busy = false;
        ui.save.disabled = false;
        ui.save.innerHTML = label;
      };

      try {
        var out = render();

        if (state.format === 'image/jpeg') {
          // JPG has no transparency: flatten onto white, not black.
          var flat = makeCanvas(out.width, out.height);
          var ctx = context(flat);
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, flat.width, flat.height);
          ctx.drawImage(out, 0, 0);
          out = flat;
        }

        T.canvasToBlob(out, state.format, state.format === 'image/png' ? undefined : state.quality / 100).then(function (blob) {
          T.download(blob, state.name + '-edited.' + T.EXT[state.format]);
          T.toast('Saved as ' + T.LABEL[state.format] + ' · ' + T.formatBytes(blob.size) + '.', 'success');
        }, function (err) {
          T.toast((err && err.message) || 'The image could not be saved.', 'error');
        }).then(done);
      } catch (err) {
        T.toast((err && err.message) || 'The image could not be saved.', 'error');
        done();
      }
    }, 30);
  }

  /* ----------------------------------------------------------------
   * Wiring
   * -------------------------------------------------------------- */
  T.dropzone(ui.drop, ui.input, open);

  ui.tabs.forEach(function (tab, index) {
    tab.addEventListener('click', function () { selectTab(tab.dataset.tab); });

    // Arrow keys move between tabs, as the tablist pattern expects.
    tab.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      e.preventDefault();
      var next = ui.tabs[(index + (e.key === 'ArrowRight' ? 1 : ui.tabs.length - 1)) % ui.tabs.length];
      next.focus();
      selectTab(next.dataset.tab);
    });
  });

  ui.undo.addEventListener('click', undo);
  ui.redo.addEventListener('click', redo);

  ui.reset.addEventListener('click', function () {
    cancelText();
    state.base = state.original;
    state.adjust = Object.assign({}, ZERO);
    commit();
    restore(state.history[state.cursor]);
    T.toast('Back to the image as you opened it. Undo brings your edits back.', 'info');
  });

  ui.fresh.addEventListener('click', closeImage);

  // Hold to compare with the original.
  var showOriginal = function (on) {
    if (!state.original) return;

    if (!on) { draw(); return; }

    var w = ui.view.width, h = ui.view.height;
    var o = state.original;
    var k = Math.min(w / o.width, h / o.height);

    viewCtx.clearRect(0, 0, w, h);
    viewCtx.imageSmoothingQuality = 'high';
    viewCtx.drawImage(o, (w - o.width * k) / 2, (h - o.height * k) / 2, o.width * k, o.height * k);
  };

  ui.compare.addEventListener('pointerdown', function (e) { ui.compare.setPointerCapture(e.pointerId); showOriginal(true); });
  ui.compare.addEventListener('pointerup', function () { showOriginal(false); });
  ui.compare.addEventListener('pointercancel', function () { showOriginal(false); });
  ui.compare.addEventListener('keydown', function (e) { if ((e.key === ' ' || e.key === 'Enter') && !e.repeat) showOriginal(true); });
  ui.compare.addEventListener('keyup', function (e) { if (e.key === ' ' || e.key === 'Enter') showOriginal(false); });
  ui.compare.addEventListener('blur', function () { showOriginal(false); });

  document.addEventListener('keydown', function (e) {
    if (!state.base || !(e.ctrlKey || e.metaKey)) return;

    // Leave the browser's own undo alone inside text fields.
    var tag = (e.target && e.target.tagName) || '';
    if (tag === 'INPUT' && e.target.type !== 'range' && e.target.type !== 'radio' && e.target.type !== 'checkbox' || tag === 'TEXTAREA') return;

    var key = e.key.toLowerCase();
    if (key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
    else if ((key === 'z' && e.shiftKey) || key === 'y') { e.preventDefault(); redo(); }
  });

  // Rotate / flip
  qa('[data-rot]').forEach(function (btn) { btn.addEventListener('click', function () { rotate(parseInt(btn.dataset.rot, 10)); }); });
  qa('[data-flip]').forEach(function (btn) { btn.addEventListener('click', function () { flip(btn.dataset.flip); }); });

  // Resize
  ui.rw.addEventListener('input', function () {
    var w = readSize(ui.rw);
    if (ui.lock.checked && w !== null) ui.rh.value = Math.max(1, Math.round(w * state.base.height / state.base.width));
  });

  ui.rh.addEventListener('input', function () {
    var h = readSize(ui.rh);
    if (ui.lock.checked && h !== null) ui.rw.value = Math.max(1, Math.round(h * state.base.width / state.base.height));
  });

  ui.lock.addEventListener('change', function () { if (ui.lock.checked) ui.rw.dispatchEvent(new Event('input')); });
  ui.resizeApply.addEventListener('click', applyResize);
  [ui.rw, ui.rh].forEach(function (input) { input.addEventListener('keydown', function (e) { if (e.key === 'Enter') applyResize(); }); });

  // Crop
  ui.ratios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      var parts = radio.value.split(':');
      state.ratio = parts.length === 2 ? parseInt(parts[0], 10) / parseInt(parts[1], 10) : 0;
      startCrop();
    });
  });

  ui.cropBox.tabIndex = 0;
  ui.cropBox.setAttribute('role', 'group');
  ui.cropBox.setAttribute('aria-label', 'Crop selection. Use the arrow keys to move it.');

  var cropDrag = null;

  ui.cropBox.addEventListener('pointerdown', function (e) {
    e.preventDefault();
    ui.cropBox.setPointerCapture(e.pointerId);
    ui.cropBox.focus({ preventScroll: true });
    cropDrag = { handle: (e.target.dataset && e.target.dataset.h) || 'move', px: e.clientX, py: e.clientY, start: Object.assign({}, state.crop) };
  });

  ui.cropBox.addEventListener('pointermove', function (e) {
    if (!cropDrag) return;
    dragCrop(cropDrag.handle, cropDrag.start, (e.clientX - cropDrag.px) / state.scale, (e.clientY - cropDrag.py) / state.scale);
    paintCrop();
  });

  var endCropDrag = function () { cropDrag = null; };
  ui.cropBox.addEventListener('pointerup', endCropDrag);
  ui.cropBox.addEventListener('pointercancel', endCropDrag);

  ui.cropBox.addEventListener('keydown', function (e) {
    var step = (e.shiftKey ? 10 : 1) * Math.max(1, Math.round(Math.max(state.base.width, state.base.height) / 200));
    var move = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];

    if (move) {
      e.preventDefault();
      dragCrop('move', state.crop, move[0], move[1]);
      paintCrop();
    } else if (e.key === 'Enter') {
      e.preventDefault();
      applyCrop();
    }
  });

  ui.cropApply.addEventListener('click', applyCrop);

  // Adjust
  ui.adj.forEach(function (input) {
    var key = input.dataset.adj;
    rangePainters[key] = T.bindRange(input);

    input.addEventListener('input', function () {
      state.adjust[key] = parseInt(input.value, 10);
      q('[data-out="' + key + '"]').textContent = input.value;
      drawSoon();
    });

    // One history step per completed drag, not one per pixel of travel.
    input.addEventListener('change', commit);
  });

  ui.adjReset.addEventListener('click', function () {
    var filter = state.adjust.filter;
    var changed = ui.adj.some(function (input) { return state.adjust[input.dataset.adj] !== 0; });
    if (!changed) return;

    state.adjust = Object.assign({}, ZERO, { filter: filter });
    syncSliders();
    draw();
    commit();
  });

  // Draw
  ui.modes.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      state.mode = radio.value;
      syncMode();
      if (state.mode === 'text') ui.text.focus();
    });
  });

  T.bindColor(ui.inkColor, ui.inkText, function (hex) {
    state.ink = hex;
    if (textNode) textNode.style.color = hex;
  });

  T.bindRange(ui.inkSize);

  ui.inkSize.addEventListener('input', function () {
    state.inkSize = parseInt(ui.inkSize.value, 10);
    ui.inkSizeOut.textContent = state.inkSize;
    if (textNode) syncText();
  });

  ui.text.addEventListener('input', syncText);
  ui.text.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !ui.textApply.disabled) applyText(); });
  ui.bold.addEventListener('change', syncText);
  ui.textApply.addEventListener('click', applyText);

  // Export
  ui.formats.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      state.format = radio.value;
      ui.eqField.hidden = state.format === 'image/png';
    });
  });

  T.bindRange(ui.eq);

  ui.eq.addEventListener('input', function () {
    state.quality = parseInt(ui.eq.value, 10);
    ui.eqOut.textContent = state.quality + '%';
  });

  ui.save.addEventListener('click', save);

  T.canEncode('image/webp').then(function (ok) {
    if (ok) return;
    var radio = ui.formats.filter(function (r) { return r.value === 'image/webp'; })[0];
    if (radio) { radio.disabled = true; radio.nextElementSibling.title = 'This browser cannot save WebP files.'; }
  });

  window.addEventListener('resize', T.debounce(layout, 120));

  // Leaving with unsaved edits is the one way to lose work here.
  window.addEventListener('beforeunload', function (e) {
    if (state.base && state.history.length > 1) { e.preventDefault(); e.returnValue = ''; }
  });
})();
