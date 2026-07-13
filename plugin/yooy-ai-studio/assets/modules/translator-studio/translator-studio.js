(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core || !Core.translator) {
    if (global.console && global.console.warn) {
      global.console.warn('[YooYTranslatorStudio] Core.translator unavailable');
    }
    return;
  }

  var MAX_CHARS = 20000;

  var state = {
    languages: [],
    modes: [],
    providers: [],
    sourceTypes: [],
    sourceType: 'text',
    sourceLanguage: 'auto',
    targetLanguage: 'en',
    mode: 'natural',
    sourceText: '',
    translatedText: '',
    detectedLanguage: '',
    providerName: 'Auto',
    providerId: 'auto',
    fallbackUsed: false,
    lastModel: '',
    openaiReady: false,
    history: [],
    galleryItemId: '',
    projectId: '',
    projectsEnabled: true,
    translating: false,
    error: '',
    toast: '',
    creditsLabel: '—',
    creditEstimate: 0,
    creditsEnabled: true,
    charCount: 0,
    abort: null
  };

  var SOURCE_TYPE_DEFS = [
    { id: 'text', label: 'Text', badge: 'TEXT', status: 'available' },
    { id: 'file', label: 'File', badge: 'FILE', status: 'planned' },
    { id: 'website', label: 'Website', badge: 'WEB', status: 'planned' },
    { id: 'image', label: 'Image', badge: 'OCR', status: 'planned' },
    { id: 'audio', label: 'Audio', badge: 'AUDIO', status: 'planned' },
    { id: 'video', label: 'Video', badge: 'VIDEO', status: 'planned' },
    { id: 'youtube', label: 'YouTube', badge: 'YOUTUBE', status: 'planned' }
  ];

  var SOURCE_TYPE_BADGE = {
    text: 'TEXT', file: 'FILE', website: 'WEB', image: 'OCR',
    audio: 'AUDIO', video: 'VIDEO', youtube: 'YOUTUBE'
  };

  var COMING_SOON_MSG = '해당 입력 방식은 다음 단계에서 제공될 예정입니다.';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function $(sel, root) {
    return (root || document).querySelector(sel);
  }

  function charCount(text) {
    return Array.from(String(text || '')).length;
  }

  function optionHtml(list, selected, filterTarget) {
    return list.map(function (item) {
      var code = item.code || item.id;
      if (filterTarget && code === 'auto') return '';
      return '<option value="' + esc(code) + '"' + (code === selected ? ' selected' : '') + '>' + esc(item.label) + '</option>';
    }).join('');
  }

  function modeOptionsHtml() {
    return state.modes.map(function (m) {
      return '<option value="' + esc(m.id) + '"' + (m.id === state.mode ? ' selected' : '') + '>' + esc(m.label) + '</option>';
    }).join('');
  }

  function sourceTypeTabsHtml() {
    var list = state.sourceTypes.length ? state.sourceTypes : SOURCE_TYPE_DEFS;
    return '<div class="yts-source-types" role="tablist" aria-label="입력 방식">' +
      list.map(function (t) {
        var id = t.id || t;
        var label = t.label || id;
        var status = t.status || (id === 'text' ? 'available' : 'planned');
        var active = state.sourceType === id ? ' is-active' : '';
        var planned = status !== 'available' ? ' is-planned' : '';
        return '<button type="button" class="yts-source-type' + active + planned + '" role="tab"' +
          ' data-source-type="' + esc(id) + '"' +
          ' aria-selected="' + (state.sourceType === id ? 'true' : 'false') + '"' +
          ' title="' + esc(status === 'available' ? label : (label + ' · Coming Soon')) + '">' +
          esc(label) +
          (status !== 'available' ? '<span class="yts-source-type-soon">Soon</span>' : '') +
          '</button>';
      }).join('') +
      '</div>';
  }

  function comingSoonPanel(typeId, title, bodyHtml) {
    return '<section class="yts-source-panel yts-source-panel--soon" data-source-panel="' + esc(typeId) + '" hidden aria-label="' + esc(title) + '">' +
      '<div class="yts-soon-banner" role="status">' +
        '<strong>' + esc(title) + '</strong>' +
        '<p>' + esc(COMING_SOON_MSG) + '</p>' +
      '</div>' +
      '<div class="yts-soon-scaffold" aria-hidden="true">' + bodyHtml + '</div>' +
      '</section>';
  }

  function plannedPanelsHtml() {
    return '' +
      comingSoonPanel('file', 'File',
        '<div class="yts-dropzone">' +
          '<p class="yts-dropzone-title">Drag & Drop</p>' +
          '<p class="yts-dropzone-hint">DOCX · PDF · PPTX · XLSX · TXT · MD · CSV · HTML · SRT · VTT</p>' +
          '<button type="button" class="yts-btn yts-btn--ghost" disabled>파일 선택</button>' +
        '</div>' +
        '<div class="yts-file-meta"><span>파일명 —</span><span>크기 —</span><button type="button" class="yts-btn yts-btn--ghost yts-btn--sm" disabled>제거</button></div>'
      ) +
      comingSoonPanel('website', 'Website',
        '<div class="yts-url-row">' +
          '<input type="url" class="yts-input" placeholder="https://example.com" disabled>' +
          '<button type="button" class="yts-btn yts-btn--ghost" disabled>가져오기</button>' +
        '</div>' +
        '<div class="yts-preview-card"><p class="yts-muted">제목 / 본문 미리보기</p></div>'
      ) +
      comingSoonPanel('image', 'Image · OCR',
        '<div class="yts-dropzone yts-dropzone--image">' +
          '<p class="yts-dropzone-title">이미지 업로드</p>' +
          '<p class="yts-dropzone-hint">OCR → 텍스트 추출 → 번역</p>' +
          '<button type="button" class="yts-btn yts-btn--ghost" disabled>이미지 선택</button>' +
        '</div>'
      ) +
      comingSoonPanel('audio', 'Audio',
        '<div class="yts-dropzone">' +
          '<p class="yts-dropzone-title">오디오 업로드</p>' +
          '<p class="yts-dropzone-hint">음성 인식 → 번역 → 번역 음성 생성</p>' +
          '<button type="button" class="yts-btn yts-btn--ghost" disabled>오디오 선택</button>' +
        '</div>'
      ) +
      comingSoonPanel('video', 'Video',
        '<div class="yts-dropzone">' +
          '<p class="yts-dropzone-title">영상 업로드</p>' +
          '<p class="yts-dropzone-hint">음성 추출 → 자막 생성 → 번역 → SRT/VTT</p>' +
          '<button type="button" class="yts-btn yts-btn--ghost" disabled>영상 선택</button>' +
        '</div>'
      ) +
      comingSoonPanel('youtube', 'YouTube',
        '<div class="yts-url-row">' +
          '<input type="url" class="yts-input" placeholder="https://www.youtube.com/watch?v=…" disabled>' +
          '<button type="button" class="yts-btn yts-btn--ghost" disabled>자막 확인</button>' +
        '</div>' +
        '<div class="yts-preview-card"><p class="yts-muted">자막 미리보기 · SRT 출력</p></div>'
      );
  }

  function shellHtml() {
    return '' +
      '<div class="yts-studio" id="yts-root">' +
        '<header class="yts-head">' +
          '<div class="yts-head-text">' +
            '<h1 class="yts-title">Translator</h1>' +
            '<p class="yts-desc">문맥과 목적에 맞게 자연스럽게 번역합니다.</p>' +
          '</div>' +
          '<div class="yts-head-meta">' +
            '<span class="yts-pill yts-pill--provider" id="yts-provider-pill">Provider · ' + esc(state.providerName) + '</span>' +
            '<span class="yts-pill" id="yts-credits-pill">' + esc(state.creditsLabel) + '</span>' +
          '</div>' +
        '</header>' +

        '<div id="yts-source-type-tabs">' + sourceTypeTabsHtml() + '</div>' +

        '<div class="yts-toolbar" id="yts-toolbar">' +
          '<label class="yts-field">' +
            '<span>원문 언어</span>' +
            '<select id="yts-source-lang" aria-label="원문 언어"></select>' +
          '</label>' +
          '<button type="button" class="yts-swap" id="yts-swap" title="언어 교환" aria-label="언어 교환">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="M7 10h13M16 5l5 5-5 5"/><path d="M17 14H4M8 19l-5-5 5-5"/></svg>' +
          '</button>' +
          '<label class="yts-field">' +
            '<span>대상 언어</span>' +
            '<select id="yts-target-lang" aria-label="대상 언어"></select>' +
          '</label>' +
          '<label class="yts-field yts-field--mode">' +
            '<span>번역 모드</span>' +
            '<select id="yts-mode" aria-label="번역 모드"></select>' +
          '</label>' +
        '</div>' +

        '<div class="yts-source-stage" id="yts-source-stage">' +
          '<section class="yts-source-panel" data-source-panel="text" aria-label="텍스트 원문">' +
            '<div class="yts-panels">' +
              '<section class="yts-panel yts-panel--source" aria-label="원문">' +
                '<div class="yts-panel-bar">' +
                  '<strong>원문</strong>' +
                  '<span class="yts-chars" id="yts-chars">0 / ' + MAX_CHARS + '</span>' +
                '</div>' +
                '<textarea id="yts-source" class="yts-textarea" maxlength="' + MAX_CHARS + '" placeholder="번역할 원문을 입력하세요…" rows="14"></textarea>' +
              '</section>' +
              '<section class="yts-panel yts-panel--result" aria-label="번역 결과">' +
                '<div class="yts-panel-bar">' +
                  '<strong>번역 결과</strong>' +
                  '<span class="yts-result-meta">' +
                    '<span class="yts-badge yts-badge--mock" id="yts-result-badge" hidden>Mock Translation</span>' +
                    '<span class="yts-detected" id="yts-detected"></span>' +
                  '</span>' +
                '</div>' +
                '<div id="yts-result" class="yts-result" tabindex="0" aria-live="polite"></div>' +
                '<div class="yts-result-actions">' +
                  '<button type="button" class="yts-btn yts-btn--ghost" id="yts-copy" disabled>복사</button>' +
                  '<button type="button" class="yts-btn yts-btn--ghost" id="yts-project" disabled hidden>Project 저장</button>' +
                  '<button type="button" class="yts-btn yts-btn--ghost" id="yts-clear" disabled>초기화</button>' +
                '</div>' +
              '</section>' +
            '</div>' +
          '</section>' +
          plannedPanelsHtml() +
        '</div>' +

        '<div class="yts-footer" id="yts-footer">' +
          '<p class="yts-hint" id="yts-hint">Auto Provider: OpenAI가 사용 가능하면 실제 번역을 수행하고, 실패 시 Mock으로 전환합니다.</p>' +
          '<button type="button" class="yts-btn yts-btn--primary" id="yts-translate">번역하기</button>' +
        '</div>' +

        '<section class="yts-history" aria-label="번역 기록">' +
          '<div class="yts-history-head">' +
            '<h2 class="yts-history-title">History</h2>' +
            '<button type="button" class="yts-btn yts-btn--ghost yts-btn--sm" id="yts-history-refresh">새로고침</button>' +
          '</div>' +
          '<div class="yts-history-list" id="yts-history-list">' +
            '<p class="yts-history-empty">아직 저장된 번역이 없습니다.</p>' +
          '</div>' +
        '</section>' +

        '<div class="yts-status" id="yts-status" hidden></div>' +
        '<div class="yts-toast" id="yts-toast" hidden></div>' +
      '</div>';
  }

  function fillSelects(root) {
    var src = $('#yts-source-lang', root);
    var tgt = $('#yts-target-lang', root);
    var mode = $('#yts-mode', root);
    if (src) src.innerHTML = optionHtml(state.languages, state.sourceLanguage, false);
    if (tgt) tgt.innerHTML = optionHtml(state.languages, state.targetLanguage, true);
    if (mode) mode.innerHTML = modeOptionsHtml();
  }

  function langIso(code) {
    var raw = String(code || '').trim();
    if (!raw || raw.toLowerCase() === 'auto') return 'AUTO';
    var base = raw.split(/[-_]/)[0];
    return base.toUpperCase().slice(0, 3);
  }

  function langBadgeHtml(source, target) {
    return '<span class="yts-lang-badge" title="' + esc(langIso(source) + ' → ' + langIso(target)) + '">' +
      '<span class="yts-lang-code">' + esc(langIso(source)) + '</span>' +
      '<span class="yts-lang-arrow" aria-hidden="true">→</span>' +
      '<span class="yts-lang-code">' + esc(langIso(target)) + '</span>' +
      '</span>';
  }

  function historyBucket(iso) {
    var d = iso ? new Date(iso) : null;
    if (!d || isNaN(d.getTime())) return 'older';
    var now = new Date();
    var startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    var startYesterday = new Date(startToday.getTime());
    startYesterday.setDate(startYesterday.getDate() - 1);
    var startWeek = new Date(startToday.getTime());
    startWeek.setDate(startWeek.getDate() - 7);
    if (d >= startToday) return 'today';
    if (d >= startYesterday) return 'yesterday';
    if (d >= startWeek) return 'last_week';
    return 'older';
  }

  var HISTORY_GROUP_LABELS = {
    today: 'Today',
    yesterday: 'Yesterday',
    last_week: 'Last Week',
    older: 'Older'
  };

  function sourceTypeBadgeHtml(sourceType) {
    var key = String(sourceType || 'text').toLowerCase();
    var label = SOURCE_TYPE_BADGE[key] || 'TEXT';
    return '<span class="yts-source-badge" title="Source · ' + esc(label) + '">' + esc(label) + '</span>';
  }

  function historyItemHtml(item) {
    var favClass = item.favorite ? ' is-favorite' : '';
    var srcLang = item.source_language === 'auto' && item.detected_language
      ? item.detected_language
      : item.source_language;
    return '<div class="yts-history-item' + favClass + '" data-history-id="' + esc(item.id) + '">' +
      '<button type="button" class="yts-history-main" data-history-reopen="' + esc(item.id) + '">' +
        '<span class="yts-history-item-top">' +
          sourceTypeBadgeHtml(item.source_type) +
          langBadgeHtml(srcLang, item.target_language) +
          '<span class="yts-history-item-title">' + esc(item.title || item.preview || 'Translation') + '</span>' +
        '</span>' +
        '<span class="yts-history-item-meta">' + esc(item.provider || '') +
          (item.project_id ? ' · Project' : '') +
        '</span>' +
      '</button>' +
      '<div class="yts-history-actions">' +
        '<button type="button" class="yts-history-action" data-history-fav="' + esc(item.id) + '" title="즐겨찾기" aria-label="즐겨찾기">' +
          (item.favorite ? '★' : '☆') +
        '</button>' +
        '<button type="button" class="yts-history-action" data-history-copy="' + esc(item.id) + '" title="번역문 복사" aria-label="번역문 복사">복사</button>' +
        '<button type="button" class="yts-history-action yts-history-action--danger" data-history-delete="' + esc(item.id) + '" title="삭제" aria-label="삭제">삭제</button>' +
      '</div>' +
    '</div>';
  }

  function isTextSource() {
    return state.sourceType === 'text';
  }

  function applySourceTypeUi(root) {
    var tabsHost = $('#yts-source-type-tabs', root);
    if (tabsHost) tabsHost.innerHTML = sourceTypeTabsHtml();

    var panels = root.querySelectorAll('[data-source-panel]');
    for (var i = 0; i < panels.length; i++) {
      var p = panels[i];
      var match = p.getAttribute('data-source-panel') === state.sourceType;
      p.hidden = !match;
    }

    var toolbar = $('#yts-toolbar', root);
    var footer = $('#yts-footer', root);
    var textMode = isTextSource();
    if (toolbar) toolbar.hidden = !textMode;
    if (footer) footer.hidden = !textMode;

    if (!textMode) {
      showStatus(root, 'info', COMING_SOON_MSG);
    } else {
      showStatus(root, '', '');
    }
    updateMeta(root);
  }

  function setSourceType(root, typeId) {
    var id = String(typeId || 'text');
    var known = false;
    var list = state.sourceTypes.length ? state.sourceTypes : SOURCE_TYPE_DEFS;
    for (var i = 0; i < list.length; i++) {
      if ((list[i].id || list[i]) === id) { known = true; break; }
    }
    if (!known) id = 'text';
    state.sourceType = id;
    applySourceTypeUi(root);
    if (id !== 'text') {
      showToast(root, COMING_SOON_MSG);
    }
  }

  function renderHistory(root) {
    var list = $('#yts-history-list', root);
    if (!list) return;
    if (!state.history.length) {
      list.innerHTML = '<p class="yts-history-empty">아직 저장된 번역이 없습니다.</p>';
      return;
    }
    var order = ['today', 'yesterday', 'last_week', 'older'];
    var groups = { today: [], yesterday: [], last_week: [], older: [] };
    state.history.forEach(function (item) {
      groups[historyBucket(item.created_at)].push(item);
    });
    var html = '';
    order.forEach(function (key) {
      if (!groups[key].length) return;
      html += '<div class="yts-history-group" data-history-group="' + key + '">' +
        '<h3 class="yts-history-group-title">' + HISTORY_GROUP_LABELS[key] + '</h3>' +
        groups[key].map(historyItemHtml).join('') +
        '</div>';
    });
    list.innerHTML = html;
  }

  function loadHistory(root) {
    if (!Core.translator.history) return Promise.resolve();
    return Core.translator.history({ limit: 40 }).then(function (res) {
      var data = (res && (res.data || res)) || {};
      state.history = data.items || [];
      renderHistory(root);
    }).catch(function () {
      state.history = [];
      renderHistory(root);
    });
  }

  function findHistoryItem(id) {
    for (var i = 0; i < state.history.length; i++) {
      if (state.history[i].id === id) return state.history[i];
    }
    return null;
  }

  function copyText(text, root, okMsg) {
    var value = String(text || '');
    if (!value) {
      showToast(root, '복사할 내용이 없습니다.');
      return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(function () {
        showToast(root, okMsg || '복사되었습니다.');
      }).catch(function () {
        fallbackCopy(value);
        showToast(root, okMsg || '복사되었습니다.');
      });
      return;
    }
    fallbackCopy(value);
    showToast(root, okMsg || '복사되었습니다.');
  }

  function toggleFavoriteHistory(root, id) {
    if (!id || !Core.translator.favorite) return;
    Core.translator.favorite(id).then(function (res) {
      var data = (res && (res.data || res)) || {};
      var item = data.item || null;
      if (item && item.id) {
        state.history = state.history.map(function (h) {
          return h.id === item.id ? Object.assign({}, h, item) : h;
        });
        renderHistory(root);
        showToast(root, item.favorite ? '즐겨찾기에 추가했습니다.' : '즐겨찾기를 해제했습니다.');
      }
    }).catch(function (e) {
      showStatus(root, 'error', (e && e.message) || '즐겨찾기를 변경할 수 없습니다.');
    });
  }

  function deleteHistoryItem(root, id) {
    if (!id || !Core.translator.removeHistory) return;
    if (!global.confirm('이 번역 기록을 삭제할까요?')) return;
    Core.translator.removeHistory(id).then(function () {
      state.history = state.history.filter(function (h) { return h.id !== id; });
      if (state.galleryItemId === id) state.galleryItemId = '';
      renderHistory(root);
      showToast(root, '기록을 삭제했습니다.');
      if (Core.notifyGalleryUpdated) Core.notifyGalleryUpdated();
      document.dispatchEvent(new CustomEvent('yoy:gallery:updated'));
    }).catch(function (e) {
      showStatus(root, 'error', (e && e.message) || '기록을 삭제할 수 없습니다.');
    });
  }

  function applyReopenPayload(root, payload) {
    if (!payload) return;
    state.sourceText = payload.source_text || payload.text || payload.prompt || '';
    state.translatedText = payload.translated_text || '';
    state.sourceLanguage = payload.source_language || state.sourceLanguage;
    state.targetLanguage = payload.target_language || state.targetLanguage;
    state.mode = payload.mode || state.mode;
    state.detectedLanguage = payload.detected_language || '';
    state.galleryItemId = payload.gallery_item_id || payload.id || '';
    state.projectId = payload.project_id || '';
    state.fallbackUsed = false;
    state.providerId = payload.provider || state.providerId;
    state.lastModel = payload.model || '';
    if (state.providerId === 'openai') {
      state.providerName = 'OpenAI · ' + (state.lastModel || '');
    } else if (state.providerId === 'mock') {
      state.providerName = 'Mock Translator · ' + (state.lastModel || 'mock-translator-v1');
    }
    var ta = $('#yts-source', root);
    if (ta) ta.value = state.sourceText;
    fillSelects(root);
    setResult(root, state.translatedText, false);
    updateMeta(root);
  }

  function reopenHistory(root, id) {
    if (!id) return;
    var req = Core.translator.reopen
      ? Core.translator.reopen(id)
      : Core.translator.historyItem(id);
    req.then(function (res) {
      var data = (res && (res.data || res)) || {};
      var payload = data.item || data;
      applyReopenPayload(root, payload);
      showToast(root, '기록을 불러왔습니다.');
    }).catch(function (e) {
      showStatus(root, 'error', (e && e.message) || '기록을 열 수 없습니다.');
    });
  }

  function consumeRegeneratePayload(root) {
    try {
      var raw = sessionStorage.getItem('yoy_regenerate');
      if (!raw) return;
      var payload = JSON.parse(raw);
      if (!payload || (payload.type !== 'translation' && payload.studio !== 'translator-studio')) return;
      sessionStorage.removeItem('yoy_regenerate');
      applyReopenPayload(root, {
        source_text: payload.source_text || payload.text || payload.prompt || payload.user_prompt || '',
        translated_text: payload.translated_text || '',
        source_language: payload.source_language || (payload.settings && payload.settings.source_language) || 'auto',
        target_language: payload.target_language || (payload.settings && payload.settings.target_language) || 'en',
        mode: payload.mode || (payload.settings && payload.settings.mode) || 'natural',
        provider: payload.provider || 'auto',
        model: payload.model || '',
        gallery_item_id: (payload.remix_source && payload.remix_source.gallery_id) || ''
      });
      showToast(root, 'Gallery에서 번역을 불러왔습니다.');
    } catch (e) { /* ignore */ }
  }

  function setResult(root, text, loading) {
    var el = $('#yts-result', root);
    if (!el) return;
    el.classList.toggle('is-loading', !!loading);
    el.textContent = '';
    if (loading) {
      el.appendChild(document.createTextNode(''));
      var sk = document.createElement('div');
      sk.className = 'yts-skeleton';
      sk.setAttribute('aria-hidden', 'true');
      el.appendChild(sk);
      return;
    }
    el.textContent = text || '';
    if (!text) {
      var ph = document.createElement('span');
      ph.className = 'yts-placeholder';
      ph.textContent = '번역 결과가 여기에 표시됩니다.';
      el.appendChild(ph);
    }
  }

  function showStatus(root, type, message) {
    var el = $('#yts-status', root);
    if (!el) return;
    if (!message) {
      el.hidden = true;
      el.textContent = '';
      el.className = 'yts-status';
      return;
    }
    el.hidden = false;
    el.className = 'yts-status yts-status--' + (type || 'error');
    el.textContent = message;
  }

  function showToast(root, message) {
    var el = $('#yts-toast', root);
    if (!el || !message) return;
    el.hidden = false;
    el.textContent = message;
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () {
      el.hidden = true;
    }, 2200);
  }

  function providerPillText() {
    if (state.fallbackUsed) {
      return 'Provider · Mock Fallback';
    }
    if (state.providerId === 'openai') {
      return 'Provider · OpenAI · ' + (state.lastModel || 'gpt-4o-mini');
    }
    if (state.providerId === 'mock') {
      return 'Provider · Mock Translator · ' + (state.lastModel || 'mock-translator-v1');
    }
    return 'Provider · Auto' + (state.openaiReady ? ' · OpenAI preferred' : ' · Mock ready');
  }

  function updateMeta(root) {
    state.charCount = charCount(state.sourceText);
    var chars = $('#yts-chars', root);
    if (chars) {
      chars.textContent = state.charCount + ' / ' + MAX_CHARS;
      chars.classList.toggle('is-warn', state.charCount > MAX_CHARS * 0.9);
    }
    var det = $('#yts-detected', root);
    if (det) {
      det.textContent = state.detectedLanguage
        ? ('감지: ' + state.detectedLanguage)
        : '';
    }
    var badge = $('#yts-result-badge', root);
    if (badge) {
      if (!state.translatedText) {
        badge.hidden = true;
      } else if (state.fallbackUsed) {
        badge.hidden = false;
        badge.className = 'yts-badge yts-badge--fallback';
        badge.textContent = 'Mock Fallback';
      } else if (state.providerId === 'mock') {
        badge.hidden = false;
        badge.className = 'yts-badge yts-badge--mock';
        badge.textContent = 'Mock Translation';
      } else if (state.providerId === 'openai') {
        badge.hidden = false;
        badge.className = 'yts-badge yts-badge--openai';
        badge.textContent = 'OpenAI';
      } else {
        badge.hidden = true;
      }
    }
    var hint = $('#yts-hint', root);
    if (hint) {
      if (state.fallbackUsed) {
        hint.textContent = 'OpenAI 번역에 실패하여 Mock Fallback으로 표시했습니다. 실제 번역 품질은 OpenAI 연결 상태를 확인한 뒤 다시 시도하세요.';
      } else if (state.providerId === 'openai') {
        hint.textContent = 'OpenAI로 번역되었습니다. 결과는 Gallery(My Works)와 History에 저장됩니다.';
      } else if (state.providerId === 'mock') {
        hint.textContent = 'Mock Provider는 화면과 작업 흐름을 확인하기 위한 개발용 번역 시뮬레이션입니다. 실제 번역 품질은 OpenAI·Google·DeepL Provider 연결 후 제공됩니다.';
      } else if (state.openaiReady) {
        hint.textContent = 'Auto Provider: OpenAI가 사용 가능하면 실제 번역을 수행하고, 실패 시 Mock으로 전환합니다.';
      } else {
        hint.textContent = 'OpenAI API 키가 없어 Mock으로 동작합니다. Operations Center에서 OpenAI 키를 설정하면 실제 번역이 활성화됩니다.';
      }
    }
    var pill = $('#yts-provider-pill', root);
    if (pill) pill.textContent = providerPillText();
    var cred = $('#yts-credits-pill', root);
    if (cred) {
      var est = state.creditsEnabled && state.creditEstimate > 0
        ? (' · 예상 ' + state.creditEstimate)
        : '';
      cred.textContent = state.creditsLabel + est;
    }
    var copyBtn = $('#yts-copy', root);
    var clearBtn = $('#yts-clear', root);
    var projectBtn = $('#yts-project', root);
    var hasResult = !!state.translatedText;
    if (copyBtn) copyBtn.disabled = !hasResult;
    if (clearBtn) clearBtn.disabled = !hasResult && !state.sourceText;
    if (projectBtn) {
      projectBtn.hidden = !state.projectsEnabled;
      projectBtn.disabled = !state.galleryItemId || !state.projectsEnabled;
    }
    var btn = $('#yts-translate', root);
    if (btn) {
      var canRun = isTextSource() && !state.translating;
      btn.disabled = !canRun;
      btn.textContent = state.translating ? '번역 중…' : '번역하기';
    }
  }

  function syncFromDom(root) {
    var ta = $('#yts-source', root);
    if (ta) state.sourceText = ta.value;
    var src = $('#yts-source-lang', root);
    var tgt = $('#yts-target-lang', root);
    var mode = $('#yts-mode', root);
    if (src) state.sourceLanguage = src.value;
    if (tgt) state.targetLanguage = tgt.value;
    if (mode) state.mode = mode.value;
  }

  var SAME_LANG_MSG = '감지된 원문 언어와 대상 언어가 같습니다. 다른 대상 언어를 선택해 주세요.';

  function normalizeLang(code) {
    var c = String(code || '').trim().replace(/_/g, '-');
    if (!c || c.toLowerCase() === 'auto') return 'auto';
    var lower = c.toLowerCase();
    if (lower === 'zh-tw' || lower.indexOf('zh-tw') === 0 || lower === 'zh-hant') return 'zh-TW';
    if (lower === 'zh' || lower === 'zh-cn' || lower.indexOf('zh-cn') === 0 || lower.indexOf('zh-hans') === 0) return 'zh-CN';
    var primary = lower.split('-')[0];
    var map = { ko: 'ko', en: 'en', ja: 'ja', es: 'es', fr: 'fr', de: 'de', it: 'it', pt: 'pt', ru: 'ru', vi: 'vi', th: 'th', id: 'id', ar: 'ar', hi: 'hi' };
    return map[primary] || c;
  }

  function detectLangClient(text) {
    var sample = String(text || '').trim();
    if (!sample) return 'en';
    if (/[\u3040-\u30FF]/.test(sample)) return 'ja';
    if (/[\u4E00-\u9FFF]/.test(sample)) return 'zh-CN';
    if (/[\uAC00-\uD7A3]/.test(sample)) return 'ko';
    if (/[\u0400-\u04FF]/.test(sample)) return 'ru';
    if (/[\u0600-\u06FF]/.test(sample)) return 'ar';
    if (/[\u0E00-\u0E7F]/.test(sample)) return 'th';
    return 'en';
  }

  function clientValidate() {
    if (!String(state.sourceText || '').trim()) {
      return '원문을 입력해 주세요.';
    }
    if (charCount(state.sourceText) > MAX_CHARS) {
      return '원문은 최대 ' + MAX_CHARS + '자까지 입력할 수 있습니다.';
    }
    var src = normalizeLang(state.sourceLanguage);
    var tgt = normalizeLang(state.targetLanguage);
    if (src !== 'auto' && src === tgt) {
      return SAME_LANG_MSG;
    }
    // Soft client check for auto — server still enforces.
    if (src === 'auto' && detectLangClient(state.sourceText) === tgt) {
      return SAME_LANG_MSG;
    }
    return '';
  }

  function doTranslate(root) {
    if (state.translating) return;
    if (!isTextSource()) {
      showStatus(root, 'info', COMING_SOON_MSG);
      showToast(root, COMING_SOON_MSG);
      return;
    }
    syncFromDom(root);
    var err = clientValidate();
    if (err) {
      showStatus(root, 'error', err);
      return;
    }
    showStatus(root, '', '');
    var previousResult = state.translatedText;
    state.translating = true;
    state.error = '';
    state.fallbackUsed = false;
    updateMeta(root);
    setResult(root, '', true);

    if (state.abort) {
      try { state.abort.abort(); } catch (e) { /* ignore */ }
    }
    state.abort = (typeof AbortController !== 'undefined') ? new AbortController() : null;

    var body = {
      source_type: 'text',
      text: state.sourceText,
      source_language: state.sourceLanguage,
      target_language: state.targetLanguage,
      mode: state.mode,
      context: '',
      provider: 'auto'
    };

    Core.translator.translate(body, state.abort ? state.abort.signal : null).then(function (res) {
      var data = (res && (res.data || res)) || {};
      state.translatedText = data.translated_text || '';
      state.detectedLanguage = data.detected_language || '';
      state.fallbackUsed = !!data.fallback_used;
      state.lastModel = data.model || '';
      state.galleryItemId = data.gallery_item_id || '';
      state.projectId = data.project_id || '';
      if (data.provider) state.providerId = data.provider;
      if (state.fallbackUsed) {
        state.providerName = 'Mock Fallback';
      } else if (data.provider === 'openai') {
        state.providerName = 'OpenAI · ' + (data.model || '');
      } else if (data.provider === 'mock') {
        state.providerName = 'Mock Translator · ' + (data.model || 'mock-translator-v1');
      } else {
        state.providerName = data.provider || 'Auto';
      }
      state.translating = false;
      setResult(root, state.translatedText, false);
      updateMeta(root);
      var toastMsg = '번역이 완료되었습니다.';
      if (data.saved) toastMsg = '번역 완료 · Language Asset 저장됨';
      var cred = data.credits || {};
      if (cred.deducted > 0) {
        toastMsg += ' · -' + cred.deducted + ' credits';
      } else if (cred.unlimited) {
        toastMsg += ' · Unlimited';
      } else if (cred.skipped && (cred.reason === 'mock' || cred.reason === 'fallback')) {
        toastMsg += ' · 무료';
      }
      showToast(root, toastMsg);
      loadCreditsLabel().then(function () { updateMeta(root); });
      refreshEstimate(root);
      loadHistory(root);
      if (Core.notifyGalleryUpdated) Core.notifyGalleryUpdated();
      document.dispatchEvent(new CustomEvent('yoy:gallery:updated'));
    }).catch(function (e) {
      if (e && e.name === 'AbortError') return;
      state.translating = false;
      var code = (e && e.code) || (e && e.details && e.details.code) || '';
      var msg = (e && e.message) ? e.message : '번역에 실패했습니다.';
      if (code === 'same_source_target_language') {
        msg = SAME_LANG_MSG;
      }
      if (code === 'insufficient_credits') {
        msg = (e && e.message) || '크레딧이 부족합니다.';
      }
      if (code === 'gallery_save_failed') {
        msg = (e && e.message) || '저장에 실패하여 크레딧을 차감하지 않았습니다.';
      }
      if (code === 'source_type_not_implemented') {
        msg = COMING_SOON_MSG;
      }
      state.error = msg;
      // Do not invent a new result; restore previous display if any.
      state.translatedText = previousResult;
      setResult(root, previousResult, false);
      updateMeta(root);
      showStatus(root, 'error', msg);
      if (code === 'same_source_target_language') {
        showToast(root, msg);
      }
    });
  }

  function doSwap(root) {
    syncFromDom(root);
    if (state.sourceLanguage === 'auto') {
      showToast(root, '자동 감지 상태에서는 언어를 교환할 수 없습니다. 원문 언어를 선택하세요.');
      return;
    }
    var tmp = state.sourceLanguage;
    state.sourceLanguage = state.targetLanguage;
    state.targetLanguage = tmp;
    if (state.translatedText && state.sourceText) {
      var t = state.sourceText;
      state.sourceText = state.translatedText;
      state.translatedText = t;
      var ta = $('#yts-source', root);
      if (ta) ta.value = state.sourceText;
    }
    fillSelects(root);
    setResult(root, state.translatedText, false);
    updateMeta(root);
  }

  function doCopy(root) {
    if (!state.translatedText) return;
    var done = function () { showToast(root, '번역 결과를 복사했습니다.'); };
    if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
      global.navigator.clipboard.writeText(state.translatedText).then(done).catch(function () {
        fallbackCopy(state.translatedText);
        done();
      });
    } else {
      fallbackCopy(state.translatedText);
      done();
    }
  }

  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); } catch (e) { /* ignore */ }
    document.body.removeChild(ta);
  }

  function doClear(root) {
    if (state.abort) {
      try { state.abort.abort(); } catch (e) { /* ignore */ }
      state.abort = null;
    }
    state.sourceText = '';
    state.translatedText = '';
    state.detectedLanguage = '';
    state.error = '';
    state.translating = false;
    state.fallbackUsed = false;
    state.lastModel = '';
    state.providerId = 'auto';
    state.providerName = 'Auto';
    state.galleryItemId = '';
    state.projectId = '';
    var ta = $('#yts-source', root);
    if (ta) ta.value = '';
    setResult(root, '', false);
    showStatus(root, '', '');
    updateMeta(root);
  }

  function doSaveProject(root) {
    if (!state.galleryItemId || !state.projectsEnabled) return;
    if (!Core.gallery || !Core.gallery.project) {
      showStatus(root, 'error', 'Gallery Project API를 사용할 수 없습니다.');
      return;
    }
    // Same path as Music/Image: POST /gallery/items/{id}/project (null → create/link My Project).
    Core.gallery.project(state.galleryItemId).then(function (res) {
      var data = (res && (res.data || res)) || {};
      var project = data.project || null;
      var item = data.item || null;
      if (item && item.project_id) state.projectId = item.project_id;
      else if (project && project.id) state.projectId = project.id;
      state.history = state.history.map(function (h) {
        if (h.id !== state.galleryItemId) return h;
        return Object.assign({}, h, { project_id: state.projectId });
      });
      renderHistory(root);
      updateMeta(root);
      showToast(root, 'Project에 저장됨: ' + ((project && project.title) || 'My Project'));
      if (Core.notifyGalleryUpdated) Core.notifyGalleryUpdated();
      document.dispatchEvent(new CustomEvent('yoy:gallery:updated'));
    }).catch(function (e) {
      showStatus(root, 'error', (e && e.message) || 'Project에 저장할 수 없습니다.');
    });
  }

  function bindEvents(root) {
    if (root.dataset.ytsBound === '1') return;
    root.dataset.ytsBound = '1';

    root.addEventListener('click', function (e) {
      var typeBtn = e.target.closest('[data-source-type]');
      if (typeBtn) {
        setSourceType(root, typeBtn.getAttribute('data-source-type'));
        return;
      }
      if (e.target.closest('#yts-translate')) { doTranslate(root); return; }
      if (e.target.closest('#yts-swap')) { doSwap(root); return; }
      if (e.target.closest('#yts-copy')) { doCopy(root); return; }
      if (e.target.closest('#yts-project')) { doSaveProject(root); return; }
      if (e.target.closest('#yts-clear')) { doClear(root); return; }
      if (e.target.closest('#yts-history-refresh')) { loadHistory(root); return; }
      var reopenBtn = e.target.closest('[data-history-reopen]');
      if (reopenBtn) {
        reopenHistory(root, reopenBtn.getAttribute('data-history-reopen'));
        return;
      }
      var favBtn = e.target.closest('[data-history-fav]');
      if (favBtn) {
        e.preventDefault();
        toggleFavoriteHistory(root, favBtn.getAttribute('data-history-fav'));
        return;
      }
      var copyBtn = e.target.closest('[data-history-copy]');
      if (copyBtn) {
        e.preventDefault();
        var copyId = copyBtn.getAttribute('data-history-copy');
        var hist = findHistoryItem(copyId);
        copyText(hist && hist.translated_text, root, '번역문을 복사했습니다.');
        return;
      }
      var delBtn = e.target.closest('[data-history-delete]');
      if (delBtn) {
        e.preventDefault();
        deleteHistoryItem(root, delBtn.getAttribute('data-history-delete'));
        return;
      }
    });

    root.addEventListener('input', function (e) {
      if (e.target && e.target.id === 'yts-source') {
        state.sourceText = e.target.value;
        updateMeta(root);
        showStatus(root, '', '');
        scheduleEstimate(root);
      }
    });

    root.addEventListener('change', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.id === 'yts-source-lang') state.sourceLanguage = t.value;
      if (t.id === 'yts-target-lang') state.targetLanguage = t.value;
      if (t.id === 'yts-mode') state.mode = t.value;
    });

    root.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        doTranslate(root);
      }
    });
  }

  function loadCreditsLabel() {
    if (Core.translator && Core.translator.credits) {
      return Core.translator.credits().then(function (res) {
        var d = (res && (res.data || res)) || {};
        if (d.unlimited) {
          state.creditsLabel = 'Credits · Unlimited';
        } else if (typeof d.balance !== 'undefined') {
          state.creditsLabel = 'Credits · ' + d.balance;
        } else {
          state.creditsLabel = 'Credits · —';
        }
      }).catch(function () {
        return loadCreditsLabelFallback();
      });
    }
    return loadCreditsLabelFallback();
  }

  function loadCreditsLabelFallback() {
    if (!Core.credits || !Core.credits.balance) {
      state.creditsLabel = 'Credits · —';
      return Promise.resolve();
    }
    return Core.credits.balance().then(function (res) {
      var d = (res && (res.data || res)) || {};
      if (d.unlimited) {
        state.creditsLabel = 'Credits · Unlimited';
      } else if (typeof d.balance !== 'undefined') {
        state.creditsLabel = 'Credits · ' + d.balance;
      } else {
        state.creditsLabel = 'Credits · —';
      }
    }).catch(function () {
      state.creditsLabel = 'Credits · —';
    });
  }

  function refreshEstimate(root) {
    if (!state.creditsEnabled || !Core.translator.estimate) {
      state.creditEstimate = 0;
      return Promise.resolve();
    }
    var text = state.sourceText || '';
    if (!String(text).trim()) {
      state.creditEstimate = 0;
      if (root) updateMeta(root);
      return Promise.resolve();
    }
    return Core.translator.estimate({
      text: text,
      provider: 'auto'
    }).then(function (res) {
      var d = (res && (res.data || res)) || {};
      state.creditEstimate = parseInt(d.estimate, 10) || 0;
      if (d.unlimited) {
        state.creditsLabel = 'Credits · Unlimited';
      } else if (typeof d.balance !== 'undefined') {
        state.creditsLabel = 'Credits · ' + d.balance;
      }
      if (root) updateMeta(root);
    }).catch(function () {
      state.creditEstimate = 0;
      if (root) updateMeta(root);
    });
  }

  var estimateTimer = null;
  function scheduleEstimate(root) {
    clearTimeout(estimateTimer);
    estimateTimer = setTimeout(function () {
      refreshEstimate(root);
    }, 400);
  }

  function mount(container) {
    if (!container || container.dataset.mounted === '1') return;
    container.dataset.mounted = '1';
    container.innerHTML = shellHtml();
    var root = $('#yts-root', container) || container;
    bindEvents(root);
    setResult(root, '', false);
    updateMeta(root);

    Promise.all([
      Core.translator.config().catch(function () { return { data: {} }; }),
      Core.translator.languages().catch(function () { return { data: { languages: [] } }; }),
      loadCreditsLabel(),
      loadHistory(root)
    ]).then(function (res) {
      var cfg = (res[0] && (res[0].data || res[0])) || {};
      var langs = (res[1] && res[1].data && res[1].data.languages) || cfg.languages || [];
      state.languages = langs;
      state.modes = cfg.modes || [];
      state.providers = cfg.providers || [];
      if (cfg.max_chars) MAX_CHARS = parseInt(cfg.max_chars, 10) || MAX_CHARS;
      state.openaiReady = !!cfg.openai_ready;
      state.projectsEnabled = !(cfg.features && cfg.features.projects === false);
      state.creditsEnabled = !(cfg.features && cfg.features.credits === false);
      state.sourceTypes = Array.isArray(cfg.source_types) && cfg.source_types.length
        ? cfg.source_types
        : SOURCE_TYPE_DEFS.slice();
      state.sourceType = cfg.default_source_type || 'text';
      state.providerId = 'auto';
      state.providerName = 'Auto';
      state.fallbackUsed = false;
      state.lastModel = '';
      if (!state.modes.length) {
        state.modes = [
          { id: 'natural', label: '자연스러운 번역' },
          { id: 'literal', label: '정확한 직역' },
          { id: 'business', label: '비즈니스' },
          { id: 'formal', label: '격식체' },
          { id: 'casual', label: '친근한 말투' },
          { id: 'marketing', label: '광고·마케팅' },
          { id: 'email', label: '이메일' },
          { id: 'document', label: '계약·문서' },
          { id: 'social', label: 'SNS' },
          { id: 'subtitle', label: '자막' }
        ];
      }
      fillSelects(root);
      applySourceTypeUi(root);
      updateMeta(root);
      refreshEstimate(root);
      consumeRegeneratePayload(root);
    }).catch(function (e) {
      showStatus(root, 'error', (e && e.message) || 'Translator 설정을 불러오지 못했습니다.');
    });
  }

  global.YooYTranslatorStudio = {
    mount: mount
  };
})(window);
