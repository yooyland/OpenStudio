(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core) return;

  Core.gallery = {
    showcase: function () { return Core.get('gallery', '/showcase'); },
    works: function () { return Core.get('gallery', '/works'); },
    types: function () { return Core.get('gallery', '/types'); },
    items: function (params) {
      var q = '';
      if (params) {
        var parts = [];
        if (params.type) parts.push('type=' + encodeURIComponent(params.type));
        if (params.favorite) parts.push('favorite=1');
        if (params.sync) parts.push('sync=1');
        if (parts.length) q = '?' + parts.join('&');
      }
      return Core.get('gallery', '/items' + q);
    },
    item: function (id) { return Core.get('gallery', '/items/' + id); },
    save: function (data) { return Core.post('gallery', '/items', data); },
    update: function (id, data) { return Core.put('gallery', '/items/' + id, data); },
    remove: function (id) { return Core.apiDelete('gallery', '/items/' + id); },
    sync: function () { return Core.post('gallery', '/sync'); },
    favorite: function (id) { return Core.post('gallery', '/items/' + id + '/favorite'); },
    visibility: function (id, isPublic) { return Core.post('gallery', '/items/' + id + '/visibility', { public: isPublic }); },
    copy: function (id) { return Core.post('gallery', '/items/' + id + '/copy'); },
    regenerate: function (id) { return Core.post('gallery', '/items/' + id + '/regenerate'); },
    download: function (id) { return Core.get('gallery', '/items/' + id + '/download'); },
    marketplace: function (id) { return Core.post('gallery', '/items/' + id + '/marketplace'); },
    community: function (id) { return Core.post('gallery', '/items/' + id + '/community'); },
    publish: function (id) { return Core.post('gallery', '/items/' + id + '/publish'); },
    project: function (id, projectId) { return Core.post('gallery', '/items/' + id + '/project', { project_id: projectId || '' }); }
  };

  Core.apiDelete = function (module, endpoint) {
    var config = Core.config || {};
    var url = (config.restUrl || '').replace(/\/$/, '') + '/' + module + endpoint;
    return fetch(url, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce || ''
      },
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok) throw new Error((json && json.error) || 'API request failed');
        return json;
      });
    });
  };
})(window);
