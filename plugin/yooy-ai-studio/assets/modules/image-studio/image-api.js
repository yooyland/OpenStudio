(function (global) {
  'use strict';

  try {
    var Core = global.YooYCore;
    if (!Core) {
      global.YooYCore = { config: global.YooYStudio || {}, debug: function () { return false; }, debugLog: function () {} };
      Core = global.YooYCore;
    }

    function parseApiError(json, res) {
      if (json && json.code === 'rest_no_route') {
        var routeErr = new Error('REST API Route Not Found — The requested endpoint is not registered.');
        routeErr.code = 'rest_no_route';
        routeErr.restNoRoute = true;
        routeErr.details = json || {};
        return routeErr;
      }
      var message = json && json.message ? json.message : ((json && typeof json.error === 'string') ? json.error : 'API request failed' + (res && res.status ? ' (' + res.status + ')' : ''));
      var err = new Error(message);
      err.details = json || {};
      err.stage = json && json.stage;
      err.code = json && json.code;
      return err;
    }

    function joinRestUrl(base, rawPath) {
      base = base || '';
      var qIndex = rawPath.indexOf('?');
      var pathOnly = qIndex === -1 ? rawPath : rawPath.slice(0, qIndex);
      var query = qIndex === -1 ? '' : rawPath.slice(qIndex + 1);
      var m = base.match(/([?&])rest_route=([^&#]*)([\s\S]*)$/);
      if (m) {
        var routeVal = m[2].replace(/\/$/, '') + pathOnly;
        var url = base.slice(0, m.index) + m[1] + 'rest_route=' + routeVal + (m[3] || '');
        if (query) url += '&' + query;
        return url;
      }
      var built = base.replace(/\/$/, '') + pathOnly;
      if (query) built += '?' + query;
      return built;
    }

    function restCall(module, endpoint, method, body) {
      var cfg = Core.config || global.YooYStudio || {};
      var restUrl = cfg.restUrl || '';
      var restRouteUrl = cfg.restRouteUrl || '';
      var nonce = cfg.nonce || '';
      if (!restUrl && global.wpApiSettings && global.wpApiSettings.root) {
        restUrl = String(global.wpApiSettings.root).replace(/\/$/, '') + '/yoy-ai-studio/v1';
      }
      if (!nonce && global.wpApiSettings && global.wpApiSettings.nonce) {
        nonce = global.wpApiSettings.nonce;
      }
      var path = '/' + module + endpoint;
      if (method === 'POST') {
        global.YooYLastGenerateRequest = path;
      }
      if (Core.debugLog) Core.debugLog('image-api', method, path);
      if ((Core.config || {}).isAdmin && method === 'POST' && path.indexOf('/image-studio/generate') !== -1) {
        if (global.console && global.console.log) {
          global.console.log('===== REST Fetch Body (pre-fetch) =====');
          global.console.log(JSON.stringify(body, null, 2));
          global.console.log('=======================================');
        }
      }

      var order = [restUrl, restRouteUrl].filter(function (b, i, arr) { return b && arr.indexOf(b) === i; });
      if (!order.length) order = [restUrl];
      var triedPretty = restUrl ? joinRestUrl(restUrl, path) : '';
      var triedRoute = restRouteUrl ? joinRestUrl(restRouteUrl, path) : '';

      function attempt(i) {
        var base = order[i];
        var url = joinRestUrl(base, path);
        var mode = (base && base.indexOf('rest_route=') !== -1) ? 'rest_route' : 'wp-json';
        if (global.console && global.console.log) {
          global.console.log('[YooY REST]', {
            method: method, endpoint: path, baseMode: mode, finalUrl: url,
            body: body || null, assetVersion: (cfg.version || '')
          });
        }
        return fetch(url, {
          method: method,
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
          },
          body: body ? JSON.stringify(body) : undefined,
          credentials: 'same-origin'
        }).then(function (res) {
          return res.text().then(function (text) {
            var json = {};
            var parseError = false;
            try { json = text ? JSON.parse(text) : {}; } catch (e) { parseError = true; }
            if (res.ok && !parseError) { return json; }
            var unreachable = (res.status === 404) || (json && json.code === 'rest_no_route') || (parseError && !res.ok);
            if (unreachable && (i + 1) < order.length) {
              return attempt(i + 1);
            }
            if (unreachable) {
              var err = new Error('REST API Route Not Found — ' + method + ' ' + path);
              err.code = 'rest_no_route';
              err.restNoRoute = true;
              err.details = {
                code: 'rest_no_route', endpoint: path, method: method,
                tried_wp_json: triedPretty, tried_rest_route: triedRoute,
                registered_similar: (global.YooYRestHealth && global.YooYRestHealth.registered) || [],
                server: json || {}
              };
              if (global.console && global.console.error) global.console.error('[YooY REST] rest_no_route', err.details);
              throw err;
            }
            if (parseError) { throw new Error('Invalid API response'); }
            throw parseApiError(json, res);
          });
        });
      }

      return attempt(0);
    }

    var ImageAPI = {
      config: function () { return Core.get ? Core.get('image-studio', '/config') : restCall('image-studio', '/config', 'GET'); },
      generate: function (d) {
        global.YooYLastGenerateRequest = '/image-studio/generate';
        return Core.post ? Core.post('image-studio', '/generate', d) : restCall('image-studio', '/generate', 'POST', d);
      },
      options: function () { return Core.get ? Core.get('image-studio', '/generate/options') : restCall('image-studio', '/generate/options', 'GET'); },
      uploadRef: function (d) { return Core.post ? Core.post('image-studio', '/reference', d) : restCall('image-studio', '/reference', 'POST', d); },
      references: function () { return Core.get ? Core.get('image-studio', '/reference') : restCall('image-studio', '/reference', 'GET'); },
      settings: function () { return Core.get ? Core.get('image-studio', '/settings') : restCall('image-studio', '/settings', 'GET'); },
      updateSettings: function (d) { return Core.put ? Core.put('image-studio', '/settings', d) : restCall('image-studio', '/settings', 'PUT', d); },
      history: function () { return Core.get ? Core.get('image-studio', '/history') : restCall('image-studio', '/history', 'GET'); },
      gallery: function () { return Core.get ? Core.get('image-studio', '/gallery') : restCall('image-studio', '/gallery', 'GET'); },
      deleteGallery: function (id) { return Core.apiDelete ? Core.apiDelete('image-studio', '/gallery/' + encodeURIComponent(id)) : restCall('image-studio', '/gallery/' + encodeURIComponent(id), 'DELETE'); },
      promptReuse: function (d) { return Core.post ? Core.post('image-studio', '/prompt-reuse', d) : restCall('image-studio', '/prompt-reuse', 'POST', d); },
      edit: function (d) { return Core.post ? Core.post('image-studio', '/edit', d) : restCall('image-studio', '/edit', 'POST', d); },
      upscale: function (d) { return Core.post ? Core.post('image-studio', '/upscale', d) : restCall('image-studio', '/upscale', 'POST', d); },
      inpaint: function (d) { return Core.post ? Core.post('image-studio', '/inpaint', d) : restCall('image-studio', '/inpaint', 'POST', d); },
      outpaint: function (d) { return Core.post ? Core.post('image-studio', '/outpaint', d) : restCall('image-studio', '/outpaint', 'POST', d); },
      providers: function () { return Core.get ? Core.get('image-studio', '/router/providers') : restCall('image-studio', '/router/providers', 'GET'); },
      jobStatus: function (provider, jobId) {
        return Core.post ? Core.post('image-studio', '/router/status', { provider: provider, job_id: jobId }) : restCall('image-studio', '/router/status', 'POST', { provider: provider, job_id: jobId });
      },
      pollJob: function (jobId, provider) {
        return Core.post ? Core.post('image-studio', '/jobs/' + jobId + '/poll', { provider: provider || 'mock' }) : restCall('image-studio', '/jobs/' + jobId + '/poll', 'POST', { provider: provider || 'mock' });
      },
      credits: function () { return Core.get ? Core.get('image-studio', '/credits') : restCall('image-studio', '/credits', 'GET'); },
      estimate: function (d) { return Core.post ? Core.post('image-studio', '/credits/estimate', d) : restCall('image-studio', '/credits/estimate', 'POST', d); },
      composePrompt: function (d) { return Core.post ? Core.post('image-studio', '/prompt/compose', d) : restCall('image-studio', '/prompt/compose', 'POST', d); },
      providerHealth: function () { return Core.get ? Core.get('image-studio', '/provider-health') : restCall('image-studio', '/provider-health', 'GET'); }
    };

    Core.image = ImageAPI;
    global.YooYImageAPI = ImageAPI;
  } catch (err) {
    if (global.console && global.console.error) {
      global.console.error('[YooYImageAPI] init failed', err);
    }
  }
})(window);
