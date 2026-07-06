(function () {
  'use strict';

  var Core = window.YooYCore;
  if (!Core || !Core.video) return;

  var state = {
    tab: 'generator',
    settings: {},
    canvas: null,
    storyboard: null,
    providers: [],
    advanced: {},
    generating: false,
    lastResult: null,
    activeGalleryId: null,
    credits: { balance: 0, unlimited: false, estimate: 0, can_afford: true }
  };

  function $(s, c) { return (c || document).querySelector(s); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function mount(container) {
    if (!container || container.dataset.mounted) return;
    container.dataset.mounted = '1';
    container.innerHTML = buildShell();
    bindEvents(container);
    loadConfig().then(function () {
      renderTab(container);
      loadSettings();
    });
  }

  function buildShell() {
    return '<div class="yvs-studio" id="yvs-root">' +
      '<nav class="yvs-tabs">' +
        tabBtn('generator', 'Generator') +
        tabBtn('canvas', 'Canvas') +
        tabBtn('templates', 'Templates') +
        tabBtn('advanced', 'Advanced') +
        tabBtn('gallery', 'Gallery') +
        tabBtn('history', 'History') +
        tabBtn('storyboard', 'Storyboard') +
        tabBtn('settings', 'Settings') +
      '</nav>' +
      '<div class="yvs-workspace" id="yvs-workspace"></div>' +
      '<aside class="yvs-controls" id="yvs-controls"></aside>' +
    '</div>';
  }

  function tabBtn(id, label) {
    return '<button class="yvs-tab' + (id === 'generator' ? ' is-active' : '') + '" data-yvs-tab="' + id + '" type="button">' + label + '</button>';
  }

  function bindEvents(root) {
    root.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-yvs-tab]');
      if (tab) { state.tab = tab.dataset.yvsTab; setActiveTab(root); renderTab(root); return; }

      var tpl = e.target.closest('[data-yvs-template]');
      if (tpl) { applyTemplate(tpl.dataset.yvsTemplate, root); return; }

      var reuse = e.target.closest('[data-yvs-reuse]');
      if (reuse) { reusePrompt(reuse.dataset.yvsReuse, reuse.dataset.yvsSource || 'history', root); return; }

      var preset = e.target.closest('[data-yvs-preset]');
      if (preset) { applyPreset(preset.dataset.yvsPreset); return; }

      if (e.target.closest('#yvs-generate')) { doGenerate(root); return; }
      if (e.target.closest('#yvs-storyboard-generate')) { doStoryboardGenerate(root); return; }
      if (e.target.closest('#yvs-save-settings')) { saveSettings(root); return; }

      var action = e.target.closest('[data-yvs-action]');
      if (action) { handleResultAction(action.dataset.yvsAction, root); return; }
    });

    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-yvs-setting]')) {
        state.settings[e.target.dataset.yvsSetting] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        updateCanvasRatio(root);
        refreshCreditsEstimate();
      }
    });
  }

  function setActiveTab(root) {
    root.querySelectorAll('.yvs-tab').forEach(function (b) {
      b.classList.toggle('is-active', b.dataset.yvsTab === state.tab);
    });
  }

  function loadConfig() {
    return Promise.all([
      Core.video.config(),
      Core.video.settings().catch(function () { return { data: { settings: {} } }; }),
      Core.video.providers(),
      Core.video.credits().catch(function () { return { data: {} }; })
    ]).then(function (res) {
      state.providers = (res[0].data && res[0].data.providers) || [];
      state.settings = (res[1].data && res[1].data.settings) || {};
      state.credits = Object.assign(state.credits, res[3].data || {});
      refreshCreditsEstimate();
    });
  }

  function refreshCreditsEstimate() {
    Core.video.estimate(state.settings).then(function (res) {
      state.credits = Object.assign(state.credits, res.data || {});
      var bar = document.querySelector('.yvs-credits-bar');
      if (bar) bar.textContent = creditLabel();
    }).catch(function () {});
  }

  function loadSettings() {
    Core.video.settings().then(function (res) {
      state.settings = (res.data && res.data.settings) || state.settings;
    });
  }

  function renderTab(root) {
    var ws = $('#yvs-workspace', root);
    var ctrl = $('#yvs-controls', root);
    if (!ws) return;

    switch (state.tab) {
      case 'generator': renderGenerator(ws, ctrl, root); break;
      case 'canvas': renderCanvas(ws, ctrl); break;
      case 'templates': renderTemplates(ws, ctrl); break;
      case 'advanced': renderAdvanced(ws, ctrl); break;
      case 'gallery': renderGallery(ws, ctrl); break;
      case 'history': renderHistory(ws, ctrl); break;
      case 'storyboard': renderStoryboard(ws, ctrl); break;
      case 'settings': renderSettings(ws, ctrl); break;
    }
  }

  function renderGenerator(ws, ctrl, root) {
    ws.innerHTML =
      '<div class="yvs-header"><h2>AI Video Generator</h2><span class="yvs-badge">Runway · Topview · Mock</span></div>' +
      '<div class="yvs-canvas-area" id="yvs-preview" data-ratio="' + esc(state.settings.aspect_ratio || '16:9') + '">' +
        previewContent() +
      '</div>' +
      '<div class="yvs-prompt-bar">' +
        '<textarea id="yvs-prompt" placeholder="한국 화장품 광고, 스마트스토어 제품 영상, 유튜브 쇼츠 등 영상 프롬프트를 입력하세요.">' + esc(state.settings.last_prompt || '') + '</textarea>' +
        '<button class="yvs-btn-primary" id="yvs-generate" type="button"' + (state.generating ? ' disabled' : '') + '>' + (state.generating ? 'Generating...' : 'Generate') + '</button>' +
      '</div>' +
      resultActionsHtml();
    ctrl.innerHTML = controlsPanel();
    updateCanvasRatio(root);
  }

  function creditLabel() {
    if (state.credits.unlimited) return 'Credits: ∞';
    var est = state.credits.estimate || 0;
    var bal = state.credits.balance ?? 0;
    return est + ' credits (잔액 ' + bal + ')';
  }

  function resultActionsHtml() {
    if (!state.lastResult || !state.lastResult.job_id) return '';
    return '<div class="yvs-result-actions">' +
      '<span>Result Actions</span>' +
      '<button class="yvs-btn-secondary" type="button" data-yvs-action="download">다운로드</button>' +
      '<button class="yvs-btn-secondary" type="button" data-yvs-action="copy">프롬프트 복사</button>' +
      '<button class="yvs-btn-secondary" type="button" data-yvs-action="reuse">재생성</button>' +
      '<button class="yvs-btn-secondary" type="button" data-yvs-action="public">공개</button>' +
      '<button class="yvs-btn-secondary" type="button" data-yvs-action="marketplace">Marketplace</button>' +
      '</div>';
  }

  function handleResultAction(action, root) {
    var galleryId = state.activeGalleryId || (state.lastResult && state.lastResult.job_id);
    if (!galleryId || !Core.gallery) return;

    var map = {
      download: function () { return Core.gallery.download(galleryId); },
      copy: function () { return Core.gallery.copy(galleryId); },
      reuse: function () { return Core.gallery.regenerate(galleryId); },
      public: function () { return Core.gallery.visibility(galleryId, true); },
      marketplace: function () { return Core.gallery.marketplace(galleryId); }
    };

    var fn = map[action];
    if (!fn) return;
    fn().then(function (res) {
      if (action === 'download') {
        var info = res.data || {};
        if (info.url) { var a = document.createElement('a'); a.href = info.url; a.download = info.filename || 'video'; a.target = '_blank'; a.click(); }
      }
      if (action === 'copy') {
        var prompt = (res.data && res.data.prompt) || '';
        if (navigator.clipboard) navigator.clipboard.writeText(prompt);
      }
      if (action === 'reuse') {
        var regen = res.data || {};
        if (regen.prompt) state.settings.last_prompt = regen.prompt;
        Object.assign(state.settings, regen);
        doGenerate(root);
      }
      notifyGalleryUpdated();
    }).catch(function (err) { alert(err.message); });
  }

  function notifyGalleryUpdated() {
    if (window.YooYGallery && typeof window.YooYGallery.reload === 'function') {
      window.YooYGallery.reload();
    }
  }

  function previewContent() {
    if (state.generating) {
      return '<div class="yvs-canvas-placeholder"><strong>Generating...</strong><div class="yvs-progress"><div class="yvs-progress-bar" style="width:60%"></div></div></div>';
    }
    if (state.lastResult && state.lastResult.output) {
      var out = state.lastResult.output;
      var url = out.primary || out.url || out.video_url || out.thumbnail;
      var mime = out.mime || '';
      if (mime.indexOf('video') === 0 || (url && url.indexOf('data:video') === 0)) {
        return '<video class="yvs-preview-video" src="' + esc(url) + '" controls autoplay muted></video>';
      }
      return '<img class="yvs-preview-img" src="' + esc(url) + '" alt="preview">';
    }
    return '<div class="yvs-canvas-placeholder"><strong>Video Preview</strong>프롬프트를 입력하고 Generate를 클릭하세요.</div>';
  }

  function controlsPanel() {
    var ratios = ['16:9', '9:16', '1:1', '4:5'];
    var durations = [3, 5, 10, 15, 30];
    var qualities = ['draft', 'standard', 'pro'];
    var motions = ['static', 'pan_left', 'pan_right', 'zoom_in', 'zoom_out', 'dolly_in', 'orbit'];
    var providerOpts = state.providers.map(function (p) {
      var sel = (state.settings.default_provider || 'mock') === p.id ? ' selected' : '';
      return '<option value="' + esc(p.id) + '"' + sel + '>' + esc(p.name) + '</option>';
    }).join('');

    return '<h3>Video Settings</h3>' +
      field('Provider', '<select data-yvs-setting="default_provider">' + providerOpts + '</select>') +
      field('Aspect Ratio', '<select data-yvs-setting="aspect_ratio">' + ratios.map(function (r) {
        return '<option value="' + r + '"' + ((state.settings.aspect_ratio || '16:9') === r ? ' selected' : '') + '>' + r + '</option>';
      }).join('') + '</select>') +
      field('Duration', '<select data-yvs-setting="duration">' + durations.map(function (d) {
        return '<option value="' + d + '"' + ((state.settings.duration || 5) == d ? ' selected' : '') + '>' + d + 's</option>';
      }).join('') + '</select>') +
      field('Quality', '<select data-yvs-setting="quality">' + qualities.map(function (q) {
        return '<option value="' + q + '"' + ((state.settings.quality || 'standard') === q ? ' selected' : '') + '>' + q + '</option>';
      }).join('') + '</select>') +
      field('Camera', '<select data-yvs-setting="camera_motion">' + motions.map(function (m) {
        return '<option value="' + m + '"' + ((state.settings.camera_motion || 'static') === m ? ' selected' : '') + '>' + m + '</option>';
      }).join('') + '</select>') +
      toggle('Korean Context', 'korean_context', state.settings.korean_context !== false) +
      toggle('Auto Save', 'auto_save', state.settings.auto_save !== false) +
      toggle('Subtitle Space', 'subtitle_space', state.settings.subtitle_space !== false) +
      '<div class="yvs-credits-bar">' + creditLabel() + '</div>';
  }

  function field(label, input) {
    return '<div class="yvs-field"><label>' + label + '</label>' + input + '</div>';
  }

  function toggle(label, key, on) {
    return '<div class="yvs-toggle"><span>' + label + '</span><button class="yvs-switch' + (on ? ' is-on' : '') + '" data-yvs-setting="' + key + '" type="button" aria-pressed="' + on + '"></button></div>';
  }

  function updateCanvasRatio(root) {
    var preview = $('#yvs-preview', root);
    if (preview) preview.dataset.ratio = state.settings.aspect_ratio || '16:9';
    root.querySelectorAll('.yvs-switch').forEach(function (sw) {
      var key = sw.dataset.yvsSetting;
      sw.classList.toggle('is-on', !!state.settings[key]);
      sw.addEventListener('click', function () {
        state.settings[key] = !state.settings[key];
        sw.classList.toggle('is-on', state.settings[key]);
      });
    });
  }

  function doGenerate(root) {
    var prompt = ($('#yvs-prompt', root) || {}).value || '';
    if (!prompt.trim()) return;

    state.generating = true;
    state.settings.last_prompt = prompt;
    state.settings.prompt = prompt;
    renderTab(root);

    Core.video.generate(Object.assign({}, state.settings, { prompt: prompt })).then(function (res) {
      finalizeJob(res.data || res, root);
    }).catch(function (err) {
      state.generating = false;
      var ws = $('#yvs-workspace', root);
      if (ws) ws.insertAdjacentHTML('beforeend', '<div class="yvs-error">' + esc(err.message) + '</div>');
      renderTab(root);
    });
  }

  function finalizeJob(data, root) {
    if (data.status === 'queued' || data.status === 'running') {
      return pollUntilDone(data, root);
    }
    state.lastResult = data;
    state.generating = false;
    state.activeGalleryId = data.job_id || '';
    if (data.credits) state.credits.balance = data.credits.balance;
    notifyGalleryUpdated();
    renderTab(root);
    return data;
  }

  function pollUntilDone(data, root) {
    var provider = data.provider || state.settings.default_provider || 'mock';
    var jobId = data.job_id;
    var attempts = 0;

    function tick() {
      attempts += 1;
      return Core.video.pollJob(jobId, provider).then(function (res) {
        var job = (res.data && res.data.job) || res.data || res;
        if ((job.status === 'queued' || job.status === 'running') && attempts < 15) {
          return new Promise(function (r) { setTimeout(r, 1000); }).then(tick);
        }
        return finalizeJob(job, root);
      });
    }
    return tick();
  }

  function renderCanvas(ws, ctrl) {
    ws.innerHTML = '<div class="yvs-loading">Loading canvas...</div>';
    Core.video.canvas().then(function (res) {
      state.canvas = (res.data && res.data.canvas) || {};
      ws.innerHTML =
        '<div class="yvs-header"><h2>Canvas</h2><span class="yvs-badge">' + esc(state.canvas.title || 'Canvas') + '</span></div>' +
        '<div class="yvs-canvas-area" data-ratio="' + esc(state.canvas.aspect_ratio || '16:9') + '">' +
          '<div class="yvs-canvas-placeholder"><strong>' + esc(state.canvas.title) + '</strong>' + state.canvas.duration + 's · ' + esc(state.canvas.aspect_ratio) + '</div>' +
        '</div>' +
        '<div class="yvs-panel"><h3>Scenes</h3><div class="yvs-scene-list">' +
          (state.canvas.scenes || []).map(function (s) {
            return '<div class="yvs-scene"><span class="yvs-scene-time">' + s.start + 's</span><span>' + esc(s.label) + ': ' + esc(s.prompt || '(empty)') + '</span><span>' + s.duration + 's</span></div>';
          }).join('') +
        '</div></div>';
      ctrl.innerHTML = '<h3>Layers</h3>' + (state.canvas.layers || []).map(function (l) {
        return '<div class="yvs-layer"><span class="yvs-layer-dot' + (l.visible ? ' visible' : '') + '"></span>' + esc(l.type) + (l.content ? ': ' + esc(l.content) : '') + '</div>';
      }).join('');
    });
  }

  function renderTemplates(ws, ctrl) {
    ws.innerHTML = '<div class="yvs-loading">Loading templates...</div>';
    Core.video.templates().then(function (res) {
      var cats = (res.data && res.data.categories) || [];
      var tpls = (res.data && res.data.templates) || [];
      ws.innerHTML =
        '<div class="yvs-header"><h2>Templates</h2><span class="yvs-badge">한국 최적화</span></div>' +
        '<div class="yvs-tags" style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">' +
          cats.map(function (c) { return '<span class="yvs-tag">' + esc(c.label) + '</span>'; }).join('') +
        '</div>' +
        '<div class="yvs-grid">' +
          tpls.map(function (t) {
            return '<div class="yvs-card" data-yvs-template="' + esc(t.id) + '"><strong>' + esc(t.title) + '</strong><span>' + esc(t.category) + ' · ' + t.duration + 's · ' + esc(t.aspect_ratio) + '</span><div class="yvs-tags">' +
              (t.tags || []).map(function (tag) { return '<span class="yvs-tag">' + esc(tag) + '</span>'; }).join('') +
            '</div></div>';
          }).join('') +
        '</div>';
      ctrl.innerHTML = '<h3>Template Guide</h3><p style="color:#888;font-size:13px">템플릿을 클릭하면 프롬프트, 스토리보드, Provider 설정이 자동 적용됩니다.</p>';
    });
  }

  function applyTemplate(id, root) {
    Core.video.applyTemplate(id).then(function (res) {
      var applied = (res.data && res.data.applied) || {};
      Object.assign(state.settings, applied);
      state.settings.last_prompt = applied.prompt || '';
      state.tab = 'generator';
      setActiveTab(root);
      renderTab(root);
    });
  }

  function renderAdvanced(ws, ctrl) {
    ws.innerHTML = '<div class="yvs-loading">Loading...</div>';
    Core.video.advanced().then(function (res) {
      state.advanced = res.data || {};
      var presets = state.advanced.presets || [];
      ws.innerHTML =
        '<div class="yvs-header"><h2>Advanced</h2></div>' +
        '<div class="yvs-grid">' +
          presets.map(function (p) {
            return '<div class="yvs-card" data-yvs-preset="' + esc(p.id) + '"><strong>' + esc(p.label) + '</strong><span>Preset</span></div>';
          }).join('') +
        '</div>' +
        '<div class="yvs-panel" style="margin-top:16px"><h3>Parameters</h3>' +
          Object.entries(state.advanced.options || {}).map(function (entry) {
            var k = entry[0], v = entry[1];
            return '<div class="yvs-field"><label>' + esc(v.label) + '</label><span style="color:#666;font-size:12px">' + esc(v.type) + ' · default: ' + esc(String(v.default)) + '</span></div>';
          }).join('') +
        '</div>';
      ctrl.innerHTML = '<h3>Advanced Tips</h3><p style="color:#888;font-size:13px">Guidance Scale과 Motion Strength를 조절하여 영상 스타일을 미세 조정하세요.</p>';
    });
  }

  function applyPreset(id) {
    var preset = (state.advanced.presets || []).find(function (p) { return p.id === id; });
    if (preset) Object.assign(state.settings, preset.values || {});
  }

  function renderGallery(ws, ctrl) {
    ws.innerHTML = '<div class="yvs-loading">Loading gallery...</div>';
    Core.video.gallery().then(function (res) {
      var items = (res.data && res.data.items) || [];
      if (!items.length) {
        ws.innerHTML = '<div class="yvs-empty">갤러리가 비어 있습니다. 영상 생성 시 Auto Save를 켜두세요.</div>';
        return;
      }
      ws.innerHTML = '<div class="yvs-header"><h2>Gallery</h2><span class="yvs-badge">' + items.length + ' videos</span></div>' +
        items.map(function (item) {
          var thumb = item.thumbnail || item.url || item.output_url || '';
          var ratio = item.aspect_ratio || (item.meta && item.meta.aspect_ratio) || '';
          return '<div class="yvs-gallery-item" data-yvs-reuse="' + esc(item.id) + '" data-yvs-source="gallery">' +
            '<img class="yvs-thumb" src="' + esc(thumb) + '" alt="">' +
            '<div class="yvs-meta"><strong>' + esc(item.title) + '</strong><span>' + esc(item.provider) + (ratio ? ' · ' + esc(ratio) : '') + '</span></div></div>';
        }).join('');
      ctrl.innerHTML = '<h3>Prompt Reuse</h3><p style="color:#888;font-size:13px">항목을 클릭하면 프롬프트와 설정을 Generator에 불러옵니다.</p>';
    });
  }

  function renderHistory(ws, ctrl) {
    ws.innerHTML = '<div class="yvs-loading">Loading history...</div>';
    Core.video.history().then(function (res) {
      var items = (res.data && res.data.history) || [];
      if (!items.length) {
        ws.innerHTML = '<div class="yvs-empty">생성 히스토리가 없습니다.</div>';
        return;
      }
      ws.innerHTML = '<div class="yvs-header"><h2>History</h2><span class="yvs-badge">' + items.length + ' jobs</span></div>' +
        items.map(function (item) {
          var id = item.id || item.job_id;
          var thumb = (item.output && (item.output.thumbnail || item.output.url)) || '';
          return '<div class="yvs-history-item" data-yvs-reuse="' + esc(id) + '" data-yvs-source="history">' +
            (thumb ? '<img class="yvs-thumb" src="' + esc(thumb) + '" alt="">' : '<div class="yvs-thumb"></div>') +
            '<div class="yvs-meta"><strong>' + esc((item.prompt || '').substring(0, 60)) + '</strong><span>' + esc(item.provider) + ' · ' + esc(item.status || 'done') + '</span></div></div>';
        }).join('');
      ctrl.innerHTML = '<h3>Prompt Reuse</h3><p style="color:#888;font-size:13px">히스토리에서 프롬프트를 재사용하거나 리믹스할 수 있습니다.</p>';
    });
  }

  function reusePrompt(id, source, root) {
    Core.video.promptReuse({ source_type: source, source_id: id }).then(function (res) {
      var reuse = (res.data && res.data.reuse) || {};
      Object.assign(state.settings, reuse);
      state.settings.last_prompt = reuse.prompt || '';
      state.tab = 'generator';
      setActiveTab(root);
      renderTab(root);
    });
  }

  function renderStoryboard(ws, ctrl) {
    ws.innerHTML = '<div class="yvs-loading">Loading storyboard...</div>';
    Core.video.storyboard().then(function (res) {
      state.storyboard = (res.data && res.data.storyboard) || {};
      ws.innerHTML =
        '<div class="yvs-header"><h2>Storyboard</h2><span class="yvs-badge">' + state.storyboard.total_duration + 's</span></div>' +
        '<div class="yvs-timeline">' +
          (state.storyboard.frames || []).map(function (f) {
            return '<div class="yvs-frame"><strong>' + esc(f.shot) + '</strong>' + esc(f.prompt) + '<br><span>' + f.duration + 's · ' + esc(f.camera) + '</span></div>';
          }).join('') +
        '</div>' +
        '<button class="yvs-btn-primary" id="yvs-storyboard-generate" type="button" style="margin-top:16px">Generate from Storyboard</button>';
      ctrl.innerHTML = '<h3>Frames</h3><p style="color:#888;font-size:13px">각 프레임의 프롬프트가 순서대로 결합되어 영상이 생성됩니다.</p>';
    });
  }

  function doStoryboardGenerate(root) {
    state.generating = true;
    state.tab = 'generator';
    setActiveTab(root);
    renderTab(root);
    Core.video.generateStoryboard(state.settings).then(function (res) {
      finalizeJob(res.data || res, root);
    }).catch(function (err) {
      state.generating = false;
      var ws = $('#yvs-workspace', root);
      if (ws) ws.insertAdjacentHTML('beforeend', '<div class="yvs-error">' + esc(err.message || 'Generation failed') + '</div>');
      renderTab(root);
    });
  }

  function renderSettings(ws, ctrl) {
    ws.innerHTML =
      '<div class="yvs-header"><h2>Video Settings</h2></div>' +
      '<div class="yvs-panel">' + controlsPanel() + '</div>' +
      '<button class="yvs-btn-primary" id="yvs-save-settings" type="button" style="margin-top:16px">Save Settings</button>';
    ctrl.innerHTML = '<h3>API Router</h3>' +
      (state.providers || []).map(function (p) {
        return '<div class="yvs-field"><label>' + esc(p.name) + '</label><span style="color:#666;font-size:12px">' + (p.models || []).length + ' models</span></div>';
      }).join('');
  }

  function saveSettings(root) {
    Core.video.updateSettings(state.settings).then(function () {
      var ws = $('#yvs-workspace', root);
      if (ws) ws.insertAdjacentHTML('afterbegin', '<div class="yvs-badge" style="margin-bottom:12px">Settings saved</div>');
    });
  }

  window.YooYVideoStudio = { mount: mount, state: state };
})();
