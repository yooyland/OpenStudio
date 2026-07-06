(function (global) {
  'use strict';
  var Core = global.YooYCore;
  if (!Core) return;

  Core.voice = {
    config: function () { return Core.get('voice-studio', '/config'); },
    speak: function (d) { return Core.post('voice-studio', '/speak', d); },
    clone: function (d) { return Core.post('voice-studio', '/clone', d); },
    options: function () { return Core.get('voice-studio', '/options'); },
    voices: function () { return Core.get('voice-studio', '/voices'); },
    insertPause: function (d) { return Core.post('voice-studio', '/pause/insert', d); },
    settings: function () { return Core.get('voice-studio', '/settings'); },
    updateSettings: function (d) { return Core.put('voice-studio', '/settings', d); },
    advanced: function () { return Core.get('voice-studio', '/advanced'); },
    history: function () { return Core.get('voice-studio', '/history'); },
    gallery: function () { return Core.get('voice-studio', '/gallery'); },
    providers: function () { return Core.get('voice-studio', '/router/providers'); }
  };
})(window);
