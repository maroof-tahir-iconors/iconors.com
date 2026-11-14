/*
 * Iconors — "Continue with Google".
 *
 * Loads Google Identity Services, renders Google's own button (so it
 * always meets Google's branding rules), and posts the ID token Google
 * returns to the server, which verifies it and signs the visitor in.
 *
 * Covered here: loading and signing-in states, errors (Google blocked or
 * unreachable, verification failed, expired page, rate limit), a button
 * that follows the site's light/dark theme and the card's width, and
 * cancellation — closing Google's window simply leaves the page as it was.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-ico-google]');
  if (!root) return;

  var GSI_SRC = 'https://accounts.google.com/gsi/client';
  var LOAD_TIMEOUT = 12000;

  var slot = root.querySelector('[data-ico-google-slot]');
  var placeholder = root.querySelector('[data-ico-google-placeholder]');
  var busy = root.querySelector('[data-ico-google-busy]');
  var busyText = root.querySelector('[data-ico-google-busy-text]');
  var errorBox = root.querySelector('[data-ico-google-error]');

  var clientId = root.dataset.clientId;
  var nonce = root.dataset.nonce;
  var context = root.dataset.context === 'signup' ? 'signup' : 'signin';
  var endpoint = root.dataset.endpoint;

  var ready = false;
  var submitting = false;
  var renderedWith = '';
  var host = null;

  /* --- UI states ---------------------------------------------------- */
  function showError(message) {
    errorBox.textContent = message;
    errorBox.hidden = false;
  }

  function clearError() {
    errorBox.hidden = true;
    errorBox.textContent = '';
  }

  function setBusy(on, text) {
    submitting = on;
    busy.hidden = !on;
    if (text) busyText.textContent = text;
    root.classList.toggle('is-busy', on);
    slot.setAttribute('aria-hidden', on ? 'true' : 'false');
  }

  /* --- Button ------------------------------------------------------- */
  function theme() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'filled_black' : 'outline';
  }

  function width() {
    // Google accepts 200–400 px.
    return Math.max(200, Math.min(400, Math.floor(slot.clientWidth || 320)));
  }

  function render(force) {
    if (!ready) return;

    var key = theme() + '|' + width();
    if (!force && key === renderedWith) return;
    renderedWith = key;

    var next = document.createElement('div');
    next.className = 'ico-google__gsi';

    window.google.accounts.id.renderButton(next, {
      type: 'standard',
      theme: theme(),
      size: 'large',
      text: 'continue_with',
      shape: 'pill',
      logo_alignment: 'center',
      width: width(),
      click_listener: clearError
    });

    if (host) host.remove();
    host = next;
    slot.appendChild(host);
    placeholder.hidden = true;
  }

  /* --- Sign-in ------------------------------------------------------ */
  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function onCredential(response) {
    if (submitting) return;

    if (!response || !response.credential) {
      showError('Google did not complete the sign-in. Please try again.');
      return;
    }

    clearError();
    setBusy(true, 'Signing you in…');

    fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken()
      },
      body: JSON.stringify({ credential: response.credential })
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        return { status: res.status, ok: res.ok, data: data };
      });
    }).then(function (result) {
      if (result.ok && result.data.redirect) {
        setBusy(true, result.data.new ? 'Account created — taking you in…' : 'Signed in — taking you in…');
        window.location.assign(result.data.redirect);
        return;
      }

      var message = result.data.message;

      if (result.status === 419) message = 'This page has expired. Refresh the page and try again.';
      else if (result.status === 429) message = 'Too many sign-in attempts. Wait a minute and try again.';

      setBusy(false);
      showError(message || 'Something went wrong signing you in with Google. Please try again.');
    }).catch(function () {
      setBusy(false);
      showError('You appear to be offline. Check your connection and try again.');
    });
  }

  /* --- Loading Google's library -------------------------------------- */
  function init() {
    if (!window.google || !window.google.accounts || !window.google.accounts.id) {
      failLoad();
      return;
    }

    window.google.accounts.id.initialize({
      client_id: clientId,
      callback: onCredential,
      nonce: nonce,
      context: context,
      ux_mode: 'popup',
      auto_select: false,
      itp_support: true
    });

    ready = true;
    render(true);
  }

  function failLoad() {
    if (ready) return;
    placeholder.classList.add('is-failed');
    showError('Google sign-in could not load. It may be blocked by a browser extension or your network. You can still use email below.');
  }

  var script = document.createElement('script');
  script.src = GSI_SRC;
  script.async = true;
  script.defer = true;
  script.onload = init;
  script.onerror = failLoad;
  document.head.appendChild(script);

  setTimeout(function () { if (!ready) failLoad(); }, LOAD_TIMEOUT);

  /* --- Keep the button matched to theme and width --------------------- */
  new MutationObserver(function () { render(false); })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

  if (window.ResizeObserver) {
    var timer = null;
    new ResizeObserver(function () {
      clearTimeout(timer);
      timer = setTimeout(function () { render(false); }, 150);
    }).observe(slot);
  }

  // Coming back to this page with the browser's Back button after signing
  // in would otherwise show a stuck spinner.
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) setBusy(false);
  });
})();
