(function (global) {
  'use strict';

  try {
    var Core = global.YooYCore;
    if (!Core) {
      global.YooYCore = { config: global.YooYStudio || {}, debug: function () { return false; }, debugLog: function () {} };
      Core = global.YooYCore;
    }

    function parseApiError(json, res) {
      var message = json && json.message ? json.message : ((json && typeof json.error === 'string') ? json.error : 'API request failed' + (res && res.status ? ' (' + res.status + ')' : ''));
      var err = new Error(message);
      err.details = json || {};
      err.stage = json && json.stage;
      err.code = json && json.code;
      return err;
    }

    function restCall(module, endpoint, method, body) {
      var cfg = Core.config || global.YooYStudio || {};
      var restUrl = cfg.restUrl || '';
      var nonce = cfg.nonce || '';
      if (!restUrl && global.wpApiSettings && global.wpApiSettings.root) {
        restUrl = String(global.wpApiSettings.root).replace(/\/$/, '') + '/yoy-ai-studio/v1';
      }
      if (!nonce && global.wpApiSettings && global.wpApiSettings.nonce) {
        nonce = global.wpApiSettings.nonce;
      }
      var path = '/' + module + endpoint;
      var url = restUrl.replace(/\/$/, '') + path;
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
          try { json = text ? JSON.parse(text) : {}; } catch (e) { throw new Error('Invalid API response'); }
          if (!res.ok) throw parseApiError(json, res);
          return json;
        });
      });
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
      estimate: function (d) { return Core.post ? Core.post('image-studio', '/credits/estimate', d) : restCall('image-studio', '/credits/estimate', 'POST', d); }
    };

    Core.image = ImageAPI;
    global.YooYImageAPI = ImageAPI;
  } catch (err) {
    if (global.console && global.console.error) {
      global.console.error('[YooYImageAPI] init failed', err);
    }
  }
})(window);
