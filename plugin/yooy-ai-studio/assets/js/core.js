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
      var restRouteUrl = config.restRouteUrl || '';
      var nonce = config.nonce || '';
      if (!restUrl && global.wpApiSettings && global.wpApiSettings.root) {
        restUrl = String(global.wpApiSettings.root).replace(/\/$/, '') + '/yoy-ai-studio/v1';
      }
      // Always be able to fall back to the index.php?rest_route= form, which
      // works on every host (Apache/Nginx) regardless of permalink settings.
      if (!restRouteUrl && restUrl) {
        var origin = restUrl.replace(/\/wp-json\/.*$/, '').replace(/\?rest_route=.*$/, '').replace(/\/$/, '');
        if (restUrl.indexOf('rest_route=') === -1 && origin) {
          restRouteUrl = origin + '/index.php?rest_route=/yoy-ai-studio/v1';
        }
      }
      if (!nonce && global.wpApiSettings && global.wpApiSettings.nonce) {
        nonce = global.wpApiSettings.nonce;
      }
      return { restUrl: restUrl, restRouteUrl: restRouteUrl, nonce: nonce };
    }

    // Split a raw endpoint path (e.g. "/core/public-works?limit=10") into its
    // path portion and query string so query params never corrupt the
    // rest_route value on plain-permalink hosts.
    function splitPath(rawPath) {
      var qIndex = rawPath.indexOf('?');
      return {
        path: qIndex === -1 ? rawPath : rawPath.slice(0, qIndex),
        query: qIndex === -1 ? '' : rawPath.slice(qIndex + 1)
      };
    }

    // Join a namespace base URL with an endpoint path. Handles BOTH:
    //   pretty: https://site/wp-json/yoy-ai-studio/v1
    //   plain : https://site/index.php?rest_route=/yoy-ai-studio/v1
    function joinRestUrl(base, rawPath) {
      base = base || '';
      var parts = splitPath(rawPath);
      var m = base.match(/([?&])rest_route=([^&#]*)([\s\S]*)$/);
      if (m) {
        var routeVal = m[2].replace(/\/$/, '') + parts.path;
        var url = base.slice(0, m.index) + m[1] + 'rest_route=' + routeVal + (m[3] || '');
        if (parts.query) url += '&' + parts.query;
        return url;
      }
      var built = base.replace(/\/$/, '') + parts.path;
      if (parts.query) built += '?' + parts.query;
      return built;
    }

    function isNoRoute(res, json) {
      return res && res.status === 404 && json && json.code === 'rest_no_route';
    }

    function parseApiError(json, res) {
      // A rest_no_route error is a REST wiring problem, NOT a provider /
      // OpenAI / billing failure. Surface it unmistakably.
      if (json && json.code === 'rest_no_route') {
        var routeErr = new Error('REST API Route Not Found — The requested endpoint is not registered.');
        routeErr.code = 'rest_no_route';
        routeErr.restNoRoute = true;
        routeErr.details = json || {};
        return routeErr;
      }
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

    function rawFetch(url, options, cfg) {
      var headers = {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || ''
      };
      return fetch(url, {
        method: options.method || 'GET',
        headers: headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
        credentials: 'same-origin'
      }).then(function (res) {
        return res.text().then(function (text) {
          var json = null;
          var parseError = false;
          try {
            json = text ? JSON.parse(text) : {};
          } catch (parseErr) {
            parseError = true;
            debugLog('json parse error', parseErr, text.slice(0, 200));
          }
          return { res: res, json: json, parseError: parseError };
        });
      });
    }

    function baseModeLabel(base) {
      return (base && base.indexOf('rest_route=') !== -1) ? 'rest_route' : 'wp-json';
    }

    // Session-persistent transport mode. Once a form (wp-json / rest_route)
    // succeeds it is kept for the whole session so Whois-style hosts that block
    // /wp-json/ transparently stay on index.php?rest_route= after one fallback.
    function getRestMode() {
      if (typeof getRestMode._v === 'string') return getRestMode._v;
      var v = '';
      try { v = global.sessionStorage.getItem('yoyRestMode') || ''; } catch (e) {}
      getRestMode._v = v;
      return v;
    }
    function setRestMode(mode) {
      if (getRestMode._v === mode) return;
      getRestMode._v = mode;
      try { global.sessionStorage.setItem('yoyRestMode', mode); } catch (e) {}
    }

    function buildNoRouteError(endpoint, method, triedPretty, triedRoute, serverJson) {
      var similar = [];
      try {
        var h = global.YooYRestHealth;
        if (h && h.registered && h.registered.length) {
          var seg = endpoint.split('?')[0].split('/').filter(Boolean);
          var needle = seg.length ? seg[0] : '';
          similar = h.registered.filter(function (r) { return needle && r.indexOf(needle) !== -1; });
        }
      } catch (e) {}
      var details = {
        code: 'rest_no_route',
        endpoint: endpoint,
        method: method,
        tried_wp_json: triedPretty,
        tried_rest_route: triedRoute,
        registered_similar: similar,
        server: serverJson || {}
      };
      var err = new Error('REST API Route Not Found — ' + method + ' ' + endpoint);
      err.code = 'rest_no_route';
      err.restNoRoute = true;
      err.details = details;
      return err;
    }

    function api(path, options) {
      options = options || {};
      var cfg = restConfig();

      var pretty = cfg.restUrl || '';
      var route = cfg.restRouteUrl || '';
      var order;
      if (getRestMode() === 'rest_route' && route) {
        order = [route, pretty];
      } else {
        order = [pretty, route];
      }
      order = order.filter(function (b, i, arr) { return b && arr.indexOf(b) === i; });
      if (!order.length) order = [pretty];

      var method = options.method || 'GET';
      var triedPretty = pretty ? joinRestUrl(pretty, path) : '';
      var triedRoute = route ? joinRestUrl(route, path) : '';

      function attempt(i) {
        var base = order[i];
        var url = joinRestUrl(base, path);
        var mode = baseModeLabel(base);

        // Trace every REST call directly before dispatch (req. 1).
        if (global.console && global.console.log) {
          global.console.log('[YooY REST]', {
            method: method,
            endpoint: path,
            baseMode: mode,
            finalUrl: url,
            body: options.body || null,
            assetVersion: config.version || ''
          });
        }

        return rawFetch(url, options, cfg).then(function (r) {
          if (r.res.ok && !r.parseError) {
            setRestMode(mode);
            return r.json;
          }
          var unreachable = isNoRoute(r.res, r.json) || r.res.status === 404 || (r.parseError && !r.res.ok);
          // Exactly one automatic fallback to the alternate permalink form.
          if (unreachable && (i + 1) < order.length) {
            debugLog('unreachable on', url, '(status ' + r.res.status + ') -> single fallback');
            return attempt(i + 1);
          }
          if (unreachable) {
            var routeErr = buildNoRouteError(path, method, triedPretty, triedRoute, r.json);
            if (global.console && global.console.error) {
              global.console.error('[YooY REST] rest_no_route — endpoint unreachable via both forms', routeErr.details);
            }
            throw routeErr;
          }
          if (r.parseError) {
            throw new Error('Invalid API response');
          }
          throw parseApiError(r.json, r.res);
        });
      }

      return attempt(0);
    }

    var Core = {
      config: config,
      debug: isDebug,
      debugLog: debugLog,

      status: function () {
        return api('/core/status');
      },

      restHealth: function () {
        return api('/core/rest-health').then(function (res) {
          var data = res && (res.data || res);
          if (data) { try { global.YooYRestHealth = data; } catch (e) {} }
          return res;
        });
      },

      systemCheck: function () {
        return api('/core/system-check').then(function (res) {
          var data = res && (res.data || res);
          if (data) { try { global.YooYSystemHealth = data; } catch (e) {} }
          return res;
        });
      },

      systemFix: function (action) {
        return api('/core/system-fix', { method: 'POST', body: { action: action } });
      },

      dashboard: function () {
        return api('/core/dashboard');
      },

      homePublic: function () {
        return api('/core/home-public');
      },

      publicWorks: function (params) {
        var q = '';
        if (params && params.limit) q += '?limit=' + encodeURIComponent(params.limit);
        if (params && params.source) q += (q ? '&' : '?') + 'source=' + encodeURIComponent(params.source);
        return api('/core/public-works' + q);
      },

      deleteJob: function (id) {
        return api('/core/jobs/' + encodeURIComponent(id), { method: 'DELETE' });
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
        create: function (data) { return Core.post('projects', '', data || {}); },
        get: function (id) { return Core.get('projects', '/' + encodeURIComponent(id)); },
        update: function (id, data) { return Core.put('projects', '/' + encodeURIComponent(id), data || {}); },
        delete: function (id) { return Core.del('projects', '/' + encodeURIComponent(id)); },
        addAsset: function (id, data) { return Core.post('projects', '/' + encodeURIComponent(id) + '/assets', data || {}); },
        removeAsset: function (id, assetId) {
          return Core.del('projects', '/' + encodeURIComponent(id) + '/assets/' + encodeURIComponent(assetId));
        }
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
          var uploadBase = (getRestMode() === 'rest_route' && c.restRouteUrl) ? c.restRouteUrl : (c.restUrl || '');
          var url = joinRestUrl(uploadBase, '/import-engine/upload');
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
        systemHealth: function () { return Core.get('admin-console', '/system-health'); },
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
        providerErrorLog: function (limit) { return Core.get('admin-console', '/provider-errors' + (limit ? '?limit=' + encodeURIComponent(limit) : '')); },
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
        },
        officialShowcase: {
          list: function () { return Core.get('admin-console', '/official-showcase'); },
          create: function (data) { return Core.post('admin-console', '/official-showcase', data); },
          update: function (id, data) { return Core.put('admin-console', '/official-showcase/' + encodeURIComponent(id), data); },
          remove: function (id) { return Core.del('admin-console', '/official-showcase/' + encodeURIComponent(id)); },
          reorder: function (orderedIds) { return Core.post('admin-console', '/official-showcase/reorder', { ordered_ids: orderedIds }); },
          seed: function () { return Core.post('admin-console', '/official-showcase/seed', {}); }
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
