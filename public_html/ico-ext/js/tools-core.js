/*
 * Iconors tools — shared helpers (window.IcoTools).
 *
 * Everything the tools do happens in this browser tab. Nothing in this
 * file, or in the tool scripts that build on it, sends a file anywhere:
 * there is no fetch, no XHR and no form post. Images are decoded with the
 * browser's own decoders and written back out through <canvas>.
 */
(function () {
  'use strict';

  var T = {};

  /* Limits. Generous for real work, low enough that one accidental
     drop of a camera roll cannot freeze the tab. */
  T.MAX_FILE_BYTES = 40 * 1024 * 1024;
  T.MAX_FILES = 30;
  T.MAX_SIDE = 12000;          // px, either edge
  T.MAX_PIXELS = 60 * 1000000; // px, total area

  T.MIME = {
    png: 'image/png',
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    webp: 'image/webp',
    gif: 'image/gif',
    bmp: 'image/bmp',
    svg: 'image/svg+xml',
    avif: 'image/avif',
    ico: 'image/x-icon'
  };

  T.LABEL = {
    'image/png': 'PNG',
    'image/jpeg': 'JPG',
    'image/webp': 'WebP',
    'image/gif': 'GIF',
    'image/bmp': 'BMP',
    'image/svg+xml': 'SVG',
    'image/avif': 'AVIF',
    'image/x-icon': 'ICO',
    'application/pdf': 'PDF'
  };

  T.EXT = {
    'image/png': 'png',
    'image/jpeg': 'jpg',
    'image/webp': 'webp',
    'image/gif': 'gif',
    'image/bmp': 'bmp',
    'image/svg+xml': 'svg',
    'image/avif': 'avif',
    'image/x-icon': 'ico',
    'application/pdf': 'pdf'
  };

  T.$ = function (sel, root) { return (root || document).querySelector(sel); };
  T.$$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /** Build an element: el('div', {class:'x', onclick:fn}, [children|text]) */
  T.el = function (tag, attrs, children) {
    var node = document.createElement(tag);

    Object.keys(attrs || {}).forEach(function (key) {
      var value = attrs[key];
      if (value === null || value === undefined || value === false) return;

      if (key === 'class') node.className = value;
      else if (key === 'text') node.textContent = value;
      else if (key.slice(0, 2) === 'on' && typeof value === 'function') node.addEventListener(key.slice(2), value);
      else node.setAttribute(key, value === true ? '' : value);
    });

    [].concat(children || []).forEach(function (child) {
      if (child === null || child === undefined || child === false) return;
      node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    });

    return node;
  };

  T.icon = function (name) { return T.el('i', { class: 'bi ' + name, 'aria-hidden': 'true' }); };

  T.formatBytes = function (bytes) {
    if (!isFinite(bytes) || bytes < 0) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(bytes < 10240 ? 1 : 0) + ' KB';
    return (bytes / 1048576).toFixed(bytes < 10485760 ? 2 : 1) + ' MB';
  };

  T.extOf = function (name) {
    var m = /\.([a-z0-9]+)$/i.exec(name || '');
    return m ? m[1].toLowerCase() : '';
  };

  T.baseName = function (name) {
    var base = String(name || 'image').replace(/\.[a-z0-9]+$/i, '');
    // Characters that are illegal in Windows file names would break the
    // download or the ZIP entry.
    return base.replace(/[\\/:*?"<>|\u0000-\u001f]+/g, '-').replace(/^\.+/, '').slice(0, 120) || 'image';
  };

  /** MIME type of a file, falling back to its extension: some systems
      hand over .ico, .bmp and .avif files with an empty type. */
  T.typeOf = function (file) {
    var type = (file.type || '').toLowerCase();
    if (type === 'image/jpg' || type === 'image/pjpeg') return 'image/jpeg';
    if (type === 'image/vnd.microsoft.icon') return 'image/x-icon';
    if (type === 'image/x-ms-bmp') return 'image/bmp';
    if (type) return type;
    return T.MIME[T.extOf(file.name)] || '';
  };

  T.debounce = function (fn, wait) {
    var timer = null;
    return function () {
      var args = arguments, self = this;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(self, args); }, wait);
    };
  };

  T.clamp = function (n, min, max) { return Math.min(max, Math.max(min, n)); };

  /* --- Toasts -------------------------------------------------------- */
  var toastRegion = null;

  T.toast = function (message, kind, ms) {
    kind = kind || 'info';

    if (!toastRegion) {
      // role=status announces without interrupting; errors are promoted
      // to role=alert on the toast itself.
      toastRegion = T.el('div', { class: 'tl-toasts', role: 'status', 'aria-live': 'polite' });
      document.body.appendChild(toastRegion);
    }

    var icons = { success: 'bi-check-circle-fill', error: 'bi-exclamation-triangle-fill', info: 'bi-info-circle-fill' };
    var toast = T.el('div', { class: 'tl-toast tl-toast--' + kind, role: kind === 'error' ? 'alert' : null }, [
      T.icon(icons[kind] || icons.info),
      T.el('span', { text: message })
    ]);

    toastRegion.appendChild(toast);

    // Keep the stack short: a batch of rejected files must not bury the page.
    while (toastRegion.children.length > 3) toastRegion.removeChild(toastRegion.firstChild);

    setTimeout(function () {
      toast.classList.add('is-leaving');
      setTimeout(function () { toast.remove(); }, 220);
    }, ms || (kind === 'error' ? 6500 : 3800));
  };

  /* --- Downloads ------------------------------------------------------ */
  T.download = function (blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = T.el('a', { href: url, download: filename, style: 'display:none' });

    document.body.appendChild(a);
    a.click();
    a.remove();

    // Revoked late: Safari cancels a download whose URL is revoked in
    // the same task as the click.
    setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
  };

  /** Give each file in a batch a distinct name ("logo.png", "logo-2.png"). */
  T.uniqueName = function (name, used) {
    var candidate = name;
    var ext = T.extOf(name);
    var stem = ext ? name.slice(0, -(ext.length + 1)) : name;
    var n = 2;

    while (used[candidate.toLowerCase()]) {
      candidate = stem + '-' + n + (ext ? '.' + ext : '');
      n++;
    }

    used[candidate.toLowerCase()] = true;
    return candidate;
  };

  /** ZIP a list of {name, blob}. Stored, not deflated: image formats are
      already compressed, so deflating them costs time and saves nothing. */
  T.zip = function (entries) {
    if (!window.IcoZip) return Promise.reject(new Error('The ZIP library did not load. Download the files one by one, or reload the page.'));

    var used = {};

    return Promise.all(entries.map(function (entry) {
      return entry.blob.arrayBuffer().then(function (buffer) {
        return { name: T.uniqueName(entry.name, used), data: new Uint8Array(buffer) };
      });
    })).then(function (files) {
      var tree = {};
      files.forEach(function (f) { tree[f.name] = [f.data, { level: 0 }]; });
      return new Blob([window.IcoZip.zipSync(tree)], { type: 'application/zip' });
    });
  };

  /* --- Canvas / decoding ----------------------------------------------- */
  T.canvasToBlob = function (canvas, type, quality) {
    return new Promise(function (resolve, reject) {
      if (!canvas.toBlob) return reject(new Error('This browser cannot export images.'));

      canvas.toBlob(function (blob) {
        if (!blob) return reject(new Error('The image is too large for this browser to export. Try a smaller size.'));

        // A browser that cannot encode the requested type silently hands
        // back a PNG. Treat that as a failure rather than mislabel a file.
        if (blob.type !== type) return reject(new Error(T.LABEL[type] + ' export is not supported in this browser.'));

        resolve(blob);
      }, type, quality);
    });
  };

  var encodeSupport = {};

  /** Can this browser write the given image type from a canvas? */
  T.canEncode = function (type) {
    if (type === 'image/png') return Promise.resolve(true);
    if (encodeSupport[type] !== undefined) return Promise.resolve(encodeSupport[type]);

    var canvas = document.createElement('canvas');
    canvas.width = canvas.height = 2;

    return T.canvasToBlob(canvas, type, 0.8)
      .then(function () { return true; }, function () { return false; })
      .then(function (ok) { encodeSupport[type] = ok; return ok; });
  };

  function svgSize(text) {
    // An SVG without width/height has no intrinsic pixel size; fall back
    // to its viewBox, then to a sensible default.
    try {
      var doc = new DOMParser().parseFromString(text, 'image/svg+xml');
      var svg = doc.documentElement;
      if (!svg || svg.nodeName.toLowerCase() !== 'svg') return null;

      var w = parseFloat(svg.getAttribute('width'));
      var h = parseFloat(svg.getAttribute('height'));
      var unitless = !/%/.test(svg.getAttribute('width') || '') && !/%/.test(svg.getAttribute('height') || '');
      if (w > 0 && h > 0 && unitless) return { width: w, height: h };

      var vb = (svg.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(parseFloat);
      if (vb.length === 4 && vb[2] > 0 && vb[3] > 0) return { width: vb[2], height: vb[3] };
    } catch (e) { /* fall through */ }

    return null;
  }

  function loadViaImg(url) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.decoding = 'async';
      img.onload = function () { resolve(img); };
      img.onerror = function () { reject(new Error('decode')); };
      img.src = url;
    });
  }

  /**
   * Decode a file into something drawable.
   * Resolves { source, width, height, vector, release() }.
   */
  T.loadImage = function (file) {
    var type = T.typeOf(file);
    var label = T.LABEL[type] || (T.extOf(file.name) || 'this').toUpperCase();
    var fail = function () {
      return new Error('Could not read "' + file.name + '". The file may be damaged, or this browser cannot open ' + label + ' images.');
    };

    var check = function (result) {
      if (!result.width || !result.height) { result.release(); throw fail(); }

      if (result.width > T.MAX_SIDE || result.height > T.MAX_SIDE || result.width * result.height > T.MAX_PIXELS) {
        var dims = result.width + ' × ' + result.height;
        result.release();
        throw new Error('"' + file.name + '" is ' + dims + ' px, which is too large to process in a browser.');
      }

      return result;
    };

    // SVG: must go through <img>, and needs an explicit size.
    if (type === 'image/svg+xml') {
      return file.text().then(function (text) {
        var size = svgSize(text) || { width: 1024, height: 1024 };
        var url = URL.createObjectURL(new Blob([text], { type: 'image/svg+xml' }));

        return loadViaImg(url).then(function (img) {
          return check({
            source: img,
            width: Math.round(size.width),
            height: Math.round(size.height),
            vector: true,
            release: function () { URL.revokeObjectURL(url); }
          });
        }, function () { URL.revokeObjectURL(url); throw fail(); });
      });
    }

    var viaImg = function () {
      var url = URL.createObjectURL(file);

      return loadViaImg(url).then(function (img) {
        return check({
          source: img,
          width: img.naturalWidth,
          height: img.naturalHeight,
          vector: false,
          release: function () { URL.revokeObjectURL(url); }
        });
      }, function () { URL.revokeObjectURL(url); throw fail(); });
    };

    if (!window.createImageBitmap) return viaImg();

    // createImageBitmap applies EXIF rotation, so phone photos come out
    // the right way up. Fall back to <img> for anything it rejects
    // (some browsers decode ICO only through <img>).
    return window.createImageBitmap(file).then(function (bitmap) {
      return check({
        source: bitmap,
        width: bitmap.width,
        height: bitmap.height,
        vector: false,
        release: function () { if (bitmap.close) bitmap.close(); }
      });
    }, viaImg);
  };

  /** Draw a decoded image onto a new canvas, optionally over a solid colour. */
  T.toCanvas = function (image, width, height, background) {
    var canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(width));
    canvas.height = Math.max(1, Math.round(height));

    var ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('The image is too large for this browser to process.');

    if (background) {
      ctx.fillStyle = background;
      ctx.fillRect(0, 0, canvas.width, canvas.height);
    }

    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image.source, 0, 0, canvas.width, canvas.height);

    return canvas;
  };

  /* --- File intake ------------------------------------------------------ */

  /**
   * Split a FileList into accepted files and human-readable rejections.
   * opts: { types: [mime], label: 'PNG, JPG…', room: number of slots left }
   */
  T.screenFiles = function (fileList, opts) {
    var accepted = [];
    var errors = [];
    var files = Array.prototype.slice.call(fileList || []);
    var room = opts.room === undefined ? T.MAX_FILES : opts.room;

    files.forEach(function (file) {
      var type = T.typeOf(file);

      if (opts.types.indexOf(type) === -1) {
        errors.push('"' + file.name + '" is not a supported file. Use ' + opts.label + '.');
      } else if (file.size === 0) {
        errors.push('"' + file.name + '" is empty.');
      } else if (file.size > T.MAX_FILE_BYTES) {
        errors.push('"' + file.name + '" is ' + T.formatBytes(file.size) + '. The limit is ' + T.formatBytes(T.MAX_FILE_BYTES) + ' per file.');
      } else if (accepted.length >= room) {
        errors.push('__limit__');
      } else {
        accepted.push(file);
      }
    });

    // One message for the overflow, however many files it was.
    var overflow = errors.filter(function (e) { return e === '__limit__'; }).length;
    errors = errors.filter(function (e) { return e !== '__limit__'; });
    if (overflow) errors.push(overflow + ' file' + (overflow === 1 ? ' was' : 's were') + ' skipped: the limit is ' + T.MAX_FILES + ' files at a time.');

    return { accepted: accepted, errors: errors };
  };

  /**
   * Wire up a dropzone: click, keyboard, drag-and-drop and paste.
   * root is a <button class="tl-drop"> with a hidden <input type=file>
   * as its next sibling (inputs cannot be nested inside buttons).
   */
  T.dropzone = function (root, input, onFiles) {
    root.addEventListener('click', function () { input.click(); });

    input.addEventListener('change', function () {
      if (input.files && input.files.length) onFiles(input.files);
      // Reset so choosing the same file twice in a row still fires.
      input.value = '';
    });

    var depth = 0;

    var hasFiles = function (e) {
      return e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1;
    };

    // Listen on the whole window: people aim for "the page", not the box.
    window.addEventListener('dragenter', function (e) {
      if (!hasFiles(e)) return;
      e.preventDefault();
      depth++;
      root.classList.add('is-over');
    });

    window.addEventListener('dragover', function (e) {
      if (!hasFiles(e)) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
    });

    window.addEventListener('dragleave', function (e) {
      if (!hasFiles(e)) return;
      depth = Math.max(0, depth - 1);
      if (depth === 0) root.classList.remove('is-over');
    });

    window.addEventListener('drop', function (e) {
      if (!hasFiles(e)) return;
      e.preventDefault();
      depth = 0;
      root.classList.remove('is-over');
      if (e.dataTransfer.files.length) onFiles(e.dataTransfer.files);
    });

    window.addEventListener('paste', function (e) {
      // Leave ordinary pasting into text fields alone.
      var tag = (e.target && e.target.tagName) || '';
      if (tag === 'INPUT' || tag === 'TEXTAREA') return;

      var files = e.clipboardData && e.clipboardData.files;
      if (files && files.length) {
        e.preventDefault();
        onFiles(files);
      }
    });
  };

  /** Keep a range input's filled track in step with its value (WebKit
      has no ::-moz-range-progress equivalent). */
  T.bindRange = function (input) {
    var paint = function () {
      var min = parseFloat(input.min || 0);
      var max = parseFloat(input.max || 100);
      var pct = max > min ? ((parseFloat(input.value) - min) / (max - min)) * 100 : 0;
      input.style.setProperty('--tl-fill', pct + '%');
    };

    input.addEventListener('input', paint);
    paint();
    return paint;
  };

  T.isHex = function (value) { return /^#[0-9a-f]{6}$/i.test(value || ''); };

  /**
   * Link a <input type=color> with a hex text box so either can be used.
   * Calls onChange(hex) only for a complete, valid colour.
   */
  T.bindColor = function (picker, text, onChange) {
    var set = function (hex, from) {
      hex = hex.toLowerCase();
      if (from !== picker) picker.value = hex;
      if (from !== text) text.value = hex.toUpperCase();
      text.classList.remove('is-invalid');
      onChange(hex);
    };

    picker.addEventListener('input', function () { set(picker.value, picker); });

    text.addEventListener('input', function () {
      var value = text.value.trim();
      if (value && value[0] !== '#') value = '#' + value;

      // Accept the #abc shorthand too.
      if (/^#[0-9a-f]{3}$/i.test(value)) value = '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3];

      if (T.isHex(value)) set(value, text);
      else text.classList.add('is-invalid');
    });

    text.addEventListener('blur', function () {
      text.value = picker.value.toUpperCase();
      text.classList.remove('is-invalid');
    });

    return function (hex) { set(hex, null); };
  };

  window.IcoTools = T;
})();
