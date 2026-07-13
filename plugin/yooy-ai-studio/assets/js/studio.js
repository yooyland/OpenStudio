(function () {
  'use strict';

  // ── Project Create Dialog (body portal, isolated from .yai-app z-index) ──
  var YOY_CREATE_DIALOG_ID = 'yoy-project-create-dialog';
  var pendingCreateWorkIds = [];
  var ignoreBackdropUntil = 0;

  function yoyProjectsDebugEnabled() {
    try {
      if (window.YOOY_DEBUG) return true;
      if (window.YooYCore && typeof window.YooYCore.debug === 'function') {
        return !!window.YooYCore.debug();
      }
      if (window.YooYStudio && window.YooYStudio.debug) return true;
    } catch (e) { /* ignore */ }
    return false;
  }

  function yoyProjectsLog() {
    if (!yoyProjectsDebugEnabled()) return;
    var args = ['[YooY Projects]'].concat(Array.prototype.slice.call(arguments));
    if (window.console && console.log) console.log.apply(console, args);
  }

  function yoyIsLoggedIn() {
    try {
      var cfg = (window.YooYCore && window.YooYCore.config) || window.YooYStudio || {};
      return !!cfg.loggedIn;
    } catch (e) {
      return false;
    }
  }

  function yoyShowLoginPrompt() {
    try {
      var modal = document.getElementById('yai-login-modal');
      if (modal) {
        modal.hidden = false;
        modal.classList.add('is-open');
      }
      if (window.console && console.warn) {
        console.warn('[YooY Projects] login required to create a project');
      }
    } catch (e) { /* ignore */ }
  }

  function yoyCreateDialogHtml() {
    return '' +
      '<div class="yoy-project-dialog__backdrop" data-yoy-project-dialog-close="1"></div>' +
      '<div class="yoy-project-dialog__panel" role="dialog" aria-modal="true" aria-labelledby="yoy-project-create-title">' +
        '<header class="yoy-project-dialog__head">' +
          '<h2 id="yoy-project-create-title">프로젝트 생성</h2>' +
          '<button type="button" class="yoy-project-dialog__close" data-yoy-project-dialog-close="1" aria-label="Close">&times;</button>' +
        '</header>' +
        '<form id="yai-project-form" class="yoy-project-dialog__body" novalidate>' +
          '<label class="yoy-project-dialog__field"><span>Project Name *</span>' +
            '<input type="text" name="title" required maxlength="120" placeholder="내 AI 프로젝트" autocomplete="off"></label>' +
          '<label class="yoy-project-dialog__field"><span>Description</span>' +
            '<textarea name="description" rows="3" maxlength="500" placeholder="이 프로젝트는 무엇을 위한 것인가요?"></textarea></label>' +
          '<label class="yoy-project-dialog__field"><span>Category</span>' +
            '<select name="category">' +
              '<option value="mixed" selected>Mixed</option><option value="image">Image</option><option value="video">Video</option>' +
              '<option value="music">Music</option><option value="writing">Writing</option><option value="translation">Translation</option>' +
              '<option value="avatar">Avatar</option><option value="voice">Voice</option>' +
            '</select></label>' +
          '<label class="yoy-project-dialog__field"><span>Visibility</span>' +
            '<select name="visibility"><option value="private" selected>Private</option><option value="public">Public</option></select></label>' +
          '<label class="yoy-project-dialog__field"><span>Language</span>' +
            '<select name="language"><option value="ko" selected>한국어 (ko)</option><option value="en">English (en)</option>' +
            '<option value="ja">日本語 (ja)</option><option value="zh">中文 (zh)</option></select></label>' +
          '<label class="yoy-project-dialog__field"><span>Cover URL</span>' +
            '<input type="url" name="cover" maxlength="500" placeholder="https://…"></label>' +
          '<p class="yoy-project-dialog__hint" id="yai-project-form-works-hint" hidden></p>' +
          '<p class="yoy-project-dialog__error" id="yai-project-form-error" hidden></p>' +
          '<footer class="yoy-project-dialog__foot">' +
            '<button type="button" class="yoy-project-dialog__btn yoy-project-dialog__btn--ghost" data-yoy-project-dialog-close="1">Cancel</button>' +
            '<button type="submit" class="yoy-project-dialog__btn yoy-project-dialog__btn--gold">Create Project</button>' +
          '</footer>' +
        '</form>' +
      '</div>';
  }

  function yoyEnsureCreateDialog() {
    var existing = document.getElementById(YOY_CREATE_DIALOG_ID);
    if (existing) {
      if (existing.parentNode !== document.body) {
        document.body.appendChild(existing);
      }
      // Remove accidental duplicates
      var dupes = document.querySelectorAll('#' + YOY_CREATE_DIALOG_ID);
      for (var i = 1; i < dupes.length; i++) {
        if (dupes[i].parentNode) dupes[i].parentNode.removeChild(dupes[i]);
      }
      return existing;
    }

    // Migrate/remove legacy modal if present
    var legacy = document.getElementById('yai-project-modal');
    if (legacy && legacy.parentNode) legacy.parentNode.removeChild(legacy);

    var modal = document.createElement('div');
    modal.id = YOY_CREATE_DIALOG_ID;
    modal.className = 'yoy-project-dialog';
    modal.setAttribute('aria-hidden', 'true');
    modal.hidden = true;
    modal.innerHTML = yoyCreateDialogHtml();
    document.body.appendChild(modal);

    modal.addEventListener('click', function (ev) {
      var closer = ev.target.closest('[data-yoy-project-dialog-close]');
      if (!closer) return;
      // Same click that opened must not immediately close via backdrop.
      if (Date.now() < ignoreBackdropUntil && closer.classList.contains('yoy-project-dialog__backdrop')) {
        return;
      }
      ev.preventDefault();
      ev.stopPropagation();
      window.YooYCloseProjectCreateDialog();
    });

    var form = modal.querySelector('#yai-project-form');
    if (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        if (typeof window.__yoySubmitProjectCreateInternal === 'function') {
          window.__yoySubmitProjectCreateInternal(form);
        } else {
          yoyFallbackSubmitCreate(form);
        }
      });
    }

    return modal;
  }

  function yoyFallbackSubmitCreate(form) {
    var Core = window.YooYCore;
    if (!Core || !Core.projects || typeof Core.projects.create !== 'function') return;
    var title = ((form.querySelector('[name="title"]') || {}).value || '').trim();
    if (!title) return;
    var cat = (form.querySelector('[name="category"]') || {}).value || 'mixed';
    var vis = (form.querySelector('[name="visibility"]') || {}).value || 'private';
    var lang = (form.querySelector('[name="language"]') || {}).value || 'ko';
    var cover = ((form.querySelector('[name="cover"]') || {}).value || '').trim();
    var desc = ((form.querySelector('[name="description"]') || {}).value || '').trim();
    yoyProjectsLog('create request started', title);
    Core.projects.create({
      name: title, title: title, description: desc, category: cat, type: cat,
      visibility: vis, language: lang, cover: cover, thumbnail_url: cover
    }).then(function (res) {
      var created = (res.data && res.data.project) || res.project || null;
      yoyProjectsLog('project created', created && created.id);
      window.YooYCloseProjectCreateDialog();
      if (window.YooYActiveProject && created && created.id) {
        window.YooYActiveProject.set({ id: created.id, name: created.title || title });
      }
      if (window.YooYStudioOpenProject && created && created.id) {
        window.YooYStudioOpenProject(created.id);
      } else {
        location.reload();
      }
    }).catch(function (err) {
      if (window.console && console.error) console.error('[YooY Projects] create failed', err);
    });
  }

  window.YooYCloseProjectCreateDialog = function () {
    var modal = document.getElementById(YOY_CREATE_DIALOG_ID);
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('yai-modal-open');
    document.body.classList.remove('yoy-project-dialog-open');
    pendingCreateWorkIds = [];
    var form = modal.querySelector('#yai-project-form');
    if (form) form.reset();
  };

  window.YooYOpenProjectCreateDialog = function (workIds) {
    try {
      if (!yoyIsLoggedIn()) {
        yoyShowLoginPrompt();
        return;
      }

      pendingCreateWorkIds = Array.isArray(workIds)
        ? workIds.filter(function (id) { return !!id; })
        : (workIds ? [workIds] : []);

      // Prefer studio-boot internal when available (keeps pending ids in boot closure too)
      if (typeof window.__yoyOpenProjectModalInternal === 'function') {
        window.__yoyOpenProjectModalInternal(pendingCreateWorkIds.slice());
        return;
      }

      var modal = yoyEnsureCreateDialog();
      var form = modal.querySelector('#yai-project-form');
      if (form) {
        form.reset();
        var lang = form.querySelector('[name="language"]');
        if (lang) lang.value = 'ko';
        var vis = form.querySelector('[name="visibility"]');
        if (vis) vis.value = 'private';
        var cat = form.querySelector('[name="category"]');
        if (cat) cat.value = 'mixed';
      }
      var err = modal.querySelector('#yai-project-form-error');
      if (err) { err.hidden = true; err.textContent = ''; }
      var hint = modal.querySelector('#yai-project-form-works-hint');
      if (hint) {
        if (pendingCreateWorkIds.length) {
          hint.textContent = '선택한 작품 ' + pendingCreateWorkIds.length + '개가 생성 후 프로젝트에 연결됩니다.';
          hint.hidden = false;
        } else {
          hint.textContent = '';
          hint.hidden = true;
        }
      }

      ignoreBackdropUntil = Date.now() + 400;
      modal.hidden = false;
      modal.removeAttribute('hidden');
      modal.setAttribute('aria-hidden', 'false');
      modal.classList.add('is-open');
      document.body.classList.add('yai-modal-open');
      document.body.classList.add('yoy-project-dialog-open');
      yoyProjectsLog('create dialog opened');

      var titleInput = form && form.querySelector('[name="title"]');
      if (titleInput) {
        setTimeout(function () { titleInput.focus(); }, 0);
      }
    } catch (err) {
      if (window.console && console.error) console.error('[YooY Projects] open failed', err);
    }
  };

  window.__yoyGetPendingCreateWorkIds = function () {
    return pendingCreateWorkIds.slice();
  };
  window.__yoyClearPendingCreateWorkIds = function () {
    pendingCreateWorkIds = [];
  };

  // Exactly one document-level delegation (Abort previous if script hot-reloads)
  if (window.__YOOY_PROJECT_CREATE_EVENTS_AC__) {
    try { window.__YOOY_PROJECT_CREATE_EVENTS_AC__.abort(); } catch (e) { /* ignore */ }
  }
  window.__YOOY_PROJECT_CREATE_EVENTS_BOUND__ = true;
  window.__YOOY_PROJECT_CREATE_EVENTS_AC__ = new AbortController();
  var createEventsSignal = window.__YOOY_PROJECT_CREATE_EVENTS_AC__.signal;

  document.addEventListener('click', function handleProjectCreateTrigger(event) {
    var button = event.target.closest(
      '[data-action="create-project"], [data-yai-create-project], #yai-projects-create-btn, #yis-create-project, .yis-create-project, .yai-create-project'
    );
    if (!button) return;
    event.preventDefault();
    event.stopPropagation();
    if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
    yoyProjectsLog('create trigger clicked', button.id || button.getAttribute('data-action') || 'create');
    window.YooYOpenProjectCreateDialog();
  }, { capture: true, signal: createEventsSignal });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    var modal = document.getElementById(YOY_CREATE_DIALOG_ID);
    if (!modal || !modal.classList.contains('is-open')) return;
    event.preventDefault();
    window.YooYCloseProjectCreateDialog();
  }, { signal: createEventsSignal });

  function startStudioBoot(attempt) {
    attempt = attempt || 0;
    try {
      if (!window.YooYCore) {
        if (attempt < 80) {
          setTimeout(function () { startStudioBoot(attempt + 1); }, 50);
        } else if (window.console && console.error) {
          console.error('[YooYStudio] YooYCore missing — Studio boot aborted (Create Dialog still available)');
        }
        return;
      }
      if (window.__YOOY_STUDIO_BOOTED__) return;
      window.__YOOY_STUDIO_BOOTED__ = true;
      bootYooYStudio(window.YooYCore);
    } catch (bootErr) {
      window.__YOOY_STUDIO_BOOTED__ = false;
      if (window.console && console.error) console.error('[YooYStudio] init failed', bootErr);
    }
  }

  function bootYooYStudio(Core) {
  var Y = window;

  var loaded = {};
  var currentProjectId = '';
  var projectDetailFilter = 'all';
  var workspaceTab = 'overview';
  var workspaceCache = { project: null, works: [] };
  var pendingCreateWorkIds = [];
  var homeActivityCache = [];
  var pendingAdminSection = '';
  var STUDIO_PAGES = ['video', 'image', 'music', 'voice', 'avatar', 'writing', 'translator'];
  var WORKSPACE_TABS = [
    { id: 'overview', label: 'Overview', reserved: false },
    { id: 'assets', label: 'Assets', reserved: false },
    { id: 'history', label: 'History', reserved: false },
    { id: 'notes', label: 'Notes', reserved: false },
    { id: 'assistant', label: 'AI Assistant', reserved: false },
    { id: 'launcher', label: 'Studio Launcher', reserved: false },
    { id: 'settings', label: 'Settings', reserved: false }
  ];
  var PROJECT_CATEGORIES = [
    { id: 'mixed', label: 'Mixed' },
    { id: 'image', label: 'Image' },
    { id: 'video', label: 'Video' },
    { id: 'music', label: 'Music' },
    { id: 'writing', label: 'Writing' },
    { id: 'translation', label: 'Translation' },
    { id: 'avatar', label: 'Avatar' },
    { id: 'voice', label: 'Voice' }
  ];
  var PROJECT_LANGUAGES = [
    { id: 'ko', label: '한국어 (ko)' },
    { id: 'en', label: 'English (en)' },
    { id: 'ja', label: '日本語 (ja)' },
    { id: 'zh', label: '中文 (zh)' }
  ];
  var PROTECTED_ROUTES = ['projects', 'project-detail', 'import', 'video', 'image', 'music', 'voice', 'avatar', 'writing', 'translator', 'history', 'assistant'];
  var PLAN_CRYSTALS = {
    free: 'gray', starter: 'green', creator: 'blue', pro: 'purple', business: 'gold'
  };
  var CRYSTAL_LABELS = {
    free: 'Obsidian Crystal', starter: 'Emerald Crystal', creator: 'Sapphire Crystal',
    pro: 'Amethyst Crystal', business: 'Golden Diamond Crystal'
  };
  var COMPARE_ROWS = [
    { id: 'image', label: 'Image Generation', icon: '🖼', highlight: true },
    { id: 'video', label: 'Video Generation', icon: '🎬', highlight: true },
    { id: 'music', label: 'Music Generation', icon: '🎵', highlight: true },
    { id: 'voice', label: 'Voice Generation', icon: '🎤', highlight: true },
    { id: 'avatar', label: 'Avatar', icon: '🤖', highlight: false },
    { id: 'writing', label: 'Writing', icon: '✍', highlight: true },
    { id: 'projects', label: 'Projects', icon: '📁', highlight: true },
    { id: 'marketplace', label: 'Marketplace', icon: '🛒', highlight: true },
    { id: 'team', label: 'Team Workspace', icon: '👥', highlight: true },
    { id: 'admin', label: 'Admin', icon: '⚙', highlight: false },
    { id: 'priority', label: 'Priority Processing', icon: '🚀', highlight: true },
    { id: 'api', label: 'API', icon: '🌐', highlight: true },
    { id: 'commercial', label: 'Commercial License', icon: '✓', highlight: true },
    { id: 'limits', label: 'Higher Limits', icon: '⚡', highlight: true }
  ];
  var PLAN_FEATURE_MATRIX = {
    free:     { image: 1, video: 0, music: 0, voice: 0, avatar: 0, writing: 0, projects: 0, marketplace: 0, team: 0, admin: 0, priority: 0, api: 0, commercial: 0, limits: 0 },
    starter:  { image: 1, video: 0, music: 1, voice: 1, avatar: 0, writing: 0, projects: 1, marketplace: 0, team: 0, admin: 0, priority: 0, api: 0, commercial: 0, limits: 0 },
    creator:  { image: 1, video: 1, music: 1, voice: 1, avatar: 0, writing: 1, projects: 1, marketplace: 1, team: 0, admin: 0, priority: 0, api: 0, commercial: 0, limits: 0 },
    pro:      { image: 1, video: 1, music: 1, voice: 1, avatar: 1, writing: 1, projects: 1, marketplace: 1, team: 0, admin: 0, priority: 1, api: 0, commercial: 1, limits: 0 },
    business: { image: 1, video: 1, music: 1, voice: 1, avatar: 1, writing: 1, projects: 1, marketplace: 1, team: 1, admin: 1, priority: 1, api: 1, commercial: 1, limits: 1 }
  };

  function normalizePlanId(plan) {
    var p = String(plan || 'free').toLowerCase();
    return PLAN_CRYSTALS[p] ? p : 'free';
  }

  function crystalInnerHtml() {
    return '<span class="yai-crystal-core" aria-hidden="true"></span>' +
      '<span class="yai-crystal-shine" aria-hidden="true"></span>';
  }

  function crystalHtml(planId, size, extraClass) {
    var id = normalizePlanId(planId);
    var color = PLAN_CRYSTALS[id];
    var sz = size || 'md';
    var cls = 'yai-membership-crystal yai-crystal--' + color + ' yai-crystal--' + sz;
    if (id === 'business') cls += ' yai-crystal--diamond';
    if (extraClass) cls += ' ' + extraClass;
    return '<div class="' + cls + '" role="img" aria-label="' + esc(CRYSTAL_LABELS[id] || 'Membership Crystal') + '">' +
      crystalInnerHtml() + '</div>';
  }

  function applyCrystal(el, planId, size) {
    if (!el) return;
    var id = normalizePlanId(planId);
    var color = PLAN_CRYSTALS[id];
    var sz = size || 'md';
    el.className = 'yai-membership-crystal yai-crystal--' + color + ' yai-crystal--' + sz +
      (id === 'business' ? ' yai-crystal--diamond' : '');
    el.setAttribute('role', 'img');
    el.removeAttribute('aria-hidden');
    el.setAttribute('aria-label', CRYSTAL_LABELS[id] || 'Membership Crystal');
    if (!el.querySelector('.yai-crystal-core')) el.innerHTML = crystalInnerHtml();
  }

  function planHasCompareFeature(planId, rowId) {
    var matrix = PLAN_FEATURE_MATRIX[normalizePlanId(planId)];
    return !!(matrix && matrix[rowId]);
  }

  function compareCheckHtml(has, planId) {
    if (has) {
      return '<span class="yai-compare-check yai-compare-check--' + (PLAN_CRYSTALS[normalizePlanId(planId)] || 'gray') + '" aria-label="Supported">✓</span>';
    }
    return '<span class="yai-compare-dash" aria-label="Not supported">—</span>';
  }

  function featureIconMeta(text) {
    var t = String(text || '').toLowerCase();
    if (t.indexOf('image') !== -1) return { icon: '🖼', label: 'Image generation' };
    if (t.indexOf('video') !== -1) return { icon: '🎬', label: 'Video generation' };
    if (t.indexOf('music') !== -1) return { icon: '🎵', label: 'Music generation' };
    if (t.indexOf('voice') !== -1 || t.indexOf('avatar') !== -1) return { icon: t.indexOf('avatar') !== -1 ? '🤖' : '🎤', label: t.indexOf('avatar') !== -1 ? 'Avatar' : 'Voice generation' };
    if (t.indexOf('writing') !== -1) return { icon: '✍', label: 'Writing' };
    if (t.indexOf('project') !== -1) return { icon: '📁', label: 'Projects' };
    if (t.indexOf('market') !== -1) return { icon: '🛒', label: 'Marketplace' };
    if (t.indexOf('team') !== -1) return { icon: '👥', label: 'Team workspace' };
    if (t.indexOf('admin') !== -1) return { icon: '⚙', label: 'Admin controls' };
    if (t.indexOf('api') !== -1 || t.indexOf('provider') !== -1) return { icon: '🌐', label: 'API provider control' };
    if (t.indexOf('priority') !== -1) return { icon: '🚀', label: 'Priority processing' };
    if (t.indexOf('commercial') !== -1) return { icon: '✓', label: 'Commercial license' };
    if (t.indexOf('limit') !== -1) return { icon: '⚡', label: 'Higher limits' };
    if (t.indexOf('gallery') !== -1) return { icon: '🖼', label: 'Gallery' };
    if (t.indexOf('community') !== -1) return { icon: '👥', label: 'Community' };
    return { icon: '✓', label: 'Feature' };
  }

  function isHighlightFeature(text) {
    return /image|video|music|voice|writing|project|marketplace|commercial|api|provider|priority|higher|team|admin|limit/i.test(String(text || ''));
  }

  function renderPlanFeatureItem(text, planId) {
    var meta = featureIconMeta(text);
    var hl = isHighlightFeature(text);
    var color = PLAN_CRYSTALS[normalizePlanId(planId)] || 'gray';
    var cls = 'yai-plan-feature' + (hl ? ' yai-plan-feature--highlight' : '');
    return '<li class="' + cls + '">' +
      '<span class="yai-plan-feature-icon" role="img" aria-label="' + esc(meta.label) + '">' + meta.icon + '</span>' +
      '<span class="yai-plan-feature-check yai-plan-feature-check--' + color + '" aria-hidden="true">✓</span>' +
      '<span>' + esc(text) + '</span></li>';
  }

  function planTierRank(planId) {
    var order = ['free', 'starter', 'creator', 'pro', 'business'];
    var idx = order.indexOf(normalizePlanId(planId));
    return idx < 0 ? 0 : idx;
  }

  function formatKrw(amount) {
    var n = Number(amount) || 0;
    return n === 0 ? 'Free' : '₩' + fmt(n);
  }

  function ensureBillingModal() {
    var existing = document.getElementById('yai-billing-modal');
    if (existing) return existing;
    var overlay = document.createElement('div');
    overlay.id = 'yai-billing-modal';
    overlay.className = 'yai-billing-modal';
    overlay.hidden = true;
    overlay.innerHTML =
      '<div class="yai-billing-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="yai-billing-modal-title">' +
        '<button type="button" class="yai-billing-modal__close" data-billing-close aria-label="Close">×</button>' +
        '<div class="yai-billing-modal__body" id="yai-billing-modal-body"></div>' +
      '</div>';
    document.body.appendChild(overlay);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay || e.target.closest('[data-billing-close]')) closeBillingModal();
    });
    return overlay;
  }

  function closeBillingModal() {
    var modal = document.getElementById('yai-billing-modal');
    if (modal) modal.hidden = true;
  }

  function isStudioAdmin() {
    return !!(Core && Core.config && Core.config.isAdmin);
  }

  function paymentNotReadyMessage(billing) {
    if (isStudioAdmin()) {
      return 'WooCommerce 상품 ID를 Membership Mapping에 연결하세요.';
    }
    return '결제 준비 중입니다. 관리자에게 문의해 주세요.';
  }

  function isPlanCheckoutReady(plan, billing) {
    billing = billing || {};
    plan = plan || {};
    if (!billing.woocommerce_active) return false;
    return !!plan.payment_ready || !!(plan.checkout_url || plan.yearly_checkout_url);
  }

  function renderPaymentSetupNotice(billing) {
    billing = billing || {};
    if (billing.payment_ready) return '';
    var msg = paymentNotReadyMessage(billing);
    var extra = isStudioAdmin()
      ? '<div class="yai-payment-setup-admin"><button type="button" class="yai-btn yai-btn--outline" data-route="admin-console">Membership Mapping 열기</button></div>'
      : '';
    return '<div class="yai-payment-setup-notice">' + esc(msg) + extra + '</div>';
  }

  function openCheckoutDialog(plan, billing) {
    var modal = ensureBillingModal();
    var body = document.getElementById('yai-billing-modal-body');
    if (!body) return;

    billing = billing || {};
    var monthly = plan.checkout_url || '';
    var yearly = plan.yearly_checkout_url || '';
    var planReady = isPlanCheckoutReady(plan, billing);

    if (!planReady || (!monthly && !yearly)) {
      body.innerHTML =
        '<h3 id="yai-billing-modal-title">업그레이드 준비 중</h3>' +
        '<p class="yai-billing-modal__lead">' + esc(paymentNotReadyMessage(billing)) + '</p>' +
        (isStudioAdmin()
          ? '<ul class="yai-billing-admin-checklist">' +
            '<li>WooCommerce 플러그인 활성화</li>' +
            '<li>플랜별 월간/연간 Product ID 연결</li>' +
            '<li>Save Mapping 후 Credits 페이지 새로고침</li>' +
            '</ul>' +
            '<button type="button" class="yai-btn yai-btn--gold" data-route="admin-console" data-billing-close>Membership Mapping</button>'
          : '<button type="button" class="yai-btn yai-btn--gold" data-billing-close>확인</button>');
      modal.hidden = false;
      body.querySelectorAll('[data-route="admin-console"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          closeBillingModal();
          if (Y.YooYStudioRoute) Y.YooYStudioRoute('admin-console');
        });
      });
      return;
    }

    var yearlyPrice = plan.yearly_price_krw ? formatKrw(plan.yearly_price_krw) + ' / 년' : '';
    body.innerHTML =
      '<h3 id="yai-billing-modal-title">' + esc(plan.name) + ' 업그레이드</h3>' +
      '<p class="yai-billing-modal__lead">' + fmt(plan.credits) + ' credits · ' + esc((plan.features || [])[0] || '') + '</p>' +
      '<div class="yai-billing-modal__options">' +
        (monthly ? '<button type="button" class="yai-btn yai-btn--gold yai-billing-checkout" data-checkout-url="' + esc(monthly) + '">' +
          '월간 결제 · ' + esc(formatKrw(plan.price_krw)) + '</button>' : '') +
        (yearly ? '<button type="button" class="yai-btn yai-billing-checkout" data-checkout-url="' + esc(yearly) + '">' +
          '연간 결제 · ' + esc(yearlyPrice) + '</button>' : '') +
      '</div>' +
      '<p class="yai-muted yai-billing-modal__note">WooCommerce 안전 결제로 이동합니다. 결제 완료 후 멤버십이 자동 반영됩니다.</p>';

    body.querySelectorAll('.yai-billing-checkout').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-checkout-url');
        if (url) {
          try { sessionStorage.setItem('yoy_billing_pending', '1'); } catch (e) {}
          window.location.href = url;
        }
      });
    });
    modal.hidden = false;
  }

  function renderPlanButton(p, cur, billing) {
    var pid = normalizePlanId(p.id);
    var action = p.action || (pid === cur ? 'current' : (planTierRank(pid) > planTierRank(cur) ? 'upgrade' : 'downgrade'));

    if (action === 'current' || pid === cur) {
      return '<button type="button" class="yai-btn yai-plan-btn yai-plan-btn--current" disabled>✓ 현재 이용 중</button>';
    }
    if (pid === 'free') {
      return '<button type="button" class="yai-btn yai-plan-btn" disabled>Free Plan</button>';
    }
    if (action === 'downgrade') {
      var email = (billing && billing.support_email) ? 'mailto:' + billing.support_email + '?subject=' + encodeURIComponent('YooY Plan Downgrade') : '#';
      return '<a class="yai-btn yai-plan-btn yai-plan-btn--ghost" href="' + esc(email) + '">문의하기</a>';
    }
    var planReady = isPlanCheckoutReady(p, billing);
    if (!planReady && pid !== 'free') {
      return '<button type="button" class="yai-btn yai-plan-btn yai-plan-btn--pending" data-plan-upgrade="' + esc(p.id) + '">Upgrade</button>';
    }
    return '<button type="button" class="yai-btn yai-btn--gold yai-plan-btn" data-plan-upgrade="' + esc(p.id) + '">' +
      esc(p.button_label || 'Upgrade') + '</button>';
  }

  function bindPlanUpgradeButtons(root, plans, billing) {
    if (!root) return;
    var planMap = {};
    plans.forEach(function (p) { planMap[normalizePlanId(p.id)] = p; });

    root.querySelectorAll('[data-plan-upgrade]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var plan = planMap[normalizePlanId(btn.getAttribute('data-plan-upgrade'))];
        if (plan) openCheckoutDialog(plan, billing);
      });
    });

    root.querySelectorAll('[data-plan-checkout]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-plan-checkout');
        if (url) {
          try { sessionStorage.setItem('yoy_billing_pending', '1'); } catch (e) {}
          window.location.href = url;
        }
      });
    });
  }

  function refreshMembershipUI() {
    loadProfile();
    loaded.credits = false;
    loaded.billing = false;
    var creditsEl = document.getElementById('yai-credits-panel');
    var billingEl = document.getElementById('yai-billing-panel');
    if (creditsEl && document.querySelector('[data-page="credits"].is-active')) loadCredits();
    if (billingEl && document.querySelector('[data-page="billing"].is-active')) loadBilling();
  }

  function watchBillingReturn() {
    var pending = false;
    try { pending = sessionStorage.getItem('yoy_billing_pending') === '1'; } catch (e) {}
    if (pending) {
      try { sessionStorage.removeItem('yoy_billing_pending'); } catch (e2) {}
      refreshMembershipUI();
    }
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') refreshMembershipUI();
    });
  }

  function renderCurrentPlanDashboard(acc) {
    var planId = normalizePlanId(acc.plan || acc.tier);
    var mu = acc.monthly_usage || {};
    var pct = mu.percent || 0;
    var isBusiness = planId === 'business';
    var dashCls = 'yai-current-plan' + (isBusiness ? ' yai-current-plan--business' : ' yai-current-plan--' + planId);
    var balance = acc.unlimited ? '∞' : fmt(acc.remaining != null ? acc.remaining : acc.balance);
    var autoRenew = acc.renewal_at ? 'Auto-renewal on' : 'Auto-renewal off';
  return '<section class="' + dashCls + '">' +
      '<div class="yai-current-plan-crystal">' + crystalHtml(planId, 'xl', isBusiness ? 'yai-crystal--current' : '') + '</div>' +
      '<div class="yai-current-plan-main">' +
        '<div class="yai-current-plan-title">' +
          '<h2>' + esc(acc.plan_name || acc.tier || 'Free') + '</h2>' +
          '<span class="yai-plan-current-badge">CURRENT PLAN</span>' +
        '</div>' +
        '<p class="yai-current-plan-allotment">' + fmt(acc.plan_credits || acc.credits || 0) + ' credits</p>' +
      '</div>' +
      '<div class="yai-current-plan-metrics">' +
        '<div class="yai-current-plan-metric"><span>Credit Balance</span><strong class="yai-current-plan-balance">' + balance + '</strong></div>' +
        '<div class="yai-current-plan-metric yai-current-plan-metric--usage">' +
          '<span>Monthly Usage</span><strong>' + fmt(mu.used || 0) + ' / ' + fmt(mu.limit || 0) + '</strong>' +
          '<div class="yai-progress yai-progress--plan" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
            '<i style="width:' + pct + '%"></i></div>' +
        '</div>' +
        '<div class="yai-current-plan-metric"><span>Next Renewal</span><strong>' + esc(acc.renewal_label || '—') + '</strong><em>' + esc(autoRenew) + '</em></div>' +
      '</div>' +
      '<div class="yai-current-plan-actions">' +
        '<button type="button" class="yai-btn yai-btn--gold" id="yai-credits-scroll-plans">Upgrade</button>' +
        '<button type="button" class="yai-btn yai-btn--outline" data-route="billing">Billing</button>' +
      '</div>' +
    '</section>';
  }

  var ROUTE_TITLES = {
    home: 'Home', assistant: 'AI Assistant', projects: 'Projects', 'project-detail': 'Project Workspace', video: 'Video Studio', image: 'Image Studio',
    music: 'Music Studio', voice: 'Voice Studio', avatar: 'Avatar Studio', writing: 'Writing Studio', translator: 'Translator',
    import: 'Import', works: 'Gallery', history: 'History', community: 'Community', market: 'Marketplace',
    credits: 'Credits', billing: 'Billing', settings: 'Settings', 'prompt-library': 'Prompt Library', 'admin-console': 'Admin Console'
  };

  function isLoggedIn() { return !!Core.config.loggedIn; }
  function loginUrl() { return Core.config.loginUrl || '#'; }

  function registerUrl() { return Core.config.registerUrl || loginUrl(); }
  function logoutUrl() { return Core.config.logoutUrl || '#'; }

  function showLoginModal() {
    var modal = document.getElementById('yai-login-modal');
    if (modal) modal.hidden = false;
  }

  function hideLoginModal() {
    var modal = document.getElementById('yai-login-modal');
    if (modal) modal.hidden = true;
  }

  function requireLogin() {
    if (isLoggedIn()) return true;
    showLoginModal();
    return false;
  }

  function openPanel(name) {
    var panel = document.getElementById('yai-panel-' + name);
    if (panel) panel.hidden = false;
  }

  function closePanels() {
    document.querySelectorAll('.yai-overlay[id^="yai-panel-"]').forEach(function (el) {
      el.hidden = true;
    });
  }

  function setTopbarTitle(name) {
    var el = document.getElementById('yai-topbar-title');
    if (el) el.textContent = ROUTE_TITLES[name] || 'YooY AI Studio';
  }

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  function fmt(n) {
    var x = Number(n);
    if (isNaN(x)) return '0';
    try { return x.toLocaleString(); } catch (e) { return String(x); }
  }

  function relTime(v) {
    if (!v) return '—';
    var d = new Date(v);
    if (isNaN(d.getTime())) return esc(v);
    var m = Math.floor((Date.now() - d.getTime()) / 60000);
    if (m < 1) return 'just now';
    if (m < 60) return m + 'm ago';
    var h = Math.floor(m / 60);
    if (h < 24) return h + 'h ago';
    return Math.floor(h / 24) + 'd ago';
  }

  function statusTag(s) {
    var k = String(s || 'active').toLowerCase();
    var cls = 'yai-tag--active';
    var label = s || 'active';
    if (k === 'completed' || k === 'done' || k === 'success') { cls = 'yai-tag--done'; label = '완료'; }
    else if (k === 'failed' || k === 'error') { cls = 'yai-tag--fail'; label = '실패'; }
    else if (k === 'processing' || k === 'running') { label = '진행중'; }
    else if (k === 'pending') { label = '대기'; }
    return '<span class="yai-tag ' + cls + '">' + esc(label) + '</span>';
  }

  function emptyBlock(icon, title, desc, btnLabel, routeName, createProject) {
    var btn = '';
    if (createProject && btnLabel) {
      btn = '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm yai-create-project" data-action="create-project" data-yai-create-project>' + esc(btnLabel) + '</button>';
    } else if (routeName && btnLabel) {
      btn = '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-route="' + esc(routeName) + '">' + esc(btnLabel) + '</button>';
    }
    var iconHtml = icon
      ? '<div class="yai-empty-card-icon" aria-hidden="true">' + icon + '</div>'
      : '<div class="yai-empty-card-icon" aria-hidden="true">✦</div>';
    return '<div class="yai-empty-card">' + iconHtml +
      '<h3>' + esc(title) + '</h3><p>' + esc(desc) + '</p>' + btn +
      '</div>';
  }

  function route(name) {
    if (name === 'admin-console' && !Core.config.isAdmin) return;
    if (PROTECTED_ROUTES.indexOf(name) !== -1 && !isLoggedIn()) {
      showLoginModal();
      return;
    }

    document.querySelectorAll('.yai-view').forEach(function (el) {
      el.classList.toggle('is-active', el.dataset.page === name);
    });

    document.querySelectorAll('[data-route]').forEach(function (btn) {
      if (btn.dataset.route) {
        var active = btn.dataset.route === name;
        if (name === 'project-detail' && btn.dataset.route === 'projects') active = true;
        if (name === 'history' && btn.dataset.route === 'history') active = true;
        btn.classList.toggle('is-active', active);
      }
    });

    setTopbarTitle(name);
    var main = document.getElementById('yai-main');
    if (main) main.scrollTop = 0;
    hydrate(name);
    syncProjectContextBanner(name);
  }

  function hydrate(name) {
    if (name === 'works') { loaded[name] = false; loadWorks(); loaded[name] = true; return; }
    if (name === 'history') { loaded[name] = false; loadHistory(); loaded[name] = true; return; }
    if (name === 'home') { loaded[name] = false; loadHome(); loaded[name] = true; return; }
    if (name === 'assistant') {
      mountStudio('assistant', 'YooYAIAssistant');
      if (window.YooYAIAssistant && typeof window.YooYAIAssistant.refresh === 'function') {
        window.YooYAIAssistant.refresh();
      }
      loaded[name] = true;
      return;
    }
    if (name === 'projects') { loadProjects(); return; }
    if (loaded[name]) return;
    loaded[name] = true;

    switch (name) {
      case 'projects': loadProjects(); break;
      case 'project-detail': loadProjectDetail(); break;
      case 'prompt-library': loadPrompts(); break;
      case 'import':
        loaded[name] = false;
        mountImport();
        loaded[name] = true;
        break;
      case 'market': loadMarket(); break;
      case 'community': loadCommunity(); break;
      case 'credits': loadCredits(); break;
      case 'billing': loadBilling(); break;
      case 'settings': loadSettings(); break;
      case 'admin-console':
        loaded[name] = false;
        if (window.YooYAdminConsole) {
          window.YooYAdminConsole.openOps(pendingAdminSection || 'overview');
          pendingAdminSection = '';
        }
        loaded[name] = true;
        break;
      case 'video': mountStudio('video', 'YooYVideoStudio'); break;
      case 'image': mountStudio('image', 'YooYImageStudio'); break;
      case 'music': mountStudio('music', 'YooYMusicStudio'); break;
      case 'voice': mountStudio('voice', 'YooYVoiceStudio'); break;
      case 'avatar': mountStudio('avatar', 'YooYAvatarStudio'); break;
      case 'writing': loadWriting(); break;
      case 'translator': mountStudio('translator', 'YooYTranslatorStudio'); break;
    }
  }

  function mountStudio(page, globalName) {
    var el = document.getElementById('yai-' + page + '-studio');
    if (!el) return;
    if (el.dataset.mounted === '1') return;

    if (!window[globalName]) {
      el.innerHTML = '<p class="yai-empty">Loading studio…</p>';
      var attempts = 0;
      (function retry() {
        attempts += 1;
        if (window[globalName]) {
          mountStudio(page, globalName);
          return;
        }
        if (attempts < 60) setTimeout(retry, 100);
      })();
      return;
    }

    window[globalName].mount(el);
  }

  function mountImport() {
    var el = document.getElementById('yai-import-engine');
    if (!el) return;
    if (!window.YooYImportEngine) {
      el.innerHTML = '<p class="yai-empty">Loading Import Engine…</p>';
      var attempts = 0;
      (function retry() {
        attempts += 1;
        if (window.YooYImportEngine) { mountImport(); return; }
        if (attempts < 60) setTimeout(retry, 100);
      })();
      return;
    }
    el.dataset.mounted = '0';
    window.YooYImportEngine.mount(el);
  }

  function isValidFeedWork(w) {
    if (!w) return false;
    var url = w.thumbnail_url || w.display_url || w.large_url || w.full_url || '';
    if (!url) return false;
    if (url.indexOf('placeholder.svg') !== -1 && !w.is_platform && w.feed_source !== 'official' && w.feed_source !== 'demo') {
      return false;
    }
    return true;
  }

  function filterFeedWorks(items) {
    return (items || []).filter(isValidFeedWork);
  }

  function applyHomeFeedData(d) {
    d = d || {};
    if (isLoggedIn()) {
      var cr = d.credits || {};
      var mu = d.monthly_usage || {};
      setText('yai-stat-credits', cr.unlimited ? '∞' : fmt(cr.balance));
      setText('yai-stat-projects', fmt(d.project_count || 0));
      setText('yai-stat-works', fmt(d.work_count || (d.works || []).length || 0));
      setText('yai-stat-likes', fmt(d.community_likes || 0));
      setText('yai-top-credits', (cr.unlimited ? '∞' : fmt(cr.balance)) + ' Credits');
      renderUsageWidget(mu);
    } else {
      setText('yai-stat-credits', '—');
      setText('yai-stat-projects', '—');
      setText('yai-stat-works', fmt((d.works || []).length || 0));
      setText('yai-stat-likes', '—');
      setText('yai-top-credits', '무료로 시작하기');
      renderUsageWidget({ used: 0, limit: 0, percent: 0 });
      var head = document.querySelector('.yai-home-head h1');
      if (head) head.textContent = 'AI Creator Platform에 오신 것을 환영합니다 ✨';
      var sub = document.querySelector('.yai-hero-sub');
      if (sub) sub.textContent = '다른 크리에이터의 작품을 둘러보고, 가입 후 나만의 작품을 만들어 보세요.';
    }

    var works = filterFeedWorks(d.works || []);
    renderWorks(works.length ? works : filterFeedWorks(d.showcase || []));
    renderJobs(d.jobs || []);
    renderAnnouncements(d.announcements || []);
    renderShowcase(filterFeedWorks(d.showcase || []));
    renderHomeMarket(filterFeedWorks(d.marketplace || []));
    renderHomeCommunity(filterFeedWorks(d.community_trending || []));
    renderHomeSections(d.home_sections || []);
    renderProjects(Array.isArray(d.projects) ? d.projects.slice(0, 8) : []);
    loadHomeRecommendations();
  }

  function loadHomeRecommendations() {
    var el = document.getElementById('yai-home-recs');
    if (!el) return;
    if (!window.YooYCreateUX || typeof window.YooYCreateUX.loadRecommendations !== 'function') {
      el.innerHTML = '<p class="yai-muted">추천 UX를 불러오는 중…</p>';
      return;
    }
    window.YooYCreateUX.loadRecommendations(el, function (card) {
      var input = document.getElementById('yai-home-prompt');
      var seed = (card && (card.seed || card.seed_prompt || card.title)) || '';
      if (input) input.value = seed;
      if (seed) {
        try { sessionStorage.setItem('yoy_home_prompt', seed); } catch (e) {}
      }
    });
  }

  function loadHome() {
    var loader = isLoggedIn() ? Core.dashboard() : Core.homePublic();
    loader.then(function (res) {
      applyHomeFeedData(res.data || {});
    }).catch(function () {
      if (isLoggedIn()) {
        renderHomeEmpty();
        return;
      }
      Core.homePublic().then(function (res) {
        applyHomeFeedData(res.data || {});
      }).catch(function () {
        renderGuestFallback();
      });
    });
  }

  function renderGuestFallback() {
    setText('yai-stat-credits', '—');
    setText('yai-stat-projects', '—');
    setText('yai-stat-works', '0');
    setText('yai-stat-likes', '—');
    setText('yai-top-credits', '무료로 시작하기');
    renderWorks([]);
    renderJobs([]);
    renderAnnouncements([]);
    renderShowcase([]);
    renderHomeMarket([]);
    renderHomeCommunity([]);
    renderHomeSections([]);
    renderProjects([]);
    loadHomeRecommendations();
    renderUsageWidget({ used: 0, limit: 0, percent: 0 });
  }

  function feedSourceLabel(source) {
    var map = {
      user: 'My Work',
      project: 'Project',
      community: 'Community',
      marketplace: 'Marketplace',
      official: 'Official',
      demo: 'Demo'
    };
    return map[source] || '';
  }

  function isPlatformFeedItem(item) {
    if (!item) return false;
    if (item.is_platform) return true;
    var src = item.feed_source || '';
    return ['community', 'marketplace', 'official', 'demo'].indexOf(src) >= 0;
  }

  function platformDiscoverCardHtml(item, routeName) {
    var src = feedSourceLabel(item.feed_source || routeName || '');
    var badge = src ? '<span class="yai-feed-badge">' + esc(src) + '</span> ' : '';
    var extra = item.likes != null ? ' · ♥ ' + fmt(item.likes) : '';
    var price = item.price === 0 || item.price === '0' ? 'Free' : (item.price != null ? fmt(item.price) + ' KRW' : '');
    var meta = routeName === 'market' && price
      ? esc(item.creator || 'Creator') + ' · ' + price
      : esc(item.creator || item.provider || 'Creator') + extra;
    return '<article class="yai-discover-card yai-discover-card--feed" data-route="' + esc(routeName || 'home') + '">' +
      badge + '<strong>' + esc(item.title || 'Work') + '</strong><span>' + meta + '</span></article>';
  }

  function showcaseCardHtml(item) {
    var src = feedSourceLabel(item.feed_source || 'official');
    return '<article class="yai-showcase-card yai-showcase-card--feed">' +
      '<span class="yai-feed-badge">' + esc(src || item.type_label || item.type || 'Work') + '</span>' +
      '<h3>' + esc(item.title || 'Work') + '</h3>' +
      '<p>' + esc(item.prompt || item.description || item.creator || '') + '</p></article>';
  }

  function typeBadgeLabel(type) {
    var map = { image: 'Image', video: 'Video', music: 'Music', voice: 'Voice', avatar: 'Avatar', writing: 'Writing', translation: 'Translation' };
    return map[type] || (type ? String(type) : 'Work');
  }

  function workCardModeArg(arg) {
    if (arg === true) return 'carousel';
    if (arg === false) return 'dense';
    return arg || 'dense';
  }

  function workHoverActions(id) {
    return '<div class="yai-work-card-hover">' +
      '<button type="button" data-work-action="open" data-work-id="' + esc(id) + '">열기</button>' +
      '<button type="button" data-work-action="regenerate" data-work-id="' + esc(id) + '">프롬프트 재사용</button>' +
      '<button type="button" data-work-action="project" data-work-id="' + esc(id) + '">프로젝트 추가</button>' +
      '<button type="button" data-work-action="share" data-work-id="' + esc(id) + '">공유</button>' +
      '<button type="button" data-work-action="marketplace" data-work-id="' + esc(id) + '">판매</button>' +
      '<button type="button" data-work-action="delete" data-work-id="' + esc(id) + '">삭제</button>' +
    '</div>';
  }

  function galleryImg(item, opts) {
    opts = opts || {};
    if (window.YooYGalleryImage && typeof window.YooYGalleryImage.imgTag === 'function') {
      return window.YooYGalleryImage.imgTag(item, opts);
    }
    var src = (window.YooYGalleryImage && window.YooYGalleryImage.pickUrl)
      ? window.YooYGalleryImage.pickUrl(item, opts.size || 'large')
      : (item.display_url || item.large_url || item.full_url || item.image_url || item.thumbnail_url || '');
    if (!src) return '';
    return '<img src="' + esc(src) + '" alt="" class="yai-gallery-img" loading="' + (opts.lazy === false ? 'eager' : 'lazy') + '" decoding="async">';
  }

  function workThumbSizeForMode(mode) {
    if (mode === 'carousel' || mode === 'dense') return 'thumb';
    return 'large';
  }

  function workTextPreview(w) {
    var text = String(
      w.translated_text || w.excerpt || w.prompt || w.description || w.title || ''
    ).replace(/\s+/g, ' ').trim();
    if (text.length > 90) text = text.slice(0, 87) + '…';
    return text;
  }

  function isHttpUrl(url) {
    return /^https?:\/\//i.test(String(url || '').trim());
  }

  function workMediaUrl(w) {
    if (window.YooYGalleryImage && typeof window.YooYGalleryImage.pickUrl === 'function') {
      return window.YooYGalleryImage.pickUrl(w, 'thumb') || window.YooYGalleryImage.pickUrl(w, 'large') || '';
    }
    return w.thumbnail_url || w.display_url || w.image_url || w.output_url || w.asset_url || '';
  }

  function workThumbHtml(w, showHover, sizeArg, priority) {
    var id = w.id || '';
    var size = sizeArg || 'large';
    var hover = showHover && id ? workHoverActions(id) : '';
    var type = String(w.type || '').toLowerCase();

    if (w.asset_missing) {
      return '<div class="yai-work-card-thumb yai-work-card-thumb--placeholder">!' + hover + '</div>';
    }

    // Text assets must never render a broken <img>.
    if (type === 'translation' || type === 'writing') {
      var preview = workTextPreview(w);
      var icon = type === 'translation' ? '文' : '📄';
      return '<div class="yai-work-card-thumb yai-work-card-thumb--text" data-work-type="' + esc(type) + '">' +
        '<span class="yai-work-card-thumb__icon" aria-hidden="true">' + icon + '</span>' +
        (preview ? '<span class="yai-work-card-thumb__preview">' + esc(preview) + '</span>' : '') +
        hover + '</div>';
    }

    if (type === 'music' || type === 'voice') {
      var tone = type === 'music' ? '♪' : '🎤';
      return '<div class="yai-work-card-thumb yai-work-card-thumb--audio">' +
        '<span class="yai-work-card-thumb__icon" aria-hidden="true">' + tone + '</span>' +
        '<span class="yai-work-card-thumb__label">' + esc(typeBadgeLabel(type)) + '</span>' +
        hover + '</div>';
    }

    if (type === 'image' || type === 'video' || type === 'avatar' || !type) {
      var mediaUrl = workMediaUrl(w);
      if (isHttpUrl(mediaUrl)) {
        var img = galleryImg(w, { size: size, priority: !!priority, className: 'yai-gallery-img' });
        if (img) {
          return '<div class="yai-work-card-thumb">' + img + hover + '</div>';
        }
      }
    }

    return '<div class="yai-work-card-thumb yai-work-card-thumb--placeholder">' +
      '<span class="yai-work-card-thumb__icon">' + esc(typeBadgeLabel(type || 'image').substring(0, 1)) + '</span>' +
      hover + '</div>';
  }

  function workCardHtml(w, modeArg) {
    var mode = workCardModeArg(modeArg);
    var id = w.id || '';
    var showHover = mode !== 'carousel';
    var dense = mode === 'dense' || mode === 'default';
    var showcase = mode === 'showcase';
    var cardClass = 'yai-work-card yai-row--work';
    if (showcase) cardClass += ' yai-work-card--showcase';
    else if (dense) cardClass += ' yai-work-card--dense';
    else cardClass += ' yai-work-card--carousel';
    var metaHtml = (dense || showcase)
      ? '<div class="yai-work-card-meta yai-work-card-meta--compact">' +
          '<span class="yai-work-type-badge">' + esc(w.type_label || typeBadgeLabel(w.type)) + '</span>' +
          '<span>' + esc(w.provider || '—') + '</span>' +
          '<span>' + relTime(w.updated_at || w.created_at) + '</span>' +
        '</div>'
      : '<div class="yai-work-card-meta">' +
          '<span class="yai-work-type-badge">' + esc(w.type_label || typeBadgeLabel(w.type)) + '</span>' +
          '<span>' + esc(w.provider || '—') + '</span>' +
          '<span>' + relTime(w.updated_at || w.created_at) + '</span>' +
          (w.project_title ? '<span>' + esc(w.project_title) + '</span>' : '') +
        '</div>';
  return '<article class="' + cardClass + '" data-work-id="' + esc(id) + '" data-work-type="' + esc(w.type || 'image') + '" role="button" tabindex="0">' +
      workThumbHtml(w, showHover, workThumbSizeForMode(mode)) +
      '<div class="yai-work-card-body">' +
        '<h3 class="yai-work-card-title">' + esc(w.title || '작품') + '</h3>' +
        metaHtml +
      '</div>' +
      '<div class="yai-work-card-menu">' +
        '<button type="button" class="yai-work-menu-btn" data-work-menu="' + esc(id) + '" aria-label="작품 메뉴">⋯</button>' +
        '<div class="yai-work-menu" hidden>' +
          '<button type="button" data-work-action="open" data-work-id="' + esc(id) + '">열기</button>' +
          '<button type="button" data-work-action="regenerate" data-work-id="' + esc(id) + '">프롬프트 재사용</button>' +
          '<button type="button" data-work-action="project" data-work-id="' + esc(id) + '">프로젝트에 추가</button>' +
          '<button type="button" data-work-action="share" data-work-id="' + esc(id) + '">공유</button>' +
          '<button type="button" data-work-action="download" data-work-id="' + esc(id) + '">다운로드</button>' +
          '<button type="button" data-work-action="marketplace" data-work-id="' + esc(id) + '">Marketplace 등록</button>' +
          '<button type="button" data-work-action="delete" data-work-id="' + esc(id) + '">삭제</button>' +
          (mode === 'project-detail'
            ? '<button type="button" data-work-action="project-remove" data-work-id="' + esc(id) + '">프로젝트에서 제거</button>' +
              '<button type="button" data-work-action="project-cover" data-work-id="' + esc(id) + '">커버로 설정</button>'
            : '') +
        '</div>' +
      '</div>' +
    '</article>';
  }

  function sectionCardHtml(w, section) {
    var id = w.id || '';
    var ratio = section.card_ratio || 'auto';
    var textMode = section.text_mode || 'below';
    var isCarousel = section.column_count === 'carousel';
    var platform = isPlatformFeedItem(w);
    var sourceBadge = w.feed_source
      ? '<span class="yai-feed-badge yai-feed-badge--card">' + esc(feedSourceLabel(w.feed_source)) + '</span>'
      : '';
    var metaHtml = '<div class="yai-work-card-meta yai-work-card-meta--compact">' +
      sourceBadge +
      '<span class="yai-work-type-badge">' + esc(w.type_label || typeBadgeLabel(w.type)) + '</span>' +
      '<span>' + esc(w.provider || w.creator || '—') + '</span>' +
      '<span>' + relTime(w.updated_at || w.created_at) + '</span>' +
    '</div>';
    var openAttrs = platform
      ? ' data-feed-item="' + esc(id) + '" data-feed-source="' + esc(w.feed_source || 'official') + '"'
      : ' data-work-id="' + esc(id) + '" data-work-type="' + esc(w.type || 'image') + '"';
    return '<article class="yai-work-card yai-work-card--section yai-work-card--ratio-' + esc(ratio) +
      ' yai-work-card--text-' + esc(textMode) + (isCarousel ? ' yai-work-card--carousel' : '') +
      ' yai-row--work' + (platform ? ' yai-work-card--platform' : '') + '"' + openAttrs + ' role="button" tabindex="0">' +
      workThumbHtml(w, !platform, 'large') +
      '<div class="yai-work-card-body">' +
        '<h3 class="yai-work-card-title">' + esc(w.title || '작품') + '</h3>' +
        metaHtml +
      '</div>' +
      (platform ? '' :
      '<div class="yai-work-card-menu">' +
        '<button type="button" class="yai-work-menu-btn" data-work-menu="' + esc(id) + '" aria-label="작품 메뉴">⋯</button>' +
        '<div class="yai-work-menu" hidden>' +
          '<button type="button" data-work-action="open" data-work-id="' + esc(id) + '">열기</button>' +
          '<button type="button" data-work-action="regenerate" data-work-id="' + esc(id) + '">프롬프트 재사용</button>' +
          '<button type="button" data-work-action="project" data-work-id="' + esc(id) + '">프로젝트 추가</button>' +
          '<button type="button" data-work-action="share" data-work-id="' + esc(id) + '">공유</button>' +
          '<button type="button" data-work-action="marketplace" data-work-id="' + esc(id) + '">판매</button>' +
          '<button type="button" data-work-action="delete" data-work-id="' + esc(id) + '">삭제</button>' +
        '</div>' +
      '</div>') +
    '</article>';
  }

  function sectionRowAttrs(section) {
    var columnCount = section.column_count != null ? section.column_count : 4;
    var isCarousel = columnCount === 'carousel';
    if (isCarousel) {
      return {
        className: 'yai-home-section-row yai-home-section-row--carousel',
        style: ' style="--yai-section-ratio:' + esc(section.card_ratio || 'auto') + '"'
      };
    }
    var cols = parseInt(columnCount, 10);
    if (isNaN(cols) || cols < 2) cols = 4;
    if (cols > 6) cols = 6;
    return {
      className: 'yai-home-section-row yai-home-section-row--grid',
      style: ' style="--yai-section-cols:' + cols + ';--yai-section-ratio:' + esc(section.card_ratio || 'auto') + '"'
    };
  }

  function homeProjectSectionCard(p) {
    var thumb = p.thumbnail_url
      ? '<div class="yai-home-project-card__thumb"><img src="' + esc(p.thumbnail_url) + '" alt=""></div>'
      : '<div class="yai-home-project-card__thumb yai-home-project-card__thumb--empty">📁</div>';
    return '<article class="yai-home-project-card" data-project-open="' + esc(p.id) + '">' + thumb +
      '<div class="yai-home-project-card__body"><h3>' + esc(p.title || 'Project') + '</h3>' +
      '<span>' + fmt(p.asset_count || 0) + ' works · ' + relTime(p.updated_at || p.created_at) + '</span></div>' +
      '<button type="button" class="yai-btn--outline yai-btn--sm" data-project-open="' + esc(p.id) + '">열기</button></article>';
  }

  function renderHomeSections(sections) {
    var el = document.getElementById('yai-home-sections');
    if (!el) return;
    var visible = (sections || []).filter(function (section) {
      if (section.type === 'project') return (section.projects || []).length > 0;
      return (section.works || []).length > 0;
    });
    if (!visible.length) {
      el.innerHTML = '';
      return;
    }
    el.innerHTML = visible.map(function (section) {
      var columnCount = section.column_count != null ? section.column_count : 4;
      var isCarousel = columnCount === 'carousel';
      var moreRoute = section.source === 'marketplace' ? 'market'
        : (section.source === 'community' ? 'community' : (section.type === 'project' ? 'projects' : 'works'));

      if (section.type === 'project') {
        var projects = section.projects || [];
        var projectCards = projects.map(homeProjectSectionCard).join('');
        return '<section class="yai-home-section yai-home-section--projects" data-home-section="' + esc(section.id || '') + '">' +
          '<div class="yai-home-section-head">' +
            '<div><h2>' + esc(section.title || 'Projects') + '</h2><p>' + esc(section.description || '') + '</p></div>' +
            '<button type="button" class="yai-text-btn" data-route="' + esc(moreRoute) + '">더보기</button>' +
          '</div>' +
          '<div class="yai-home-section-row yai-home-section-row--projects">' + projectCards + '</div>' +
        '</section>';
      }

      var works = section.works || [];
      var row = sectionRowAttrs(section);
      var cards = works.map(function (w) { return sectionCardHtml(w, section); }).join('');
      return '<section class="yai-home-section yai-home-section--gallery' + (isCarousel ? ' yai-home-section--carousel' : ' yai-home-section--grid') +
        '" data-home-section="' + esc(section.id || '') + '" data-section-columns="' + esc(String(columnCount)) +
        '" data-card-ratio="' + esc(section.card_ratio || 'auto') + '" data-text-mode="' + esc(section.text_mode || 'below') + '">' +
        '<div class="yai-home-section-head">' +
          '<div><h2>' + esc(section.title || 'Section') + '</h2><p>' + esc(section.description || '') + '</p></div>' +
          '<button type="button" class="yai-text-btn" data-route="' + esc(moreRoute) + '">더보기</button>' +
        '</div>' +
        '<div class="' + row.className + '"' + row.style + '>' + cards + '</div>' +
      '</section>';
    }).join('');
  }

  var homePresetId = '';

  function resolveStudioFromPrompt(prompt, preset) {
    if (preset && preset.studio) return preset.studio;
    if (preset && preset.id) {
      var presetStudio = { kr_movie: 'video', kr_mv: 'video', kr_blog: 'writing' };
      if (presetStudio[preset.id]) return presetStudio[preset.id];
      if (String(preset.id).indexOf('kr_') === 0) return 'image';
    }
    var t = String(prompt || '').toLowerCase();
    if (/영상|비디오|video|유튜브|youtube|릴스|reels|쇼츠|shorts|뮤직비디오|mv/.test(t)) return 'video';
    if (/음악|music|bgm|song|뮤직|멜로디/.test(t)) return 'music';
    if (/음성|voice|tts|나레이션|더빙|보이스/.test(t)) return 'voice';
    if (/아바타|avatar|버추얼/.test(t)) return 'avatar';
    if (/글|writing|블로그|blog|카피|copy|스크립트|script|원고/.test(t)) return 'writing';
    return 'image';
  }

  function renderHomePresets() {
    var el = document.getElementById('yai-home-presets');
    if (!el) return;
    Core.prompts.presets().then(function (res) {
      var presets = (res.data && res.data.presets) || [];
      if (!presets.length) { el.innerHTML = ''; return; }
      el.innerHTML = '<span class="yai-preset-label">한국형 Preset</span>' +
        presets.slice(0, 12).map(function (p) {
          var active = homePresetId === p.id ? ' is-active' : '';
          return '<button type="button" class="yai-preset-chip' + active + '" data-yai-preset="' + esc(p.id) + '">' + esc(p.label) + '</button>';
        }).join('');
    }).catch(function () { el.innerHTML = ''; });
  }

  function renderHomeMarket(items) {
    var el = document.getElementById('yai-home-market');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = emptyBlock('🛒', 'Marketplace is empty', 'Listed templates and creator assets will appear here.', 'Browse Marketplace', 'market');
      return;
    }
    el.innerHTML = items.map(function (it) { return platformDiscoverCardHtml(it, 'market'); }).join('');
  }

  function renderHomeCommunity(items) {
    var el = document.getElementById('yai-home-community-trending');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = emptyBlock('👥', 'No trending works', 'Community highlights will appear when creators publish.', 'Open Community', 'community');
      return;
    }
    el.innerHTML = items.map(function (it) { return platformDiscoverCardHtml(it, 'community'); }).join('');
  }

  function launchFromHome() {
    if (!requireLogin()) return;
    var promptEl = document.getElementById('yai-home-prompt');
    var prompt = (promptEl && promptEl.value || '').trim();
    var presetCtx = '';
    var presetStudio = null;
    if (homePresetId) {
      try {
        sessionStorage.setItem('yoy_home_preset', homePresetId);
      } catch (e) { /* ignore */ }
    }
    Core.prompts.presets().then(function (res) {
      var presets = (res.data && res.data.presets) || [];
      var preset = presets.find(function (p) { return p.id === homePresetId; });
      if (preset) {
        presetCtx = preset.context || '';
        presetStudio = preset;
      }
      var studio = resolveStudioFromPrompt(prompt, presetStudio);
      if (prompt) {
        var full = presetCtx ? (prompt + ' — ' + presetCtx) : prompt;
        try {
          sessionStorage.setItem('yoy_home_prompt', full);
          sessionStorage.setItem('yoy_home_studio', studio);
        } catch (err) { /* ignore */ }
      }
      route(studio);
    }).catch(function () {
      if (prompt) {
        try { sessionStorage.setItem('yoy_home_prompt', prompt); } catch (e) {}
      }
      route(resolveStudioFromPrompt(prompt, null));
    });
  }

  function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function renderUsageWidget(mu) {
    var sub = document.getElementById('yai-stat-credit-usage');
    if (sub) {
      var pct = mu.percent || 0;
      sub.textContent = fmt(mu.used || 0) + ' used · ' + pct + '% monthly';
    }
    var el = document.getElementById('yai-home-usage');
    if (el) el.innerHTML = '';
  }

  function renderProjects(items) {
    var el = document.getElementById('yai-home-projects');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = emptyBlock('📁', 'No projects yet', 'Create your first project to organize AI work.', 'Start Project', 'projects', true);
      return;
    }
    el.innerHTML = items.map(projectChipHtml).join('');
  }

  function projectThumbHtml(p) {
    var thumb = p.thumbnail_url || '';
    if (thumb) {
      return '<div class="yai-project-chip-thumb"><img src="' + esc(thumb) + '" alt=""></div>';
    }
    return '<div class="yai-project-chip-thumb yai-project-chip-thumb--gold">Gold</div>';
  }

  function projectChipHtml(p) {
    return '<article class="yai-project-chip" data-project-open="' + esc(p.id) + '">' +
      projectThumbHtml(p) +
      '<div><strong>' + esc(p.title) + '</strong><span>' + fmt(p.asset_count || p.items || 0) + ' works · ' + relTime(p.updated_at) + '</span></div>' +
      '<button type="button" class="yai-btn--outline" data-project-open="' + esc(p.id) + '">열기</button>' +
    '</article>';
  }

  function projectCardHtml(p) {
    var vis = p.visibility === 'public' ? 'Public' : 'Private';
    var status = (p.status || 'active');
    return '<article class="yai-card yai-project-card" data-project-open="' + esc(p.id) + '">' +
      '<div class="yai-project-card-cover">' + (p.thumbnail_url
        ? '<img src="' + esc(p.thumbnail_url) + '" alt="">'
        : '<div class="yai-project-card-cover--gold">Gold Crystal</div>') +
      '</div>' +
      '<strong>' + esc(p.title) + '</strong>' +
      '<p>' + esc(p.description || 'No description') + '</p>' +
      '<span>' + esc(p.type || 'mixed') + ' · ' + esc(vis) + ' · ' + esc(status) +
        ' · ' + fmt(p.asset_count || p.items || 0) + ' assets · ' + relTime(p.updated_at || p.created_at) + '</span>' +
      '<div class="yai-project-actions">' +
      '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-project-open="' + esc(p.id) + '">Open</button>' +
      '<button type="button" class="yai-btn--outline yai-project-rename" data-id="' + esc(p.id) + '" data-title="' + esc(p.title) + '">Rename</button>' +
      '<button type="button" class="yai-btn--outline yai-project-delete" data-id="' + esc(p.id) + '">Delete</button>' +
      '</div></article>';
  }

  function showToast(message, isError) {
    var existing = document.getElementById('yai-toast');
    if (existing) existing.remove();
    var toast = document.createElement('div');
    toast.id = 'yai-toast';
    toast.className = 'yai-toast' + (isError ? ' yai-toast--error' : '');
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(function () { toast.classList.add('is-visible'); });
    setTimeout(function () {
      toast.classList.remove('is-visible');
      setTimeout(function () { if (toast.parentNode) toast.remove(); }, 300);
    }, 3200);
  }

  function categoryOptionsHtml(selected) {
    return PROJECT_CATEGORIES.map(function (c) {
      return '<option value="' + esc(c.id) + '"' + (c.id === selected ? ' selected' : '') + '>' + esc(c.label) + '</option>';
    }).join('');
  }

  function languageOptionsHtml(selected) {
    return PROJECT_LANGUAGES.map(function (c) {
      return '<option value="' + esc(c.id) + '"' + (c.id === (selected || 'ko') ? ' selected' : '') + '>' + esc(c.label) + '</option>';
    }).join('');
  }

  function setActiveProjectFromRecord(project) {
    if (!project || !project.id) return;
    currentProjectId = project.id;
    if (Y.YooYActiveProject && typeof Y.YooYActiveProject.set === 'function') {
      Y.YooYActiveProject.set({ id: project.id, name: project.title || project.name || 'Project' });
    }
  }

  function linkGalleryAssetToProject(projectId, galleryId) {
    if (!projectId || !galleryId) {
      return Promise.reject(new Error('프로젝트 또는 Gallery Asset이 없습니다.'));
    }
    if (!Core.projects || typeof Core.projects.addAsset !== 'function') {
      return Promise.reject(new Error('Projects API를 사용할 수 없습니다.'));
    }
    yoyProjectsLog('asset link started', projectId, galleryId);
    return Core.projects.addAsset(projectId, { gallery_id: galleryId }).then(function (res) {
      yoyProjectsLog('asset linked', galleryId);
      return res;
    });
  }

  function unlinkGalleryAssetFromProject(projectId, galleryId) {
    if (!projectId || !galleryId) {
      return Promise.reject(new Error('프로젝트 또는 Gallery Asset이 없습니다.'));
    }
    if (!Core.projects || typeof Core.projects.removeAsset !== 'function') {
      return Promise.reject(new Error('Projects API를 사용할 수 없습니다.'));
    }
    return Core.projects.removeAsset(projectId, galleryId);
  }

  function ensureProjectModal() {
    return yoyEnsureCreateDialog();
  }

  function openProjectModal(workIds) {
    if (!requireLogin()) {
      yoyProjectsLog('create dialog blocked — login required');
      return;
    }
    pendingCreateWorkIds = Array.isArray(workIds)
      ? workIds.filter(function (id) { return !!id; })
      : (workIds ? [workIds] : []);

    var modal = yoyEnsureCreateDialog();
    var form = modal.querySelector('#yai-project-form');
    if (!form) return;
    form.reset();
    var lang = form.querySelector('[name="language"]');
    if (lang) lang.value = 'ko';
    var vis = form.querySelector('[name="visibility"]');
    if (vis) vis.value = 'private';
    var cat = form.querySelector('[name="category"]');
    if (cat) cat.value = 'mixed';
    var err = modal.querySelector('#yai-project-form-error');
    if (err) { err.hidden = true; err.textContent = ''; }
    var hint = modal.querySelector('#yai-project-form-works-hint');
    if (hint) {
      if (pendingCreateWorkIds.length) {
        hint.textContent = '선택한 작품 ' + pendingCreateWorkIds.length + '개가 생성 후 프로젝트에 연결됩니다.';
        hint.hidden = false;
      } else {
        hint.textContent = '';
        hint.hidden = true;
      }
    }

    ignoreBackdropUntil = Date.now() + 400;
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open');
    document.body.classList.add('yai-modal-open');
    document.body.classList.add('yoy-project-dialog-open');
    yoyProjectsLog('create dialog opened');

    var titleInput = form.querySelector('[name="title"]');
    if (titleInput) {
      setTimeout(function () { titleInput.focus(); }, 0);
    }
  }

  function closeProjectModal() {
    window.YooYCloseProjectCreateDialog();
    pendingCreateWorkIds = [];
  }

  function submitProjectCreate(form) {
    if (!Core.projects || typeof Core.projects.create !== 'function') {
      showToast('Projects API를 사용할 수 없습니다.', true);
      return;
    }

    var errEl = document.getElementById('yai-project-form-error');
    var submitBtn = form.querySelector('[type="submit"]');
    var titleInput = form.querySelector('[name="title"]');
    var descInput = form.querySelector('[name="description"]');
    var catInput = form.querySelector('[name="category"]');
    var visInput = form.querySelector('[name="visibility"]');
    var langInput = form.querySelector('[name="language"]');
    var coverInput = form.querySelector('[name="cover"]');
    var title = ((titleInput && titleInput.value) || '').trim();
    var cover = ((coverInput && coverInput.value) || '').trim();
    var category = (catInput && catInput.value) || 'mixed';
    var workIds = pendingCreateWorkIds.slice();
    var payload = {
      name: title,
      title: title,
      description: ((descInput && descInput.value) || '').trim(),
      category: category,
      type: category,
      visibility: (visInput && visInput.value) || 'private',
      language: (langInput && langInput.value) || 'ko',
      cover: cover,
      thumbnail_url: cover
    };

    if (!payload.title) {
      if (errEl) { errEl.textContent = '프로젝트 이름을 입력해 주세요.'; errEl.hidden = false; }
      return;
    }
    if (payload.title.length > 120) {
      if (errEl) { errEl.textContent = '프로젝트 이름은 120자 이하여야 합니다.'; errEl.hidden = false; }
      return;
    }

    if (errEl) errEl.hidden = true;
    if (submitBtn) submitBtn.disabled = true;

    yoyProjectsLog('create request started', payload.name || payload.title);
    Core.projects.create(payload).then(function (res) {
      var created = (res.data && res.data.project) || res.project || null;
      if (!created || !created.id) {
        throw new Error('프로젝트가 생성되었지만 응답에 ID가 없습니다.');
      }
      yoyProjectsLog('project created', created.id);

      var linkChain = Promise.resolve();
      if (workIds.length) {
        linkChain = workIds.reduce(function (chain, galleryId) {
          return chain.then(function () {
            return linkGalleryAssetToProject(created.id, galleryId).catch(function (linkErr) {
              if (window.console && console.warn) {
                console.warn('[YooY Projects] asset link failed', galleryId, linkErr);
              }
            });
          });
        }, Promise.resolve());
      }

      return linkChain.then(function () {
        pendingCreateWorkIds = [];
        closeProjectModal();
        showToast(workIds.length
          ? '프로젝트가 생성되고 Asset이 연결되었습니다.'
          : '프로젝트가 생성되었습니다.');
        loaded.projects = false;
        loadProjects();
        refreshHomeProjects();
        setActiveProjectFromRecord(created);
        openProjectDetail(created.id, workIds.length ? 'assets' : 'overview');
      });
    }).catch(function (err) {
      var msg = err.message || '프로젝트 생성에 실패했습니다.';
      if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
      showToast(msg, true);
    }).finally(function () {
      if (submitBtn) submitBtn.disabled = false;
    });
  }

  function saveGalleryItemToProject(galleryId) {
    if (!galleryId || !requireLogin()) return;
    if (!Core.projects || typeof Core.projects.list !== 'function') {
      showToast('Projects API를 사용할 수 없습니다.', true);
      return;
    }
    Core.projects.list().then(function (res) {
      var projects = (res.data && res.data.projects) || [];
      if (!projects.length) {
        openProjectModal([galleryId]);
        return;
      }
      openProjectPicker(galleryId);
    }).catch(function (err) {
      showToast(err.message || '프로젝트 목록을 불러오지 못했습니다.', true);
    });
  }

  function refreshHomeProjects() {
    Core.dashboard().then(function (res) {
      var d = res.data || {};
      renderProjects(d.projects || []);
      setText('yai-stat-projects', fmt(d.project_count || 0));
    }).catch(function () {
      Core.projects.list().then(function (res) {
        var items = (res.data && res.data.projects) || [];
        renderProjects(items.slice(0, 5));
        setText('yai-stat-projects', fmt(items.length));
      }).catch(function () {});
    });
  }

  function renderWorks(items) {
    var el = document.getElementById('yai-home-works');
    if (!el) return;
    el.className = 'yai-block-body yai-works-grid yai-works-grid--showcase';
    items = filterFeedWorks(items);
    if (!items.length) {
      if (!isLoggedIn()) {
        el.innerHTML = '<div class="yai-empty-card"><div class="yai-empty-card-icon" aria-hidden="true">✨</div>' +
          '<h3>작품을 불러오는 중입니다</h3><p>Official Showcase와 Community 작품이 곧 표시됩니다.</p>' +
          '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-yai-free-start>무료로 시작하기</button></div>';
        return;
      }
      el.innerHTML = emptyBlock('🖼', 'No works yet', 'Generate or import your first asset to fill the gallery.', 'Open Image Studio', 'image');
      return;
    }
    el.innerHTML = items.slice(0, 12).map(function (w) {
      return isPlatformFeedItem(w)
        ? sectionCardHtml(w, { card_ratio: 'auto', text_mode: 'below', column_count: 4 })
        : workCardHtml(w, 'showcase');
    }).join('');
  }

  var STUDIO_ROUTE_MAP = {
    'video-studio': 'video',
    'image-studio': 'image',
    'music-studio': 'music',
    'voice-studio': 'voice',
    'avatar-studio': 'avatar',
    'writing-studio': 'writing',
    'translator-studio': 'translator'
  };

  function routeToStudioFromWork(payload, fallbackType) {
    var studio = (payload && payload.studio) || '';
    var routeName = STUDIO_ROUTE_MAP[studio] || (payload && payload.type) || fallbackType || 'image';
    if (Y.YooYStudioRoute) {
      Y.YooYStudioRoute(routeName);
    }
  }

  function closeAllWorkMenus() {
    document.querySelectorAll('.yai-work-menu').forEach(function (menu) {
      menu.hidden = true;
    });
    document.querySelectorAll('.yai-work-card.is-menu-open').forEach(function (card) {
      card.classList.remove('is-menu-open');
    });
  }

  function refreshWorkViews() {
    loadHome();
    if (Y.YooYGallery && typeof Y.YooYGallery.reload === 'function') {
      Y.YooYGallery.reload();
    }
    if (loaded['project-detail']) loadProjectDetail();
  }

  function openWorkDetail(workId) {
    if (!workId) return;
    if (Y.YooYGallery && typeof Y.YooYGallery.openDetail === 'function') {
      Y.YooYGallery.openDetail(workId);
      return;
    }
    if (!Core.gallery || typeof Core.gallery.item !== 'function') {
      showToast('갤러리를 불러오지 못했습니다. 페이지를 새로고침해 주세요.', true);
      return;
    }
    Core.gallery.item(workId).then(function (res) {
      var item = (res.data && res.data.item) || null;
      if (!item) {
        showToast('작품을 찾을 수 없습니다.', true);
        return;
      }
      openWorkPreview(item);
    }).catch(function (err) {
      showToast(err.message || '상세 정보를 불러올 수 없습니다.', true);
    });
  }

  function handleWorkAction(action, workId) {
    if (!workId) return;

    if (action === 'open') {
      openWorkDetail(workId);
      return;
    }

    if (action === 'marketplace') {
      if (!requireLogin()) return;
      if (Y.YooYGallery && typeof Y.YooYGallery.openMarketplace === 'function') {
        Y.YooYGallery.openMarketplace(workId);
      } else {
        showToast('Marketplace 등록 기능을 불러오지 못했습니다.', true);
      }
      return;
    }

    if (['regenerate', 'download', 'share', 'delete', 'project', 'project-remove', 'project-cover'].indexOf(action) !== -1 && !requireLogin()) {
      return;
    }

    if (!Core.gallery || typeof Core.gallery.remove !== 'function') {
      showToast('갤러리 API를 불러오지 못했습니다. 페이지를 새로고침해 주세요.', true);
      return;
    }

    if (action === 'regenerate') {
      Core.gallery.regenerate(workId).then(function (res) {
        var payload = res.data || {};
        try {
          sessionStorage.setItem('yoy_regenerate', JSON.stringify(payload));
          if (payload.reference_assets && payload.reference_assets.length) {
            sessionStorage.setItem('yoy_reference_asset', JSON.stringify(payload.reference_assets[0]));
          }
        } catch (e) { /* ignore */ }
        routeToStudioFromWork(payload, 'image');
        showToast('프롬프트를 불러왔습니다.');
      }).catch(function (err) { showToast(err.message || '재사용에 실패했습니다.', true); });
      return;
    }
    if (action === 'download') {
      Core.gallery.download(workId).then(function (res) {
        var info = res.data || {};
        if (info.url) {
          var a = document.createElement('a');
          a.href = info.url;
          a.download = info.filename || 'download';
          a.target = '_blank';
          a.click();
          showToast('다운로드를 시작합니다.');
        } else {
          showToast('다운로드 URL을 찾을 수 없습니다.', true);
        }
      }).catch(function (err) { showToast(err.message || '다운로드에 실패했습니다.', true); });
      return;
    }
    if (action === 'share') {
      Core.gallery.share(workId).then(function (res) {
        var data = res.data || {};
        var copy = data.url || data.text || '';
        if (!copy) {
          showToast('공유할 내용을 만들 수 없습니다.', true);
          return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(copy).then(function () {
            showToast(data.text && !data.url ? '번역문을 복사했습니다.' : '공유 링크를 복사했습니다.');
          }).catch(function () { showToast(copy); });
        } else {
          showToast(copy);
        }
      }).catch(function (err) { showToast(err.message || '공유에 실패했습니다.', true); });
      return;
    }
    if (action === 'project') {
      saveGalleryItemToProject(workId);
      return;
    }
    if (action === 'project-remove') {
      var removeFrom = currentProjectId ||
        (Y.YooYActiveProject && Y.YooYActiveProject.get() && Y.YooYActiveProject.get().id) || '';
      if (!removeFrom) {
        showToast('연결된 프로젝트를 찾을 수 없습니다.', true);
        return;
      }
      unlinkGalleryAssetFromProject(removeFrom, workId).then(function () {
        showToast('프로젝트에서 제거했습니다.');
        refreshWorkViews();
        refreshHomeProjects();
        if (loaded['project-detail']) loadProjectDetail();
      }).catch(function (err) { showToast(err.message || '제거에 실패했습니다.', true); });
      return;
    }
    if (action === 'project-cover') {
      if (!currentProjectId) return;
      Core.gallery.item(workId).then(function (res) {
        var item = (res.data && res.data.item) || {};
        var thumb = item.thumbnail_url || item.thumbnail || item.image_url || item.output_url || item.asset_url || '';
        return Core.projects.update(currentProjectId, {
          cover_asset_id: workId,
          thumbnail_url: thumb
        });
      }).then(function () {
        showToast('프로젝트 커버를 변경했습니다.');
        loadProjectDetail();
        refreshHomeProjects();
      }).catch(function (err) { showToast(err.message || '커버 변경에 실패했습니다.', true); });
      return;
    }
    if (action === 'delete') {
      if (!confirm('이 작품을 삭제하시겠습니까?')) return;
      Core.gallery.remove(workId).then(function () {
        showToast('작품을 삭제했습니다.');
        if (Core.notifyGalleryUpdated) Core.notifyGalleryUpdated();
        refreshWorkViews();
      }).catch(function (err) { showToast(err.message || '삭제에 실패했습니다.', true); });
    }
  }

  function openWorkPreview(work) {
    if (!work) return;
    var mediaUrl = window.YooYGalleryImage
      ? window.YooYGalleryImage.pickUrl(work, 'full')
      : (work.full_url || work.original_url || work.url || work.asset_url || work.image_url || work.output_url || '');
    if (!mediaUrl && work.type !== 'writing' && work.type !== 'translation') return;
    var overlay = document.getElementById('yai-work-preview');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'yai-work-preview';
      overlay.className = 'yai-overlay';
      overlay.innerHTML = '<div class="yai-modal"><button type="button" class="yai-modal-close" data-yai-close-preview>×</button><div id="yai-work-preview-body"></div></div>';
      document.body.appendChild(overlay);
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay || e.target.closest('[data-yai-close-preview]')) overlay.hidden = true;
      });
    }
    var body = document.getElementById('yai-work-preview-body');
    if (work.type === 'video' || work.type === 'avatar') {
      body.innerHTML = '<video src="' + esc(mediaUrl) + '" controls autoplay style="max-width:100%"></video>';
    } else if (work.type === 'music' || work.type === 'voice') {
      body.innerHTML = '<audio src="' + esc(mediaUrl) + '" controls autoplay style="width:100%"></audio>';
    } else if (work.type === 'writing') {
      body.innerHTML = '<div class="yai-card" style="padding:16px">' + esc(work.user_prompt || work.prompt || '') + '</div>';
    } else if (work.type === 'translation') {
      var tr = work.translated_text || (work.meta && work.meta.translated_text) || '';
      var src = work.user_prompt || work.prompt || '';
      body.innerHTML = '<div class="yai-card" style="padding:16px"><p class="yai-muted">' + esc(src) +
        '</p><hr><p><strong>' + esc(tr) + '</strong></p></div>';
    } else {
      body.innerHTML = galleryImg(work, { size: 'full', lazy: false, className: 'yai-gallery-img yai-gallery-img--preview' })
        || ('<img src="' + esc(mediaUrl) + '" alt="" class="yai-gallery-img yai-gallery-img--preview">');
    }
    overlay.hidden = false;
  }

  function activityGuidance(code) {
    var map = {
      provider_not_tested: 'AI 공급업체 테스트가 완료되지 않았습니다. Operations Center에서 Test Connection을 실행하세요.',
      provider_not_configured: 'AI 공급업체 API Key가 설정되지 않았습니다.',
      insufficient_provider_credit: 'AI 공급업체 계정의 크레딧이 부족합니다. 사용자 크레딧과는 별도입니다.',
      invalid_size: '선택한 이미지 크기가 해당 모델에서 지원되지 않습니다. 자동 크기로 다시 시도하세요.',
      no_output_asset: '이미지는 생성되었지만 저장 단계에서 실패했습니다. 다시 저장을 시도하세요.',
      poll_timeout: '생성 시간이 초과되었습니다. 백그라운드 작업 또는 Provider 상태를 확인하세요.'
    };
    return map[code] || '생성 중 오류가 발생했습니다. 프롬프트를 수정하거나 다른 Provider로 다시 시도하세요.';
  }

  function activityStatusBadge(status) {
    var k = String(status || '').toLowerCase();
    if (k === 'completed' || k === 'done' || k === 'success' || k === 'succeeded') {
      return '<span class="yai-activity-badge yai-activity-badge--done">완료</span>';
    }
    if (k === 'failed' || k === 'error') {
      return '<span class="yai-activity-badge yai-activity-badge--fail">실패</span>';
    }
    if (k === 'running' || k === 'processing' || k === 'queued' || k === 'pending') {
      return '<span class="yai-activity-badge yai-activity-badge--running"><span class="yai-activity-spinner"></span>진행</span>';
    }
    return statusTag(status);
  }

  function normalizeActivityStatus(status) {
    return String(status || '').toLowerCase();
  }

  function isActivityCompleted(item) {
    var s = normalizeActivityStatus(item.status);
    return s === 'completed' || s === 'done' || s === 'success' || s === 'succeeded';
  }

  function isActivityFailed(item) {
    var s = normalizeActivityStatus(item.status);
    return s === 'failed' || s === 'error';
  }

  function isActivityRunning(item) {
    var s = normalizeActivityStatus(item.status);
    return s === 'running' || s === 'processing' || s === 'queued' || s === 'pending';
  }

  function openRunningActivity(item) {
    try {
      sessionStorage.setItem('yoy_resume_job', JSON.stringify({
        job_id: item.job_id,
        provider: item.provider || 'auto',
        studio: item.target_route || item.type || 'image'
      }));
      if (item.prompt) {
        sessionStorage.setItem('yoy_regenerate', JSON.stringify({
          studio: item.studio || ((item.target_route || 'image') + '-studio'),
          type: item.type,
          prompt: item.prompt,
          provider: 'auto',
          settings: { default_provider: 'auto' }
        }));
      }
    } catch (e) { /* ignore */ }
    route(item.target_route || 'image');
  }

  function handleActivityClick(item) {
    if (!item) return;
    if (isActivityFailed(item)) {
      openFailureResolver(item);
      return;
    }
    if (isActivityCompleted(item)) {
      if (item.work_id) {
        openWorkDetail(item.work_id);
        return;
      }
      route(item.target_route || 'image');
      return;
    }
    if (isActivityRunning(item)) {
      openRunningActivity(item);
      return;
    }
    route(item.target_route || 'image');
  }

  function ensureFailureDrawer() {
    var drawer = document.getElementById('yai-failure-drawer');
    if (!drawer) {
      drawer = document.createElement('aside');
      drawer.id = 'yai-failure-drawer';
      drawer.className = 'yai-failure-drawer';
      drawer.hidden = true;
      drawer.innerHTML = '<div class="yai-failure-drawer__backdrop" data-failure-close></div>' +
        '<div class="yai-failure-drawer__panel" role="dialog" aria-labelledby="yai-failure-title">' +
        '<header class="yai-failure-drawer__head"><h2 id="yai-failure-title">실패 작업 해결</h2>' +
        '<button type="button" class="yai-modal-close" data-failure-close aria-label="닫기">×</button></header>' +
        '<div class="yai-failure-drawer__body" id="yai-failure-body"></div>' +
        '<footer class="yai-failure-drawer__foot" id="yai-failure-foot"></footer></div>';
      document.body.appendChild(drawer);
      drawer.addEventListener('click', function (e) {
        if (e.target.closest('[data-failure-close]')) closeFailureResolver();
      });
    }
    return drawer;
  }

  function closeFailureResolver() {
    var drawer = document.getElementById('yai-failure-drawer');
    if (drawer) drawer.hidden = true;
    document.body.classList.remove('yai-failure-open');
  }

  function copyActivityLog(item) {
    var text = [
      'Job: ' + (item.job_id || ''),
      'Type: ' + (item.type || ''),
      'Provider: ' + (item.provider || ''),
      'Model: ' + (item.model || ''),
      'Error: ' + (item.error_code || ''),
      'Message: ' + (item.error_message || ''),
      'Raw: ' + (item.raw_error || '')
    ].join('\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        showToast('실패 로그를 복사했습니다.');
      }).catch(function () { showToast(text); });
    } else {
      showToast(text);
    }
  }

  function retryActivityJob(item, providerOverride) {
    var routeName = item.target_route || 'image';
    var payload = {
      studio: item.studio || (routeName + '-studio'),
      type: item.type || routeName,
      prompt: item.prompt || '',
      provider: providerOverride || 'auto',
      settings: { default_provider: providerOverride || 'auto' }
    };
    try {
      sessionStorage.setItem('yoy_regenerate', JSON.stringify(payload));
      if (item.job_id) {
        sessionStorage.setItem('yoy_resume_job', JSON.stringify({ job_id: item.job_id, studio: routeName }));
      }
    } catch (e) { /* ignore */ }
    closeFailureResolver();
    route(routeName);
    showToast('재시도를 위해 Studio를 열었습니다.');
  }

  function deleteActivityJob(item) {
    if (!item || !item.job_id) return;
    if (!window.confirm('이 실패 작업을 삭제할까요?')) return;
    if (!Core.deleteJob) {
      showToast('삭제 API를 사용할 수 없습니다.', true);
      return;
    }
    Core.deleteJob(item.job_id).then(function () {
      homeActivityCache = homeActivityCache.filter(function (j) {
        return (j.job_id || j.id) !== item.job_id;
      });
      renderJobs(homeActivityCache);
      closeFailureResolver();
      showToast('실패 작업을 삭제했습니다.');
    }).catch(function (err) {
      showToast(err.message || '삭제에 실패했습니다.', true);
    });
  }

  function openFailureResolver(item) {
    var drawer = ensureFailureDrawer();
    var body = document.getElementById('yai-failure-body');
    var foot = document.getElementById('yai-failure-foot');
    if (!body || !foot) return;

    var guidance = activityGuidance(item.error_code);
    var jobId = esc(item.job_id || item.id || '');

    body.innerHTML = '<div class="yai-failure-meta">' +
      '<div class="yai-failure-row"><span>작업 타입</span><strong>' + esc(typeBadgeLabel(item.type)) + '</strong></div>' +
      '<div class="yai-failure-row"><span>Provider</span><strong>' + esc(item.provider || '—') + '</strong></div>' +
      '<div class="yai-failure-row"><span>Model</span><strong>' + esc(item.model || '—') + '</strong></div>' +
      '<div class="yai-failure-row"><span>발생 시간</span><strong>' + relTime(item.updated_at || item.created_at) + '</strong></div>' +
      '</div>' +
      '<div class="yai-failure-prompt"><label>원본 Prompt</label><p>' + esc(item.prompt || '—') + '</p></div>' +
      '<div class="yai-failure-alert yai-failure-alert--error"><strong>' + esc(item.error_code || 'generation_failed') + '</strong>' +
      '<p>' + esc(guidance) + '</p>' +
      (item.error_message ? '<p class="yai-failure-raw">' + esc(item.error_message) + '</p>' : '') +
      (item.raw_error ? '<pre class="yai-failure-raw">' + esc(String(item.raw_error).slice(0, 600)) + '</pre>' : '') +
      '</div>' +
      '<div id="yai-failure-credits" class="yai-failure-credits"><p>크레딧 상태 확인 중…</p></div>';

    foot.innerHTML =
      '<button type="button" class="yai-btn yai-btn--gold" data-failure-action="retry" data-failure-job="' + jobId + '">Smart Auto 재시도</button>' +
      '<button type="button" class="yai-btn--outline" data-failure-action="retry-provider" data-provider="' + esc(item.provider || 'auto') + '" data-failure-job="' + jobId + '">같은 Provider 재시도</button>' +
      '<button type="button" class="yai-btn--outline" data-failure-action="copy" data-failure-job="' + jobId + '">로그 복사</button>' +
      '<button type="button" class="yai-btn--outline yai-btn--danger" data-failure-action="delete" data-failure-job="' + jobId + '">삭제</button>' +
      (Core.config.isAdmin
        ? '<button type="button" class="yai-btn--outline" data-failure-action="admin" data-failure-job="' + jobId + '">Provider 설정</button>'
        : '<button type="button" class="yai-btn--outline" data-failure-action="contact" data-failure-job="' + jobId + '">관리자에게 문의</button>');

    drawer.hidden = false;
    document.body.classList.add('yai-failure-open');

    Core.dashboard().then(function (res) {
      var d = res.data || {};
      var cr = d.credits || {};
      var creditsEl = document.getElementById('yai-failure-credits');
      if (!creditsEl) return;
      var userCredit = cr.unlimited ? '무제한 (∞)' : fmt(cr.balance) + ' Credits';
      var billingNote = item.error_code === 'insufficient_provider_credit'
        ? 'AI 공급업체 계정 크레딧 부족 (사용자 크레딧과 별도)'
        : '공급업체 계정 상태는 Operations Center에서 확인하세요.';
      creditsEl.innerHTML =
        '<div class="yai-failure-row"><span>사용자 크레딧</span><strong>' + esc(userCredit) + '</strong></div>' +
        '<div class="yai-failure-row"><span>Provider Billing</span><strong>' + esc(billingNote) + '</strong></div>';
    }).catch(function () {});
  }

  function renderJobs(items) {
    var el = document.getElementById('yai-home-jobs');
    if (!el) return;
    homeActivityCache = (items || []).slice(0, 7);
    if (!homeActivityCache.length) {
      el.innerHTML = emptyBlock('⚡', 'No activity yet', '생성 기록이 여기에 표시됩니다.', 'Create Now', 'image');
      return;
    }
    el.innerHTML = homeActivityCache.map(function (j) {
      var aid = esc(j.job_id || j.id || '');
      var label = esc(j.title || typeBadgeLabel(j.type || 'Generation'));
      var time = relTime(j.updated_at || j.created_at);
      return '<button type="button" class="yai-activity-item" data-activity-id="' + aid + '">' +
        activityStatusBadge(j.status) +
        '<div class="yai-activity-item__body"><strong>' + label + '</strong>' +
        '<span>' + esc(typeBadgeLabel(j.type)) + ' · ' + time + '</span></div>' +
        '<span class="yai-activity-item__chev" aria-hidden="true">›</span></button>';
    }).join('');
  }

  function renderAnnouncements(items) {
    var el = document.getElementById('yai-home-announcements');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = emptyBlock('📢', 'No announcements', 'Platform updates and release notes will appear here.', 'Settings', 'settings');
      return;
    }
    el.innerHTML = items.map(function (a) {
      var badge = a.is_new ? '<span class="yai-tag yai-tag--new">NEW</span> ' : '';
      return '<div class="yai-row" style="grid-template-columns:1fr auto"><div><strong>' + badge + esc(a.title || 'Update') + '</strong><span>' + esc(a.body || a.message || '') + '</span></div></div>';
    }).join('');
  }

  function renderShowcase(items) {
    var el = document.getElementById('yai-showcase');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = emptyBlock('✦', 'Official showcase empty', 'Curated platform highlights will appear when configured.', 'Explore Community', 'community');
      return;
    }
    el.innerHTML = items.map(function (it) { return showcaseCardHtml(it); }).join('');
  }

  function renderHomeEmpty() {
    if (!isLoggedIn()) {
      loadHome();
      return;
    }
    setText('yai-stat-credits', '—');
    setText('yai-stat-projects', '0');
    setText('yai-stat-works', '0');
    setText('yai-stat-likes', '0');
    setText('yai-top-credits', 'Login to start creating');
    var head = document.querySelector('.yai-home-head h1');
    if (head && !isLoggedIn()) {
      head.textContent = 'Login to start creating ✨';
    }
    renderWorks([]);
    renderJobs([]);
    renderAnnouncements([]);
    renderShowcase([]);
    renderHomeSections([]);
    renderProjects([]);
    loadHomeRecommendations();
    renderUsageWidget({ used: 0, limit: 0, percent: 0 });
  }

  function openProjectDetail(projectId, tab) {
    if (!projectId || !requireLogin()) return;
    currentProjectId = projectId;
    projectDetailFilter = 'all';
    workspaceTab = tab || 'overview';
    workspaceCache = { project: null, works: [] };
    loaded['project-detail'] = false;
    if (Y.YooYActiveProject && typeof Y.YooYActiveProject.set === 'function') {
      var existing = Y.YooYActiveProject.get();
      if (!existing || existing.id !== projectId) {
        Y.YooYActiveProject.set({ id: projectId, name: 'Project' });
      }
    }
    route('project-detail');
  }

  function renderWorkspaceTabs() {
    var tabsEl = document.getElementById('yai-workspace-tabs');
    if (!tabsEl) return;
    tabsEl.innerHTML = WORKSPACE_TABS.map(function (t) {
      var active = workspaceTab === t.id ? ' is-active' : '';
      var reserved = t.reserved ? ' yai-workspace-tab--reserved' : '';
      return '<button type="button" class="yai-workspace-tab' + active + reserved + '" data-workspace-tab="' + esc(t.id) + '">' +
        esc(t.label) + (t.reserved ? ' <span class="yai-tab-badge">Soon</span>' : '') +
      '</button>';
    }).join('');
  }

  function studioRouteForType(type) {
    var map = {
      image: 'image', video: 'video', music: 'music', voice: 'voice',
      avatar: 'avatar', writing: 'writing', translation: 'translator'
    };
    return map[type] || 'image';
  }

  function workspaceAssetCardHtml(w) {
    var id = w.id || '';
    var studio = studioRouteForType(w.type);
    return '<article class="yai-card yai-workspace-asset-card" data-work-id="' + esc(id) + '">' +
      '<strong>' + esc(w.title || 'Untitled') + '</strong>' +
      '<span class="yai-muted">' + esc(typeBadgeLabel(w.type || 'image')) + ' · ' + relTime(w.created_at || w.updated_at) + '</span>' +
      '<div class="yai-project-actions">' +
        '<button type="button" class="yai-btn--outline yai-btn--sm" data-ws-asset-action="open" data-work-id="' + esc(id) + '">Open</button>' +
        '<button type="button" class="yai-btn--outline yai-btn--sm" data-ws-asset-action="preview" data-work-id="' + esc(id) + '">Preview</button>' +
        '<button type="button" class="yai-btn--outline yai-btn--sm" data-ws-asset-action="remove" data-work-id="' + esc(id) + '">Remove from Project</button>' +
        '<button type="button" class="yai-btn--outline yai-btn--sm" data-ws-asset-action="studio" data-work-id="' + esc(id) + '" data-studio-route="' + esc(studio) + '">Go to Source Studio</button>' +
      '</div></article>';
  }

  function renderWorkspaceOverview(project, works) {
    var recent = (works || []).slice().sort(function (a, b) {
      return String(b.created_at || '').localeCompare(String(a.created_at || ''));
    }).slice(0, 5);
    var cat = project.category || project.type || 'mixed';
    var launchers = [
      { route: 'image', label: 'Image' },
      { route: 'video', label: 'Video' },
      { route: 'music', label: 'Music' },
      { route: 'translator', label: 'Translator' },
      { route: 'writing', label: 'Writing' },
      { route: 'voice', label: 'Voice' },
      { route: 'avatar', label: 'Avatar' }
    ];
    return '<div class="yai-workspace-overview">' +
      '<div class="yai-workspace-meta-grid">' +
        '<div><span class="yai-muted">Name</span><strong>' + esc(project.title || '') + '</strong></div>' +
        '<div><span class="yai-muted">Category</span><strong>' + esc(cat) + '</strong></div>' +
        '<div><span class="yai-muted">Visibility</span><strong>' + esc(project.visibility || 'private') + '</strong></div>' +
        '<div><span class="yai-muted">Language</span><strong>' + esc(project.language || 'ko') + '</strong></div>' +
        '<div><span class="yai-muted">Assets</span><strong>' + fmt(project.asset_count || works.length || 0) + '</strong></div>' +
        '<div><span class="yai-muted">Created</span><strong>' + esc(relTime(project.created_at)) + '</strong></div>' +
        '<div><span class="yai-muted">Updated</span><strong>' + esc(relTime(project.updated_at)) + '</strong></div>' +
      '</div>' +
      '<p class="yai-workspace-desc">' + esc(project.description || '설명이 없습니다.') + '</p>' +
      (project.thumbnail_url
        ? '<div class="yai-project-detail-cover"><img src="' + esc(project.thumbnail_url) + '" alt=""></div>'
        : '') +
      '<section class="yai-workspace-section"><h3>Studio Launcher</h3>' +
        '<div class="yai-studio-launcher">' +
          launchers.map(function (s) {
            return '<button type="button" class="yai-btn yai-btn--outline" data-workspace-studio="' + esc(s.route) + '">' + esc(s.label) + '</button>';
          }).join('') +
        '</div></section>' +
      '<section class="yai-workspace-section"><h3>최근 작업</h3>' +
        (recent.length
          ? '<div class="yai-workspace-recent">' + recent.map(function (w) {
              return '<button type="button" class="yai-text-btn" data-ws-asset-action="open" data-work-id="' + esc(w.id) + '">' +
                esc(w.title || 'Untitled') + ' · ' + esc(typeBadgeLabel(w.type || '')) + '</button>';
            }).join('')
          : '<p class="yai-muted">아직 연결된 작품이 없습니다.</p>') +
      '</section></div>';
  }

  function renderWorkspaceAssets(works) {
    var types = ['all', 'image', 'video', 'music', 'voice', 'avatar', 'writing', 'translation'];
    var filtered = projectDetailFilter === 'all'
      ? works
      : works.filter(function (w) { return w.type === projectDetailFilter; });
    var chips = types.map(function (t) {
      var active = projectDetailFilter === t ? ' is-active' : '';
      return '<button type="button" class="yai-filter-chip' + active + '" data-project-filter="' + t + '">' +
        (t === 'all' ? 'All' : typeBadgeLabel(t)) + '</button>';
    }).join('');
    return '<div class="yai-workspace-assets">' +
      '<div class="yai-project-detail-filters">' + chips + '</div>' +
      '<div class="yai-project-detail-toolbar">' +
        '<button type="button" class="yai-btn yai-btn--gold" id="yai-project-add-works">Gallery에서 추가</button>' +
        '<span class="yai-muted">' + fmt(filtered.length) + ' assets · gallery_id 참조</span>' +
      '</div>' +
      (filtered.length
        ? '<div class="yai-workspace-asset-list">' + filtered.map(workspaceAssetCardHtml).join('') + '</div>'
        : emptyBlock('', 'No assets', 'Gallery 작품을 이 프로젝트에 연결하세요. Asset 본문은 복제되지 않습니다.', 'Gallery', 'works')) +
      '</div>';
  }

  function renderWorkspaceHistory(works) {
    var sorted = (works || []).slice().sort(function (a, b) {
      return String(b.created_at || '').localeCompare(String(a.created_at || ''));
    });
    if (!sorted.length) {
      return '<div class="yai-empty"><h3>History</h3><p>이 프로젝트에 연결된 Gallery 활동이 없습니다.</p></div>';
    }
    return '<div class="yai-workspace-history"><p class="yai-muted">Gallery Store · project_id filter · created_at 정렬</p>' +
      '<ul class="yai-workspace-history-list">' +
      sorted.map(function (w) {
        var studio = studioRouteForType(w.type);
        var credits = (w.credits_used != null ? w.credits_used : (w.credits != null ? w.credits : null));
        return '<li class="yai-workspace-history-item">' +
          '<div class="yai-workspace-history-main">' +
            '<button type="button" class="yai-text-btn" data-ws-asset-action="open" data-work-id="' + esc(w.id) + '">' +
              esc(w.title || 'Untitled') + '</button>' +
            '<span class="yai-muted">' + esc(typeBadgeLabel(w.type || '')) +
              ' · ' + esc(w.provider || '—') +
              ' · ' + esc(relTime(w.created_at)) +
              (credits != null ? ' · ' + fmt(credits) + ' credits' : '') +
            '</span>' +
          '</div>' +
          '<button type="button" class="yai-btn--outline yai-btn--sm" data-ws-asset-action="studio" data-work-id="' +
            esc(w.id) + '" data-studio-route="' + esc(studio) + '">Source Studio</button>' +
        '</li>';
      }).join('') +
      '</ul></div>';
  }

  function renderWorkspaceNotes(project) {
    return '<form id="yai-workspace-notes-form" class="yai-workspace-notes">' +
      '<label class="yai-field"><span>Project Notes</span>' +
        '<textarea name="notes" rows="10" maxlength="5000" placeholder="프로젝트 메모를 남겨 보세요.">' +
          esc(project.notes || '') +
        '</textarea></label>' +
      '<p class="yai-muted" id="yai-workspace-notes-meta">마지막 수정: ' +
        esc(project.notes_updated_at ? relTime(project.notes_updated_at) : '없음') + '</p>' +
      '<p class="yai-modal-error" id="yai-workspace-notes-error" hidden></p>' +
      '<button type="submit" class="yai-btn yai-btn--gold" id="yai-workspace-notes-save">Save Notes</button>' +
      '</form>';
  }

  function renderWorkspaceSettings(project) {
    var cat = project.category || project.type || 'mixed';
    return '<form id="yai-workspace-settings-form" class="yai-workspace-settings">' +
      '<label class="yai-field"><span>Project Name</span><input type="text" name="title" required maxlength="120" value="' + esc(project.title || '') + '"></label>' +
      '<label class="yai-field"><span>Description</span><textarea name="description" rows="3" maxlength="500">' + esc(project.description || '') + '</textarea></label>' +
      '<label class="yai-field"><span>Category</span><select name="category">' + categoryOptionsHtml(cat) + '</select></label>' +
      '<label class="yai-field"><span>Visibility</span><select name="visibility">' +
        '<option value="private"' + (project.visibility !== 'public' ? ' selected' : '') + '>Private</option>' +
        '<option value="public"' + (project.visibility === 'public' ? ' selected' : '') + '>Public</option>' +
      '</select></label>' +
      '<label class="yai-field"><span>Language</span><select name="language">' + languageOptionsHtml(project.language || 'ko') + '</select></label>' +
      '<label class="yai-field"><span>Cover URL</span><input type="url" name="thumbnail_url" maxlength="500" value="' + esc(project.thumbnail_url || '') + '" placeholder="optional"></label>' +
      '<div class="yai-workspace-settings-actions">' +
        '<button type="submit" class="yai-btn yai-btn--gold">Save Settings</button>' +
        '<button type="button" class="yai-btn--outline yai-btn--danger" id="yai-workspace-settings-delete">Delete Project</button>' +
      '</div></form>';
  }

  function renderWorkspaceReserved(tabId) {
    var label = tabId === 'members' ? 'Members' : 'Timeline';
    return '<div class="yai-empty yai-empty--reserved">' +
      '<h3>' + esc(label) + ' — 준비 중</h3>' +
      '<p>이 탭은 예약되어 있습니다. 현재 버전에서는 기능을 제공하지 않습니다.</p>' +
      '</div>';
  }

  function renderWorkspaceLauncher() {
    var launchers = [
      { route: 'image', label: 'Image' },
      { route: 'video', label: 'Video' },
      { route: 'writing', label: 'Writing' },
      { route: 'music', label: 'Music' },
      { route: 'voice', label: 'Voice' },
      { route: 'avatar', label: 'Avatar' },
      { route: 'translator', label: 'Translator' },
      { route: 'assistant', label: 'AI Assistant' }
    ];
    return '<div class="yai-workspace-launcher">' +
      '<p class="yai-muted">Create 영역 — Active Project 컨텍스트로 Studio를 엽니다. Studio는 메인이 아니라 실행기입니다.</p>' +
      '<div class="yai-workspace-launcher-grid">' +
        launchers.map(function (s) {
          return '<button type="button" class="yai-btn yai-btn--outline" data-workspace-studio="' + esc(s.route) + '">' + esc(s.label) + '</button>';
        }).join('') +
      '</div></div>';
  }

  function renderWorkspaceAssistant() {
    return '<div class="yai-workspace-assistant" id="yai-workspace-assistant-root"></div>';
  }

  function paintWorkspacePanel() {
    var panel = document.getElementById('yai-workspace-panel');
    if (!panel) return;
    var project = workspaceCache.project || {};
    var works = workspaceCache.works || [];
    renderWorkspaceTabs();

    if (workspaceTab === 'overview') panel.innerHTML = renderWorkspaceOverview(project, works);
    else if (workspaceTab === 'assets') panel.innerHTML = renderWorkspaceAssets(works);
    else if (workspaceTab === 'history') panel.innerHTML = renderWorkspaceHistory(works);
    else if (workspaceTab === 'notes') panel.innerHTML = renderWorkspaceNotes(project);
    else if (workspaceTab === 'assistant') panel.innerHTML = renderWorkspaceAssistant();
    else if (workspaceTab === 'launcher') panel.innerHTML = renderWorkspaceLauncher();
    else if (workspaceTab === 'settings') panel.innerHTML = renderWorkspaceSettings(project);
    else panel.innerHTML = renderWorkspaceOverview(project, works);

    if (workspaceTab === 'assistant') {
      var root = document.getElementById('yai-workspace-assistant-root');
      if (root && window.YooYAIAssistant && typeof window.YooYAIAssistant.mount === 'function') {
        root.dataset.mounted = '';
        window.YooYAIAssistant.mount(root);
      } else if (root) {
        root.innerHTML = '<p class="yai-muted">AI Assistant를 불러오는 중… <button type="button" class="yai-text-btn" data-route="assistant">열기</button></p>';
      }
    }

    var addBtn = document.getElementById('yai-project-add-works');
    if (addBtn) addBtn.addEventListener('click', function () { route('works'); });

    var notesForm = document.getElementById('yai-workspace-notes-form');
    if (notesForm) {
      notesForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var ta = notesForm.querySelector('[name="notes"]');
        var saveBtn = document.getElementById('yai-workspace-notes-save');
        var errEl = document.getElementById('yai-workspace-notes-error');
        var notes = ta ? ta.value : '';
        if (errEl) { errEl.hidden = true; errEl.textContent = ''; }
        if (saveBtn) saveBtn.disabled = true;
        Core.projects.update(currentProjectId, { notes: notes }).then(function (res) {
          var updated = (res.data && res.data.project) || {};
          workspaceCache.project = updated;
          showToast('Notes saved.');
          paintWorkspacePanel();
        }).catch(function (err) {
          var msg = friendlyProjectError(err) || 'Notes 저장에 실패했습니다.';
          if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
          showToast(msg, true);
        }).finally(function () {
          if (saveBtn) saveBtn.disabled = false;
        });
      });
    }

    var settingsForm = document.getElementById('yai-workspace-settings-form');
    if (settingsForm) {
      settingsForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var payload = {
          title: (settingsForm.querySelector('[name="title"]').value || '').trim(),
          description: (settingsForm.querySelector('[name="description"]').value || '').trim(),
          category: settingsForm.querySelector('[name="category"]').value || 'mixed',
          type: settingsForm.querySelector('[name="category"]').value || 'mixed',
          visibility: settingsForm.querySelector('[name="visibility"]').value || 'private',
          language: settingsForm.querySelector('[name="language"]').value || 'ko',
          thumbnail_url: (settingsForm.querySelector('[name="thumbnail_url"]').value || '').trim()
        };
        if (!payload.title) {
          showToast('프로젝트 이름은 필수입니다.', true);
          return;
        }
        Core.projects.update(currentProjectId, payload).then(function (res) {
          var updated = (res.data && res.data.project) || {};
          workspaceCache.project = updated;
          setActiveProjectFromRecord(updated);
          showToast('Settings saved.');
          loadProjectDetail();
          refreshHomeProjects();
        }).catch(function (err) { showToast(err.message || 'Settings 저장 실패', true); });
      });
      var del = document.getElementById('yai-workspace-settings-delete');
      if (del) {
        del.addEventListener('click', function () {
          if (!window.confirm('Delete this project? Gallery assets are not deleted — only the project link is removed.')) return;
          Core.projects.delete(currentProjectId).then(function () {
            showToast('Project deleted.');
            if (Y.YooYActiveProject) Y.YooYActiveProject.clear();
            currentProjectId = '';
            route('projects');
            loadHome();
          }).catch(function (err) { showToast(err.message, true); });
        });
      }
    }
  }

  function loadProjectDetail() {
    if (!currentProjectId) {
      route('projects');
      return;
    }
    var titleEl = document.getElementById('yai-project-detail-title');
    var descEl = document.getElementById('yai-project-detail-desc');
    var panel = document.getElementById('yai-workspace-panel');
    if (!panel) return;

    panel.innerHTML = '<div class="yai-empty"><p>Loading workspace…</p></div>';
    renderWorkspaceTabs();

    Promise.all([
      Core.projects.get(currentProjectId),
      Core.gallery && typeof Core.gallery.works === 'function'
        ? Core.gallery.works({ project_id: currentProjectId }).catch(function () {
            return { data: { works: [] } };
          })
        : Promise.resolve({ data: { works: [] } })
    ]).then(function (results) {
      var project = (results[0].data && results[0].data.project) || {};
      var galleryWorks = (results[1].data && (results[1].data.works || results[1].data.items)) || [];
      var byId = {};
      galleryWorks.forEach(function (w) {
        if (w && w.id) byId[w.id] = w;
      });

      // Project.assets[].gallery_id is Source of Truth — Gallery body is not copied.
      var assetRefs = Array.isArray(project.assets) ? project.assets : [];
      var works;
      if (assetRefs.length) {
        works = assetRefs.map(function (a) {
          var gid = a.gallery_id || a.id || '';
          var g = byId[gid] || {};
          return {
            id: gid,
            gallery_id: gid,
            type: a.type || g.type || 'image',
            title: a.title || g.title || 'Work',
            thumbnail_url: a.thumbnail || g.thumbnail_url || g.thumbnail || '',
            image_url: a.url || g.image_url || g.output_url || g.asset_url || '',
            output_url: a.url || g.output_url || '',
            created_at: a.added_at || g.created_at || '',
            updated_at: g.updated_at || a.added_at || '',
            project_id: project.id || currentProjectId
          };
        }).filter(function (w) { return !!w.id; });
      } else {
        works = galleryWorks;
      }

      workspaceCache = { project: project, works: works };
      setActiveProjectFromRecord(project);

      if (titleEl) titleEl.textContent = project.title || 'Project Workspace';
      if (descEl) {
        descEl.textContent = 'Project Workspace · ' +
          fmt(project.asset_count || works.length || 0) + ' assets · ' +
          (project.visibility === 'public' ? 'Public' : 'Private');
      }
      paintWorkspacePanel();
    }).catch(function (err) {
      panel.innerHTML = emptyBlock('', 'Could not load workspace', err.message || 'Request failed.', 'Back', 'projects');
    });
  }

  function ensureProjectPickerModal() {
    var modal = document.getElementById('yai-project-picker-modal');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'yai-project-picker-modal';
    modal.className = 'yai-modal-shell';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="yai-modal-backdrop" data-yai-picker-close></div>' +
      '<div class="yai-modal-panel" role="dialog">' +
        '<header class="yai-modal-head"><h2>프로젝트 선택</h2><button type="button" class="yai-modal-close" data-yai-picker-close>&times;</button></header>' +
        '<div class="yai-modal-body"><div id="yai-project-picker-list"></div></div>' +
        '<footer class="yai-modal-foot">' +
          '<button type="button" class="yai-btn--outline" data-yai-picker-close>취소</button>' +
          '<button type="button" class="yai-btn yai-btn--gold" data-yai-picker-create>Create Project</button>' +
        '</footer>' +
      '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', function (e) {
      if (e.target.closest('[data-yai-picker-close]')) closeProjectPicker();
      if (e.target.closest('[data-yai-picker-create]')) {
        var wid = pickerWorkId;
        closeProjectPicker();
        openProjectModal(wid ? [wid] : []);
      }
    });
    return modal;
  }

  var pickerWorkId = '';

  function openProjectPicker(workId) {
    if (!workId || !requireLogin()) return;
    pickerWorkId = workId;
    var modal = ensureProjectPickerModal();
    var list = modal.querySelector('#yai-project-picker-list');
    list.innerHTML = '<p class="yai-muted">Loading…</p>';
    modal.hidden = false;
    document.body.classList.add('yai-modal-open');

    Core.projects.list().then(function (res) {
      var projects = (res.data && res.data.projects) || [];
      if (!projects.length) {
        list.innerHTML = '<p class="yai-muted">프로젝트가 없습니다. 새 프로젝트를 만들고 작품을 연결하세요.</p>';
        return;
      }
      list.innerHTML = projects.map(function (p) {
        return '<button type="button" class="yai-project-chip" data-picker-project="' + esc(p.id) + '" style="width:100%;margin-bottom:8px">' +
          projectThumbHtml(p) +
          '<div><strong>' + esc(p.title) + '</strong><span>' + fmt(p.asset_count || p.items || 0) + ' works</span></div>' +
        '</button>';
      }).join('') + '<button type="button" class="yai-btn--outline" data-picker-project="" style="width:100%;margin-top:8px">프로젝트에서 제거</button>';
    }).catch(function (err) {
      list.innerHTML = '<p class="yai-error">' + esc(err.message || 'Failed to load projects.') + '</p>';
    });
  }

  function closeProjectPicker() {
    var modal = document.getElementById('yai-project-picker-modal');
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('yai-modal-open');
    pickerWorkId = '';
  }

  function assignWorkToProject(projectId) {
    if (!pickerWorkId) return;
    var workId = pickerWorkId;

    if (!projectId) {
      // "프로젝트에서 제거" — need owning project id from active/detail context
      var removePid = currentProjectId ||
        (Y.YooYActiveProject && Y.YooYActiveProject.get() && Y.YooYActiveProject.get().id) || '';
      if (!removePid) {
        showToast('제거할 프로젝트를 찾을 수 없습니다.', true);
        return;
      }
      unlinkGalleryAssetFromProject(removePid, workId).then(function () {
        closeProjectPicker();
        showToast('프로젝트에서 제거했습니다.');
        refreshWorkViews();
        refreshHomeProjects();
        if (loaded['project-detail']) loadProjectDetail();
      }).catch(function (err) {
        showToast(err.message || '프로젝트 연결 해제에 실패했습니다.', true);
      });
      return;
    }

    linkGalleryAssetToProject(projectId, workId).then(function () {
      closeProjectPicker();
      showToast('프로젝트에 추가했습니다.');
      refreshWorkViews();
      refreshHomeProjects();
      Core.projects.get(projectId).then(function (res) {
        var p = (res.data && res.data.project) || { id: projectId };
        setActiveProjectFromRecord(p);
        openProjectDetail(projectId, 'assets');
      }).catch(function () {
        if (Y.YooYActiveProject) Y.YooYActiveProject.set({ id: projectId, name: 'Project' });
        openProjectDetail(projectId, 'assets');
      });
    }).catch(function (err) {
      showToast(err.message || '프로젝트 연결에 실패했습니다.', true);
    });
  }

  function renderProjectsEmpty(el) {
    el.innerHTML =
      '<div class="yai-empty">' +
        '<h3>아직 생성된 프로젝트가 없습니다.</h3>' +
        '<p>프로젝트를 만들고 Gallery 작품을 묶어 관리하세요.</p>' +
        '<button type="button" class="yai-btn yai-btn--gold yai-create-project" data-action="create-project" data-yai-create-project>첫 프로젝트 만들기</button>' +
      '</div>' +
      '<div id="yai-projects-suggest" class="yai-projects-suggest"><p class="yai-muted">Loading recent works…</p></div>';

    if (!Core.gallery || typeof Core.gallery.works !== 'function') return;

    Core.gallery.works().then(function (res) {
      var works = (res.data && (res.data.works || res.data.items)) || [];
      var unassigned = works.filter(function (w) { return !(w.project_id || ''); }).slice(0, 6);
      var suggestEl = document.getElementById('yai-projects-suggest');
      if (!suggestEl) return;
      if (!unassigned.length) {
        suggestEl.innerHTML = '<p class="yai-muted">프로젝트에 추가할 최근 작품이 없습니다.</p>';
        return;
      }
      suggestEl.innerHTML =
        '<h3>Recent Works</h3>' +
        '<div class="yai-works-grid yai-works-grid--dense">' +
          unassigned.map(function (w) {
            return '<div class="yai-project-suggest-item">' +
              workCardHtml(w, 'dense') +
              '<div class="yai-project-actions yai-recent-work-actions">' +
                '<button type="button" class="yai-btn--outline yai-btn--sm" data-recent-action="open" data-work-id="' + esc(w.id) + '">Open</button>' +
                '<button type="button" class="yai-btn--outline yai-btn--sm" data-recent-action="preview" data-work-id="' + esc(w.id) + '">Preview</button>' +
                '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-recent-action="add" data-work-id="' + esc(w.id) + '">Add to Project</button>' +
              '</div>' +
            '</div>';
          }).join('') +
        '</div>';
    }).catch(function () {
      var suggestEl = document.getElementById('yai-projects-suggest');
      if (suggestEl) suggestEl.innerHTML = '';
    });
  }

  function syncProjectContextBanner(pageName) {
    var banner = document.getElementById('yai-project-context-banner');
    var isStudio = STUDIO_PAGES.indexOf(pageName) !== -1;
    if (!isStudio) {
      if (banner) banner.hidden = true;
      return;
    }
    var view = document.querySelector('.yai-view[data-page="' + pageName + '"]');
    if (!view) return;
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'yai-project-context-banner';
      banner.className = 'yai-project-context-banner';
    }
    if (banner.parentNode !== view) {
      view.insertBefore(banner, view.firstChild);
    }
    banner.hidden = false;
    var active = Y.YooYActiveProject ? Y.YooYActiveProject.get() : null;
    if (active && active.id) {
      banner.innerHTML =
        '<div class="yai-project-context-banner__inner">' +
          '<span>Working in Project: <strong>' + esc(active.name || 'Project') + '</strong></span>' +
          '<div class="yai-project-context-banner__actions">' +
            '<button type="button" class="yai-text-btn" data-project-ctx="open">Open Workspace</button>' +
            '<button type="button" class="yai-text-btn" data-project-ctx="change">Change Project</button>' +
            '<button type="button" class="yai-text-btn" data-project-ctx="clear">Clear Project</button>' +
          '</div>' +
        '</div>';
    } else {
      banner.innerHTML =
        '<div class="yai-project-context-banner__inner yai-project-context-banner__inner--idle">' +
          '<span class="yai-muted">No active project</span>' +
          '<div class="yai-project-context-banner__actions">' +
            '<button type="button" class="yai-text-btn" data-project-ctx="pick">Select Project</button>' +
            '<button type="button" class="yai-text-btn" data-project-ctx="create">Create Project</button>' +
          '</div>' +
        '</div>';
    }
  }

  function friendlyProjectError(err) {
    if (!err) return '';
    if (err.restNoRoute || err.code === 'rest_no_route') {
      return 'Projects API에 연결할 수 없습니다. 페이지를 새로고침하거나 관리자에게 문의해 주세요.';
    }
    var msg = String(err.message || '');
    if (/stack|exception|undefined index|fatal|wpdb|mysql/i.test(msg)) {
      return '요청을 처리하지 못했습니다. 잠시 후 다시 시도해 주세요.';
    }
    return msg || '요청에 실패했습니다.';
  }

  function openActiveProjectPicker() {
    if (!requireLogin()) return;
    Core.projects.list().then(function (res) {
      var projects = (res.data && res.data.projects) || [];
      if (!projects.length) {
        openProjectModal();
        return;
      }
      var modal = ensureProjectPickerModal();
      var list = modal.querySelector('#yai-project-picker-list');
      pickerWorkId = '';
      list.innerHTML = projects.map(function (p) {
        return '<button type="button" class="yai-project-chip" data-active-project-pick="' + esc(p.id) + '" data-active-project-name="' + esc(p.title || 'Project') + '" style="width:100%;margin-bottom:8px">' +
          projectThumbHtml(p) +
          '<div><strong>' + esc(p.title) + '</strong><span>' + fmt(p.asset_count || p.items || 0) + ' works</span></div>' +
        '</button>';
      }).join('');
      modal.hidden = false;
      document.body.classList.add('yai-modal-open');
    }).catch(function (err) {
      showToast(friendlyProjectError(err) || 'Failed to load projects.', true);
    });
  }

  function loadProjects() {
    var el = document.getElementById('yai-projects-list');
    if (!el) return;
    if (!isLoggedIn()) {
      el.innerHTML = emptyBlock('', 'Login required', '로그인 후 프로젝트를 관리할 수 있습니다.', 'Home', 'home');
      return;
    }
    el.innerHTML = '<div class="yai-empty"><p>Loading projects…</p></div>';
    if (!Core.projects || typeof Core.projects.list !== 'function') {
      renderProjectsEmpty(el);
      showToast('Projects API를 사용할 수 없습니다.', true);
      return;
    }
    Core.projects.list().then(function (res) {
      var items = (res.data && res.data.projects) || [];
      if (!items.length) {
        renderProjectsEmpty(el);
        return;
      }
      el.innerHTML = '<div class="yai-project-grid">' + items.map(projectCardHtml).join('') + '</div>';
    }).catch(function (err) {
      var msg = friendlyProjectError(err) || 'Request failed.';
      var isNoRoute = !!(err && (err.restNoRoute || err.code === 'rest_no_route'));
      renderProjectsEmpty(el);
      var note = document.createElement('div');
      note.className = 'yai-empty yai-empty--warn';
      note.innerHTML = '<p class="yai-error">' + esc(msg) + '</p>' +
        '<button type="button" class="yai-btn yai-btn--outline" data-route="projects">다시 시도</button>';
      el.insertBefore(note, el.firstChild);
      showToast(msg, true);
      if (isNoRoute && typeof Core.restHealth === 'function') {
        Core.restHealth().catch(function () {});
      }
    });
  }

  function loadPrompts() {
    var el = document.getElementById('yai-prompts');
    if (!el) return;
    Promise.all([Core.prompts.list(), Core.prompts.presets()]).then(function (r) {
      var saved = (r[0].data && r[0].data.saved) || [];
      var official = (r[0].data && r[0].data.official) || [];
      var presets = (r[1].data && r[1].data.presets) || [];
      var html = '<div class="yai-prompt-section"><h3>한국형 Preset</h3><div class="yai-tags" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">';
      html += presets.map(function (p) {
        return '<button type="button" class="yai-tag yai-tag--active yai-preset-apply" data-preset-id="' + esc(p.id) + '" data-preset-studio="' + esc(p.studio || 'image') + '" data-preset-context="' + esc(p.context || '') + '">' + esc(p.label) + '</button>';
      }).join('');
      html += '</div></div>';
      if (saved.length) {
        html += '<div class="yai-prompt-section"><h3>저장된 Prompt</h3>' + saved.map(function (p) {
          return '<div class="yai-card yai-prompt-card" data-prompt-use="' + esc(p.prompt) + '"><strong>' + esc(p.title) + '</strong><p>' + esc(p.prompt) + '</p></div>';
        }).join('') + '</div>';
      }
      html += '<div class="yai-prompt-section"><h3>공식 Prompt</h3>' + official.map(function (p) {
        return '<div class="yai-card yai-prompt-card" data-prompt-use="' + esc(p.prompt) + '"><strong>' + esc(p.title) + '</strong><p>' + esc(p.prompt) + '</p></div>';
      }).join('') + '</div>';
      el.innerHTML = html;
    });
  }

  function loadMarket() {
    var el = document.getElementById('yai-marketplace');
    if (!el) return;
    Core.marketplace.items().then(function (res) {
      var items = (res.data && res.data.items) || [];
      if (!items.length) { el.innerHTML = emptyBlock('', 'Marketplace empty', 'No listings yet.', 'Community', 'community'); return; }
      el.innerHTML = items.map(function (it) {
        return '<div class="yai-card"><strong>' + esc(it.title) + '</strong><span>' + esc(it.creator) + ' · ' + (it.price === 0 ? 'Free' : fmt(it.price) + ' KRW') + '</span></div>';
      }).join('');
    });
  }

  function loadCommunity() {
    var el = document.getElementById('yai-community');
    if (!el) return;
    Core.community.feed().then(function (res) {
      var feed = (res.data && res.data.feed) || [];
      if (!feed.length) { el.innerHTML = emptyBlock('', 'Community empty', 'Share gallery works to start the feed.', 'Gallery', 'works'); return; }
      el.innerHTML = feed.map(function (it) {
        return '<div class="yai-card"><strong>' + esc(it.title) + '</strong><span>' + esc(it.creator) + ' · ♥' + fmt(it.likes || 0) + '</span></div>';
      }).join('');
    });
  }

  function loadWorks() {
    var el = document.getElementById('yai-works');
    if (!el) return;
    if (window.YooYGallery) { window.YooYGallery.mount(el); return; }
    Core.gallery.works().then(function (res) {
      var works = (res.data && res.data.works) || [];
      if (!works.length) { el.innerHTML = emptyBlock('', 'Gallery empty', 'Save generated works here.', 'Video Studio', 'video'); return; }
      el.innerHTML = works.map(function (w) { return '<div class="yai-card"><strong>' + esc(w.title || 'Work') + '</strong></div>'; }).join('');
    });
  }

  function loadHistory() {
    var el = document.getElementById('yai-history');
    if (!el) return;
    if (window.YooYGallery) {
      window.YooYGallery.mount(el);
      return;
    }
    Core.gallery.works().then(function (res) {
      var works = ((res.data && res.data.works) || []).slice().sort(function (a, b) {
        return String(b.created_at || '').localeCompare(String(a.created_at || ''));
      });
      if (!works.length) {
        el.innerHTML = emptyBlock('', 'History empty', '생성·저장된 Gallery 작업이 여기에 시간순으로 표시됩니다.', 'Create', 'image');
        return;
      }
      el.innerHTML = '<ul class="yai-workspace-history-list">' + works.map(function (w) {
        return '<li class="yai-workspace-history-item"><strong>' + esc(w.title || 'Work') + '</strong>' +
          '<span class="yai-muted">' + esc(typeBadgeLabel(w.type || '')) + ' · ' + esc(relTime(w.created_at)) + '</span></li>';
      }).join('') + '</ul>';
    }).catch(function () {
      el.innerHTML = emptyBlock('', 'History', 'Gallery를 불러오지 못했습니다.', 'Gallery', 'works');
    });
  }

  function loadCredits() {
    var el = document.getElementById('yai-credits-panel');
    if (!el) return;

    if (!isLoggedIn()) {
      Core.credits.plans().then(function (res) {
        var plans = (res.data && res.data.plans) || [];
        var billing = (res.data && res.data.billing) || {};
        el.innerHTML = '<div class="yai-credits-upgrade-cta"><p>Login to view your balance, usage, and credit history.</p>' +
          '<a class="yai-btn yai-btn--gold yai-login-link" href="' + esc(loginUrl()) + '">Login</a></div>' +
          renderPaymentSetupNotice(billing) +
          '<h2 class="yai-credits-section-title">Plans</h2>' +
          renderPlanCards(plans, 'free', billing, false) +
          '<h2 class="yai-credits-section-title">Plan Comparison</h2>' +
          renderPlanComparison(plans, 'free');
        bindPlanUpgradeButtons(el, plans, billing);
      });
      return;
    }

    Core.credits.overview().then(function (res) {
      var d = res.data || {};
      var acc = d.account || {};
      var plans = d.plans || [];
      var billing = d.billing || {};
      var txs = d.transactions || [];

      var html = renderCurrentPlanDashboard(acc);
      html += renderPaymentSetupNotice(billing);
      html += '<h2 class="yai-credits-section-title">Plans</h2>';
      html += renderPlanCards(plans, acc.plan || 'free', billing, true);
      html += '<h2 class="yai-credits-section-title">Plan Comparison</h2>';
      html += renderPlanComparison(plans, acc.plan || 'free');
      html += '<h2 class="yai-credits-section-title">Credit Ledger</h2>';
      html += renderLedger(txs);
      el.innerHTML = html;

      var scrollBtn = document.getElementById('yai-credits-scroll-plans');
      if (scrollBtn) {
        scrollBtn.addEventListener('click', function () {
          var first = el.querySelector('.yai-plan-card:not(.is-current)');
          if (first) first.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      }

      bindPlanUpgradeButtons(el, plans, billing);
    }).catch(function (err) {
      el.innerHTML = '<p class="yai-error">' + esc(err.message || 'Failed to load credits.') + '</p>';
    });
  }

  function loadBilling() {
    var el = document.getElementById('yai-billing-panel');
    if (!el) return;

    if (!isLoggedIn()) {
      el.innerHTML = '<div class="yai-billing-empty"><p>결제 내역을 보려면 로그인하세요.</p>' +
        '<a class="yai-btn yai-btn--gold yai-login-link" href="' + esc(loginUrl()) + '">Login</a></div>';
      return;
    }

    Core.credits.overview().then(function (res) {
      var d = res.data || {};
      var acc = d.account || {};
      var billing = d.billing || {};
      var plans = d.plans || [];
      var orders = billing.orders || [];
      var invoices = billing.invoices || orders;
      var purchases = billing.credit_purchases || [];

      var html = '<div class="yai-billing-hero">' +
        '<div><h2>' + esc(acc.plan_name || 'Free') + '</h2>' +
        '<span class="yai-plan-current-badge">CURRENT PLAN</span>' +
        '<p class="yai-muted">다음 갱신: ' + esc(acc.renewal_label || '—') + '</p></div>' +
        '<div class="yai-billing-hero-actions">' +
          '<button type="button" class="yai-btn yai-btn--gold" data-route="credits">Upgrade</button>' +
        '</div></div>';

      html += '<div class="yai-billing-grid">' +
        statCard('Credit Balance', acc.unlimited ? '∞' : fmt(acc.balance), acc.plan_credits + ' / month allotment') +
        statCard('Monthly Usage', fmt((acc.monthly_usage || {}).used || 0) + ' / ' + fmt((acc.monthly_usage || {}).limit || 0)) +
        statCard('Renew Date', acc.renewal_label || '—', acc.renewal_at ? 'Auto-renewal' : 'Manual') +
      '</div>';

      html += '<h3 class="yai-credits-section-title">Payment Providers</h3>' +
        '<div class="yai-billing-providers">' +
        (billing.providers || []).map(function (p) {
          return '<span class="yai-billing-provider' + (p.enabled ? ' is-on' : '') + '">' + esc(p.label) + '</span>';
        }).join('') + '</div>';

      html += '<h3 class="yai-credits-section-title">Payment History</h3>';
      if (!orders.length) {
        html += '<p class="yai-muted">아직 결제 내역이 없습니다.</p>';
      } else {
        html += '<div class="yai-ledger-table"><table><thead><tr><th>Date</th><th>Plan</th><th>Total</th><th>Status</th><th>Invoice</th></tr></thead><tbody>';
        orders.forEach(function (o) {
          html += '<tr><td>' + esc(relTime(o.created_at || o.recorded_at)) + '</td>' +
            '<td>' + esc(o.plan_name || o.plan_id || '—') + '</td>' +
            '<td>' + esc(String(o.total || 0)) + ' ' + esc(o.currency || 'KRW') + '</td>' +
            '<td>' + esc(o.status || 'completed') + '</td>' +
            '<td>' + (o.invoice_url ? '<a href="' + esc(o.invoice_url) + '" target="_blank" rel="noopener">View</a>' : '—') + '</td></tr>';
        });
        html += '</tbody></table></div>';
      }

      html += '<h3 class="yai-credits-section-title">Credits Purchased</h3>';
      html += renderLedger(purchases.length ? purchases : []);

      html += '<h3 class="yai-credits-section-title">Manage Subscription</h3>' +
        '<div class="yai-billing-manage">' +
        '<button type="button" class="yai-btn yai-btn--gold" data-route="credits">Upgrade Plan</button>' +
        '<a class="yai-btn yai-btn--outline" href="mailto:' + esc(billing.support_email || '') + '?subject=' + encodeURIComponent('YooY Subscription') + '">Downgrade / Cancel</a>' +
        '</div>';

      html += '<h3 class="yai-credits-section-title">Available Plans</h3>';
      html += renderPlanCards(plans, acc.plan || 'free', billing, true);

      el.innerHTML = html;
      bindPlanUpgradeButtons(el, plans, billing);
    }).catch(function (err) {
      el.innerHTML = '<p class="yai-error">' + esc(err.message || 'Failed to load billing.') + '</p>';
    });
  }

  function statCard(label, value, extra) {
    return '<article class="yai-credits-stat"><span>' + esc(label) + '</span><strong>' + esc(String(value)) + '</strong>' +
      (extra ? '<em>' + esc(extra) + '</em>' : '') + '</article>';
  }

  function renderPlanComparison(plans, currentPlan) {
    if (!plans.length) return '';
    var cur = normalizePlanId(currentPlan);
    var html = '<div class="yai-compare-matrix-wrap" style="--compare-cols:' + plans.length + '"><div class="yai-compare-matrix">';
    html += '<div class="yai-compare-matrix-row yai-compare-matrix-row--head">';
    html += '<div class="yai-compare-feature-col">Features</div>';
    plans.forEach(function (p) {
      var pid = normalizePlanId(p.id);
      var isCurrent = pid === cur || !!p.is_current;
      html += '<div class="yai-compare-plan-col' + (isCurrent ? ' is-current' : '') + ' yai-compare-plan-col--' + pid + '">' +
        crystalHtml(pid, 'sm') +
        '<strong>' + esc(p.name) + '</strong>' +
        (isCurrent ? '<span class="yai-plan-current-badge">CURRENT PLAN</span>' : '') +
        '</div>';
    });
    html += '</div>';
    COMPARE_ROWS.forEach(function (row) {
      var featCls = 'yai-compare-feature-col' + (row.highlight ? ' yai-compare-feature-col--highlight' : '');
      html += '<div class="yai-compare-matrix-row">';
      html += '<div class="' + featCls + '">' +
        '<span class="yai-compare-feature-icon" role="img" aria-label="' + esc(row.label) + '">' + row.icon + '</span>' +
        '<span>' + esc(row.label) + '</span></div>';
      plans.forEach(function (p) {
        var pid = normalizePlanId(p.id);
        var isCurrent = pid === cur || !!p.is_current;
        var has = planHasCompareFeature(pid, row.id);
        html += '<div class="yai-compare-cell' + (isCurrent ? ' is-current' : '') + '">' +
          compareCheckHtml(has, pid) + '</div>';
      });
      html += '</div>';
    });
    return html + '</div></div>';
  }

  function renderPlanCards(plans, currentPlan, billing, interactive) {
    if (!plans.length) return '<p class="yai-muted">No plans configured.</p>';
    var cur = normalizePlanId(currentPlan);
    billing = billing || {};
    var html = '<div class="yai-plan-grid">';
    plans.forEach(function (p) {
      var pid = normalizePlanId(p.id);
      var isCurrent = pid === cur || !!p.is_current;
      var features = (p.features || []).filter(function (f) {
        return !/^\d[\d,]*\s*credits?$/i.test(String(f).trim());
      }).map(function (f) { return renderPlanFeatureItem(f, pid); }).join('');
      var priceMonthly = formatKrw(p.price_krw) + (p.price_krw ? ' / 월' : '');
      var priceYearly = p.yearly_price_krw ? '<div class="yai-plan-price-yearly">' + esc(formatKrw(p.yearly_price_krw)) + ' / 년</div>' : '';
      var btnHtml = interactive ? renderPlanButton(p, cur, billing) : '';
      var cardCls = 'yai-plan-card yai-plan-card--' + pid + (isCurrent ? ' is-current' : '');
      html += '<article class="' + cardCls + '">' +
        (isCurrent ? '<span class="yai-plan-current-badge yai-plan-current-badge--corner">CURRENT PLAN</span>' : '') +
        '<div class="yai-plan-card-crystal">' + crystalHtml(pid, 'lg', isCurrent ? 'yai-crystal--current yai-crystal--plan-current' : '') + '</div>' +
        '<h3 class="yai-plan-card-name">' + esc(p.name) + '</h3>' +
        '<div class="yai-plan-price">' + esc(priceMonthly) + '</div>' +
        priceYearly +
        '<div class="yai-plan-credits">' + fmt(p.credits) + ' credits included</div>' +
        '<ul class="yai-plan-features">' + features + '</ul>' + btnHtml + '</article>';
    });
    return html + '</div>';
  }

  function renderLedger(rows) {
    if (!rows.length) return '<p class="yai-muted">No transactions yet.</p>';
    var html = '<div class="yai-ledger-table"><table><thead><tr>' +
      '<th>Date</th><th>Action</th><th>Studio</th><th>Change</th><th>Balance</th><th>Status</th>' +
      '</tr></thead><tbody>';
    rows.slice(0, 50).forEach(function (tx) {
      var amt = tx.amount != null ? tx.amount : tx.delta;
      html += '<tr>' +
        '<td>' + esc(relTime(tx.created_at)) + '</td>' +
        '<td>' + esc(tx.label || tx.type || '—') + '</td>' +
        '<td>' + esc(tx.studio || tx.module || '—') + '</td>' +
        '<td>' + esc((Number(amt) >= 0 ? '+' : '') + String(amt == null ? '—' : amt)) + '</td>' +
        '<td>' + esc(String(tx.balance_after != null ? tx.balance_after : '—')) + '</td>' +
        '<td>' + esc(tx.status || 'completed') + '</td></tr>';
    });
    return html + '</tbody></table></div>';
  }

  function loadSettings() {
    var el = document.getElementById('yai-settings');
    if (!el) return;
    Core.settings.get().then(function (res) {
      var s = (res.data && res.data.settings) || {};
      el.innerHTML =
        '<div class="yai-setting-card"><strong>Korean Context</strong><span>' + (s.korean_context ? 'Enabled' : 'Disabled') + '</span></div>' +
        '<div class="yai-setting-card"><strong>Default AI Engine</strong><span>' + esc(s.default_provider || 'Auto') + '</span></div>' +
        '<div class="yai-setting-card"><strong>Auto Save</strong><span>' + (s.auto_save ? 'On' : 'Off') + '</span></div>' +
        '<div class="yai-setting-card"><strong>Output Quality</strong><span>' + esc(s.quality || 'Standard') + '</span></div>';
    });
  }

  function loadWriting() {
    var el = document.getElementById('yai-gen-writing');
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';
    el.innerHTML =
      '<div class="yai-writing-layout">' +
        '<textarea class="yai-prompt-input" placeholder="블로그, 광고 카피, 스크립트 프롬프트를 입력하세요…"></textarea>' +
        '<aside id="yai-writing-ref-host"></aside>' +
      '</div>' +
      '<button class="yai-generate-btn" type="button">Generate</button><div class="yai-result"></div>';
    var input = el.querySelector('.yai-prompt-input');
    var btn = el.querySelector('.yai-generate-btn');
    var result = el.querySelector('.yai-result');
    var refPanel = null;
    var refAssets = [];

    if (window.YooYReferenceAssetsPanel) {
      refPanel = window.YooYReferenceAssetsPanel.mount(el.querySelector('#yai-writing-ref-host'), {
        studio: 'writing-studio',
        assets: [],
        onChange: function (assets) { refAssets = assets || []; }
      });
    }

    try {
      var saved = sessionStorage.getItem('yoy_home_prompt');
      if (saved) { input.value = saved; sessionStorage.removeItem('yoy_home_prompt'); }
    } catch (e) {}
    btn.addEventListener('click', function () {
      if (!requireLogin()) return;
      var prompt = input.value.trim();
      if (!prompt) return;
      btn.disabled = true;
      var payload = { type: 'writing', prompt: prompt, provider: 'auto' };
      if (window.YooYReferenceAssetsPanel && refPanel) {
        payload = window.YooYReferenceAssetsPanel.applyToSettings(payload, refPanel.getAssets());
      } else if (refAssets.length) {
        payload.reference_assets = refAssets;
      }
      Core.router.generate(payload).then(function (res) {
        var data = res.data || {};
        result.innerHTML = '<div class="yai-card"><strong>Done</strong><p>Credits: ' + esc(String(data.credits_used)) + '</p></div>';
      }).catch(function (err) { result.innerHTML = '<p class="yai-error">' + esc(err.message) + '</p>'; })
        .finally(function () { btn.disabled = false; });
    });
  }

  function loadProfile() {
    if (!isLoggedIn()) return;
    Core.credits.overview().then(function (res) {
      var d = res.data || {};
      var acc = d.account || {};
      var mu = acc.monthly_usage || {};
      var plan = normalizePlanId(acc.plan || acc.tier);
      applyCrystal(document.getElementById('yai-membership-crystal'), plan, 'md');
      applyCrystal(document.getElementById('yai-topbar-crystal'), plan, 'sm');
      var profileCard = document.getElementById('yai-profile-card');
      if (profileCard) {
        profileCard.className = 'yai-profile' + (plan === 'business' ? ' yai-profile--business' : ' yai-profile--' + plan);
      }
      var el = document.getElementById('yai-credits');
      if (el) el.textContent = 'Credits: ' + (acc.unlimited ? '∞' : fmt(acc.balance));
      var tier = document.getElementById('yai-tier-badge');
      if (tier) tier.textContent = acc.plan_name || acc.tier || 'Free';
      var usage = document.getElementById('yai-monthly-usage');
      if (usage) usage.textContent = 'Monthly: ' + fmt(mu.used || 0) + ' / ' + fmt(mu.limit || 0);
      if (document.getElementById('yai-top-credits')) {
        setText('yai-top-credits', (acc.unlimited ? '∞' : fmt(acc.balance)) + ' Credits');
      }
    }).catch(function () {
      Core.credits.balance().then(function (res) {
        var d = res.data || {};
        var el = document.getElementById('yai-credits');
        if (el) el.textContent = 'Credits: ' + (d.unlimited ? '∞' : fmt(d.balance));
      }).catch(function () {});
    });
  }

  document.addEventListener('click', function (e) {
    var panelBtn = e.target.closest('[data-yai-panel]');
    if (panelBtn) {
      e.preventDefault();
      openPanel(panelBtn.getAttribute('data-yai-panel'));
      return;
    }

    if (e.target.closest('[data-yai-close-panel]') || e.target.classList.contains('yai-overlay') && e.target.id && e.target.id.indexOf('yai-panel-') === 0) {
      if (e.target.classList.contains('yai-overlay') && e.target !== e.currentTarget) return;
      closePanels();
      return;
    }

    if (e.target.closest('[data-yai-free-start]')) {
      e.preventDefault();
      window.location.href = registerUrl();
      return;
    }

    if (e.target.closest('[data-yai-close-modal]')) {
      hideLoginModal();
      return;
    }

    if (e.target.id === 'yai-login-modal') {
      hideLoginModal();
      return;
    }

    // Create-project clicks are handled by the capture-phase document
    // delegation → window.YooYOpenProjectCreateDialog (see file header).

    var addToNewProject = e.target.closest('[data-add-to-new-project]');
    if (addToNewProject) {
      e.preventDefault();
      e.stopPropagation();
      if (!requireLogin()) return;
      saveGalleryItemToProject(addToNewProject.getAttribute('data-add-to-new-project'));
      return;
    }

    var pickerProject = e.target.closest('[data-picker-project]');
    if (pickerProject) {
      e.preventDefault();
      assignWorkToProject(pickerProject.getAttribute('data-picker-project') || '');
      return;
    }

    var activePick = e.target.closest('[data-active-project-pick]');
    if (activePick) {
      e.preventDefault();
      setActiveProjectFromRecord({
        id: activePick.getAttribute('data-active-project-pick'),
        title: activePick.getAttribute('data-active-project-name') || 'Project'
      });
      closeProjectPicker();
      syncProjectContextBanner(document.querySelector('.yai-view.is-active') &&
        document.querySelector('.yai-view.is-active').getAttribute('data-page'));
      showToast('Active Project: ' + (activePick.getAttribute('data-active-project-name') || ''));
      return;
    }

    var activityBtn = e.target.closest('[data-activity-id]');
    if (activityBtn) {
      e.preventDefault();
      var aid = activityBtn.getAttribute('data-activity-id');
      var activityItem = homeActivityCache.find(function (j) {
        return (j.job_id || j.id) === aid;
      });
      if (activityItem) handleActivityClick(activityItem);
      return;
    }

    var failureAction = e.target.closest('[data-failure-action]');
    if (failureAction) {
      e.preventDefault();
      var fAction = failureAction.getAttribute('data-failure-action');
      var fid = failureAction.getAttribute('data-failure-job');
      var fitem = homeActivityCache.find(function (j) {
        return (j.job_id || j.id) === fid;
      });
      if (!fitem) return;
      if (fAction === 'retry') retryActivityJob(fitem, 'auto');
      else if (fAction === 'retry-provider') {
        retryActivityJob(fitem, failureAction.getAttribute('data-provider') || 'auto');
      } else if (fAction === 'delete') deleteActivityJob(fitem);
      else if (fAction === 'copy') copyActivityLog(fitem);
      else if (fAction === 'admin') {
        pendingAdminSection = 'providers';
        closeFailureResolver();
        route('admin-console');
      } else if (fAction === 'contact') {
        showToast('관리자에게 문의해 주세요. Job ID: ' + (fitem.job_id || ''));
      }
      return;
    }

    var recentAction = e.target.closest('[data-recent-action]');
    if (recentAction) {
      e.preventDefault();
      e.stopPropagation();
      var rid = recentAction.getAttribute('data-work-id');
      var ract = recentAction.getAttribute('data-recent-action');
      if (ract === 'open' || ract === 'preview') openWorkDetail(rid);
      else if (ract === 'add') saveGalleryItemToProject(rid);
      return;
    }

    var wsTab = e.target.closest('[data-workspace-tab]');
    if (wsTab) {
      e.preventDefault();
      workspaceTab = wsTab.getAttribute('data-workspace-tab') || 'overview';
      paintWorkspacePanel();
      return;
    }

    var wsGoto = e.target.closest('[data-workspace-goto]');
    if (wsGoto) {
      e.preventDefault();
      workspaceTab = wsGoto.getAttribute('data-workspace-goto') || 'settings';
      paintWorkspacePanel();
      return;
    }

    var wsStudio = e.target.closest('[data-workspace-studio]');
    if (wsStudio) {
      e.preventDefault();
      if (workspaceCache.project) setActiveProjectFromRecord(workspaceCache.project);
      route(wsStudio.getAttribute('data-workspace-studio'));
      return;
    }

    var wsAsset = e.target.closest('[data-ws-asset-action]');
    if (wsAsset) {
      e.preventDefault();
      e.stopPropagation();
      var wid = wsAsset.getAttribute('data-work-id');
      var wact = wsAsset.getAttribute('data-ws-asset-action');
      if (wact === 'open' || wact === 'preview') openWorkDetail(wid);
      else if (wact === 'remove') {
        if (!window.confirm('프로젝트에서만 연결을 해제합니다. Gallery 원본은 삭제되지 않습니다.')) return;
        var removePid = currentProjectId ||
          (workspaceCache.project && workspaceCache.project.id) || '';
        unlinkGalleryAssetFromProject(removePid, wid).then(function () {
          showToast('프로젝트에서 제거했습니다.');
          loadProjectDetail();
          refreshHomeProjects();
        }).catch(function (err) { showToast(err.message || '제거 실패', true); });
      } else if (wact === 'studio') {
        if (workspaceCache.project) setActiveProjectFromRecord(workspaceCache.project);
        route(wsAsset.getAttribute('data-studio-route') || 'image');
      }
      return;
    }

    var ctxBtn = e.target.closest('[data-project-ctx]');
    if (ctxBtn) {
      e.preventDefault();
      var ctx = ctxBtn.getAttribute('data-project-ctx');
      if (ctx === 'open') {
        var cur = Y.YooYActiveProject && Y.YooYActiveProject.get();
        if (cur && cur.id) openProjectDetail(cur.id, 'overview');
      } else if (ctx === 'change' || ctx === 'pick') {
        openActiveProjectPicker();
      } else if (ctx === 'create') {
        window.YooYOpenProjectCreateDialog();
      } else if (ctx === 'clear') {
        if (Y.YooYActiveProject) Y.YooYActiveProject.clear();
        syncProjectContextBanner(document.querySelector('.yai-view.is-active') &&
          document.querySelector('.yai-view.is-active').getAttribute('data-page'));
        showToast('Active Project cleared.');
      }
      return;
    }

    var projectOpen = e.target.closest('[data-project-open]');
    if (projectOpen && !e.target.closest('.yai-project-rename') && !e.target.closest('.yai-project-delete')) {
      e.preventDefault();
      openProjectDetail(projectOpen.getAttribute('data-project-open'), 'overview');
      return;
    }

    var filterChip = e.target.closest('[data-project-filter]');
    if (filterChip) {
      e.preventDefault();
      projectDetailFilter = filterChip.getAttribute('data-project-filter') || 'all';
      workspaceTab = 'assets';
      paintWorkspacePanel();
      return;
    }

    var feedItem = e.target.closest('[data-feed-item]');
    if (feedItem) {
      e.preventDefault();
      var feedSource = feedItem.getAttribute('data-feed-source') || 'official';
      var titleEl = feedItem.querySelector('.yai-work-card-title');
      var promptText = titleEl ? (titleEl.textContent || '').trim() : '';
      if (feedSource === 'community') {
        route('community');
        return;
      }
      if (feedSource === 'marketplace') {
        route('market');
        return;
      }
      try { sessionStorage.setItem('yoy_home_prompt', promptText); } catch (err) {}
      showToast((feedSourceLabel(feedSource) || 'Platform') + ' 작품 · Studio에서 만들기');
      route('image');
      return;
    }

    var workRow = e.target.closest('.yai-work-card, .yai-row--work');
    if (workRow) {
      var quickAction = e.target.closest('[data-work-action]');
      if (quickAction) {
        e.preventDefault();
        e.stopPropagation();
        handleWorkAction(quickAction.getAttribute('data-work-action'), quickAction.getAttribute('data-work-id'));
        closeAllWorkMenus();
        return;
      }
      var menuBtn = e.target.closest('[data-work-menu]');
      if (menuBtn) {
        e.preventDefault();
        e.stopPropagation();
        var wrap = menuBtn.closest('.yai-work-card-menu');
        var menu = wrap && wrap.querySelector('.yai-work-menu');
        var card = menuBtn.closest('.yai-work-card');
        var willOpen = menu && menu.hidden;
        closeAllWorkMenus();
        if (willOpen && menu) {
          menu.hidden = false;
          if (card) card.classList.add('is-menu-open');
        }
        return;
      }
      if (e.target.closest('.yai-work-menu')) {
        return;
      }
      e.preventDefault();
      var workId = workRow.getAttribute('data-work-id');
      if (workId) openWorkDetail(workId);
      return;
    }

    if (!e.target.closest('.yai-work-card-menu')) {
      closeAllWorkMenus();
    }

    var renameBtn = e.target.closest('.yai-project-rename');
    if (renameBtn) {
      e.preventDefault();
      if (!requireLogin()) return;
      var newTitle = window.prompt('Rename project', renameBtn.getAttribute('data-title') || '');
      if (!newTitle || !newTitle.trim()) return;
      Core.projects.update(renameBtn.getAttribute('data-id'), { title: newTitle.trim() }).then(function () {
        loadProjects();
        showToast('Project renamed.');
      }).catch(function (err) { showToast(err.message, true); });
      return;
    }

    var deleteBtn = e.target.closest('.yai-project-delete');
    if (deleteBtn) {
      e.preventDefault();
      if (!requireLogin()) return;
      if (!window.confirm('Delete this project?')) return;
      Core.projects.delete(deleteBtn.getAttribute('data-id')).then(function () {
        loadProjects();
        loadHome();
        showToast('Project deleted.');
      }).catch(function (err) { showToast(err.message, true); });
      return;
    }

    var promptCard = e.target.closest('[data-prompt-use]');
    if (promptCard) {
      e.preventDefault();
      var promptText = promptCard.getAttribute('data-prompt-use') || '';
      try { sessionStorage.setItem('yoy_home_prompt', promptText); } catch (err) {}
      route(resolveStudioFromPrompt(promptText, null));
      return;
    }

    var presetApply = e.target.closest('.yai-preset-apply');
    if (presetApply) {
      e.preventDefault();
      var ctx = presetApply.getAttribute('data-preset-context') || '';
      var studio = presetApply.getAttribute('data-preset-studio') || 'image';
      var label = (presetApply.textContent || '').trim();
      var full = ctx ? (label + ' — ' + ctx) : label;
      try { sessionStorage.setItem('yoy_home_prompt', full); } catch (err) {}
      route(studio);
      return;
    }

    var btn = e.target.closest('[data-route]');
    if (!btn) return;
    e.preventDefault();
    var adminSec = btn.getAttribute('data-admin-section');
    if (adminSec) pendingAdminSection = adminSec;
    route(btn.dataset.route);
  });

  var createBtn = document.getElementById('yai-home-create');
  if (createBtn) {
    createBtn.addEventListener('click', function () {
      launchFromHome();
    });
  }

  var coachBtn = document.getElementById('yai-home-coach');
  if (coachBtn) {
    coachBtn.addEventListener('click', function () {
      var input = document.getElementById('yai-home-prompt');
      var panel = document.getElementById('yai-home-coach-panel');
      var seed = input ? input.value : '';
      if (!window.YooYCreateUX || typeof window.YooYCreateUX.composePrompt !== 'function') {
        if (panel) {
          panel.hidden = false;
          panel.innerHTML = '<p class="yai-muted">Prompt Coach를 사용할 수 없습니다.</p>';
        }
        return;
      }
      window.YooYCreateUX.composePrompt(seed, panel, function (composed, studio) {
        if (input) input.value = composed;
        try {
          sessionStorage.setItem('yoy_home_prompt', composed);
          if (studio) sessionStorage.setItem('yoy_home_studio', studio);
        } catch (e) {}
        showToast('보완된 Prompt가 적용되었습니다. Studio로 이동하려면 빠른 시작을 누르세요.');
      });
    });
  }

  document.addEventListener('click', function (e) {
    var presetBtn = e.target.closest('[data-yai-preset]');
    if (presetBtn) {
      e.preventDefault();
      homePresetId = presetBtn.dataset.yaiPreset;
      renderHomePresets();
      return;
    }
  });

  document.addEventListener('yoy:gallery:updated', function () {
    if (loaded.home) loadHome();
    if (loaded['project-detail']) loadProjectDetail();
    loadProfile();
  });

  if (Y.YooYActiveProject && typeof Y.YooYActiveProject.subscribe === 'function') {
    Y.YooYActiveProject.subscribe(function () {
      var activeView = document.querySelector('.yai-view.is-active');
      syncProjectContextBanner(activeView && activeView.getAttribute('data-page'));
    });
  }

  var projectDeleteBtn = document.getElementById('yai-project-detail-delete');
  if (projectDeleteBtn) {
    projectDeleteBtn.addEventListener('click', function () {
      if (!currentProjectId || !requireLogin()) return;
      if (!window.confirm('Delete this project? Gallery assets are not deleted — only the project link is removed.')) return;
      Core.projects.delete(currentProjectId).then(function () {
        showToast('Project deleted.');
        if (Y.YooYActiveProject) Y.YooYActiveProject.clear();
        currentProjectId = '';
        route('projects');
        loadHome();
      }).catch(function (err) { showToast(err.message, true); });
    });
  }

  try {
    loadProfile();
    watchBillingReturn();
    ensureProjectModal();
    route('home');
  } catch (bootUiErr) {
    if (window.console && window.console.error) {
      window.console.error('[YooYStudio] UI boot failed', bootUiErr);
    }
  }

  window.__yoyOpenProjectModalInternal = openProjectModal;
  window.__yoySubmitProjectCreateInternal = submitProjectCreate;
  window.YooYOpenProjectCreateDialog = function (workIds) {
    try {
      openProjectModal(workIds);
    } catch (dlgErr) {
      if (window.console && console.error) console.error('[YooY Projects] open failed', dlgErr);
    }
  };
  window.YooYStudioRoute = route;
  window.YooYStudioOpenProject = openProjectDetail;
  window.YooYStudioPickProject = openProjectPicker;
  window.YooYStudioSaveToProject = saveGalleryItemToProject;
  window.YooYStudioOpenProjectModal = window.YooYOpenProjectCreateDialog;
  } // end bootYooYStudio

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { startStudioBoot(0); });
  } else {
    startStudioBoot(0);
  }
})();
