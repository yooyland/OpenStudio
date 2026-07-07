(function () {
  'use strict';

  try {
  var Core = window.YooYCore;
  if (!Core) return;

  var loaded = {};
  var currentProjectId = '';
  var projectDetailFilter = 'all';
  var PROTECTED_ROUTES = ['projects', 'project-detail', 'import', 'video', 'image', 'music', 'voice', 'avatar', 'writing'];
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
          if (global.YooYStudioRoute) global.YooYStudioRoute('admin-console');
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
    home: 'Home', projects: 'Projects', 'project-detail': 'Project', video: 'Video Studio', image: 'Image Studio',
    music: 'Music Studio', voice: 'Voice Studio', avatar: 'Avatar Studio', writing: 'Writing Studio',
    import: 'Import', works: 'Gallery', community: 'Community', market: 'Marketplace',
    credits: 'Credits', billing: 'Billing', settings: 'Settings', 'prompt-library': 'Prompt Library', 'admin-console': 'Operations Center'
  };

  function isLoggedIn() { return !!Core.config.loggedIn; }
  function loginUrl() { return Core.config.loginUrl || '#'; }

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
      btn = '<button type="button" class="yai-btn--outline" data-yai-create-project>' + esc(btnLabel) + '</button>';
    } else if (routeName && btnLabel) {
      btn = '<button type="button" class="yai-btn--outline" data-route="' + esc(routeName) + '">' + esc(btnLabel) + '</button>';
    }
    return '<div class="yai-empty">' +
      '<div class="yai-empty-icon">' + (icon || '') + '</div>' +
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
        btn.classList.toggle('is-active', btn.dataset.route === name);
      }
    });

    setTopbarTitle(name);
    var main = document.getElementById('yai-main');
    if (main) main.scrollTop = 0;
    hydrate(name);
  }

  function hydrate(name) {
    if (name === 'works') { loaded[name] = false; loadWorks(); loaded[name] = true; return; }
    if (name === 'home') { loaded[name] = false; loadHome(); loaded[name] = true; return; }
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
        if (window.YooYAdminConsole) window.YooYAdminConsole.openOps('overview');
        loaded[name] = true;
        break;
      case 'video': mountStudio('video', 'YooYVideoStudio'); break;
      case 'image': mountStudio('image', 'YooYImageStudio'); break;
      case 'music': mountStudio('music', 'YooYMusicStudio'); break;
      case 'voice': mountStudio('voice', 'YooYVoiceStudio'); break;
      case 'avatar': mountStudio('avatar', 'YooYAvatarStudio'); break;
      case 'writing': loadWriting(); break;
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

  function loadHome() {
    if (!Core.config.loggedIn) { renderHomeEmpty(); return; }

    Core.dashboard().then(function (res) {
      var d = res.data || {};
      var cr = d.credits || {};
      var mu = d.monthly_usage || {};

      setText('yai-stat-credits', cr.unlimited ? '∞' : fmt(cr.balance));
      setText('yai-stat-projects', fmt(d.project_count || 0));
      setText('yai-stat-jobs', fmt(d.job_count || 0));
      setText('yai-stat-likes', fmt(d.community_likes || 0));
      setText('yai-stat-usage', fmt(mu.used || 0) + ' / ' + fmt(mu.limit || 0));
      setText('yai-top-credits', (cr.unlimited ? '∞' : fmt(cr.balance)) + ' Credits');

      var bar = document.getElementById('yai-stat-usage-bar');
      if (bar) bar.style.width = (mu.percent || 0) + '%';

      renderUsageWidget(mu);
      renderProjects(d.projects || []);
      renderWorks(d.works || []);
      renderJobs(d.jobs || []);
      renderAnnouncements(d.announcements || []);
      renderShowcase(d.showcase || []);
      renderHomePresets();
      renderHomeMarket(d.marketplace || []);
      renderHomeCommunity(d.community_trending || []);
      renderHomeSections(d.home_sections || []);
    }).catch(function () { renderHomeEmpty(); });
  }

  function typeBadgeLabel(type) {
    var map = { image: 'Image', video: 'Video', music: 'Music', voice: 'Voice', avatar: 'Avatar', writing: 'Writing' };
    return map[type] || (type ? String(type) : 'Work');
  }

  function workThumbHtml(w) {
    var thumb = w.thumbnail_url || w.thumbnail || w.image_url || w.output_url || w.asset_url || '';
    if (w.asset_missing) {
      return '<div class="yai-work-card-thumb yai-work-card-thumb--placeholder">!</div>';
    }
    if (thumb && (w.type === 'image' || w.type === 'video' || w.type === 'avatar' || !w.type)) {
      return '<div class="yai-work-card-thumb"><img src="' + esc(thumb) + '" alt=""></div>';
    }
    return '<div class="yai-work-card-thumb yai-work-card-thumb--placeholder">' + esc(typeBadgeLabel(w.type).substring(0, 1)) + '</div>';
  }

  function workCardHtml(w, compact) {
    var id = w.id || '';
    var hover = compact ? '' :
      '<div class="yai-work-card-hover">' +
        '<button type="button" data-work-action="open" data-work-id="' + esc(id) + '">열기</button>' +
        '<button type="button" data-work-action="regenerate" data-work-id="' + esc(id) + '">재사용</button>' +
        '<button type="button" data-work-action="project" data-work-id="' + esc(id) + '">프로젝트</button>' +
        '<button type="button" data-work-action="share" data-work-id="' + esc(id) + '">공유</button>' +
        '<button type="button" data-work-action="delete" data-work-id="' + esc(id) + '">삭제</button>' +
      '</div>';
  return '<article class="yai-work-card yai-row--work" data-work-id="' + esc(id) + '" data-work-type="' + esc(w.type || 'image') + '" role="button" tabindex="0">' +
      workThumbHtml(w) +
      hover +
      '<div class="yai-work-card-body">' +
        '<h3 class="yai-work-card-title">' + esc(w.title || '작품') + '</h3>' +
        '<div class="yai-work-card-meta">' +
          '<span class="yai-work-type-badge">' + esc(w.type_label || typeBadgeLabel(w.type)) + '</span>' +
          '<span>' + esc(w.provider || '—') + '</span>' +
          '<span>' + relTime(w.updated_at || w.created_at) + '</span>' +
          (w.project_title ? '<span>' + esc(w.project_title) + '</span>' : '') +
        '</div>' +
      '</div>' +
      '<div class="yai-work-card-menu">' +
        '<button type="button" class="yai-work-menu-btn" data-work-menu="' + esc(id) + '" aria-label="작품 메뉴">⋯</button>' +
        '<div class="yai-work-menu" hidden>' +
          '<button type="button" data-work-action="open" data-work-id="' + esc(id) + '">열기</button>' +
          '<button type="button" data-work-action="regenerate" data-work-id="' + esc(id) + '">프롬프트 재사용</button>' +
          '<button type="button" data-work-action="project" data-work-id="' + esc(id) + '">프로젝트에 추가</button>' +
          '<button type="button" data-work-action="download" data-work-id="' + esc(id) + '">다운로드</button>' +
          '<button type="button" data-work-action="marketplace" data-work-id="' + esc(id) + '">Marketplace</button>' +
          '<button type="button" data-work-action="delete" data-work-id="' + esc(id) + '">삭제</button>' +
        '</div>' +
      '</div>' +
    '</article>';
  }

  function renderHomeSections(sections) {
    var el = document.getElementById('yai-home-sections');
    if (!el) return;
    if (!sections.length) {
      el.innerHTML = '';
      return;
    }
    el.innerHTML = sections.map(function (section) {
      var works = section.works || [];
      var cards = works.length
        ? works.map(function (w) { return workCardHtml(w, true); }).join('')
        : '<p class="yai-muted">표시할 작품이 없습니다.</p>';
      return '<section class="yai-home-section" data-home-section="' + esc(section.id || '') + '">' +
        '<div class="yai-home-section-head">' +
          '<div><h2>' + esc(section.title || 'Section') + '</h2><p>' + esc(section.description || '') + '</p></div>' +
          '<button type="button" class="yai-text-btn" data-route="works">더보기</button>' +
        '</div>' +
        '<div class="yai-home-section-row">' + cards + '</div>' +
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
      el.innerHTML = '<p class="yai-muted">Marketplace에 등록된 작품이 없습니다.</p>';
      return;
    }
    el.innerHTML = items.map(function (it) {
      return '<article class="yai-discover-card" data-route="market"><strong>' + esc(it.title || 'Work') + '</strong>' +
        '<span>' + esc(it.creator || 'Creator') + ' · ' + (it.price === 0 || it.price === '0' ? 'Free' : fmt(it.price) + ' KRW') + '</span></article>';
    }).join('');
  }

  function renderHomeCommunity(items) {
    var el = document.getElementById('yai-home-community-trending');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = '<p class="yai-muted">Community에 공유된 작품이 없습니다.</p>';
      return;
    }
    el.innerHTML = items.map(function (it) {
      return '<article class="yai-discover-card" data-route="community"><strong>' + esc(it.title || 'Work') + '</strong>' +
        '<span>' + esc(it.creator || 'Creator') + ' · ♥ ' + fmt(it.likes || 0) + '</span></article>';
    }).join('');
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
    var el = document.getElementById('yai-home-usage');
    if (!el) return;
    var pct = mu.percent || 0;
    el.innerHTML =
      '<div class="yai-usage-ring">' +
      '<svg viewBox="0 0 36 36"><path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="3"/>' +
      '<path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#d8a63a" stroke-width="3" stroke-dasharray="' + pct + ',100"/></svg>' +
      '<strong>' + pct + '%</strong><span>' + fmt(mu.used || 0) + ' credits used this month</span>' +
      '<button type="button" class="yai-btn--outline" data-route="credits">Recharge Credits</button></div>';
  }

  function renderProjects(items) {
    var el = document.getElementById('yai-home-projects');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = emptyBlock('', 'No projects yet', 'Create your first project to organize AI work.', 'Start Project', 'projects');
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
    return '<article class="yai-card yai-project-card" data-project-open="' + esc(p.id) + '">' +
      '<div class="yai-project-card-cover">' + (p.thumbnail_url
        ? '<img src="' + esc(p.thumbnail_url) + '" alt="">'
        : '<div class="yai-project-card-cover--gold">Gold Crystal</div>') +
      '</div>' +
      '<strong>' + esc(p.title) + '</strong>' +
      '<p>' + esc(p.description || 'No description') + '</p>' +
      '<span>' + esc(p.type) + ' · ' + esc(vis) + ' · ' + fmt(p.asset_count || p.items || 0) + ' items · ' + relTime(p.updated_at || p.created_at) + '</span>' +
      '<div class="yai-project-actions">' +
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

  function ensureProjectModal() {
    var modal = document.getElementById('yai-project-modal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'yai-project-modal';
    modal.className = 'yai-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="yai-modal-backdrop" data-yai-modal-close></div>' +
      '<div class="yai-modal-panel" role="dialog" aria-labelledby="yai-project-modal-title">' +
        '<header class="yai-modal-head"><h2 id="yai-project-modal-title">Create Project</h2><button type="button" class="yai-modal-close" data-yai-modal-close aria-label="Close">&times;</button></header>' +
        '<form id="yai-project-form" class="yai-modal-body">' +
          '<label class="yai-field"><span>Project title</span><input type="text" name="title" required maxlength="120" placeholder="My AI project"></label>' +
          '<label class="yai-field"><span>Description</span><textarea name="description" rows="3" maxlength="500" placeholder="What is this project for?"></textarea></label>' +
          '<label class="yai-field"><span>Type / category</span><select name="type">' +
            '<option value="mixed">Mixed</option><option value="video">Video</option><option value="image">Image</option>' +
            '<option value="music">Music</option><option value="writing">Writing</option><option value="avatar">Avatar</option><option value="voice">Voice</option>' +
          '</select></label>' +
          '<label class="yai-field"><span>Visibility</span><select name="visibility"><option value="private">Private</option><option value="public">Public</option></select></label>' +
          '<p class="yai-modal-error" id="yai-project-form-error" hidden></p>' +
          '<footer class="yai-modal-foot"><button type="button" class="yai-btn--outline" data-yai-modal-close>Cancel</button><button type="submit" class="yai-btn yai-btn--gold">Create</button></footer>' +
        '</form>' +
      '</div>';
    document.body.appendChild(modal);

    modal.addEventListener('click', function (e) {
      if (e.target.closest('[data-yai-modal-close]')) closeProjectModal();
    });

    var form = modal.querySelector('#yai-project-form');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      submitProjectCreate(form);
    });

    return modal;
  }

  function openProjectModal() {
    if (!requireLogin()) return;
    var modal = ensureProjectModal();
    var form = modal.querySelector('#yai-project-form');
    form.reset();
    var err = modal.querySelector('#yai-project-form-error');
    if (err) { err.hidden = true; err.textContent = ''; }
    modal.hidden = false;
    document.body.classList.add('yai-modal-open');
    var titleInput = form.querySelector('[name="title"]');
    if (titleInput) titleInput.focus();
  }

  function closeProjectModal() {
    var modal = document.getElementById('yai-project-modal');
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('yai-modal-open');
  }

  function submitProjectCreate(form) {
    var errEl = document.getElementById('yai-project-form-error');
    var submitBtn = form.querySelector('[type="submit"]');
    var payload = {
      title: (form.title.value || '').trim(),
      description: (form.description.value || '').trim(),
      type: form.type.value || 'mixed',
      visibility: form.visibility.value || 'private'
    };

    if (!payload.title) {
      if (errEl) { errEl.textContent = 'Project title is required.'; errEl.hidden = false; }
      return;
    }

    if (errEl) errEl.hidden = true;
    if (submitBtn) submitBtn.disabled = true;

    Core.projects.create(payload).then(function (res) {
      closeProjectModal();
      showToast('Project created successfully.');
      loaded.projects = false;
      loadProjects();
      refreshHomeProjects();
    }).catch(function (err) {
      var msg = err.message || 'Failed to create project.';
      if (errEl) { errEl.textContent = msg; errEl.hidden = false; }
      showToast(msg, true);
    }).finally(function () {
      if (submitBtn) submitBtn.disabled = false;
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
    if (!items.length) {
      el.innerHTML = emptyBlock('', 'No works saved', 'Generate in any studio and save to Gallery.', 'Open Gallery', 'works');
      return;
    }
    el.innerHTML = items.slice(0, 8).map(function (w) { return workCardHtml(w, false); }).join('');
  }

  function handleWorkAction(action, workId) {
    if (!workId || !global.YooYGallery) return;
    if (action === 'open' || action === 'marketplace') {
      global.YooYGallery.openDetail(workId);
      return;
    }
    if (!Core.gallery) return;
    if (action === 'regenerate') {
      Core.gallery.regenerate(workId).then(function (res) {
        try { sessionStorage.setItem('yoy_regenerate', JSON.stringify(res.data || {})); } catch (e) { /* ignore */ }
        if (global.YooYStudioRoute) global.YooYStudioRoute('image');
      });
      return;
    }
    if (action === 'download') {
      Core.gallery.download(workId).then(function (res) {
        var info = res.data || {};
        if (info.url) { var a = document.createElement('a'); a.href = info.url; a.download = info.filename || 'download'; a.target = '_blank'; a.click(); }
      });
      return;
    }
    if (action === 'share') {
      Core.gallery.share(workId).then(function (res) {
        var url = (res.data && res.data.url) || '';
        if (url && navigator.clipboard) navigator.clipboard.writeText(url);
      });
      return;
    }
    if (action === 'project') {
      openProjectPicker(workId);
      return;
    }
    if (action === 'delete') {
      if (!confirm('이 작품을 삭제하시겠습니까?')) return;
      Core.gallery.remove(workId).then(function () { loadHome(); });
    }
  }

  function openWorkPreview(work) {
    if (!work || !work.url) return;
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
      body.innerHTML = '<video src="' + esc(work.url) + '" controls autoplay style="max-width:100%"></video>';
    } else if (work.type === 'music' || work.type === 'voice') {
      body.innerHTML = '<audio src="' + esc(work.url) + '" controls autoplay style="width:100%"></audio>';
    } else {
      body.innerHTML = '<img src="' + esc(work.url) + '" alt="" style="max-width:100%;border-radius:12px">';
    }
    overlay.hidden = false;
  }

  function renderJobs(items) {
    var el = document.getElementById('yai-home-jobs');
    if (!el) return;
    var compact = (items || []).slice(0, 7);
    if (!compact.length) {
      el.innerHTML = emptyBlock('', 'No activity yet', 'Your generation history appears here.', 'Create Now', 'video');
      return;
    }
    el.innerHTML = compact.map(function (j) {
      var status = String(j.status || 'pending').toLowerCase();
      var label = typeBadgeLabel(j.type || j.studio || 'Generation');
      return '<div class="yai-timeline-item">' + statusTag(status) +
        '<div><strong>' + esc(label) + '</strong><span>' + relTime(j.updated_at || j.created_at) + '</span></div></div>';
    }).join('');
  }

  function renderAnnouncements(items) {
    var el = document.getElementById('yai-home-announcements');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = emptyBlock('', 'No announcements', 'Platform updates will appear here.', 'Settings', 'settings');
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
      el.innerHTML = emptyBlock('', 'Showcase is empty', 'Share works to Community to populate the showcase.', 'Community', 'community');
      return;
    }
    el.innerHTML = items.map(function (it) {
      return '<article class="yai-showcase-card"><span>' + esc(it.type_label || it.type || 'Work') + '</span>' +
        '<h3>' + esc(it.title || 'Work') + '</h3><p>' + esc(it.prompt || it.creator || '') + '</p></article>';
    }).join('');
  }

  function renderHomeEmpty() {
    setText('yai-stat-credits', '—');
    setText('yai-stat-projects', '0');
    setText('yai-stat-jobs', '0');
    setText('yai-stat-likes', '0');
    setText('yai-stat-usage', '0 / 0');
    setText('yai-top-credits', 'Login to start creating');
    var head = document.querySelector('.yai-home-head h1');
    if (head && !isLoggedIn()) {
      head.textContent = 'Login to start creating ✨';
    }
    renderProjects([]);
    renderWorks([]);
    renderJobs([]);
    renderAnnouncements([]);
    renderShowcase([]);
    renderHomeSections([]);
    renderUsageWidget({ used: 0, limit: 0, percent: 0 });
  }

  function openProjectDetail(projectId) {
    if (!projectId || !requireLogin()) return;
    currentProjectId = projectId;
    projectDetailFilter = 'all';
    loaded['project-detail'] = false;
    route('project-detail');
  }

  function loadProjectDetail() {
    if (!currentProjectId) {
      route('projects');
      return;
    }
    var titleEl = document.getElementById('yai-project-detail-title');
    var descEl = document.getElementById('yai-project-detail-desc');
    var coverEl = document.getElementById('yai-project-detail-cover');
    var filtersEl = document.getElementById('yai-project-detail-filters');
    var worksEl = document.getElementById('yai-project-detail-works');
    if (!worksEl) return;

    worksEl.innerHTML = '<div class="yai-empty"><p>Loading project…</p></div>';

    Promise.all([
      Core.projects.get(currentProjectId),
      Core.gallery.works({ project_id: currentProjectId })
    ]).then(function (results) {
      var project = (results[0].data && results[0].data.project) || {};
      var works = (results[1].data && (results[1].data.works || results[1].data.items)) || [];

      if (titleEl) titleEl.textContent = project.title || 'Project';
      if (descEl) descEl.textContent = project.description || '프로젝트 작품을 관리합니다.';
      if (coverEl) {
        coverEl.innerHTML = project.thumbnail_url
          ? '<img src="' + esc(project.thumbnail_url) + '" alt="">'
          : '<div class="yai-project-card-cover--gold" style="min-height:180px;display:flex;align-items:center;justify-content:center;">Gold Crystal</div>';
      }

      var types = ['all', 'image', 'video', 'music', 'voice'];
      if (filtersEl) {
        filtersEl.innerHTML = types.map(function (t) {
          var label = t === 'all' ? 'All' : typeBadgeLabel(t);
          var active = projectDetailFilter === t ? ' is-active' : '';
          return '<button type="button" class="yai-filter-chip' + active + '" data-project-filter="' + t + '">' + label + '</button>';
        }).join('');
      }

      var filtered = projectDetailFilter === 'all'
        ? works
        : works.filter(function (w) { return w.type === projectDetailFilter; });

      if (!filtered.length) {
        worksEl.innerHTML = emptyBlock('', 'No works in project', 'Gallery에서 작품을 이 프로젝트에 추가하세요.', 'Gallery', 'works');
        return;
      }
      worksEl.innerHTML = filtered.map(function (w) { return workCardHtml(w, false); }).join('');
    }).catch(function (err) {
      worksEl.innerHTML = emptyBlock('', 'Could not load project', err.message || 'Request failed.', 'Back', 'projects');
    });
  }

  function ensureProjectPickerModal() {
    var modal = document.getElementById('yai-project-picker-modal');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'yai-project-picker-modal';
    modal.className = 'yai-modal';
    modal.hidden = true;
    modal.innerHTML =
      '<div class="yai-modal-backdrop" data-yai-picker-close></div>' +
      '<div class="yai-modal-panel" role="dialog">' +
        '<header class="yai-modal-head"><h2>프로젝트 선택</h2><button type="button" class="yai-modal-close" data-yai-picker-close>&times;</button></header>' +
        '<div class="yai-modal-body"><div id="yai-project-picker-list"></div></div>' +
        '<footer class="yai-modal-foot"><button type="button" class="yai-btn--outline" data-yai-picker-close>취소</button></footer>' +
      '</div>';
    document.body.appendChild(modal);
    modal.addEventListener('click', function (e) {
      if (e.target.closest('[data-yai-picker-close]')) closeProjectPicker();
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
        list.innerHTML = '<p class="yai-muted">프로젝트가 없습니다. 먼저 프로젝트를 생성하세요.</p>' +
          '<button type="button" class="yai-btn yai-btn--gold" data-yai-create-project style="margin-top:12px">Create Project</button>';
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
    if (!pickerWorkId || !Core.gallery) return;
    Core.gallery.project(pickerWorkId, projectId).then(function () {
      closeProjectPicker();
      showToast(projectId ? '프로젝트에 추가했습니다.' : '프로젝트에서 제거했습니다.');
      loadHome();
      if (loaded['project-detail']) loadProjectDetail();
    }).catch(function (err) {
      showToast(err.message || 'Failed to update project.', true);
    });
  }

  function loadProjects() {
    var el = document.getElementById('yai-projects-list');
    if (!el) return;
    el.innerHTML = '<div class="yai-empty"><p>Loading projects…</p></div>';
    Core.projects.list().then(function (res) {
      var items = (res.data && res.data.projects) || [];
      if (!items.length) {
        el.innerHTML = emptyBlock('', 'No projects', 'Start your first project.', 'Create', null, true);
        return;
      }
      el.innerHTML = '<div class="yai-project-grid">' + items.map(projectCardHtml).join('') + '</div>';
    }).catch(function (err) {
      el.innerHTML = emptyBlock('', 'Could not load projects', err.message || 'Request failed.', 'Retry', 'projects');
      showToast(err.message || 'Failed to load projects.', true);
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

    if (e.target.closest('[data-yai-close-modal]')) {
      hideLoginModal();
      return;
    }

    if (e.target.id === 'yai-login-modal') {
      hideLoginModal();
      return;
    }

    var createBtn = e.target.closest('[data-yai-create-project]');
    if (createBtn) {
      e.preventDefault();
      if (!requireLogin()) return;
      openProjectModal();
      return;
    }

    var pickerProject = e.target.closest('[data-picker-project]');
    if (pickerProject) {
      e.preventDefault();
      assignWorkToProject(pickerProject.getAttribute('data-picker-project') || '');
      return;
    }

    var projectOpen = e.target.closest('[data-project-open]');
    if (projectOpen && !e.target.closest('.yai-project-rename') && !e.target.closest('.yai-project-delete')) {
      e.preventDefault();
      openProjectDetail(projectOpen.getAttribute('data-project-open'));
      return;
    }

    var filterChip = e.target.closest('[data-project-filter]');
    if (filterChip) {
      e.preventDefault();
      projectDetailFilter = filterChip.getAttribute('data-project-filter') || 'all';
      loadProjectDetail();
      return;
    }

    var workRow = e.target.closest('.yai-work-card, .yai-row--work');
    if (workRow) {
      var quickAction = e.target.closest('[data-work-action]');
      if (quickAction) {
        e.preventDefault();
        e.stopPropagation();
        handleWorkAction(quickAction.getAttribute('data-work-action'), quickAction.getAttribute('data-work-id'));
        return;
      }
      if (e.target.closest('[data-work-menu]') || e.target.closest('.yai-work-menu')) {
        var menuBtn = e.target.closest('[data-work-menu]');
        if (menuBtn) {
          e.preventDefault();
          e.stopPropagation();
          var wrap = menuBtn.closest('.yai-work-menu-wrap, .yai-work-card-menu');
          var menu = wrap && wrap.querySelector('.yai-work-menu');
          if (menu) menu.hidden = !menu.hidden;
        }
        var actionBtn = e.target.closest('[data-work-action]');
        if (actionBtn) {
          e.preventDefault();
          e.stopPropagation();
          handleWorkAction(actionBtn.getAttribute('data-work-action'), actionBtn.getAttribute('data-work-id'));
        }
        return;
      }
      e.preventDefault();
      var workId = workRow.getAttribute('data-work-id');
      if (workId && global.YooYGallery && global.YooYGallery.openDetail) {
        global.YooYGallery.openDetail(workId);
      }
      return;
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
    route(btn.dataset.route);
  });

  var createBtn = document.getElementById('yai-home-create');
  if (createBtn) {
    createBtn.addEventListener('click', function () {
      launchFromHome();
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

  var projectEditBtn = document.getElementById('yai-project-detail-edit');
  if (projectEditBtn) {
    projectEditBtn.addEventListener('click', function () {
      if (!currentProjectId || !requireLogin()) return;
      Core.projects.get(currentProjectId).then(function (res) {
        var project = (res.data && res.data.project) || {};
        var title = window.prompt('Project title', project.title || '');
        if (!title || !title.trim()) return;
        var description = window.prompt('Project description', project.description || '') || '';
        return Core.projects.update(currentProjectId, { title: title.trim(), description: description.trim() });
      }).then(function () {
        showToast('Project updated.');
        loadProjectDetail();
        refreshHomeProjects();
      }).catch(function (err) { showToast(err.message, true); });
    });
  }

  var projectDeleteBtn = document.getElementById('yai-project-detail-delete');
  if (projectDeleteBtn) {
    projectDeleteBtn.addEventListener('click', function () {
      if (!currentProjectId || !requireLogin()) return;
      if (!window.confirm('Delete this project?')) return;
      Core.projects.delete(currentProjectId).then(function () {
        showToast('Project deleted.');
        currentProjectId = '';
        route('projects');
        loadHome();
      }).catch(function (err) { showToast(err.message, true); });
    });
  }

  loadProfile();
  watchBillingReturn();
  route('home');
  window.YooYStudioRoute = route;
  window.YooYStudioOpenProject = openProjectDetail;
  window.YooYStudioPickProject = openProjectPicker;
  } catch (studioErr) {
    if (window.console && window.console.error) {
      window.console.error('[YooYStudio] init failed', studioErr);
    }
  }
})();
