(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core) return;

  function withSignal(promise, signal) {
    if (!signal) return promise;
    return new Promise(function (resolve, reject) {
      var settled = false;
      function onAbort() {
        if (settled) return;
        settled = true;
        var err = new Error('Aborted');
        err.name = 'AbortError';
        reject(err);
      }
      if (signal.aborted) {
        onAbort();
        return;
      }
      signal.addEventListener('abort', onAbort);
      promise.then(function (value) {
        signal.removeEventListener('abort', onAbort);
        if (signal.aborted) {
          onAbort();
          return;
        }
        settled = true;
        resolve(value);
      }, function (err) {
        signal.removeEventListener('abort', onAbort);
        if (settled) return;
        settled = true;
        reject(err);
      });
    });
  }

  Core.translator = {
    config: function () {
      return Core.get('translator-studio', '/config');
    },
    languages: function () {
      return Core.get('translator-studio', '/languages');
    },
    modes: function () {
      return Core.get('translator-studio', '/modes');
    },
    providers: function () {
      return Core.get('translator-studio', '/providers');
    },
    history: function (params) {
      var q = '';
      if (params && params.limit) q = '?limit=' + encodeURIComponent(params.limit);
      return Core.get('translator-studio', '/history' + q);
    },
    historyItem: function (id) {
      return Core.get('translator-studio', '/history/' + encodeURIComponent(id));
    },
    reopen: function (id) {
      return Core.post('translator-studio', '/history/' + encodeURIComponent(id) + '/reopen', {});
    },
    translate: function (body, signal) {
      // Core.post provides wp-json / rest_route fallback + nonce.
      // AbortController cancels UI handling of a late response.
      return withSignal(Core.post('translator-studio', '/translate', body || {}), signal);
    }
  };
})(window);
