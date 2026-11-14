/*
 * Iconors — Image Converter.
 *
 * Decodes with the browser's own image decoders, re-encodes through
 * <canvas> (PNG, JPG, WebP) or with the small writers below (BMP, ICO,
 * PDF). No file leaves the page.
 */
(function () {
  'use strict';

  var T = window.IcoTools;
  var root = document.querySelector('[data-converter]');
  if (!T || !root) return;

  var INPUT_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/bmp', 'image/svg+xml', 'image/avif', 'image/x-icon'];
  var INPUT_LABEL = 'PNG, JPG, WebP, SVG, GIF, BMP, AVIF or ICO';
  var OPAQUE = ['image/jpeg', 'image/bmp', 'application/pdf']; // formats with no alpha channel
  var ICO_SIZES = [16, 32, 48, 64, 128, 256];

  var ui = {
    drop: T.$('[data-drop]', root),
    input: T.$('[data-input]', root),
    list: T.$('[data-list]', root),
    progress: T.$('[data-progress]', root),
    formats: T.$$('input[name="cv-format"]', root),
    formatNote: T.$('[data-format-note]', root),
    qualityField: T.$('[data-quality-field]', root),
    quality: T.$('[data-quality]', root),
    qualityOut: T.$('[data-quality-out]', root),
    bgField: T.$('[data-bg-field]', root),
    bg: T.$('[data-bg]', root),
    bgText: T.$('[data-bg-text]', root),
    width: T.$('[data-width]', root),
    widthHelp: T.$('[data-width-help]', root),
    convert: T.$('[data-convert]', root),
    convertLabel: T.$('[data-convert-label]', root),
    downloadAll: T.$('[data-download-all]', root),
    downloadLabel: T.$('[data-download-label]', root),
    clear: T.$('[data-clear]', root)
  };

  var state = {
    items: [],
    nextId: 1,
    busy: false,
    format: 'image/png',
    quality: 90,
    background: '#ffffff',
    width: null,
    widthInvalid: false
  };

  var WIDTH_HELP = ui.widthHelp.textContent;

  /* ----------------------------------------------------------------
   * Encoders the browser does not provide
   * -------------------------------------------------------------- */

  /** 24-bit uncompressed BMP. Rows are stored bottom-up, BGR, each row
      padded to a multiple of four bytes. */
  function encodeBmp(canvas) {
    var w = canvas.width, h = canvas.height;
    var data = canvas.getContext('2d').getImageData(0, 0, w, h).data;
    var rowSize = Math.floor((24 * w + 31) / 32) * 4;
    var size = 54 + rowSize * h;
    var buffer = new ArrayBuffer(size);
    var view = new DataView(buffer);
    var out = new Uint8Array(buffer);

    view.setUint8(0, 0x42); view.setUint8(1, 0x4d);  // "BM"
    view.setUint32(2, size, true);
    view.setUint32(10, 54, true);                    // pixel data offset
    view.setUint32(14, 40, true);                    // BITMAPINFOHEADER
    view.setInt32(18, w, true);
    view.setInt32(22, h, true);                      // positive = bottom-up
    view.setUint16(26, 1, true);                     // planes
    view.setUint16(28, 24, true);                    // bits per pixel
    view.setUint32(34, rowSize * h, true);
    view.setInt32(38, 2835, true);                   // 72 dpi
    view.setInt32(42, 2835, true);

    for (var y = 0; y < h; y++) {
      var src = (h - 1 - y) * w * 4;
      var dst = 54 + y * rowSize;

      for (var x = 0; x < w; x++) {
        out[dst++] = data[src + 2];
        out[dst++] = data[src + 1];
        out[dst++] = data[src];
        src += 4;
      }
    }

    return new Blob([buffer], { type: 'image/bmp' });
  }

  /** ICO holding PNG-compressed images, the layout every current
      Windows version and browser reads. A size byte of 0 means 256. */
  function encodeIco(pngs) {
    var header = new ArrayBuffer(6 + 16 * pngs.length);
    var view = new DataView(header);
    var offset = header.byteLength;

    view.setUint16(2, 1, true);            // type: icon
    view.setUint16(4, pngs.length, true);

    pngs.forEach(function (png, i) {
      var at = 6 + i * 16;
      view.setUint8(at, png.size >= 256 ? 0 : png.size);
      view.setUint8(at + 1, png.size >= 256 ? 0 : png.size);
      view.setUint16(at + 4, 1, true);     // colour planes
      view.setUint16(at + 6, 32, true);    // bits per pixel
      view.setUint32(at + 8, png.bytes.byteLength, true);
      view.setUint32(at + 12, offset, true);
      offset += png.bytes.byteLength;
    });

    return new Blob([header].concat(pngs.map(function (p) { return p.bytes; })), { type: 'image/x-icon' });
  }

  /** One-page PDF wrapping a JPEG. The page is sized to the image at
      96 dpi (1 px = 0.75 pt), so it prints at its natural size. */
  function encodePdf(jpegBytes, w, h) {
    var pageW = +(w * 0.75).toFixed(2);
    var pageH = +(h * 0.75).toFixed(2);
    var enc = new TextEncoder();
    var parts = [];
    var offsets = [];
    var length = 0;

    var push = function (chunk) {
      var bytes = typeof chunk === 'string' ? enc.encode(chunk) : chunk;
      parts.push(bytes);
      length += bytes.byteLength;
    };

    var obj = function (n, body) {
      offsets[n] = length;
      push(n + ' 0 obj\n' + body + '\nendobj\n');
    };

    var content = 'q ' + pageW + ' 0 0 ' + pageH + ' 0 0 cm /Im0 Do Q';

    push('%PDF-1.4\n%âãÏÓ\n');
    obj(1, '<< /Type /Catalog /Pages 2 0 R >>');
    obj(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    obj(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pageW + ' ' + pageH + '] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>');

    offsets[4] = length;
    push('4 0 obj\n<< /Type /XObject /Subtype /Image /Width ' + w + ' /Height ' + h + ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' + jpegBytes.byteLength + ' >>\nstream\n');
    push(jpegBytes);
    push('\nendstream\nendobj\n');

    obj(5, '<< /Length ' + content.length + ' >>\nstream\n' + content + '\nendstream');

    var xref = length;
    var table = 'xref\n0 6\n0000000000 65535 f \n';
    for (var i = 1; i <= 5; i++) table += ('0000000000' + offsets[i]).slice(-10) + ' 00000 n \n';

    push(table + 'trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF\n');

    return new Blob(parts, { type: 'application/pdf' });
  }

  /* ----------------------------------------------------------------
   * Conversion
   * -------------------------------------------------------------- */
  function targetSize(image) {
    var w = image.width, h = image.height;

    if (state.width) {
      // Raster images are never enlarged: upscaling only adds blur.
      // Vectors scale freely.
      var want = image.vector ? state.width : Math.min(state.width, w);
      h = Math.max(1, Math.round(h * (want / w)));
      w = want;
    }

    return { width: w, height: h };
  }

  function convertOne(item) {
    return T.loadImage(item.file).then(function (image) {
      var done = function (blob) { image.release(); return blob; };
      var failed = function (err) { image.release(); throw err; };
      var size = targetSize(image);
      var format = state.format;

      if (size.width > T.MAX_SIDE || size.height > T.MAX_SIDE || size.width * size.height > T.MAX_PIXELS) {
        return failed(new Error('That width would make the image ' + size.width + ' × ' + size.height + ' px, which is too large. Try a smaller width.'));
      }

      try {
        if (format === 'image/x-icon') {
          // Each size is the artwork fitted inside a transparent square.
          return Promise.all(ICO_SIZES.map(function (px) {
            var canvas = document.createElement('canvas');
            canvas.width = canvas.height = px;

            var ctx = canvas.getContext('2d');
            var scale = Math.min(px / image.width, px / image.height);
            var dw = Math.max(1, Math.round(image.width * scale));
            var dh = Math.max(1, Math.round(image.height * scale));

            ctx.imageSmoothingQuality = 'high';
            ctx.drawImage(image.source, Math.round((px - dw) / 2), Math.round((px - dh) / 2), dw, dh);

            return T.canvasToBlob(canvas, 'image/png')
              .then(function (blob) { return blob.arrayBuffer(); })
              .then(function (bytes) { return { size: px, bytes: bytes }; });
          })).then(encodeIco).then(done, failed);
        }

        var opaque = OPAQUE.indexOf(format) !== -1;
        var canvas = T.toCanvas(image, size.width, size.height, opaque ? state.background : null);

        if (format === 'image/bmp') return Promise.resolve(done(encodeBmp(canvas)));

        if (format === 'application/pdf') {
          return T.canvasToBlob(canvas, 'image/jpeg', 0.92)
            .then(function (blob) { return blob.arrayBuffer(); })
            .then(function (bytes) { return encodePdf(new Uint8Array(bytes), canvas.width, canvas.height); })
            .then(done, failed);
        }

        var quality = format === 'image/png' ? undefined : state.quality / 100;
        return T.canvasToBlob(canvas, format, quality).then(done, failed);
      } catch (err) {
        return failed(err);
      }
    });
  }

  function run() {
    var queue = state.items.filter(function (item) { return item.status === 'ready'; });
    if (!queue.length || state.busy) return;

    state.busy = true;
    var finished = 0;
    var failed = 0;

    ui.progress.hidden = false;
    setProgress(0);
    renderControls();

    // One at a time: decoding several large images at once can exhaust
    // memory on phones, and the progress bar stays meaningful.
    var next = function () {
      var item = queue.shift();

      if (!item) {
        state.busy = false;
        ui.progress.hidden = true;
        renderControls();

        var ok = finished - failed;
        if (ok) T.toast(ok + ' file' + (ok === 1 ? '' : 's') + ' converted to ' + T.LABEL[state.format] + '.', 'success');
        if (failed) T.toast(failed + ' file' + (failed === 1 ? '' : 's') + ' could not be converted. See the list for details.', 'error');
        return;
      }

      item.status = 'working';
      renderItem(item);

      convertOne(item).then(function (blob) {
        item.status = 'done';
        item.out = blob;
        item.outName = T.baseName(item.file.name) + '.' + T.EXT[state.format];
        item.error = null;
      }, function (err) {
        item.status = 'error';
        item.out = null;
        item.error = (err && err.message) || 'This file could not be converted.';
        failed++;
      }).then(function () {
        finished++;
        setProgress(finished / (finished + queue.length));
        renderItem(item);

        // Yield so the browser can paint between files.
        setTimeout(next, 0);
      });
    };

    next();
  }

  /* ----------------------------------------------------------------
   * Rendering
   * -------------------------------------------------------------- */
  function setProgress(fraction) {
    ui.progress.firstElementChild.style.inlineSize = Math.round(fraction * 100) + '%';
  }

  function renderItem(item) {
    var node = item.node;
    node.className = 'tl-file' + (item.status === 'error' ? ' is-error' : '');

    var meta = [T.el('span', { text: T.LABEL[item.type] || 'Image' }), T.el('span', { text: T.formatBytes(item.file.size) })];

    if (item.status === 'done') {
      meta.push(T.icon('bi-arrow-right'));
      meta.push(T.el('strong', { text: T.LABEL[item.out.type] + ' · ' + T.formatBytes(item.out.size) }));
    }

    var badge;
    if (item.status === 'working') badge = T.el('span', { class: 'tl-badge tl-badge--info' }, [T.el('span', { class: 'tl-spin' }), 'Converting']);
    else if (item.status === 'done') badge = T.el('span', { class: 'tl-badge tl-badge--ok' }, [T.icon('bi-check-lg'), 'Done']);
    else if (item.status === 'error') badge = T.el('span', { class: 'tl-badge tl-badge--error', text: 'Failed' });
    else badge = T.el('span', { class: 'tl-badge tl-badge--muted', text: 'Ready' });

    var body = T.el('div', { class: 'tl-file__body' }, [
      T.el('p', { class: 'tl-file__name', text: item.file.name, title: item.file.name }),
      T.el('p', { class: 'tl-file__meta' }, meta),
      item.error ? T.el('p', { class: 'tl-help tl-help--error', text: item.error }) : null
    ]);

    var side = T.el('div', { class: 'tl-file__side' }, [
      badge,
      item.status === 'done' ? T.el('button', {
        class: 'tl-iconbtn tl-iconbtn--go', type: 'button',
        'aria-label': 'Download ' + item.outName, title: 'Download',
        onclick: function () { T.download(item.out, item.outName); }
      }, [T.icon('bi-download')]) : null,
      T.el('button', {
        class: 'tl-iconbtn', type: 'button', disabled: state.busy,
        'aria-label': 'Remove ' + item.file.name, title: 'Remove',
        onclick: function () { remove(item); }
      }, [T.icon('bi-x-lg')])
    ]);

    node.replaceChildren(T.el('div', { class: 'tl-file__main' }, [item.thumbNode, body]), side);
  }

  function renderControls() {
    var count = state.items.length;
    // A failed file is not retried until a setting changes; converting it
    // again unchanged would only fail the same way.
    var pending = state.items.filter(function (i) { return i.status === 'ready'; }).length;
    var done = state.items.filter(function (i) { return i.status === 'done'; });

    ui.drop.classList.toggle('tl-drop--compact', count > 0);

    ui.convert.disabled = state.busy || pending === 0 || state.widthInvalid;
    ui.convertLabel.textContent = state.busy
      ? 'Converting…'
      : (pending ? 'Convert ' + pending + ' file' + (pending === 1 ? '' : 's') + ' to ' + T.LABEL[state.format] : 'Convert');

    ui.downloadAll.hidden = done.length === 0;
    ui.downloadAll.disabled = state.busy;
    ui.downloadLabel.textContent = done.length > 1 ? 'Download all as ZIP (' + done.length + ')' : 'Download';

    ui.clear.hidden = count === 0;
    ui.clear.disabled = state.busy;

    T.$$('.tl-iconbtn[aria-label^="Remove"]', ui.list).forEach(function (btn) { btn.disabled = state.busy; });
  }

  function renderSettings() {
    var format = state.format;
    var lossy = format === 'image/jpeg' || format === 'image/webp';

    ui.qualityField.hidden = !lossy;
    ui.bgField.hidden = OPAQUE.indexOf(format) === -1;

    var notes = {
      'image/png': 'Lossless, keeps transparency. Best for logos, icons and screenshots.',
      'image/jpeg': 'Small files for photos. No transparency.',
      'image/webp': 'Smaller than JPG and PNG, keeps transparency. Supported by every modern browser.',
      'image/bmp': 'Uncompressed 24-bit bitmap. Large files, no transparency.',
      'image/x-icon': 'Multi-size icon (16 to 256 px) for favicons and Windows apps. Square images work best.',
      'application/pdf': 'A one-page PDF sized to the image.'
    };

    ui.formatNote.textContent = notes[format] || '';

    // An icon is always the fixed set of sizes above.
    ui.width.disabled = format === 'image/x-icon';
    ui.widthHelp.textContent = format === 'image/x-icon' ? 'Icons are saved at fixed sizes, so width does not apply.' : WIDTH_HELP;
  }

  /* ----------------------------------------------------------------
   * State changes
   * -------------------------------------------------------------- */

  /** A setting changed, so finished results no longer match it. */
  function invalidate() {
    state.items.forEach(function (item) {
      if (item.status === 'done' || item.status === 'error') {
        item.status = 'ready';
        item.out = null;
        item.error = null;
        renderItem(item);
      }
    });

    renderControls();
  }

  function add(fileList) {
    if (state.busy) {
      T.toast('Wait for the current conversion to finish, then add more files.', 'info');
      return;
    }

    var screened = T.screenFiles(fileList, { types: INPUT_TYPES, label: INPUT_LABEL, room: T.MAX_FILES - state.items.length });
    screened.errors.forEach(function (message) { T.toast(message, 'error'); });

    screened.accepted.forEach(function (file) {
      var url = URL.createObjectURL(file);
      var thumb = T.el('div', { class: 'tl-file__thumb' }, [T.el('img', { src: url, alt: '', loading: 'lazy', decoding: 'async' })]);

      // A browser that cannot decode this type shows a file icon instead
      // of a broken-image marker; the real error appears on convert.
      thumb.firstChild.addEventListener('error', function () { thumb.replaceChildren(T.icon('bi-file-earmark-image')); });

      var item = {
        id: state.nextId++,
        file: file,
        type: T.typeOf(file),
        status: 'ready',
        out: null,
        outName: '',
        error: null,
        thumbUrl: url,
        thumbNode: thumb,
        node: T.el('li', { class: 'tl-file' })
      };

      state.items.push(item);
      ui.list.appendChild(item.node);
      renderItem(item);
    });

    renderControls();
  }

  function remove(item) {
    if (state.busy) return;

    URL.revokeObjectURL(item.thumbUrl);
    item.node.remove();
    state.items = state.items.filter(function (i) { return i !== item; });
    renderControls();
  }

  function clearAll() {
    if (state.busy) return;

    state.items.forEach(function (item) { URL.revokeObjectURL(item.thumbUrl); });
    state.items = [];
    ui.list.replaceChildren();
    renderControls();
  }

  function downloadAll() {
    var done = state.items.filter(function (i) { return i.status === 'done'; });
    if (!done.length) return;

    if (done.length === 1) {
      T.download(done[0].out, done[0].outName);
      return;
    }

    ui.downloadAll.disabled = true;

    T.zip(done.map(function (i) { return { name: i.outName, blob: i.out }; })).then(function (zip) {
      T.download(zip, 'iconors-converted.zip');
      T.toast('ZIP with ' + done.length + ' files is downloading.', 'success');
    }, function (err) {
      T.toast(err.message || 'Could not build the ZIP.', 'error');
    }).then(function () {
      ui.downloadAll.disabled = state.busy;
    });
  }

  /* ----------------------------------------------------------------
   * Wiring
   * -------------------------------------------------------------- */
  T.dropzone(ui.drop, ui.input, add);

  ui.formats.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      state.format = radio.value;
      renderSettings();
      invalidate();
    });
  });

  var paintQuality = T.bindRange(ui.quality);

  ui.quality.addEventListener('input', function () {
    state.quality = parseInt(ui.quality.value, 10);
    ui.qualityOut.textContent = state.quality + '%';
  });

  ui.quality.addEventListener('change', invalidate);

  T.bindColor(ui.bg, ui.bgText, function (hex) {
    if (hex === state.background) return;
    state.background = hex;
    invalidate();
  });

  ui.width.addEventListener('input', function () {
    var raw = ui.width.value.trim();
    var value = parseInt(raw, 10);
    var valid = raw === '' || (/^\d+$/.test(raw) && value >= 1 && value <= T.MAX_SIDE);

    ui.width.classList.toggle('is-invalid', !valid);
    ui.widthHelp.classList.toggle('tl-help--error', !valid);
    ui.widthHelp.textContent = valid ? WIDTH_HELP : 'Enter a whole number between 1 and ' + T.MAX_SIDE + '.';

    // Converting at the original size while the box shows a rejected
    // number would be a surprise, so an invalid width blocks the button.
    state.widthInvalid = !valid;

    var next = valid && raw !== '' ? value : null;
    if (next !== state.width) {
      state.width = next;
      invalidate();
    } else {
      renderControls();
    }
  });

  ui.convert.addEventListener('click', run);
  ui.downloadAll.addEventListener('click', downloadAll);
  ui.clear.addEventListener('click', clearAll);

  // WebP cannot be written by every browser (older Safari). Disable the
  // option up front instead of failing after the visitor has chosen it.
  T.canEncode('image/webp').then(function (ok) {
    if (ok) return;

    var radio = ui.formats.filter(function (r) { return r.value === 'image/webp'; })[0];
    if (!radio) return;

    radio.disabled = true;
    radio.nextElementSibling.title = 'This browser cannot save WebP files.';
  });

  paintQuality();
  renderSettings();
  renderControls();
})();
