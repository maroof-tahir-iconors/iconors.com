/*
 * Iconors — header behaviour that sits outside the Vite build.
 *
 * Progressive enhancement only. Bootstrap's data attributes already make
 * the Tools menu open on click and with the keyboard; this adds opening
 * on hover for mouse users and focuses the search field when the mobile
 * search button opens the drawer. If this file fails to load, nothing in
 * the header stops working.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  ready(function () {
    initToolsHover();
    initMobileSearch();
  });

  /* Open the Tools menu on hover — mouse only. A touch screen has no
     hover, and a synthetic one there would make the first tap open the
     menu and immediately toggle it shut again. */
  function initToolsHover() {
    var wrap = document.querySelector('[data-ico-tools]');
    if (!wrap || !window.bootstrap || !window.bootstrap.Dropdown) return;
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;

    var button = wrap.querySelector('[data-bs-toggle="dropdown"]');
    if (!button) return;

    var dropdown = window.bootstrap.Dropdown.getOrCreateInstance(button);
    var closeTimer = null;

    wrap.addEventListener('mouseenter', function () {
      clearTimeout(closeTimer);
      if (button.getAttribute('aria-expanded') === 'true') return;

      var hadFocus = document.activeElement === button;
      dropdown.show();

      // show() moves focus to the button, which paints a keyboard focus
      // ring on something the visitor only pointed at. Hand focus back
      // unless they had already put it there themselves.
      if (!hadFocus) button.blur();
    });

    // A short grace period, so a diagonal move toward the panel that
    // briefly leaves the wrapper does not snap the menu shut.
    wrap.addEventListener('mouseleave', function () {
      clearTimeout(closeTimer);
      closeTimer = setTimeout(function () {
        if (!wrap.contains(document.activeElement) || document.activeElement === button) {
          dropdown.hide();
        }
      }, 180);
    });

    // Hover has already opened the menu by the time a mouse user clicks
    // "Tools", so Bootstrap's toggle would close it under their cursor.
    // A mouse click on the button while the pointer is over the menu
    // therefore keeps it open; moving away is what closes it. Keyboard
    // and touch activation still toggle as normal.
    //
    // Done by vetoing Bootstrap's own hide event rather than stopping the
    // click: Bootstrap listens for it in the capture phase on document,
    // so it has always run before a listener on the button could object.
    var mousePress = false;

    button.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse') mousePress = true;
    });

    window.addEventListener('pointerup', function () {
      // The click (and Bootstrap's toggle) fires just after pointerup.
      setTimeout(function () { mousePress = false; }, 80);
    });

    button.addEventListener('hide.bs.dropdown', function (e) {
      if (mousePress && wrap.matches(':hover')) e.preventDefault();
    });
  }

  /* The small-screen search button opens the drawer; put the caret in
     the search field once the slide-in has finished. */
  function initMobileSearch() {
    var drawer = document.getElementById('icoMobileNav');
    if (!drawer) return;

    var wantsSearch = false;

    document.querySelectorAll('[data-ico-mobile-search]').forEach(function (btn) {
      btn.addEventListener('click', function () { wantsSearch = true; });
    });

    drawer.addEventListener('shown.bs.offcanvas', function () {
      if (!wantsSearch) return;
      wantsSearch = false;

      var input = drawer.querySelector('input[type="search"]');
      if (input) input.focus();
    });
  }
})();
