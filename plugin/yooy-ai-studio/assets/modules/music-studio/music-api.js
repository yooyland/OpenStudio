(function (global) {
  'use strict';
  var Core = global.YooYCore;
  if (!Core) return;

  Core.music = {
    config: function () { return Core.get('music-studio', '/config'); },
    generate: function (d) { return Core.post('music-studio', '/generate', d); },
    options: function () { return Core.get('music-studio', '/generate/options'); },
    structures: function () { return Core.get('music-studio', '/structure'); },
    skeleton: function (id, lang) { return Core.get('music-studio', '/structure/' + id + '/skeleton?language=' + (lang || 'ko')); },
    references: function () { return Core.get('music-studio', '/reference'); },
    uploadRef: function (d) { return Core.post('music-studio', '/reference', d); },
    settings: function () { return Core.get('music-studio', '/settings'); },
    updateSettings: function (d) { return Core.put('music-studio', '/settings', d); },
    advanced: function () { return Core.get('music-studio', '/advanced'); },
    history: function () { return Core.get('music-studio', '/history'); },
    gallery: function () { return Core.get('music-studio', '/gallery'); },
    promptReuse: function (d) { return Core.post('music-studio', '/prompt-reuse', d); },
    credits: function () { return Core.get('music-studio', '/credits'); },
    estimate: function (d) { return Core.post('music-studio', '/credits/estimate', d); },
    providers: function () { return Core.get('music-studio', '/router/providers'); }
  };
})(window);
