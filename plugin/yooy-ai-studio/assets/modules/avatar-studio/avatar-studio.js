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
      applyIncomingHandoff(container);
      renderTab(container);
    });
  }

  function applyIncomingHandoff(container) {
    if (!window.YooYStudioHandoff || typeof window.YooYStudioHandoff.apply !== 'function') return;
    window.YooYStudioHandoff.apply('avatar', container, function (ctx) {
      if (ctx.prompt) state.settings.script = ctx.prompt;
      if (window.YooYStudioHandoff.consumePromptKeys) window.YooYStudioHandoff.consumePromptKeys();
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
    var SM = window.YooYStudioSimpleMode;
    ws.innerHTML =
      '<div class="yas-header">' +
        (window.YooYNavigation ? window.YooYNavigation.headerActionsHtml('avatar') : '') +
        (SM ? SM.headerHtml('Avatar Studio', '캐릭터가 말하는 영상을 만들어보세요.') : '<h2>Avatar Studio</h2><p>캐릭터가 말하는 영상을 만들어보세요.</p>') +
      '</div>' +
      '<div class="yas-preview" id="yas-preview">' + previewHtml() + '</div>' +
      '<div class="yas-field"><label>참고 인물 / 캐릭터</label></div>' +
      '<div class="yas-avatar-grid">' + avatars.map(function (a) {
        return '<div class="yas-avatar-card' + (state.settings.avatar_id === a.id ? ' is-selected' : '') + '" data-yas-avatar="' + esc(a.id) + '">' +
          '<img src="' + esc(a.preview) + '" alt=""><span>' + esc(a.name) + '</span></div>';
      }).join('') + '</div>' +
      '<div class="yas-field"><label for="yas-script">원하는 아바타 설명 / 대본</label><textarea id="yas-script" placeholder="아바타가 말할 내용을 적어 주세요.">' + esc(state.settings.script || '') + '</textarea></div>' +
      '<p class="yas-field-error" id="yas-script-error" hidden>대본을 입력해 주세요.</p>' +
      '<div class="yas-subtitle-preview" id="yas-subtitle-preview"></div>' +
      '<button class="yas-btn-primary yai-btn-gold-primary" id="yas-generate" type="button" style="margin-top:16px"' + (state.generating ? ' disabled' : '') + '>' +
        (state.generating ? '아바타 만드는 중…' : '아바타 만들기') +
      '</button>';

    ctrl.innerHTML = controlsHtml();
    if (SM) SM.bind(ctrl);
    debounceSubtitle(root);
  }

  function previewHtml() {
    if (state.generating) return '<div class="yas-loading">아바타 만드는 중…</div>';
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

    var SM = window.YooYStudioSimpleMode;
    var prov = state.providers.map(function (p) {
      return { id: p.id, label: SM ? SM.providerOptionLabel(p.id, p.name) : p.name };
    });

    var essential =
      '<h3 style="color:#d8a63a;font-size:13px;margin:0">기본 설정</h3>' +
      sel('expression', '스타일', state.options.expressions || [], 'id', 'label') +
      sel('duration', '길이', (state.options.durations || []).map(function (d) { return { id: d, label: d + '초' }; }), 'id', 'label') +
      sel('background', '배경', state.options.backgrounds || [], 'id', 'label') +
      toggle('subtitle_enabled', '자막', state.settings.subtitle_enabled !== false);

    var advancedInner =
      sel('default_provider', '엔진', prov, 'id', 'label') +
      sel('voice_id', '목소리', state.options.voices || [], 'id', 'name') +
      sel('gesture', '제스처', state.options.gestures || [], 'id', 'label') +
      sel('camera', '카메라', state.options.cameras || [], 'id', 'label') +
      sel('emotion', '감정', state.options.emotions || [], 'id', 'label') +
      toggle('lip_sync', '립싱크', state.settings.lip_sync !== false);

    if (SM) return essential + SM.detailsHtml('avatar', advancedInner);
    return essential + '<details class="yai-studio-adv" data-studio-adv="avatar"><summary class="yai-studio-adv__summary">고급 설정 ▾</summary><div class="yai-studio-adv__body">' + advancedInner + '</div></details>';
  }

  function toggle(key, label, on) {
    return '<div class="yas-toggle"><span>' + label + '</span><button class="yas-switch' + (on ? ' is-on' : '') + '" data-yas-toggle="' + key + '" type="button"></button></div>';
  }

  function doGenerate(root) {
    var errEl = $('#yas-script-error', root);
    if (!(state.settings.script || '').trim()) {
      var ta = $('#yas-script', root);
      if (ta) state.settings.script = ta.value || '';
    }
    if (!(state.settings.script || '').trim()) {
      if (errEl) errEl.hidden = false;
      return;
    }
    if (errEl) errEl.hidden = true;
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
