(function (global) {
  'use strict';

  var publicApi = {
    mount: function () {},
    doGenerate: function () {},
    state: null,
    ready: false
  };
  global.YooYImageStudio = publicApi;

  try {
  var Core = global.YooYCore;

  function debugLog() {
    var cfg = (Core && Core.config) || global.YooYStudio || {};
    var on = !!(cfg.debug || global.YOOY_DEBUG || (Core && Core.debug && Core.debug()));
    if (!on) return;
    var args = ['[YooYImageStudio]'].concat(Array.prototype.slice.call(arguments));
    if (global.console && global.console.log) global.console.log.apply(global.console, args);
  }

  function getImageApi() {
    if (Core && Core.image) return Core.image;
    if (global.YooYImageAPI) return global.YooYImageAPI;
    return null;
  }

  var state = {
    tab: 'generate',
    settings: {},
    schema: {},
    providers: [],
    generating: false,
    editMode: 'edit',
    lastResult: null,
    selectedImage: null,
    selectedResultIndex: 0,
    referenceUrl: '',
    refPanel: null,
    credits: { balance: 0, unlimited: false, estimate: 0, can_afford: true },
    activeGalleryId: null,
    generateStartedAt: 0,
    lastDebugInfo: null,
    smartAuto: true,
    studioMode: 'smart',
    advancedOpen: false,
    fieldLocks: {},
    lastAutoProfile: null,
    lastOptimizedPrompt: '',
    lastUserPrompt: '',
    lastComposerMeta: null,
    creativeBrief: null,
    rawUserRequest: '',
    intentDomain: '',
    promptVersion: '',
    showFinalPrompt: false,
    recommendedStyleId: '',
    refAnalysisLabels: [],
    gallerySelectedId: null,
    galleryItems: [],
    generationMode: 'fast',
    generateStep: '',
    lastProviderHealth: null,
    providerHealthLoading: false
  };

  var POLL_MAX_MS = 90000;
  var POLL_MAX_ATTEMPTS = 45;
  var POLL_INTERVAL_MS = 2000;
  var POLL_STALE_MS = 30000;
  var ASYNC_POLL_PROVIDERS = ['runway', 'replicate', 'kling', 'luma', 'pika', 'google-veo'];

  function $(s, c) { return (c || document).querySelector(s); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  function mount(container) {
    try {
    if (!container || container.dataset.mounted) return;

    var api = getImageApi();
    if (!api) {
      container.innerHTML = '<div class="yis-error">Image Studio API unavailable. Reload the page.</div>';
      debugLog('mount blocked: no image API');
      return;
    }

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
    installGlobalGenerateHandler();
    consumeRegenerate();
    Promise.all([
      api.config(),
      api.settings().catch(function () { return { data: { settings: {} } }; }),
      api.credits().catch(function () { return { data: {} }; })
    ]).then(function (res) {
      state.schema = (res[0].data && res[0].data.schema) || {};
      state.providers = (res[0].data && res[0].data.providers) || [];
      state.settings = (res[1].data && res[1].data.settings) || {};
      state.generationMode = state.settings.generation_mode || 'fast';
      syncModelForProvider(state.settings.default_provider || 'auto', true);
      syncSizeForProvider(true);
      state.credits = Object.assign(state.credits, res[2].data || {});
      state.smartAuto = state.settings.smart_auto !== false;
      state.studioMode = state.smartAuto === false ? 'custom' : 'smart';
      if (state.settings.smart_auto === undefined) state.settings.smart_auto = true;
      initExtraSettings();
      consumeHomePrompt();
      refreshEstimate().then(function () { renderTab(container); bindGenerateButton(container); updateProviderUX(container); });
      global.YooYImageStudioReady = true;
      publicApi.ready = true;
      debugLog('mounted');
    }).catch(function (err) {
      container.innerHTML = '<div class="yis-error">Image Studio failed to load: ' + esc(err.message) + '</div>';
      debugLog('mount load error', err);
    });
    } catch (mountErr) {
      debugLog('mount error', mountErr);
      if (container) container.innerHTML = '<div class="yis-error">Image Studio mount error: ' + esc(mountErr.message) + '</div>';
    }
  }

  function consumeHomePrompt() {
    try {
      var saved = sessionStorage.getItem('yoy_home_prompt');
      if (saved) {
        state.settings.last_prompt = saved;
        state.settings.prompt = saved;
        sessionStorage.removeItem('yoy_home_prompt');
      }
      var raw = sessionStorage.getItem('yoy_assistant_raw_request');
      if (raw) {
        state.rawUserRequest = raw;
        sessionStorage.removeItem('yoy_assistant_raw_request');
      }
      var briefRaw = sessionStorage.getItem('yoy_assistant_creative_brief');
      if (briefRaw) {
        try {
          state.creativeBrief = JSON.parse(briefRaw);
        } catch (e2) { state.creativeBrief = null; }
        sessionStorage.removeItem('yoy_assistant_creative_brief');
      }
      var domain = sessionStorage.getItem('yoy_assistant_intent_domain');
      if (domain) {
        state.intentDomain = domain;
        state.settings.intent_domain = domain;
        sessionStorage.removeItem('yoy_assistant_intent_domain');
      }
      var pver = sessionStorage.getItem('yoy_assistant_prompt_version');
      if (pver) {
        state.promptVersion = pver;
        sessionStorage.removeItem('yoy_assistant_prompt_version');
      }
    } catch (e) { /* ignore */ }
  }

  function consumeRegenerate() {
    try {
      var raw = sessionStorage.getItem('yoy_regenerate');
      if (!raw) return;
      var payload = JSON.parse(raw);
      if (payload.studio !== 'image-studio' && payload.type !== 'image') return;
      sessionStorage.removeItem('yoy_regenerate');
      state.settings.prompt = payload.prompt || '';
      state.settings.last_prompt = payload.user_prompt || payload.prompt || '';
      if (payload.optimized_prompt) state.settings.optimized_prompt = payload.optimized_prompt;
      if (payload.provider) state.settings.default_provider = payload.provider;
      if (payload.model) {
        state.settings.default_model = payload.model;
        state.settings.model = payload.model;
      }
      if (payload.settings && typeof payload.settings === 'object') {
        Object.keys(payload.settings).forEach(function (k) {
          if (payload.settings[k] != null && payload.settings[k] !== '') {
            state.settings[k] = payload.settings[k];
          }
        });
      }
      state.tab = 'generate';
    } catch (e) { /* ignore */ }
  }

  function refreshEstimate() {
    var api = getImageApi();
    if (!api) return Promise.resolve();
    return api.estimate(state.settings).then(function (res) {
      state.credits = Object.assign(state.credits, res.data || {});
    }).catch(function () {});
  }

  function syncPromptFields(root) {
    var promptEl = $('#yis-prompt', root);
    if (promptEl) state.settings.last_prompt = promptEl.value;
    var negativeEl = root.querySelector('[data-yis-setting="negative_prompt"]');
    if (negativeEl) state.settings.negative_prompt = negativeEl.value;
  }

  function updateCreditsUI(root) {
    var bar = root && root.querySelector('.yis-credits-bar');
    if (bar) bar.textContent = creditLabel();
    var btn = root && root.querySelector('#yis-generate');
    if (btn && !state.generating) {
      btn.textContent = 'Generate · ' + creditLabel();
    }
  }

  function selectedProviderId() {
    return state.settings.default_provider || 'auto';
  }

  function findProvider(id) {
    var pid = String(id || '');
    for (var i = 0; i < state.providers.length; i++) {
      if (state.providers[i].id === pid) return state.providers[i];
    }
    return null;
  }

  var MODEL_DEFAULTS = {
    openai: 'gpt-image-1',
    'mock-image': 'mock-image-v1',
    mock: 'mock-image-v1',
    flux: 'flux-schnell',
    replicate: 'flux-schnell',
    'gemini-image': 'gemini-image-v1',
    stability: 'stable-diffusion-xl',
    ideogram: 'ideogram-v2'
  };

  var SIZE_PROFILES = {
    'openai:gpt-image-1': {
      sizes: ['auto', '1024x1024', '1024x1536', '1536x1024'],
      aspect_map: {
        '1:1': '1024x1024',
        '16:9': '1536x1024',
        '9:16': '1024x1536',
        '4:5': '1024x1536',
        '3:2': '1536x1024',
        '2:3': '1024x1536'
      }
    },
    'openai:dall-e-3': {
      sizes: ['1024x1024', '1024x1792', '1792x1024'],
      aspect_map: {
        '1:1': '1024x1024',
        '16:9': '1792x1024',
        '9:16': '1024x1792',
        '4:5': '1024x1792',
        '3:2': '1792x1024',
        '2:3': '1024x1792'
      }
    }
  };

  function isOpenAiProvider() {
    var pid = selectedProviderId();
    return pid === 'openai' || pid === 'openai-image';
  }

  function isGptImage1Model() {
    var model = state.settings.default_model || state.settings.model || '';
    if (model) return model === 'gpt-image-1';
    return isOpenAiProvider();
  }

  function resolvedPreviewProviderId() {
    var pid = selectedProviderId();
    if (pid !== 'auto') return pid;
    var live = state.providers.filter(function (p) {
      return !p.is_mock && (p.auto_eligible || p.usable || p.status === 'connected');
    });
    if (!live.length) return 'mock-image';
    var openai = null;
    for (var i = 0; i < live.length; i++) {
      if (live[i].id === 'openai' || live[i].id === 'openai-image') {
        openai = live[i];
        break;
      }
    }
    if (openai) return 'openai';
    return live[0].id;
  }

  function sizeProfileKey() {
    var pid = resolvedPreviewProviderId();
    var model = state.settings.default_model || state.settings.model || defaultModelForProvider(pid);
    return pid + ':' + model;
  }

  function activeSizeProfile() {
    return SIZE_PROFILES[sizeProfileKey()] || null;
  }

  function mappedSizeForAspect(aspectRatio) {
    var profile = activeSizeProfile();
    if (!profile) return '';
    return profile.aspect_map[aspectRatio] || profile.sizes[0] || '';
  }

  var OUTPUT_SIZE_RATIOS = ['1:1', '16:9', '9:16', '4:5', '3:2', '2:3'];

  function formatPx(size) {
    return String(size || '').replace(/x/gi, '×');
  }

  function computeDisplaySize(aspectRatio, resolution) {
    var base = Math.max(512, parseInt(resolution, 10) || 1024);
    switch (aspectRatio) {
      case '16:9':
        return (base === 1792 ? '1792×1024' : Math.round(base * 16 / 9) + '×' + base);
      case '9:16':
        return (base === 1792 ? '1024×1792' : base + '×' + Math.round(base * 16 / 9));
      case '4:5':
        return base + '×' + Math.round(base * 5 / 4);
      case '3:2':
        return Math.round(base * 3 / 2) + '×' + base;
      case '2:3':
        return base + '×' + Math.round(base * 3 / 2);
      default:
        return base + '×' + base;
    }
  }

  function buildOutputSizePresets() {
    var profile = activeSizeProfile();
    var presets = [];
    if (profile) {
      if (profile.sizes.indexOf('auto') >= 0) {
        presets.push({ id: 'auto', label: 'Auto', aspect_ratio: '1:1', size: 'auto' });
      }
      OUTPUT_SIZE_RATIOS.forEach(function (ratio) {
        var sz = profile.aspect_map[ratio];
        if (!sz) return;
        presets.push({
          id: ratio + '|' + sz,
          label: ratio + ' (' + formatPx(sz) + ')',
          aspect_ratio: ratio,
          size: sz
        });
      });
      return presets;
    }
    var ratios = ((state.schema && state.schema.aspect_ratios) || []).map(function (r) {
      return r.id || r;
    }).filter(Boolean);
    if (!ratios.length) ratios = ['1:1', '16:9', '9:16'];
    var resolution = String(state.settings.resolution || '1024');
    ratios.forEach(function (ratio) {
      presets.push({
        id: ratio + '|' + resolution,
        label: ratio + ' (' + computeDisplaySize(ratio, resolution) + ')',
        aspect_ratio: ratio,
        resolution: resolution
      });
    });
    return presets;
  }

  function currentOutputSizeId() {
    var profile = activeSizeProfile();
    if (profile && state.settings.size === 'auto') return 'auto';
    var aspect = state.settings.aspect_ratio || '1:1';
    if (profile) {
      var size = state.settings.size || mappedSizeForAspect(aspect);
      return aspect + '|' + size;
    }
    return aspect + '|' + String(state.settings.resolution || '1024');
  }

  function applyOutputSizeSelection(id) {
    if (id === 'auto') {
      state.settings.size = 'auto';
      state.settings.aspect_ratio = state.settings.aspect_ratio || '1:1';
      return;
    }
    var parts = String(id || '').split('|');
    var aspect = parts[0] || '1:1';
    var val = parts[1] || '';
    state.settings.aspect_ratio = aspect;
    if (activeSizeProfile()) {
      state.settings.size = val || mappedSizeForAspect(aspect);
    } else {
      state.settings.resolution = val || '1024';
      state.settings.size = '';
    }
  }

  function normalizeOutputSizeSelection() {
    var presets = buildOutputSizePresets();
    if (!presets.length) return;
    var cur = currentOutputSizeId();
    for (var i = 0; i < presets.length; i++) {
      if (presets[i].id === cur) return;
    }
    applyOutputSizeSelection(presets[0].id);
  }

  function outputSizeOptionsHtml() {
    var presets = buildOutputSizePresets();
    var cur = currentOutputSizeId();
    if (presets.length) {
      var matched = false;
      for (var i = 0; i < presets.length; i++) {
        if (presets[i].id === cur) { matched = true; break; }
      }
      if (!matched) {
        applyOutputSizeSelection(presets[0].id);
        cur = presets[0].id;
      }
    }
    return presets.map(function (preset) {
      return '<option value="' + esc(preset.id) + '"' + (cur === preset.id ? ' selected' : '') + '>' +
        esc(preset.label) + '</option>';
    }).join('');
  }

  function syncSizeForProvider(force) {
    var profile = activeSizeProfile();
    if (!profile) {
      if (force && !state.settings.size) state.settings.size = '';
      normalizeOutputSizeSelection();
      return;
    }
    var ratio = state.settings.aspect_ratio || '1:1';
    var mapped = mappedSizeForAspect(ratio);
    var current = state.settings.size || '';
    if (force || !current || profile.sizes.indexOf(current) < 0 || current === '1024x1792') {
      state.settings.size = mapped || profile.sizes[0];
    }
    normalizeOutputSizeSelection();
  }

  function getSmartAuto() {
    return global.YooYImageStudioSmartAuto || null;
  }

  var LOCKABLE_FIELDS = [
    'default_provider', 'default_model', 'seed', 'style', 'quality', 'color_palette', 'mood',
    'background', 'lighting', 'composition', 'camera', 'lens', 'camera_angle', 'depth_of_field',
    'commercial_mode', 'brand_tone', 'product_type', 'negative_prompt', 'image_count', 'output_format'
  ];

  var MOOD_OPTIONS = [
    { id: 'neutral', label: 'Neutral' }, { id: 'dreamy', label: 'Dreamy' }, { id: 'energetic', label: 'Energetic' },
    { id: 'moody', label: 'Moody' }, { id: 'romantic', label: 'Romantic' }, { id: 'epic', label: 'Epic' }
  ];
  var CAMERA_OPTIONS = [
    { id: 'cinema_50mm', label: 'Cinema 50mm' }, { id: 'wide_24mm', label: 'Wide 24mm' },
    { id: 'tele_85mm', label: 'Telephoto 85mm' }, { id: 'macro', label: 'Macro' }
  ];
  var LENS_OPTIONS = [
    { id: 'standard', label: 'Standard' }, { id: 'anamorphic', label: 'Anamorphic' },
    { id: 'fisheye', label: 'Fisheye' }, { id: 'portrait', label: 'Portrait' }
  ];
  var ANGLE_OPTIONS = [
    { id: 'eye_level', label: 'Eye Level' }, { id: 'low_angle', label: 'Low Angle' },
    { id: 'high_angle', label: 'High Angle' }, { id: 'birds_eye', label: "Bird's Eye" }
  ];
  var DOF_OPTIONS = [
    { id: 'shallow', label: 'Shallow' }, { id: 'medium', label: 'Medium' }, { id: 'deep', label: 'Deep' }
  ];
  var FORMAT_OPTIONS = [
    { id: 'png', label: 'PNG' }, { id: 'jpeg', label: 'JPEG' }, { id: 'webp', label: 'WebP' }
  ];

  function initExtraSettings() {
    state.settings.mood = state.settings.mood || 'neutral';
    state.settings.camera = state.settings.camera || 'cinema_50mm';
    state.settings.lens = state.settings.lens || 'standard';
    state.settings.camera_angle = state.settings.camera_angle || 'eye_level';
    state.settings.depth_of_field = state.settings.depth_of_field || 'medium';
    state.settings.output_format = state.settings.output_format || 'png';
    state.settings.commercial_mode = state.settings.commercial_mode !== false;
  }

  function isFieldAuto(key) {
    if (!state.smartAuto) return false;
    return state.fieldLocks[key] !== 'manual';
  }

  function setStudioMode(mode, root) {
    state.studioMode = mode;
    state.smartAuto = mode === 'smart';
    state.settings.smart_auto = state.smartAuto;
    if (mode === 'smart') {
      state.fieldLocks = {};
    } else {
      state.advancedOpen = true;
      LOCKABLE_FIELDS.forEach(function (k) { state.fieldLocks[k] = 'manual'; });
    }
    renderTab(root);
    bindGenerateButton(root);
    if (mode === 'smart' && state.advancedOpen) previewSmartAuto(root);
  }

  function toggleFieldLock(key, root) {
    if (!state.smartAuto) return;
    if (state.fieldLocks[key] === 'manual') {
      delete state.fieldLocks[key];
      previewSmartAuto(root);
    } else {
      state.fieldLocks[key] = 'manual';
    }
    refreshAdvancedInner(root);
  }

  function modeToggleHtml() {
    var smartOn = state.studioMode !== 'custom';
    return '<div class="yis-mode-toggle" role="radiogroup" aria-label="Studio mode">' +
      '<button type="button" class="yis-mode-opt' + (smartOn ? ' is-active' : '') + '" data-yis-mode="smart" aria-pressed="' + smartOn + '">' +
        '<span class="yis-mode-dot" aria-hidden="true"></span>Smart Auto</button>' +
      '<button type="button" class="yis-mode-opt' + (!smartOn ? ' is-active' : '') + '" data-yis-mode="custom" aria-pressed="' + !smartOn + '">' +
        '<span class="yis-mode-dot" aria-hidden="true"></span>Custom</button>' +
      '<p class="yis-mode-hint">' + (smartOn ? 'AI가 대부분의 설정을 자동 결정합니다. 필요한 항목만 Advanced에서 Manual로 전환하세요.' : '전문가 모드 — Advanced에서 원하는 항목만 직접 수정하세요.') + '</p>' +
    '</div>';
  }

  function applyProfileToAutoFields(profile) {
    if (!profile) return;
    var Smart = getSmartAuto();
    var map = {
      default_provider: profile.provider,
      default_model: profile.model,
      quality: profile.quality,
      style: profile.style,
      lighting: profile.lighting,
      composition: profile.composition,
      background: profile.background,
      color_palette: profile.color_palette,
      brand_tone: profile.brand_tone,
      product_type: profile.product_type,
      mood: profile.mood,
      camera: profile.camera,
      lens: profile.lens,
      camera_angle: profile.camera_angle,
      depth_of_field: profile.depth_of_field,
      image_count: profile.image_count,
      output_format: profile.output_format || 'png'
    };
    Object.keys(map).forEach(function (key) {
      if (key === 'default_model' && (profile.provider === 'auto' || selectedProviderId() === 'auto')) {
        return;
      }
      if (isFieldAuto(key) && map[key] != null && map[key] !== '') {
        state.settings[key] = map[key];
        if (key === 'default_model') state.settings.model = map[key];
      }
    });
    if (isFieldAuto('commercial_mode')) {
      state.settings.commercial_mode = profile.commercial !== false;
    }
    if (isFieldAuto('negative_prompt') && Smart && profile.commercial !== false) {
      state.settings.negative_prompt = Smart.PREMIUM_NEGATIVE;
    }
  }

  function analyzeSmartProfile(prompt) {
    var Smart = getSmartAuto();
    if (!Smart) return null;
    var refAssets = state.settings.reference_assets || [];
    var refCtx = Smart.analyzeReference(refAssets, prompt);
    state.refAnalysisLabels = refCtx.labels || [];
    var profile = Smart.analyzePrompt(prompt, refCtx);
    if (state.recommendedStyleId && Smart.STYLE_PRESETS && Smart.STYLE_PRESETS[state.recommendedStyleId]) {
      var preset = Smart.STYLE_PRESETS[state.recommendedStyleId];
      profile.style = preset.style || profile.style;
      profile.lighting = preset.lighting || profile.lighting;
      profile.brand_tone = preset.brand_tone || profile.brand_tone;
      profile.quality = preset.quality || profile.quality;
      profile.composition = preset.composition || profile.composition;
      profile.recommendedStyle = preset.label;
    }
    return { profile: profile, refCtx: refCtx, Smart: Smart };
  }

  function fetchServerCompose(prompt, callback) {
    var api = getImageApi();
    if (!api || !api.composePrompt || !prompt || !prompt.trim()) {
      if (callback) callback(null);
      return;
    }
    api.composePrompt({
      user_prompt: prompt,
      prompt: prompt,
      smart_auto: state.smartAuto !== false,
      generation_mode: state.generationMode || 'fast',
      provider: state.settings.default_provider || 'auto',
      quality: state.generationMode === 'premium' ? 'hd' : (state.settings.quality || 'standard'),
      creative_brief: state.creativeBrief || undefined,
      intent_domain: state.intentDomain || state.settings.intent_domain || undefined,
      raw_user_request: state.rawUserRequest || prompt
    }).then(function (res) {
      var data = (res && res.data) ? res.data : res;
      if (callback) callback(data || null);
    }).catch(function () {
      if (callback) callback(null);
    });
  }

  function applyServerCompose(prompt, composed) {
    if (!composed) return;
    var Smart = getSmartAuto();
    state.lastOptimizedPrompt = composed.canonical_prompt || composed.prompt || state.lastOptimizedPrompt;
    state.lastComposerMeta = composed.meta || null;
    if (composed.creative_brief) {
      state.creativeBrief = composed.creative_brief;
      state.intentDomain = composed.creative_brief.content_domain || state.intentDomain;
    }
    if (Smart && Smart.applyComposerResult && composed.settings) {
      Smart.applyComposerResult(state.settings, composed);
      applyProfileToAutoFields(composed.settings);
    }
    if (composed.negative_prompt) {
      state.settings.negative_prompt = composed.negative_prompt;
    }
    state.lastUserPrompt = prompt;
  }

  function previewSmartAuto(root) {
    if (!state.smartAuto) return;
    var prompt = ($('#yis-prompt', root) || {}).value || state.settings.last_prompt || '';
    if (!prompt.trim()) return;
    var analyzed = analyzeSmartProfile(prompt);
    if (!analyzed) return;
    applyProfileToAutoFields(analyzed.profile);
    syncModelForProvider(state.settings.default_provider || 'auto', true);
    state.lastAutoProfile = analyzed.profile;
    state.lastOptimizedPrompt = analyzed.Smart.optimizePrompt(prompt, analyzed.profile, analyzed.refCtx);
    state.lastUserPrompt = prompt;
    fetchServerCompose(prompt, function (composed) {
      applyServerCompose(prompt, composed);
      refreshAdvancedInner(root);
      refreshAutoResultPanel(root);
    });
    refreshAdvancedInner(root);
    refreshAutoResultPanel(root);
  }

  function runSmartAuto(prompt) {
    if (!state.smartAuto) {
      return { optimizedPrompt: prompt, profile: null };
    }
    var analyzed = analyzeSmartProfile(prompt);
    if (!analyzed) return { optimizedPrompt: prompt, profile: null };
    applyProfileToAutoFields(analyzed.profile);
    syncModelForProvider(state.settings.default_provider || 'auto', true);
    var localOptimized = analyzed.Smart.optimizePrompt(prompt, analyzed.profile, analyzed.refCtx);
    state.lastAutoProfile = analyzed.profile;
    state.lastOptimizedPrompt = localOptimized;
    state.lastUserPrompt = prompt;
    fetchServerCompose(prompt, function (composed) {
      applyServerCompose(prompt, composed);
    });
    return {
      optimizedPrompt: prompt,
      profile: analyzed.profile,
      refCtx: analyzed.refCtx,
      serverCompose: true
    };
  }

  function smartAutoCardHtml() {
    return '<div class="yis-smart-card">' +
      '<div class="yis-smart-card__head"><strong>Smart Auto</strong><span class="yis-smart-badge">ON</span></div>' +
      '<p class="yis-smart-card__lead">의미·감정·장면을 분석해 ChatGPT급 프리미엄 Prompt를 자동 생성합니다. 단어를 그리지 않고 작품을 만듭니다.</p>' +
      '<ul class="yis-smart-checklist">' +
        '<li>✔ Emotion Engine — 감정을 시각언어로 변환</li><li>✔ Scene Planner — 장면 설계</li><li>✔ K-Culture 미학</li>' +
        '<li>✔ Commercial Optimizer</li><li>✔ Provider별 Prompt 최적화</li><li>✔ 텍스트·만화풍 차단</li>' +
        '<li>✔ 모든 옵션 AUTO</li><li>✔ Fast/Premium 품질 우선</li><li>✔ Reference 분석</li>' +
      '</ul></div>';
  }

  function styleRecommendationHtml(prompt) {
    var Smart = getSmartAuto();
    if (!Smart || state.smartAuto === false) return '';
    prompt = prompt || state.settings.last_prompt || '';
    var recs = Smart.recommendStyles(prompt);
    if (!state.recommendedStyleId && recs[0]) state.recommendedStyleId = recs[0].id;
    return '<div class="yis-style-rec"><span class="yis-style-rec__label">추천 스타일</span>' +
      '<div class="yis-style-rec__chips">' + recs.map(function (r) {
        var active = state.recommendedStyleId === r.id ? ' is-active' : '';
        return '<button type="button" class="yis-style-chip' + active + '" data-yis-style-pick="' + esc(r.id) + '">' + esc(r.label) + '</button>';
      }).join('') + '</div></div>';
  }

  function refAnalysisCardHtml() {
    if (!state.refAnalysisLabels || !state.refAnalysisLabels.length) return '';
    return '<div class="yis-ref-analysis"><span class="yis-ref-analysis__label">Reference 분석</span>' +
      '<div class="yis-ref-analysis__tags">' + state.refAnalysisLabels.map(function (l) {
        return '<span class="yis-ref-tag">' + esc(l) + '</span>';
      }).join('') + '</div></div>';
  }

  function promptIntelligencePanelHtml() {
    var brief = state.creativeBrief || {};
    var userReq = state.rawUserRequest || state.lastUserPrompt || state.settings.last_prompt || '';
    var understood = [
      ['중심 주제', brief.primary_subject || '—'],
      ['목적', brief.ad_subtype || brief.what || brief.content_domain || '—'],
      ['형식', brief.medium || brief.output_format || '—'],
      ['타깃', brief.audience || '—'],
      ['톤', brief.tone || '—'],
      ['색감', brief.color_palette || '—'],
      ['구도', brief.composition || '—'],
      ['반드시 포함', (brief.required_elements || []).join(', ') || '—'],
      ['제외 요소', (brief.forbidden_elements || []).slice(0, 6).join(', ') || '—']
    ];
    var finalPrompt = state.showFinalPrompt
      ? ('<div class="yis-spi__final"><span>C. 이미지 생성용 최종 Prompt</span><p>' + esc(state.lastOptimizedPrompt || 'Compose 후 표시됩니다.') + '</p></div>')
      : '';
    return '<div class="yis-spi" id="yis-spi">' +
      '<div class="yis-spi__block"><span class="yis-spi__label">A. 사용자 요청</span><p>' + esc(userReq || '프롬프트를 입력하세요') + '</p></div>' +
      '<div class="yis-spi__block"><span class="yis-spi__label">B. AI가 이해한 내용</span>' +
        '<dl class="yis-spi__grid">' + understood.map(function (row) {
          return '<div><dt>' + esc(row[0]) + '</dt><dd>' + esc(row[1]) + '</dd></div>';
        }).join('') + '</dl></div>' +
      finalPrompt +
      '<div class="yis-spi__actions">' +
        '<button type="button" class="yis-btn-secondary" id="yis-spi-edit">AI 이해 내용 수정</button>' +
        '<button type="button" class="yis-btn-secondary" id="yis-spi-recompose">Prompt 다시 구성</button>' +
        '<button type="button" class="yis-btn-secondary" id="yis-spi-show-final">최종 Prompt 보기</button>' +
      '</div></div>';
  }

  function autoSelectedCardHtml() {
    if (!state.lastAutoProfile && !state.lastComposerMeta && !state.creativeBrief) return '';
    var Smart = getSmartAuto();
    var rows = [];
    if (state.lastComposerMeta && Smart) {
      rows = Smart.composerProfileLabels(state.lastComposerMeta);
    } else if (state.lastAutoProfile && Smart) {
      rows = Smart.profileLabels(state.lastAutoProfile);
    }
    var analysis = (state.lastComposerMeta && state.lastComposerMeta.analysis) || {};
    if (analysis.domain || analysis.primary_subject) {
      rows = [
        { key: 'domain', label: 'Domain', value: analysis.domain || '—' },
        { key: 'subject', label: '중심 주제', value: analysis.primary_subject || '—' }
      ].concat(rows);
    }
    var promptPreview = state.lastOptimizedPrompt
      ? '<div class="yis-auto-result__prompt"><span>최종 Prompt</span><p>' + esc(state.lastOptimizedPrompt) + '</p></div>'
      : '';
    return '<div class="yis-auto-result" id="yis-auto-result">' +
      '<h4 class="yis-auto-result__title">Prompt Intelligence</h4>' +
      '<dl class="yis-auto-result__grid">' + rows.map(function (r) {
        return '<div><dt>' + esc(r.label) + '</dt><dd>' + esc(r.value) + '</dd></div>';
      }).join('') + '</dl>' + promptPreview + '</div>';
  }

  function advancedSectionHtml() {
    var chevron = state.advancedOpen ? '▲' : '▼';
    return '<details class="yis-advanced" id="yis-advanced"' + (state.advancedOpen ? ' open' : '') + '>' +
      '<summary class="yis-advanced__summary">' + chevron + ' Advanced Settings</summary>' +
      '<div class="yis-advanced-body"><div class="yis-advanced-inner" id="yis-advanced-inner">' + advancedFieldsInnerHtml() + '</div></div>' +
    '</details>';
  }

  function advancedFieldsInnerHtml() {
    var s = state.schema;
    var optimized = state.lastOptimizedPrompt || '';
    var transparentOn = (state.settings.background || '') === 'transparent';
    return advSection('AI', 'Provider, model, and reproducibility settings.', '<div class="yis-adv-section__grid">' +
      lockedSelectField('default_provider', 'Provider', [{ id: 'auto', label: 'Auto (Best Available)' }].concat(
        state.providers.filter(function (p) { return p.id !== 'auto'; }).map(function (p) { return { id: p.id, label: providerOptionLabel(p) }; })
      )) +
      lockedFieldHtml('default_model', 'Model', '<select data-yis-setting="default_model" id="yis-model-select"' + fieldDisabledAttr('default_model') + '>' + modelSelectHtml() + '</select>') +
      '<div class="yis-provider-preflight" id="yis-provider-preflight" hidden></div>' +
      lockedFieldHtml('seed', 'Seed', '<div class="yis-seed-row"><input type="number" data-yis-setting="seed" value="' + esc(String(state.settings.seed != null ? state.settings.seed : -1)) + '"' + fieldDisabledAttr('seed') + '><button class="yis-btn-secondary" type="button" data-yis-action="seed-random"' + (isFieldAuto('seed') && state.smartAuto ? ' disabled' : '') + '>Random</button></div>') +
    '</div>') +
    advSection('Style', 'Visual tone, quality, and composition controls.', '<div class="yis-adv-section__grid">' +
      fieldRow(
        lockedSelectField('style', 'Style', s.styles || []),
        lockedSelectField('quality', 'Quality', s.qualities || [])
      ) +
      fieldRow(
        lockedSelectField('color_palette', 'Color', s.color_palettes || []),
        lockedSelectField('mood', 'Mood', MOOD_OPTIONS)
      ) +
      fieldRow(
        lockedSelectField('background', 'Background', s.backgrounds || []),
        lockedSelectField('lighting', 'Lighting', s.lighting || [])
      ) +
      lockedSelectField('composition', 'Composition', s.compositions || []) +
    '</div>') +
    advSection('Camera', 'Lens, angle, and depth-of-field options.', '<div class="yis-adv-section__grid">' +
      fieldRow(
        lockedSelectField('camera', 'Camera', CAMERA_OPTIONS),
        lockedSelectField('lens', 'Lens', LENS_OPTIONS)
      ) +
      fieldRow(
        lockedSelectField('camera_angle', 'Camera Angle', ANGLE_OPTIONS),
        lockedSelectField('depth_of_field', 'Depth of Field', DOF_OPTIONS)
      ) +
    '</div>') +
    advSection('Brand', 'Commercial and brand-oriented generation settings.', '<div class="yis-adv-section__grid">' +
      lockedFieldHtml('commercial_mode', 'Commercial Mode', '<label class="yis-check-row"><input type="checkbox" data-yis-setting="commercial_mode"' + (state.settings.commercial_mode !== false ? ' checked' : '') + fieldDisabledAttr('commercial_mode') + '> Commercial Quality</label>') +
      fieldRow(
        lockedSelectField('brand_tone', 'Brand Tone', s.brand_tones || []),
        lockedSelectField('product_type', 'Product Type', s.product_types || [])
      ) +
    '</div>') +
    advSection('Prompt', 'Optimized and negative prompt overrides.', '<div class="yis-adv-section__grid">' +
      (optimized ? lockedFieldHtml('optimized_prompt', 'Optimized Prompt', '<textarea readonly rows="4" class="yis-optimized-readonly">' + esc(optimized) + '</textarea>') : '') +
      lockedFieldHtml('negative_prompt', 'Negative Prompt', '<textarea data-yis-setting="negative_prompt" rows="3" aria-label="Negative prompt"' + fieldDisabledAttr('negative_prompt') + '>' + esc(state.settings.negative_prompt || '') + '</textarea>') +
    '</div>') +
    advSection('Output', 'Batch size, format, and transparency.', '<div class="yis-adv-section__grid">' +
      fieldRow(
        lockedSelectField('image_count', 'Image Count', (s.image_counts || [1, 2, 3, 4]).map(function (n) { return { id: n, label: String(n) }; })),
        lockedSelectField('output_format', 'Output Format', FORMAT_OPTIONS)
      ) +
      lockedFieldHtml('transparent_bg', 'Transparent Background', '<label class="yis-check-row"><input type="checkbox" data-yis-setting="transparent_bg"' + (transparentOn ? ' checked' : '') + fieldDisabledAttr('background') + '> Enable transparent background</label>') +
      developerInfoHtml() +
    '</div>');
  }

  function advSection(title, desc, inner) {
    return '<div class="yis-adv-section"><h4 class="yis-adv-section__title">' + esc(title) + '</h4>' +
      '<p class="yis-adv-section__desc">' + esc(desc) + '</p>' + inner + '</div>';
  }

  function fieldDisabledAttr(key) {
    return (isFieldAuto(key) && state.smartAuto) ? ' disabled' : '';
  }

  function lockedSelectField(key, label, options) {
    return lockedFieldHtml(key, label, '<select data-yis-setting="' + key + '"' + fieldDisabledAttr(key) + '>' +
      options.map(function (o) {
        var v = typeof o === 'object' ? o.id : o;
        var l = typeof o === 'object' ? (o.label || o.id) : o;
        return '<option value="' + esc(String(v)) + '"' + (String(state.settings[key]) === String(v) ? ' selected' : '') + '>' + esc(String(l)) + '</option>';
      }).join('') + '</select>');
  }

  function lockedFieldHtml(key, label, inputHtml) {
    var locked = isFieldAuto(key) && state.smartAuto;
    var lockBtn = state.smartAuto && key !== 'optimized_prompt'
      ? '<button type="button" class="yis-lock-btn' + (locked ? ' is-auto' : ' is-manual') + '" data-yis-field-lock="' + esc(key) + '" aria-pressed="' + (locked ? 'true' : 'false') + '">' + (locked ? 'Auto' : 'Manual') + '</button>'
      : '';
    return '<div class="yis-locked-field' + (locked ? ' is-locked' : '') + '" data-yis-field="' + esc(key) + '">' +
      '<div class="yis-locked-field__head"><label>' + esc(label) + '</label>' + lockBtn + '</div>' +
      '<div class="yis-locked-field__body">' + inputHtml + '</div></div>';
  }

  function refreshAdvancedInner(root) {
    var inner = root && root.querySelector('#yis-advanced-inner');
    if (!inner) return;
    inner.innerHTML = advancedFieldsInnerHtml();
    updateProviderUX(root);
  }

  function providerOptionsHtml() {
    var html = '<option value="auto"' + ((state.settings.default_provider || 'auto') === 'auto' ? ' selected' : '') + '>Auto (Best Available)</option>';
    html += state.providers.filter(function (p) { return p.id !== 'auto'; }).map(function (p) {
      return '<option value="' + esc(p.id) + '"' + ((state.settings.default_provider || 'auto') === p.id ? ' selected' : '') + '>' +
        esc(providerOptionLabel(p)) + '</option>';
    }).join('');
    return html;
  }

  function isDebugMode() {
    var cfg = (Core && Core.config) || global.YooYStudio || {};
    return !!(cfg.debug || global.YOOY_DEBUG || (Core && Core.debug && Core.debug()));
  }

  function isStudioAdmin() {
    return !!(Core && Core.config && Core.config.isAdmin);
  }

  function shouldLogOpenAiPayload(payload) {
    var provider = String((payload && (payload.provider || payload.default_provider)) || 'auto').toLowerCase();
    return provider === 'openai' || provider === 'openai-image' || provider === 'auto';
  }

  function mapBackgroundToOpenAi(background) {
    return String(background || '') === 'transparent' ? 'transparent' : 'opaque';
  }

  function buildOpenAiPreview(payload) {
    payload = payload || {};
    return {
      provider: payload.provider || payload.default_provider || 'openai',
      model: payload.model || payload.default_model || 'gpt-image-1',
      size: payload.size || mappedSizeForAspect(payload.aspect_ratio || '1:1') || 'auto',
      quality: payload.quality || 'standard',
      background: mapBackgroundToOpenAi(payload.background || 'studio_white'),
      output_format: payload.output_format || 'png',
      prompt: payload.prompt || ''
    };
  }

  function logGenerationRequest(payload) {
    if (!global.console || !global.console.log) return;
    payload = payload || {};
    var health = state.lastProviderHealth && state.lastProviderHealth.resolved ? state.lastProviderHealth.resolved : null;
    var resolvedProvider = (health && health.label) || payload.provider || 'auto';
    var model = payload.model || (health && health.model) || 'auto';
    var size = payload.size || payload.resolution || 'auto';
    var quality = payload.quality || (state.generationMode === 'premium' ? 'high' : 'standard');
    var commercial = (payload.commercial_mode !== false && payload.commercial !== false) ? 'Yes' : 'No';
    var promptLen = String(payload.prompt || payload.user_prompt || '').length;
    global.console.log(
      '===== YooY Image Generation =====\n' +
      'Resolved Provider : ' + resolvedProvider + '\n' +
      'Model             : ' + model + '\n' +
      'Size              : ' + size + '\n' +
      'Quality           : ' + quality + '\n' +
      'Commercial        : ' + commercial + '\n' +
      'Prompt Length     : ' + promptLen + '\n' +
      '================================='
    );
  }

  function logOpenAiRequestPreview(payload) {
    if (!isStudioAdmin() || !shouldLogOpenAiPayload(payload)) return;
    if (!global.console || !global.console.log) return;
    var preview = buildOpenAiPreview(payload);
    global.console.log(
      '===== OpenAI Request =====\n' +
      'provider: ' + preview.provider + '\n' +
      'model: ' + preview.model + '\n' +
      'size: ' + preview.size + '\n' +
      'quality: ' + preview.quality + '\n' +
      'background: ' + preview.background + '\n' +
      'output_format: ' + preview.output_format + '\n' +
      'prompt: ' + preview.prompt + '\n' +
      '=========================='
    );
  }

  function logOpenAiResponse(data) {
    if (!isStudioAdmin() || !global.console || !global.console.log) return;
    var debug = (data && data.meta && data.meta.openai_debug) || (data && data.openai_debug);
    if (debug) {
      global.console.log('===== OpenAI API Request JSON =====');
      global.console.log(JSON.stringify(debug.request || {}, null, 2));
      global.console.log('===== OpenAI Response =====');
      global.console.log(JSON.stringify(debug.response || {}, null, 2));
      global.console.log('============================');
      return;
    }
    if (data && data.raw) {
      global.console.log('===== OpenAI Response =====');
      global.console.log(JSON.stringify(data.raw, null, 2));
      global.console.log('===========================');
    }
  }

  function developerInfoHtml() {
    if (!isDebugMode()) return '';
    var info = state.lastDebugInfo || {};
    var pid = info.provider || selectedProviderId();
    var model = info.model || state.settings.default_model || state.settings.model || '';
    var size = state.settings.size || state.settings.resolution || '';
    var mapped = info.apiSize || mappedSizeForAspect(state.settings.aspect_ratio || '1:1');
    var latency = info.latency != null ? (info.latency + ' ms') : '—';
    return '<details class="yis-dev-info" id="yis-dev-info"><summary>Developer Info</summary>' +
      '<div class="yis-dev-info-grid">' +
      '<div><b>Provider</b><span data-yis-dev="provider">' + esc(pid) + '</span></div>' +
      '<div><b>Model</b><span data-yis-dev="model">' + esc(model) + '</span></div>' +
      '<div><b>API Size</b><span data-yis-dev="api-size">' + esc(mapped || size || '—') + '</span></div>' +
      '<div><b>Latency</b><span data-yis-dev="latency">' + esc(latency) + '</span></div>' +
      '</div></details>';
  }

  function refreshDeveloperInfoPanel(root) {
    if (!isDebugMode() || !root) return;
    var panel = root.querySelector('#yis-dev-info');
    if (!panel) return;
    var info = state.lastDebugInfo || {};
    var set = function (key, val) {
      var el = panel.querySelector('[data-yis-dev="' + key + '"]');
      if (el) el.textContent = val || '—';
    };
    set('provider', info.provider || selectedProviderId());
    set('model', info.model || state.settings.default_model || state.settings.model || '');
    set('api-size', info.apiSize || mappedSizeForAspect(state.settings.aspect_ratio || '1:1') || state.settings.size || '—');
    set('latency', info.latency != null ? (info.latency + ' ms') : '—');
  }

  function isMockModel(model) {
    return String(model || '').indexOf('mock-') === 0;
  }

  function isMockProviderId(providerId) {
    var pid = providerId || selectedProviderId();
    if (!pid || pid === 'auto') return false;
    if (pid === 'mock' || pid === 'mock-image' || pid.indexOf('mock-') === 0) return true;
    var p = findProvider(pid);
    return !!(p && p.is_mock);
  }

  function modelIdsForProvider(providerId) {
    var pid = providerId || selectedProviderId();
    if (pid === 'auto') pid = resolvedPreviewProviderId();
    var p = findProvider(pid);
    if (p && p.models && p.models.length) {
      return p.models.map(function (m) { return (m && m.id) ? m.id : m; }).filter(Boolean);
    }
    return MODEL_DEFAULTS[pid] ? [MODEL_DEFAULTS[pid]] : ['gpt-image-1'];
  }

  function defaultModelForProvider(providerId) {
    var pid = providerId || selectedProviderId();
    if (pid === 'auto') return '';
    var ids = modelIdsForProvider(pid);
    if (MODEL_DEFAULTS[pid] && ids.indexOf(MODEL_DEFAULTS[pid]) >= 0) {
      return MODEL_DEFAULTS[pid];
    }
    return ids[0] || 'gpt-image-1';
  }

  function syncModelForProvider(providerId, force) {
    var pid = providerId || selectedProviderId();
    if (pid === 'auto') {
      if (force) {
        delete state.settings.default_model;
        delete state.settings.model;
      }
      return;
    }
    var allowed = modelIdsForProvider(pid);
    var current = state.settings.default_model || state.settings.model || '';
    var incompatible = isMockProviderId(pid) ? !isMockModel(current) : isMockModel(current);
    if (force || !current || allowed.indexOf(current) < 0 || incompatible) {
      state.settings.default_model = defaultModelForProvider(pid);
      state.settings.model = state.settings.default_model;
    }
  }

  function modelSelectHtml() {
    var pid = selectedProviderId();
    if (pid === 'auto') {
      return '<option value="" selected>Auto (서버가 결정)</option>';
    }
    var models = modelIdsForProvider(pid);
    var cur = state.settings.default_model || state.settings.model || defaultModelForProvider(pid);
    if (models.indexOf(cur) < 0) cur = models[0];
    state.settings.default_model = cur;
    state.settings.model = cur;
    return models.map(function (id) {
      return '<option value="' + esc(id) + '"' + (cur === id ? ' selected' : '') + '>' + esc(id) + '</option>';
    }).join('');
  }

  function isExplicitLiveSelection(id) {
    var pid = id || selectedProviderId();
    if (!pid || pid === 'auto') return false;
    if (pid === 'mock' || pid.indexOf('mock-') === 0) return false;
    var p = findProvider(pid);
    if (p && p.is_mock) return false;
    return true;
  }

  function hasTestedLiveProvider() {
    return state.providers.some(function (p) {
      return !p.is_mock && (p.auto_eligible || p.usable || p.status === 'connected');
    });
  }

  function providerOptionLabel(p) {
    var name = p.name || p.id;
    var status = p.status_label || '';
    if (!status) {
      if (p.status === 'connected') status = 'Connected';
      else if (p.status === 'not_tested') status = 'Not Tested';
      else if (p.status === 'available') status = 'Available';
      else if (p.status === 'not_configured') status = 'Not Configured';
    }
    return status ? name + ' — ' + status : name;
  }

  function getProviderPreflight() {
    var id = selectedProviderId();
    if (id === 'auto') {
      if (!hasTestedLiveProvider()) {
        return {
          ok: true,
          mode: 'auto_mock',
          message: '연결된 실제 Provider가 없습니다. Auto는 Mock Image를 사용합니다.'
        };
      }
      return { ok: true, mode: 'auto', message: 'Auto가 최적의 Provider/Model을 선택합니다.' };
    }
    if (!isExplicitLiveSelection(id)) return { ok: true, mode: 'mock' };
    var p = findProvider(id);
    if (!p) return { ok: true, mode: 'unknown' };
    if (p.usable || p.status === 'connected') return { ok: true, mode: 'live', provider: p };
    if (p.status === 'not_tested' || p.error_code === 'provider_not_tested') {
      return {
        ok: false,
        code: 'provider_not_tested',
        provider: p,
        message: (p.name || 'This provider') + ' must pass Test Connection before use.'
      };
    }
    if (p.status === 'not_configured' || p.error_code === 'provider_not_configured') {
      return {
        ok: false,
        code: 'provider_not_configured',
        provider: p,
        message: (p.name || 'This provider') + ' API key is missing. Configure it in Operations Center.'
      };
    }
    return {
      ok: false,
      code: p.error_code || 'provider_unavailable',
      provider: p,
      message: (p.name || 'This provider') + ' is not available.'
    };
  }

  function providerErrorActionsHtml(code) {
    var isAdmin = !!(Core && Core.config && Core.config.isAdmin);
    var html = '<div class="yis-error-actions">';
    if (code === 'provider_not_tested' && isAdmin) {
      html += '<button class="yis-btn-secondary" type="button" data-yis-action="open-ops">Open Operations Center</button>';
    }
    if (code === 'provider_not_tested' || code === 'provider_not_configured') {
      html += '<button class="yis-btn-secondary" type="button" data-yis-action="use-auto">Use Auto/Mock for Test</button>';
    }
    html += '</div>';
    return html;
  }

  function refreshOutputSizeControl(root) {
    var select = root && root.querySelector('[data-yis-setting="output_size"]');
    if (!select) return;
    var cur = currentOutputSizeId();
    select.innerHTML = outputSizeOptionsHtml();
    select.value = cur;
  }

  function updateProviderUX(root) {
    var pre = getProviderPreflight();
    var preEl = $('#yis-provider-preflight', root);
    if (preEl) {
      if (pre.mode === 'auto_mock') {
        preEl.hidden = false;
        preEl.className = 'yis-provider-preflight yis-provider-preflight--info';
        preEl.innerHTML = '<strong>Auto routing</strong><p>' + esc(pre.message) + '</p>';
      } else if (!pre.ok) {
        preEl.hidden = false;
        preEl.className = 'yis-provider-preflight yis-provider-preflight--warn';
        preEl.innerHTML = '<strong>Provider not ready</strong><p>' + esc(pre.message) + '</p>' +
          providerErrorActionsHtml(pre.code);
      } else {
        preEl.hidden = true;
        preEl.innerHTML = '';
      }
    }
    var btn = $('#yis-generate', root);
    if (btn && !state.generating) {
      var blocked = !pre.ok && (pre.code === 'provider_not_tested' || pre.code === 'provider_not_configured');
      btn.disabled = blocked;
      btn.title = blocked ? pre.message : '';
      if (!blocked) btn.textContent = 'Generate · ' + creditLabel();
    }
    var select = root && root.querySelector('[data-yis-setting="default_provider"]');
    if (select) {
      select.setAttribute('aria-describedby', preEl && !preEl.hidden ? 'yis-provider-preflight' : '');
    }
  }

  function switchProviderToAuto(root) {
    state.settings.default_provider = 'auto';
    var select = root && root.querySelector('[data-yis-setting="default_provider"]');
    if (select) select.value = 'auto';
    clearGenerateError(root);
    updateProviderUX(root);
    var api = getImageApi();
    if (api && api.updateSettings) {
      api.updateSettings(state.settings).catch(function () {});
    }
  }

  function openOperationsCenter() {
    if (global.YooYStudioRoute) {
      global.YooYStudioRoute('admin-console');
      return;
    }
    document.dispatchEvent(new CustomEvent('yoy:route', { detail: { route: 'admin-console' } }));
  }

  function showGenerateInfo(root, message) {
    var el = $('#yis-generate-info', root);
    if (!el) return;
    el.textContent = message || '';
    el.hidden = !message;
  }

  function providerBillingFailureMessages(data) {
    if (!data) return null;
    if (data.user_message) {
      return {
        primary: data.user_message,
        secondary: data.user_credits_message || 'YooY 사용자 크레딧과는 별도입니다.'
      };
    }
    if (data.fallback_reason === 'replicate_insufficient_credit') {
      return {
        primary: 'AI 공급업체 API 계정의 크레딧이 부족합니다. 다른 공급업체로 다시 시도합니다.',
        secondary: data.user_credits_message || 'YooY 사용자 크레딧과는 별도입니다.'
      };
    }
    var err = String(data.error || '').toLowerCase();
    var rawTitle = data.raw && data.raw.title ? String(data.raw.title).toLowerCase() : '';
    if (/insufficient credit|billing|payment required|unauthorized|invalid api token|rate limit/.test(err) ||
        /insufficient credit|billing|payment required/.test(rawTitle)) {
      var provider = data.provider_used || data.provider || data.catalog_provider || 'Replicate';
      var label = provider === 'replicate' || provider === 'flux' ? 'Replicate' : provider;
      return {
        primary: label + ' API 계정의 크레딧이 부족합니다.',
        secondary: 'YooY 사용자 크레딧과는 별도입니다.'
      };
    }
    return null;
  }

  function replicateBillingUserMessage(data) {
    var msgs = providerBillingFailureMessages(data);
    if (!msgs) return '';
    return msgs.primary + ' ' + msgs.secondary;
  }

  function showProviderBillingError(root, data) {
    var msgs = providerBillingFailureMessages(data);
    if (!msgs) {
      showGenerateError(root, data && data.error ? data.error : 'Generation failed.');
      return;
    }
    var area = root && root.querySelector('.yis-prompt-area');
    if (!area) return;
    var existing = area.querySelector('#yis-generate-error');
    if (existing) existing.remove();
    area.insertAdjacentHTML('beforeend',
      '<div class="yis-error yis-error--provider-billing" id="yis-generate-error" role="alert">' +
        '<strong>' + esc(msgs.primary) + '</strong>' +
        '<p class="yis-error-copy">' + esc(msgs.secondary) + '</p>' +
      '</div>');
  }

  function jobMissingProviderReference(job) {
    if (!job) return false;
    if (hasOutputAsset(job)) return false;
    return !job.provider_job_id;
  }

  function showGenerateError(root, errOrMessage) {
    var area = root && root.querySelector('.yis-prompt-area');
    if (!area) return;
    var existing = area.querySelector('#yis-generate-error');
    if (existing) existing.remove();

    var d = null;
    var message = '';
    var code = '';
    if (errOrMessage && errOrMessage.details) {
      d = errOrMessage.details;
      message = d.message || errOrMessage.message || 'Generation failed.';
      code = d.code || errOrMessage.code || '';
    } else if (errOrMessage && typeof errOrMessage === 'object' && errOrMessage.stage) {
      d = errOrMessage;
      message = d.message || 'Generation failed.';
      code = d.code || '';
    } else if (errOrMessage && errOrMessage.message) {
      message = errOrMessage.message;
      d = errOrMessage.details || null;
      code = (d && d.code) || errOrMessage.code || '';
    } else {
      message = String(errOrMessage || 'Generation failed.');
    }

    // A REST route error is NOT a provider / OpenAI / billing failure.
    // Show it as an infrastructure (endpoint registration) problem and always
    // surface the exact endpoint + tried URLs so the failure is diagnosable.
    if (code === 'rest_no_route' || (errOrMessage && errOrMessage.restNoRoute)) {
      var rd = (d && d.endpoint) ? d : ((errOrMessage && errOrMessage.details) || {});
      var routeMeta = '';
      var addRow = function (label, val) {
        if (!val) return;
        routeMeta += '<span><b>' + esc(label) + ':</b> ' + esc(String(val)) + '</span>';
      };
      addRow('method', rd.method);
      addRow('endpoint', rd.endpoint);
      addRow('tried wp-json', rd.tried_wp_json);
      addRow('tried rest_route', rd.tried_route || rd.tried_rest_route);
      if (rd.missing && rd.missing.length) addRow('missing', rd.missing.join(', '));
      if (rd.registered_similar && rd.registered_similar.length) {
        addRow('registered', rd.registered_similar.slice(0, 6).join(', '));
      }
      if (global.console && global.console.error) {
        global.console.error('[ImageStudio] rest_no_route surfaced to UI', rd);
      }
      area.insertAdjacentHTML('beforeend',
        '<div class="yis-error yis-error--route" id="yis-generate-error" role="alert">' +
          '<strong>REST API Route Not Found</strong>' +
          '<p class="yis-error-copy">The requested endpoint is not registered. 이 오류는 OpenAI/공급업체/크레딧 문제가 아니라 WordPress REST 라우트 문제입니다.</p>' +
          errorAnalysisHtml(errOrMessage) +
          (routeMeta ? '<div class="yis-error-meta yis-error-meta--route">' + routeMeta + '</div>' : '') +
          diagnosticReportButtonsHtml() +
        '</div>');
      return;
    }

    if (code === 'provider_model_mismatch') {
      message = message || 'Selected model does not match the provider. Choose a valid model for this provider.';
    }
    if (code === 'provider_size_mismatch') {
      message = message || 'Selected image size is not supported for this provider/model combination.';
    }

    if (code === 'provider_not_tested') {
      var providerName = (d && d.provider_name) || (d && d.provider_requested) || 'OpenAI Image';
      message = providerName + ' must pass Test Connection before use.';
      area.insertAdjacentHTML('beforeend',
        '<div class="yis-error yis-error--provider" id="yis-generate-error" role="alert">' +
          '<strong>' + esc(message) + '</strong>' +
          '<p class="yis-error-copy">Run <em>Test Connection</em> in Operations Center before using this live provider.</p>' +
          providerErrorActionsHtml(code) +
        '</div>');
      return;
    }

    var meta = '';
    if (d) {
      meta = '<div class="yis-error-meta">' +
        (d.stage ? '<span><b>' + esc(d.stage) + '</b></span>' : '') +
        (d.code ? '<span>' + esc(d.code) + '</span>' : '') +
        (d.provider_requested ? '<span>requested: ' + esc(d.provider_requested) + '</span>' : '') +
        (d.provider_resolved ? '<span>resolved: ' + esc(d.provider_resolved) + '</span>' : '') +
        (d.model_requested ? '<span>model: ' + esc(d.model_requested) + '</span>' : '') +
        (d.reason ? '<span>' + esc(d.reason) + '</span>' : '') +
        (d.missing_fields && d.missing_fields.length ? '<span>missing: ' + esc(d.missing_fields.join(', ')) + '</span>' : '') +
        '</div>';
      if (d.code === 'provider_not_configured') {
        meta += providerErrorActionsHtml(d.code);
      }
    }

    area.insertAdjacentHTML('beforeend',
      '<div class="yis-error" id="yis-generate-error" role="alert"><strong>' + esc(message) + '</strong>' + meta + '</div>');
  }

  function clearGenerateError(root) {
    var el = root && root.querySelector('#yis-generate-error');
    if (el) el.remove();
    showGenerateInfo(root, '');
  }

  function notifyGalleryUpdated() {
    if (Core && Core.notifyGalleryUpdated) {
      Core.notifyGalleryUpdated();
    } else {
      document.dispatchEvent(new CustomEvent('yoy:gallery:updated'));
    }
    if (window.YooYGallery && window.YooYGallery.reload) {
      window.YooYGallery.reload();
    }
  }

  function tab(id, label) {
    return '<button class="yis-tab' + (id === 'generate' ? ' is-active' : '') + '" data-yis-tab="' + id + '" type="button">' + label + '</button>';
  }

  function bindEvents(root) {
    root.addEventListener('click', function (e) {
      try {
      var fixEl = e.target.closest('[data-yoy-fix]');
      if (fixEl) {
        var D = global.YooYDiagnostics;
        if (D && D.fix) {
          fixEl.disabled = true;
          var orig = fixEl.textContent;
          fixEl.textContent = '수정 중…';
          D.fix(fixEl.getAttribute('data-yoy-fix')).then(function () {
            verifySystemThen(root, function () {});
          }).catch(function () {
            fixEl.disabled = false;
            fixEl.textContent = orig;
          });
        }
        return;
      }
      var repEl = e.target.closest('[data-yoy-report]');
      if (repEl) {
        var Dr = global.YooYDiagnostics;
        if (Dr && Dr.report) {
          Dr.report(repEl.getAttribute('data-yoy-report'), { error: state.lastGenerateError, context: { studio: 'image-studio' } });
        }
        return;
      }

      var t = e.target.closest('[data-yis-tab]');
      if (t) { state.tab = t.dataset.yisTab; setTab(root); renderTab(root); return; }

      if (e.target.closest('#yis-edit-run')) { doEdit(root); return; }
      if (e.target.closest('#yis-save-settings')) { saveSettings(root); return; }

      var reuse = e.target.closest('[data-yis-reuse]');
      if (reuse) { reusePrompt(reuse.dataset.yisReuse, reuse.dataset.yisSource || 'history', root); return; }

      var stylePick = e.target.closest('[data-yis-style-pick]');
      if (stylePick) {
        state.recommendedStyleId = stylePick.dataset.yisStylePick;
        refreshStyleRecommendations(root);
        return;
      }

      var modeBtn = e.target.closest('[data-yis-mode]');
      if (modeBtn) {
        setStudioMode(modeBtn.dataset.yisMode, root);
        return;
      }

      var speedBtn = e.target.closest('[data-yis-speed]');
      if (speedBtn && !state.generating) {
        state.generationMode = speedBtn.dataset.yisSpeed === 'premium' ? 'premium' : 'fast';
        state.settings.generation_mode = state.generationMode;
        renderTab(root);
        bindGenerateButton(root);
        loadProviderHealth(root);
        return;
      }

      var lockBtn = e.target.closest('[data-yis-field-lock]');
      if (lockBtn) {
        toggleFieldLock(lockBtn.dataset.yisFieldLock, root);
        return;
      }

      var galPick = e.target.closest('[data-yis-gallery-id]');
      if (galPick) {
        state.gallerySelectedId = galPick.dataset.yisGalleryId;
        renderGalleryDetail(root);
        return;
      }

      var galAction = e.target.closest('[data-yis-gallery-action]');
      if (galAction) {
        handleGalleryAction(galAction.dataset.yisGalleryAction, galAction.dataset.yisGalleryId, root);
        return;
      }

      var thumb = e.target.closest('[data-yis-result-thumb]');
      if (thumb) {
        setActiveResultIndex(parseInt(thumb.dataset.yisResultThumb, 10) || 0, root);
        return;
      }

      var img = e.target.closest('[data-yis-select]');
      if (img) { state.selectedImage = img.dataset.yisSelect; renderTab(root); return; }

      var tool = e.target.closest('[data-yis-edit]');
      if (tool) { state.editMode = tool.dataset.yisEdit; renderTab(root); return; }

      var action = e.target.closest('[data-yis-action]');
      if (action) {
        if (action.dataset.yisAction === 'open-ops') {
          openOperationsCenter();
          return;
        }
        if (action.dataset.yisAction === 'use-auto') {
          switchProviderToAuto(root);
          return;
        }
        if (action.dataset.yisAction === 'seed-random') {
          state.settings.seed = -1;
          var seedInput = root.querySelector('[data-yis-setting="seed"]');
          if (seedInput) seedInput.value = '-1';
          return;
        }
        handleResultAction(action.dataset.yisAction, root);
        return;
      }

      } catch (clickErr) {
        debugLog('click handler error', clickErr);
        showGenerateError(root, clickErr.message || 'UI action failed.');
      }
    });

    root.addEventListener('change', function (e) {
      if (e.target.matches('[data-yis-setting]')) {
        syncPromptFields(root);
        var k = e.target.dataset.yisSetting;
        if (k === 'output_size') {
          applyOutputSizeSelection(e.target.value);
        } else if (k === 'transparent_bg') {
          state.settings.background = e.target.checked ? 'transparent' : (state.settings.background === 'transparent' ? 'studio_white' : state.settings.background);
        } else if (k === 'commercial_mode') {
          state.settings.commercial_mode = e.target.checked;
        } else {
          state.settings[k] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
        }
        if (k === 'default_provider') {
          syncModelForProvider(state.settings.default_provider, true);
          syncSizeForProvider(true);
          var modelSelect = root.querySelector('#yis-model-select');
          if (modelSelect) modelSelect.innerHTML = modelSelectHtml();
          refreshOutputSizeControl(root);
          updateProviderUX(root);
          loadProviderHealth(root);
        }
        if (k === 'default_model' || k === 'quality') {
          loadProviderHealth(root);
        }
        if (k === 'default_model') {
          state.settings.model = state.settings.default_model;
          syncSizeForProvider(true);
          refreshOutputSizeControl(root);
        }
        if (k === 'output_size' || k === 'default_provider' || k === 'default_model') {
          updatePreviewRatio(root);
        }
        refreshEstimate().then(function () {
          updateCreditsUI(root);
        });
      }
    });

    root.addEventListener('input', function (e) {
      if (e.target.id === 'yis-prompt') state.settings.last_prompt = e.target.value;
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
      case 'gallery': renderGallery(ws, ctrl, root); break;
      case 'history': renderHistory(ws, ctrl); break;
      case 'settings': renderSettings(ws, ctrl); break;
    }
  }

  function generationModeHtml() {
    var fast = state.generationMode !== 'premium';
    return '<div class="yis-speed-toggle" role="radiogroup" aria-label="Generation speed">' +
      '<button type="button" class="yis-speed-opt' + (fast ? ' is-active' : '') + '" data-yis-speed="fast">Fast</button>' +
      '<button type="button" class="yis-speed-opt' + (!fast ? ' is-active' : '') + '" data-yis-speed="premium">Premium</button>' +
      '<p class="yis-speed-hint">' + (fast ? '빠른 응답 우선 (standard, 1장)' : '품질 우선 (고급 프롬프트 최적화)') + '</p></div>';
  }

  function generationStepLabel(step) {
    var labels = {
      queued: 'Queued',
      preparing: 'Preparing',
      generating: 'Generating',
      saving: 'Saving',
      completed: 'Completed',
      failed: 'Failed',
      timeout: 'Timeout'
    };
    return labels[step] || step;
  }

  function estimateGenerationEta() {
    var pid = selectedProviderId();
    if (pid === 'auto' && hasTestedLiveProvider()) return '약 10~30초';
    if (ASYNC_POLL_PROVIDERS.indexOf(pid) >= 0) return '약 1~5분';
    if (isOpenAiProvider() || pid === 'openai-image') return '약 10~30초';
    return '약 30~90초';
  }

  function generationProgressHtml() {
    if (!state.generating) return '';
    var step = state.generateStep || 'preparing';
    var steps = ['queued', 'preparing', 'generating', 'saving', 'completed'];
    var curIdx = Math.max(0, steps.indexOf(step));
    var stepHtml = steps.map(function (s, i) {
      var cls = i < curIdx ? ' is-done' : (i === curIdx ? ' is-active' : '');
      return '<span class="yis-gen-step' + cls + '">' + generationStepLabel(s) + '</span>';
    }).join('<span class="yis-gen-arrow">→</span>');
    var elapsed = state.generateStartedAt ? Math.round((Date.now() - state.generateStartedAt) / 1000) : 0;
    var bgMsg = elapsed >= 45
      ? '<p class="yis-gen-bg">작업은 백그라운드에서 계속 진행됩니다. 완료되면 Gallery에 저장됩니다.</p>'
      : '';
    return '<div class="yis-generate-progress" role="status" aria-live="polite">' +
      '<p class="yis-generate-progress__title">AI가 이미지를 생성 중입니다</p>' +
      '<div class="yis-generate-progress__steps">' + stepHtml + '</div>' +
      '<p class="yis-generate-progress__eta">예상 시간: ' + esc(estimateGenerationEta()) + '</p>' +
      bgMsg + '</div>';
  }

  function updateGenerateProgress(root) {
    var host = root && root.querySelector('#yis-generate-progress');
    var boardHost = root && root.querySelector('#yis-result-board-progress');
    var html = state.generating ? generationProgressHtml() : '';
    if (host) {
      if (state.generating) {
        host.innerHTML = html;
        host.hidden = false;
      } else {
        host.innerHTML = '';
        host.hidden = true;
      }
    }
    if (boardHost && state.generating) {
      boardHost.innerHTML = html;
    }
  }

  function shouldPollJob(data) {
    if (!data) return false;
    var status = data.status || '';
    if (status === 'failed' || status === 'error' || status === 'timeout') return false;
    if (status === 'completed') return false;
    if (jobMissingProviderReference(data) && replicateBillingUserMessage(data)) return false;
    if (jobMissingProviderReference(data)) return false;
    var prov = data.provider_used || data.provider || '';
    if (ASYNC_POLL_PROVIDERS.indexOf(prov) < 0 && status === 'completed') return false;
    return ['queued', 'running', 'processing', 'pending'].indexOf(status) >= 0;
  }

  function mapStatusToStep(status) {
    switch (status) {
      case 'queued': return 'queued';
      case 'pending': return 'preparing';
      case 'processing':
      case 'running': return 'generating';
      case 'completed': return 'completed';
      default: return 'generating';
    }
  }

  function renderGenerate(ws, ctrl, root) {
    var promptVal = state.settings.last_prompt || '';
    ws.innerHTML =
      '<div class="yis-header"><h2>Image Studio</h2><p class="yis-muted">Create · 추천 → Prompt → Generate → Gallery → Project</p></div>' +
      modeToggleHtml() +
      generationModeHtml() +
      resultBoardHtml() +
      '<div class="yis-prompt-area yis-generate-flow">' +
        '<div class="yai-create-ux__recs" id="yis-create-recs"></div>' +
        '<label class="yis-prompt-label" for="yis-prompt">Prompt</label>' +
        '<textarea id="yis-prompt" placeholder="예: 이재명 정치 광고, 제주 관광 포스터, 프리미엄 향수 광고...">' + esc(promptVal) + '</textarea>' +
        '<div class="yis-create-ux-actions" style="display:flex;gap:0.5rem;justify-content:flex-end;margin:0.4rem 0 0.6rem">' +
          '<button type="button" class="yis-btn-secondary" id="yis-prompt-coach">Prompt 보완</button>' +
        '</div>' +
        promptIntelligencePanelHtml() +
        '<div class="yai-create-ux__coach" id="yis-coach-panel" hidden></div>' +
        '<div class="yis-ref-block">' +
          '<label class="yis-prompt-label">Reference (Optional)</label>' +
          '<div id="yis-ref-panel-host"></div>' +
          refAnalysisCardHtml() +
        '</div>' +
        field('Aspect Ratio', '<select class="yis-output-size" data-yis-setting="output_size" id="yis-output-size" aria-label="Aspect ratio">' +
          outputSizeOptionsHtml() + '</select>') +
        '<div class="yis-actions"><button class="yis-btn-primary" id="yis-generate" type="button"' + (state.generating ? ' disabled' : '') + '>' +
        (state.generating ? '생성 중…' : 'Generate') + ' · ' + creditLabel() + '</button>' +
        '<div id="yis-generate-progress"' + (state.generating ? '' : ' hidden') + '>' + (state.generating ? generationProgressHtml() : '') + '</div>' +
        '<div class="yis-info" id="yis-generate-info" hidden></div></div>' +
        advancedSectionHtml() +
      '</div>';
    ctrl.innerHTML = sidePanelHtml();
    mountRefAssets($('#yis-ref-panel-host', ws), 'image-studio');
    updateResultBoardRatio(root);
    bindPromptFields(root);
    bindGenerateButton(root);
    bindAdvancedPanel(root);
    updateProviderUX(root);
    loadProviderHealth(root);
    bindCreateUx(root);
    bindPromptIntelligence(root);
  }

  function bindPromptIntelligence(root) {
    var editBtn = root && root.querySelector('#yis-spi-edit');
    var recomposeBtn = root && root.querySelector('#yis-spi-recompose');
    var showFinalBtn = root && root.querySelector('#yis-spi-show-final');
    var promptEl = root && root.querySelector('#yis-prompt');
    if (editBtn) {
      editBtn.addEventListener('click', function () {
        var subject = window.prompt('중심 주제를 수정하세요', (state.creativeBrief && state.creativeBrief.primary_subject) || '');
        if (subject == null) return;
        state.creativeBrief = Object.assign({}, state.creativeBrief || {}, {
          primary_subject: subject,
          raw_user_request: state.rawUserRequest || (promptEl && promptEl.value) || ''
        });
        renderTab(root);
        bindGenerateButton(root);
        bindPromptIntelligence(root);
      });
    }
    if (recomposeBtn) {
      recomposeBtn.addEventListener('click', function () {
        var prompt = promptEl ? promptEl.value : '';
        state.rawUserRequest = state.rawUserRequest || prompt;
        previewSmartAuto(root);
        fetchServerCompose(prompt, function (composed) {
          applyServerCompose(prompt, composed);
          renderTab(root);
          bindGenerateButton(root);
          bindPromptIntelligence(root);
        });
      });
    }
    if (showFinalBtn) {
      showFinalBtn.addEventListener('click', function () {
        state.showFinalPrompt = !state.showFinalPrompt;
        if (!state.lastOptimizedPrompt && promptEl) {
          fetchServerCompose(promptEl.value, function (composed) {
            applyServerCompose(promptEl.value, composed);
            renderTab(root);
            bindGenerateButton(root);
            bindPromptIntelligence(root);
          });
          return;
        }
        renderTab(root);
        bindGenerateButton(root);
        bindPromptIntelligence(root);
      });
    }
  }

  function bindCreateUx(root) {
    var recEl = root && root.querySelector('#yis-create-recs');
    var promptEl = root && root.querySelector('#yis-prompt');
    var coachBtn = root && root.querySelector('#yis-prompt-coach');
    var coachPanel = root && root.querySelector('#yis-coach-panel');
    if (window.YooYCreateUX && recEl) {
      var showRecs = !promptEl || !String(promptEl.value || '').trim();
      if (showRecs) {
        window.YooYCreateUX.loadRecommendations(recEl, function (card) {
          if (promptEl) promptEl.value = (card && (card.seed || card.seed_prompt || card.title)) || '';
          state.settings.last_prompt = promptEl ? promptEl.value : '';
        });
      } else {
        recEl.innerHTML = '';
      }
    }
    if (coachBtn && window.YooYCreateUX) {
      coachBtn.addEventListener('click', function () {
        var seed = promptEl ? promptEl.value : '';
        window.YooYCreateUX.composePrompt(seed, coachPanel, function (composed) {
          if (promptEl) promptEl.value = composed;
          state.settings.last_prompt = composed;
        });
      });
    }
    if (promptEl && recEl) {
      promptEl.addEventListener('input', function () {
        if (String(promptEl.value || '').trim()) {
          recEl.innerHTML = '';
        } else if (window.YooYCreateUX) {
          window.YooYCreateUX.loadRecommendations(recEl, function (card) {
            promptEl.value = (card && (card.seed || card.seed_prompt || card.title)) || '';
            state.settings.last_prompt = promptEl.value;
          });
        }
      });
    }
  }

  function sidePanelHtml() {
    return aiEnginePanelHtml() +
      autoSelectedCardHtml() +
      '<div class="yis-credits-bar">' + creditLabel() + '</div>';
  }

  function engineToneClass(tone) {
    switch (tone) {
      case 'ok': return 'is-ok';
      case 'error': return 'is-error';
      case 'warn': return 'is-warn';
      case 'muted': return 'is-muted';
      default: return 'is-pending';
    }
  }

  function aiEnginePanelHtml() {
    var h = state.lastProviderHealth;
    var inner;
    if (state.providerHealthLoading && !h) {
      inner = '<p class="yis-ai-engine__loading">엔진 확인 중…</p>';
    } else if (!h || !h.resolved) {
      inner = '<p class="yis-ai-engine__loading">AI Engine 상태를 불러오는 중…</p>';
    } else {
      var r = h.resolved;
      var tone = engineToneClass(r.status_tone);
      var warn = (!r.is_openai && !r.is_mock)
        ? '<p class="yis-ai-engine__warn">현재 OpenAI가 아닌 다른 공급업체로 생성됩니다.</p>'
        : (r.is_mock ? '<p class="yis-ai-engine__warn">실제 API 없이 Mock으로 생성됩니다.</p>' : '');
      inner =
        '<div class="yis-ai-engine__row">' +
          '<span class="yis-ai-engine__dot ' + tone + '"></span>' +
          '<div class="yis-ai-engine__main">' +
            '<strong class="yis-ai-engine__name">' + esc(r.label || '—') + '</strong>' +
            (r.model ? '<span class="yis-ai-engine__model">' + esc(r.model) + '</span>' : '') +
          '</div>' +
          '<span class="yis-ai-engine__status ' + tone + '">' + esc(r.status_label || '—') + '</span>' +
        '</div>' + warn;
    }
    return '<div class="yis-ai-engine" id="yis-ai-engine"><h4 class="yis-ai-engine__title">AI Engine</h4>' + inner + '</div>';
  }

  function refreshAiEnginePanel(root) {
    var el = (root || document).querySelector('#yis-ai-engine');
    if (!el) return;
    el.outerHTML = aiEnginePanelHtml();
  }

  function loadProviderHealth(root) {
    var api = getImageApi();
    if (!api || !api.providerHealth) return;
    if (state.providerHealthLoading) return;
    state.providerHealthLoading = true;
    refreshAiEnginePanel(root);
    api.providerHealth().then(function (res) {
      var data = (res && res.data) ? res.data : res;
      state.lastProviderHealth = data || null;
      state.providerHealthLoading = false;
      refreshAiEnginePanel(root);
    }).catch(function () {
      state.providerHealthLoading = false;
      refreshAiEnginePanel(root);
    });
  }

  function bindGenerateButton(root) {
    var btn = root.querySelector('#yis-generate');
    if (!btn) return;
    if (btn.dataset.yisGenerateBound === '1') return;
    btn.dataset.yisGenerateBound = '1';
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      global.YooYLastGenerateClick = Date.now();
      debugLog('generate click (direct bind)');
      doGenerate(root);
    });
  }

  var globalGenerateHandlerInstalled = false;
  function installGlobalGenerateHandler() {
    if (globalGenerateHandlerInstalled) return;
    globalGenerateHandlerInstalled = true;
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('#yis-generate') : null;
      if (!btn) return;
      if (btn.dataset.yisGenerateBound === '1') return;
      var root = document.getElementById('yai-image-studio');
      if (!root || !root.contains(btn)) return;
      global.YooYLastGenerateClick = Date.now();
      debugLog('generate click (capture fallback)');
      try {
        doGenerate(root);
      } catch (genErr) {
        debugLog('generate capture error', genErr);
        showGenerateError(root, genErr.message || 'Generate failed.');
      }
    }, true);
  }

  function bindPromptFields(root) {
    var promptEl = $('#yis-prompt', root);
    if (promptEl && !promptEl.dataset.bound) {
      promptEl.dataset.bound = '1';
      var recTimer = null;
      promptEl.addEventListener('input', function () {
        state.settings.last_prompt = promptEl.value;
        if (state.smartAuto) {
          clearTimeout(recTimer);
          recTimer = setTimeout(function () {
            state.recommendedStyleId = '';
            refreshStyleRecommendations(root);
          }, 200);
        }
      });
    }
  }

  function refreshStyleRecommendations(root) {
    var host = root.querySelector('#yis-style-rec-host');
    if (!host || state.smartAuto === false) return;
    host.innerHTML = styleRecommendationHtml(state.settings.last_prompt || '');
  }

  function bindAdvancedPanel(root) {
    var adv = root.querySelector('#yis-advanced');
    if (!adv || adv.dataset.bound) return;
    adv.dataset.bound = '1';
    adv.addEventListener('toggle', function () {
      state.advancedOpen = adv.open;
      var summary = adv.querySelector('.yis-advanced__summary');
      if (summary) summary.textContent = (adv.open ? '▲' : '▼') + ' Advanced Settings';
      if (adv.open) {
        if (state.smartAuto) previewSmartAuto(root);
        refreshAdvancedInner(root);
      }
    });
  }

  function refreshAutoResultPanel(root) {
    var existing = root.querySelector('#yis-auto-result');
    var html = autoSelectedCardHtml();
    if (existing) {
      existing.outerHTML = html || '';
    } else if (html) {
      var ctrl = $('#yis-controls', root);
      if (ctrl) ctrl.insertAdjacentHTML('beforeend', html);
    }
  }

  function refreshRefAnalysisPanel(root) {
    var card = root.querySelector('.yis-ref-analysis');
    var html = refAnalysisCardHtml();
    if (card) {
      card.outerHTML = html || '';
    } else if (html) {
      var host = root.querySelector('#yis-ref-panel-host');
      if (host) host.insertAdjacentHTML('afterend', html);
    }
  }

  function activeResultIndex() {
    var images = resultImages(state.lastResult);
    if (!images.length) return 0;
    if (state.selectedResultIndex != null && state.selectedResultIndex < images.length) {
      return state.selectedResultIndex;
    }
    for (var i = 0; i < images.length; i++) {
      if (images[i].url === state.selectedImage) return i;
    }
    return 0;
  }

  function setActiveResultIndex(index, root) {
    var images = resultImages(state.lastResult);
    if (!images.length || !images[index]) return;
    state.selectedResultIndex = index;
    state.selectedImage = images[index].url;
    state.activeGalleryId = (state.lastResult.job_id || 'job') + '_' + index;
    refreshResultBoard(root);
  }

  function refreshResultBoard(root) {
    var board = root && root.querySelector('#yis-result-board');
    if (board) {
      board.outerHTML = resultBoardHtml();
      updateResultBoardRatio(root);
    } else if (root) {
      renderTab(root);
    }
  }

  function resultAspectRatio(data) {
    if (data && data.aspect_ratio) return data.aspect_ratio;
    if (state.settings.size === 'auto') return '1:1';
    return state.settings.aspect_ratio || '1:1';
  }

  function resultBoardRatioClass(ratio) {
    var r = String(ratio || '1:1');
    if (r === '16:9' || r === '4:3' || r === '3:2' || r === '21:9') return 'landscape';
    if (r === '9:16' || r === '3:4' || r === '2:3') return 'portrait';
    return 'square';
  }

  function resultTitle(data) {
    var prompt = (data && (data.user_prompt || data.prompt)) || state.lastUserPrompt || state.settings.last_prompt || '';
    if (!prompt) return 'Untitled Work';
    return prompt.length > 56 ? prompt.slice(0, 56) + '…' : prompt;
  }

  function resultBoardEmptyHtml() {
    return '<section class="yis-result-board yis-result-board--empty" id="yis-result-board" aria-label="Result Board">' +
      '<div class="yis-result-board__stage">' +
        '<div class="yis-result-board__empty">' +
          '<div class="yis-result-board__empty-icon" aria-hidden="true">◇</div>' +
          '<h3>작품을 생성하세요</h3>' +
          '<p>프롬프트를 입력하고 Generate를 누르면 이곳에 작품이 크게 표시됩니다.<br>YooY AI Studio는 작품 감상 및 후속 제작을 위한 스튜디오입니다.</p>' +
        '</div>' +
      '</div></section>';
  }

  function resultBoardGeneratingHtml() {
    return '<section class="yis-result-board yis-result-board--generating" id="yis-result-board" aria-label="Result Board">' +
      '<div class="yis-result-board__stage">' +
        '<div class="yis-result-board__canvas yis-result-board__canvas--square yis-result-board__canvas--generating">' +
          '<div class="yis-result-board__generating" id="yis-result-board-progress">' + generationProgressHtml() + '</div>' +
        '</div>' +
      '</div></section>';
  }

  function resultBoardThumbsHtml(images, activeIndex) {
    return '<div class="yis-result-board__thumbs" role="tablist" aria-label="Generated works">' +
      images.map(function (img, i) {
        var thumb = img.thumbnail || img.url;
        return '<button type="button" class="yis-result-board__thumb' + (i === activeIndex ? ' is-active' : '') + '" data-yis-result-thumb="' + i + '" role="tab" aria-selected="' + (i === activeIndex ? 'true' : 'false') + '" aria-label="Work ' + (i + 1) + '">' +
          '<img src="' + esc(thumb) + '" alt="">' +
        '</button>';
      }).join('') +
    '</div>';
  }

  function resultMetaItem(label, value) {
    return '<div class="yis-result-board__meta-item"><dt>' + esc(label) + '</dt><dd>' + esc(value == null || value === '' ? '—' : String(value)) + '</dd></div>';
  }

  function resultBoardMetaHtml(data, index, total) {
    var meta = data.meta || {};
    var resMeta = meta.provider_resolution || {};
    var provider = data.provider_used || data.provider || resMeta.catalog_provider || '—';
    var model = data.model || resMeta.model || '—';
    var resolution = data.resolution || data.size || meta.resolution || state.settings.resolution || '—';
    var format = (data.output_format || state.settings.output_format || meta.output_format || 'png').toString().toUpperCase();
    var project = meta.project_name || data.project_name || data.project_title || data.project || '—';
    var created = data.created_at || data.updated_at || '';

    return '<div class="yis-result-board__meta">' +
      '<h3 class="yis-result-board__title">' + esc(resultTitle(data)) + '</h3>' +
      '<dl class="yis-result-board__meta-grid">' +
        resultMetaItem('생성 시간', created ? formatGalleryDate(created) : '—') +
        resultMetaItem('Provider', provider) +
        resultMetaItem('Model', model) +
        resultMetaItem('해상도', resolution) +
        resultMetaItem('파일 형식', format) +
        resultMetaItem('프로젝트', project) +
        (total > 1 ? resultMetaItem('작품', (index + 1) + ' / ' + total) : '') +
      '</dl></div>';
  }

  function resultToolbarBtn(action, label, danger) {
    return '<button type="button" class="yis-result-board__action' + (danger ? ' yis-result-board__action--danger' : '') + '" data-yis-action="' + esc(action) + '">' + esc(label) + '</button>';
  }

  function resultBoardToolbarHtml() {
    if (!state.lastResult || !state.lastResult.job_id) return '';
    return '<div class="yis-result-board__toolbar">' +
      '<span class="yis-result-board__toolbar-label">Action Toolbar</span>' +
      '<div class="yis-result-board__toolbar-actions">' +
        resultToolbarBtn('download', '다운로드') +
        resultToolbarBtn('project', '프로젝트 저장') +
        resultToolbarBtn('edit', 'AI 편집') +
        resultToolbarBtn('reuse', '프롬프트 재사용') +
        resultToolbarBtn('variation', '변형 생성') +
        resultToolbarBtn('upscale', '업스케일') +
        resultToolbarBtn('remove-bg', '배경 제거') +
        resultToolbarBtn('share', 'Community 공유') +
        resultToolbarBtn('marketplace', 'Marketplace 등록') +
        resultToolbarBtn('delete', '삭제', true) +
      '</div></div>';
  }

  function resultBoardHtml() {
    if (state.generating) return resultBoardGeneratingHtml();
    var images = resultImages(state.lastResult);
    if (!images.length) return resultBoardEmptyHtml();

    var idx = activeResultIndex();
    var img = images[idx];
    var data = state.lastResult;
    var ratio = resultAspectRatio(data);
    var ratioCls = resultBoardRatioClass(ratio);

    return '<section class="yis-result-board" id="yis-result-board" aria-label="Result Board">' +
      '<div class="yis-result-board__stage">' +
        '<div class="yis-result-board__canvas yis-result-board__canvas--' + ratioCls + '" data-ratio="' + esc(ratio) + '">' +
          '<img class="yis-result-board__image" src="' + esc(img.url) + '" alt="' + esc(resultTitle(data)) + '">' +
        '</div>' +
      '</div>' +
      (images.length > 1 ? resultBoardThumbsHtml(images, idx) : '') +
      resultBoardMetaHtml(data, idx, images.length) +
      resultBoardToolbarHtml() +
    '</section>';
  }

  function previewHtml() {
    return resultBoardHtml();
  }

  function resultImages(data) {
    if (!data) return [];
    if (data.images && data.images.length) {
      return data.images.filter(function (img) {
        return img && (img.url || img.image_url);
      }).map(function (img) {
        return {
          url: img.url || img.image_url,
          thumbnail: img.thumbnail || img.thumbnail_url || img.url || img.image_url
        };
      });
    }
    var primary = data.image_url || (data.output && (data.output.primary || data.output.url));
    if (primary) {
      return [{
        url: primary,
        thumbnail: data.thumbnail_url || (data.output && data.output.thumbnail) || primary
      }];
    }
    return [];
  }

  function hasOutputAsset(data) {
    return resultImages(data).length > 0;
  }

  function smartControlsHtml() {
    return advancedSectionHtml();
  }

  function controlsHtml() {
    return smartControlsHtml();
  }

  function creditLabel() {
    if (state.credits.unlimited) return 'Credits: ∞';
    var est = state.credits.estimate || 0;
    var bal = state.credits.balance != null ? state.credits.balance : 0;
    return est + ' credits (잔액 ' + bal + ')';
  }

  function resultActionsHtml() {
    return '';
  }

  function handleResultAction(action, root) {
    var galleryId = state.activeGalleryId || (state.lastResult && (state.lastResult.job_id + '_0'));
    if (!galleryId) return;

    if (action === 'edit') {
      state.tab = 'edit';
      setTab(root);
      renderTab(root);
      return;
    }

    if (action === 'variation') {
      if (Core && Core.gallery && Core.gallery.regenerate) {
        Core.gallery.regenerate(galleryId).then(function () {
          state.settings.last_prompt = (state.settings.last_prompt || '') + ' — creative variation';
          state.tab = 'generate';
          setTab(root);
          renderTab(root);
        }).catch(function (err) { alert(err.message || 'Variation failed.'); });
      } else {
        reusePrompt(galleryId, 'result', root);
        state.settings.last_prompt = (state.settings.last_prompt || '') + ' — creative variation';
        renderTab(root);
      }
      return;
    }

    if (action === 'upscale') {
      var src = state.selectedImage;
      if (!src) return;
      var api = getImageApi();
      if (!api || !api.upscale) { alert('Upscale is not available.'); return; }
      api.upscale({
        source_url: src,
        provider: state.settings.default_provider || 'auto',
        auto_save: true
      }).then(function (res) {
        finalizeJob(res.data || res, root);
      }).catch(function (err) { showGenerateError(root, err.message || 'Upscale failed.'); });
      return;
    }

    if (action === 'remove-bg') {
      state.selectedImage = state.selectedImage || (resultImages(state.lastResult)[activeResultIndex()] || {}).url;
      state.tab = 'edit';
      state.editMode = 'edit';
      setTab(root);
      renderTab(root);
      return;
    }

    if (action === 'delete') {
      if (!window.confirm('이 작품을 삭제할까요?')) return;
      deleteGalleryItem(galleryId).then(function () {
        var images = resultImages(state.lastResult);
        if (images.length <= 1) {
          state.lastResult = null;
          state.selectedImage = null;
          state.selectedResultIndex = 0;
          state.activeGalleryId = null;
        } else {
          state.selectedResultIndex = 0;
          state.activeGalleryId = (state.lastResult.job_id || 'job') + '_0';
          state.selectedImage = images[0] && images[0].url;
        }
        notifyGalleryUpdated();
        renderTab(root);
      }).catch(function (err) { showGenerateError(root, err.message || 'Delete failed.'); });
      return;
    }

    if (!Core || !Core.gallery) return;

    var map = {
      download: function () { return Core.gallery.download(galleryId); },
      copy: function () { return Core.gallery.copy(galleryId); },
      reuse: function () { return Core.gallery.regenerate(galleryId); },
      public: function () { return Core.gallery.visibility(galleryId, true); },
      share: function () { return Core.gallery.share(galleryId); },
      marketplace: function () { return Core.gallery.marketplace(galleryId); },
      project: function () { return Core.gallery.project(galleryId, ''); }
    };

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
      if (action === 'share') {
        var link = (res.data && (res.data.url || res.data.share_url)) || '';
        if (link && navigator.clipboard) navigator.clipboard.writeText(link);
        if (link) alert('공유 링크가 복사되었습니다.');
      }
      if (action === 'project') {
        alert('프로젝트에 저장되었습니다.');
      }
      notifyGalleryUpdated();
    }).catch(function (err) { alert(err.message || 'Action failed.'); });
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

  function mountRefAssets(host, studioKey) {
    if (!host || !global.YooYReferenceAssetsPanel) return;
    if (state.refPanel) state.refPanel.destroy();
    state.refPanel = global.YooYReferenceAssetsPanel.mount(host, {
      studio: studioKey || 'image-studio',
      assets: state.settings.reference_assets || [],
      onChange: function (assets) {
        state.settings.reference_assets = assets;
        state.referenceUrl = assets[0] ? assets[0].url : '';
        state.settings.reference_url = state.referenceUrl;
        if (state.smartAuto && getSmartAuto()) {
          var refCtx = getSmartAuto().analyzeReference(assets, state.settings.last_prompt || '');
          state.refAnalysisLabels = refCtx.labels || [];
        }
        var root = document.getElementById('yai-image-studio');
        if (root) {
          refreshRefAnalysisPanel(root);
          if (state.advancedOpen && state.smartAuto) previewSmartAuto(root);
        }
      }
    });
  }

  function applyRefPayload(payload) {
    if (global.YooYReferenceAssetsPanel && state.refPanel) {
      return global.YooYReferenceAssetsPanel.applyToSettings(payload, state.refPanel.getAssets());
    }
    return payload;
  }

  function refUploadHtml() {
    return '<div id="yis-ref-panel-host"></div>';
  }

  function updateResultBoardRatio(root) {
    var canvas = root && root.querySelector('.yis-result-board__canvas:not(.yis-result-board__canvas--generating)');
    if (!canvas) return;
    var ratio = state.lastResult ? resultAspectRatio(state.lastResult) : (state.settings.size === 'auto' ? '1:1' : (state.settings.aspect_ratio || '1:1'));
    var cls = resultBoardRatioClass(ratio);
    canvas.dataset.ratio = ratio;
    canvas.className = 'yis-result-board__canvas yis-result-board__canvas--' + cls;
  }

  function updatePreviewRatio(root) {
    updateResultBoardRatio(root);
  }

  // Verifies the REST routes required for generation are registered before
  // starting. This runs BEFORE any provider work so a rest_no_route condition
  // is never misreported as an OpenAI / provider / billing failure.
  function verifyRestHealthThen(root, next) {
    if (state.restHealth && state.restHealth.ok === true) { next(); return; }
    if (state.restHealth && state.restHealth.ok === false) {
      showRestRouteError(root, state.restHealth);
      return;
    }
    if (!(Core && Core.restHealth)) { next(); return; }
    Core.restHealth().then(function (res) {
      var data = (res && (res.data || res)) || {};
      state.restHealth = data;
      if (data.ok === false) {
        showRestRouteError(root, data);
      } else {
        next();
      }
    }).catch(function () {
      // Health endpoint unreachable — let the actual generate call surface any error.
      next();
    });
  }

  function showRestRouteError(root, health) {
    var missing = (health && health.missing) || [];
    if (global.console && global.console.error) {
      global.console.error('[ImageStudio] REST route health failed. Missing:', missing);
    }
    showGenerateError(root, {
      code: 'rest_no_route',
      restNoRoute: true,
      details: { code: 'rest_no_route', missing: missing }
    });
  }

  function doGenerate(root) {
    global.YooYLastGenerateClick = Date.now();
    syncPromptFields(root);
    var prompt = ($('#yis-prompt', root) || {}).value || state.settings.last_prompt || '';
    if (!prompt.trim()) {
      showGenerateError(root, 'Enter a prompt before generating.');
      return;
    }

    var smart = runSmartAuto(prompt);
    var sendPrompt = smart.serverCompose ? prompt : (smart.optimizedPrompt || prompt);
    var negative = state.settings.negative_prompt || '';

    var preflight = getProviderPreflight();
    if (!preflight.ok) {
      showGenerateError(root, {
        stage: 'provider_validation',
        code: preflight.code,
        message: preflight.message,
        provider_name: preflight.provider && preflight.provider.name
      });
      updateProviderUX(root);
      return;
    }

    if (state.generating) return;

    var api = getImageApi();
    if (!api) {
      showGenerateError(root, 'Image API unavailable. Reload the page.');
      return;
    }

    verifySystemThen(root, function () {
      startGenerate(root, prompt, sendPrompt, negative);
    });
  }

  // Essential-only pre-generate gate driven by the self-diagnosis engine.
  // Blocks generation (no Running job) when REST / Provider / Credits /
  // Gallery / Image Save are not healthy. Falls back to the REST-health gate
  // when diagnostics are unavailable.
  function verifySystemThen(root, next) {
    var D = global.YooYDiagnostics;
    if (!D || !D.run) { verifyRestHealthThen(root, next); return; }
    D.run(true).then(function (report) {
      if (report && report.essential_ok === false) {
        showSystemBlock(root, report);
      } else {
        next();
      }
    }).catch(function () {
      verifyRestHealthThen(root, next);
    });
  }

  function showSystemBlock(root, report) {
    var area = root && root.querySelector('.yis-prompt-area');
    if (!area) return;
    var existing = area.querySelector('#yis-generate-error');
    if (existing) existing.remove();

    var failed = (report.checks || []).filter(function (c) {
      return c.essential && c.status === 'error';
    });
    var rows = failed.map(function (c) {
      var fixBtn = c.fixable
        ? '<button type="button" class="yis-sys-fix" data-yoy-fix="' + esc(c.fix_action) + '">Fix →</button>'
        : '';
      return '<div class="yis-sys-block-row"><span>🔴 <b>' + esc(c.label) + '</b> — ' + esc(c.message) + '</span>' + fixBtn + '</div>';
    }).join('');

    if (global.console && global.console.error) {
      global.console.error('[ImageStudio] generation blocked by System Check', failed);
    }

    area.insertAdjacentHTML('beforeend',
      '<div class="yis-error yis-error--route" id="yis-generate-error" role="alert">' +
        '<strong>생성을 시작할 수 없습니다 — 필수 시스템 점검 실패</strong>' +
        '<p class="yis-error-copy">아래 항목을 해결한 뒤 다시 시도하세요. (Running 작업은 생성되지 않았습니다.)</p>' +
        '<div class="yis-sys-block">' + rows + '</div>' +
        diagnosticReportButtonsHtml() +
      '</div>');
  }

  function diagnosticReportButtonsHtml() {
    return '<div class="yis-diag-report">진단 리포트: ' +
      '<button type="button" data-yoy-report="json">JSON</button>' +
      '<button type="button" data-yoy-report="txt">TXT</button>' +
      '<button type="button" data-yoy-report="md">Markdown</button>' +
      '</div>';
  }

  // Prioritised root-cause analysis + inline Fix buttons for a known error.
  function errorAnalysisHtml(err) {
    var D = global.YooYDiagnostics;
    if (!D || !D.analyzeError) return '';
    var analysis = D.analyzeError(err);
    if (!analysis) return '';
    var causeRows = analysis.causes.map(function (c) {
      var fix = c.fix
        ? '<button type="button" class="yis-sys-fix" data-yoy-fix="' + esc(c.fix.action) + '">' + esc(c.fix.label) + '</button>'
        : '';
      return '<li><span class="yis-cause-p">' + esc(c.priority) + '</span>' + esc(c.text) + fix + '</li>';
    }).join('');
    return '<div class="yis-error-analysis">' +
      (analysis.note ? '<p class="yis-error-copy">' + esc(analysis.note) + '</p>' : '') +
      '<div class="yis-cause-title">가능한 원인 (우선순위순)</div>' +
      '<ul class="yis-cause-list">' + causeRows + '</ul>' +
      '</div>';
  }

  function startGenerate(root, prompt, sendPrompt, negative) {
    var api = getImageApi();
    if (!api) {
      showGenerateError(root, 'Image API unavailable. Reload the page.');
      return;
    }

    clearGenerateError(root);
    state.generating = true;
    state.generateStartedAt = Date.now();
    state.generateStep = 'preparing';
    state.settings.last_prompt = prompt;
    state.settings.prompt = sendPrompt;
    state.settings.smart_auto = state.smartAuto !== false;
    state.settings.generation_mode = state.generationMode || 'fast';
    if (state.referenceUrl) state.settings.reference_url = state.referenceUrl;
    renderTab(root);
    bindGenerateButton(root);
    updateGenerateProgress(root);

    var providerId = state.settings.default_provider || 'auto';
    var isAutoProvider = providerId === 'auto';
    var payload = applyRefPayload(Object.assign({}, state.settings, {
      prompt: sendPrompt,
      user_prompt: prompt,
      raw_user_request: state.rawUserRequest || prompt,
      optimized_prompt: state.lastOptimizedPrompt || sendPrompt,
      negative_prompt: negative,
      provider: providerId,
      size: state.settings.size || mappedSizeForAspect(state.settings.aspect_ratio || '1:1') || '',
      auto_save: true,
      smart_auto: state.smartAuto !== false,
      generation_mode: state.generationMode || 'fast',
      output_format: state.settings.output_format || 'png',
      mood: state.settings.mood,
      camera: state.settings.camera,
      lens: state.settings.lens,
      camera_angle: state.settings.camera_angle,
      depth_of_field: state.settings.depth_of_field,
      commercial: state.settings.commercial_mode !== false && state.settings.commercial !== false,
      commercial_mode: state.settings.commercial_mode,
      creative_brief: state.creativeBrief || undefined,
      intent_domain: state.intentDomain || state.settings.intent_domain || undefined,
      prompt_version: state.promptVersion || 'spi-image-1'
    }));

    if (state.generationMode === 'fast') {
      payload.quality = 'standard';
      payload.image_count = 1;
      if (!payload.size && state.settings.aspect_ratio === '1:1') {
        payload.resolution = '1024';
      }
    } else if (state.smartAuto) {
      payload.quality = state.settings.quality || 'hd';
    }

    if (isAutoProvider) {
      delete payload.model;
      delete payload.default_model;
    } else {
      payload.model = state.settings.default_model || state.settings.model || '';
    }

    state.generateStep = 'generating';
    updateGenerateProgress(root);

    logGenerationRequest(payload);
    logOpenAiRequestPreview(payload);
    debugLog('generate request', payload.provider);
    global.YooYLastGenerateRequest = '/image-studio/generate';

    api.generate(payload).then(function (res) {
      finalizeJob(res.data || res, root);
    }).catch(function (err) {
      if (isStudioAdmin() && err && err.details) {
        logOpenAiResponse(err.details.data || err.details);
      }
      state.lastGenerateError = err;
      state.generating = false;
      showGenerateError(root, err);
      renderTab(root);
      bindGenerateButton(root);
      loadProviderHealth(root);
    });
  }

  function finalizeJob(data, root) {
    if (!data) {
      state.generating = false;
      showGenerateError(root, 'Empty response from server.');
      renderTab(root);
      return;
    }

    if (jobMissingProviderReference(data) && ['queued', 'running', 'processing', 'pending'].indexOf(data.status || '') >= 0) {
      state.generating = false;
      if (providerBillingFailureMessages(data)) {
        showProviderBillingError(root, data);
      } else {
        showGenerateError(root, data.error || 'Job has no provider reference and no output.');
      }
      renderTab(root);
      bindGenerateButton(root);
      return;
    }

    if (shouldPollJob(data)) {
      state.generateStep = mapStatusToStep(data.status);
      updateGenerateProgress(root);
      return pollUntilDone(data, root);
    }

    if (data.status === 'failed' || data.status === 'error' || data.status === 'timeout') {
      state.generating = false;
      if (providerBillingFailureMessages(data)) {
        showProviderBillingError(root, data);
      } else {
        showGenerateError(root, data.error || 'Generation failed.');
      }
      renderTab(root);
      bindGenerateButton(root);
      loadProviderHealth(root);
      return;
    }

    if (data.status === 'completed' && !hasOutputAsset(data)) {
      state.generating = false;
      showGenerateError(root, data.error || 'Generation completed but no output asset was returned.');
      renderTab(root);
      bindGenerateButton(root);
      return;
    }

    state.lastResult = data;
    state.generating = false;
    state.generateStep = 'completed';
    state.selectedResultIndex = 0;
    state.activeGalleryId = (data.job_id || '') + '_0';
    var imgs = resultImages(data);
    if (imgs[0]) state.selectedImage = imgs[0].url;
    if (data.credits) state.credits.balance = data.credits.balance;
    var resMeta = data.meta && data.meta.provider_resolution ? data.meta.provider_resolution : data;
    var latencyMs = data.latency_ms || data.duration_ms || (state.generateStartedAt ? (Date.now() - state.generateStartedAt) : null);
    state.lastDebugInfo = {
      provider: data.provider_used || data.provider || selectedProviderId(),
      model: data.model || state.settings.default_model || state.settings.model || '',
      apiSize: resMeta.size || data.size || mappedSizeForAspect(state.settings.aspect_ratio || '1:1') || '',
      latency: latencyMs
    };
    refreshDeveloperInfoPanel(root);
    logOpenAiResponse(data);
    if (data.warning) {
      showGenerateInfo(root, data.warning);
    } else if (data.fallback_reason === 'replicate_insufficient_credit') {
      var fb = providerBillingFailureMessages(data);
      showGenerateInfo(root, fb ? (fb.primary + ' ' + fb.secondary) : data.warning);
    } else if (isDebugMode() && (data.size_corrected || (resMeta && resMeta.size_corrected))) {
      showGenerateInfo(root, '[Debug] Size adjusted from ' + (resMeta.size_original || resMeta.size_requested || 'requested') + ' to ' + (resMeta.size || state.settings.size) + '.');
    } else if (data.fallback_reason) {
      showGenerateInfo(root, isDebugMode() ? ('Using Mock provider (' + data.fallback_reason + ').') : '');
    } else if (isDebugMode()) {
      var used = data.provider_used || data.provider || '';
      if (used) showGenerateInfo(root, 'Provider used: ' + used);
    } else {
      showGenerateInfo(root, '');
    }
    notifyGalleryUpdated();
    refreshAutoResultPanel(root);
    renderTab(root);
    bindGenerateButton(root);
    return data;
  }

  function pollUntilDone(data, root) {
    var provider = data.provider_used || data.provider || state.settings.default_provider || 'mock';
    var jobId = data.job_id;
    var attempts = 0;
    var startedAt = Date.now();
    var lastStatus = data.status || '';
    var lastProgress = data.progress || 0;
    var lastChangeAt = Date.now();

    function failPoll(message, job) {
      state.generating = false;
      state.generateStep = message.indexOf('timeout') >= 0 || message.indexOf('Timeout') >= 0 ? 'timeout' : 'failed';
      if (providerBillingFailureMessages(job)) {
        showProviderBillingError(root, job);
      } else {
        showGenerateError(root, (job && job.error) || message);
      }
      renderTab(root);
      bindGenerateButton(root);
    }

    function tick() {
      attempts += 1;
      var elapsed = Date.now() - startedAt;
      if (elapsed >= POLL_MAX_MS || attempts > POLL_MAX_ATTEMPTS) {
        failPoll('Generation timed out after ' + Math.round(elapsed / 1000) + ' seconds.', data);
        return;
      }

      state.generateStep = mapStatusToStep(lastStatus);
      updateGenerateProgress(root);

      return getImageApi().pollJob(jobId, provider).then(function (res) {
        var job = (res.data && res.data.job) || res.data || res;
        var status = job.status || '';
        var progress = job.progress || 0;

        if (status !== lastStatus || progress !== lastProgress) {
          lastStatus = status;
          lastProgress = progress;
          lastChangeAt = Date.now();
        } else if (Date.now() - lastChangeAt >= POLL_STALE_MS) {
          failPoll('Generation timed out (no progress for 30 seconds).', job);
          return;
        }

        if (!job.provider_job_id && !hasOutputAsset(job) && provider !== 'mock' &&
            ['queued', 'running', 'processing', 'pending'].indexOf(status) >= 0) {
          failPoll(replicateBillingUserMessage(job) || job.error || 'Job has no provider reference and no output.', job);
          return;
        }

        if (shouldPollJob(job)) {
          return new Promise(function (r) { setTimeout(r, POLL_INTERVAL_MS); }).then(tick);
        }

        state.generateStep = status === 'completed' ? 'saving' : mapStatusToStep(status);
        updateGenerateProgress(root);
        return finalizeJob(job, root);
      }).catch(function (err) {
        state.generating = false;
        state.generateStep = 'failed';
        showGenerateError(root, err);
        renderTab(root);
        bindGenerateButton(root);
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
    var api = getImageApi();
    if (!api) return;
    var fn = api[state.editMode] || api.edit;
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

  function renderGallery(ws, ctrl, root) {
    root = root || document.getElementById('yai-image-studio');
    ws.innerHTML = '<div class="yis-loading">Loading...</div>';
    ctrl.innerHTML = '<div class="yis-gallery-hint">이미지를 선택하면 생성 정보가 표시됩니다.</div>';
    getImageApi().gallery().then(function (res) {
      var items = (res.data && res.data.items) || [];
      state.galleryItems = items;
      if (!items.length) {
        ws.innerHTML = '<div class="yis-empty">갤러리가 비어 있습니다.</div>';
        ctrl.innerHTML = '<div class="yis-gallery-hint">Generate로 이미지를 생성하세요.</div>';
        return;
      }
      if (!state.gallerySelectedId || !items.some(function (it) { return it.id === state.gallerySelectedId; })) {
        state.gallerySelectedId = items[0].id;
      }
      ws.innerHTML = '<div class="yis-header"><h2>Gallery</h2><span class="yis-badge">' + items.length + '</span></div>' +
        '<div class="yis-grid yis-gallery-grid">' + items.map(function (item) {
          if (item.asset_missing) {
            return '<div class="yis-thumb-card yis-thumb-card--missing" data-yis-gallery-id="' + esc(item.id) + '"><span>Asset missing</span><span>' + esc(item.title) + '</span></div>';
          }
          var url = item.image_url || item.output_url || item.url || '';
          var thumb = item.thumbnail_url || item.thumbnail || url;
          var active = state.gallerySelectedId === item.id ? ' is-selected' : '';
          return '<div class="yis-thumb-card' + active + '" data-yis-gallery-id="' + esc(item.id) + '">' +
            '<img src="' + esc(thumb) + '" alt=""><span>' + esc(item.title) + '</span></div>';
        }).join('') + '</div>';
      if (root) renderGalleryDetail(root);
    });
  }

  function findGalleryItem(id) {
    return (state.galleryItems || []).find(function (it) { return it.id === id; }) || null;
  }

  function galleryOptimizedPrompt(item) {
    if (!item) return '';
    var meta = item.meta || {};
    return meta.optimized_prompt || item.optimized_prompt || '';
  }

  function renderGalleryDetail(root) {
    var ctrl = $('#yis-controls', root);
    if (!ctrl) return;
    var item = findGalleryItem(state.gallerySelectedId);
    if (!item) {
      ctrl.innerHTML = '<div class="yis-gallery-hint">이미지를 선택하세요.</div>';
      return;
    }
    var url = item.image_url || item.output_url || item.url || '';
    var thumb = item.thumbnail_url || item.thumbnail || url;
    var meta = item.meta || {};
    var optimized = galleryOptimizedPrompt(item) || (state.lastResult && state.lastResult.job_id && item.job_id === state.lastResult.job_id ? state.lastOptimizedPrompt : '');
    var created = item.created_at || '';
    var resolution = meta.resolution || item.resolution || '—';
    var credits = item.credits_used != null ? item.credits_used : (meta.credits_used || '—');
    var refUrl = meta.reference_url || item.reference_url || '';

    ctrl.innerHTML = '<div class="yis-gallery-detail">' +
      '<h3 class="yis-gallery-detail__title">생성 정보</h3>' +
      (thumb ? '<img class="yis-gallery-detail__preview" src="' + esc(thumb) + '" alt="">' : '') +
      '<dl class="yis-gallery-detail__meta">' +
        detailRow('Provider', item.provider || '—') +
        detailRow('Model', item.model || '—') +
        detailRow('Prompt', item.prompt || '—') +
        (optimized ? detailRow('Optimized Prompt', optimized) : '') +
        (refUrl ? '<div class="yis-gallery-detail__ref"><dt>Reference</dt><dd><img src="' + esc(refUrl) + '" alt="ref"></dd></div>' : '') +
        detailRow('생성시간', created ? formatGalleryDate(created) : '—') +
        detailRow('해상도', resolution) +
        detailRow('Credits', String(credits)) +
      '</dl>' +
      '<div class="yis-gallery-detail__actions">' +
        '<button type="button" class="yis-btn-secondary" data-yis-gallery-action="regenerate" data-yis-gallery-id="' + esc(item.id) + '">재생성</button>' +
        '<button type="button" class="yis-btn-secondary" data-yis-gallery-action="edit" data-yis-gallery-id="' + esc(item.id) + '">Edit</button>' +
        '<button type="button" class="yis-btn-secondary" data-yis-gallery-action="variation" data-yis-gallery-id="' + esc(item.id) + '">Variation</button>' +
        '<button type="button" class="yis-btn-secondary" data-yis-gallery-action="upscale" data-yis-gallery-id="' + esc(item.id) + '">Upscale</button>' +
        '<button type="button" class="yis-btn-secondary" data-yis-gallery-action="download" data-yis-gallery-id="' + esc(item.id) + '">Download</button>' +
        '<button type="button" class="yis-btn-secondary yis-btn-danger" data-yis-gallery-action="delete" data-yis-gallery-id="' + esc(item.id) + '">Delete</button>' +
      '</div></div>';

    root.querySelectorAll('[data-yis-gallery-id]').forEach(function (el) {
      el.classList.toggle('is-selected', el.dataset.yisGalleryId === state.gallerySelectedId);
    });
  }

  function detailRow(label, value) {
    return '<div><dt>' + esc(label) + '</dt><dd>' + esc(value) + '</dd></div>';
  }

  function formatGalleryDate(iso) {
    try {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return iso;
      return d.toLocaleString();
    } catch (e) { return iso; }
  }

  function handleGalleryAction(action, id, root) {
    if (!id) return;
    var item = findGalleryItem(id);
    if (action === 'download' && Core && Core.gallery) {
      Core.gallery.download(id).then(function (res) {
        var info = res.data || {};
        if (info.url) { var a = document.createElement('a'); a.href = info.url; a.download = info.filename || 'image'; a.target = '_blank'; a.click(); }
      });
      return;
    }
    if (action === 'delete') {
      deleteGalleryItem(id).then(function () {
        state.galleryItems = (state.galleryItems || []).filter(function (it) { return it.id !== id; });
        state.gallerySelectedId = state.galleryItems[0] ? state.galleryItems[0].id : null;
        renderGallery($('#yis-workspace', root), $('#yis-controls', root), root);
      }).catch(function (err) {
        showGenerateError(root, err.message || 'Delete failed.');
      });
      return;
    }
    if (action === 'edit') {
      if (item) {
        state.selectedImage = item.image_url || item.output_url || item.url || '';
        state.activeGalleryId = id;
      }
      state.tab = 'edit';
      setTab(root);
      renderTab(root);
      return;
    }
    if (action === 'upscale') {
      var src = item && (item.image_url || item.output_url || item.url);
      if (!src) return;
      getImageApi().upscale({ source_url: src, provider: item.provider || state.settings.default_provider || 'auto', auto_save: true }).then(function (res) {
        finalizeJob(res.data || res, root);
        state.tab = 'generate';
        setTab(root);
        renderTab(root);
      });
      return;
    }
    if (action === 'variation') {
      reusePrompt(id, 'gallery', root);
      state.settings.last_prompt = (state.settings.last_prompt || '') + ' — creative variation';
      state.tab = 'generate';
      setTab(root);
      renderTab(root);
      return;
    }
    if (action === 'regenerate' && Core && Core.gallery) {
      Core.gallery.regenerate(id).then(function () {
        state.tab = 'generate';
        setTab(root);
        renderTab(root);
      });
    }
  }

  function deleteGalleryItem(id) {
    var api = getImageApi();
    if (api && api.deleteGallery) return api.deleteGallery(id);
    var cfg = (Core && Core.config) || global.YooYStudio || {};
    var url = (cfg.restUrl || '').replace(/\/$/, '') + '/image-studio/gallery/' + encodeURIComponent(id);
    return fetch(url, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().then(function (json) {
        if (!res.ok) throw new Error((json && json.message) || 'Delete failed');
        return json;
      });
    });
  }

  function renderHistory(ws, ctrl) {
    ws.innerHTML = '<div class="yis-loading">Loading...</div>';
    getImageApi().history().then(function (res) {
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
    getImageApi().promptReuse({ source_type: source, source_id: id }).then(function (res) {
      var reuse = (res.data && res.data.reuse) || {};
      Object.assign(state.settings, reuse);
      state.settings.last_prompt = reuse.prompt || '';
      state.tab = 'generate';
      setTab(root);
      renderTab(root);
    });
  }

  function renderSettings(ws, ctrl) {
    ws.innerHTML = '<div class="yis-header"><h2>Settings</h2></div>' +
      '<div class="yis-advanced-inner" style="margin-top:12px">' + advancedFieldsInnerHtml() + '</div>' +
      '<button class="yis-btn-primary" id="yis-save-settings" type="button" style="margin-top:16px">Save Settings</button>';
    ctrl.innerHTML = '<h3 style="color:#d8a63a;font-size:13px">API Router</h3>' +
      state.providers.map(function (p) {
        return '<div class="yis-field"><label>' + esc(p.name) + '</label><span style="color:#666;font-size:12px">' + (p.models || []).length + ' models</span></div>';
      }).join('');
  }

  function saveSettings(root) {
    var api = getImageApi();
    if (!api) return;
    api.updateSettings(state.settings).then(function () { renderTab(root); });
  }

  publicApi.mount = mount;
  publicApi.doGenerate = doGenerate;
  publicApi.state = state;
  global.YooYImageStudio = publicApi;

  installGlobalGenerateHandler();

  function bootImageStudio() {
    try {
      var el = document.getElementById('yai-image-studio');
      if (!el) return;
      var view = el.closest('.yai-view');
      if (view && !view.classList.contains('is-active')) return;
      if (el.dataset.mounted === '1') return;
      mount(el);
    } catch (bootErr) {
      debugLog('boot error', bootErr);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootImageStudio);
  } else {
    bootImageStudio();
  }

  } catch (initErr) {
    if (global.console && global.console.error) {
      global.console.error('[YooYImageStudio] init failed', initErr);
    }
    publicApi.mount = function (container) {
      if (container) {
        container.innerHTML = '<div class="yis-error">Image Studio failed to initialize.</div>';
      }
    };
  }
})(window);
