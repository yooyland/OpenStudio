/**
 * YooY Global Original Image Viewer
 * Canonical full-resolution lightbox shared across Studio surfaces.
 */
(function (global) {
  'use strict';

  var ROOT_ID = 'yoy-original-image-viewer';
  var MIN_ZOOM = 0.25;
  var MAX_ZOOM = 8;
  var state = {
    open: false,
    mode: 'fit', // fit | percent | free
    scale: 1,
    fitScale: 1,
    naturalW: 0,
    naturalH: 0,
    url: '',
    title: '',
    mime: '',
    items: null,
    index: 0,
    panX: 0,
    panY: 0,
    dragging: false,
    dragStartX: 0,
    dragStartY: 0,
    panStartX: 0,
    panStartY: 0,
    lastFocus: null,
    loadToken: 0
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function pickFullUrl(item, explicitUrl) {
    if (explicitUrl) return String(explicitUrl).trim();
    if (!item) return '';
    if (global.YooYGalleryImage && typeof global.YooYGalleryImage.pickUrl === 'function') {
      return global.YooYGalleryImage.pickUrl(item, 'full')
        || global.YooYGalleryImage.pickUrl(item, 'large')
        || '';
    }
    return item.full_url || item.original_url || item.asset_url
      || item.image_url || item.output_url || item.url || item.large_url || '';
  }

  function guessMime(url, item) {
    if (item && item.mime) return String(item.mime);
    if (item && item.format) {
      var f = String(item.format).toLowerCase();
      if (f.indexOf('/') >= 0) return f;
      return 'image/' + f.replace(/^\./, '');
    }
    var m = String(url || '').match(/\.(png|jpe?g|webp|gif|svg)(?:\?|$)/i);
    if (!m) return '';
    var ext = m[1].toLowerCase();
    if (ext === 'jpg') ext = 'jpeg';
    return 'image/' + ext;
  }

  function safeTitle(item, fallback) {
    var t = (item && (item.title || item.display_title || item.caption)) || fallback || '';
    t = String(t).trim();
    if (!t || /광고\s*이미지|generated|untitled|quality\s*escalator|작품\s*\(/i.test(t)) {
      return '원본 이미지';
    }
    return t;
  }

  function ensureRoot() {
    var root = document.getElementById(ROOT_ID);
    if (root) return root;
    root = document.createElement('div');
    root.id = ROOT_ID;
    root.className = 'yoy-oiv';
    root.hidden = true;
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', '원본 이미지 보기');
    root.innerHTML =
      '<div class="yoy-oiv__backdrop" data-yoy-oiv-close></div>' +
      '<div class="yoy-oiv__shell" tabindex="-1">' +
        '<header class="yoy-oiv__bar">' +
          '<div class="yoy-oiv__title" id="yoy-oiv-title"></div>' +
          '<div class="yoy-oiv__meta" id="yoy-oiv-meta" aria-live="polite"></div>' +
          '<div class="yoy-oiv__controls" role="toolbar" aria-label="이미지 보기 도구">' +
            '<button type="button" class="yoy-oiv__btn" data-yoy-oiv-fit>화면 맞춤</button>' +
            '<button type="button" class="yoy-oiv__btn" data-yoy-oiv-100>100%</button>' +
            '<button type="button" class="yoy-oiv__btn" data-yoy-oiv-zoom-out aria-label="축소">−</button>' +
            '<button type="button" class="yoy-oiv__btn" data-yoy-oiv-zoom-in aria-label="확대">+</button>' +
            '<button type="button" class="yoy-oiv__btn yoy-oiv__btn--gold" data-yoy-oiv-download>다운로드</button>' +
            '<button type="button" class="yoy-oiv__btn" data-yoy-oiv-close aria-label="닫기">닫기</button>' +
          '</div>' +
        '</header>' +
        '<div class="yoy-oiv__stage" id="yoy-oiv-stage">' +
          '<div class="yoy-oiv__canvas" id="yoy-oiv-canvas">' +
            '<img class="yoy-oiv__img" id="yoy-oiv-img" alt="" draggable="false">' +
          '</div>' +
          '<div class="yoy-oiv__error" id="yoy-oiv-error" hidden>' +
            '<p>원본 이미지를 불러오지 못했습니다.</p>' +
            '<div class="yoy-oiv__error-actions">' +
              '<button type="button" class="yoy-oiv__btn yoy-oiv__btn--gold" data-yoy-oiv-retry>다시 시도</button>' +
              '<button type="button" class="yoy-oiv__btn" data-yoy-oiv-close>닫기</button>' +
            '</div>' +
          '</div>' +
          '<div class="yoy-oiv__loading" id="yoy-oiv-loading" hidden>불러오는 중…</div>' +
        '</div>' +
        '<button type="button" class="yoy-oiv__nav yoy-oiv__nav--prev" data-yoy-oiv-prev aria-label="이전" hidden>‹</button>' +
        '<button type="button" class="yoy-oiv__nav yoy-oiv__nav--next" data-yoy-oiv-next aria-label="다음" hidden>›</button>' +
      '</div>';
    document.body.appendChild(root);
    bindRoot(root);
    return root;
  }

  function lockScroll(on) {
    var cls = 'yoy-original-viewer-open';
    if (on) {
      document.documentElement.classList.add(cls);
      document.body.classList.add(cls);
    } else {
      document.documentElement.classList.remove(cls);
      document.body.classList.remove(cls);
    }
  }

  function setLoading(on) {
    var el = document.getElementById('yoy-oiv-loading');
    if (el) el.hidden = !on;
  }

  function setError(on) {
    var el = document.getElementById('yoy-oiv-error');
    var img = document.getElementById('yoy-oiv-img');
    if (el) el.hidden = !on;
    if (img) img.hidden = !!on;
  }

  function updateMeta() {
    var meta = document.getElementById('yoy-oiv-meta');
    if (!meta) return;
    var bits = [];
    if (state.naturalW && state.naturalH) {
      bits.push(state.naturalW + ' × ' + state.naturalH);
    }
    if (state.mime) {
      var shortMime = state.mime.replace(/^image\//i, '').toUpperCase();
      bits.push(shortMime);
    }
    if (state.mode === 'fit') bits.push('화면 맞춤');
    else if (state.mode === 'percent') bits.push('100%');
    else bits.push(Math.round(state.scale * 100) + '%');
    meta.textContent = bits.join(' · ');
  }

  function computeFitScale() {
    var stage = document.getElementById('yoy-oiv-stage');
    if (!stage || !state.naturalW || !state.naturalH) return 1;
    var pad = 24;
    var sw = Math.max(80, stage.clientWidth - pad);
    var sh = Math.max(80, stage.clientHeight - pad);
    return Math.min(1, sw / state.naturalW, sh / state.naturalH);
  }

  function applyTransform() {
    var canvas = document.getElementById('yoy-oiv-canvas');
    var img = document.getElementById('yoy-oiv-img');
    if (!canvas || !img) return;
    var s = state.scale;
    canvas.style.width = Math.round(state.naturalW * s) + 'px';
    canvas.style.height = Math.round(state.naturalH * s) + 'px';
    img.style.width = '100%';
    img.style.height = '100%';
    canvas.style.transform = 'translate(' + state.panX + 'px,' + state.panY + 'px)';
    canvas.classList.toggle('is-pannable', s > state.fitScale + 0.01);
    updateMeta();
  }

  function setFit() {
    state.mode = 'fit';
    state.fitScale = computeFitScale();
    state.scale = state.fitScale;
    state.panX = 0;
    state.panY = 0;
    applyTransform();
  }

  function set100() {
    state.mode = 'percent';
    // Never upscale beyond natural size.
    state.scale = 1;
    state.panX = 0;
    state.panY = 0;
    applyTransform();
  }

  function zoomBy(factor, cx, cy) {
    var stage = document.getElementById('yoy-oiv-stage');
    if (!stage) return;
    var prev = state.scale;
    var next = Math.max(MIN_ZOOM, Math.min(MAX_ZOOM, prev * factor));
    // Cap at natural size for intentional "100%" feel unless user zooms past via +
    // Allow free zoom above 1 for inspection, but 100% button stays at 1.
    if (next === prev) return;
    state.mode = 'free';
    var rect = stage.getBoundingClientRect();
    var pivotX = (cx != null ? cx : rect.left + rect.width / 2) - rect.left - rect.width / 2 - state.panX;
    var pivotY = (cy != null ? cy : rect.top + rect.height / 2) - rect.top - rect.height / 2 - state.panY;
    var ratio = next / prev;
    state.panX -= pivotX * (ratio - 1);
    state.panY -= pivotY * (ratio - 1);
    state.scale = next;
    applyTransform();
  }

  function loadImage(url) {
    var img = document.getElementById('yoy-oiv-img');
    if (!img || !url) {
      setError(true);
      return;
    }
    var token = ++state.loadToken;
    setError(false);
    setLoading(true);
    img.onload = function () {
      if (token !== state.loadToken) return;
      state.naturalW = img.naturalWidth || 0;
      state.naturalH = img.naturalHeight || 0;
      setLoading(false);
      setError(false);
      setFit();
    };
    img.onerror = function () {
      if (token !== state.loadToken) return;
      setLoading(false);
      setError(true);
    };
    img.alt = state.title || '원본 이미지';
    img.removeAttribute('src');
    img.src = url;
  }

  function syncNav() {
    var prev = document.querySelector('[data-yoy-oiv-prev]');
    var next = document.querySelector('[data-yoy-oiv-next]');
    var list = state.items;
    var show = !!(list && list.length > 1);
    if (prev) prev.hidden = !show;
    if (next) next.hidden = !show;
  }

  function openAtIndex(index) {
    if (!state.items || !state.items.length) return;
    var i = ((index % state.items.length) + state.items.length) % state.items.length;
    state.index = i;
    var entry = state.items[i] || {};
    var item = entry.item || entry;
    state.url = pickFullUrl(item, entry.url);
    state.title = safeTitle(item, entry.title);
    state.mime = guessMime(state.url, item);
    var titleEl = document.getElementById('yoy-oiv-title');
    if (titleEl) titleEl.textContent = state.title;
    syncNav();
    loadImage(state.url);
  }

  function downloadCurrent() {
    var url = state.url;
    if (!url) return;
    var a = document.createElement('a');
    a.href = url;
    var name = (state.title || 'yoy-original').replace(/[\\/:*?"<>|]+/g, '_');
    var ext = (state.mime && state.mime.split('/')[1]) || 'png';
    a.download = name + '.' + ext.replace('jpeg', 'jpg');
    a.rel = 'noopener';
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  function onKey(e) {
    if (!state.open) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key === 'Tab') {
      var root = document.getElementById(ROOT_ID);
      if (!root) return;
      var focusables = root.querySelectorAll('button:not([hidden]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      var list = [];
      for (var i = 0; i < focusables.length; i++) {
        if (!focusables[i].hidden && focusables[i].offsetParent !== null) list.push(focusables[i]);
      }
      if (!list.length) return;
      var first = list[0];
      var last = list[list.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
      return;
    }
    if (e.key === '+' || e.key === '=') {
      e.preventDefault();
      zoomBy(1.2);
      return;
    }
    if (e.key === '-' || e.key === '_') {
      e.preventDefault();
      zoomBy(1 / 1.2);
      return;
    }
    if (e.key === '0') {
      e.preventDefault();
      setFit();
      return;
    }
    if (e.key === 'ArrowLeft' && state.items && state.items.length > 1) {
      e.preventDefault();
      openAtIndex(state.index - 1);
      return;
    }
    if (e.key === 'ArrowRight' && state.items && state.items.length > 1) {
      e.preventDefault();
      openAtIndex(state.index + 1);
    }
  }

  function bindRoot(root) {
    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-yoy-oiv-close]')) {
        close();
        return;
      }
      if (e.target.closest('[data-yoy-oiv-fit]')) {
        setFit();
        return;
      }
      if (e.target.closest('[data-yoy-oiv-100]')) {
        set100();
        return;
      }
      if (e.target.closest('[data-yoy-oiv-zoom-in]')) {
        zoomBy(1.25);
        return;
      }
      if (e.target.closest('[data-yoy-oiv-zoom-out]')) {
        zoomBy(1 / 1.25);
        return;
      }
      if (e.target.closest('[data-yoy-oiv-download]')) {
        downloadCurrent();
        return;
      }
      if (e.target.closest('[data-yoy-oiv-retry]')) {
        loadImage(state.url);
        return;
      }
      if (e.target.closest('[data-yoy-oiv-prev]')) {
        openAtIndex(state.index - 1);
        return;
      }
      if (e.target.closest('[data-yoy-oiv-next]')) {
        openAtIndex(state.index + 1);
      }
    });

    var stage = root.querySelector('#yoy-oiv-stage');
    if (stage) {
      stage.addEventListener('wheel', function (e) {
        if (!state.open) return;
        e.preventDefault();
        var factor = e.deltaY < 0 ? 1.12 : 1 / 1.12;
        zoomBy(factor, e.clientX, e.clientY);
      }, { passive: false });

      stage.addEventListener('pointerdown', function (e) {
        if (e.button != null && e.button !== 0) return;
        if (state.scale <= state.fitScale + 0.01) return;
        state.dragging = true;
        state.dragStartX = e.clientX;
        state.dragStartY = e.clientY;
        state.panStartX = state.panX;
        state.panStartY = state.panY;
        stage.setPointerCapture(e.pointerId);
        stage.classList.add('is-dragging');
      });
      stage.addEventListener('pointermove', function (e) {
        if (!state.dragging) return;
        state.panX = state.panStartX + (e.clientX - state.dragStartX);
        state.panY = state.panStartY + (e.clientY - state.dragStartY);
        applyTransform();
      });
      function endDrag(e) {
        if (!state.dragging) return;
        state.dragging = false;
        stage.classList.remove('is-dragging');
        try { stage.releasePointerCapture(e.pointerId); } catch (err) { /* ignore */ }
      }
      stage.addEventListener('pointerup', endDrag);
      stage.addEventListener('pointercancel', endDrag);
    }

    global.addEventListener('resize', function () {
      if (!state.open) return;
      if (state.mode === 'fit') setFit();
      else applyTransform();
    });
  }

  function open(opts) {
    opts = opts || {};
    var root = ensureRoot();
    state.lastFocus = document.activeElement;
    state.items = Array.isArray(opts.items) && opts.items.length ? opts.items : null;
    state.index = opts.index || 0;

    var item = opts.item || null;
    if (!item && state.items && state.items[state.index]) {
      item = state.items[state.index].item || state.items[state.index];
    }
    state.url = pickFullUrl(item, opts.url);
    state.title = safeTitle(item, opts.title);
    state.mime = guessMime(state.url, item) || String(opts.mime || '');
    state.open = true;
    state.panX = 0;
    state.panY = 0;

    var titleEl = document.getElementById('yoy-oiv-title');
    if (titleEl) titleEl.textContent = state.title;

    root.hidden = false;
    lockScroll(true);
    syncNav();
    document.addEventListener('keydown', onKey, true);

    var shell = root.querySelector('.yoy-oiv__shell');
    if (shell) shell.focus();

    if (!state.url) {
      setLoading(false);
      setError(true);
      updateMeta();
      return;
    }
    loadImage(state.url);
  }

  function close() {
    if (!state.open) return;
    state.open = false;
    state.loadToken++;
    var root = document.getElementById(ROOT_ID);
    if (root) root.hidden = true;
    lockScroll(false);
    document.removeEventListener('keydown', onKey, true);
    if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
      try { state.lastFocus.focus(); } catch (e) { /* ignore */ }
    }
    state.lastFocus = null;
  }

  function openFromItem(item, extra) {
    extra = extra || {};
    open({
      item: item,
      title: extra.title,
      items: extra.items,
      index: extra.index || 0,
      url: extra.url,
      mime: extra.mime
    });
  }

  global.YooYOriginalImageViewer = {
    open: open,
    openFromItem: openFromItem,
    close: close,
    pickFullUrl: pickFullUrl,
    isOpen: function () { return !!state.open; }
  };
})(window);
