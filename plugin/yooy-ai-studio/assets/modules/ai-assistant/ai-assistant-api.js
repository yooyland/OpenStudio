(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core) return;

  Core.assistant = {
    config: function () {
      return Core.get('ai-assistant', '/config');
    },
    recommendations: function (params) {
      var q = '';
      if (params && params.project_id) {
        q = '?project_id=' + encodeURIComponent(params.project_id);
      }
      return Core.get('ai-assistant', '/recommendations' + q);
    },
    context: function (params) {
      var parts = [];
      if (params && params.project_id) parts.push('project_id=' + encodeURIComponent(params.project_id));
      if (params && params.studio) parts.push('studio=' + encodeURIComponent(params.studio));
      var q = parts.length ? ('?' + parts.join('&')) : '';
      return Core.get('ai-assistant', '/context' + q);
    },
    chat: function (body) {
      return Core.post('ai-assistant', '/chat', body || {});
    },
    compose: function (body) {
      return Core.post('ai-assistant', '/compose', body || {});
    }
  };
})(typeof window !== 'undefined' ? window : this);
