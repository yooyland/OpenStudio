(function (global) {
  'use strict';

  try {
    var config = global.YooYStudio || {};

    function isDebug() {
      return !!(config.debug || global.YOOY_DEBUG);
    }

    function debugLog() {
      if (!isDebug()) return;
      var args = ['[YooYCore]'].concat(Array.prototype.slice.call(arguments));
      if (global.console && global.console.log) global.console.log.apply(global.console, args);
    }

    function restConfig() {
      var restUrl = config.restUrl || '';
      var nonce = config.nonce || '';
      if (!restUrl && global.wpApiSettings && global.wpApiSettings.root) {
        restUrl = String(global.wpApiSettings.root).replace(/\/$/, '') + '/yoy-ai-studio/v1';
      }
      if (!nonce && global.wpApiSettings && global.wpApiSettings.nonce) {
        nonce = global.wpApiSettings.nonce;
      }
      return { restUrl: restUrl, nonce: nonce };
    }

    function parseApiError(json, res) {
      var message = '';
      if (json && json.message) {
        message = json.message;
      } else if (json && typeof json.error === 'string') {
        message = json.error;
      } else if (json && json.error && json.error.message) {
        message = json.error.message;
      } else {
        message = 'API request failed' + (res && res.status ? ' (' + res.status + ')' : '');
      }
      var err = new Error(message);
      err.details = json || {};
      err.stage = json && json.stage;
      err.code = json && json.code;
      return err;
    }

    function api(path, options) {
      options = options || {};
      var cfg = restConfig();
      var url = (cfg.restUrl || '').replace(/\/$/, '') + path;
      var headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || ''
      };

      debugLog('request', options.method || 'GET', url);

      if (config.isAdmin && options.body && path.indexOf('/image-studio/generate') !== -1) {
        if (global.console && global.console.log) {
          global.console.log('===== REST Fetch Body (pre-fetch) =====');
          global.console.log(JSON.stringify(options.body, null, 2));
          global.console.log('=======================================');
        }
      }

      return fetch(url, {
        method: options.method || 'GET',
        headers: headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
        credentials: 'same-origin'
      }).then(function (res) {
        return res.text().then(function (text) {
          var json = null;
          try {
            json = text ? JSON.parse(text) : {};
          } catch (parseErr) {
            debugLog('json parse error', parseErr, text.slice(0, 200));
            throw new Error('Invalid API response');
          }
          if (!res.ok) {
            throw parseApiError(json, res);
          }
          return json;
        });
      });
    }

    var Core = {
      config: config,
      debug: isDebug,
      debugLog: debugLog,

      status: function () {
        return api('/core/status');
      },

      dashboard: function () {
        return api('/core/dashboard');
      },

      modules: function () {
        return api('/core/modules');
      },

      get: function (module, endpoint) {
        return api('/' + module + endpoint);
      },

      post: function (module, endpoint, body) {
        var ep = '/' + module + endpoint;
        global.YooYLastGenerateRequest = ep;
        return api(ep, { method: 'POST', body: body });
      },

      put: function (module, endpoint, body) {
        return api('/' + module + endpoint, { method: 'PUT', body: body });
      },

      del: function (module, endpoint) {
        return api('/' + module + endpoint, { method: 'DELETE' });
      },

      credits: {
        balance: function () { return Core.get('credits', '/balance'); },
        overview: function () { return Core.get('credits', '/overview'); },
        plans: function () { return Core.get('credits', '/plans'); },
        transactions: function () { return Core.get('credits', '/transactions'); }
      },

      gallery: {
        showcase: function () { return Core.get('gallery', '/showcase'); },
        works: function () { return Core.get('gallery', '/works'); }
      },

      projects: {
        list: function () { return Core.get('projects', ''); },
        create: function (data) { return Core.post('projects', '', data); },
        get: function (id) { return Core.get('projects', '/' + encodeURIComponent(id)); },
        update: function (id, data) { return Core.put('projects', '/' + encodeURIComponent(id), data); },
        delete: function (id) { return Core.del('projects', '/' + encodeURIComponent(id)); },
        addAsset: function (id, data) { return Core.post('projects', '/' + encodeURIComponent(id) + '/assets', data); },
        removeAsset: function (id, assetId) { return Core.del('projects', '/' + encodeURIComponent(id) + '/assets/' + encodeURIComponent(assetId)); }
      },

      prompts: {
        list: function () { return Core.get('prompt-library', '/prompts'); },
        presets: (function () {
          var cache = null;
          var pending = null;
          return function () {
            if (cache) return Promise.resolve({ data: cache, success: true });
            if (pending) return pending;
            pending = Core.get('prompt-library', '/presets').then(function (res) {
              cache = res.data || res;
              pending = null;
              return res;
            }).catch(function (err) {
              pending = null;
              throw err;
            });
            return pending;
          };
        })()
      },

      marketplace: {
        items: function () { return Core.get('marketplace', '/items'); }
      },

      importEngine: {
        schema: function () { return Core.get('import-engine', '/schema'); },
        queue: function () { return Core.get('import-engine', '/queue'); },
        history: function (limit) {
          var q = limit ? '?limit=' + encodeURIComponent(limit) : '';
          return Core.get('import-engine', '/history' + q);
        },
        process: function (body) { return Core.post('import-engine', '/process', body); },
        stats: function () { return Core.get('import-engine', '/stats'); },
        uploadFiles: function (fileList, options) {
          options = options || {};
          var c = restConfig();
          var url = (c.restUrl || '').replace(/\/$/, '') + '/import-engine/upload';
          var fd = new FormData();
          for (var i = 0; i < fileList.length; i++) {
            fd.append('files[]', fileList[i]);
          }
          fd.append('source', options.source || 'upload');
          fd.append('origin', options.origin || 'Imported');
          if (options.project_id) fd.append('project_id', options.project_id);
          if (options.new_project_title) fd.append('new_project_title', options.new_project_title);
          if (options.type_hint) fd.append('type_hint', options.type_hint);

          return fetch(url, {
            method: 'POST',
            headers: { 'X-WP-Nonce': c.nonce || '' },
            body: fd,
            credentials: 'same-origin'
          }).then(function (res) {
            return res.text().then(function (text) {
              var json = {};
              try { json = text ? JSON.parse(text) : {}; } catch (e) { throw new Error('Invalid API response'); }
              if (!res.ok) throw new Error((json && json.error) || json.message || 'Upload failed');
              return json;
            });
          });
        }
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
      },

      admin: {
        dashboard: function () { return Core.get('admin-console', '/dashboard'); },
        providers: function () { return Core.get('admin-console', '/providers'); },
        getProvider: function (id) { return Core.get('admin-console', '/providers/' + encodeURIComponent(id)); },
        providersSummary: function () { return Core.get('admin-console', '/providers/summary'); },
        saveProvider: function (id, data) {
            var providerId = id || (data && data.id);
            var payload = data ? Object.assign({}, data) : {};
            if (!providerId) {
                providerId = payload.id;
            }
            if (!providerId) {
                return Promise.reject(new Error('Provider id is required.'));
            }
            delete payload.id;
            var path = '/providers/' + encodeURIComponent(providerId);
            return Core.put('admin-console', path, payload).catch(function () {
                return Core.post('admin-console', path, payload);
            });
        },
        setProviderStudioDefault: function (id, studio) {
            return Core.post('admin-console', '/providers/' + encodeURIComponent(id) + '/studio-default', { studio: studio });
        },
        testProvider: function (idOrData) {
            var id = typeof idOrData === 'string' ? idOrData : (idOrData && idOrData.id);
            if (!id) {
                return Promise.reject(new Error('Provider id is required.'));
            }
            return Core.post('admin-console', '/providers/' + encodeURIComponent(id) + '/test', {});
        },
        disableProvider: function (id) { return Core.post('admin-console', '/providers/' + encodeURIComponent(id) + '/disable', {}); },
        enableProvider: function (id) { return Core.post('admin-console', '/providers/' + encodeURIComponent(id) + '/enable', {}); },
        providerLogs: function (id) { return Core.get('admin-console', '/providers/' + encodeURIComponent(id) + '/logs'); },
        providerMonitoring: function (id) { return Core.get('admin-console', '/providers/' + encodeURIComponent(id) + '/monitoring'); },
        users: function (search) {
          var q = search ? '?search=' + encodeURIComponent(search) : '';
          return Core.get('admin-console', '/users' + q);
        },
        adjustCredits: function (userId, data) {
          return Core.put('admin-console', '/users/' + userId + '/credits', data);
        },
        setUserPlan: function (userId, data) {
          return Core.put('admin-console', '/users/' + userId + '/plan', data);
        },
        creditPackages: function () { return Core.get('admin-console', '/credits/packages'); },
        saveCreditPackages: function (data) { return Core.put('admin-console', '/credits/packages', data); },
        searchWcProducts: function (q) {
          var query = q ? '?q=' + encodeURIComponent(q) : '';
          return Core.get('admin-console', '/credits/wc-products' + query);
        },
        creditTransactions: function (userId) {
          var q = userId ? '?user_id=' + userId : '';
          return Core.get('admin-console', '/credits/transactions' + q);
        },
        settings: function () { return Core.get('admin-console', '/settings'); },
        saveSettings: function (data) { return Core.put('admin-console', '/settings', data); },
        logs: function (providerId) {
          var q = providerId ? '?provider_id=' + encodeURIComponent(providerId) : '';
          return Core.get('admin-console', '/logs' + q);
        },
        system: function () { return Core.get('admin-console', '/system'); },
        backup: function () { return Core.get('admin-console', '/backup'); },
        projects: function () { return Core.get('admin-console', '/projects'); },
        gallery: function () { return Core.get('admin-console', '/gallery'); },
        community: function () { return Core.get('admin-console', '/community'); },
        marketplace: function () { return Core.get('admin-console', '/marketplace'); },
        prompts: function () { return Core.get('admin-console', '/prompts'); },
        importStats: function () { return Core.get('import-engine', '/stats'); },
        homeSections: {
          list: function () { return Core.get('admin-console', '/home-sections'); },
          create: function (data) { return Core.post('admin-console', '/home-sections', data); },
          update: function (id, data) { return Core.put('admin-console', '/home-sections/' + encodeURIComponent(id), data); },
          remove: function (id) { return Core.del('admin-console', '/home-sections/' + encodeURIComponent(id)); },
          reorder: function (orderedIds) { return Core.post('admin-console', '/home-sections/reorder', { ordered_ids: orderedIds }); },
          searchWorks: function (q, limit) {
            var parts = [];
            if (q) parts.push('q=' + encodeURIComponent(q));
            if (limit) parts.push('limit=' + encodeURIComponent(limit));
            var query = parts.length ? '?' + parts.join('&') : '';
            return Core.get('admin-console', '/home-sections/works-search' + query);
          }
        }
      }
    };

    Core.notifyGalleryUpdated = function () {
      try {
        document.dispatchEvent(new CustomEvent('yoy:gallery:updated'));
      } catch (e) { /* ignore */ }
    };

    global.YooYCore = Core;
    debugLog('core initialized');
  } catch (err) {
    if (global.console && global.console.error) {
      global.console.error('[YooYCore] init failed', err);
    }
    if (!global.YooYCore) {
      global.YooYCore = { config: global.YooYStudio || {}, debug: function () { return false; }, debugLog: function () {} };
    }
  }
})(window);
