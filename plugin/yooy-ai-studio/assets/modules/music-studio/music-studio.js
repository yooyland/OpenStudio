(function () {
  'use strict';

  var Core = window.YooYCore;
  if (!Core || !Core.music) return;

  var state = {
    tab: 'create',
    mode: 'custom',
    settings: {},
    schema: {},
    providers: [],
    structures: [],
    credits: { balance: 0, unlimited: false, estimate: 0, can_afford: true },
    generating: false,
    lastResult: null,
    activeGalleryId: null,
    referenceUrl: '',
    refPanel: null
  };

  function $(s, c) { return (c || document).querySelector(s); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function mount(container) {
    if (!container || container.dataset.mounted) return;
    container.dataset.mounted = '1';
    container.innerHTML = '<div class="yms-studio" id="yms-root">' +
      '<nav class="yms-tabs">' +
        tab('create', 'Create') + tab('gallery', 'Gallery') + tab('history', 'History') +
        tab('advanced', 'Advanced') + tab('settings', 'Settings') +
      '</nav>' +
      '<div class="yms-workspace" id="yms-workspace"></div>' +
      '<aside class="yms-controls" id="yms-controls"></aside></div>';
    bindEvents(container);
    Promise.all([
      Core.music.config(),
      Core.music.settings().catch(function () { return { data: { settings: {} } }; }),
      Core.music.credits().catch(function () { return { data: { balance: 0 } }; }),
      Core.music.structures()
    ]).then(function (res) {
      state.schema = (res[0].data && res[0].data.schema) || {};
      state.providers = (res[0].data && res[0].data.providers) || [];
      state.settings = (res[1].data && res[1].data.settings) || {};
      state.mode = state.settings.mode || 'custom';
      state.credits = Object.assign(state.credits, res[2].data || {});
      state.structures = (res[3].data && res[3].data.templates) || [];
      refreshCreditsEstimate();
      renderTab(container);
    });
  }

  function refreshCreditsEstimate() {
    Core.music.estimate(state.settings).then(function (res) {
      state.credits = Object.assign(state.credits, res.data || {});
    }).catch(function () {});
  }

  function tab(id, label) {
    return '<button class="yms-tab' + (id === 'create' ? ' is-active' : '') + '" data-yms-tab="' + id + '" type="button">' + label + '</button>';
  }

  function bindEvents(root) {
    root.addEventListener('click', function (e) {
      var t = e.target.closest('[data-yms-tab]');
      if (t) { state.tab = t.dataset.ymsTab; setTab(root); renderTab(root); return; }

      var mode = e.target.closest('[data-yms-mode]');
      if (mode) { state.mode = mode.dataset.ymsMode; state.settings.mode = state.mode; renderTab(root); return; }

      var st = e.target.closest('[data-yms-structure]');
      if (st) { loadSkeleton(st.dataset.ymsStructure, root); return; }

      if (e.target.closest('#yms-generate')) { doGenerate(root); return; }
      if (e.target.closest('#yms-save-settings')) { saveSettings(root); return; }

      var action = e.target.closest('[data-yms-action]');
      if (action) { handleResultAction(action.dataset.ymsAction, root); return; }

      var reuse = e.target.closest('[data-yms-reuse]');
      if (reuse) { reusePrompt(reuse.dataset.ymsReuse, reuse.dataset.ymsSource || 'history', root); return; }
    });

    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-yms-setting]')) {
        state.settings[e.target.dataset.ymsSetting] = e.target.type === 'range' ? parseInt(e.target.value, 10) : e.target.value;
        if (['duration', 'audio_quality'].indexOf(e.target.dataset.ymsSetting) >= 0) refreshCreditsEstimate();
      }
    });

    root.addEventListener('input', function (e) {
      if (e.target.matches('[data-yms-setting]') && e.target.type === 'range') {
        state.settings[e.target.dataset.ymsSetting] = parseInt(e.target.value, 10);
        var val = e.target.parentElement.querySelector('.yms-range-val');
        if (val) val.textContent = e.target.value;
      }
    });
  }

  function setTab(root) {
    root.querySelectorAll('.yms-tab').forEach(function (b) {
      b.classList.toggle('is-active', b.dataset.ymsTab === state.tab);
    });
  }

  function renderTab(root) {
    var ws = $('#yms-workspace', root);
    var ctrl = $('#yms-controls', root);
    if (!ws) return;
    switch (state.tab) {
      case 'create': renderCreate(ws, ctrl, root); break;
      case 'gallery': renderGallery(ws, ctrl); break;
      case 'history': renderHistory(ws, ctrl); break;
      case 'advanced': renderAdvanced(ws, ctrl); break;
      case 'settings': renderSettings(ws, ctrl); break;
    }
  }

  function creditsBar() {
    var bal = state.credits.unlimited ? '∞' : (state.credits.balance ?? 0);
    var est = state.credits.estimate || state.estimate || 0;
    return '<div class="yms-credits-bar"><span>Cost: <strong>' + est + '</strong> credits</span><span>Balance: <strong>' + bal + '</strong></span></div>';
  }

  function resultActionsHtml() {
    if (!state.lastResult || !state.lastResult.job_id) return '';
    return '<div class="yms-result-actions">' +
      '<span>Result Actions</span>' +
      '<button class="yms-btn-secondary" type="button" data-yms-action="download">다운로드</button>' +
      '<button class="yms-btn-secondary" type="button" data-yms-action="copy">프롬프트 복사</button>' +
      '<button class="yms-btn-secondary" type="button" data-yms-action="reuse">재생성</button>' +
      '<button class="yms-btn-secondary" type="button" data-yms-action="publish">갤러리 공개</button>' +
      '<button class="yms-btn-secondary" type="button" data-yms-action="marketplace">Marketplace</button>' +
      '<button class="yms-btn-secondary" type="button" data-yms-action="project">Project 저장</button>' +
      '</div>';
  }

  function handleResultAction(action, root) {
    var galleryId = state.activeGalleryId || (state.lastResult && state.lastResult.job_id);
    if (!galleryId || !Core.gallery) return;

    var map = {
      download: function () { return Core.gallery.download(galleryId); },
      copy: function () { return Core.gallery.copy(galleryId); },
      reuse: function () { return Core.gallery.regenerate(galleryId); },
      publish: function () { return Core.gallery.publish(galleryId); },
      marketplace: function () { return Core.gallery.marketplace(galleryId); },
      project: function () { return Core.gallery.project(galleryId); }
    };

    var fn = map[action];
    if (!fn) return;
    fn().then(function (res) {
      if (action === 'download') {
        var info = res.data || {};
        if (info.url) { var a = document.createElement('a'); a.href = info.url; a.download = info.filename || 'track'; a.target = '_blank'; a.click(); }
      }
      if (action === 'copy') {
        var prompt = (res.data && res.data.prompt) || '';
        if (navigator.clipboard) navigator.clipboard.writeText(prompt);
      }
      if (action === 'reuse') {
        var regen = res.data || {};
        if (regen.lyrics) state.settings.lyrics = regen.lyrics;
        if (regen.prompt) state.settings.prompt = regen.prompt;
        Object.assign(state.settings, regen);
        state.mode = regen.mode || state.mode;
        doGenerate(root);
      }
      if (action === 'project' && res.data && res.data.project) {
        alert('Project에 저장됨: ' + (res.data.project.title || 'My Project'));
      }
      notifyGalleryUpdated();
    }).catch(function (err) { alert(err.message); });
  }

  function notifyGalleryUpdated() {
    if (window.YooYGallery && typeof window.YooYGallery.reload === 'function') {
      window.YooYGallery.reload();
    }
  }

  function renderCreate(ws, ctrl, root) {
    var isCustom = state.mode === 'custom';
    ws.innerHTML = creditsBar() +
      '<div class="yms-header"><h2>Music Studio</h2><span class="yms-badge">Suno Structure</span></div>' +
      '<div class="yms-mode-toggle">' +
        '<button class="yms-mode-btn' + (isCustom ? ' is-active' : '') + '" data-yms-mode="custom" type="button">Custom Mode</button>' +
        '<button class="yms-mode-btn' + (!isCustom ? ' is-active' : '') + '" data-yms-mode="description" type="button">Simple Mode</button>' +
      '</div>' +
      playerHtml() +
      '<div class="yms-field"><label>Title</label><input data-yms-setting="title" value="' + esc(state.settings.title || '') + '" placeholder="트랙 제목"></div>' +
      (isCustom
        ? '<div class="yms-field"><label>Lyrics</label><div class="yms-structure-tags">' +
            state.structures.map(function (s) {
              return '<span class="yms-struct-tag" data-yms-structure="' + esc(s.id) + '">' + esc(s.label) + '</span>';
            }).join('') +
          '</div><textarea id="yms-lyrics" data-yms-setting="lyrics" placeholder="[Verse]&#10;가사를 입력하세요&#10;&#10;[Chorus]&#10;후렴 가사">' + esc(state.settings.lyrics || '') + '</textarea></div>'
        : '<div class="yms-field"><label>Style Description</label><textarea id="yms-prompt" data-yms-setting="prompt" placeholder="K-pop, upbeat, female vocal, 128 BPM, synth">' + esc(state.settings.prompt || state.settings.style_prompt || '') + '</textarea></div>') +
      '<div class="yms-field"><label>Negative Prompt</label><textarea data-yms-setting="negative_prompt" style="min-height:48px" placeholder="제외할 스타일">' + esc(state.settings.negative_prompt || '') + '</textarea></div>' +
      '<button class="yms-btn-primary" id="yms-generate" type="button"' + (state.generating ? ' disabled' : '') + '>' + (state.generating ? 'Creating...' : 'Create') + '</button>' +
      resultActionsHtml();

    ctrl.innerHTML = controlsHtml() + refHtml();
    mountRefAssets($('#yms-ref-panel-host', ctrl), 'music-studio');
  }

  function mountRefAssets(host, studioKey) {
    if (!host || !window.YooYReferenceAssetsPanel) return;
    if (state.refPanel) state.refPanel.destroy();
    state.refPanel = window.YooYReferenceAssetsPanel.mount(host, {
      studio: studioKey || 'music-studio',
      assets: state.settings.reference_assets || [],
      onChange: function (assets) {
        state.settings.reference_assets = assets;
        state.referenceUrl = assets[0] ? assets[0].url : '';
        state.settings.reference_url = state.referenceUrl;
      }
    });
  }

  function applyRefPayload(payload) {
    if (window.YooYReferenceAssetsPanel && state.refPanel) {
      return window.YooYReferenceAssetsPanel.applyToSettings(payload, state.refPanel.getAssets());
    }
    return payload;
  }

  function refHtml() {
    return '<div id="yms-ref-panel-host"></div>';
  }

  function playerHtml() {
    if (state.generating) {
      return '<div class="yms-player"><div class="yms-cover" style="display:flex;align-items:center;justify-content:center;color:#d8a63a">♪</div><div class="yms-player-info"><strong>Creating...</strong><span>음악을 생성 중입니다</span></div></div>';
    }
    if (!state.lastResult || !state.lastResult.output) {
      return '<div class="yms-player"><div class="yms-cover" style="display:flex;align-items:center;justify-content:center;color:#555">♪</div><div class="yms-player-info"><strong>Preview</strong><span>음악을 생성하세요</span></div></div>';
    }
    var r = state.lastResult;
    var out = r.output || {};
    var cover = out.cover_url || out.thumbnail || '';
    var audio = out.audio_url || out.primary || '';
    return '<div class="yms-player">' +
      (cover ? '<img class="yms-cover" src="' + esc(cover) + '" alt="">' : '<div class="yms-cover" style="display:flex;align-items:center;justify-content:center;color:#555">♪</div>') +
      '<div class="yms-player-info"><strong>' + esc(r.title || 'Track') + '</strong><span>' + esc(r.genre) + ' · ' + esc(r.mood) + ' · ' + esc(String(r.tempo)) + ' BPM</span>' +
      (audio ? '<audio controls src="' + esc(audio) + '"></audio>' : '') + '</div></div>';
  }

  function controlsHtml() {
    var s = state.schema;
    var prov = state.providers.map(function (p) {
      return '<option value="' + esc(p.id) + '"' + ((state.settings.default_provider || 'auto') === p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>';
    }).join('');

    function sel(key, label, items) {
      return '<div class="yms-field"><label>' + label + '</label><select data-yms-setting="' + key + '">' +
        items.map(function (it) {
          var v = it.id !== undefined ? it.id : it;
          var l = it.label || it;
          return '<option value="' + esc(String(v)) + '"' + (String(state.settings[key]) === String(v) ? ' selected' : '') + '>' + esc(String(l)) + '</option>';
        }).join('') + '</select></div>';
    }

    return '<h3 style="color:#d8a63a;font-size:13px;margin:0">MUSIC SETTINGS</h3>' +
      sel('default_provider', 'Provider', state.providers.map(function (p) { return { id: p.id, label: p.name }; })) +
      '<div class="yms-field-row">' + sel('genre', 'Genre', s.genres || []) + sel('mood', 'Mood', s.moods || []) + '</div>' +
      '<div class="yms-field-row">' + sel('tempo', 'Tempo', s.tempos || []) + sel('instrument', 'Instrument', s.instruments || []) + '</div>' +
      '<div class="yms-field-row">' + sel('vocal', 'Vocal', s.vocals || []) + sel('language', 'Language', s.languages || []) + '</div>' +
      sel('duration', 'Duration', (s.durations || []).map(function (d) { return { id: d, label: d + 's' }; }));
  }

  function loadSkeleton(id, root) {
    Core.music.skeleton(id, state.settings.language || 'ko').then(function (res) {
      state.settings.lyrics = (res.data && res.data.lyrics) || '';
      state.settings.structure_template = id;
      renderTab(root);
    });
  }

  function doGenerate(root) {
    var lyrics = ($('#yms-lyrics', root) || {}).value;
    var prompt = ($('#yms-prompt', root) || {}).value;
    if (state.mode === 'custom' && !(lyrics || '').trim()) return;
    if (state.mode === 'description' && !(prompt || '').trim()) return;

    state.generating = true;
    state.settings.mode = state.mode;
    if (lyrics) state.settings.lyrics = lyrics;
    if (prompt) state.settings.prompt = prompt;
    renderTab(root);

    var payload = applyRefPayload(Object.assign({}, state.settings));
    Core.music.generate(payload).then(function (res) {
      finalizeJob(res.data || res, root);
    }).catch(function (err) {
      state.generating = false;
      var ws = $('#yms-workspace', root);
      if (ws) ws.insertAdjacentHTML('beforeend', '<div class="yms-error">' + esc(err.message) + '</div>');
      renderTab(root);
    });
  }

  function hasOutputAsset(data) {
    var url = data.audio_url || (data.output && (data.output.primary || data.output.audio_url || data.output.url));
    return !!(url && String(url).indexOf('http') === 0);
  }

  function finalizeJob(data, root) {
    if (data.status === 'queued' || data.status === 'running') {
      return pollUntilDone(data, root);
    }
    if (data.status === 'failed' || data.error) {
      state.generating = false;
      var ws = $('#yms-workspace', root);
      if (ws) ws.insertAdjacentHTML('beforeend', '<div class="yms-error">' + esc(data.error || 'Generation failed.') + '</div>');
      renderTab(root);
      return data;
    }
    if (data.status === 'completed' && !hasOutputAsset(data)) {
      data.status = 'failed';
      data.error = 'Generation completed but no output asset was returned.';
      state.generating = false;
      var ws2 = $('#yms-workspace', root);
      if (ws2) ws2.insertAdjacentHTML('beforeend', '<div class="yms-error">' + esc(data.error) + '</div>');
      renderTab(root);
      return data;
    }
    state.lastResult = data;
    state.generating = false;
    state.activeGalleryId = data.job_id || '';
    if (data.credits) {
      state.credits.balance = data.credits.balance;
      state.credits.unlimited = data.credits.unlimited;
    }
    notifyGalleryUpdated();
    renderTab(root);
    return data;
  }

  function pollUntilDone(data, root) {
    var provider = data.provider || state.settings.default_provider || 'auto';
    var jobId = data.job_id;
    var attempts = 0;

    function tick() {
      attempts += 1;
      return Core.music.pollJob(jobId, provider).then(function (res) {
        var job = (res.data && res.data.job) || res.data || res;
        if ((job.status === 'queued' || job.status === 'running') && attempts < 15) {
          return new Promise(function (r) { setTimeout(r, 1000); }).then(tick);
        }
        return finalizeJob(job, root);
      });
    }
    return tick();
  }

  function renderGallery(ws) {
    ws.innerHTML = '<div class="yms-loading">Loading...</div>';
    Core.music.gallery().then(function (res) {
      var items = (res.data && res.data.items) || [];
      if (!items.length) { ws.innerHTML = '<div class="yms-empty">갤러리가 비어 있습니다.</div>'; return; }
      ws.innerHTML = '<div class="yms-header"><h2>Gallery</h2><span class="yms-badge">' + items.length + '</span></div>' +
        items.map(function (item) {
          var cover = item.thumbnail || item.cover_url || '';
          var genre = item.genre || (item.meta && item.meta.genre) || '';
          var mood = item.mood || (item.meta && item.meta.mood) || '';
          return '<div class="yms-track-card" data-yms-reuse="' + esc(item.id) + '" data-yms-source="gallery">' +
            (cover ? '<img src="' + esc(cover) + '" alt="">' : '<div style="width:48px;height:48px;background:#222;border-radius:8px"></div>') +
            '<div class="yms-track-meta"><strong>' + esc(item.title) + '</strong>' +
            '<span>' + esc(genre) + (mood ? ' · ' + esc(mood) : '') + '</span></div></div>';
        }).join('');
    });
  }

  function renderHistory(ws) {
    ws.innerHTML = '<div class="yms-loading">Loading...</div>';
    Core.music.history().then(function (res) {
      var items = (res.data && res.data.history) || [];
      if (!items.length) { ws.innerHTML = '<div class="yms-empty">히스토리가 없습니다.</div>'; return; }
      ws.innerHTML = '<div class="yms-header"><h2>Music History</h2><span class="yms-badge">' + items.length + '</span></div>' +
        items.map(function (item) {
          var id = item.id || item.job_id;
          var cover = (item.output && (item.output.cover_url || item.output.thumbnail)) || item.thumbnail || '';
          return '<div class="yms-track-card" data-yms-reuse="' + esc(id) + '" data-yms-source="history">' +
            (cover ? '<img src="' + esc(cover) + '" alt="">' : '<div style="width:48px;height:48px;background:#222;border-radius:8px"></div>') +
            '<div class="yms-track-meta"><strong>' + esc(item.title || 'Track') + '</strong><span>' + esc(item.genre) + ' · ' + esc(item.provider) + '</span></div></div>';
        }).join('');
    });
  }

  function reusePrompt(id, source, root) {
    Core.music.promptReuse({ source_type: source, source_id: id }).then(function (res) {
      Object.assign(state.settings, (res.data && res.data.reuse) || {});
      state.mode = 'custom';
      state.tab = 'create';
      setTab(root);
      renderTab(root);
    });
  }

  function renderAdvanced(ws, ctrl) {
    ws.innerHTML = '<div class="yms-header"><h2>Advanced Settings</h2></div>';
    var adv = (state.schema.advanced) || {};
    var html = '';
    ['weirdness', 'style_influence'].forEach(function (key) {
      var cfg = adv[key] || { min: 0, max: 100, default: 50, label: key };
      var val = state.settings[key] ?? cfg.default;
      html += '<div class="yms-advanced-slider"><span><label>' + esc(cfg.label) + '</label><span class="yms-range-val">' + val + '</span></span>' +
        '<input type="range" data-yms-setting="' + key + '" min="' + cfg.min + '" max="' + cfg.max + '" value="' + val + '"></div>';
    });
    html += '<div class="yms-field"><label>Audio Quality</label><select data-yms-setting="audio_quality">' +
      '<option value="standard"' + (state.settings.audio_quality === 'standard' ? ' selected' : '') + '>Standard</option>' +
      '<option value="high"' + (state.settings.audio_quality === 'high' ? ' selected' : '') + '>High</option></select></div>';
    ws.innerHTML += html;
    ctrl.innerHTML = '<p style="color:#888;font-size:13px">Suno-style Weirdness와 Style Influence를 조절합니다.</p>';
  }

  function renderSettings(ws, ctrl) {
    ws.innerHTML = '<div class="yms-header"><h2>Settings</h2></div>' + controlsHtml() +
      '<button class="yms-btn-primary" id="yms-save-settings" type="button" style="margin-top:16px">Save</button>';
    ctrl.innerHTML = '<h3 style="color:#d8a63a;font-size:13px">API Router</h3>' +
      state.providers.map(function (p) {
        return '<div class="yms-field"><label>' + esc(p.name) + '</label><span style="color:#666;font-size:12px">' + (p.models || []).length + ' models</span></div>';
      }).join('');
  }

  function saveSettings(root) {
    Core.music.updateSettings(state.settings).then(function () { renderTab(root); });
  }

  window.YooYMusicStudio = { mount: mount, state: state };
})();
