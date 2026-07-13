/**
 * YooY AI Assistant — Conversational Creative Partner UI
 * Recs → Chat → Fixed Composer → Inline Studio Actions
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
    typing: false
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

  function activeProjectId() {
    if (global.YooYActiveProject && typeof global.YooYActiveProject.getId === 'function') {
      return global.YooYActiveProject.getId() || '';
    }
    return '';
  }

  function activeProjectName() {
    var p = global.YooYActiveProject && global.YooYActiveProject.get && global.YooYActiveProject.get();
    return p && p.name ? p.name : '';
  }

  function routeTo(name) {
    if (typeof global.YooYStudioRoute === 'function') {
      global.YooYStudioRoute(name);
      return;
    }
    var btn = document.querySelector('.yai-nav-item[data-route="' + name + '"]');
    if (btn) btn.click();
  }

  function buildHandoffPrompt() {
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

  function handoffToStudio(studio) {
    var route = studio || (state.brief && state.brief.primary_studio) || 'image';
    var prompt = buildHandoffPrompt();
    var draft = state.draft || {};
    var creativeBrief = draft.creative_brief || null;
    var intentDomain = draft.intent_domain || (creativeBrief && creativeBrief.content_domain) || '';
    var rawRequest = draft.raw_user_request || draft.seed || '';
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
      var pid = activeProjectId();
      if (pid) {
        global.sessionStorage.setItem('yoy_assistant_project_id', pid);
      }
    } catch (e) { /* ignore */ }
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

  function setBusy(on) {
    state.busy = !!on;
    state.typing = !!on;
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
            '<p class="yai-assistant__subtitle">당신의 아이디어를 함께 실현하는 AI 파트너</p>' +
          '</div>' +
          '<div class="yai-assistant__meta" data-assistant-meta>' +
            '<span class="yai-assistant__badge yai-assistant__badge--ready">System Ready</span>' +
            (project ? '<span class="yai-assistant__badge">Project · ' + esc(project) + '</span>' : '<span class="yai-assistant__badge">General Mode</span>') +
            '<button type="button" class="yai-assistant__badge" data-assistant-reset aria-label="대화 초기화">초기화</button>' +
          '</div>' +
        '</header>' +

        '<div class="yai-assistant__scroll" data-assistant-scroll>' +
          '<section class="yai-assistant__recs" aria-labelledby="yai-as-recs-title">' +
            '<div class="yai-assistant__recs-head">' +
              '<div>' +
                '<h2 class="yai-assistant__recs-title" id="yai-as-recs-title">추천으로 시작하기</h2>' +
                '<p class="yai-assistant__recs-desc">인기 주제와 템플릿으로 빠르게 시작해 보세요.</p>' +
              '</div>' +
              '<button type="button" class="yai-assistant__recs-more" data-assistant-reset>전체 보기 ›</button>' +
            '</div>' +
            '<div class="yai-assistant__card-track" role="list" data-assistant-cards aria-label="추천 목적"></div>' +
          '</section>' +

          '<div class="yai-assistant-chat" role="log" aria-live="polite" aria-relevant="additions" data-assistant-messages></div>' +
          '<div class="yai-assistant-quick" data-assistant-quick hidden></div>' +
          '<div class="yai-assistant-draft" data-assistant-draft hidden></div>' +
        '</div>' +

        '<div class="yai-assistant-composer-wrap">' +
          '<form class="yai-assistant-composer" data-assistant-form>' +
            '<div class="yai-assistant-composer__inner">' +
              '<label class="yai-assistant-sr-only" for="yai-assistant-input">메시지 입력</label>' +
              '<textarea id="yai-assistant-input" class="yai-assistant-composer__input" data-assistant-input rows="2" ' +
                'placeholder="무엇이든 물어보세요. 아이디어를 설명하면 작품으로 만들어 드릴게요!"></textarea>' +
              '<div class="yai-assistant-composer__toolbar">' +
                '<div class="yai-assistant-composer__tools">' +
                  '<button type="button" class="yai-assistant-tool yai-assistant-tool--plus" data-tool="plus" aria-label="더보기">+</button>' +
                  '<button type="button" class="yai-assistant-tool" data-tool="file" aria-label="파일 첨부"><span aria-hidden="true">📎</span><span class="yai-assistant-tool--label"> 파일 첨부</span></button>' +
                  '<button type="button" class="yai-assistant-tool" data-tool="image" aria-label="이미지"><span aria-hidden="true">🖼</span><span class="yai-assistant-tool--label"> 이미지</span></button>' +
                  '<button type="button" class="yai-assistant-tool" data-tool="website" aria-label="웹사이트"><span aria-hidden="true">🌐</span><span class="yai-assistant-tool--label"> 웹사이트</span></button>' +
                  '<button type="button" class="yai-assistant-tool" data-tool="voice" aria-label="음성 입력"><span aria-hidden="true">🎙</span><span class="yai-assistant-tool--label"> 음성 입력</span></button>' +
                '</div>' +
                '<div class="yai-assistant-composer__send-group">' +
                  '<button type="button" class="yai-assistant-mic" data-tool="mic" aria-label="마이크">🎤</button>' +
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

  function messageActionsHtml(msg) {
    if (!msg || msg.role !== 'assistant' || !msg.actions || !msg.actions.length) return '';
    return '<div class="yai-assistant-message__actions">' +
      msg.actions.map(function (a) {
        var route = a.route || a.id;
        var label = a.label || ACTION_LABELS[route] || route;
        return '<button type="button" class="yai-assistant-action-btn" data-studio-route="' +
          esc(route) + '">' + esc(label) + '</button>';
      }).join('') +
      '<button type="button" class="yai-assistant-action-btn" data-studio-route="image">더 많은 Studio</button>' +
    '</div>';
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
              '<p>안녕하세요! 무엇을 만들고 싶으신가요?</p>' +
              '<p>위에서 목적을 고르거나, 아래 입력창에 아이디어를 적어 주세요. 제가 먼저 질문하며 함께 기획할게요.</p>' +
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
        '<div class="yai-assistant-message yai-assistant-message--assistant" aria-label="AI가 입력 중">' +
          '<div class="yai-assistant-message__avatar" aria-hidden="true">🤖</div>' +
          '<div class="yai-assistant-message__stack">' +
            '<div class="yai-assistant-message__bubble">' +
              '<span class="yai-assistant-typing"><span></span><span></span><span></span></span>' +
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
  }

  function applyChatPayload(data) {
    if (!data) return;
    if (data.reply) {
      var actions = [];
      if ((data.phase === 'plan' || data.phase === 'ready') && Array.isArray(data.studio_actions)) {
        actions = data.studio_actions;
      }
      state.messages.push({
        role: 'assistant',
        text: data.reply,
        ts: Date.now(),
        actions: actions
      });
    }
    if (data.phase) state.phase = data.phase;
    if (data.brief) state.brief = data.brief;
    if (Array.isArray(data.studio_actions)) state.actions = data.studio_actions;
    if (Array.isArray(data.quick_replies)) state.quick = data.quick_replies;
    if (data.composed && data.composed.draft) state.draft = data.composed;
    else if (data.phase !== 'ready') state.draft = null;
    if (data.context) state.context = Object.assign({}, state.context || {}, data.context);
  }

  function sendMessage(text) {
    var msg = String(text || '').trim();
    if (!msg || state.busy) return;
    if (!Core || !Core.assistant) {
      toast('AI Assistant API를 사용할 수 없습니다.');
      return;
    }

    state.messages.push({ role: 'user', text: msg, ts: Date.now() });
    state.quick = [];
    paint();
    setBusy(true);

    Core.assistant.chat({
      message: msg,
      project_id: activeProjectId() || undefined,
      brief: state.brief || undefined,
      history: state.messages.slice(-12).map(function (m) {
        return { role: m.role, content: m.text };
      })
    }).then(function (res) {
      applyChatPayload(res && res.data);
      paint();
    }).catch(function (err) {
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
    setBusy(true);
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

  function resetChat() {
    state.messages = [];
    state.actions = [];
    state.quick = [];
    state.draft = null;
    state.brief = null;
    state.phase = 'welcome';
    paint();
    var input = rootEl && rootEl.querySelector('[data-assistant-input]');
    if (input) input.focus();
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

      var action = e.target.closest('[data-studio-route]');
      if (action) {
        handoffToStudio(action.getAttribute('data-studio-route'));
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

      if (e.target.closest('[data-assistant-reset]')) {
        resetChat();
        return;
      }

      var tool = e.target.closest('[data-tool]');
      if (tool) {
        var kind = tool.getAttribute('data-tool');
        if (kind === 'file' || kind === 'image' || kind === 'website' || kind === 'voice' || kind === 'mic' || kind === 'plus') {
          toast('Input Adapter — Coming Soon (가짜 업로드 없음)');
        }
      }
    });
  }

  function loadBootstrap() {
    if (!Core || !Core.assistant) {
      paint();
      return;
    }
    var pid = activeProjectId();
    Promise.all([
      Core.assistant.recommendations({ project_id: pid || undefined }).catch(function () { return null; }),
      (global.YooYStudio && global.YooYStudio.loggedIn && pid)
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
    } else {
      rootEl = el.querySelector('#yai-assistant') || el;
    }
    loadBootstrap();
    paint();
  }

  global.YooYAIAssistant = {
    mount: mount,
    refresh: loadBootstrap
  };
})(typeof window !== 'undefined' ? window : this);
