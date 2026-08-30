/**
 * YooY Studio — Home dashboard (server-driven sections via home_sections API).
 */
(function (global) {
  'use strict';

  var CFG = global.YooYHomeDashboardConfig;
  if (!CFG) return;

  var state = {
    sections: CFG.cloneDefaults(),
    feed: {},
    account: {},
    planMenuOpen: false,
    serverConfigured: false,
  };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmt(n) {
    var x = Number(n);
    if (!Number.isFinite(x) || x < 0) x = 0;
    try { return Math.floor(x).toLocaleString('ko-KR'); } catch (e) { return String(x); }
  }

  function isAdmin() {
    var cfg = global.YooYStudio || {};
    return !!cfg.isAdmin;
  }

  function sectionDisplayType(section) {
    return section.display_type || section.type || 'gallery';
  }

  function sortedVisibleSections() {
    return (state.sections || [])
      .filter(function (s) { return s.visible !== false; })
      .sort(function (a, b) { return (a.order || 0) - (b.order || 0); });
  }

  function fallbackWorks(feed, section) {
    var all = (feed && feed.works) || [];
    var source = section.data_source || '';
    if (source === 'community') return (feed && feed.community_trending) || all;
    if (source === 'marketplace') return (feed && feed.marketplace) || all;
    if (source === 'templates') return (feed && feed.showcase) || all;
    if (section.id === 'focus' || (section.filter && section.filter.orientation === 'portrait')) {
      return all.filter(function (w, i) { return i % 2 === 0; });
    }
    if (section.id === 'wide' || (section.filter && section.filter.orientation === 'landscape')) {
      return all.filter(function (w, i) { return i % 2 === 1; });
    }
    return all;
  }

  function sectionWorks(section, feed) {
    if (Array.isArray(section.works) && section.works.length) return section.works;
    if (Array.isArray(section.projects) && section.projects.length) return section.projects;
    return fallbackWorks(feed, section);
  }

  function jobsFromFeed(feed) {
    return (feed && feed.jobs) || [];
  }

  function layoutClass(section) {
    var layout = section.layout || (section.column_count === 'carousel' ? 'carousel' : 'grid');
    return layout === 'carousel' ? 'yai-hd-carousel' : 'yai-hd-grid';
  }

  function ratioClass(section) {
    var ratio = section.card_ratio || 'auto';
    if (ratio === 'portrait' || ratio === '3/4') return 'yai-hd-section--portrait';
    if (ratio === 'wide' || ratio === 'landscape' || ratio === '16/9') return 'yai-hd-section--wide';
    return '';
  }

  function thumbHtml(item) {
    var url = item.thumbnail_url || item.display_url || item.large_url || item.cover || '';
    if (url) {
      return '<div class="yai-hd-thumb"><img src="' + esc(url) + '" alt="" loading="lazy"></div>';
    }
    return '<div class="yai-hd-thumb yai-hd-thumb--empty" aria-hidden="true">✦</div>';
  }

  function galleryCard(item) {
    var title = item.title || item.name || '작품';
    var id = item.id || '';
    return '<article class="yai-hd-card yai-hd-card--gallery" data-work-id="' + esc(id) + '" tabindex="0" role="button">' +
      thumbHtml(item) +
      '<div class="yai-hd-card__body"><strong>' + esc(title) + '</strong></div>' +
    '</article>';
  }

  function projectCard(item) {
    var title = item.title || item.name || 'Project';
    var id = item.id || '';
    return '<article class="yai-hd-card yai-hd-card--gallery" data-project-open="' + esc(id) + '" tabindex="0" role="button">' +
      thumbHtml(item) +
      '<div class="yai-hd-card__body"><strong>' + esc(title) + '</strong><span>Project</span></div>' +
    '</article>';
  }

  function templateCard(item) {
    var title = item.title || item.label || item.name || '템플릿';
    var seed = item.seed || item.seed_prompt || item.prompt || title;
    return '<article class="yai-hd-card yai-hd-card--template" data-template-seed="' + esc(seed) + '" tabindex="0" role="button">' +
      thumbHtml(item) +
      '<div class="yai-hd-card__body"><strong>' + esc(title) + '</strong><span>바로 사용</span></div>' +
    '</article>';
  }

  function recentRow(item) {
    var title = item.title || item.type_label || item.type || '작업';
    var status = item.status || 'active';
    return '<article class="yai-hd-recent" data-job-id="' + esc(item.id || '') + '">' +
      '<div class="yai-hd-recent__icon" aria-hidden="true">◆</div>' +
      '<div class="yai-hd-recent__body"><strong>' + esc(title) + '</strong><span>' + esc(status) + '</span></div>' +
      '<button type="button" class="yai-text-btn" data-route="history">열기</button>' +
    '</article>';
  }

  function guideBlock() {
    return '<div class="yai-hd-guide-grid">' +
      '<article class="yai-hd-guide-card"><span>1</span><strong>아이디어 입력</strong><p>히어로 입력창에 만들고 싶은 것을 적어 보세요.</p></article>' +
      '<article class="yai-hd-guide-card"><span>2</span><strong>스튜디오 선택</strong><p>추천 칩이나 빠른 도구로 Image·Video 등을 고르세요.</p></article>' +
      '<article class="yai-hd-guide-card"><span>3</span><strong>결과 저장</strong><p>Gallery와 Project에 자동으로 정리됩니다.</p></article>' +
    '</div>';
  }

  function emptySectionCopy(section) {
    return '<div class="yai-hd-empty">' +
      '<p class="yai-muted">아직 표시할 ' + esc(section.title) + '이 없습니다.</p>' +
      '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-route="assistant">AI Assistant로 시작</button>' +
    '</div>';
  }

  function moreRouteFor(section) {
    var dt = sectionDisplayType(section);
    var src = section.data_source || '';
    if (dt === 'guide') return 'assistant';
    if (dt === 'recent') return 'history';
    if (dt === 'template') return 'prompt-library';
    if (dt === 'projects') return 'projects';
    if (src === 'community') return 'community';
    if (src === 'marketplace') return 'market';
    return 'works';
  }

  function renderSectionBlock(section, feed) {
    var dt = sectionDisplayType(section);
    var limit = Number(section.limit) || 12;
    var body = '';
    var layout = layoutClass(section);
    var ratioCls = ratioClass(section);

    if (dt === 'guide') {
      body = guideBlock();
    } else if (dt === 'recent') {
      var jobs = jobsFromFeed(feed).slice(0, limit);
      body = jobs.length
        ? '<div class="yai-hd-recent-list">' + jobs.map(recentRow).join('') + '</div>'
        : emptySectionCopy(section);
    } else if (dt === 'projects') {
      var projects = (section.projects && section.projects.length)
        ? section.projects
        : (feed && feed.projects) || [];
      body = projects.length
        ? '<div class="' + layout + '">' + projects.slice(0, limit).map(projectCard).join('') + '</div>'
        : emptySectionCopy(section);
    } else if (dt === 'template') {
      var tpl = sectionWorks(section, feed).slice(0, limit);
      body = tpl.length
        ? '<div class="' + layout + '">' + tpl.map(templateCard).join('') + '</div>'
        : emptySectionCopy(section);
    } else {
      var works = sectionWorks(section, feed).slice(0, limit);
      body = works.length
        ? '<div class="' + layout + '">' + works.map(galleryCard).join('') + '</div>'
        : emptySectionCopy(section);
    }

    return '<section class="yai-hd-section ' + ratioCls + '" data-section-id="' + esc(section.id) + '" data-section-type="' + esc(dt) + '">' +
      '<header class="yai-hd-section__head">' +
        '<div><h2>' + esc(section.title) + '</h2><p>' + esc(section.description || '') + '</p></div>' +
        '<button type="button" class="yai-text-btn" data-route="' + esc(moreRouteFor(section)) + '">전체 보기</button>' +
      '</header>' +
      '<div class="yai-hd-section__body">' + body + '</div>' +
    '</section>';
  }

  function renderSections() {
    var root = document.getElementById('yai-home-sections-root');
    if (!root) return;
    var html = sortedVisibleSections().map(function (s) {
      return renderSectionBlock(s, state.feed);
    }).join('');
    root.innerHTML = html || '<p class="yai-muted">표시할 홈 섹션이 없습니다.</p>';
  }

  function sourceLabel(source) {
    var map = {
      gallery: 'Gallery',
      projects: 'Projects',
      templates: 'Templates',
      community: 'Community',
      marketplace: 'Marketplace',
      guide: 'Guide',
    };
    return map[source] || source || '—';
  }

  function renderSectionManager() {
    var aside = document.querySelector('.yai-hd-aside');
    var panel = document.getElementById('yai-home-section-manager');
    if (!panel) return;

    if (!isAdmin()) {
      if (aside) aside.hidden = true;
      var layout = document.querySelector('.yai-hd-layout');
      if (layout) layout.classList.add('yai-hd-layout--full');
      return;
    }

    if (aside) aside.hidden = false;
    var sorted = sortedVisibleSections().concat(
      (state.sections || []).filter(function (s) { return s.visible === false; })
    ).sort(function (a, b) { return (a.order || 0) - (b.order || 0); });

    var configNote = state.serverConfigured
      ? '서버 설정이 적용 중입니다. 변경은 Admin Console에서 저장됩니다.'
      : '서버 설정 없음 — 기본 섹션 레이아웃을 사용 중입니다.';

    panel.innerHTML =
      '<header class="yai-hd-manager__head"><h3>섹션 관리</h3><p class="yai-muted">' + esc(configNote) + '</p></header>' +
      '<div class="yai-hd-manager__list">' + sorted.map(function (s) {
        return '<div class="yai-hd-manager__row yai-hd-manager__row--readonly">' +
          '<span class="yai-hd-manager__title">' + esc(s.title) + '</span>' +
          '<span class="yai-hd-manager__meta">' + esc(sourceLabel(s.data_source)) + ' · ' + esc(sectionDisplayType(s)) + '</span>' +
          '<span class="yai-hd-manager__status">' + (s.visible !== false ? '표시' : '숨김') + '</span>' +
        '</div>';
      }).join('') + '</div>' +
      '<footer class="yai-hd-manager__foot">' +
        '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-route="admin-console" data-admin-section="home-sections">Admin Console에서 편집</button>' +
      '</footer>';
  }

  function renderHeroChips() {
    var el = document.getElementById('yai-home-hero-chips');
    if (!el) return;
    el.innerHTML = CFG.HERO_CHIPS.map(function (chip) {
      return '<button type="button" class="yai-hd-chip" data-hero-chip data-studio="' + esc(chip.studio) + '" data-seed="' + esc(chip.seed) + '">' + esc(chip.label) + '</button>';
    }).join('');
  }

  function renderQuickTools() {
    var el = document.getElementById('yai-home-quick-tools');
    if (!el) return;
    el.innerHTML = CFG.QUICK_TOOLS.map(function (tool) {
      return '<button type="button" class="yai-hd-quick" data-quick-tool data-studio="' + esc(tool.studio) + '" data-seed="' + esc(tool.seed) + '">' +
        '<span class="yai-hd-quick__label">' + esc(tool.label) + '</span></button>';
    }).join('');
  }

  function applySeedToHero(seed, studio) {
    var input = document.getElementById('yai-home-prompt');
    if (input && seed) input.value = seed;
    try {
      if (seed) sessionStorage.setItem('yoy_home_prompt', seed);
      if (studio) sessionStorage.setItem('yoy_home_studio', studio);
    } catch (e) { /* noop */ }
  }

  function bindHeroAndTools() {
    document.addEventListener('click', function (e) {
      var chip = e.target.closest('[data-hero-chip]');
      if (chip) {
        e.preventDefault();
        applySeedToHero(chip.getAttribute('data-seed'), chip.getAttribute('data-studio'));
        return;
      }
      var tool = e.target.closest('[data-quick-tool]');
      if (tool) {
        e.preventDefault();
        applySeedToHero(tool.getAttribute('data-seed'), tool.getAttribute('data-studio'));
        if (global.YooYStudioRoute && tool.getAttribute('data-studio')) {
          global.YooYStudioRoute(tool.getAttribute('data-studio'));
        }
        return;
      }
      var tpl = e.target.closest('[data-template-seed]');
      if (tpl) {
        e.preventDefault();
        applySeedToHero(tpl.getAttribute('data-template-seed'), 'image');
        if (global.YooYStudioRoute) global.YooYStudioRoute('assistant');
        return;
      }
      var work = e.target.closest('[data-work-id]');
      if (work && work.getAttribute('data-work-id')) {
        e.preventDefault();
        if (global.YooYStudioRoute) global.YooYStudioRoute('works');
      }
    });
  }

  function updatePlanDropdown(acc) {
    acc = acc || state.account || {};
    var planName = acc.plan_name || acc.tier || 'Free';
    var planId = String(acc.plan || acc.tier || 'free').toLowerCase();
    var current = document.getElementById('yai-plan-dropdown-current');
    if (current) current.textContent = planName;

    var btn = document.getElementById('yai-pro-plan-btn');
    if (btn) {
      btn.textContent = planId === 'free' ? 'Pro 플랜' : (planName + ' ▾');
    }

    var sidebarPlan = document.getElementById('yai-sidebar-plan-name');
    if (sidebarPlan) sidebarPlan.textContent = planName;
  }

  function updateCreditCard(acc) {
    acc = acc || state.account || {};
    state.account = acc;
    var total = acc.plan_credits != null ? acc.plan_credits : (acc.credits || 0);
    var remaining = acc.unlimited ? null : (acc.remaining != null ? acc.remaining : acc.balance);
    var totalEl = document.getElementById('yai-credit-total');
    var remainEl = document.getElementById('yai-credit-remaining');
    if (totalEl) totalEl.textContent = fmt(total);
    if (remainEl) remainEl.textContent = acc.unlimited ? '∞' : fmt(remaining);
    updatePlanDropdown(acc);
  }

  function closePlanMenu() {
    state.planMenuOpen = false;
    var menu = document.getElementById('yai-plan-dropdown-menu');
    if (menu) menu.hidden = true;
    var btn = document.getElementById('yai-pro-plan-btn');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function openPlanMenu() {
    state.planMenuOpen = true;
    var menu = document.getElementById('yai-plan-dropdown-menu');
    if (menu) menu.hidden = false;
    var btn = document.getElementById('yai-pro-plan-btn');
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }

  function bindPlanDropdown() {
    var btn = document.getElementById('yai-pro-plan-btn');
    if (!btn || btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (state.planMenuOpen) closePlanMenu();
      else openPlanMenu();
    });

    document.addEventListener('click', function (e) {
      if (!state.planMenuOpen) return;
      if (e.target.closest('#yai-plan-dropdown-wrap')) return;
      closePlanMenu();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closePlanMenu();
    });

    var menu = document.getElementById('yai-plan-dropdown-menu');
    if (menu) {
      menu.addEventListener('click', function (e) {
        if (!e.target.closest('[data-plan-action]')) return;
        closePlanMenu();
      });
    }
  }

  function onFeed(feed) {
    feed = feed || {};
    state.feed = feed;
    state.serverConfigured = CFG.hasServerSections(feed);
    state.sections = CFG.sectionsFromFeed(feed);
    renderSectionManager();
    renderSections();
  }

  function init() {
    renderHeroChips();
    renderQuickTools();
    renderSectionManager();
    renderSections();
    bindHeroAndTools();
    bindPlanDropdown();
    updateGreeting();
  }

  function updateGreeting() {
    var el = document.getElementById('yai-hd-greeting-title');
    if (!el) return;
    var cfg = global.YooYStudio || {};
    var name = (cfg.user && cfg.user.name) ? String(cfg.user.name).trim() : '';
    if (name && cfg.loggedIn) {
      el.textContent = '안녕하세요, ' + name + '님! 👋';
    }
  }

  global.YooYHomeDashboard = {
    init: init,
    onFeed: onFeed,
    updateCredits: updateCreditCard,
    getSections: function () { return state.sections.slice(); },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : globalThis);
