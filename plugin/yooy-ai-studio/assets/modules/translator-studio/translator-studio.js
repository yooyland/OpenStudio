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
    projectId: '',
    projects: [],
    history: [],
    galleryItemId: '',
    translating: false,
    error: '',
    toast: '',
    creditsLabel: '—',
    charCount: 0,
    abort: null
  };

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

        '<div class="yts-toolbar">' +
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
          '<label class="yts-field yts-field--project">' +
            '<span>Project</span>' +
            '<select id="yts-project" aria-label="Project">' +
              '<option value="">저장만 (프로젝트 없음)</option>' +
            '</select>' +
          '</label>' +
        '</div>' +

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
              '<button type="button" class="yts-btn yts-btn--ghost" id="yts-clear" disabled>초기화</button>' +
            '</div>' +
          '</section>' +
        '</div>' +

        '<div class="yts-footer">' +
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
    var project = $('#yts-project', root);
    if (src) src.innerHTML = optionHtml(state.languages, state.sourceLanguage, false);
    if (tgt) tgt.innerHTML = optionHtml(state.languages, state.targetLanguage, true);
    if (mode) mode.innerHTML = modeOptionsHtml();
    if (project) {
      var opts = '<option value="">저장만 (프로젝트 없음)</option>';
      state.projects.forEach(function (p) {
        var id = p.id || '';
        opts += '<option value="' + esc(id) + '"' + (id === state.projectId ? ' selected' : '') + '>' +
          esc(p.title || id) + '</option>';
      });
      project.innerHTML = opts;
    }
  }

  function renderHistory(root) {
    var list = $('#yts-history-list', root);
    if (!list) return;
    if (!state.history.length) {
      list.innerHTML = '<p class="yts-history-empty">아직 저장된 번역이 없습니다.</p>';
      return;
    }
    list.innerHTML = state.history.map(function (item) {
      var langs = esc((item.source_language || '?') + ' → ' + (item.target_language || '?'));
      return '<button type="button" class="yts-history-item" data-history-id="' + esc(item.id) + '">' +
        '<span class="yts-history-item-title">' + esc(item.title || item.preview || 'Translation') + '</span>' +
        '<span class="yts-history-item-meta">' + langs + ' · ' + esc(item.provider || '') +
          (item.credits_used ? (' · ' + item.credits_used + ' cr') : '') + '</span>' +
      '</button>';
    }).join('');
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

  function loadProjects() {
    if (!Core.projects || typeof Core.projects.list !== 'function') {
      state.projects = [];
      return Promise.resolve();
    }
    return Core.projects.list().then(function (res) {
      var data = (res && (res.data || res)) || {};
      state.projects = data.projects || data.items || [];
    }).catch(function () {
      state.projects = [];
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
    state.projectId = payload.project_id || '';
    state.galleryItemId = payload.gallery_item_id || payload.id || '';
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
        project_id: payload.project_id || '',
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
        hint.textContent = 'OpenAI로 번역되었습니다. Gallery·History·Credits 연동은 다음 Phase에서 제공됩니다.';
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
    if (cred) cred.textContent = state.creditsLabel;
    var copyBtn = $('#yts-copy', root);
    var clearBtn = $('#yts-clear', root);
    var hasResult = !!state.translatedText;
    if (copyBtn) copyBtn.disabled = !hasResult;
    if (clearBtn) clearBtn.disabled = !hasResult && !state.sourceText;
    var btn = $('#yts-translate', root);
    if (btn) {
      btn.disabled = !!state.translating;
      btn.textContent = state.translating ? '번역 중…' : '번역하기';
    }
  }

  function syncFromDom(root) {
    var ta = $('#yts-source', root);
    if (ta) state.sourceText = ta.value;
    var src = $('#yts-source-lang', root);
    var tgt = $('#yts-target-lang', root);
    var mode = $('#yts-mode', root);
    var project = $('#yts-project', root);
    if (src) state.sourceLanguage = src.value;
    if (tgt) state.targetLanguage = tgt.value;
    if (mode) state.mode = mode.value;
    if (project) state.projectId = project.value || '';
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
      text: state.sourceText,
      source_language: state.sourceLanguage,
      target_language: state.targetLanguage,
      mode: state.mode,
      context: '',
      provider: 'auto',
      project_id: state.projectId || ''
    };

    Core.translator.translate(body, state.abort ? state.abort.signal : null).then(function (res) {
      var data = (res && (res.data || res)) || {};
      state.translatedText = data.translated_text || '';
      state.detectedLanguage = data.detected_language || '';
      state.fallbackUsed = !!data.fallback_used;
      state.lastModel = data.model || '';
      state.galleryItemId = data.gallery_item_id || '';
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
      if (data.saved) toastMsg = '번역 완료 · My Works에 저장됨';
      if (data.credits && data.credits.deducted > 0) {
        toastMsg += ' · -' + data.credits.deducted + ' credits';
      } else if (data.credits && data.credits.skipped) {
        toastMsg += ' · Credits 미차감';
      }
      showToast(root, toastMsg);
      loadCreditsLabel().then(function () { updateMeta(root); });
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
    var ta = $('#yts-source', root);
    if (ta) ta.value = '';
    setResult(root, '', false);
    showStatus(root, '', '');
    updateMeta(root);
  }

  function bindEvents(root) {
    if (root.dataset.ytsBound === '1') return;
    root.dataset.ytsBound = '1';

    root.addEventListener('click', function (e) {
      if (e.target.closest('#yts-translate')) { doTranslate(root); return; }
      if (e.target.closest('#yts-swap')) { doSwap(root); return; }
      if (e.target.closest('#yts-copy')) { doCopy(root); return; }
      if (e.target.closest('#yts-clear')) { doClear(root); return; }
      if (e.target.closest('#yts-history-refresh')) { loadHistory(root); return; }
      var hist = e.target.closest('[data-history-id]');
      if (hist) { reopenHistory(root, hist.getAttribute('data-history-id')); return; }
    });

    root.addEventListener('input', function (e) {
      if (e.target && e.target.id === 'yts-source') {
        state.sourceText = e.target.value;
        updateMeta(root);
        showStatus(root, '', '');
      }
    });

    root.addEventListener('change', function (e) {
      var t = e.target;
      if (!t) return;
      if (t.id === 'yts-source-lang') state.sourceLanguage = t.value;
      if (t.id === 'yts-target-lang') state.targetLanguage = t.value;
      if (t.id === 'yts-mode') state.mode = t.value;
      if (t.id === 'yts-project') state.projectId = t.value || '';
    });

    root.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        doTranslate(root);
      }
    });
  }

  function loadCreditsLabel() {
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
      loadProjects(),
      loadHistory(root)
    ]).then(function (res) {
      var cfg = (res[0] && (res[0].data || res[0])) || {};
      var langs = (res[1] && res[1].data && res[1].data.languages) || cfg.languages || [];
      state.languages = langs;
      state.modes = cfg.modes || [];
      state.providers = cfg.providers || [];
      if (cfg.max_chars) MAX_CHARS = parseInt(cfg.max_chars, 10) || MAX_CHARS;
      state.openaiReady = !!cfg.openai_ready;
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
      updateMeta(root);
      consumeRegeneratePayload(root);
    }).catch(function (e) {
      showStatus(root, 'error', (e && e.message) || 'Translator 설정을 불러오지 못했습니다.');
    });
  }

  global.YooYTranslatorStudio = {
    mount: mount
  };
})(window);
