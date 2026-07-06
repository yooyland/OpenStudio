(function () {
  'use strict';

  var Core = window.YooYCore;
  if (!Core || !Core.image) return;

  var state = {
    tab: 'generate',
    settings: {},
    schema: {},
    providers: [],
    generating: false,
    editMode: 'edit',
    lastResult: null,
    selectedImage: null,
    referenceUrl: '',
    credits: { balance: 0, unlimited: false, estimate: 0, can_afford: true },
    activeGalleryId: null
  };

  function $(s, c) { return (c || document).querySelector(s); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function mount(container) {
    if (!container || container.dataset.mounted) return;
    container.dataset.mounted = '1';
    container.innerHTML = '<div class="yis-studio" id="yis-root">' +
      '<nav class="yis-tabs">' +
        tab('generate', 'Generate') + tab('edit', 'Edit') + tab('gallery', 'Gallery') +
        tab('history', 'History') + tab('settings', 'Settings') +
      '</nav>' +
      '<div class="yis-workspace" id="yis-workspace"></div>' +
      '<aside class="yis-controls" id="yis-controls"></aside>' +
    '</div>';
    bindEvents(container);
    consumeRegenerate();
    Promise.all([
      Core.image.config(),
      Core.image.settings().catch(function () { return { data: { settings: {} } }; }),
      Core.image.credits().catch(function () { return { data: {} }; })
    ]).then(function (res) {
      state.schema = (res[0].data && res[0].data.schema) || {};
      state.providers = (res[0].data && res[0].data.providers) || [];
      state.settings = (res[1].data && res[1].data.settings) || {};
      state.credits = Object.assign(state.credits, res[2].data || {});
      refreshEstimate();
      renderTab(container);
    });
  }

  function consumeRegenerate() {
    try {
      var raw = sessionStorage.getItem('yoy_regenerate');
      if (!raw) return;
      var payload = JSON.parse(raw);
      if (payload.studio !== 'image-studio' && payload.type !== 'image') return;
      sessionStorage.removeItem('yoy_regenerate');
      state.settings.prompt = payload.prompt || '';
      state.settings.last_prompt = payload.prompt || '';
      state.settings.default_provider = payload.provider || state.settings.default_provider;
      state.tab = 'generate';
    } catch (e) { /* ignore */ }
  }

  function refreshEstimate() {
    return Core.image.estimate(state.settings).then(function (res) {
      state.credits = Object.assign(state.credits, res.data || {});
    }).catch(function () {});
  }

  function notifyGalleryUpdated() {
    document.dispatchEvent(new CustomEvent('yoy:gallery:updated'));
    if (window.YooYGallery && window.YooYGallery.reload) {
      window.YooYGallery.reload();
    }
  }

  function tab(id, label) {
    return '<button class="yis-tab' + (id === 'generate' ? ' is-active' : '') + '" data-yis-tab="' + id + '" type="button">' + label + '</button>';
  }

  function bindEvents(root) {
    root.addEventListener('click', function (e) {
      var t = e.target.closest('[data-yis-tab]');
      if (t) { state.tab = t.dataset.yisTab; setTab(root); renderTab(root); return; }

      if (e.target.closest('#yis-generate')) { doGenerate(root); return; }
      if (e.target.closest('#yis-edit-run')) { doEdit(root); return; }
      if (e.target.closest('#yis-save-settings')) { saveSettings(root); return; }

      var reuse = e.target.closest('[data-yis-reuse]');
      if (reuse) { reusePrompt(reuse.dataset.yisReuse, reuse.dataset.yisSource || 'history', root); return; }

      var img = e.target.closest('[data-yis-select]');
      if (img) { state.selectedImage = img.dataset.yisSelect; renderTab(root); return; }

      var tool = e.target.closest('[data-yis-edit]');
      if (tool) { state.editMode = tool.dataset.yisEdit; renderTab(root); return; }

      var action = e.target.closest('[data-yis-action]');
      if (action) { handleResultAction(action.dataset.yisAction, root); return; }

      var ref = e.target.closest('#yis-ref-upload');
      if (ref) { $('#yis-ref-file', root).click(); return; }
    });

    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-yis-setting]')) {
        var k = e.target.dataset.yisSetting;
        state.settings[k] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        updatePreviewRatio(root);
        refreshEstimate().then(function () { if (state.tab === 'generate') renderTab(root); });
      }
      if (e.target.id === 'yis-ref-file') { handleRefUpload(e.target, root); }
    });
  }

  function setTab(root) {
    root.querySelectorAll('.yis-tab').forEach(function (b) {
      b.classList.toggle('is-active', b.dataset.yisTab === state.tab);
    });
  }

  function renderTab(root) {
    var ws = $('#yis-workspace', root);
    var ctrl = $('#yis-controls', root);
    if (!ws) return;
    switch (state.tab) {
      case 'generate': renderGenerate(ws, ctrl, root); break;
      case 'edit': renderEdit(ws, ctrl, root); break;
      case 'gallery': renderGallery(ws, ctrl); break;
      case 'history': renderHistory(ws, ctrl); break;
      case 'settings': renderSettings(ws, ctrl); break;
    }
  }

  function renderGenerate(ws, ctrl, root) {
    var ratio = state.settings.aspect_ratio || '1:1';
    ws.innerHTML =
      '<div class="yis-header"><h2>Image Generator</h2><span class="yis-badge">GPT Image · Topview · Mock</span></div>' +
      '<div class="yis-preview" id="yis-preview" data-ratio="' + esc(ratio) + '">' + previewHtml() + '</div>' +
      '<div class="yis-prompt-area">' +
        '<textarea id="yis-prompt" placeholder="스마트스토어 제품 썸네일, K-Beauty 광고 비주얼, 한국 SNS 배너 등 프롬프트를 입력하세요.">' + esc(state.settings.last_prompt || '') + '</textarea>' +
        '<div class="yis-negative"><textarea id="yis-negative" placeholder="Negative Prompt (제외할 요소)">' + esc(state.settings.negative_prompt || '') + '</textarea></div>' +
        '<div class="yis-actions"><button class="yis-btn-primary" id="yis-generate" type="button"' + (state.generating ? ' disabled' : '') + '>' +
        (state.generating ? 'Generating...' : 'Generate') + ' · ' + creditLabel() + '</button></div>' +
        resultActionsHtml() +
      '</div>';
    ctrl.innerHTML = controlsHtml() + refUploadHtml(root);
    updatePreviewRatio(root);
  }

  function previewHtml() {
    if (state.generating) return '<div class="yis-loading">Generating...</div>';
    if (state.lastResult && state.lastResult.images && state.lastResult.images.length) {
      return '<div class="yis-grid" style="width:100%;padding:16px">' +
        state.lastResult.images.map(function (img, i) {
          return '<div class="yis-thumb-card' + (state.selectedImage === img.url ? ' is-selected' : '') + '" data-yis-select="' + esc(img.url) + '">' +
            '<img src="' + esc(img.url) + '" alt="result ' + i + '"><span>Image ' + (i + 1) + '</span></div>';
        }).join('') + '</div>';
    }
    return '<div class="yis-empty">프롬프트를 입력하고 Generate를 클릭하세요.</div>';
  }

  function controlsHtml() {
    var s = state.schema;
    var provOpts = state.providers.map(function (p) {
      return '<option value="' + esc(p.id) + '"' + ((state.settings.default_provider || 'mock') === p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>';
    }).join('');

    return '<h3 style="color:#d8a63a;font-size:13px;margin:0 0 8px">IMAGE SETTINGS</h3>' +
      field('Provider', '<select data-yis-setting="default_provider">' + provOpts + '</select>') +
      fieldRow(
        selectField('aspect_ratio', 'Aspect Ratio', (s.aspect_ratios || []).map(function (r) { return r.id; })),
        selectField('resolution', 'Resolution', s.resolutions || ['1024'])
      ) +
      fieldRow(
        selectField('lighting', 'Lighting', (s.lighting || []).map(function (l) { return l.id; })),
        selectField('composition', 'Composition', (s.compositions || []).map(function (c) { return c.id; }))
      ) +
      fieldRow(
        selectField('style', 'Style', (s.styles || []).map(function (st) { return st.id; })),
        selectField('quality', 'Quality', (s.qualities || []).map(function (q) { return q.id; }))
      ) +
      fieldRow(
        selectField('background', 'Background', s.backgrounds || []),
        selectField('color_palette', 'Color', s.color_palettes || [])
      ) +
      fieldRow(
        selectField('product_type', 'Product', s.product_types || []),
        selectField('brand_tone', 'Brand Tone', s.brand_tones || [])
      ) +
      fieldRow(
        selectField('image_count', 'Image Count', s.image_counts || [1, 2, 3, 4]),
        '<div class="yis-field"><label>Seed</label><div class="yis-seed-row"><input type="number" data-yis-setting="seed" value="' + esc(String(state.settings.seed ?? -1)) + '"><button class="yis-btn-secondary" type="button" onclick="document.querySelector(\'[data-yis-setting=seed]\').value=-1">Random</button></div></div>'
      ) +
      '<div class="yis-credits-bar">' + creditLabel() + '</div>';
  }

  function creditLabel() {
    if (state.credits.unlimited) return 'Credits: ∞';
    var est = state.credits.estimate || 0;
    var bal = state.credits.balance ?? 0;
    return est + ' credits (잔액 ' + bal + ')';
  }

  function resultActionsHtml() {
    if (!state.lastResult || !state.lastResult.job_id) return '';
    var id = state.activeGalleryId || (state.lastResult.job_id + '_0');
    return '<div class="yis-result-actions">' +
      '<span>Result Actions</span>' +
      '<button class="yis-btn-secondary" type="button" data-yis-action="download">다운로드</button>' +
      '<button class="yis-btn-secondary" type="button" data-yis-action="edit">편집</button>' +
      '<button class="yis-btn-secondary" type="button" data-yis-action="copy">프롬프트 복사</button>' +
      '<button class="yis-btn-secondary" type="button" data-yis-action="reuse">재사용</button>' +
      '<button class="yis-btn-secondary" type="button" data-yis-action="public">공개</button>' +
      '<button class="yis-btn-secondary" type="button" data-yis-action="marketplace">Marketplace</button>' +
      '</div>';
  }

  function handleResultAction(action, root) {
    var galleryId = state.activeGalleryId || (state.lastResult && (state.lastResult.job_id + '_0'));
    if (!galleryId || !Core.gallery) return;

    var map = {
      download: function () { return Core.gallery.download(galleryId); },
      copy: function () { return Core.gallery.copy(galleryId); },
      reuse: function () { return Core.gallery.regenerate(galleryId); },
      public: function () { return Core.gallery.visibility(galleryId, true); },
      marketplace: function () { return Core.gallery.marketplace(galleryId); },
      edit: null
    };

    if (action === 'edit') {
      state.tab = 'edit';
      setTab(root);
      renderTab(root);
      return;
    }

    var fn = map[action];
    if (!fn) return;
    fn().then(function (res) {
      if (action === 'download') {
        var info = res.data || {};
        if (info.url) { var a = document.createElement('a'); a.href = info.url; a.download = info.filename || 'image'; a.target = '_blank'; a.click(); }
      }
      if (action === 'copy') {
        var prompt = (res.data && res.data.prompt) || '';
        if (navigator.clipboard) navigator.clipboard.writeText(prompt);
      }
      if (action === 'reuse') {
        consumeRegenerate();
        renderTab(root);
      }
      notifyGalleryUpdated();
    }).catch(function (err) { alert(err.message); });
  }

  function selectField(key, label, options) {
    return field(label, '<select data-yis-setting="' + key + '">' +
      options.map(function (o) {
        var v = typeof o === 'object' ? o.id : o;
        var l = typeof o === 'object' ? o.label || o.id : o;
        return '<option value="' + esc(String(v)) + '"' + (String(state.settings[key]) === String(v) ? ' selected' : '') + '>' + esc(String(l)) + '</option>';
      }).join('') + '</select>');
  }

  function field(label, input) {
    return '<div class="yis-field"><label>' + label + '</label>' + input + '</div>';
  }

  function fieldRow(a, b) {
    return '<div class="yis-field-row">' + a + b + '</div>';
  }

  function refUploadHtml(root) {
    return '<div style="margin-top:12px"><h3 style="color:#d8a63a;font-size:13px">REFERENCE IMAGE</h3>' +
      '<div class="yis-ref-upload" id="yis-ref-upload">' +
        (state.referenceUrl ? '<img class="yis-ref-preview" src="' + esc(state.referenceUrl) + '">' : '클릭하여 참조 이미지 업로드') +
      '</div><input type="file" id="yis-ref-file" accept="image/*" style="display:none"></div>';
  }

  function handleRefUpload(input, root) {
    var file = input.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (ev) {
      Core.image.uploadRef({ image_base64: ev.target.result }).then(function (res) {
        state.referenceUrl = (res.data && res.data.reference && res.data.reference.url) || '';
        state.settings.reference_url = state.referenceUrl;
        renderTab(root);
      });
    };
    reader.readAsDataURL(file);
  }

  function updatePreviewRatio(root) {
    var p = $('#yis-preview', root);
    if (p) p.dataset.ratio = state.settings.aspect_ratio || '1:1';
  }

  function doGenerate(root) {
    var prompt = ($('#yis-prompt', root) || {}).value || '';
    var negative = ($('#yis-negative', root) || {}).value || '';
    if (!prompt.trim()) return;

    state.generating = true;
    state.settings.last_prompt = prompt;
    state.settings.negative_prompt = negative;
    state.settings.prompt = prompt;
    if (state.referenceUrl) state.settings.reference_url = state.referenceUrl;
    renderTab(root);

    Core.image.generate(state.settings).then(function (res) {
      finalizeJob(res.data || res, root);
    }).catch(function (err) {
      state.generating = false;
      var ws = $('#yis-workspace', root);
      if (ws) ws.insertAdjacentHTML('beforeend', '<div class="yis-error">' + esc(err.message) + '</div>');
      renderTab(root);
    });
  }

  function finalizeJob(data, root) {
    if (data.status === 'queued' || data.status === 'running') {
      return pollUntilDone(data, root);
    }
    state.lastResult = data;
    state.generating = false;
    state.activeGalleryId = (data.job_id || '') + '_0';
    if (data.images && data.images[0]) state.selectedImage = data.images[0].url;
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
      return Core.image.pollJob(jobId, provider).then(function (res) {
        var job = (res.data && res.data.job) || res.data || res;
        if ((job.status === 'queued' || job.status === 'running') && attempts < 10) {
          return new Promise(function (r) { setTimeout(r, 800); }).then(tick);
        }
        return finalizeJob(job, root);
      });
    }
    return tick();
  }

  function renderEdit(ws, ctrl, root) {
    var src = state.selectedImage || '';
    ws.innerHTML =
      '<div class="yis-header"><h2>Image Edit</h2></div>' +
      '<div class="yis-edit-tools">' +
        ['edit', 'upscale', 'inpaint', 'outpaint'].map(function (m) {
          return '<button class="yis-edit-tool' + (state.editMode === m ? ' is-active' : '') + '" data-yis-edit="' + m + '" type="button">' + m.charAt(0).toUpperCase() + m.slice(1) + '</button>';
        }).join('') +
      '</div>' +
      '<div class="yis-preview" data-ratio="1:1">' +
        (src ? '<img src="' + esc(src) + '" alt="edit source">' : '<div class="yis-empty">갤러리 또는 생성 결과에서 이미지를 선택하세요.</div>') +
      '</div>' +
      '<div class="yis-prompt-area"><textarea id="yis-edit-prompt" placeholder="' + esc(state.editMode) + ' 프롬프트 (선택)"></textarea>' +
      '<button class="yis-btn-primary" id="yis-edit-run" type="button">Run ' + esc(state.editMode) + '</button></div>';
    ctrl.innerHTML = '<p style="color:#888;font-size:13px">선택된 이미지에 Edit, Upscale, Inpaint, Outpaint를 적용합니다.</p>';
  }

  function doEdit(root) {
    if (!state.selectedImage) return;
    var prompt = ($('#yis-edit-prompt', root) || {}).value || '';
    var fn = Core.image[state.editMode] || Core.image.edit;
    fn({ source_url: state.selectedImage, prompt: prompt, provider: state.settings.default_provider || 'mock', auto_save: true }).then(function (res) {
      var data = res.data || res;
      state.selectedImage = (data.images && data.images[0] && data.images[0].url) || (data.output && data.output.primary) || (data.output && data.output.url) || state.selectedImage;
      state.lastResult = data;
      state.activeGalleryId = data.job_id || state.activeGalleryId;
      notifyGalleryUpdated();
      state.tab = 'generate';
      setTab(root);
      renderTab(root);
    });
  }

  function renderGallery(ws, ctrl) {
    ws.innerHTML = '<div class="yis-loading">Loading...</div>';
    Core.image.gallery().then(function (res) {
      var items = (res.data && res.data.items) || [];
      if (!items.length) { ws.innerHTML = '<div class="yis-empty">갤러리가 비어 있습니다.</div>'; return; }
      ws.innerHTML = '<div class="yis-header"><h2>Gallery</h2><span class="yis-badge">' + items.length + '</span></div>' +
        '<div class="yis-grid">' + items.map(function (item) {
          var url = item.output_url || item.url || item.thumbnail || '';
          return '<div class="yis-thumb-card" data-yis-reuse="' + esc(item.id) + '" data-yis-source="gallery" data-yis-select="' + esc(url) + '">' +
            '<img src="' + esc(item.thumbnail || url) + '" alt=""><span>' + esc(item.title) + '</span></div>';
        }).join('') + '</div>';
      ctrl.innerHTML = '<p style="color:#888;font-size:13px">클릭: Prompt Reuse · 이미지 선택: Edit 탭에서 편집</p>';
    });
  }

  function renderHistory(ws, ctrl) {
    ws.innerHTML = '<div class="yis-loading">Loading...</div>';
    Core.image.history().then(function (res) {
      var items = (res.data && res.data.history) || [];
      if (!items.length) { ws.innerHTML = '<div class="yis-empty">프롬프트 히스토리가 없습니다.</div>'; return; }
      ws.innerHTML = '<div class="yis-header"><h2>Prompt History</h2><span class="yis-badge">' + items.length + '</span></div>' +
        items.map(function (item) {
          var id = item.id || item.job_id;
          var thumb = (item.images && item.images[0] && item.images[0].url) || (item.output && item.output.url) || '';
          return '<div class="yis-history-item" data-yis-reuse="' + esc(id) + '" data-yis-source="history">' +
            (thumb ? '<img src="' + esc(thumb) + '" alt="">' : '<div style="width:48px;height:48px;background:#222;border-radius:8px"></div>') +
            '<div class="yis-history-meta"><strong>' + esc((item.prompt || '').substring(0, 50)) + '</strong>' +
            '<span>' + esc(item.provider) + ' · ' + esc(item.quality || '') + ' · x' + esc(String(item.image_count || 1)) + '</span></div></div>';
        }).join('');
      ctrl.innerHTML = '<p style="color:#888;font-size:13px">히스토리에서 프롬프트와 설정을 재사용합니다.</p>';
    });
  }

  function reusePrompt(id, source, root) {
    Core.image.promptReuse({ source_type: source, source_id: id }).then(function (res) {
      var reuse = (res.data && res.data.reuse) || {};
      Object.assign(state.settings, reuse);
      state.settings.last_prompt = reuse.prompt || '';
      state.tab = 'generate';
      setTab(root);
      renderTab(root);
    });
  }

  function renderSettings(ws, ctrl) {
    ws.innerHTML = '<div class="yis-header"><h2>Settings</h2></div><div style="margin-top:12px">' + controlsHtml() + '</div>' +
      '<button class="yis-btn-primary" id="yis-save-settings" type="button" style="margin-top:16px">Save Settings</button>';
    ctrl.innerHTML = '<h3 style="color:#d8a63a;font-size:13px">API Router</h3>' +
      state.providers.map(function (p) {
        return '<div class="yis-field"><label>' + esc(p.name) + '</label><span style="color:#666;font-size:12px">' + (p.models || []).length + ' models</span></div>';
      }).join('');
  }

  function saveSettings(root) {
    Core.image.updateSettings(state.settings).then(function () { renderTab(root); });
  }

  window.YooYImageStudio = { mount: mount, state: state };
})();
