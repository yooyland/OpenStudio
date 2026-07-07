(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core) return;

  Core.referenceAssets = {
    schema: function () { return Core.get('reference-assets', '/schema'); },
    list: function (params) {
      var q = '';
      if (params) {
        var parts = [];
        if (params.studio) parts.push('studio=' + encodeURIComponent(params.studio));
        if (params.project_id) parts.push('project_id=' + encodeURIComponent(params.project_id));
        if (params.type) parts.push('type=' + encodeURIComponent(params.type));
        if (parts.length) q = '?' + parts.join('&');
      }
      return Core.get('reference-assets', '' + q);
    },
    get: function (id) { return Core.get('reference-assets', '/' + encodeURIComponent(id)); },
    upload: function (data) { return Core.post('reference-assets', '', data); },
    update: function (id, data) { return Core.put('reference-assets', '/' + encodeURIComponent(id), data); },
    remove: function (id) { return Core.del('reference-assets', '/' + encodeURIComponent(id)); },
    fromGallery: function (data) { return Core.post('reference-assets', '/from-gallery', data); },
    fromImport: function (importId, data) {
      return Core.post('reference-assets', '/from-import/' + encodeURIComponent(importId), data || {});
    },
    fromProject: function (data) { return Core.post('reference-assets', '/from-project', data); }
  };
})(window);
