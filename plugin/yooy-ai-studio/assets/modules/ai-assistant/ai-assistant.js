/**
 * YooY AI Assistant — Universal Creator Command Center (Phase 9)
 * Orchestration / navigation only — prepare Studios, never auto-Generate / auto-spend Credits.
 */
(function (global) {
  'use strict';

  var Core = global.YooYCore;
  var mounted = false;
  var rootEl = null;
  var state = {
    context: null,
    cards: [],
    messages: [],
    actions: [],
    quick: [],
    draft: null,
    brief: null,
    phase: 'welcome',
    busy: false,
    typing: false,
    statusText: '',
    selectedAsset: null,
    lastAsset: null,
    attachment: null,
    lastCommandAction: null
  };

  var ICON_MAP = {
    megaphone: '📣',
    clapper: '🎬',
    phone: '📱',
    doc: '📝',
    headphones: '🎧',
    translate: '文A',
    folder: '📁'
  };

  var ACTION_LABELS = {
    image: '이미지 만들기',
    video: '영상 만들기',
    writing: '글쓰기',
    music: '음악 만들기',
    voice: '나레이션',
    avatar: '아바타',
    translator: '번역하기'
  };

  var CTX_KEY = 'yoy_assistant_ui_context';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function formatBody(text) {
    var raw = String(text || '');
    var lines = raw.split(/\n/);
    var html = [];
    var inList = false;
    lines.forEach(function (line) {
      var t = line.trim();
      if (/^[-•*]\s+/.test(t)) {
        if (!inList) {
          html.push('<ul>');
          inList = true;
        }
        html.push('<li>' + esc(t.replace(/^[-•*]\s+/, '')) + '</li>');
        return;
      }
      if (inList) {
        html.push('</ul>');
        inList = false;
      }
      if (t === '') return;
      html.push('<p>' + esc(line) + '</p>');
    });
    if (inList) html.push('</ul>');
    return html.join('') || '<p></p>';
  }

  function timeLabel(ts) {
    try {
      var d = ts ? new Date(ts) : new Date();
      return d.toLocaleTimeString('ko-KR', { hour: 'numeric', minute: '2-digit' });
    } catch (e) {
      return '';
    }
  }

  function isLoggedIn() {
    return !!(global.YooYStudio && global.YooYStudio.loggedIn);
  }

  function requireLogin(pendingKey) {
    try {
      if (pendingKey) sessionStorage.setItem('yoy_pending_after_auth', pendingKey);
    } catch (e) { /* ignore */ }
    if (typeof global.YooYRequireLogin === 'function') {
      return global.YooYRequireLogin();
    }
    if (isLoggedIn()) return true;
    var modal = document.getElementById('yai-login-modal');
    if (modal) modal.hidden = false;
    toast('로그인이 필요합니다.');
    return false;
  }

  function activeProjectId() {
    if (global.YooYActiveProject && typeof global.YooYActiveProject.getId === 'function') {
      return global.YooYActiveProject.getId() || '';
    }
    return '';
  }

  function activeProjectName() {
    var p = global.YooYActiveProject && global.YooYActiveProject.get && global.YooYActiveProject.get();
    return p && (p.name || p.title) ? (p.name || p.title) : '';
  }

  function routeTo(name) {
    if (typeof global.YooYStudioRoute === 'function') {
      global.YooYStudioRoute(name);
      return;
    }
    var btn = document.querySelector('.yai-nav-item[data-route="' + name + '"]');
    if (btn) btn.click();
  }

  function track(eventName, payload) {
    try {
      if (Core && typeof Core.track === 'function') {
        Core.track(eventName, payload || {});
      } else if (Core && typeof Core.debugLog === 'function') {
        Core.debugLog(eventName, payload || {});
      }
    } catch (e) { /* ignore */ }
  }

  function persistContext() {
    try {
      sessionStorage.setItem(CTX_KEY, JSON.stringify({
        selectedAsset: state.selectedAsset,
        lastAsset: state.lastAsset,
        attachment: state.attachment
      }));
    } catch (e) { /* ignore */ }
  }

  function loadPersistedContext() {
    try {
      var raw = sessionStorage.getItem(CTX_KEY);
      if (!raw) return;
      var data = JSON.parse(raw);
      if (data.selectedAsset) state.selectedAsset = data.selectedAsset;
      if (data.lastAsset) state.lastAsset = data.lastAsset;
      if (data.attachment) state.attachment = data.attachment;
    } catch (e) { /* ignore */ }
  }

  function setSelectedAsset(asset, opts) {
    opts = opts || {};
    if (!asset) {
      state.selectedAsset = null;
    } else {
      state.selectedAsset = {
        gallery_id: String(asset.gallery_id || asset.id || ''),
        type: String(asset.type || 'image'),
        title: String(asset.title || ''),
        thumbnail: asset.thumbnail || asset.url || asset.preview || '',
        url: asset.url || asset.thumbnail || asset.preview || '',
        studio: String(asset.studio || ''),
        public_safe: !!asset.public_safe
      };
      if (!opts.skipLast) state.lastAsset = state.selectedAsset;
    }
    persistContext();
    renderContextChips();
  }

  function clearContextChip(kind) {
    if (kind === 'asset') state.selectedAsset = null;
    if (kind === 'attachment') state.attachment = null;
    persistContext();
    renderContextChips();
  }

  function clientContextPayload() {
    return {
      selected_asset: state.selectedAsset || undefined,
      last_asset: state.lastAsset || undefined
    };
  }

  function buildHandoffPrompt(action) {
    if (action && action.prompt) return String(action.prompt);
    if (state.draft && state.draft.draft && state.draft.requires_approval === false) {
      return state.draft.draft;
    }
    if (state.brief) {
      return [state.brief.goal, state.brief.audience, state.brief.tone, state.brief.format]
        .filter(Boolean).join(' · ');
    }
    for (var i = state.messages.length - 1; i >= 0; i--) {
      if (state.messages[i].role === 'user') return state.messages[i].text;
    }
    return '';
  }

  function handoffToStudio(studio, action) {
    var route = studio || (action && action.studio) || (state.brief && state.brief.primary_studio) || 'image';
    var prompt = buildHandoffPrompt(action);
    var draft = state.draft || {};
    var creativeBrief = draft.creative_brief || null;
    var intentDomain = draft.intent_domain || (creativeBrief && creativeBrief.content_domain) || '';
    var rawRequest = draft.raw_user_request || draft.seed || (action && action.prompt) || '';
    if (!rawRequest && state.brief && state.brief.goal) rawRequest = state.brief.goal;
    if (!creativeBrief && state.brief) {
      creativeBrief = {
        primary_subject: state.brief.goal || prompt,
        core_message: state.brief.goal || '',
        audience: state.brief.audience || '',
        tone: state.brief.tone || '',
        medium: state.brief.format || '',
        content_domain: intentDomain || 'general',
        raw_user_request: rawRequest || prompt
      };
    }
    var ref = (action && action.reference_asset) || state.selectedAsset || state.lastAsset;
    try {
      if (prompt) {
        global.sessionStorage.setItem('yoy_home_prompt', prompt);
        global.sessionStorage.setItem('yoy_home_studio', route);
      }
      if (rawRequest) {
        global.sessionStorage.setItem('yoy_assistant_raw_request', rawRequest);
      }
      if (creativeBrief) {
        global.sessionStorage.setItem('yoy_assistant_creative_brief', JSON.stringify(creativeBrief));
      }
      if (intentDomain) {
        global.sessionStorage.setItem('yoy_assistant_intent_domain', intentDomain);
      }
      global.sessionStorage.setItem('yoy_assistant_prompt_version', draft.prompt_version || 'spi-assistant-1');
      global.sessionStorage.setItem('yoy_assistant_auto_generate', '0');
      var pid = activeProjectId();
      if (pid) {
        global.sessionStorage.setItem('yoy_assistant_project_id', pid);
      }
      if (ref && (ref.url || ref.thumbnail || ref.gallery_id)) {
        global.sessionStorage.setItem('yoy_reference_asset', JSON.stringify({
          url: ref.url || ref.thumbnail || '',
          title: ref.title || '',
          gallery_id: ref.gallery_id || '',
          type: ref.type || '',
          source: 'assistant',
          public_safe: !!ref.public_safe
        }));
        global.sessionStorage.setItem('yoy_home_attachment', JSON.stringify({
          url: ref.url || ref.thumbnail || '',
          preview: ref.thumbnail || ref.url || '',
          name: ref.title || '참고 작품',
          title: ref.title || '',
          gallery_id: ref.gallery_id || '',
          type: 'image',
          source: 'assistant'
        }));
      }
      if (state.attachment && !ref) {
        global.sessionStorage.setItem('yoy_home_attachment', JSON.stringify(state.attachment));
      }
      if (global.YooYNavigation) {
        global.YooYNavigation.rememberSource({
          previous_route: 'assistant',
          source_context: 'assistant',
          active_project_id: pid || ''
        });
        global.YooYNavigation.push({
          route: 'assistant',
          source_context: 'assistant',
          active_project_id: pid || ''
        });
      }
    } catch (e) { /* ignore */ }
    track('assistant_route', { studio: route, auto_generate: false });
    routeTo(route);
  }

  function toast(msg) {
    if (!rootEl) return;
    var old = rootEl.querySelector('.yai-assistant-toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'yai-assistant-toast';
    el.setAttribute('role', 'status');
    el.textContent = msg;
    rootEl.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.remove(); }, 2600);
  }

  function setBusy(on, statusText) {
    state.busy = !!on;
    state.typing = !!on;
    state.statusText = on ? (statusText || '요청 이해 중...') : '';
    if (!rootEl) return;
    var send = rootEl.querySelector('[data-assistant-send]');
    var input = rootEl.querySelector('[data-assistant-input]');
    if (send) send.disabled = state.busy;
    if (input) input.disabled = state.busy;
    renderMessages();
  }

  function scrollChat() {
    var sc = rootEl && rootEl.querySelector('[data-assistant-scroll]');
    if (sc) sc.scrollTop = sc.scrollHeight;
  }

  function estimateLine(action) {
    // Phase 8 estimates only — never invent. Card may omit until Studio shows estimate.
    return '';
  }

  function actionCardHtml(action) {
    if (!action || !action.type) return '';
    var title = action.label || '작업 준비';
    var ref = action.reference_asset;
    var thumb = ref && (ref.thumbnail || ref.url)
      ? '<div class="yai-assistant-action-card__thumb"><img src="' + esc(ref.thumbnail || ref.url) + '" alt=""></div>'
      : '';
    var prompt = action.prompt
      ? '<div class="yai-assistant-action-card__row"><span>요청</span><strong>' + esc(action.prompt) + '</strong></div>'
      : '';
    var est = estimateLine(action);
    var risk = action.risk || 'low';
    var primary = action.type === 'prepare_creation'
      ? '<button type="button" class="yai-assistant-action-btn yai-assistant-action-btn--primary" data-cmd-exec="' + esc(action.type) + '">' +
        esc(action.label || 'Studio에서 계속') + '</button>'
      : '<button type="button" class="yai-assistant-action-btn yai-assistant-action-btn--primary" data-cmd-exec="' + esc(action.type) + '">' +
        esc(action.label || '실행') + '</button>';
    var cancel = (risk === 'high' || risk === 'medium')
      ? '<button type="button" class="yai-assistant-action-btn" data-cmd-cancel>취소</button>'
      : '';
    var options = '';
    if (action.type === 'clarify_asset' && Array.isArray(action.options) && action.options.length) {
      options = '<div class="yai-assistant-action-card__options">' +
        action.options.map(function (o) {
          return '<button type="button" class="yai-assistant-chip" data-pick-asset="' + esc(o.id) + '" data-pick-label="' + esc(o.label || '') + '" data-pick-type="' + esc(o.type || '') + '">' +
            esc(o.label || '작품') + '</button>';
        }).join('') + '</div>';
    }
    return (
      '<div class="yai-assistant-action-card" data-risk="' + esc(risk) + '" role="group" aria-label="' + esc(title) + '">' +
        '<div class="yai-assistant-action-card__title">' + esc(title) + '</div>' +
        thumb +
        prompt +
        (est ? '<div class="yai-assistant-action-card__row"><span>예상</span><strong>' + esc(est) + '</strong></div>' : '') +
        (action.target ? '<div class="yai-assistant-action-card__row"><span>대상</span><strong>' + esc(action.target) + '</strong></div>' : '') +
        options +
        '<div class="yai-assistant-message__actions">' + primary + cancel + '</div>' +
      '</div>'
    );
  }

  function messageActionsHtml(msg) {
    if (!msg || msg.role !== 'assistant') return '';
    var html = '';
    if (msg.commandAction) {
      html += actionCardHtml(msg.commandAction);
    }
    if (msg.actions && msg.actions.length && !(msg.commandAction && msg.commandAction.type === 'prepare_creation')) {
      html += '<div class="yai-assistant-message__actions">' +
        msg.actions.map(function (a) {
          var route = a.route || a.id;
          var label = a.label || ACTION_LABELS[route] || route;
          return '<button type="button" class="yai-assistant-action-btn" data-studio-route="' +
            esc(route) + '">' + esc(label) + '</button>';
        }).join('') +
      '</div>';
    }
    return html;
  }

  function shellHtml() {
    var project = activeProjectName();
    return (
      '<div id="yai-assistant" class="yai-assistant" data-yai-assistant>' +
        '<header class="yai-assistant__header">' +
          '<div>' +
            '<div class="yai-assistant__title-row">' +
              '<span class="yai-assistant__spark" aria-hidden="true">✦</span>' +
              '<h1 class="yai-assistant__title">AI Assistant</h1>' +
            '</div>' +
            '<p class="yai-assistant__subtitle">만들고 · 찾고 · 이어가는 크리에이터 커맨드 센터</p>' +
          '</div>' +
          '<div class="yai-assistant__meta" data-assistant-meta>' +
            '<span class="yai-assistant__badge yai-assistant__badge--ready">System Ready</span>' +
            (project ? '<span class="yai-assistant__badge">Project · ' + esc(project) + '</span>' : '<span class="yai-assistant__badge">General Mode</span>') +
            '<button type="button" class="yai-assistant__badge" data-assistant-new-chat aria-label="새 대화">새 대화</button>' +
          '</div>' +
        '</header>' +

        '<div class="yai-assistant__scroll" data-assistant-scroll>' +
          '<section class="yai-assistant__recs" aria-labelledby="yai-as-recs-title">' +
            '<div class="yai-assistant__recs-head">' +
              '<div>' +
                '<h2 class="yai-assistant__recs-title" id="yai-as-recs-title">추천으로 시작하기</h2>' +
                '<p class="yai-assistant__recs-desc">인기 주제와 템플릿으로 빠르게 시작해 보세요.</p>' +
              '</div>' +
              '<button type="button" class="yai-assistant__recs-more" data-assistant-new-chat>새 대화 ›</button>' +
            '</div>' +
            '<div class="yai-assistant__card-track" role="list" data-assistant-cards aria-label="추천 목적"></div>' +
          '</section>' +

          '<div class="yai-assistant-chat" role="log" aria-live="polite" aria-relevant="additions" data-assistant-messages></div>' +
          '<div class="yai-assistant-quick" data-assistant-quick hidden></div>' +
          '<div class="yai-assistant-draft" data-assistant-draft hidden></div>' +
        '</div>' +

        '<div class="yai-assistant-composer-wrap">' +
          '<div class="yai-assistant-context-chips" data-assistant-context-chips hidden></div>' +
          '<form class="yai-assistant-composer" data-assistant-form>' +
            '<div class="yai-assistant-composer__inner">' +
              '<label class="yai-assistant-sr-only" for="yai-assistant-input">메시지 입력</label>' +
              '<textarea id="yai-assistant-input" class="yai-assistant-composer__input" data-assistant-input rows="2" ' +
                'placeholder="무엇을 만들고 싶으신가요? 예: 이 사진으로 10초 광고 영상"></textarea>' +
              '<div class="yai-assistant-composer__toolbar">' +
                '<div class="yai-assistant-composer__tools">' +
                  '<button type="button" class="yai-assistant-tool yai-assistant-tool--plus" data-tool="plus" aria-label="첨부">+</button>' +
                  '<button type="button" class="yai-assistant-tool" data-tool="file" aria-label="파일 첨부"><span aria-hidden="true">📎</span><span class="yai-assistant-tool--label"> 파일</span></button>' +
                  '<button type="button" class="yai-assistant-tool" data-tool="image" aria-label="이미지"><span aria-hidden="true">🖼</span><span class="yai-assistant-tool--label"> 이미지</span></button>' +
                  '<button type="button" class="yai-assistant-tool" data-tool="website" aria-label="웹사이트"><span aria-hidden="true">🌐</span><span class="yai-assistant-tool--label"> URL</span></button>' +
                '</div>' +
                '<div class="yai-assistant-composer__send-group">' +
                  '<button type="button" class="yai-assistant-tool" data-assistant-compose aria-label="Prompt 보조">Prompt</button>' +
                  '<button type="submit" class="yai-assistant-send" data-assistant-send>전송 ✈</button>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</form>' +
        '</div>' +
      '</div>'
    );
  }

  function renderCards() {
    var el = rootEl && rootEl.querySelector('[data-assistant-cards]');
    if (!el) return;
    var cards = state.cards.slice(0, 6);
    if (!cards.length) {
      el.innerHTML = '<p style="color:#9aa3b2;font-size:14px">추천을 불러오는 중…</p>';
      return;
    }
    el.innerHTML = cards.map(function (c) {
      var icon = ICON_MAP[c.icon] || '✨';
      return (
        '<button type="button" class="yai-assistant-recommendation-card" role="listitem" ' +
          'data-tone="' + esc(c.tone || 'purple') + '" data-card-id="' + esc(c.id) + '" ' +
          'aria-label="' + esc((c.title || '') + ' 시작하기') + '">' +
          '<span class="yai-assistant-recommendation-card__icon" aria-hidden="true">' + icon + '</span>' +
          '<strong class="yai-assistant-recommendation-card__title">' + esc(c.title || '') + '</strong>' +
          '<span class="yai-assistant-recommendation-card__desc">' + esc(c.description || '') + '</span>' +
          '<span class="yai-assistant-recommendation-card__cta">' + esc(c.cta || '시작하기') + ' →</span>' +
        '</button>'
      );
    }).join('');
  }

  function renderContextChips() {
    var el = rootEl && rootEl.querySelector('[data-assistant-context-chips]');
    if (!el) return;
    var chips = [];
    var pname = activeProjectName();
    if (pname) {
      chips.push('<span class="yai-assistant-ctx-chip">프로젝트 · ' + esc(pname) + '</span>');
    }
    if (state.selectedAsset) {
      chips.push(
        '<span class="yai-assistant-ctx-chip">' +
          '선택 · ' + esc(state.selectedAsset.title || state.selectedAsset.type || '작품') +
          '<button type="button" data-clear-ctx="asset" aria-label="선택 작품 제거">×</button></span>'
      );
    }
    if (state.attachment) {
      chips.push(
        '<span class="yai-assistant-ctx-chip">' +
          '첨부 · ' + esc(state.attachment.name || state.attachment.title || '파일') +
          '<button type="button" data-clear-ctx="attachment" aria-label="첨부 제거">×</button></span>'
      );
    }
    if (!chips.length) {
      el.hidden = true;
      el.innerHTML = '';
      return;
    }
    el.hidden = false;
    el.innerHTML = chips.join('');
  }

  function renderMessages() {
    var el = rootEl && rootEl.querySelector('[data-assistant-messages]');
    if (!el) return;

    var html = '';
    if (!state.messages.length && !state.typing) {
      html =
        '<div class="yai-assistant-message yai-assistant-message--assistant">' +
          '<div class="yai-assistant-message__avatar" aria-hidden="true">🤖</div>' +
          '<div class="yai-assistant-message__stack">' +
            '<div class="yai-assistant-message__bubble">' +
              '<p>무엇을 만들고 싶으신가요?</p>' +
              '<p>원하는 작업을 말씀해 주시면 Studio·Gallery·Projects로 이어서 준비해 드릴게요. Generate는 직접 확인한 뒤 실행됩니다.</p>' +
            '</div>' +
            '<div class="yai-assistant-message__meta">' + esc(timeLabel()) + '</div>' +
          '</div>' +
        '</div>';
    } else {
      html = state.messages.map(function (m) {
        var isUser = m.role === 'user';
        var cls = isUser ? 'yai-assistant-message--user' : 'yai-assistant-message--assistant';
        return (
          '<div class="yai-assistant-message ' + cls + '">' +
            (isUser ? '' : '<div class="yai-assistant-message__avatar" aria-hidden="true">🤖</div>') +
            '<div class="yai-assistant-message__stack">' +
              '<div class="yai-assistant-message__bubble">' + formatBody(m.text) + '</div>' +
              messageActionsHtml(m) +
              '<div class="yai-assistant-message__meta">' +
                esc(timeLabel(m.ts)) +
                (isUser ? ' · ✓' : '') +
              '</div>' +
            '</div>' +
          '</div>'
        );
      }).join('');
    }

    if (state.typing) {
      html +=
        '<div class="yai-assistant-message yai-assistant-message--assistant" aria-label="' + esc(state.statusText || 'AI가 입력 중') + '">' +
          '<div class="yai-assistant-message__avatar" aria-hidden="true">🤖</div>' +
          '<div class="yai-assistant-message__stack">' +
            '<div class="yai-assistant-message__bubble">' +
              '<span class="yai-assistant-typing" aria-hidden="true"><span></span><span></span><span></span></span>' +
              (state.statusText ? '<span class="yai-assistant-status-text">' + esc(state.statusText) + '</span>' : '') +
            '</div>' +
          '</div>' +
        '</div>';
    }

    el.innerHTML = html;
    scrollChat();
  }

  function renderQuick() {
    var el = rootEl && rootEl.querySelector('[data-assistant-quick]');
    if (!el) return;
    if (!state.quick.length) {
      el.hidden = true;
      el.innerHTML = '';
      return;
    }
    el.hidden = false;
    el.innerHTML = state.quick.map(function (q) {
      return '<button type="button" class="yai-assistant-chip" data-quick="' + esc(q) + '">' + esc(q) + '</button>';
    }).join('');
  }

  function renderDraft() {
    var el = rootEl && rootEl.querySelector('[data-assistant-draft]');
    if (!el) return;
    if (!state.draft || !state.draft.draft) {
      el.hidden = true;
      el.innerHTML = '';
      return;
    }
    el.hidden = false;
    el.innerHTML =
      '<strong>Prompt Composer · 보조</strong>' +
      '<p class="yai-assistant-draft__text">' + esc(state.draft.draft) + '</p>' +
      '<div class="yai-assistant-message__actions">' +
        '<button type="button" class="yai-assistant-action-btn" data-approve-prompt>승인하고 Studio로</button>' +
        '<button type="button" class="yai-assistant-action-btn" data-dismiss-draft>닫기</button>' +
      '</div>';
  }

  function paint() {
    renderCards();
    renderMessages();
    renderQuick();
    renderDraft();
    renderContextChips();
  }

  function applyChatPayload(data) {
    if (!data) return;
    if (data.reply) {
      var actions = [];
      if ((data.phase === 'plan' || data.phase === 'ready' || data.phase === 'action') && Array.isArray(data.studio_actions)) {
        actions = data.studio_actions;
      }
      state.messages.push({
        role: 'assistant',
        text: data.reply,
        ts: Date.now(),
        actions: actions,
        commandAction: data.command_action || null
      });
    }
    if (data.phase) state.phase = data.phase;
    if (data.brief) state.brief = data.brief;
    if (Array.isArray(data.studio_actions)) state.actions = data.studio_actions;
    if (Array.isArray(data.quick_replies)) state.quick = data.quick_replies;
    if (data.composed && data.composed.draft) state.draft = data.composed;
    else if (data.phase !== 'ready') state.draft = null;
    if (data.context) state.context = Object.assign({}, state.context || {}, data.context);
    if (data.command_action) {
      state.lastCommandAction = data.command_action;
      track('assistant_command', { type: data.command_action.type || '' });
    }
  }

  function statusForMessage(msg) {
    var m = String(msg || '');
    if (/크레딧|플랜/.test(m)) return '크레딧 확인 중...';
    if (/최근|찾아|갤러리|작품/.test(m)) return '최근 작품 찾는 중...';
    if (/공개|삭제|마켓/.test(m)) return '작업 준비 중...';
    if (/만들|영상|이미지|음악|번역|나레이션/.test(m)) return '작업 준비 중...';
    return '요청 이해 중...';
  }

  function sendMessage(text) {
    var msg = String(text || '').trim();
    if (!msg || state.busy) return;

    if (!isLoggedIn()) {
      try {
        sessionStorage.setItem('yoy_assistant_pending_message', msg);
        sessionStorage.setItem('yoy_pending_after_auth', 'assistant');
      } catch (e) { /* ignore */ }
      requireLogin('assistant');
      state.messages.push({ role: 'user', text: msg, ts: Date.now() });
      state.messages.push({
        role: 'assistant',
        text: '로그인 후 이어서 도와드릴게요. 요청은 잠시 보관해 두었습니다.',
        ts: Date.now(),
        actions: []
      });
      paint();
      return;
    }

    if (!Core || !Core.assistant) {
      toast('AI Assistant API를 사용할 수 없습니다.');
      return;
    }

    state.messages.push({ role: 'user', text: msg, ts: Date.now() });
    state.quick = [];
    paint();
    setBusy(true, statusForMessage(msg));

    var body = {
      message: msg,
      project_id: activeProjectId() || undefined,
      brief: state.brief || undefined,
      history: state.messages.slice(-12).map(function (m) {
        return { role: m.role, content: m.text };
      }),
      selected_asset: state.selectedAsset || undefined,
      last_asset: state.lastAsset || undefined
    };

    Core.assistant.chat(body).then(function (res) {
      applyChatPayload(res && res.data);
      paint();
    }).catch(function (err) {
      var code = err && (err.status || err.code);
      if (code === 401 || code === 403) {
        try {
          sessionStorage.setItem('yoy_assistant_pending_message', msg);
          sessionStorage.setItem('yoy_pending_after_auth', 'assistant');
        } catch (e2) { /* ignore */ }
        requireLogin('assistant');
      }
      state.messages.push({
        role: 'assistant',
        text: (err && err.message) ? err.message : '응답에 실패했습니다. 다시 시도해 주세요.',
        ts: Date.now(),
        actions: []
      });
      paint();
    }).finally(function () {
      setBusy(false);
    });
  }

  function showCreditsInfo() {
    var acc = global.YooYCreditsUI && typeof global.YooYCreditsUI.getAccount === 'function'
      ? global.YooYCreditsUI.getAccount()
      : null;
    if (acc && (acc.balance != null || acc.credits != null)) {
      var bal = acc.balance != null ? acc.balance : acc.credits;
      var plan = acc.plan_label || acc.plan_name || acc.plan || '';
      state.messages.push({
        role: 'assistant',
        text: '현재 크레딧은 ' + bal + '입니다.' + (plan ? (' 플랜: ' + plan) : ''),
        ts: Date.now(),
        actions: []
      });
      paint();
      return;
    }
    if (Core && Core.credits && typeof Core.credits.balance === 'function') {
      Core.credits.balance().then(function (res) {
        var d = (res && res.data) || {};
        var bal = d.balance != null ? d.balance : (d.credits != null ? d.credits : null);
        state.messages.push({
          role: 'assistant',
          text: bal != null ? ('현재 크레딧은 ' + bal + '입니다.') : '크레딧 정보를 불러오지 못했습니다.',
          ts: Date.now(),
          actions: []
        });
        paint();
      }).catch(function () {
        toast('크레딧을 확인할 수 없습니다.');
      });
      return;
    }
    routeTo('credits');
  }

  function showPlanInfo() {
    var acc = global.YooYCreditsUI && typeof global.YooYCreditsUI.getAccount === 'function'
      ? global.YooYCreditsUI.getAccount()
      : null;
    if (acc && (acc.plan_label || acc.plan_name || acc.plan)) {
      state.messages.push({
        role: 'assistant',
        text: '현재 플랜은 ' + (acc.plan_label || acc.plan_name || acc.plan) + '입니다.',
        ts: Date.now(),
        actions: []
      });
      paint();
      return;
    }
    routeTo('credits');
  }

  function executeCommand(action) {
    if (!action || !action.type) return;
    track('assistant_action_prepare', { type: action.type, risk: action.risk || 'low' });

    switch (action.type) {
      case 'prepare_creation':
        handoffToStudio(action.studio, action);
        break;
      case 'show_gallery':
        track('assistant_search', { query: action.query || '', type: action.filter_type || '' });
        try {
          if (action.query) sessionStorage.setItem('yoy_assistant_gallery_query', action.query);
          if (action.filter_type) sessionStorage.setItem('yoy_assistant_gallery_type', action.filter_type);
        } catch (e) { /* ignore */ }
        routeTo('works');
        break;
      case 'show_credits':
        showCreditsInfo();
        break;
      case 'show_plan':
        showPlanInfo();
        break;
      case 'open_project':
        if (action.project_id) {
          try { sessionStorage.setItem('yoy_open_project_id', action.project_id); } catch (e2) { /* ignore */ }
        }
        routeTo('projects');
        break;
      case 'open_projects':
        routeTo('projects');
        break;
      case 'open_templates':
        routeTo('home');
        try {
          var tpl = document.querySelector('[data-home-templates], #yai-home-templates, [data-route="templates"]');
          if (tpl && tpl.scrollIntoView) tpl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e3) { /* ignore */ }
        toast('Home에서 템플릿을 골라 이어갈 수 있어요.');
        break;
      case 'prepare_publish':
        track('assistant_publish_prepare', { target: action.target || 'community' });
        preparePublish(action);
        break;
      case 'confirm_delete':
        confirmDelete(action);
        break;
      case 'clarify_asset':
        try {
          if (!action.query) sessionStorage.setItem('yoy_assistant_gallery_type', '');
        } catch (e4) { /* ignore */ }
        routeTo('works');
        break;
      default:
        toast('지원하지 않는 작업입니다.');
    }
  }

  function resolveAssetId(action) {
    var ref = (action && action.reference_asset) || state.selectedAsset || state.lastAsset;
    return ref && (ref.gallery_id || ref.id) ? String(ref.gallery_id || ref.id) : '';
  }

  function preparePublish(action) {
    var id = resolveAssetId(action);
    if (!id) {
      state.messages.push({
        role: 'assistant',
        text: '공개할 작품을 찾지 못했습니다. Gallery에서 작품을 선택한 뒤 다시 시도해 주세요.',
        ts: Date.now(),
        actions: []
      });
      paint();
      return;
    }
    if (!requireLogin('assistant')) return;
    routeTo('works');
    setTimeout(function () {
      if (global.YooYGallery && typeof global.YooYGallery.openPublish === 'function') {
        global.YooYGallery.openPublish(id);
        toast('공개 미리보기를 열었습니다. 확인 후 공개해 주세요.');
      } else {
        toast('Gallery에서 공개하기를 진행해 주세요.');
      }
    }, 350);
  }

  function confirmDelete(action) {
    var id = resolveAssetId(action);
    if (!id) {
      state.messages.push({
        role: 'assistant',
        text: '삭제할 작품을 찾지 못했습니다. 어떤 작품인지 알려 주세요.',
        ts: Date.now(),
        commandAction: {
          type: 'clarify_asset',
          risk: 'low',
          label: '최근 작품 보기',
          options: ((state.context && state.context.recent_assets) || []).slice(0, 4).map(function (a) {
            return { id: a.gallery_id || a.id, label: a.title || a.type || '작품', type: a.type || '' };
          })
        }
      });
      paint();
      return;
    }
    if (!requireLogin('assistant')) return;
    var run = function () {
      if (!Core || !Core.gallery || typeof Core.gallery.remove !== 'function') {
        toast('삭제 API를 사용할 수 없습니다.');
        return;
      }
      Core.gallery.remove(id).then(function () {
        if (state.selectedAsset && String(state.selectedAsset.gallery_id) === id) setSelectedAsset(null);
        state.messages.push({
          role: 'assistant',
          text: '작품을 삭제했습니다.',
          ts: Date.now(),
          actions: []
        });
        paint();
      }).catch(function () {
        toast('삭제에 실패했습니다.');
      });
    };
    if (global.YooYConfirm && global.YooYConfirm.dialog) {
      global.YooYConfirm.dialog({
        title: '작품을 삭제할까요?',
        body: '삭제는 되돌리기 어려울 수 있습니다. 정말 진행할까요?',
        buttons: [
          { id: 'cancel', label: '취소', variant: 'ghost' },
          { id: 'confirm', label: '삭제', variant: 'gold' }
        ]
      }).then(function (res) {
        if (res === 'confirm') run();
      });
      return;
    }
    if (global.confirm('정말 이 작품을 삭제할까요?')) run();
  }

  function startFromCard(card) {
    if (!card) return;
    var seed = card.seed || card.title || '';
    sendMessage(seed);
  }

  function composeSecondary() {
    var seed = buildHandoffPrompt();
    if (!seed) {
      toast('먼저 아이디어를 이야기해 주세요. Prompt는 보조 기능입니다.');
      return;
    }
    if (!Core || !Core.assistant) return;
    setBusy(true, '작업 준비 중...');
    Core.assistant.compose({
      prompt: seed,
      studio: (state.brief && state.brief.primary_studio) || undefined,
      project_id: activeProjectId() || undefined
    }).then(function (res) {
      var data = res && res.data;
      if (data && data.composed) {
        state.draft = {
          seed: data.seed || seed,
          draft: data.composed,
          fields: data.fields || {},
          studio: data.studio || 'image',
          requires_approval: true,
          creative_brief: data.creative_brief || null,
          intent_domain: data.intent_domain || '',
          raw_user_request: data.raw_user_request || data.seed || seed,
          prompt_version: data.prompt_version || 'spi-assistant-1'
        };
        state.messages.push({
          role: 'assistant',
          text: 'Prompt Composer(보조)로 초안을 준비했습니다. 승인 후에만 Studio로 전달됩니다.',
          ts: Date.now(),
          actions: []
        });
      }
      paint();
    }).catch(function () {
      toast('Prompt 보조 생성에 실패했습니다.');
    }).finally(function () {
      setBusy(false);
    });
  }

  function resetInputOnly() {
    state.draft = null;
    state.quick = [];
    state.actions = state.actions || [];
    paint();
    var input = rootEl && rootEl.querySelector('[data-assistant-input]');
    if (input) {
      input.value = '';
      input.focus();
    }
    toast('입력·추천 선택을 초기화했습니다.');
  }

  function resetChat() {
    state.messages = [];
    state.actions = [];
    state.quick = [];
    state.draft = null;
    state.brief = null;
    state.phase = 'welcome';
    state.lastCommandAction = null;
    paint();
    var input = rootEl && rootEl.querySelector('[data-assistant-input]');
    if (input) {
      input.value = '';
      input.focus();
    }
  }

  function assistantIsDirty() {
    var input = rootEl && rootEl.querySelector('[data-assistant-input]');
    var typed = input && String(input.value || '').trim();
    return !!(typed || (state.messages && state.messages.length) || state.draft || state.brief);
  }

  function newChat() {
    if (!assistantIsDirty()) {
      resetChat();
      toast('새 대화를 시작했습니다.');
      return;
    }
    if (global.YooYConfirm && global.YooYConfirm.dialog) {
      global.YooYConfirm.dialog({
        title: '새 대화를 시작할까요?',
        body: '현재 대화 화면만 새 세션으로 바뀝니다. Active Project는 유지되며, 서버 History는 삭제하지 않습니다.',
        buttons: [
          { id: 'cancel', label: '취소', variant: 'ghost' },
          { id: 'confirm', label: '새 대화', variant: 'gold' }
        ]
      }).then(function (action) {
        if (action === 'confirm') {
          resetChat();
          toast('새 대화를 시작했습니다.');
        }
      });
      return;
    }
    resetChat();
  }

  function ensureFileInput(id, accept, multiple, onChange) {
    var input = document.getElementById(id);
    if (!input) {
      input = document.createElement('input');
      input.type = 'file';
      input.id = id;
      input.hidden = true;
      input.accept = accept || '';
      input.multiple = !!multiple;
      document.body.appendChild(input);
    } else {
      input.accept = accept || input.accept || '';
      input.multiple = !!multiple;
    }
    input.onchange = onChange;
    return input;
  }

  function setAttachmentFromHome(att) {
    state.attachment = att || null;
    persistContext();
    renderContextChips();
  }

  function uploadViaImport(files, kind) {
    if (!Core || !Core.importEngine || typeof Core.importEngine.uploadFiles !== 'function') {
      toast('첨부 기능을 사용할 수 없습니다.');
      return;
    }
    if (!requireLogin('assistant')) return;
    setBusy(true, '작업 준비 중...');
    Core.importEngine.uploadFiles(files, {
      source: 'upload',
      origin: 'Assistant',
      type_hint: kind === 'image' ? 'image' : ''
    }).then(function (res) {
      var data = (res && (res.data || res)) || {};
      var results = data.results || [];
      var item = results[0] || data.item || data;
      setAttachmentFromHome({
        type: kind || 'file',
        url: item.url || item.preview || (item.asset && item.asset.url) || '',
        preview: item.preview || item.url || (item.asset && item.asset.preview) || '',
        name: item.name || item.title || (files[0] && files[0].name) || '첨부',
        title: item.title || item.name || '',
        gallery_id: item.gallery_id || item.id || '',
        source: 'assistant'
      });
      toast('첨부를 추가했습니다.');
    }).catch(function () {
      toast('첨부 업로드에 실패했습니다.');
    }).finally(function () { setBusy(false); });
  }

  function pickAssistantAttachment(kind) {
    if (kind === 'website') {
      var url = global.prompt('가져올 웹페이지 URL을 입력하세요');
      if (!url) return;
      if (Core && Core.importEngine && typeof Core.importEngine.process === 'function') {
        setBusy(true, '작업 준비 중...');
        Core.importEngine.process({ source: 'website', url: url, origin: 'Assistant' }).then(function (res) {
          var d = (res && res.data) || {};
          var row = (d.results && d.results[0]) || d;
          setAttachmentFromHome({
            type: 'url',
            url: url,
            title: (row && (row.title || row.name)) || url,
            name: (row && (row.title || row.name)) || url,
            preview: (row && (row.preview || row.url)) || '',
            source: 'assistant'
          });
          toast('URL을 첨부했습니다.');
        }).catch(function () {
          toast('URL을 가져오지 못했습니다.');
        }).finally(function () { setBusy(false); });
        return;
      }
      toast('URL 가져오기를 사용할 수 없습니다.');
      return;
    }
    if (kind === 'image') {
      var img = ensureFileInput('yai-as-file-image', 'image/*', false, function () {
        var files = img.files;
        img.value = '';
        if (!files || !files.length) return;
        uploadViaImport(files, 'image');
      });
      img.click();
      return;
    }
    if (kind === 'file' || kind === 'plus') {
      var doc = ensureFileInput('yai-as-file-doc', '.txt,.pdf,.doc,.docx,image/*', false, function () {
        var files = doc.files;
        doc.value = '';
        if (!files || !files.length) return;
        uploadViaImport(files, 'file');
      });
      doc.click();
    }
  }

  function bind() {
    if (!rootEl || rootEl.dataset.bound === '1') return;
    rootEl.dataset.bound = '1';

    rootEl.addEventListener('submit', function (e) {
      var form = e.target.closest('[data-assistant-form]');
      if (!form) return;
      e.preventDefault();
      var input = rootEl.querySelector('[data-assistant-input]');
      var val = input ? input.value : '';
      if (input) input.value = '';
      sendMessage(val);
    });

    rootEl.addEventListener('keydown', function (e) {
      var ta = e.target.closest('[data-assistant-input]');
      if (!ta) return;
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        var form = rootEl.querySelector('[data-assistant-form]');
        if (form && form.requestSubmit) form.requestSubmit();
        else if (form) form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      }
    });

    rootEl.addEventListener('click', function (e) {
      var clear = e.target.closest('[data-clear-ctx]');
      if (clear) {
        clearContextChip(clear.getAttribute('data-clear-ctx'));
        return;
      }

      var pick = e.target.closest('[data-pick-asset]');
      if (pick) {
        setSelectedAsset({
          gallery_id: pick.getAttribute('data-pick-asset'),
          title: pick.getAttribute('data-pick-label') || '',
          type: pick.getAttribute('data-pick-type') || 'image'
        });
        toast('작품을 선택했습니다. 원하는 작업을 이어서 말씀해 주세요.');
        return;
      }

      var card = e.target.closest('[data-card-id]');
      if (card) {
        var found = state.cards.find(function (c) { return c.id === card.getAttribute('data-card-id'); });
        startFromCard(found);
        return;
      }

      var chip = e.target.closest('[data-quick]');
      if (chip) {
        sendMessage(chip.getAttribute('data-quick') || '');
        return;
      }

      var cmd = e.target.closest('[data-cmd-exec]');
      if (cmd) {
        var type = cmd.getAttribute('data-cmd-exec');
        var action = state.lastCommandAction;
        if (!action || action.type !== type) {
          for (var i = state.messages.length - 1; i >= 0; i--) {
            if (state.messages[i].commandAction && state.messages[i].commandAction.type === type) {
              action = state.messages[i].commandAction;
              break;
            }
          }
        }
        if (action) executeCommand(action);
        return;
      }

      if (e.target.closest('[data-cmd-cancel]')) {
        toast('취소했습니다.');
        return;
      }

      var actionBtn = e.target.closest('[data-studio-route]');
      if (actionBtn) {
        handoffToStudio(actionBtn.getAttribute('data-studio-route'));
        return;
      }

      if (e.target.closest('[data-assistant-compose]')) {
        composeSecondary();
        return;
      }

      if (e.target.closest('[data-approve-prompt]')) {
        if (!state.draft || !state.draft.draft) return;
        state.draft.requires_approval = false;
        handoffToStudio(state.draft.studio || 'image');
        return;
      }

      if (e.target.closest('[data-dismiss-draft]')) {
        state.draft = null;
        renderDraft();
        return;
      }

      if (e.target.closest('[data-assistant-new-chat]')) {
        newChat();
        return;
      }
      if (e.target.closest('[data-assistant-back]')) {
        if (global.YooYNavigation) global.YooYNavigation.goBack('assistant');
        return;
      }
      if (e.target.closest('[data-assistant-reset]')) {
        resetInputOnly();
        return;
      }

      var tool = e.target.closest('[data-tool]');
      if (tool) {
        pickAssistantAttachment(tool.getAttribute('data-tool'));
      }
    });
  }

  function resumePendingMessage() {
    try {
      var pending = sessionStorage.getItem('yoy_assistant_pending_message');
      if (pending && isLoggedIn()) {
        sessionStorage.removeItem('yoy_assistant_pending_message');
        setTimeout(function () { sendMessage(pending); }, 200);
      }
    } catch (e) { /* ignore */ }
  }

  function onCreationSuccess(ev) {
    var detail = (ev && ev.detail) || {};
    var asset = {
      gallery_id: detail.gallery_id || detail.id || detail.work_id || '',
      type: detail.type || detail.studio || 'image',
      title: detail.title || '',
      thumbnail: detail.thumbnail || detail.url || detail.preview || '',
      url: detail.url || detail.thumbnail || '',
      studio: detail.studio || detail.type || ''
    };
    if (!asset.gallery_id && !asset.url) return;
    setSelectedAsset(asset);
  }

  function loadBootstrap() {
    loadPersistedContext();
    if (!Core || !Core.assistant) {
      paint();
      return;
    }
    var pid = activeProjectId();
    Promise.all([
      Core.assistant.recommendations({ project_id: pid || undefined }).catch(function () { return null; }),
      (isLoggedIn() && pid)
        ? Core.assistant.context({ project_id: pid }).catch(function () { return null; })
        : Promise.resolve(null)
    ]).then(function (results) {
      var rec = results[0] && results[0].data;
      var ctx = results[1] && results[1].data;
      if (rec) {
        state.cards = (rec.cards || []).filter(function (c) {
          return c.id !== 'purpose_webtoon';
        });
        if (rec.context) state.context = Object.assign({}, state.context || {}, rec.context);
      }
      if (ctx) state.context = ctx;
      if (!state.context) {
        state.context = {
          mode: pid ? 'project' : 'general',
          project: pid ? { id: pid, title: activeProjectName() } : null
        };
      }
      paint();
      var input = rootEl && rootEl.querySelector('[data-assistant-input]');
      if (input) {
        try { input.focus(); } catch (e) { /* ignore */ }
      }
      resumePendingMessage();
    });
  }

  function mount(el) {
    if (!el) return;
    if (!mounted || el.dataset.mounted !== '1') {
      el.innerHTML = shellHtml();
      rootEl = el.querySelector('#yai-assistant') || el;
      el.dataset.mounted = '1';
      mounted = true;
      state.messages = [];
      state.brief = null;
      state.phase = 'welcome';
      state.draft = null;
      state.quick = [];
      bind();
      if (!document.body.dataset.yoyAsCreationBound) {
        document.body.dataset.yoyAsCreationBound = '1';
        document.addEventListener('yoy:creation-success', onCreationSuccess);
      }
    } else {
      rootEl = el.querySelector('#yai-assistant') || el;
    }
    loadBootstrap();
    paint();
  }

  global.YooYAIAssistant = {
    mount: mount,
    refresh: loadBootstrap,
    reset: function () { resetChat(); },
    newChat: newChat,
    isDirty: assistantIsDirty,
    setSelectedAsset: setSelectedAsset,
    getSelectedAsset: function () { return state.selectedAsset; }
  };

  if (global.YooYStudioState && typeof global.YooYStudioState.register === 'function') {
    global.YooYStudioState.register('assistant', {
      isDirty: assistantIsDirty,
      dirtyFlags: function () {
        return { dirty: assistantIsDirty(), pendingBrief: !!state.draft };
      },
      reset: function () { resetChat(); },
      goBack: function () { return false; },
      canGoBack: function () { return false; },
      saveDraft: function () { return Promise.resolve(false); }
    });
  }
})(typeof window !== 'undefined' ? window : this);
