(function (global) {
  'use strict';
  var Core = global.YooYCore;
  if (!Core) return;

  Core.avatar = {
    config: function () { return Core.get('avatar-studio', '/config'); },
    generate: function (d) { return Core.post('avatar-studio', '/generate', d); },
    options: function () { return Core.get('avatar-studio', '/generate/options'); },
    settings: function () { return Core.get('avatar-studio', '/settings'); },
    updateSettings: function (d) { return Core.put('avatar-studio', '/settings', d); },
    subtitlePreview: function (d) { return Core.post('avatar-studio', '/subtitle/preview', d); },
    history: function () { return Core.get('avatar-studio', '/history'); },
    gallery: function () { return Core.get('avatar-studio', '/gallery'); },
    promptReuse: function (d) { return Core.post('avatar-studio', '/prompt-reuse', d); },
    providers: function () { return Core.get('avatar-studio', '/router/providers'); }
  };
})(window);
