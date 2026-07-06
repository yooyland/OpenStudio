(function (global) {
  'use strict';
  var Core = global.YooYCore;
  if (!Core) return;

  var ImageAPI = {
    config: function () { return Core.get('image-studio', '/config'); },
    generate: function (d) { return Core.post('image-studio', '/generate', d); },
    options: function () { return Core.get('image-studio', '/generate/options'); },
    uploadRef: function (d) { return Core.post('image-studio', '/reference', d); },
    references: function () { return Core.get('image-studio', '/reference'); },
    settings: function () { return Core.get('image-studio', '/settings'); },
    updateSettings: function (d) { return Core.put('image-studio', '/settings', d); },
    history: function () { return Core.get('image-studio', '/history'); },
    gallery: function () { return Core.get('image-studio', '/gallery'); },
    promptReuse: function (d) { return Core.post('image-studio', '/prompt-reuse', d); },
    edit: function (d) { return Core.post('image-studio', '/edit', d); },
    upscale: function (d) { return Core.post('image-studio', '/upscale', d); },
    inpaint: function (d) { return Core.post('image-studio', '/inpaint', d); },
    outpaint: function (d) { return Core.post('image-studio', '/outpaint', d); },
    providers: function () { return Core.get('image-studio', '/router/providers'); },
    jobStatus: function (provider, jobId) {
      return Core.post('image-studio', '/router/status', { provider: provider, job_id: jobId });
    },
    pollJob: function (jobId, provider) {
      return Core.post('image-studio', '/jobs/' + jobId + '/poll', { provider: provider || 'mock' });
    },
    credits: function () { return Core.get('image-studio', '/credits'); },
    estimate: function (d) { return Core.post('image-studio', '/credits/estimate', d); }
  };

  Core.image = ImageAPI;
})(window);
