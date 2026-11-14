/* Iconors Contributor Studio — sidebar collapse, access-request modal,
   and the drag & drop bulk uploader. No dependencies. */
(function () {
  'use strict';

  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  };

  /* ----------------------------------------------------- theme (light default) */
  (function () {
    var btn = document.querySelector('[data-cs-theme-toggle]');
    if (!btn) { return; }
    var root = document.documentElement;
    var want = store.get('cs-theme') || 'light';
    // The site's main bundle applies the public site's theme after load;
    // keep the studio on its own choice.
    function enforce() { if (root.getAttribute('data-bs-theme') !== want) { root.setAttribute('data-bs-theme', want); } }
    enforce();
    if (window.MutationObserver) {
      new MutationObserver(enforce).observe(root, { attributes: true, attributeFilter: ['data-bs-theme'] });
    }
    window.addEventListener('load', enforce);
    var icon = btn.querySelector('[data-cs-theme-icon]');
    function paint() {
      var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      if (icon) { icon.className = 'bi ' + (dark ? 'bi-sun' : 'bi-moon-stars'); }
      btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    }
    btn.addEventListener('click', function () {
      want = want === 'dark' ? 'light' : 'dark';
      store.set('cs-theme', want);
      enforce();
      paint();
    });
    paint();
  })();

  /* ------------------------------------------------- sidebar collapse */
  var shell = document.querySelector('[data-cs-shell]');
  if (shell) {
    var btn = document.querySelector('[data-cs-collapse]');
    var apply = function (collapsed) {
      shell.classList.toggle('is-collapsed', collapsed);
      if (btn) {
        btn.innerHTML = collapsed ? '&raquo;' : '&laquo;';
        btn.setAttribute('aria-label', collapsed ? 'Show menu labels' : 'Hide menu labels');
        btn.setAttribute('aria-expanded', String(!collapsed));
      }
    };
    apply(store.get('cs-collapsed') === '1');
    if (btn) {
      btn.addEventListener('click', function () {
        var next = !shell.classList.contains('is-collapsed');
        store.set('cs-collapsed', next ? '1' : '0');
        apply(next);
      });
    }
    var burger = document.querySelector('[data-cs-burger]');
    if (burger) {
      burger.addEventListener('click', function () { shell.classList.toggle('is-open'); });
      document.addEventListener('click', function (e) {
        if (shell.classList.contains('is-open') && !e.target.closest('.cs-side') && !e.target.closest('[data-cs-burger]')) {
          shell.classList.remove('is-open');
        }
      });
    }
  }

  /* --------------------------------------------- request-access modal */
  var modal = document.getElementById('csRequestModal');
  if (modal) {
    var form = modal.querySelector('form');
    var typeField = modal.querySelector('[data-cs-type-label]');
    var urlField = modal.querySelector('input[name="portfolio_url"]');
    var open = function (trigger) {
      form.action = trigger.getAttribute('data-action');
      typeField.value = trigger.getAttribute('data-label');
      modal.classList.add('is-open');
      setTimeout(function () { urlField.focus(); }, 30);
    };
    var close = function () { modal.classList.remove('is-open'); };
    document.addEventListener('click', function (e) {
      var t = e.target.closest('[data-cs-request]');
      if (t) { e.preventDefault(); open(t); return; }
      if (e.target === modal || e.target.closest('[data-cs-close]')) { close(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); }
      var t = e.target.closest && e.target.closest('[data-cs-request]');
      if (t && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); open(t); }
    });
  }

  /* --------------------------------------------------------- uploader */
  var up = document.querySelector('[data-cs-uploader]');
  if (!up) { return; }

  var endpoint = up.getAttribute('data-endpoint');
  var required = up.getAttribute('data-required').split(',');
  var allowed = up.getAttribute('data-allowed').split(',');
  var maxMb = parseFloat(up.getAttribute('data-max-mb'));
  var reviewUrl = up.getAttribute('data-review-url');
  // Uploads land in a draft session; the editor opens when the batch finishes.
  var draftNew = up.getAttribute('data-draft-new');
  var draftId = up.getAttribute('data-draft-id') || null;
  var editorUrl = up.getAttribute('data-after-url') || null;
  function ensureDraft() {
    if (draftId || !draftNew) { return Promise.resolve(draftId); }
    return fetch(draftNew, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.json(); })
      .then(function (j) { draftId = String(j.id); editorUrl = j.url; return draftId; });
  }
  var input = up.querySelector('input[type=file]');
  var drop = up.querySelector('[data-cs-drop]');
  var idle = up.querySelector('[data-cs-idle]');
  var busy = up.querySelector('[data-cs-busy]');
  var busyText = up.querySelector('[data-cs-busy-text]');
  var bar = up.querySelector('[data-cs-bar]');
  var results = document.querySelector('[data-cs-results]');
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
  var running = false;

  function ext(name) { var i = name.lastIndexOf('.'); return i < 0 ? '' : name.slice(i + 1).toLowerCase(); }
  function stem(name) { var i = name.lastIndexOf('.'); return (i < 0 ? name : name.slice(0, i)).trim().toLowerCase(); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function line(ok, text) {
    var div = document.createElement('div');
    div.className = ok ? 'ok' : 'err';
    div.innerHTML = '<i class="bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + '"></i><span>' + esc(text) + '</span>';
    results.prepend(div);
    results.hidden = false;
  }

  function handle(fileList) {
    if (running) { return; }
    var groups = {};
    var order = [];
    Array.prototype.forEach.call(fileList, function (f) {
      var e = ext(f.name);
      if (allowed.indexOf(e) < 0) { line(false, f.name + ' — this file type is not accepted here.'); return; }
      if (f.size > maxMb * 1048576) { line(false, f.name + ' — larger than ' + maxMb + ' MB.'); return; }
      var s = stem(f.name);
      if (!groups[s]) { groups[s] = []; order.push(s); }
      groups[s].push(f);
    });

    var jobs = [];
    order.forEach(function (s) {
      var files = groups[s];
      var hasReq = files.some(function (f) { return required.indexOf(ext(f.name)) >= 0; });
      if (!hasReq) { line(false, files[0].name + ' — needs a matching .' + required[0] + ' file with the same name.'); return; }
      jobs.push(files);
    });
    if (!jobs.length) { return; }

    var totalFiles = jobs.reduce(function (n, j) { return n + j.length; }, 0);
    var doneFiles = 0;
    var okCount = 0;
    running = true;
    idle.hidden = true;
    busy.hidden = false;

    function paint() {
      var pct = Math.round(doneFiles / totalFiles * 100);
      busyText.textContent = Math.min(doneFiles + 1, totalFiles) + '/' + totalFiles + ' file uploading';
      bar.style.width = pct + '%';
      bar.textContent = pct + '%';
    }
    paint();

    var queue = jobs.slice();
    var active = 0;
    ensureDraft().then(function () { next(); }, function () {
      line(false, 'Could not start a draft session — please reload the page and try again.');
      running = false; busy.hidden = true; idle.hidden = false;
    });
    return;

    function next() {
      if (!queue.length && active === 0) {
        running = false;
        busyText.textContent = 'Upload finished';
        bar.style.width = '100%';
        bar.textContent = '100%';
        setTimeout(function () { busy.hidden = true; idle.hidden = false; }, 1500);
        if (okCount) {
          var div = document.createElement('div');
          div.className = 'ok';
          if (editorUrl) {
            div.innerHTML = '<i class="bi bi-info-circle-fill"></i><span>' + okCount + ' saved to your draft. Opening the draft editor… <a href="' + esc(editorUrl) + '" style="color:var(--ico-primary)">Open now →</a></span>';
            setTimeout(function () { window.location.href = editorUrl; }, 1600);
          } else {
            div.innerHTML = '<i class="bi bi-info-circle-fill"></i><span>' + okCount + ' submitted for review. <a href="' + reviewUrl + '" style="color:var(--ico-primary)">View Under Review →</a></span>';
          }
          results.prepend(div);
        }
        return;
      }
      while (active < 3 && queue.length) {
        send(queue.shift());
      }
    }

    function send(files) {
      active++;
      var fd = new FormData();
      files.forEach(function (f) { fd.append('files[]', f, f.name); });
      if (draftId) { fd.append('draft_id', draftId); }
      var xhr = new XMLHttpRequest();
      xhr.open('POST', endpoint);
      xhr.setRequestHeader('X-CSRF-TOKEN', token);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.onload = function () {
        var data = {};
        try { data = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status === 200 && data.ok) {
          okCount++;
          line(true, data.title + ' — ' + (draftId ? 'uploaded' : 'submitted') + ' (' + files.map(function (f) { return ext(f.name).toUpperCase(); }).join(' + ') + ')');
        } else {
          var msg = data.message || (data.errors ? Object.values(data.errors)[0][0] : 'Upload failed (' + xhr.status + ').');
          line(false, files[0].name + ' — ' + msg);
        }
        finish();
      };
      xhr.onerror = function () { line(false, files[0].name + ' — network error, please retry.'); finish(); };
      xhr.send(fd);
      function finish() { active--; doneFiles += files.length; paint(); next(); }
    }
  }

  drop.addEventListener('click', function () { if (!running) { input.click(); } });
  drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  input.addEventListener('change', function () { handle(input.files); input.value = ''; });
  ['dragenter', 'dragover'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
  });
  ['dragleave', 'drop'].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
  });
  drop.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) { handle(e.dataTransfer.files); } });
})();
