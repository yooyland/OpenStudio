/**
 * Template library + lightweight start sheet. Handoff uses existing Studio routes.
 */
(function (global) {
  'use strict';

  var sheetEl = null;
  var pendingTpl = null;
  var pendingValues = {};
  var pendingAttach = null;
  var category = 'popular';

  function catalog() { return global.YooYCreationCatalog; }
  function Core() { return global.YooYCore; }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg) {
    if (global.YooYHomeBottomComposer && typeof global.YooYHomeBottomComposer.toast === 'function') {
      global.YooYHomeBottomComposer.toast(msg);
      return;
    }
    var host = document.getElementById('yai-main') || document.body;
    var el = document.createElement('div');
    el.className = 'yai-nav-toast';
    el.textContent = msg;
    host.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.remove(); }, 2400);
  }

  function emit(name, detail) {
    try { document.dispatchEvent(new CustomEvent(name, { detail: detail || {} })); } catch (e) { /* ignore */ }
    if (Core() && typeof Core().debugLog === 'function') Core().debugLog(name, detail);
  }

  function requireLogin() {
    var cfg = global.YooYStudio || {};
    if (cfg.loggedIn) return true;
    var modal = document.getElementById('yai-login-modal');
    if (modal) modal.hidden = false;
    else toast('로그인이 필요합니다.');
    return false;
  }

  function cardHtml(tpl) {
    return '<article class="yai-ct-card yai-ct-card--' + esc(tpl.art || tpl.studio) + '" data-creation-tpl="' + esc(tpl.id) + '" tabindex="0" role="button" aria-label="' + esc(tpl.title) + ' 사용하기">' +
      '<div class="yai-ct-card__media">' +
        '<span class="yai-ct-card__art" aria-hidden="true"></span>' +
        '<span class="yai-ct-card__badge">' + esc(studioBadge(tpl.studio)) + '</span>' +
        '<div class="yai-ct-card__overlay"><button type="button" class="yai-ct-card__cta" data-creation-tpl="' + esc(tpl.id) + '">사용하기</button></div>' +
      '</div>' +
      '<div class="yai-ct-card__body"><strong>' + esc(tpl.title) + '</strong><span>' + esc(tpl.description) + '</span></div>' +
    '</article>';
  }

  function studioBadge(studio) {
    var map = { image: '이미지', video: '영상', writing: '글쓰기', music: '음악', voice: '음성', translator: '번역', avatar: '아바타' };
    return map[studio] || studio;
  }

  function ensureSheet() {
    if (sheetEl) return sheetEl;
    sheetEl = document.createElement('div');
    sheetEl.id = 'yai-ct-sheet';
    sheetEl.className = 'yai-ct-sheet';
    sheetEl.hidden = true;
    sheetEl.innerHTML =
      '<div class="yai-ct-sheet__backdrop" data-ct-close></div>' +
      '<div class="yai-ct-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="yai-ct-sheet-title">' +
        '<button type="button" class="yai-ct-sheet__x" data-ct-close aria-label="닫기">×</button>' +
        '<h2 id="yai-ct-sheet-title"></h2>' +
        '<p class="yai-ct-sheet__desc"></p>' +
        '<div class="yai-ct-sheet__fields" id="yai-ct-sheet-fields"></div>' +
        '<p class="yai-ct-sheet__err" id="yai-ct-sheet-err" hidden></p>' +
        '<p class="yai-ct-sheet__status" id="yai-ct-sheet-status" hidden></p>' +
        '<button type="button" class="yai-ct-sheet__go" id="yai-ct-sheet-go">이 템플릿으로 시작</button>' +
      '</div>';
    (document.getElementById('yai-app') || document.body).appendChild(sheetEl);
    sheetEl.addEventListener('click', function (e) {
      if (e.target.closest('[data-ct-close]')) { closeSheet(); return; }
      if (e.target.id === 'yai-ct-sheet-go') { submitSheet(); return; }
      var pick = e.target.closest('[data-ct-pick]');
      if (pick) pickFile(pick.getAttribute('data-ct-pick'));
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sheetEl && !sheetEl.hidden) closeSheet();
    });
    return sheetEl;
  }

  function closeSheet() {
    if (!sheetEl) return;
    sheetEl.hidden = true;
    pendingTpl = null;
    pendingAttach = null;
  }

  function setErr(msg) {
    var el = document.getElementById('yai-ct-sheet-err');
    if (!el) return;
    if (!msg) { el.hidden = true; el.textContent = ''; return; }
    el.hidden = false;
    el.textContent = msg;
  }

  function setStatus(msg) {
    var el = document.getElementById('yai-ct-sheet-status');
    if (!el) return;
    if (!msg) { el.hidden = true; el.textContent = ''; return; }
    el.hidden = false;
    el.textContent = msg;
  }

  function fieldHtml(field) {
    if (field.type === 'image' || field.type === 'file') {
      var kind = field.type === 'image' ? 'image' : 'file';
      return '<label class="yai-ct-field">' + esc(field.label) +
        '<button type="button" class="yai-ct-upload" data-ct-pick="' + kind + '">' + (kind === 'image' ? '이미지 추가' : '파일 추가') + '</button>' +
        '<span class="yai-ct-upload-name" data-ct-name="' + kind + '"></span></label>';
    }
    if (field.type === 'select') {
      var opts = (field.options || []).map(function (o) { return '<option value="' + esc(o) + '">' + esc(o) + '</option>'; }).join('');
      return '<label class="yai-ct-field">' + esc(field.label) +
        '<select data-ct-field="' + esc(field.id) + '">' + opts + '</select></label>';
    }
    var typ = field.type === 'url' ? 'url' : 'text';
    return '<label class="yai-ct-field">' + esc(field.label) +
      '<input type="' + typ + '" data-ct-field="' + esc(field.id) + '" autocomplete="off"></label>';
  }

  function openSheet(tpl) {
    pendingTpl = tpl;
    pendingValues = {};
    pendingAttach = null;
    ensureSheet();
    sheetEl.hidden = false;
    sheetEl.querySelector('#yai-ct-sheet-title').textContent = tpl.title;
    sheetEl.querySelector('.yai-ct-sheet__desc').textContent = tpl.description || '';
    var host = document.getElementById('yai-ct-sheet-fields');
    host.innerHTML = (tpl.fields || []).map(fieldHtml).join('') || '<p class="yai-muted">바로 Studio에서 이어서 만들 수 있습니다.</p>';
    setErr('');
    setStatus('');
    emit('yoy:template_view', { id: tpl.id });
    var first = host.querySelector('input, select, button');
    if (first) first.focus();
  }

  function firstResultItem(res) {
    var data = (res && (res.data || res)) || {};
    var results = data.results || [];
    var row = results[0] || null;
    return row ? (row.item || row) : null;
  }

  function pickFile(kind) {
    var input = document.getElementById('yai-ct-file-' + kind);
    if (!input) {
      input = document.createElement('input');
      input.type = 'file';
      input.id = 'yai-ct-file-' + kind;
      input.className = 'yai-sr-only';
      input.accept = kind === 'image' ? 'image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp' : '.txt,.pdf,.doc,.docx,text/plain,application/pdf';
      document.body.appendChild(input);
      input.addEventListener('change', function () {
        var files = input.files;
        input.value = '';
        if (!files || !files.length) return;
        upload(files, kind);
      });
    }
    input.click();
  }

  function upload(fileList, kind) {
    var api = Core();
    if (!api || !api.importEngine || typeof api.importEngine.uploadFiles !== 'function') {
      toast('파일을 불러오지 못했습니다. 다시 시도해 주세요.');
      return;
    }
    setStatus(kind === 'image' ? '이미지 준비 중...' : '파일 불러오는 중...');
    api.importEngine.uploadFiles(fileList, { source: 'upload', origin: 'Template', type_hint: kind === 'image' ? 'image' : '' })
      .then(function (res) {
        var item = firstResultItem(res);
        if (!item) {
          toast('파일을 불러오지 못했습니다. 다시 시도해 주세요.');
          setStatus('');
          return;
        }
        var url = item.image_url || item.url || item.thumbnail_url || '';
        pendingAttach = {
          type: kind === 'image' ? 'image' : 'file',
          source: 'import-engine',
          gallery_id: item.id || '',
          url: url,
          preview: item.thumbnail_url || url,
          name: item.title || fileList[0].name,
          title: item.title || fileList[0].name
        };
        var nameEl = document.querySelector('[data-ct-name="' + kind + '"]');
        if (nameEl) nameEl.textContent = pendingAttach.name;
        setStatus('');
      })
      .catch(function () {
        toast('파일을 불러오지 못했습니다. 다시 시도해 주세요.');
        setStatus('');
      });
  }

  function importUrl(raw) {
    var url = String(raw || '').trim();
    if (!url || !/^https?:\/\//i.test(url)) return Promise.reject(new Error('url'));
    var api = Core();
    var run = api && api.translator && api.translator.extractWebsite
      ? api.translator.extractWebsite({ source_url: url })
      : (api && api.post ? api.post('translator-studio', '/extract-website', { source_url: url }) : null);
    if (!run) return Promise.reject(new Error('url'));
    return run.then(function (res) {
      var data = (res && (res.data || res)) || {};
      var preview = data.preview || {};
      var normalized = data.normalized || {};
      pendingAttach = {
        type: 'url',
        source: 'website-adapter',
        url: preview.source_url || url,
        title: preview.title || url,
        name: preview.source_domain || url,
        excerpt: preview.content_preview || preview.excerpt || normalized.content || ''
      };
    });
  }

  function collectValues(tpl) {
    var values = {};
    (tpl.fields || []).forEach(function (f) {
      if (f.type === 'image' || f.type === 'file') return;
      var el = document.querySelector('[data-ct-field="' + f.id + '"]');
      values[f.id] = el ? String(el.value || '').trim() : '';
    });
    return values;
  }

  function submitSheet() {
    if (!pendingTpl) return;
    var tpl = pendingTpl;
    var values = collectValues(tpl);
    var fields = tpl.fields || [];
    var i;
    for (i = 0; i < fields.length; i++) {
      var f = fields[i];
      if (!f.required) continue;
      if (f.type === 'image' && (!pendingAttach || pendingAttach.type !== 'image')) {
        setErr('제품 이미지를 추가해 주세요.');
        return;
      }
      if (f.type === 'file' && (!pendingAttach || pendingAttach.type !== 'file')) {
        setErr('파일을 추가해 주세요.');
        return;
      }
      if ((f.type === 'text' || f.type === 'url') && !values[f.id]) {
        setErr(f.type === 'url' ? 'URL을 입력해 주세요.' : (f.label + '을 입력해 주세요.'));
        return;
      }
    }
    var needsImage = fields.some(function (x) { return x.type === 'image' && x.required; });
    if (needsImage && !pendingAttach) {
      setErr('제품 이미지를 추가해 주세요.');
      return;
    }

    var go = function () {
      try { sessionStorage.removeItem('yoy_pending_after_auth'); } catch (eClr) { /* ignore */ }
      startHandoff(tpl, values, pendingAttach);
    };

    // Persist template context before auth gate so login redirect can resume.
    try {
      var cat = catalog();
      var prompt = cat.fillPrompt(tpl, values);
      sessionStorage.setItem('yoy_home_template', JSON.stringify({
        template_id: tpl.id,
        title: tpl.title,
        studio: tpl.studio,
        aspect_ratio: tpl.aspect || '',
        duration: tpl.duration || '',
        style: values.style_hint || '',
        values: values
      }));
      sessionStorage.setItem('yoy_home_prompt', prompt);
      sessionStorage.setItem('yoy_home_original_prompt', prompt);
      sessionStorage.setItem('yoy_home_studio', tpl.studio);
      sessionStorage.setItem('yoy_pending_after_auth', 'template');
      if (pendingAttach) sessionStorage.setItem('yoy_home_attachment', JSON.stringify(pendingAttach));
    } catch (ePre) { /* ignore */ }

    if (!requireLogin()) return;

    var urlField = fields.filter(function (x) { return x.type === 'url'; })[0];
    if (urlField && values[urlField.id]) {
      setStatus('자료 가져오는 중...');
      importUrl(values[urlField.id]).then(function () {
        setStatus('');
        go();
      }).catch(function () {
        setStatus('');
        setErr('URL 내용을 가져오지 못했습니다.');
      });
      return;
    }
    go();
  }

  function startHandoff(tpl, values, attach) {
    var cat = catalog();
    var prompt = cat.fillPrompt(tpl, values);
    var payload = {
      template_id: tpl.id,
      title: tpl.title,
      studio: tpl.studio,
      aspect_ratio: tpl.aspect || '',
      duration: tpl.duration || '',
      style: values.style_hint || '',
      values: values
    };
    try {
      sessionStorage.setItem('yoy_home_template', JSON.stringify(payload));
      sessionStorage.setItem('yoy_home_prompt', prompt);
      sessionStorage.setItem('yoy_home_original_prompt', prompt);
      sessionStorage.setItem('yoy_home_studio', tpl.studio);
      sessionStorage.removeItem('yoy_home_remix');
      if (attach) {
        sessionStorage.setItem('yoy_home_attachment', JSON.stringify(attach));
        if (attach.url || attach.preview) {
          sessionStorage.setItem('yoy_reference_asset', JSON.stringify({
            url: attach.url || attach.preview,
            title: attach.title || attach.name || '',
            gallery_id: attach.gallery_id || '',
            source: 'template'
          }));
        }
      } else {
        sessionStorage.removeItem('yoy_home_attachment');
      }
    } catch (e) { /* ignore */ }
    emit('yoy:template_start', { id: tpl.id, studio: tpl.studio });
    emit('yoy:template_generate_handoff', { id: tpl.id, studio: tpl.studio });
    closeSheet();
    if (global.YooYStudioRoute) global.YooYStudioRoute(tpl.studio);
  }

  function openTemplate(id) {
    var tpl = catalog().byId(id);
    if (!tpl) {
      toast('현재 이 기능은 준비 중입니다.');
      return;
    }
    openSheet(tpl);
  }

  function startTool(id) {
    var tool = catalog().toolById(id);
    if (!tool || tool.enabled === false) {
      toast('현재 이 기능은 준비 중입니다.');
      return;
    }
    emit('yoy:quick_tool_click', { id: id });
    if (tool.templateId) {
      openTemplate(tool.templateId);
      return;
    }
    var fake = {
      id: tool.id,
      title: tool.label,
      description: '필요한 자료만 준비하면 Studio로 연결됩니다.',
      studio: tool.studio,
      art: tool.studio,
      fields: [],
      prompt: tool.prompt || tool.label
    };
    if (tool.requireImage) fake.fields.push({ id: 'image', type: 'image', label: '이미지', required: true });
    if (tool.requireFile) fake.fields.push({ id: 'file', type: 'file', label: '문서', required: true });
    if (!fake.fields.length) fake.fields.push({ id: 'topic', type: 'text', label: '하고 싶은 것', required: false });
    openSheet(fake);
  }

  function renderHomeFeatured(limit) {
    return catalog().featured(limit || 4).map(cardHtml).join('');
  }

  function renderLibrary() {
    var root = document.getElementById('yai-templates-root');
    if (!root || !catalog()) return;
    var chips = catalog().CATEGORIES.map(function (c) {
      var on = category === c.id ? ' is-on' : '';
      return '<button type="button" class="yai-ct-chip' + on + '" data-ct-cat="' + esc(c.id) + '">' + esc(c.label) + '</button>';
    }).join('');
    var items = catalog().inCategory(category);
    root.innerHTML =
      '<header class="yai-ct-lib-head">' +
        '<h1>Templates</h1>' +
        '<p>원하는 결과를 고르면 YooY가 필요한 설정을 준비합니다.</p>' +
      '</header>' +
      '<div class="yai-ct-chips" role="tablist">' + chips + '</div>' +
      '<div class="yai-ct-grid">' + items.map(cardHtml).join('') + '</div>';
  }

  function bind() {
    if (document.documentElement.dataset.ctBound === '1') return;
    document.documentElement.dataset.ctBound = '1';
    document.addEventListener('click', function (e) {
      var cat = e.target.closest('[data-ct-cat]');
      if (cat) {
        e.preventDefault();
        category = cat.getAttribute('data-ct-cat');
        renderLibrary();
        return;
      }
      var tplBtn = e.target.closest('[data-creation-tpl]');
      if (tplBtn) {
        e.preventDefault();
        openTemplate(tplBtn.getAttribute('data-creation-tpl'));
        return;
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var card = e.target.closest('[data-creation-tpl]');
      if (!card) return;
      e.preventDefault();
      openTemplate(card.getAttribute('data-creation-tpl'));
    });
  }

  global.YooYCreationTemplates = {
    openTemplate: openTemplate,
    startTool: startTool,
    renderLibrary: renderLibrary,
    renderHomeFeatured: renderHomeFeatured,
    bind: bind
  };

  function boot() {
    bind();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})(typeof window !== 'undefined' ? window : this);
