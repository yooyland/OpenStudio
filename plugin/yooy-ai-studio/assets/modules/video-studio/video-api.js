(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core) return;

  var VideoAPI = {
    config: function () { return Core.get('video-studio', '/config'); },
    generate: function (data) { return Core.post('video-studio', '/generate', data); },
    generateOptions: function () { return Core.get('video-studio', '/generate/options'); },
    canvas: function () { return Core.get('video-studio', '/canvas'); },
    saveCanvas: function (data) { return Core.post('video-studio', '/canvas', data); },
    templates: function (cat) { return Core.get('video-studio', '/templates' + (cat ? '?category=' + cat : '')); },
    applyTemplate: function (id) { return Core.post('video-studio', '/templates/' + id + '/apply', {}); },
    advanced: function () { return Core.get('video-studio', '/advanced'); },
    gallery: function () { return Core.get('video-studio', '/gallery'); },
    history: function () { return Core.get('video-studio', '/history'); },
    promptReuse: function (data) { return Core.post('video-studio', '/prompt-reuse', data); },
    settings: function () { return Core.get('video-studio', '/settings'); },
    updateSettings: function (data) { return Core.put('video-studio', '/settings', data); },
    storyboard: function () { return Core.get('video-studio', '/storyboard'); },
    saveStoryboard: function (data) { return Core.post('video-studio', '/storyboard', data); },
    generateStoryboard: function (data) { return Core.post('video-studio', '/storyboard/generate', data || {}); },
    providers: function () { return Core.get('video-studio', '/router/providers'); },
    jobStatus: function (data) { return Core.post('video-studio', '/router/status', data); }
  };

  Core.video = VideoAPI;
})(window);
