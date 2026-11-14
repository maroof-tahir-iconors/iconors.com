/* Iconors Contributor Studio — "My Drafts" editor.
   Pack details, bundle picker, category, per-item names / descriptions /
   tags, suggested tags, extra formats, mockups, Save / Submit / Delete. */
(function () {
  'use strict';
  var stateEl = document.getElementById('csDraftState');
  if (!stateEl) { return; }
  var S = JSON.parse(stateEl.textContent);
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };
  var dirty = false;
  var clipboard = [];

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function api(url, method, body, isForm) {
    var opts = { method: method, headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' } };
    if (body) {
      if (isForm) { opts.body = body; } else { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    }
    return fetch(url, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok && !j.message && j.errors) { j.message = Object.values(j.errors)[0][0] || Object.values(j.errors)[0]; }
        j._status = r.status; j.ok = r.ok && j.ok !== false; return j;
      });
    });
  }
  var errBox = $('[data-d-error]'), okBox = $('[data-d-ok]');
  function flash(ok, msg) {
    (ok ? errBox : okBox).hidden = true;
    var box = ok ? okBox : errBox;
    box.textContent = msg; box.hidden = false;
    if (!ok) { window.scrollTo({ top: 0, behavior: 'smooth' }); }
    if (ok) { setTimeout(function () { box.hidden = true; }, 3500); }
  }
  function markDirty() { dirty = true; }
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ------------------------------------------------------- price + licence */
  var isPremium = !!S.draft.is_premium;
  $$('[data-d-price] button').forEach(function (b) {
    b.addEventListener('click', function () {
      isPremium = b.getAttribute('data-v') === '1';
      $$('[data-d-price] button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
      $('[data-d-free]').hidden = isPremium;
      $('[data-d-premium]').hidden = !isPremium;
      markDirty();
    });
  });
  $$('[data-f]').forEach(function (el) { el.addEventListener('input', markDirty); el.addEventListener('change', markDirty); });

  /* --------------------------------------------------------------- category */
  var catId = S.draft.category_id;
  var catMenu = $('[data-d-cat-menu]');
  $('[data-d-cat-open]').addEventListener('click', function (e) { e.stopPropagation(); catMenu.hidden = !catMenu.hidden; if (!catMenu.hidden) { $('[data-d-cat-search]').focus(); } });
  $('[data-d-cat-search]').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    $$('.cs-cat-list label', catMenu).forEach(function (l) { l.hidden = l.textContent.toLowerCase().indexOf(q) < 0; });
  });
  $$('input[name="d_category"]', catMenu).forEach(function (r) {
    r.addEventListener('change', function () {
      catId = parseInt(r.value, 10);
      $('[data-d-cat-label]').textContent = r.parentNode.textContent.trim();
      catMenu.hidden = true; markDirty(); clearErr('category_id');
    });
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('[data-d-cat-menu]') && !e.target.closest('[data-d-cat-open]')) { catMenu.hidden = true; } });

  /* ---------------------------------------------------------------- bundles */
  var bundleId = S.draft.bundle_id, pickedBundle = null;
  var bModal = $('[data-d-bundle-modal]'), bGrid = $('[data-d-bundle-grid]'), bContinue = $('[data-d-bundle-continue]');
  var bundles = [];
  function renderBundles() {
    var q = ($('[data-d-bundle-search]').value || '').toLowerCase();
    var list = bundles.filter(function (b) { return b.name.toLowerCase().indexOf(q) >= 0; });
    if (!list.length) { bGrid.innerHTML = '<div class="text-center p-4" style="color:#64748b;grid-column:1/-1">No bundles yet — create one with “New Bundle”.</div>'; return; }
    bGrid.innerHTML = list.map(function (b) {
      var img = b.preview ? (b.preview.lottie ? '<div class="ico-lottie" data-ico-lottie="' + esc(b.preview.url) + '"></div>' : '<img src="' + esc(b.preview.url) + '" alt="">') : '<span style="font-size:.7rem;letter-spacing:.06em;color:#64748b">NO PACK ADDED</span>';
      return '<button type="button" class="cs-bundle' + ((pickedBundle || {}).id === b.id || (!pickedBundle && bundleId === b.id) ? ' is-picked' : '') + '" data-bid="' + b.id + '">' +
        '<span class="cs-bundle__img">' + img + '</span><span class="cs-bundle__name">' + esc(b.name) + '</span><small>' + b.packs + ' packs</small></button>';
    }).join('');
    $$('[data-bid]', bGrid).forEach(function (el) {
      el.addEventListener('click', function () {
        pickedBundle = bundles.find(function (b) { return b.id === parseInt(el.getAttribute('data-bid'), 10); });
        $$('[data-bid]', bGrid).forEach(function (x) { x.classList.toggle('is-picked', x === el); });
        bContinue.disabled = false;
      });
    });
  }
  function loadBundles() {
    return api(S.urls.bundles, 'GET').then(function (j) { bundles = j.bundles || []; renderBundles(); });
  }
  $('[data-d-bundle-open]').addEventListener('click', function () { bModal.classList.add('is-open'); pickedBundle = null; bContinue.disabled = true; loadBundles(); });
  $('[data-d-bundle-close]').addEventListener('click', function () { bModal.classList.remove('is-open'); });
  bModal.addEventListener('click', function (e) { if (e.target === bModal) { bModal.classList.remove('is-open'); } });
  $('[data-d-bundle-search]').addEventListener('input', renderBundles);
  $('[data-d-bundle-new]').addEventListener('click', function () {
    $('[data-d-bundle-new-name]').hidden = false; $('[data-d-bundle-create]').hidden = false; $('[data-d-bundle-new-name]').focus();
  });
  function createBundle() {
    var name = $('[data-d-bundle-new-name]').value.trim();
    if (name.length < 3) { $('[data-d-bundle-new-name]').focus(); return; }
    api(S.urls.bundleStore, 'POST', { name: name }).then(function (j) {
      if (!j.ok) { alert(j.message || 'Could not create bundle.'); return; }
      $('[data-d-bundle-new-name]').value = '';
      return loadBundles().then(function () {
        pickedBundle = bundles.find(function (b) { return b.id === j.id; }); renderBundles(); bContinue.disabled = false;
      });
    });
  }
  $('[data-d-bundle-create]').addEventListener('click', createBundle);
  $('[data-d-bundle-new-name]').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); createBundle(); } });
  bContinue.addEventListener('click', function () {
    if (!pickedBundle) { return; }
    bundleId = pickedBundle.id;
    $('[data-d-bundle-label]').textContent = pickedBundle.name;
    bModal.classList.remove('is-open'); markDirty(); clearErr('bundle_id');
  });

  /* --------------------------------------------------------------- tag boxes */
  function TagBox(listEl, countEl, initial) {
    this.tags = (initial || []).slice(0, S.maxTags);
    this.list = listEl; this.count = countEl; this.render();
  }
  TagBox.prototype.render = function () {
    var self = this;
    this.list.innerHTML = this.tags.map(function (t, i) { return '<span class="cs-chip-tag">' + esc(t) + ' <button type="button" data-i="' + i + '" aria-label="Remove ' + esc(t) + '">&times;</button></span>'; }).join('');
    $$('button', this.list).forEach(function (b) { b.addEventListener('click', function () { self.tags.splice(parseInt(b.getAttribute('data-i'), 10), 1); self.render(); markDirty(); }); });
    if (this.count) {
      var low = this.tags.length < S.minTags;
      this.count.innerHTML = this.tags.length + '/' + S.maxTags + ' Tags' + (low ? ' <em>(' + S.minTags + ' required)</em>' : '');
      this.count.classList.toggle('is-low', low);
    }
    if (this.onChange) { this.onChange(); }
  };
  TagBox.prototype.add = function (raw) {
    var self = this, changed = false;
    String(raw).split(',').forEach(function (t) {
      t = t.trim().toLowerCase().replace(/\s+/g, ' ').slice(0, 40);
      if (t && self.tags.indexOf(t) < 0 && self.tags.length < S.maxTags) { self.tags.push(t); changed = true; }
    });
    if (changed) { this.render(); markDirty(); }
  };
  function bindInput(input, box) {
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); box.add(input.value); input.value = ''; }
      if (e.key === 'Backspace' && !input.value && box.tags.length) { box.tags.pop(); box.render(); markDirty(); }
    });
    input.addEventListener('blur', function () { if (input.value.trim()) { box.add(input.value); input.value = ''; } });
    input.addEventListener('paste', function () { setTimeout(function () { if (input.value.indexOf(',') >= 0) { box.add(input.value); input.value = ''; } }, 0); });
  }

  /* ---------------------------------------------------------------- items */
  var items = {};
  var byId = {};
  S.items.forEach(function (it) { byId[it.id] = it; });

  function suggest(card) {
    var id = card.getAttribute('data-item');
    var box = items[id].box;
    var sug = $('[data-tag-sug]', card);
    var title = $('[data-item-title]', card).value;
    var qs = 'title=' + encodeURIComponent(title) + box.tags.map(function (t) { return '&have[]=' + encodeURIComponent(t); }).join('');
    fetch(S.urls.suggest + '?' + qs, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      var tags = (j.tags || []).filter(function (t) { return box.tags.indexOf(t) < 0; });
      items[id].suggested = tags;
      sug.innerHTML = tags.length ? tags.map(function (t) { return '<button type="button" class="cs-chip-sug" data-t="' + esc(t) + '">' + esc(t) + ' +</button>'; }).join('') : '<span class="cs-sub small">No suggestions — type your own tags.</span>';
      $$('[data-t]', sug).forEach(function (b) { b.addEventListener('click', function () { box.add(b.getAttribute('data-t')); b.remove(); }); });
    }).catch(function () { sug.innerHTML = ''; });
  }

  $$('[data-item]').forEach(function (card) {
    var id = card.getAttribute('data-item');
    var data = byId[id] || { tags: [] };
    var box = new TagBox($('[data-tag-list]', card), $('[data-tag-count]', card), data.tags);
    items[id] = { card: card, box: box, suggested: [] };
    box.onChange = function () { if (box.tags.length >= S.minTags) { clearErr('item_' + id); } };
    bindInput($('[data-tag-input]', card), box);

    $('[data-tag-clear]', card).addEventListener('click', function () { box.tags = []; box.render(); markDirty(); });
    $('[data-tag-copy]', card).addEventListener('click', function () {
      clipboard = box.tags.slice();
      try { navigator.clipboard.writeText(clipboard.join(', ')); } catch (e) {}
      this.textContent = 'Copied'; var b = this; setTimeout(function () { b.textContent = 'Copy'; }, 1200);
    });
    $('[data-tag-paste]', card).addEventListener('click', function () { if (clipboard.length) { box.add(clipboard.join(',')); } });
    $('[data-tag-addall]', card).addEventListener('click', function () { box.add(items[id].suggested.join(',')); $('[data-tag-sug]', card).innerHTML = ''; });

    var t;
    $('[data-item-title]', card).addEventListener('input', function () { markDirty(); clearTimeout(t); t = setTimeout(function () { suggest(card); }, 600); });
    $('[data-item-desc]', card).addEventListener('input', markDirty);

    $$('[data-bg]', card).forEach(function (b) {
      b.addEventListener('click', function () {
        var media = $('[data-item-media]', card);
        media.classList.remove('is-white', 'is-dark', 'is-checker');
        media.classList.add('is-' + b.getAttribute('data-bg'));
        $$('[data-bg]', card).forEach(function (x) { x.classList.toggle('is-active', x === b); });
      });
    });

    $('[data-item-preview]', card).addEventListener('change', function () {
      if (this.checked && $$('[data-item-preview]:checked').length > 4) { this.checked = false; flash(false, 'Choose up to 4 items for the pack preview.'); return; }
      markDirty();
    });

    $('[data-item-del]', card).addEventListener('click', function () {
      if (!confirm('Remove “' + $('[data-item-title]', card).value + '” from this draft?')) { return; }
      api(S.urls.item.replace('__ID__', id), 'DELETE').then(function (j) {
        if (!j.ok) { flash(false, j.message || 'Could not remove the item.'); return; }
        card.remove(); delete items[id];
        var n = Object.keys(items).length;
        $('[data-d-count]').textContent = n + ' ' + ($('[data-d-count]').textContent.split(' ').slice(1).join(' '));
      });
    });

    var fmtInput = $('[data-fmt-input]', card), fmtExt = null;
    $$('[data-fmt-add]', card).forEach(function (b) {
      b.addEventListener('click', function () { fmtExt = b.getAttribute('data-fmt-add'); fmtInput.accept = '.' + fmtExt; fmtInput.click(); });
    });
    fmtInput.addEventListener('change', function () {
      var f = fmtInput.files[0]; fmtInput.value = '';
      if (!f) { return; }
      var btn = $('[data-fmt-add="' + fmtExt + '"]', card);
      btn.textContent = 'Uploading…';
      var fd = new FormData(); fd.append('file', f, f.name);
      api(S.urls.format.replace('__ID__', id), 'POST', fd, true).then(function (j) {
        if (!j.ok) { btn.textContent = '+ ' + fmtExt.toUpperCase(); flash(false, j.message || 'Upload failed.'); return; }
        var on = document.createElement('span'); on.className = 'cs-fmt is-on'; on.innerHTML = '<i class="bi bi-check2"></i> ' + fmtExt.toUpperCase();
        btn.replaceWith(on);
      });
    });

    suggest(card);
  });

  /* --------------------------------------------------------- advanced options */
  var adv = $('[data-d-adv]');
  $$('[data-d-adv-toggle]').forEach(function (b) { b.addEventListener('click', function () { adv.hidden = !adv.hidden; }); });
  var advBox = (function () {
    var wrap = $('[data-adv-tags]');
    wrap.innerHTML = '<div class="cs-item__taghead"><span data-adv-count></span><span class="cs-links"><button type="button" data-adv-clear>Clear</button> • <button type="button" data-adv-copy>Copy</button></span></div><div class="cs-chips" data-adv-list></div><input class="cs-tag-input" data-adv-input placeholder="Add tag and press Enter" aria-label="Add tag for all items">';
    var b = new TagBox($('[data-adv-list]', wrap), $('[data-adv-count]', wrap), []);
    bindInput($('[data-adv-input]', wrap), b);
    $('[data-adv-clear]', wrap).addEventListener('click', function () { b.tags = []; b.render(); });
    $('[data-adv-copy]', wrap).addEventListener('click', function () { clipboard = b.tags.slice(); });
    return b;
  })();
  $('[data-adv-apply]').addEventListener('click', function () {
    var name = $('[data-adv-name]').value.trim(), desc = $('[data-adv-desc]').value.trim();
    Object.keys(items).forEach(function (id) {
      var c = items[id].card;
      if (name) { $('[data-item-title]', c).value = name; }
      if (desc) { $('[data-item-desc]', c).value = desc; }
      if (advBox.tags.length) { items[id].box.add(advBox.tags.join(',')); }
    });
    markDirty();
    flash(true, 'Applied to all ' + Object.keys(items).length + ' items.');
  });

  /* ---------------------------------------------------------------- mockups */
  var mockDrop = $('[data-d-mock-drop]'), mockInput = $('[data-d-mock-input]'), mockList = $('[data-d-mocks]');
  function bindMock(el) {
    $('[data-mock-del]', el).addEventListener('click', function () {
      api(S.urls.mockupDelete.replace('__ID__', el.getAttribute('data-mock-id')), 'DELETE').then(function (j) { if (j.ok) { el.remove(); } });
    });
  }
  $$('[data-mock-id]', mockList).forEach(bindMock);
  function uploadMocks(files) {
    Array.prototype.forEach.call(files, function (f) {
      if (f.size > 2 * 1048576) { flash(false, f.name + ' is larger than 2 MB.'); return; }
      if ($$('[data-mock-id]', mockList).length >= 8) { flash(false, 'You can upload up to 8 mockups.'); return; }
      var fd = new FormData(); fd.append('file', f, f.name);
      api(S.urls.mockup, 'POST', fd, true).then(function (j) {
        if (!j.ok) { flash(false, j.message || 'Mockup upload failed.'); return; }
        var el = document.createElement('div'); el.className = 'cs-mock'; el.setAttribute('data-mock-id', j.id);
        el.innerHTML = (j.video ? '<video src="' + esc(j.url) + '" muted loop autoplay playsinline></video>' : '<img src="' + esc(j.url) + '" alt="">') + '<button type="button" data-mock-del aria-label="Remove mockup">&times;</button>';
        mockList.appendChild(el); bindMock(el);
      });
    });
  }
  mockDrop.addEventListener('click', function () { mockInput.click(); });
  mockDrop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); mockInput.click(); } });
  mockInput.addEventListener('change', function () { uploadMocks(mockInput.files); mockInput.value = ''; });
  ['dragenter', 'dragover'].forEach(function (ev) { mockDrop.addEventListener(ev, function (e) { e.preventDefault(); mockDrop.classList.add('is-over'); }); });
  ['dragleave', 'drop'].forEach(function (ev) { mockDrop.addEventListener(ev, function (e) { e.preventDefault(); mockDrop.classList.remove('is-over'); }); });
  mockDrop.addEventListener('drop', function (e) { if (e.dataTransfer) { uploadMocks(e.dataTransfer.files); } });

  /* ------------------------------------------------------- save / submit */
  function payload() {
    return {
      pack_title: $('[data-f="pack_title"]').value,
      description: $('[data-f="description"]').value,
      is_premium: isPremium,
      price: parseFloat(($('[data-f="price"]') || {}).value || '0') || 0,
      license_id: isPremium ? null : (parseInt($('[data-f="license_id"]').value, 10) || null),
      bundle_id: bundleId || null,
      category_id: catId || null,
      preview_ids: $$('[data-item-preview]:checked').map(function (c) { return parseInt(c.closest('[data-item]').getAttribute('data-item'), 10); }),
      items: Object.keys(items).map(function (id) {
        var c = items[id].card;
        return { id: parseInt(id, 10), title: $('[data-item-title]', c).value, description: $('[data-item-desc]', c).value, tags: items[id].box.tags };
      })
    };
  }
  function clearErr(key) { var el = $('[data-err-key="' + key + '"]'); if (el) { el.classList.remove('has-error'); } }
  function busy(btn, on, label) { btn.disabled = on; if (label) { btn.textContent = label; } }

  var saveBtn = $('[data-d-save]'), submitBtn = $('[data-d-submit]');
  saveBtn.addEventListener('click', function () {
    busy(saveBtn, true, 'Saving…');
    api(S.urls.save, 'POST', payload()).then(function (j) {
      busy(saveBtn, false, 'Save Draft');
      if (j.ok) { dirty = false; flash(true, j.message || 'Draft saved.'); } else { flash(false, j.message || 'Could not save.'); }
    });
  });
  $('[data-d-more]').addEventListener('click', function (e) {
    if (!dirty) { return; }
    e.preventDefault(); var href = this.href;
    api(S.urls.save, 'POST', payload()).then(function () { dirty = false; location.href = href; });
  });
  submitBtn.addEventListener('click', function () {
    $$('.has-error').forEach(function (el) { el.classList.remove('has-error'); });
    busy(submitBtn, true, 'Submitting…');
    api(S.urls.submit, 'POST', payload()).then(function (j) {
      busy(submitBtn, false, 'Submit');
      if (j.ok) { dirty = false; flash(true, j.message); setTimeout(function () { location.href = j.redirect; }, 900); return; }
      var errs = j.errors || {};
      Object.keys(errs).forEach(function (k) {
        var key = k === 'pack_title' ? null : k;
        var el = key ? $('[data-err-key="' + key + '"]') : $('[data-f="pack_title"]');
        if (k === 'license_id') { el = $('[data-f="license_id"]'); }
        if (k === 'price') { el = $('[data-f="price"]'); }
        if (el) { el.classList.add('has-error'); }
      });
      var n = Object.keys(errs).length;
      flash(false, (j.message || 'Please fix the highlighted fields.') + (n > 1 ? ' (' + n + ' things to fix — highlighted in red)' : ''));
    });
  });
  $('[data-f="pack_title"]').addEventListener('input', function () { this.classList.remove('has-error'); });

  var delModal = $('[data-d-del-modal]');
  $('[data-d-delete]').addEventListener('click', function () { delModal.classList.add('is-open'); });
  $('[data-d-del-no]').addEventListener('click', function () { delModal.classList.remove('is-open'); });
  delModal.addEventListener('click', function (e) { if (e.target === delModal) { delModal.classList.remove('is-open'); } });
  $('[data-d-del-yes]').addEventListener('click', function () {
    api(S.urls.destroy, 'DELETE').then(function (j) { if (j.ok) { dirty = false; location.href = j.redirect; } else { flash(false, j.message || 'Could not delete.'); } });
  });
})();
