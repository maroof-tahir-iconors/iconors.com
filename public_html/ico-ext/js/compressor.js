/*
 * Iconors — Image Compressor.
 *
 * JPG and WebP are re-encoded through <canvas> at the chosen quality.
 * PNG has no quality setting of its own, so it is reduced to a palette
 * with UPNG.js in a worker (the pngquant approach). No file leaves the
 * page, and a result that is not smaller than the original is discarded
 * in favour of the original.
 */
(function () {
  'use strict';

  var T = window.IcoTools;
  var root = document.querySelector('[data-compressor]');
  if (!T || !root) return;

  var INPUT_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  var INPUT_LABEL = 'JPG, PNG or WebP';

  var ui = {
    drop: T.$('[data-drop]', root),
    input: T.$('[data-input]', root),
    list: T.$('[data-list]', root),
    summary: T.$('[data-summary]', root),
    summaryText: T.$('[data-summary-text]', root),
    downloadAll: T.$('[data-download-all]', root),
    downloadLabel: T.$('[data-download-label]', root),
    clear: T.$('[data-clear]', root),
    quality: T.$('[data-quality]', root),
    qualityOut: T.$('[data-quality-out]', root),
    formats: T.$$('input[name="cp-format"]', root),
    formatNote: T.$('[data-format-note]', root),
    max: T.$('[data-max]', root),
    preview: T.$('[data-preview]', root),
    previewName: T.$('[data-preview-name]', root),
    compare: T.$('[data-compare]', root),
    before: T.$('[data-before]', root),
    after: T.$('[data-after]', root),
    beforeTag: T.$('[data-before-tag]', root),
    afterTag: T.$('[data-after-tag]', root),
    compareBusy: T.$('[data-compare-busy]', root),
    split: T.$('[data-split]', root)
  };

  var state = {
    items: [],
    selected: null,
    quality: 75,
    format: 'keep',
    max: 0,
    // Bumped whenever a setting changes. A result that comes back for an
    // older generation is thrown away instead of being shown.
    generation: 0,
    running: false
  };

  /* ----------------------------------------------------------------
   * PNG quantisation (worker, with an on-page fallback)
   * -------------------------------------------------------------- */
  var worker = null;
  var workerBroken = false;
  var jobs = {};
  var nextJob = 1;

  function getWorker() {
    if (worker || workerBroken || !window.Worker) return worker;

    try {
      var url = root.dataset.worker + (root.dataset.worker.indexOf('?') === -1 ? '?' : '&') + 'upng=' + encodeURIComponent(root.dataset.upng);
      worker = new Worker(url);

      worker.onmessage = function (event) {
        var job = jobs[event.data.id];
        if (!job) return;
        delete jobs[event.data.id];

        if (event.data.error) job.reject(new Error(event.data.error));
        else job.resolve(event.data.png);
      };

      worker.onerror = function () {
        // The worker script failed to load or crashed. Fail what was
        // waiting and use the on-page path from now on.
        workerBroken = true;
        worker = null;
        Object.keys(jobs).forEach(function (id) { jobs[id].reject(new Error('worker')); delete jobs[id]; });
      };
    } catch (err) {
      workerBroken = true;
      worker = null;
    }

    return worker;
  }

  var upngOnPage = null;

  function loadUpngOnPage() {
    if (window.UPNG) return Promise.resolve(window.UPNG);

    return upngOnPage || (upngOnPage = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = root.dataset.upng;
      script.onload = function () { window.UPNG ? resolve(window.UPNG) : reject(new Error('The PNG compressor did not load. Reload the page and try again.')); };
      script.onerror = function () { reject(new Error('The PNG compressor did not load. Check your connection and reload the page.')); };
      document.head.appendChild(script);
    }));
  }

  function encodePng(imageData, colours) {
    var w = imageData.width, h = imageData.height;

    var onPage = function () {
      // Read the pixels again: the first buffer was transferred away.
      return loadUpngOnPage().then(function (UPNG) { return UPNG.encode([imageData.data.buffer.slice(0)], w, h, colours); });
    };

    var active = getWorker();
    if (!active) return onPage();

    return new Promise(function (resolve, reject) {
      var id = nextJob++;
      var pixels = imageData.data.buffer.slice(0);

      jobs[id] = { resolve: resolve, reject: reject };
      active.postMessage({ id: id, pixels: pixels, width: w, height: h, colours: colours }, [pixels]);
    }).catch(function (err) {
      if (err && err.message === 'worker') return onPage();
      throw err;
    });
  }

  /** Slider position → palette size. 100 is lossless; below that the
      palette shrinks on a curve, because the visible difference between
      256 and 200 colours is far smaller than between 40 and 20. */
  function paletteFor(quality) {
    if (quality >= 100) return 0;
    return T.clamp(Math.round(256 * Math.pow(quality / 82, 1.7)), 8, 256);
  }

  /* ----------------------------------------------------------------
   * Compression
   * -------------------------------------------------------------- */
  function compressOne(item) {
    var targetType = state.format === 'keep' ? item.type : state.format;
    var quality = state.quality;
    var max = state.max;

    return T.loadImage(item.file).then(function (image) {
      var w = image.width, h = image.height;
      var longest = Math.max(w, h);
      var resized = max > 0 && longest > max;

      if (resized) {
        var scale = max / longest;
        w = Math.max(1, Math.round(w * scale));
        h = Math.max(1, Math.round(h * scale));
      }

      var canvas;
      try {
        // JPG has no alpha, so flatten onto white rather than black.
        canvas = T.toCanvas(image, w, h, targetType === 'image/jpeg' ? '#ffffff' : null);
      } finally {
        image.release();
      }

      var encode;

      if (targetType === 'image/png') {
        var pixels = canvas.getContext('2d').getImageData(0, 0, w, h);
        encode = encodePng(pixels, paletteFor(quality)).then(function (buffer) {
          return new Blob([buffer], { type: 'image/png' });
        });
      } else {
        encode = T.canvasToBlob(canvas, targetType, quality / 100);
      }

      return encode.then(function (blob) {
        // Same format, same dimensions and no smaller: the original was
        // already tighter than anything we can produce. Keep it.
        var keepOriginal = blob.size >= item.file.size && targetType === item.type && !resized;

        return {
          blob: keepOriginal ? item.file : blob,
          type: keepOriginal ? item.type : targetType,
          width: keepOriginal ? image.width : w,
          height: keepOriginal ? image.height : h,
          kept: keepOriginal
        };
      });
    });
  }

  function pump() {
    if (state.running) return;

    var generation = state.generation;

    // The image on screen in the comparison goes first, so moving the
    // slider gives feedback on what the visitor is actually looking at.
    var item = (state.selected && state.selected.generation !== generation && state.selected.status !== 'error-fatal' ? state.selected : null)
      || state.items.filter(function (i) { return i.generation !== generation && i.status !== 'error-fatal'; })[0];

    if (!item) {
      renderSummary();
      return;
    }

    state.running = true;
    item.status = 'working';
    renderItem(item);
    if (item === state.selected) ui.compareBusy.hidden = false;

    compressOne(item).then(function (result) {
      if (generation !== state.generation) return; // settings moved on

      item.status = 'done';
      item.result = result;
      item.error = null;
      item.generation = generation;

      if (item.resultUrl) URL.revokeObjectURL(item.resultUrl);
      item.resultUrl = URL.createObjectURL(result.blob);
    }, function (err) {
      if (generation !== state.generation) return;

      // A file that cannot be decoded will not start decoding because a
      // slider moved, so it is not retried on later generations.
      item.status = /Could not read|too large/.test((err && err.message) || '') ? 'error-fatal' : 'error';
      item.result = null;
      item.error = (err && err.message) || 'This image could not be compressed.';
      item.generation = generation;
    }).then(function () {
      state.running = false;

      if (item.status === 'working') item.status = 'queued'; // superseded mid-flight
      if (state.items.indexOf(item) !== -1) renderItem(item);
      if (item === state.selected) renderPreview();

      renderSummary();
      setTimeout(pump, 0);
    });
  }

  /** Settings changed: everything needs doing again. */
  var restart = T.debounce(function () {
    state.generation++;
    state.items.forEach(function (item) {
      if (item.status !== 'error-fatal') {
        item.status = 'queued';
        renderItem(item);
      }
    });
    renderSummary();
    pump();
  }, 220);

  /* ----------------------------------------------------------------
   * Rendering
   * -------------------------------------------------------------- */
  function savedPercent(item) {
    return Math.max(0, Math.round((1 - item.result.blob.size / item.file.size) * 100));
  }

  function outName(item) {
    var ext = T.EXT[item.result.type];
    // Same name as the original would overwrite it in the visitor's
    // Downloads folder, so mark the copy.
    return T.baseName(item.file.name) + '-min.' + ext;
  }

  function renderItem(item) {
    var node = item.node;
    var fatal = item.status === 'error' || item.status === 'error-fatal';
    node.className = 'tl-file' + (item === state.selected ? ' is-selected' : '') + (fatal ? ' is-error' : '');

    var meta = [T.el('span', { text: T.LABEL[item.type] }), T.el('span', { text: T.formatBytes(item.file.size) })];
    var badge;

    if (item.status === 'done') {
      meta.push(T.icon('bi-arrow-right'));
      meta.push(T.el('strong', { text: (item.result.type !== item.type ? T.LABEL[item.result.type] + ' · ' : '') + T.formatBytes(item.result.blob.size) }));
      if (item.result.width !== item.width && item.width) meta.push(T.el('span', { text: item.result.width + ' × ' + item.result.height + ' px' }));

      badge = item.result.kept
        ? T.el('span', { class: 'tl-badge tl-badge--muted', text: 'Already optimised', title: 'This file could not be made smaller at these settings, so the original is kept.' })
        : T.el('span', { class: 'tl-badge tl-badge--ok', text: '−' + savedPercent(item) + '%' });
    } else if (fatal) {
      badge = T.el('span', { class: 'tl-badge tl-badge--error', text: 'Failed' });
    } else {
      badge = T.el('span', { class: 'tl-badge tl-badge--info' }, [T.el('span', { class: 'tl-spin' }), item.status === 'working' ? 'Compressing' : 'Waiting']);
    }

    var pick = T.el('button', {
      class: 'tl-file__main', type: 'button',
      'aria-label': 'Preview ' + item.file.name, 'aria-pressed': item === state.selected ? 'true' : 'false',
      onclick: function () { select(item); }
    }, [
      item.thumbNode,
      // Spans, not <p>/<div>: a button may only contain phrasing content.
      T.el('span', { class: 'tl-file__body' }, [
        T.el('span', { class: 'tl-file__name', text: item.file.name, title: item.file.name }),
        T.el('span', { class: 'tl-file__meta' }, meta),
        item.error ? T.el('span', { class: 'tl-help tl-help--error', text: item.error }) : null
      ])
    ]);

    var side = T.el('div', { class: 'tl-file__side' }, [
      badge,
      item.status === 'done' ? T.el('button', {
        class: 'tl-iconbtn tl-iconbtn--go', type: 'button',
        'aria-label': 'Download ' + outName(item), title: 'Download',
        onclick: function () { T.download(item.result.blob, outName(item)); }
      }, [T.icon('bi-download')]) : null,
      T.el('button', {
        class: 'tl-iconbtn', type: 'button',
        'aria-label': 'Remove ' + item.file.name, title: 'Remove',
        onclick: function () { remove(item); }
      }, [T.icon('bi-x-lg')])
    ]);

    node.replaceChildren(pick, side);
  }

  function renderSummary() {
    var count = state.items.length;
    var done = state.items.filter(function (i) { return i.status === 'done'; });
    var pending = state.items.filter(function (i) { return i.status === 'queued' || i.status === 'working'; }).length;

    ui.drop.classList.toggle('tl-drop--compact', count > 0);
    ui.summary.hidden = count === 0;
    if (!count) return;

    var before = 0, after = 0;
    done.forEach(function (i) { before += i.file.size; after += i.result.blob.size; });

    var html;
    if (!done.length) {
      html = pending ? 'Compressing ' + pending + ' image' + (pending === 1 ? '' : 's') + '…' : 'No images could be compressed.';
      ui.summaryText.textContent = html;
    } else {
      var saved = before > 0 ? Math.max(0, Math.round((1 - after / before) * 100)) : 0;

      ui.summaryText.replaceChildren(
        T.el('strong', { text: T.formatBytes(before) }), ' → ', T.el('strong', { text: T.formatBytes(after) }),
        ' · saved ', T.el('strong', { text: saved + '%' }),
        ' across ' + done.length + ' image' + (done.length === 1 ? '' : 's') + (pending ? ' (' + pending + ' still compressing)' : '')
      );
    }

    ui.downloadAll.disabled = done.length === 0 || pending > 0;
    ui.downloadLabel.textContent = done.length > 1 ? 'Download all as ZIP' : 'Download';
  }

  function renderPreview() {
    var item = state.selected;

    if (!item || item.status === 'error' || item.status === 'error-fatal') {
      ui.preview.hidden = true;
      return;
    }

    ui.preview.hidden = false;
    ui.previewName.textContent = item.file.name;
    if (item.width && item.height) ui.compare.style.setProperty('--tl-ratio', item.width + ' / ' + item.height);

    if (ui.before.dataset.for !== String(item.id)) {
      ui.before.src = item.thumbUrl;
      ui.before.dataset.for = String(item.id);
      // Until the first result arrives, show the original on both sides
      // instead of the previous image's compressed copy.
      ui.after.src = item.resultUrl || item.thumbUrl;
    }

    ui.beforeTag.textContent = 'Original · ' + T.formatBytes(item.file.size);

    if (item.status === 'done') {
      ui.after.src = item.resultUrl;
      ui.afterTag.textContent = 'Compressed · ' + T.formatBytes(item.result.blob.size);
      ui.compareBusy.hidden = true;
    } else {
      ui.afterTag.textContent = 'Compressed';
      ui.compareBusy.hidden = false;
    }
  }

  /* ----------------------------------------------------------------
   * State changes
   * -------------------------------------------------------------- */
  function select(item) {
    var previous = state.selected;
    state.selected = item;

    if (previous && previous !== item && state.items.indexOf(previous) !== -1) renderItem(previous);
    if (item) renderItem(item);
    renderPreview();
  }

  function add(fileList) {
    var screened = T.screenFiles(fileList, { types: INPUT_TYPES, label: INPUT_LABEL, room: T.MAX_FILES - state.items.length });
    screened.errors.forEach(function (message) { T.toast(message, 'error'); });

    screened.accepted.forEach(function (file) {
      var url = URL.createObjectURL(file);
      var img = T.el('img', { src: url, alt: '', loading: 'lazy', decoding: 'async' });
      var thumb = T.el('span', { class: 'tl-file__thumb' }, [img]);

      var item = {
        id: Date.now() + Math.random(),
        file: file,
        type: T.typeOf(file),
        status: 'queued',
        generation: -1,
        result: null,
        resultUrl: null,
        error: null,
        width: 0,
        height: 0,
        thumbUrl: url,
        thumbNode: thumb,
        node: T.el('li', { class: 'tl-file' })
      };

      // The thumbnail doubles as a cheap way to learn the dimensions for
      // the comparison's aspect ratio.
      img.addEventListener('load', function () {
        item.width = img.naturalWidth;
        item.height = img.naturalHeight;
        if (item === state.selected) renderPreview();
      });

      img.addEventListener('error', function () { thumb.replaceChildren(T.icon('bi-file-earmark-image')); });

      state.items.push(item);
      ui.list.appendChild(item.node);
      renderItem(item);
    });

    if (!state.selected && state.items.length) select(state.items[0]);

    renderSummary();
    pump();
  }

  function release(item) {
    URL.revokeObjectURL(item.thumbUrl);
    if (item.resultUrl) URL.revokeObjectURL(item.resultUrl);
    item.node.remove();
  }

  function remove(item) {
    release(item);
    state.items = state.items.filter(function (i) { return i !== item; });

    if (state.selected === item) {
      state.selected = null;
      ui.before.dataset.for = '';
      select(state.items[0] || null);
    }

    renderSummary();
  }

  function clearAll() {
    state.items.forEach(release);
    state.items = [];
    state.selected = null;
    state.generation++; // orphan anything still in flight
    ui.before.dataset.for = '';
    renderPreview();
    renderSummary();
  }

  function downloadAll() {
    var done = state.items.filter(function (i) { return i.status === 'done'; });
    if (!done.length) return;

    if (done.length === 1) {
      T.download(done[0].result.blob, outName(done[0]));
      return;
    }

    ui.downloadAll.disabled = true;

    T.zip(done.map(function (i) { return { name: outName(i), blob: i.result.blob }; })).then(function (zip) {
      T.download(zip, 'iconors-compressed.zip');
      T.toast('ZIP with ' + done.length + ' images is downloading.', 'success');
    }, function (err) {
      T.toast(err.message || 'Could not build the ZIP.', 'error');
    }).then(renderSummary);
  }

  /* ----------------------------------------------------------------
   * Wiring
   * -------------------------------------------------------------- */
  T.dropzone(ui.drop, ui.input, add);
  T.bindRange(ui.quality);

  ui.quality.addEventListener('input', function () {
    state.quality = parseInt(ui.quality.value, 10);
    ui.qualityOut.textContent = state.quality + '%';
    restart();
  });

  ui.formats.forEach(function (radio) {
    radio.addEventListener('change', function () {
      if (!radio.checked) return;
      state.format = radio.value;

      ui.formatNote.textContent = {
        keep: 'Each file keeps its own format.',
        'image/jpeg': 'Everything is saved as JPG. Transparent areas become white.',
        'image/webp': 'Everything is saved as WebP, usually the smallest option. Transparency is kept.'
      }[state.format];

      restart();
    });
  });

  ui.max.addEventListener('change', function () {
    state.max = parseInt(ui.max.value, 10) || 0;
    restart();
  });

  ui.split.addEventListener('input', function () {
    ui.compare.style.setProperty('--tl-split', ui.split.value + '%');
  });

  ui.downloadAll.addEventListener('click', downloadAll);
  ui.clear.addEventListener('click', clearAll);

  T.canEncode('image/webp').then(function (ok) {
    if (ok) return;

    var radio = ui.formats.filter(function (r) { return r.value === 'image/webp'; })[0];
    if (radio) {
      radio.disabled = true;
      radio.nextElementSibling.title = 'This browser cannot save WebP files.';
    }

    // WebP sources cannot be re-encoded here either; say so up front.
    INPUT_TYPES = ['image/jpeg', 'image/png'];
    INPUT_LABEL = 'JPG or PNG (this browser cannot save WebP)';
  });

  renderSummary();
})();
