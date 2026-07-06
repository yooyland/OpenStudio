(function (global) {
  'use strict';

  var config = global.YooYStudio || {};

  function api(path, options) {
    options = options || {};
    var url = (config.restUrl || '').replace(/\/$/, '') + path;
    var headers = {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce || ''
    };

    return fetch(url, {
      method: options.method || 'GET',
      headers: headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok) {
          throw new Error((json && json.error) || 'API request failed');
        }
        return json;
      });
    });
  }

  var Core = {
    config: config,

    status: function () {
      return api('/core/status');
    },

    modules: function () {
      return api('/core/modules');
    },

    get: function (module, endpoint) {
      return api('/' + module + endpoint);
    },

    post: function (module, endpoint, body) {
      return api('/' + module + endpoint, { method: 'POST', body: body });
    },

    put: function (module, endpoint, body) {
      return api('/' + module + endpoint, { method: 'PUT', body: body });
    },

    credits: {
      balance: function () { return Core.get('credits', '/balance'); },
      plans: function () { return Core.get('credits', '/plans'); },
      transactions: function () { return Core.get('credits', '/transactions'); }
    },

    gallery: {
      showcase: function () { return Core.get('gallery', '/showcase'); },
      works: function () { return Core.get('gallery', '/works'); }
    },

    projects: {
      list: function () { return Core.get('projects', ''); },
      create: function (data) { return Core.post('projects', '', data); }
    },

    prompts: {
      list: function () { return Core.get('prompt-library', '/prompts'); },
      presets: function () { return Core.get('prompt-library', '/presets'); }
    },

    marketplace: {
      items: function () { return Core.get('marketplace', '/items'); }
    },

    community: {
      feed: function () { return Core.get('community', '/feed'); }
    },

    settings: {
      get: function () { return Core.get('settings', ''); },
      update: function (data) { return Core.put('settings', '', data); }
    },

    profile: {
      me: function () { return Core.get('user-profile', '/me'); }
    },

    router: {
      providers: function () { return Core.get('ai-router', '/providers'); },
      generate: function (data) { return Core.post('ai-router', '/route', data); },
      jobStatus: function (type, provider, jobId) {
        return Core.get('ai-router', '/jobs/' + jobId + '?type=' + encodeURIComponent(type) + '&provider=' + encodeURIComponent(provider));
      },
      estimate: function (data) { return Core.post('ai-router', '/estimate', data); }
    }
  };

  global.YooYCore = Core;
})(window);
