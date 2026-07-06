(function () {
  'use strict';

  var Core = window.YooYCore;
  if (!Core || !Core.voice) return;

  var state = {
    tab: 'tts',
    settings: {},
    options: {},
    providers: [],
    voices: [],
    generating: false,
    lastResult: null,
    cloneSample: null
  };

  function $(s, c) { return (c || document).querySelector(s); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function mount(container) {
    if (!container || container.dataset.mounted) return;
    container.dataset.mounted = '1';
    container.innerHTML = '<div class="yvs-studio" id="yvs-root">' +
      '<nav class="yvs-tabs">' +
        tab('tts', 'TTS') + tab('clone', 'Voice Clone') + tab('gallery', 'Gallery') +
        tab('history', 'History') + tab('advanced', 'Advanced') +
      '</nav>' +
      '<div class="yvs-workspace" id="yvs-workspace"></div>' +
      '<aside class="yvs-controls" id="yvs-controls"></aside></div>';
    bindEvents(container);
    Promise.all([
      Core.voice.config(),
      Core.voice.options(),
      Core.voice.settings().catch(function () { return { data: { settings: {} } }; })
    ]).then(function (res) {
      state.providers = (res[0].data && res[0].data.providers) || [];
      state.options = res[1].data || {};
      state.voices = state.options.voices || [];
      state.settings = (res[2].data && res[2].data.settings) || {};
      renderTab(container);
    });
  }

  function tab(id, label) {
    return '<button class="yvs-tab' + (id === 'tts' ? ' is-active' : '') + '" data-yvs-tab="' + id + '" type="button">' + label + '</button>';
  }

  function bindEvents(root) {
    root.addEventListener('click', function (e) {
      var t = e.target.closest('[data-yvs-tab]');
      if (t) { state.tab = t.dataset.yvsTab; setTab(root); renderTab(root); return; }

      var v = e.target.closest('[data-yvs-voice]');
      if (v) { state.settings.voice_id = v.dataset.yvsVoice; renderTab(root); return; }

      if (e.target.closest('#yvs-speak')) { doSpeak(root); return; }
      if (e.target.closest('#yvs-clone')) { doClone(root); return; }

      var pause = e.target.closest('[data-yvs-pause]');
      if (pause) { insertPause(parseFloat(pause.dataset.yvsPause), root); return; }

      var reuse = e.target.closest('[data-yvs-reuse]');
      if (reuse) { reuseText(reuse.dataset.yvsReuse, root); return; }

      if (e.target.closest('#yvs-clone-upload')) { $('#yvs-clone-file', root).click(); return; }
    });

    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-yvs-setting]')) {
        var k = e.target.dataset.yvsSetting;
        state.settings[k] = e.target.type === 'range' ? parseFloat(e.target.value) : e.target.value;
      }
      if (e.target.id === 'yvs-clone-file') handleCloneFile(e.target);
    });

    root.addEventListener('input', function (e) {
      if (e.target.matches('[data-yvs-setting]') && e.target.type === 'range') {
        state.settings[e.target.dataset.yvsSetting] = parseFloat(e.target.value);
        var val = e.target.parentElement.querySelector('.yvs-range-val');
        if (val) val.textContent = e.target.value;
      }
    });
  }

  function setTab(root) {
    root.querySelectorAll('.yvs-tab').forEach(function (b) {
      b.classList.toggle('is-active', b.dataset.yvsTab === state.tab);
    });
  }

  function renderTab(root) {
    var ws = $('#yvs-workspace', root);
    var ctrl = $('#yvs-controls', root);
    if (!ws) return;
    switch (state.tab) {
      case 'tts': renderTTS(ws, ctrl, root); break;
      case 'clone': renderClone(ws, ctrl); break;
      case 'gallery': renderGallery(ws); break;
      case 'history': renderHistory(ws); break;
      case 'advanced': renderAdvanced(ws, ctrl); break;
    }
  }

  function renderTTS(ws, ctrl, root) {
    ws.innerHTML =
      '<div class="yvs-header"><h2>Text to Speech</h2><span class="yvs-badge">ElevenLabs</span></div>' +
      playerHtml() +
      '<div class="yvs-voice-list">' + state.voices.map(function (v) {
        var icon = v.gender === 'male' ? '♂' : v.gender === 'female' ? '♀' : '◎';
        return '<div class="yvs-voice-item' + (state.settings.voice_id === v.id ? ' is-selected' : '') + '" data-yvs-voice="' + esc(v.id) + '">' +
          '<div class="yvs-voice-icon">' + icon + '</div><strong>' + esc(v.name) + '</strong>' +
          '<span>' + esc(v.language) + (v.category === 'cloned' ? ' · cloned' : '') + '</span></div>';
      }).join('') + '</div>' +
      '<div class="yvs-pause-bar">' +
        ['0.3', '0.5', '1', '2'].map(function (s) {
          return '<button class="yvs-btn-secondary" data-yvs-pause="' + s + '" type="button">+' + s + 's</button>';
        }).join('') +
      '</div>' +
      '<div class="yvs-field"><label>Text</label><textarea id="yvs-text" data-yvs-setting="text" placeholder="읽을 텍스트를 입력하세요. [pause:0.5s] 태그로 쉼을 넣을 수 있습니다.">' + esc(state.settings.text || '') + '</textarea></div>' +
      '<button class="yvs-btn-primary" id="yvs-speak" type="button"' + (state.generating ? ' disabled' : '') + '>' + (state.generating ? 'Generating...' : 'Generate Speech') + '</button>';

    ctrl.innerHTML = controlsHtml();
  }

  function playerHtml() {
    if (state.lastResult && state.lastResult.output && state.lastResult.output.audio_url) {
      return '<div class="yvs-player"><audio controls src="' + esc(state.lastResult.output.audio_url) + '"></audio></div>';
    }
    return '<div class="yvs-player" style="color:#555;text-align:center;padding:24px">음성 미리보기</div>';
  }

  function controlsHtml() {
    function sel(key, label, items, vk, lk) {
      return '<div class="yvs-field"><label>' + label + '</label><select data-yvs-setting="' + key + '">' +
        items.map(function (it) {
          var v = vk ? it[vk] : it;
          var l = lk ? it[lk] : it;
          return '<option value="' + esc(String(v)) + '"' + (String(state.settings[key]) === String(v) ? ' selected' : '') + '>' + esc(String(l)) + '</option>';
        }).join('') + '</select></div>';
    }

    return '<h3 style="color:#d8a63a;font-size:13px;margin:0 0 8px">VOICE</h3>' +
      sel('default_provider', 'Provider', state.providers.map(function (p) { return { id: p.id, label: p.name }; }), 'id', 'label') +
      sel('emotion', 'Emotion', state.options.emotions || [], 'id', 'label') +
      sel('language', 'Language', state.options.languages || [], 'id', 'label') +
      slider('speed', 'Speed', 0.5, 2, 0.05, state.settings.speed || 1) +
      slider('pitch', 'Pitch', -20, 20, 1, state.settings.pitch || 0);
  }

  function slider(key, label, min, max, step, val) {
    return '<div class="yvs-slider"><span><label>' + label + '</label><span class="yvs-range-val">' + val + '</span></span>' +
      '<input type="range" data-yvs-setting="' + key + '" min="' + min + '" max="' + max + '" step="' + step + '" value="' + val + '"></div>';
  }

  function insertPause(seconds, root) {
    var ta = $('#yvs-text', root);
    if (!ta) return;
    Core.voice.insertPause({ text: ta.value, seconds: seconds }).then(function (res) {
      ta.value = (res.data && res.data.text) || ta.value;
      state.settings.text = ta.value;
    });
  }

  function doSpeak(root) {
    var text = ($('#yvs-text', root) || {}).value || '';
    if (!text.trim()) return;
    state.generating = true;
    state.settings.text = text;
    renderTab(root);
    Core.voice.speak(state.settings).then(function (res) {
      state.lastResult = res.data || res;
      state.generating = false;
      renderTab(root);
    }).catch(function (err) {
      state.generating = false;
      var ws = $('#yvs-workspace', root);
      if (ws) ws.insertAdjacentHTML('beforeend', '<div class="yvs-error">' + esc(err.message) + '</div>');
    });
  }

  function renderClone(ws) {
    ws.innerHTML =
      '<div class="yvs-header"><h2>Voice Clone</h2><span class="yvs-badge">Instant Clone</span></div>' +
      '<div class="yvs-field"><label>Clone Name</label><input id="yvs-clone-name" placeholder="내 목소리"></div>' +
      '<div class="yvs-clone-upload" id="yvs-clone-upload">' + (state.cloneSample ? '✓ Sample loaded' : '음성 샘플 업로드 (10초~5분)') + '</div>' +
      '<input type="file" id="yvs-clone-file" accept="audio/*" style="display:none">' +
      '<button class="yvs-btn-primary" id="yvs-clone" type="button">Clone Voice</button>';
  }

  function handleCloneFile(input) {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (ev) { state.cloneSample = ev.target.result; };
    reader.readAsDataURL(file);
  }

  function doClone(root) {
    var name = ($('#yvs-clone-name', root) || {}).value || '';
    if (!name || !state.cloneSample) return;
    Core.voice.clone({ clone_name: name, sample_base64: state.cloneSample, provider: state.settings.default_provider || 'mock' }).then(function () {
      return Core.voice.options();
    }).then(function (res) {
      state.voices = (res.data && res.data.voices) || state.voices;
      state.tab = 'tts';
      setTab(root);
      renderTab(root);
    });
  }

  function renderGallery(ws) {
    ws.innerHTML = '<div class="yvs-loading" style="color:#d8a63a">Loading...</div>';
    Core.voice.gallery().then(function (res) {
      var items = (res.data && res.data.items) || [];
      if (!items.length) { ws.innerHTML = '<div class="yvs-empty">갤러리가 비어 있습니다.</div>'; return; }
      ws.innerHTML = '<div class="yvs-header"><h2>Gallery</h2></div>' + items.map(function (item) {
        return '<div class="yvs-track" data-yvs-reuse="' + esc(item.id) + '"><div class="yvs-track-meta"><strong>' + esc(item.title) + '</strong>' +
          '<span>' + esc(item.voice_id) + ' · ' + esc(item.emotion) + '</span></div></div>';
      }).join('');
    });
  }

  function renderHistory(ws) {
    ws.innerHTML = '<div style="color:#d8a63a;text-align:center;padding:20px">Loading...</div>';
    Core.voice.history().then(function (res) {
      var items = (res.data && res.data.history) || [];
      if (!items.length) { ws.innerHTML = '<div class="yvs-empty">히스토리가 없습니다.</div>'; return; }
      ws.innerHTML = '<div class="yvs-header"><h2>History</h2></div>' + items.map(function (item) {
        var id = item.id || item.job_id;
        return '<div class="yvs-track" data-yvs-reuse="' + esc(id) + '"><div class="yvs-track-meta"><strong>' + esc((item.text || '').substring(0, 50)) + '</strong>' +
          '<span>' + esc(item.voice_id) + ' · ' + esc(item.provider) + '</span></div></div>';
      }).join('');
    });
  }

  function reuseText(id, root) {
    Promise.all([Core.voice.history(), Core.voice.gallery()]).then(function (res) {
      var hItem = ((res[0].data && res[0].data.history) || []).find(function (i) { return (i.id || i.job_id) === id; });
      var gItem = ((res[1].data && res[1].data.items) || []).find(function (i) { return i.id === id; });
      var item = hItem || gItem;
      if (!item) return;
      state.settings.text = item.text || '';
      state.settings.voice_id = item.voice_id || state.settings.voice_id;
      state.settings.emotion = item.emotion || state.settings.emotion;
      state.tab = 'tts';
      setTab(root);
      renderTab(root);
    });
  }

  function renderAdvanced(ws, ctrl) {
    var adv = state.options.advanced || {};
    var html = '<div class="yvs-header"><h2>Advanced</h2></div>';
    ['stability', 'similarity', 'style_exaggeration'].forEach(function (key) {
      var cfg = adv[key] || { min: 0, max: 100, default: 50, label: key };
      var val = state.settings[key] ?? cfg.default;
      html += slider(key, cfg.label, cfg.min, cfg.max, 1, val);
    });
    ws.innerHTML = html;
    ctrl.innerHTML = '<h3 style="color:#d8a63a;font-size:13px">API Router</h3>' +
      state.providers.map(function (p) {
        return '<div class="yvs-field"><label>' + esc(p.name) + '</label><span style="color:#666;font-size:12px">' + (p.models || []).length + ' models</span></div>';
      }).join('');
  }

  window.YooYVoiceStudio = { mount: mount, state: state };
})();
