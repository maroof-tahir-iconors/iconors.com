/*
 * Iconors — PNG compression worker.
 *
 * Palette quantisation is heavy enough to freeze the page for seconds on
 * a large image, so it runs here, off the main thread. The pixels arrive
 * and leave as transferred buffers; nothing is copied and nothing is sent
 * anywhere but back to the page that started this worker.
 *
 * The page passes the UPNG script URL as ?upng=… so this file never has
 * to guess where the vendor folder is.
 */
(function () {
  'use strict';

  var ready = false;
  var loadError = null;

  try {
    var url = new URL(self.location.href).searchParams.get('upng');
    if (!url) throw new Error('Missing library URL.');
    importScripts(url);
    ready = !!self.UPNG;
  } catch (err) {
    loadError = err;
  }

  self.onmessage = function (event) {
    var job = event.data;

    if (!ready) {
      self.postMessage({ id: job.id, error: 'The PNG compressor did not load' + (loadError ? ': ' + loadError.message : '.') });
      return;
    }

    try {
      // colours === 0 asks UPNG for a lossless encode.
      var png = self.UPNG.encode([job.pixels], job.width, job.height, job.colours);
      self.postMessage({ id: job.id, png: png }, [png]);
    } catch (err) {
      self.postMessage({ id: job.id, error: (err && err.message) || 'PNG compression failed.' });
    }
  };
})();
