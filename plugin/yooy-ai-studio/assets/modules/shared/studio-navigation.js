/**
 * YooY Studio Navigation + Reset UX
 * Reuses Shell route / sessionStorage — no new Store.
 */
(function (global) {
  'use strict';

  var STACK_KEY = 'yoy_nav_stack';
  var CTX_KEY = 'yoy_nav_context';
  var MAX_STACK = 40;
  var handlers = Object.create(null);
  var currentRoute = 'home';
  var stepStacks = Object.create(null);
  var leaveGuardBusy = false;

  /** Generative studios + Assistant — may show a right-side chrome action. */
  var STUDIO_ROUTES = {
    assistant: true,
    image: true,
    video: true,
    writing: true,
    music: true,
    voice: true,
    avatar: true,
    translator: true
  };

  /** Pages with editable/generative state that show 「초기화」 (not Assistant). */
  var RESET_LABEL_ROUTES = {
    image: true,
    video: true,
    writing: true,
    music: true,
    voice: true,
    avatar: true,
    translator: true
  };

  var ROUTE_FALLBACK = {
    image: 'home',
    video: 'home',
    writing: 'home',
    music: 'home',
    voice: 'home',
    avatar: 'home',
    translator: 'home',
    assistant: 'home',
    'project-detail': 'projects',
    works: 'home',
    history: 'works',
    credits: 'home',
    settings: 'home',
    billing: 'credits'
  };

  function readJson(key, fallback) {
    try {
      var raw = global.sessionStorage.getItem(key);
      if (!raw) return fallback;
      return JSON.parse(raw);
    } catch (e) {
      return fallback;
    }
  }

  function writeJson(key, value) {
    try {
      global.sessionStorage.setItem(key, JSON.stringify(value));
    } catch (e) { /* ignore quota */ }
  }

  function activeProjectId() {
    try {
      if (global.YooYActiveProject && typeof global.YooYActiveProject.getId === 'function') {
        return global.YooYActiveProject.getId() || '';
      }
    } catch (e) { /* ignore */ }
    return '';
  }

  function toast(msg) {
    var host = document.getElementById('yai-main') || document.body;
    var old = host.querySelector('.yai-nav-toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'yai-nav-toast';
    el.setAttribute('role', 'status');
    el.textContent = msg;
    host.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.remove(); }, 2800);
  }

  /* ── Confirm dialog ── */
  function ensureDialogRoot() {
    var root = document.getElementById('yai-nav-dialog');
    if (root) return root;
    root = document.createElement('div');
    root.id = 'yai-nav-dialog';
    root.className = 'yai-nav-dialog';
    root.hidden = true;
    root.innerHTML =
      '<div class="yai-nav-dialog__backdrop" data-yai-nav-dismiss="1"></div>' +
      '<div class="yai-nav-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="yai-nav-dialog-title" tabindex="-1">' +
        '<h2 id="yai-nav-dialog-title" class="yai-nav-dialog__title"></h2>' +
        '<p class="yai-nav-dialog__body"></p>' +
        '<div class="yai-nav-dialog__actions"></div>' +
      '</div>';
    document.body.appendChild(root);
    return root;
  }

  function confirmDialog(opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var root = ensureDialogRoot();
      var panel = root.querySelector('.yai-nav-dialog__panel');
      var titleEl = root.querySelector('.yai-nav-dialog__title');
      var bodyEl = root.querySelector('.yai-nav-dialog__body');
      var actionsEl = root.querySelector('.yai-nav-dialog__actions');
      var prevFocus = document.activeElement;
      titleEl.textContent = opts.title || '확인';
      bodyEl.textContent = opts.body || '';
      actionsEl.innerHTML = '';
      var buttons = opts.buttons || [
        { id: 'cancel', label: '취소', variant: 'ghost' },
        { id: 'confirm', label: '확인', variant: 'warn' }
      ];
      buttons.forEach(function (b) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'yai-nav-dialog__btn yai-nav-dialog__btn--' + (b.variant || 'ghost');
        btn.textContent = b.label;
        btn.setAttribute('data-yai-nav-action', b.id);
        actionsEl.appendChild(btn);
      });

      function close(result) {
        root.hidden = true;
        root.classList.remove('is-open');
        root.removeEventListener('keydown', onKey);
        root.removeEventListener('click', onClick);
        if (prevFocus && prevFocus.focus) {
          try { prevFocus.focus(); } catch (e) { /* ignore */ }
        }
        resolve(result);
      }
      function onKey(e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          close('cancel');
          return;
        }
        if (e.key === 'Tab' && panel) {
          var focusables = panel.querySelectorAll('button:not([disabled])');
          if (!focusables.length) return;
          var first = focusables[0];
          var last = focusables[focusables.length - 1];
          if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
          } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        }
      }
      function onClick(e) {
        if (e.target.closest('[data-yai-nav-dismiss]')) {
          close('cancel');
          return;
        }
        var act = e.target.closest('[data-yai-nav-action]');
        if (act) close(act.getAttribute('data-yai-nav-action'));
      }

      root.hidden = false;
      root.classList.add('is-open');
      root.addEventListener('keydown', onKey);
      root.addEventListener('click', onClick);
      var primary = actionsEl.querySelector('[data-yai-nav-action="confirm"], [data-yai-nav-action="reset"], [data-yai-nav-action="discard"]')
        || actionsEl.querySelector('button');
      if (panel) panel.focus();
      if (primary) setTimeout(function () { primary.focus(); }, 0);
    });
  }

  /* ── Navigation stack ── */
  function getStack() {
    var stack = readJson(STACK_KEY, []);
    return Array.isArray(stack) ? stack : [];
  }

  function setStack(stack) {
    writeJson(STACK_KEY, stack.slice(-MAX_STACK));
  }

  function getContext() {
    return readJson(CTX_KEY, {}) || {};
  }

  function setContext(ctx) {
    writeJson(CTX_KEY, ctx || {});
  }

  function pushRoute(entry) {
    if (!entry || !entry.route) return;
    var stack = getStack();
    var last = stack[stack.length - 1];
    if (last && last.route === entry.route && (last.source_context || '') === (entry.source_context || '')) {
      return;
    }
    stack.push({
      route: entry.route,
      active_project_id: entry.active_project_id || activeProjectId(),
      source_context: entry.source_context || '',
      source_asset_id: entry.source_asset_id || '',
      tab: entry.tab || '',
      ts: Date.now()
    });
    setStack(stack);
  }

  function peekBack() {
    var stack = getStack();
    return stack.length ? stack[stack.length - 1] : null;
  }

  function popBack() {
    var stack = getStack();
    if (!stack.length) return null;
    var item = stack.pop();
    setStack(stack);
    return item;
  }

  function rememberSource(ctx) {
    var next = Object.assign({}, getContext(), ctx || {}, {
      active_project_id: (ctx && ctx.active_project_id) || activeProjectId(),
      ts: Date.now()
    });
    setContext(next);
    return next;
  }

  function setCurrent(route) {
    currentRoute = route || 'home';
    syncChrome();
  }

  function getCurrent() {
    return currentRoute;
  }

  function canGoBack(studioId) {
    var id = studioId || currentRoute;
    var h = handlers[id];
    if (h && typeof h.canGoBack === 'function' && h.canGoBack()) return true;
    if ((stepStacks[id] || []).length) return true;
    if (peekBack()) return true;
    var ctx = getContext();
    if (ctx.previous_route || ctx.source_context) return true;
    return !!ROUTE_FALLBACK[id];
  }

  function resolveBackTarget(studioId) {
    var id = studioId || currentRoute;
    var h = handlers[id];
    if (h && typeof h.goBack === 'function') {
      var handled = h.goBack();
      if (handled === true) return { handled: true };
    }
    var steps = stepStacks[id] || [];
    if (steps.length) {
      var prevStep = steps.pop();
      stepStacks[id] = steps;
      if (h && typeof h.setStep === 'function') {
        h.setStep(prevStep);
        return { handled: true, step: prevStep };
      }
    }
    var popped = popBack();
    if (popped && popped.route) {
      return { handled: false, route: popped.route, meta: popped };
    }
    var ctx = getContext();
    if (ctx.source_context === 'project' || ctx.source_context === 'project-workspace') {
      return { handled: false, route: 'project-detail', meta: ctx };
    }
    if (ctx.source_context === 'assistant' || ctx.previous_route === 'assistant') {
      return { handled: false, route: 'assistant', meta: ctx };
    }
    if (ctx.previous_route && ctx.previous_route !== id) {
      return { handled: false, route: ctx.previous_route, meta: ctx };
    }
    return { handled: false, route: ROUTE_FALLBACK[id] || 'home', meta: ctx };
  }

  function goBack(studioId) {
    var id = studioId || currentRoute;
    var target = resolveBackTarget(id);
    if (target.handled) {
      syncChrome();
      return true;
    }
    var routeName = target.route || 'home';
    navigate(routeName, { replace: true, fromBack: true, meta: target.meta });
    return true;
  }

  function navigate(routeName, opts) {
    opts = opts || {};
    if (typeof global.YooYStudioRoute === 'function') {
      global.YooYStudioRoute(routeName, Object.assign({}, opts, { viaNav: true }));
      return;
    }
    var btn = document.querySelector('.yai-nav-item[data-route="' + routeName + '"]');
    if (btn) btn.click();
  }

  function pushStep(studioId, step) {
    if (!studioId || !step) return;
    stepStacks[studioId] = stepStacks[studioId] || [];
    var stack = stepStacks[studioId];
    if (stack[stack.length - 1] === step) return;
    stack.push(step);
  }

  /* ── Studio registry ── */
  function register(studioId, api) {
    if (!studioId || !api) return;
    handlers[studioId] = Object.assign({}, handlers[studioId] || {}, api);
    syncChrome();
  }

  function getHandler(studioId) {
    return handlers[studioId] || null;
  }

  function isDirty(studioId) {
    var id = studioId || currentRoute;
    var h = handlers[id];
    if (h && typeof h.isDirty === 'function') {
      try { return !!h.isDirty(); } catch (e) { return false; }
    }
    return false;
  }

  function canReset(studioId) {
    var id = studioId || currentRoute;
    if (!STUDIO_ROUTES[id]) return false;
    var h = handlers[id];
    if (h && typeof h.canReset === 'function') return !!h.canReset();
    return true;
  }

  function hasRemixOrAssetContext() {
    var ctx = getContext();
    if (ctx.source_asset_id) return true;
    if (ctx.source_context === 'gallery' || ctx.source_context === 'works' || ctx.source_context === 'remix') {
      return true;
    }
    var peek = peekBack();
    if (peek && (peek.route === 'works' || peek.source_context === 'remix' || peek.source_asset_id)) {
      return true;
    }
    try {
      if (global.sessionStorage.getItem('yoy_home_remix') && peek &&
        (peek.route === 'home' || peek.route === 'works' || peek.route === 'community' || peek.route === 'market')) {
        return true;
      }
    } catch (e) { /* ignore */ }
    return false;
  }

  function hasProjectWorkflowContext() {
    var ctx = getContext();
    if (ctx.source_context === 'project' || ctx.source_context === 'project-workspace') return true;
    if (ctx.previous_route === 'project-detail') return true;
    var peek = peekBack();
    if (peek && peek.route === 'project-detail') return true;
    if (peek && peek.source_context === 'project-workspace') return true;
    return false;
  }

  /**
   * Semantic back only — never generic history labels like 「← Templates」.
   * @return {{label:string,aria:string,route:string}|null}
   */
  function resolveSemanticBack(route) {
    var id = route || currentRoute;
    if (id === 'home') return null;

    var h = handlers[id];
    if (h && typeof h.canGoBack === 'function' && h.canGoBack()) {
      return { label: '이전', aria: '이전 단계로', route: '' };
    }
    if ((stepStacks[id] || []).length) {
      return { label: '이전', aria: '이전 단계로', route: '' };
    }

    if (id === 'project-detail') {
      return { label: '프로젝트로', aria: '프로젝트 목록으로', route: 'projects' };
    }
    if (id === 'history') {
      return { label: 'Gallery로', aria: 'Gallery로', route: 'works' };
    }

    var isStudio = !!STUDIO_ROUTES[id];
    if (!isStudio) return null;

    if (hasProjectWorkflowContext()) {
      return { label: '프로젝트로', aria: '프로젝트로', route: 'project-detail' };
    }
    if (hasRemixOrAssetContext()) {
      var ctx = getContext();
      if (ctx.source_asset_id || (function () {
        try { return !!global.sessionStorage.getItem('yoy_home_remix'); } catch (e) { return false; }
      })()) {
        return { label: '작품으로', aria: '작품으로', route: 'works' };
      }
      return { label: 'Gallery로', aria: 'Gallery로', route: 'works' };
    }

    return null;
  }

  function dirtyFlags(studioId) {
    var id = studioId || currentRoute;
    var h = handlers[id];
    if (h && typeof h.dirtyFlags === 'function') {
      try { return h.dirtyFlags() || {}; } catch (e) { return {}; }
    }
    return { dirty: isDirty(id) };
  }

  function remountStudio(page, globalName) {
    var el = document.getElementById('yai-' + page + '-studio');
    if (!el) return false;
    el.dataset.mounted = '0';
    el.innerHTML = '';
    if (global[globalName] && typeof global[globalName].mount === 'function') {
      global[globalName].mount(el);
      return true;
    }
    return false;
  }

  function runReset(studioId, opts) {
    opts = opts || {};
    var id = studioId || currentRoute;
    var h = handlers[id];
    try {
      if (h && typeof h.abort === 'function') h.abort();
    } catch (e) { /* continue */ }
    try {
      if (h && typeof h.reset === 'function') {
        h.reset(opts);
      } else if (id === 'writing' && typeof global.YooYWritingReset === 'function') {
        global.YooYWritingReset();
      } else {
        var map = {
          image: 'YooYImageStudio',
          video: 'YooYVideoStudio',
          music: 'YooYMusicStudio',
          voice: 'YooYVoiceStudio',
          avatar: 'YooYAvatarStudio',
          translator: 'YooYTranslatorStudio',
          assistant: 'YooYAIAssistant'
        };
        if (map[id] && global[map[id]] && typeof global[map[id]].reset === 'function') {
          global[map[id]].reset(opts);
        } else if (map[id]) {
          remountStudio(id === 'assistant' ? 'assistant' : id, map[id]);
        }
      }
      stepStacks[id] = [];
      if (!opts.silent) {
        toast(opts.toastMessage || (id === 'assistant' ? '새 대화를 시작했습니다.' : '작업을 초기화했습니다.'));
      } else if (id === 'assistant') {
        toast(opts.toastMessage || '새 대화를 시작했습니다.');
      }
      syncChrome();
      return true;
    } catch (err) {
      try {
        remountStudio(id, {
          image: 'YooYImageStudio',
          video: 'YooYVideoStudio',
          music: 'YooYMusicStudio',
          voice: 'YooYVoiceStudio',
          avatar: 'YooYAvatarStudio',
          translator: 'YooYTranslatorStudio',
          assistant: 'YooYAIAssistant'
        }[id]);
      } catch (e2) { /* ignore */ }
      toast(id === 'assistant' ? '대화를 새로 시작했습니다.' : '초기화 중 문제가 있어 화면을 새로 그렸습니다.');
      syncChrome();
      return false;
    }
  }

  function resetWithConfirm(studioId) {
    var id = studioId || currentRoute;

    if (id === 'assistant') {
      var aDirty = isDirty(id);
      if (!aDirty) {
        runReset(id, { silent: true, toastMessage: '새 대화를 시작했습니다.' });
        return Promise.resolve('reset');
      }
      return confirmDialog({
        title: '새 대화를 시작할까요?',
        body: '현재 대화 화면만 새 세션으로 바뀝니다. Active Project와 선택 작품 맥락은 유지됩니다.',
        buttons: [
          { id: 'cancel', label: '취소', variant: 'ghost' },
          { id: 'reset', label: '새 대화', variant: 'gold' }
        ]
      }).then(function (action) {
        if (action === 'cancel') return 'cancel';
        runReset(id, { toastMessage: '새 대화를 시작했습니다.' });
        return 'reset';
      });
    }

    var flags = dirtyFlags(id);
    var dirty = !!flags.dirty || isDirty(id);
    if (!dirty) {
      runReset(id, { silent: true });
      return Promise.resolve('reset');
    }

    var unsaved = !!flags.unsavedResult || !!flags.editing || !!flags.pendingBrief;
    var buttons = unsaved
      ? [
          { id: 'cancel', label: '취소', variant: 'ghost' },
          { id: 'reset', label: '초기화', variant: 'warn' },
          { id: 'save-reset', label: '저장 후 초기화', variant: 'gold' }
        ]
      : [
          { id: 'cancel', label: '취소', variant: 'ghost' },
          { id: 'reset', label: '초기화', variant: 'warn' }
        ];

    return confirmDialog({
      title: '현재 작업 내용을 초기화할까요?',
      body: '입력한 프롬프트, 첨부자료와 미저장 결과가 삭제됩니다. Gallery 또는 Project에 저장된 작품은 삭제되지 않습니다.',
      buttons: buttons
    }).then(function (action) {
      if (action === 'cancel') return 'cancel';
      if (action === 'save-reset') {
        var h = handlers[id];
        if (h && typeof h.saveDraft === 'function') {
          return Promise.resolve(h.saveDraft()).then(function (ok) {
            if (ok === false) {
              toast('저장에 실패했습니다. 초기화를 취소했습니다.');
              return 'cancel';
            }
            runReset(id, { afterSave: true });
            return 'save-reset';
          }).catch(function () {
            toast('저장에 실패했습니다. 초기화를 취소했습니다.');
            return 'cancel';
          });
        }
        toast('이 Studio에서는 바로 저장을 지원하지 않습니다. 저장 후 다시 초기화해 주세요.');
        return 'cancel';
      }
      runReset(id);
      return 'reset';
    });
  }

  function guardLeave(fromRoute, toRoute) {
    if (leaveGuardBusy) return Promise.resolve(true);
    if (!fromRoute || fromRoute === toRoute) return Promise.resolve(true);
    if (!isDirty(fromRoute)) return Promise.resolve(true);
    leaveGuardBusy = true;
    return confirmDialog({
      title: '이 화면을 나갈까요?',
      body: '저장하지 않은 작업 내용이 있을 수 있습니다. Gallery/Project에 이미 저장된 작품은 유지됩니다.',
      buttons: [
        { id: 'cancel', label: '취소', variant: 'ghost' },
        { id: 'leave', label: '나가기', variant: 'warn' }
      ]
    }).then(function (action) {
      leaveGuardBusy = false;
      return action === 'leave';
    }).catch(function () {
      leaveGuardBusy = false;
      return false;
    });
  }

  function hideBackButton(backBtn) {
    if (!backBtn) return;
    backBtn.hidden = true;
    backBtn.setAttribute('hidden', 'hidden');
    backBtn.disabled = true;
    backBtn.setAttribute('aria-disabled', 'true');
    backBtn.style.display = 'none';
    backBtn.removeAttribute('data-semantic-route');
  }

  function syncChrome() {
    var backBtn = document.getElementById('yai-nav-back');
    var resetBtn = document.getElementById('yai-nav-reset');
    var showReset = canReset(currentRoute);
    var semantic = resolveSemanticBack(currentRoute);
    try {
      document.body.classList.toggle('yai-route-home', currentRoute === 'home');
      document.body.classList.toggle('yai-nav-has-back', !!semantic);
      document.body.classList.toggle('yai-nav-has-reset', !!showReset);
    } catch (eBody) { /* ignore */ }

    if (backBtn) {
      if (!semantic) {
        hideBackButton(backBtn);
      } else {
        var labelEl = backBtn.querySelector('.yai-nav-chrome-label');
        if (labelEl) labelEl.textContent = semantic.label;
        backBtn.setAttribute('aria-label', semantic.aria || semantic.label);
        backBtn.title = semantic.aria || semantic.label;
        if (semantic.route) backBtn.setAttribute('data-semantic-route', semantic.route);
        else backBtn.removeAttribute('data-semantic-route');
        backBtn.hidden = false;
        backBtn.removeAttribute('hidden');
        backBtn.style.display = '';
        backBtn.disabled = false;
        backBtn.setAttribute('aria-disabled', 'false');
      }
    }

    if (resetBtn) {
      var resetLabel = currentRoute === 'assistant' ? '새 대화' : '초기화';
      var resetLabelEl = resetBtn.querySelector('.yai-nav-chrome-label');
      if (resetLabelEl) resetLabelEl.textContent = resetLabel;
      resetBtn.setAttribute('aria-label', resetLabel);
      resetBtn.title = currentRoute === 'assistant'
        ? '현재 대화를 새 세션으로'
        : '현재 작업 초기화';
      // Hide 「초기화」 on list/browse pages; Assistant uses 「새 대화」.
      var showAction = showReset && (RESET_LABEL_ROUTES[currentRoute] || currentRoute === 'assistant');
      resetBtn.hidden = !showAction;
      resetBtn.disabled = !showAction;
      resetBtn.setAttribute('aria-disabled', showAction ? 'false' : 'true');
      if (!showAction) resetBtn.setAttribute('hidden', 'hidden');
      else resetBtn.removeAttribute('hidden');
    }
  }

  function bindChrome() {
    var backBtn = document.getElementById('yai-nav-back');
    var resetBtn = document.getElementById('yai-nav-reset');
    if (backBtn && backBtn.dataset.bound !== '1') {
      backBtn.dataset.bound = '1';
      backBtn.addEventListener('click', function () {
        var semanticRoute = backBtn.getAttribute('data-semantic-route');
        if (semanticRoute) {
          navigate(semanticRoute, { replace: true, fromBack: true });
          return;
        }
        goBack(currentRoute);
      });
    }
    if (resetBtn && resetBtn.dataset.bound !== '1') {
      resetBtn.dataset.bound = '1';
      resetBtn.addEventListener('click', function () {
        resetWithConfirm(currentRoute);
      });
    }
    syncChrome();
  }

  /** Local page headers no longer duplicate topbar back/reset. */
  function headerActionsHtml(studioId) {
    return '';
  }

  function bindHeaderActions(root, studioId) {
    if (!root) return;
    root.querySelectorAll('[data-yai-studio-back]').forEach(function (btn) {
      if (btn.dataset.bound === '1') return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () { goBack(studioId); });
    });
    root.querySelectorAll('[data-yai-studio-reset]').forEach(function (btn) {
      if (btn.dataset.bound === '1') return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () { resetWithConfirm(studioId); });
    });
  }

  // Public API
  global.YooYConfirm = {
    dialog: confirmDialog
  };

  global.YooYNavigation = {
    push: pushRoute,
    peek: peekBack,
    pop: popBack,
    goBack: goBack,
    canGoBack: canGoBack,
    navigate: navigate,
    rememberSource: rememberSource,
    getContext: getContext,
    setCurrent: setCurrent,
    getCurrent: getCurrent,
    pushStep: pushStep,
    bindChrome: bindChrome,
    syncChrome: syncChrome,
    guardLeave: guardLeave,
    headerActionsHtml: headerActionsHtml,
    bindHeaderActions: bindHeaderActions,
    STUDIO_ROUTES: STUDIO_ROUTES
  };

  global.YooYStudioState = {
    register: register,
    get: getHandler,
    isDirty: isDirty,
    canGoBack: canGoBack,
    goBack: goBack,
    reset: function (id, opts) { return runReset(id, opts); },
    saveDraft: function (id) {
      var h = handlers[id];
      if (h && typeof h.saveDraft === 'function') return h.saveDraft();
      return Promise.resolve(false);
    },
    dirtyFlags: dirtyFlags,
    remount: remountStudio
  };

  global.YooYStudioReset = {
    reset: function (studioId, opts) {
      if (opts && opts.force) return runReset(studioId, opts);
      return resetWithConfirm(studioId);
    },
    resetNow: runReset
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindChrome);
  } else {
    bindChrome();
  }
})(typeof window !== 'undefined' ? window : this);
