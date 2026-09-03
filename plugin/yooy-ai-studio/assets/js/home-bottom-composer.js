/**
 * Home bottom composer — Auto Studio, attachments (Import Engine / website extract), UI chrome.
 * Generate still goes through studio.js launchFromHome().
 */
(function (global) {
  'use strict';

  var studioAuto = true;
  var attachment = null;
  var pendingStudio = '';

  var IMAGE_ACCEPT = 'image/png,image/jpeg,image/webp,image/gif,.png,.jpg,.jpeg,.webp,.gif';
  var FILE_ACCEPT = '.txt,.pdf,.doc,.docx,text/plain,application/pdf';

  function composerEl() {
    return document.getElementById('yai-home-bottom-composer');
  }

  function Core() {
    return global.YooYCore;
  }

  function syncVisibility(routeName) {
    var el = composerEl();
    if (!el) return;
    var onHome = !routeName || routeName === 'home';
    if (!routeName) {
      var active = document.querySelector('.yai-view.is-active');
      onHome = !!(active && active.getAttribute('data-page') === 'home');
    }
    el.hidden = !onHome;
    if (!onHome) {
      closePlusMenu();
      hideUrlRow();
      hideIntentChoice();
    }
  }

  function closePlusMenu() {
    var menu = document.getElementById('yai-home-composer-plus-menu');
    var btn = document.getElementById('yai-home-composer-plus');
    if (menu) menu.hidden = true;
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function toast(msg) {
    var host = document.getElementById('yai-main') || document.body;
    var old = host.querySelector('.yai-home-composer-toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'yai-nav-toast yai-home-composer-toast';
    el.setAttribute('role', 'status');
    el.textContent = msg;
    host.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.remove(); }, 2600);
  }

  function setStatus(msg) {
    var el = document.getElementById('yai-home-composer-status');
    if (!el) return;
    if (!msg) {
      el.hidden = true;
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.textContent = msg;
  }

  function persistAttachment() {
    try {
      if (attachment) sessionStorage.setItem('yoy_home_attachment', JSON.stringify(attachment));
      else sessionStorage.removeItem('yoy_home_attachment');
    } catch (e) { /* ignore */ }
  }

  function renderChip() {
    var host = document.getElementById('yai-home-attach-chip');
    if (!host) return;
    if (!attachment) {
      host.innerHTML = '';
      host.hidden = true;
      return;
    }
    host.hidden = false;
    var thumb = attachment.preview
      ? '<img src="' + String(attachment.preview).replace(/"/g, '') + '" alt="">'
      : '<span class="yai-home-composer__chip-ico">📎</span>';
    var label = attachment.name || attachment.title || attachment.url || '첨부';
    host.innerHTML =
      '<span class="yai-home-composer__chip">' + thumb +
      '<em>' + String(label).replace(/</g, '') + '</em>' +
      '<button type="button" data-home-attach-clear aria-label="첨부 제거">×</button></span>';
  }

  function setAttachment(next) {
    attachment = next || null;
    persistAttachment();
    renderChip();
  }

  function hideUrlRow() {
    var row = document.getElementById('yai-home-url-row');
    if (row) row.hidden = true;
  }

  function showUrlRow() {
    var row = document.getElementById('yai-home-url-row');
    if (row) {
      row.hidden = false;
      var input = document.getElementById('yai-home-url-input');
      if (input) input.focus();
    }
  }

  function hideIntentChoice() {
    var el = document.getElementById('yai-home-intent-choice');
    if (el) el.hidden = true;
  }

  function showIntentChoice(candidates) {
    var el = document.getElementById('yai-home-intent-choice');
    if (!el) return;
    var opts = candidates && candidates.length ? candidates : ['image', 'video', 'writing'];
    var labels = { image: '이미지', video: '영상', writing: '글쓰기' };
    el.hidden = false;
    el.innerHTML = '<span>어떤 형태로 만들까요?</span>' +
      opts.map(function (id) {
        return '<button type="button" data-home-intent-pick="' + id + '">' + (labels[id] || id) + '</button>';
      }).join('') +
      '<button type="button" data-home-intent-pick="pick">직접 선택</button>';
  }

  function autosizePrompt() {
    var ta = document.getElementById('yai-home-prompt');
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.min(120, Math.max(44, ta.scrollHeight)) + 'px';
  }

  function ensureFileInput(id, accept, multiple, onChange) {
    var input = document.getElementById(id);
    if (!input) {
      input = document.createElement('input');
      input.type = 'file';
      input.id = id;
      input.className = 'yai-sr-only';
      input.accept = accept;
      if (multiple) input.multiple = false;
      var host = composerEl();
      if (host) host.appendChild(input);
    }
    if (input.dataset.bound !== '1') {
      input.dataset.bound = '1';
      input.addEventListener('change', onChange);
    }
    return input;
  }

  function firstResultItem(res) {
    var data = (res && (res.data || res)) || {};
    var results = data.results || [];
    var row = results[0] || null;
    if (!row) return null;
    return row.item || row;
  }

  function uploadFiles(fileList, kind) {
    var cfg = global.YooYStudio || {};
    if (!cfg.loggedIn) {
      try { sessionStorage.setItem('yoy_pending_after_auth', 'upload'); } catch (eAuth) { /* ignore */ }
      var modal = document.getElementById('yai-login-modal');
      if (modal) modal.hidden = false;
      else toast('로그인이 필요합니다.');
      return;
    }
    var api = Core();
    if (!api || !api.importEngine || typeof api.importEngine.uploadFiles !== 'function') {
      toast('업로드를 사용할 수 없습니다.');
      return;
    }
    setStatus(kind === 'image' ? '이미지 준비 중...' : '파일 불러오는 중...');
    api.importEngine.uploadFiles(fileList, { source: 'upload', origin: 'Home', type_hint: kind === 'image' ? 'image' : '' })
      .then(function (res) {
        var item = firstResultItem(res);
        if (!item || (item.status && item.status === 'failed')) {
          toast('파일을 불러오지 못했습니다. 다시 시도해 주세요.');
          setStatus('');
          return;
        }
        var url = item.image_url || item.url || item.thumbnail_url || '';
        setAttachment({
          type: kind === 'image' ? 'image' : 'file',
          source: 'import-engine',
          gallery_id: item.id || item.gallery_id || '',
          url: url,
          preview: item.thumbnail_url || url,
          name: item.title || item.filename || fileList[0].name,
          mime: item.mime || fileList[0].type || '',
          title: item.title || fileList[0].name
        });
        setStatus('');
      })
      .catch(function () {
        toast('파일을 불러오지 못했습니다. 다시 시도해 주세요.');
        setStatus('');
      });
  }

  function importUrl(rawUrl) {
    var url = String(rawUrl || '').trim();
    if (!url || !/^https?:\/\//i.test(url)) {
      toast('URL 내용을 가져오지 못했습니다.');
      return;
    }
    var cfg = global.YooYStudio || {};
    if (!cfg.loggedIn) {
      try { sessionStorage.setItem('yoy_pending_after_auth', 'upload'); } catch (eAuth) { /* ignore */ }
      var modal = document.getElementById('yai-login-modal');
      if (modal) modal.hidden = false;
      else toast('로그인이 필요합니다.');
      return;
    }
    var api = Core();
    var run;
    if (api && api.translator && typeof api.translator.extractWebsite === 'function') {
      run = api.translator.extractWebsite({ source_url: url });
    } else if (api && typeof api.post === 'function') {
      run = api.post('translator-studio', '/extract-website', { source_url: url });
    }
    if (!run) {
      toast('URL 내용을 가져오지 못했습니다.');
      return;
    }
    setStatus('자료 가져오는 중...');
    run.then(function (res) {
      var data = (res && (res.data || res)) || {};
      var preview = data.preview || {};
      var normalized = data.normalized || {};
      setAttachment({
        type: 'url',
        source: 'website-adapter',
        url: preview.source_url || url,
        title: preview.title || preview.source_domain || url,
        name: preview.source_domain || url,
        extraction_id: data.extraction_id || '',
        excerpt: preview.content_preview || preview.excerpt || normalized.content || ''
      });
      hideUrlRow();
      setStatus('');
    }).catch(function () {
      toast('URL 내용을 가져오지 못했습니다.');
      setStatus('');
    });
  }

  function pickAttachment(kind) {
    if (kind === 'url') {
      showUrlRow();
      return;
    }
    if (kind === 'image') {
      var img = ensureFileInput('yai-home-file-image', IMAGE_ACCEPT, false, function () {
        var files = img.files;
        img.value = '';
        if (!files || !files.length) return;
        var f = files[0];
        if (f.type && f.type.indexOf('image/') !== 0) {
          toast('현재 지원하지 않는 파일 형식입니다.');
          return;
        }
        uploadFiles(files, 'image');
      });
      img.click();
      return;
    }
    if (kind === 'file') {
      var doc = ensureFileInput('yai-home-file-doc', FILE_ACCEPT, false, function () {
        var files = doc.files;
        doc.value = '';
        if (!files || !files.length) return;
        var f = files[0];
        var n = String(f.name || '').toLowerCase();
        var ok = /\.(txt|pdf|doc|docx)$/.test(n) || /text\/|pdf|word/.test(f.type || '');
        if (!ok) {
          toast('현재 지원하지 않는 파일 형식입니다.');
          return;
        }
        uploadFiles(files, 'file');
      });
      doc.click();
    }
  }

  function bind() {
    var plus = document.getElementById('yai-home-composer-plus');
    var menu = document.getElementById('yai-home-composer-plus-menu');
    var autoBtn = document.getElementById('yai-home-studio-auto');
    var prompt = document.getElementById('yai-home-prompt');

    if (plus && plus.dataset.bound !== '1') {
      plus.dataset.bound = '1';
      plus.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!menu) return;
        var open = menu.hidden;
        menu.hidden = !open;
        plus.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    if (menu && menu.dataset.bound !== '1') {
      menu.dataset.bound = '1';
      menu.addEventListener('click', function (e) {
        var item = e.target.closest('[data-home-attach]');
        if (!item) return;
        e.preventDefault();
        closePlusMenu();
        pickAttachment(item.getAttribute('data-home-attach'));
      });
    }

    var urlGo = document.getElementById('yai-home-url-go');
    if (urlGo && urlGo.dataset.bound !== '1') {
      urlGo.dataset.bound = '1';
      urlGo.addEventListener('click', function (e) {
        e.preventDefault();
        var input = document.getElementById('yai-home-url-input');
        importUrl(input ? input.value : '');
      });
    }

    var chipHost = document.getElementById('yai-home-attach-chip');
    if (chipHost && chipHost.dataset.bound !== '1') {
      chipHost.dataset.bound = '1';
      chipHost.addEventListener('click', function (e) {
        if (!e.target.closest('[data-home-attach-clear]')) return;
        e.preventDefault();
        setAttachment(null);
      });
    }

    var choice = document.getElementById('yai-home-intent-choice');
    if (choice && choice.dataset.bound !== '1') {
      choice.dataset.bound = '1';
      choice.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-home-intent-pick]');
        if (!btn) return;
        e.preventDefault();
        var pick = btn.getAttribute('data-home-intent-pick');
        hideIntentChoice();
        if (pick === 'pick') {
          try { sessionStorage.setItem('yoy_home_studio_auto', '0'); } catch (err) { /* ignore */ }
          studioAuto = false;
          if (autoBtn) {
            autoBtn.classList.remove('is-on');
            autoBtn.setAttribute('aria-pressed', 'false');
          }
          toast('사이드바에서 Studio를 선택해 주세요.');
          return;
        }
        pendingStudio = pick;
        try { sessionStorage.setItem('yoy_home_studio', pick); } catch (e2) { /* ignore */ }
        var create = document.getElementById('yai-home-create');
        if (create) create.click();
      });
    }

    if (autoBtn && autoBtn.dataset.bound !== '1') {
      autoBtn.dataset.bound = '1';
      autoBtn.addEventListener('click', function () {
        studioAuto = !studioAuto;
        autoBtn.classList.toggle('is-on', studioAuto);
        autoBtn.setAttribute('aria-pressed', studioAuto ? 'true' : 'false');
        try {
          sessionStorage.setItem('yoy_home_studio_auto', studioAuto ? '1' : '0');
        } catch (err) { /* ignore */ }
      });
      try {
        studioAuto = sessionStorage.getItem('yoy_home_studio_auto') !== '0';
        autoBtn.classList.toggle('is-on', studioAuto);
        autoBtn.setAttribute('aria-pressed', studioAuto ? 'true' : 'false');
      } catch (e2) { /* ignore */ }
    }

    if (prompt && prompt.dataset.composerBound !== '1') {
      prompt.dataset.composerBound = '1';
      prompt.addEventListener('input', autosizePrompt);
      prompt.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          var create = document.getElementById('yai-home-create');
          if (create) create.click();
        }
      });
      autosizePrompt();
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.yai-home-composer__plus-wrap')) closePlusMenu();
    });

    try {
      var saved = sessionStorage.getItem('yoy_home_attachment');
      if (saved) setAttachment(JSON.parse(saved));
    } catch (e3) { /* ignore */ }
  }

  global.YooYHomeBottomComposer = {
    sync: syncVisibility,
    bind: bind,
    isStudioAuto: function () { return studioAuto; },
    getAttachment: function () { return attachment; },
    takePendingStudio: function () {
      var s = pendingStudio;
      pendingStudio = '';
      return s;
    },
    showIntentChoice: showIntentChoice,
    hideIntentChoice: hideIntentChoice,
    setStatus: setStatus,
    toast: toast
  };

  function boot() {
    bind();
    syncVisibility();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(typeof window !== 'undefined' ? window : this);
