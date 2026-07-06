(function () {
  'use strict';

  var Core = window.YooYCore;
  if (!Core || !Core.avatar) return;

  var state = {
    tab: 'create',
    settings: {},
    options: {},
    providers: [],
    generating: false,
    lastResult: null
  };

  function $(s, c) { return (c || document).querySelector(s); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function mount(container) {
    if (!container || container.dataset.mounted) return;
    container.dataset.mounted = '1';
    container.innerHTML = '<div class="yas-studio" id="yas-root">' +
      '<nav class="yas-tabs">' +
        tab('create', 'Create') + tab('scene', 'Scene') + tab('gallery', 'Gallery') +
        tab('history', 'History') + tab('settings', 'Settings') +
      '</nav>' +
      '<div class="yas-workspace" id="yas-workspace"></div>' +
      '<aside class="yas-controls" id="yas-controls"></aside></div>';
    bindEvents(container);
    Promise.all([
      Core.avatar.config(),
      Core.avatar.settings().catch(function () { return { data: { settings: {} } }; })
    ]).then(function (res) {
      state.options = (res[0].data && res[0].data.options) || {};
      state.providers = (res[0].data && res[0].data.providers) || [];
      state.settings = (res[1].data && res[1].data.settings) || {};
      renderTab(container);
    });
  }

  function tab(id, label) {
    return '<button class="yas-tab' + (id === 'create' ? ' is-active' : '') + '" data-yas-tab="' + id + '" type="button">' + label + '</button>';
  }

  function bindEvents(root) {
    root.addEventListener('click', function (e) {
      var t = e.target.closest('[data-yas-tab]');
      if (t) { state.tab = t.dataset.yasTab; setTab(root); renderTab(root); return; }

      var av = e.target.closest('[data-yas-avatar]');
      if (av) { state.settings.avatar_id = av.dataset.yasAvatar; renderTab(root); return; }

      var sc = e.target.closest('[data-yas-scene]');
      if (sc) { applyScene(sc.dataset.yasScene, root); return; }

      if (e.target.closest('#yas-generate')) { doGenerate(root); return; }
      if (e.target.closest('#yas-save')) { saveSettings(root); return; }

      var reuse = e.target.closest('[data-yas-reuse]');
      if (reuse) { reuseItem(reuse.dataset.yasReuse, reuse.dataset.yasSource || 'history', root); return; }

      var sw = e.target.closest('.yas-switch');
      if (sw) {
        var key = sw.dataset.yasToggle;
        state.settings[key] = !state.settings[key];
        sw.classList.toggle('is-on', state.settings[key]);
      }
    });

    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-yas-setting]')) {
        state.settings[e.target.dataset.yasSetting] = e.target.value;
      }
    });

    root.addEventListener('input', function (e) {
      if (e.target.id === 'yas-script') {
        state.settings.script = e.target.value;
        debounceSubtitle(root);
      }
    });
  }

  var subtitleTimer;
  function debounceSubtitle(root) {
    clearTimeout(subtitleTimer);
    subtitleTimer = setTimeout(function () {
      Core.avatar.subtitlePreview(state.settings).then(function (res) {
        var el = $('#yas-subtitle-preview', root);
        if (!el) return;
        var sub = (res.data && res.data.subtitle) || {};
        if (!sub.enabled || !sub.tracks) { el.innerHTML = ''; return; }
        el.innerHTML = sub.tracks.map(function (t) {
          return '<div>[' + t.start + 's] ' + esc(t.text) + '</div>';
        }).join('');
      }).catch(function () {});
    }, 500);
  }

  function setTab(root) {
    root.querySelectorAll('.yas-tab').forEach(function (b) {
      b.classList.toggle('is-active', b.dataset.yasTab === state.tab);
    });
  }

  function renderTab(root) {
    var ws = $('#yas-workspace', root);
    var ctrl = $('#yas-controls', root);
    if (!ws) return;
    switch (state.tab) {
      case 'create': renderCreate(ws, ctrl, root); break;
      case 'scene': renderScenes(ws, ctrl); break;
      case 'gallery': renderGallery(ws, ctrl); break;
      case 'history': renderHistory(ws, ctrl); break;
      case 'settings': renderSettings(ws, ctrl); break;
    }
  }

  function renderCreate(ws, ctrl, root) {
    var avatars = state.options.avatars || [];
    ws.innerHTML =
      '<div class="yas-header"><h2>Avatar Studio</h2><span class="yas-badge">Vidu · HeyGen</span></div>' +
      '<div class="yas-preview" id="yas-preview">' + previewHtml() + '</div>' +
      '<h3 style="color:#d8a63a;font-size:13px;margin:0 0 8px">AVATAR</h3>' +
      '<div class="yas-avatar-grid">' + avatars.map(function (a) {
        return '<div class="yas-avatar-card' + (state.settings.avatar_id === a.id ? ' is-selected' : '') + '" data-yas-avatar="' + esc(a.id) + '">' +
          '<img src="' + esc(a.preview) + '" alt=""><span>' + esc(a.name) + '</span></div>';
      }).join('') + '</div>' +
      '<div class="yas-field"><label>Script</label><textarea id="yas-script" placeholder="아바타가 말할 대본을 입력하세요. 한국어 자막이 자동 생성됩니다.">' + esc(state.settings.script || '') + '</textarea></div>' +
      '<div class="yas-subtitle-preview" id="yas-subtitle-preview"></div>' +
      '<button class="yas-btn-primary" id="yas-generate" type="button" style="margin-top:16px"' + (state.generating ? ' disabled' : '') + '>' + (state.generating ? 'Generating...' : 'Generate Avatar Video') + '</button>';

    ctrl.innerHTML = controlsHtml();
    debounceSubtitle(root);
  }

  function previewHtml() {
    if (state.generating) return '<div class="yas-loading">Generating avatar video...</div>';
    if (state.lastResult && state.lastResult.output) {
      var out = state.lastResult.output;
      if (out.video_url) {
        if (out.video_url.indexOf('data:image') === 0) {
          return '<img src="' + esc(out.video_url) + '" alt="avatar preview">';
        }
        return '<video controls src="' + esc(out.video_url) + '"></video>';
      }
      return '<img src="' + esc(out.thumbnail || out.video_url) + '" alt="preview">';
    }
    var av = (state.options.avatars || []).find(function (a) { return a.id === state.settings.avatar_id; });
    return av ? '<img src="' + esc(av.preview) + '" alt=""><div style="position:absolute;bottom:12px;left:12px;background:rgba(0,0,0,.7);padding:6px 12px;border-radius:8px;font-size:12px">' + esc(av.name) + '</div>' : '<div class="yas-empty">아바타를 선택하세요</div>';
  }

  function controlsHtml() {
    function sel(key, label, items, valKey, labelKey) {
      return '<div class="yas-field"><label>' + label + '</label><select data-yas-setting="' + key + '">' +
        items.map(function (it) {
          var v = valKey ? it[valKey] : it;
          var l = labelKey ? it[labelKey] : it;
          return '<option value="' + esc(String(v)) + '"' + (String(state.settings[key]) === String(v) ? ' selected' : '') + '>' + esc(String(l)) + '</option>';
        }).join('') + '</select></div>';
    }

    var prov = state.providers.map(function (p) { return { id: p.id, label: p.name }; });

    return '<h3 style="color:#d8a63a;font-size:13px;margin:0">CONTROLS</h3>' +
      sel('default_provider', 'Provider', prov, 'id', 'label') +
      sel('voice_id', 'Voice', state.options.voices || [], 'id', 'name') +
      '<div class="yas-field-row">' +
        sel('expression', 'Expression', state.options.expressions || [], 'id', 'label') +
        sel('gesture', 'Gesture', state.options.gestures || [], 'id', 'label') +
      '</div>' +
      '<div class="yas-field-row">' +
        sel('camera', 'Camera', state.options.cameras || [], 'id', 'label') +
        sel('emotion', 'Emotion', state.options.emotions || [], 'id', 'label') +
      '</div>' +
      sel('background', 'Background', state.options.backgrounds || [], 'id', 'label') +
      toggle('lip_sync', 'Lip Sync', state.settings.lip_sync !== false) +
      toggle('subtitle_enabled', 'Subtitle', state.settings.subtitle_enabled !== false) +
      sel('duration', 'Duration', (state.options.durations || []).map(function (d) { return { id: d, label: d + 's' }; }), 'id', 'label');
  }

  function toggle(key, label, on) {
    return '<div class="yas-toggle"><span>' + label + '</span><button class="yas-switch' + (on ? ' is-on' : '') + '" data-yas-toggle="' + key + '" type="button"></button></div>';
  }

  function doGenerate(root) {
    if (!(state.settings.script || '').trim()) return;
    state.generating = true;
    renderTab(root);
    Core.avatar.generate(state.settings).then(function (res) {
      state.lastResult = res.data || res;
      state.generating = false;
      renderTab(root);
    }).catch(function (err) {
      state.generating = false;
      var ws = $('#yas-workspace', root);
      if (ws) ws.insertAdjacentHTML('beforeend', '<div class="yas-error">' + esc(err.message) + '</div>');
    });
  }

  function renderScenes(ws) {
    var scenes = state.options.scenes || [];
    ws.innerHTML = '<div class="yas-header"><h2>Scene Templates</h2><span class="yas-badge">한국 최적화</span></div>' +
      scenes.map(function (s) {
        return '<div class="yas-scene-card" data-yas-scene="' + esc(s.id) + '"><strong>' + esc(s.label) + '</strong><span>' + esc(s.template) + '</span></div>';
      }).join('');
  }

  function applyScene(id, root) {
    Core.avatar.promptReuse({ source_type: 'scene', source_id: id }).then(function (res) {
      Object.assign(state.settings, (res.data && res.data.reuse) || {});
      state.tab = 'create';
      setTab(root);
      renderTab(root);
    });
  }

  function renderGallery(ws) {
    ws.innerHTML = '<div class="yas-loading">Loading...</div>';
    Core.avatar.gallery().then(function (res) {
      var items = (res.data && res.data.items) || [];
      if (!items.length) { ws.innerHTML = '<div class="yas-empty">갤러리가 비어 있습니다.</div>'; return; }
      ws.innerHTML = '<div class="yas-header"><h2>Gallery</h2></div>' + items.map(function (item) {
        return '<div class="yas-track" data-yas-reuse="' + esc(item.id) + '" data-yas-source="gallery">' +
          '<img src="' + esc(item.thumbnail || '') + '" alt=""><div class="yas-track-meta"><strong>' + esc(item.title) + '</strong><span>' + esc(item.scene_id) + '</span></div></div>';
      }).join('');
    });
  }

  function renderHistory(ws) {
    ws.innerHTML = '<div class="yas-loading">Loading...</div>';
    Core.avatar.history().then(function (res) {
      var items = (res.data && res.data.history) || [];
      if (!items.length) { ws.innerHTML = '<div class="yas-empty">히스토리가 없습니다.</div>'; return; }
      ws.innerHTML = '<div class="yas-header"><h2>Prompt History</h2></div>' + items.map(function (item) {
        var id = item.id || item.job_id;
        return '<div class="yas-track" data-yas-reuse="' + esc(id) + '" data-yas-source="history">' +
          '<img src="' + esc((item.output && item.output.thumbnail) || '') + '" alt=""><div class="yas-track-meta"><strong>' + esc((item.script || '').substring(0, 40)) + '</strong><span>' + esc(item.provider) + '</span></div></div>';
      }).join('');
    });
  }

  function reuseItem(id, source, root) {
    Core.avatar.promptReuse({ source_type: source, source_id: id }).then(function (res) {
      Object.assign(state.settings, (res.data && res.data.reuse) || {});
      state.tab = 'create';
      setTab(root);
      renderTab(root);
    });
  }

  function renderSettings(ws, ctrl) {
    ws.innerHTML = '<div class="yas-header"><h2>Settings</h2></div>' + controlsHtml() +
      '<button class="yas-btn-primary" id="yas-save" type="button" style="margin-top:16px">Save</button>';
    ctrl.innerHTML = '<h3 style="color:#d8a63a;font-size:13px">API Router</h3>' +
      state.providers.map(function (p) {
        return '<div class="yas-field"><label>' + esc(p.name) + '</label><span style="color:#666;font-size:12px">' + (p.models || []).length + ' models</span></div>';
      }).join('');
  }

  function saveSettings(root) {
    Core.avatar.updateSettings(state.settings).then(function () { renderTab(root); });
  }

  window.YooYAvatarStudio = { mount: mount, state: state };
})();
