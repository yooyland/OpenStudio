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
    workById: {},
    planMenuOpen: false,
    sectionDrawerOpen: false,
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
      .filter(function (s) {
        if (s.visible === false) return false;
        if (!isLoggedInHome()) {
          var dt = sectionDisplayType(s);
          var src = String(s.data_source || s.source || '');
          var sid = String(s.id || '');
          var stitle = String(s.title || '');
          if (dt === 'recent' || dt === 'projects') return false;
          if (src === 'user' || src === 'demo' || src === 'official') return false;
          if (dt === 'template' && (sid.indexOf('saved') !== -1 || stitle.indexOf('저장') !== -1)) return false;
        }
        return true;
      })
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

  function isLoggedInHome() {
    if (global.YooYCore && global.YooYCore.config) return !!global.YooYCore.config.loggedIn;
    if (global.YooYStudio) return !!global.YooYStudio.loggedIn;
    return false;
  }

  function isRealPublicWork(w) {
    if (!w) return false;
    if (w.is_demo || w.feed_source === 'demo' || w.source === 'demo') return false;
    var url = w.thumbnail_url || w.display_url || w.large_url || w.cover || w.url || w.image_url || '';
    if (!url) return false;
    if (url.indexOf('placeholder.svg') !== -1 || url.indexOf('placehold.co') !== -1) return false;
    if (url.indexOf('official-showcase/thumbs') !== -1) return false;
    return true;
  }

  function sectionWorks(section, feed) {
    var list = [];
    if (Array.isArray(section.works) && section.works.length) list = section.works;
    else if (Array.isArray(section.projects) && section.projects.length) list = section.projects;
    else list = fallbackWorks(feed, section);
    var dt = sectionDisplayType(section);
    if (dt === 'template' || dt === 'projects' || (section.data_source || '') === 'templates') {
      return list || [];
    }
    return (list || []).filter(isRealPublicWork);
  }

  function guestSectionTitle(section) {
    if (isLoggedInHome()) return section.title || '';
    var id = String(section.id || '');
    var src = String(section.data_source || section.source || '');
    if (src === 'community' || id.indexOf('community') !== -1 || id.indexOf('best') !== -1) {
      return '인기 작품';
    }
    if (src === 'marketplace' || id.indexOf('market') !== -1) {
      return '이런 작품도 만들어보세요';
    }
    if (id.indexOf('latest') !== -1 || id.indexOf('recent') !== -1 || (section.title || '').indexOf('최근') !== -1) {
      return '최근 공개 작품';
    }
    return section.title || '공개 작품';
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

  function mediaTypeOf(item) {
    var t = String(item.type || item.media_type || item.work_type || item.asset_type || '').toLowerCase();
    if (t.indexOf('video') >= 0) return 'video';
    if (t.indexOf('avatar') >= 0) return 'avatar';
    if (t.indexOf('voice') >= 0 || t.indexOf('tts') >= 0 || t.indexOf('speech') >= 0) return 'voice';
    if (t.indexOf('music') >= 0 || t.indexOf('song') >= 0 || t.indexOf('bgm') >= 0) return 'music';
    if (t.indexOf('audio') >= 0) return 'music';
    if (t.indexOf('translat') >= 0 || t.indexOf('language') >= 0) return 'translator';
    if (t.indexOf('writ') >= 0 || t.indexOf('text') >= 0 || t.indexOf('blog') >= 0) return 'writing';
    return 'image';
  }

  function typeBadgeLabel(type) {
    var map = {
      video: '영상',
      music: '음악',
      voice: '보이스',
      avatar: '아바타',
      writing: '글쓰기',
      translator: '번역',
      image: '이미지',
      audio: '오디오'
    };
    return map[type] || '작품';
  }

  function skeletonIcon(type) {
    var map = {
      video: '▶',
      music: '♪',
      voice: '◎',
      avatar: '☺',
      writing: '✎',
      translator: '文',
      image: '◈',
      audio: '♪'
    };
    return map[type] || '◈';
  }

  function primaryCtaLabel(type) {
    switch (type) {
      case 'video': return '이 영상처럼 만들기';
      case 'music': return '이 스타일로 만들기';
      case 'voice': return '이 스타일로 만들기';
      case 'writing': return '이 형식으로 쓰기';
      case 'translator': return '이 형식으로 번역하기';
      case 'avatar': return '이 캐릭터로 만들기';
      default: return '따라 만들기';
    }
  }

  function studioRouteForType(type) {
    switch (type) {
      case 'video': return 'video';
      case 'music': return 'music';
      case 'voice': return 'voice';
      case 'writing': return 'writing';
      case 'translator': return 'translator';
      case 'avatar': return 'avatar';
      default: return 'image';
    }
  }

  function thumbUrl(item) {
    return item.thumbnail_url || item.display_url || item.large_url || item.cover || item.preview_url || item.url || '';
  }

  function rememberWork(item) {
    if (!item) return;
    var id = item.id || item.gallery_id || '';
    if (id) state.workById[id] = item;
  }

  function buildRemixShell(item) {
    item = item || {};
    var type = mediaTypeOf(item);
    var id = item.id || item.gallery_id || '';
    var preview = thumbUrl(item);
    return {
      source: 'home_remix',
      gallery_id: id,
      id: id,
      type: type,
      studio: studioRouteForType(type),
      prompt: item.prompt || item.source_prompt || item.seed_prompt || item.title || '',
      thumbnail_url: preview,
      preview_url: item.preview_url || item.display_url || preview,
      aspect_ratio: item.aspect_ratio || item.ratio || item.card_ratio || '',
      style: item.style || item.style_meta || item.style_preset || null,
      provider: item.provider || item.provider_id || '',
      model: item.model || item.model_id || '',
      project_id: item.project_id || '',
      reference_assets: preview ? [{ gallery_id: id, url: preview, type: type }] : [],
      content_type: type
    };
  }

  function storeRemixShell(item) {
    var shell = buildRemixShell(item);
    try {
      sessionStorage.setItem('yoy_home_remix', JSON.stringify(shell));
      if (shell.prompt) sessionStorage.setItem('yoy_home_prompt', shell.prompt);
      if (shell.studio) sessionStorage.setItem('yoy_home_studio', shell.studio);
      if (shell.reference_assets && shell.reference_assets[0]) {
        sessionStorage.setItem('yoy_reference_asset', JSON.stringify(shell.reference_assets[0]));
      }
      sessionStorage.setItem('yoy_home_attachment', JSON.stringify({
        type: shell.type || 'image',
        source: 'gallery',
        gallery_id: shell.gallery_id || shell.id || '',
        url: shell.preview_url || shell.thumbnail_url || '',
        preview: shell.thumbnail_url || shell.preview_url || '',
        name: item.title || '',
        title: item.title || ''
      }));
      if (shell.project_id && window.YooYActiveProject && typeof window.YooYActiveProject.set === 'function') {
        window.YooYActiveProject.set({ id: shell.project_id, name: item.title || item.project_name || 'Project' });
      }
    } catch (e) { /* noop */ }
    return shell;
  }

  function thumbHtml(item, section) {
    var type = mediaTypeOf(item);
    var badge = '<span class="yai-hd-type-badge">' + esc(typeBadgeLabel(type)) + '</span>';
    var url = thumbUrl(item);
    var ratioCls = '';
    var play = '';
    var wave = '';
    if (section) {
      var ratio = section.card_ratio || 'auto';
      if (ratio === 'portrait' || ratio === '3/4') ratioCls = ' yai-hd-thumb--portrait';
      else if (ratio === 'wide' || ratio === 'landscape' || ratio === '16/9') ratioCls = ' yai-hd-thumb--landscape';
      else if (type === 'writing' || type === 'translator') ratioCls = ' yai-hd-thumb--writing';
      else if (type === 'video' || type === 'music' || type === 'voice') ratioCls = ' yai-hd-thumb--landscape';
    }
    if (type === 'video') {
      play = '<span class="yai-hd-thumb__play" aria-hidden="true">▶</span>';
      if (item.duration || item.duration_label) {
        play += '<span class="yai-hd-thumb__duration">' + esc(String(item.duration_label || item.duration)) + '</span>';
      }
    }
    if (type === 'music' || type === 'voice') {
      wave = '<span class="yai-hd-thumb__wave" aria-hidden="true"></span>';
    }
    if (url) {
      return '<div class="yai-hd-thumb yai-hd-thumb--' + type + ratioCls + '">' +
        '<img src="' + esc(url) + '" alt="" loading="lazy">' + play + wave + badge + '</div>';
    }
    return '<div class="yai-hd-thumb yai-hd-thumb--skeleton yai-hd-thumb--' + type + ratioCls + '" aria-hidden="true">' +
      '<span class="yai-hd-thumb__skeleton-icon">' + skeletonIcon(type) + '</span>' +
      '<span class="yai-hd-thumb__shimmer"></span>' + play + wave + badge + '</div>';
  }

  function cardOverlayHtml(item) {
    var id = item.id || item.gallery_id || '';
    var type = mediaTypeOf(item);
    var cta = primaryCtaLabel(type);
    return '<div class="yai-hd-card__overlay">' +
      '<button type="button" class="yai-hd-card__cta" data-home-remix data-work-action="regenerate" data-work-id="' + esc(id) + '">' + esc(cta) + '</button>' +
      '<div class="yai-hd-card__more-wrap">' +
        '<button type="button" class="yai-hd-card__more" data-home-more aria-label="더보기" aria-expanded="false" aria-haspopup="true">⋯</button>' +
        '<div class="yai-hd-card__more-menu" role="menu" hidden>' +
          '<button type="button" role="menuitem" data-work-action="open" data-work-id="' + esc(id) + '">상세 보기</button>' +
          '<button type="button" role="menuitem" data-work-action="project" data-work-id="' + esc(id) + '">프로젝트에 추가</button>' +
          '<button type="button" role="menuitem" data-work-action="download" data-work-id="' + esc(id) + '">다운로드</button>' +
          '<button type="button" role="menuitem" data-work-action="regenerate" data-work-id="' + esc(id) + '" data-home-remix>복제</button>' +
          '<button type="button" role="menuitem" data-work-action="delete" data-work-id="' + esc(id) + '">삭제</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function galleryCard(item, section) {
    rememberWork(item);
    var title = item.title || item.name || '작품';
    var id = item.id || item.gallery_id || '';
    var type = mediaTypeOf(item);
    var likes = item.likes != null ? item.likes : (item.like_count != null ? item.like_count : null);
    var meta = '';
    if (likes != null) {
      meta = '<div class="yai-hd-card__meta"><span class="yai-hd-card__likes">♥ ' + esc(String(likes)) + '</span></div>';
    }
    return '<article class="yai-hd-card yai-hd-card--gallery yai-hd-card--' + type + '" data-gallery-id="' + esc(id) + '">' +
      '<div class="yai-hd-card__media">' +
        thumbHtml(item, section) +
        cardOverlayHtml(item) +
      '</div>' +
      '<div class="yai-hd-card__body"><strong>' + esc(title) + '</strong>' + meta + '</div>' +
    '</article>';
  }

  function projectCard(item, section) {
    var title = item.title || item.name || 'Project';
    var id = item.id || '';
    return '<article class="yai-hd-card yai-hd-card--gallery yai-hd-card--project" data-project-open="' + esc(id) + '" tabindex="0" role="button">' +
      thumbHtml(item, section) +
      '<div class="yai-hd-card__body"><strong>' + esc(title) + '</strong><span>Project</span></div>' +
      '<div class="yai-hd-card__overlay"><button type="button" class="yai-hd-card__cta" data-project-continue="' + esc(id) + '">계속 작업하기</button></div>' +
    '</article>';
  }

  function templateCard(item, section) {
    var title = item.title || item.label || item.name || '템플릿';
    var seed = item.seed || item.seed_prompt || item.prompt || title;
    return '<article class="yai-hd-card yai-hd-card--template yai-hd-card--' + mediaTypeOf(item) + '" data-template-seed="' + esc(seed) + '" tabindex="0" role="button">' +
      thumbHtml(item, section) +
      '<div class="yai-hd-card__body"><strong>' + esc(title) + '</strong><span>바로 사용</span></div>' +
    '</article>';
  }

  function recentIcon(item) {
    return skeletonIcon(mediaTypeOf(item));
  }

  function recentRow(item) {
    var title = item.title || item.type_label || item.type || '작업';
    var status = item.status || 'active';
    var thumb = thumbUrl(item);
    var isProject = String(item.entity || item.kind || item.item_type || '').toLowerCase() === 'project' ||
      (!!item.asset_count && !item.gallery_id && String(item.type || '').toLowerCase() === 'project');
    var thumbBlock = thumb
      ? '<div class="yai-hd-recent__thumb"><img src="' + esc(thumb) + '" alt="" loading="lazy"></div>'
      : '<div class="yai-hd-recent__thumb yai-hd-recent__thumb--skeleton"><span>' + recentIcon(item) + '</span></div>';
    if (isProject) {
      return '<article class="yai-hd-recent yai-hd-recent--project" data-project-open="' + esc(item.id || '') + '">' +
        thumbBlock +
        '<div class="yai-hd-recent__body"><strong>' + esc(title) + '</strong><span>Project</span></div>' +
        '<button type="button" class="yai-text-btn" data-project-continue="' + esc(item.id || '') + '">계속 작업하기</button>' +
      '</article>';
    }
    return '<article class="yai-hd-recent yai-hd-recent--' + mediaTypeOf(item) + '" data-work-id="' + esc(item.id || item.gallery_id || '') + '" role="button" tabindex="0">' +
      thumbBlock +
      '<div class="yai-hd-recent__body"><strong>' + esc(title) + '</strong><span>' + esc(status) + '</span></div>' +
      '<button type="button" class="yai-text-btn" data-work-action="regenerate" data-work-id="' + esc(item.id || item.gallery_id || '') + '" data-home-remix>' +
        esc(primaryCtaLabel(mediaTypeOf(item))) + '</button>' +
    '</article>';
  }

  function guideBlock() {
    return '<div class="yai-hd-guide-grid">' +
      '<article class="yai-hd-guide-card"><span>1</span><strong>원하는 것을 말하세요</strong><p>하단 생성 바에 만들고 싶은 결과를 적어 보세요.</p></article>' +
      '<article class="yai-hd-guide-card"><span>2</span><strong>Studio는 YooY가 고릅니다</strong><p>자동 선택으로 Studio에 연결하거나 추천 카드를 눌러 시작하세요.</p></article>' +
      '<article class="yai-hd-guide-card"><span>3</span><strong>결과는 Gallery에</strong><p>작품은 Gallery와 Project에 정리됩니다.</p></article>' +
    '</div>';
  }

  function emptySectionCopy(section) {
    var guest = !isLoggedInHome();
    var title = guestSectionTitle(section) || section.title || '작품';
    return '<div class="yai-hd-empty">' +
      '<p class="yai-muted">아직 공개된 ' + esc(title) + '이(가) 없습니다.</p>' +
      (guest
        ? '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-yai-free-start>무료로 시작하기</button>'
        : '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-route="assistant">AI Assistant로 시작</button>') +
    '</div>';
  }

  function moreRouteFor(section) {
    var dt = sectionDisplayType(section);
    var src = section.data_source || '';
    if (dt === 'guide') return 'assistant';
    if (dt === 'recent') return 'history';
    if (dt === 'template') return 'templates';
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
        ? '<div class="' + layout + '">' + projects.slice(0, limit).map(function (p) { return projectCard(p, section); }).join('') + '</div>'
        : emptySectionCopy(section);
    } else if (dt === 'template') {
      var id = String(section.id || '');
      var isCatalog = (id === 'templates' || id === 'sec_templates' || id === 'sec_templates_default');
      if (!isLoggedInHome() && (id.indexOf('saved') !== -1 || (section.title || '').indexOf('저장') !== -1)) {
        return '';
      }
      if (isCatalog && global.YooYCreationTemplates && typeof global.YooYCreationTemplates.renderHomeFeatured === 'function') {
        body = '<div class="yai-ct-row">' + global.YooYCreationTemplates.renderHomeFeatured(4) + '</div>';
      } else {
        var tpl = sectionWorks(section, feed).filter(isRealPublicWork).slice(0, limit);
        body = tpl.length
          ? '<div class="' + layout + '">' + tpl.map(function (t) { return templateCard(t, section); }).join('') + '</div>'
          : emptySectionCopy(section);
      }
    } else {
      var works = sectionWorks(section, feed).slice(0, limit);
      body = works.length
        ? '<div class="' + layout + '">' + works.map(function (w) { return galleryCard(w, section); }).join('') + '</div>'
        : emptySectionCopy(section);
    }

    return '<section class="yai-hd-section ' + ratioCls + '" data-section-id="' + esc(section.id) + '" data-section-type="' + esc(dt) + '">' +
      '<header class="yai-hd-section__head">' +
        '<div><h2>' + esc(guestSectionTitle(section)) + '</h2><p>' + esc(section.description || '') + '</p></div>' +
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
    var panel = document.getElementById('yai-home-section-manager');
    var manageBtn = document.getElementById('yai-section-manage-btn');
    if (!panel) return;

    if (!isAdmin()) {
      if (manageBtn) manageBtn.hidden = true;
      closeSectionDrawer();
      return;
    }

    if (manageBtn) manageBtn.hidden = false;

    var sorted = sortedVisibleSections().concat(
      (state.sections || []).filter(function (s) { return s.visible === false; })
    ).sort(function (a, b) { return (a.order || 0) - (b.order || 0); });

    var configNote = state.serverConfigured
      ? '서버 설정이 적용 중입니다. 변경은 Admin Console에서 저장됩니다.'
      : '서버 설정 없음 — 기본 섹션 레이아웃을 사용 중입니다.';

    panel.innerHTML =
      '<p class="yai-muted yai-hd-manager__note">' + esc(configNote) + '</p>' +
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

  function openSectionDrawer() {
    if (!isAdmin()) return;
    state.sectionDrawerOpen = true;
    var drawer = document.getElementById('yai-section-drawer');
    var overlay = document.getElementById('yai-section-drawer-overlay');
    if (drawer) {
      drawer.hidden = false;
      requestAnimationFrame(function () { drawer.classList.add('is-open'); });
    }
    if (overlay) {
      overlay.hidden = false;
      requestAnimationFrame(function () { overlay.classList.add('is-visible'); });
    }
    document.body.classList.add('yai-hd-drawer-open');
  }

  function closeSectionDrawer() {
    state.sectionDrawerOpen = false;
    var drawer = document.getElementById('yai-section-drawer');
    var overlay = document.getElementById('yai-section-drawer-overlay');
    if (drawer) {
      drawer.classList.remove('is-open');
      setTimeout(function () {
        if (!state.sectionDrawerOpen) drawer.hidden = true;
      }, 280);
    }
    if (overlay) {
      overlay.classList.remove('is-visible');
      setTimeout(function () {
        if (!state.sectionDrawerOpen) overlay.hidden = true;
      }, 280);
    }
    document.body.classList.remove('yai-hd-drawer-open');
  }

  function bindSectionDrawer() {
    var openBtn = document.getElementById('yai-section-manage-btn');
    var closeBtn = document.getElementById('yai-section-drawer-close');
    var overlay = document.getElementById('yai-section-drawer-overlay');

    if (openBtn && openBtn.dataset.bound !== '1') {
      openBtn.dataset.bound = '1';
      openBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (state.sectionDrawerOpen) closeSectionDrawer();
        else openSectionDrawer();
      });
    }
    if (closeBtn && closeBtn.dataset.bound !== '1') {
      closeBtn.dataset.bound = '1';
      closeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        closeSectionDrawer();
      });
    }
    if (overlay && overlay.dataset.bound !== '1') {
      overlay.dataset.bound = '1';
      overlay.addEventListener('click', function () { closeSectionDrawer(); });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && state.sectionDrawerOpen) closeSectionDrawer();
    });
  }

  function studioRecoAssetUrl(rel) {
    if (!rel) return '';
    if (/^https?:\/\//i.test(rel)) return rel;
    var base = '';
    try {
      base = (global.YooYStudio && global.YooYStudio.pluginUrl) || '';
    } catch (e) { base = ''; }
    if (!base) return rel;
    return String(base).replace(/\/?$/, '/') + String(rel).replace(/^\//, '');
  }

  function renderStudioRecos() {
    var el = document.getElementById('yai-home-studio-recos');
    if (!el || !CFG.STUDIO_RECOS) return;
    el.innerHTML = CFG.STUDIO_RECOS.map(function (s, idx) {
      var accent = s.accent || s.id || 'gold';
      var jpg = studioRecoAssetUrl(s.image || '');
      var webp = studioRecoAssetUrl(s.imageWebp || '');
      var examples = Array.isArray(s.examples) ? s.examples.slice(0, 3) : [];
      var lazy = idx < 2 ? 'eager' : 'lazy';
      var fetchPri = idx < 2 ? ' high' : '';
      var picture = jpg
        ? ('<picture class="yai-hd-studio-card__picture">' +
            (webp ? '<source type="image/webp" srcset="' + esc(webp) + '">' : '') +
            '<img class="yai-hd-studio-card__img" src="' + esc(jpg) + '" alt="' + esc(s.title || 'Studio') + '" loading="' + lazy + '" decoding="async"' +
            (fetchPri ? ' fetchpriority="high"' : '') + '>' +
          '</picture>')
        : '<span class="yai-hd-studio-card__art" aria-hidden="true"></span>';
      var tags = examples.length
        ? ('<ul class="yai-hd-studio-card__tags" aria-label="예시">' +
            examples.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('') +
          '</ul>')
        : '';
      return '<article class="yai-hd-studio-card yai-hd-studio-card--' + esc(accent) + '" data-studio-reco="' + esc(s.route) + '" tabindex="0" role="link" aria-label="' + esc((s.title || 'Studio') + ' 선택하기') + '">' +
        '<div class="yai-hd-studio-card__media">' + picture + '</div>' +
        '<div class="yai-hd-studio-card__body">' +
          '<div class="yai-hd-studio-card__title">' +
            '<span class="yai-hd-studio-card__icon" aria-hidden="true">' + esc(s.icon || '◆') + '</span>' +
            '<strong>' + esc(s.title) + '</strong>' +
          '</div>' +
          '<p class="yai-hd-studio-card__desc">' + esc(s.desc || '') + '</p>' +
          tags +
          '<span class="yai-hd-studio-card__cta">선택하기 →</span>' +
        '</div></article>';
    }).join('');
  }

  function renderHeroChips() {
    var el = document.getElementById('yai-home-hero-chips');
    if (!el) return;
    el.innerHTML = (CFG.HERO_CHIPS || []).map(function (chip) {
      return '<button type="button" class="yai-hd-chip" data-hero-chip data-studio="' + esc(chip.studio) + '" data-seed="' + esc(chip.seed) + '">' + esc(chip.label) + '</button>';
    }).join('');
  }

  function renderQuickTools() {
    var el = document.getElementById('yai-home-quick-tools');
    if (!el) return;
    var tools = (global.YooYCreationCatalog && global.YooYCreationCatalog.TOOLS) || (CFG.QUICK_TOOLS || []);
    el.innerHTML = tools.map(function (tool) {
      var id = tool.id || '';
      var studio = tool.studio || '';
      var label = tool.label || '';
      return '<button type="button" class="yai-hd-quick" data-quick-tool data-tool-id="' + esc(id) + '" data-studio="' + esc(studio) + '">' +
        '<span class="yai-hd-quick__label">' + esc(label) + '</span></button>';
    }).join('');
  }

  function applySeedToHero(seed, studio) {
    var input = document.getElementById('yai-home-prompt');
    if (input && seed) {
      input.value = seed;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    try {
      if (seed) sessionStorage.setItem('yoy_home_prompt', seed);
      if (studio) sessionStorage.setItem('yoy_home_studio', studio);
    } catch (e) { /* noop */ }
  }

  function closeAllHomeMoreMenus(except) {
    document.querySelectorAll('.yai-hd-card__more-menu').forEach(function (menu) {
      if (except && menu === except) return;
      menu.hidden = true;
    });
    document.querySelectorAll('.yai-hd-card__more[aria-expanded="true"]').forEach(function (btn) {
      if (except && btn.nextElementSibling === except) return;
      btn.setAttribute('aria-expanded', 'false');
    });
    document.querySelectorAll('.yai-hd-card.is-menu-open').forEach(function (card) {
      if (except && card.contains(except)) return;
      card.classList.remove('is-menu-open');
    });
  }

  function bindHeroAndTools() {
    document.addEventListener('click', function (e) {
      var moreBtn = e.target.closest('[data-home-more]');
      if (moreBtn) {
        e.preventDefault();
        e.stopPropagation();
        var wrap = moreBtn.closest('.yai-hd-card__more-wrap');
        var menu = wrap ? wrap.querySelector('.yai-hd-card__more-menu') : null;
        var card = moreBtn.closest('.yai-hd-card');
        var open = menu && menu.hidden;
        closeAllHomeMoreMenus(open ? menu : null);
        if (!menu) return;
        menu.hidden = !open;
        moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (card) card.classList.toggle('is-menu-open', open);
        return;
      }

      if (e.target.closest('[data-home-remix]')) {
        e.preventDefault();
        e.stopPropagation();
        var remixBtn = e.target.closest('[data-home-remix]');
        var remixId = remixBtn.getAttribute('data-work-id') || '';
        var item = state.workById[remixId] || { id: remixId };
        var shell = storeRemixShell(item);
        closeAllHomeMoreMenus();
        var studio = shell.studio || studioRouteForType(shell.type || mediaTypeOf(item));
        var loggedIn = !!(global.YooYCore && global.YooYCore.config && global.YooYCore.config.loggedIn);
        if (!loggedIn && global.YooYStudio) loggedIn = !!global.YooYStudio.loggedIn;
        if (!loggedIn) {
          try { sessionStorage.setItem('yoy_pending_after_auth', 'remix'); } catch (eAuth) { /* ignore */ }
          var modal = document.getElementById('yai-login-modal');
          if (modal) modal.hidden = false;
          return;
        }
        if (global.YooYStudioRoute) {
          global.YooYStudioRoute(studio);
        }
        return;
      }

      if (e.target.closest('.yai-hd-card__more-menu [data-work-action]')) {
        closeAllHomeMoreMenus();
        return;
      }

      if (!e.target.closest('.yai-hd-card__more-wrap')) {
        closeAllHomeMoreMenus();
      }

      var reco = e.target.closest('[data-studio-reco]');
      if (reco) {
        e.preventDefault();
        var routeName = reco.getAttribute('data-studio-reco');
        if (global.YooYStudioRoute && routeName) global.YooYStudioRoute(routeName);
        return;
      }
      var chip = e.target.closest('[data-hero-chip]');
      if (chip) {
        e.preventDefault();
        applySeedToHero(chip.getAttribute('data-seed'), chip.getAttribute('data-studio'));
        return;
      }
      var tool = e.target.closest('[data-quick-tool]');
      if (tool) {
        e.preventDefault();
        var toolId = tool.getAttribute('data-tool-id');
        if (toolId && global.YooYCreationTemplates && typeof global.YooYCreationTemplates.startTool === 'function') {
          global.YooYCreationTemplates.startTool(toolId);
        } else {
          applySeedToHero(tool.getAttribute('data-seed'), tool.getAttribute('data-studio'));
          if (global.YooYStudioRoute && tool.getAttribute('data-studio')) {
            global.YooYStudioRoute(tool.getAttribute('data-studio'));
          }
        }
        return;
      }
      var continueBtn = e.target.closest('[data-project-continue]');
      if (continueBtn) {
        e.preventDefault();
        e.stopPropagation();
        var pid = continueBtn.getAttribute('data-project-continue') || '';
        if (global.YooYStudioContinueProject) {
          global.YooYStudioContinueProject(pid);
        } else if (global.YooYActiveProject) {
          global.YooYActiveProject.set({ id: pid, name: 'Project' });
          if (global.YooYStudioRoute) global.YooYStudioRoute('project-detail');
        }
        return;
      }
      var recentAsset = e.target.closest('.yai-hd-recent[data-work-id]');
      if (recentAsset && !e.target.closest('[data-work-action]') && !e.target.closest('[data-home-remix]')) {
        e.preventDefault();
        var rid = recentAsset.getAttribute('data-work-id') || '';
        if (global.YooYGallery && typeof global.YooYGallery.openDetail === 'function') {
          global.YooYGallery.openDetail(rid);
        }
        return;
      }
      var continueWork = e.target.closest('[data-continue-work]');
      if (continueWork) {
        e.preventDefault();
        var row = continueWork.closest('.yai-hd-recent') || continueWork;
        var jobId = row.getAttribute('data-job-id') || '';
        var jobs = jobsFromFeed(state.feed);
        var found = null;
        jobs.forEach(function (j) {
          if (String(j.id || j.gallery_id || '') === String(jobId)) found = j;
        });
        if (found) storeRemixShell(found);
        var studio = found ? studioRouteForType(mediaTypeOf(found)) : 'image';
        if (global.YooYStudioRoute) global.YooYStudioRoute(studio);
        return;
      }
      var tpl = e.target.closest('[data-template-seed]');
      if (tpl) {
        e.preventDefault();
        applySeedToHero(tpl.getAttribute('data-template-seed'), 'image');
        if (global.YooYStudioRoute) global.YooYStudioRoute('image');
      }
    });

    document.addEventListener('keydown', function (e) {
      var reco = e.target.closest && e.target.closest('[data-studio-reco]');
      if (!reco) return;
      if (e.key !== 'Enter' && e.key !== ' ') return;
      e.preventDefault();
      var routeName = reco.getAttribute('data-studio-reco');
      if (global.YooYStudioRoute && routeName) global.YooYStudioRoute(routeName);
    });
  }

  function updatePlanDropdown(acc) {
    acc = acc || state.account || {};
    var planName = acc.plan_name || acc.tier || 'Free';
    var current = document.getElementById('yai-plan-dropdown-current');
    if (current) current.textContent = planName;

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
    state.workById = {};
    state.serverConfigured = CFG.hasServerSections(feed);
    state.sections = CFG.sectionsFromFeed(feed);
    // If server only returned private user sections, guest filter would wipe Home.
    // Prefer Discovery defaults so Guest/Auth share one product shell.
    if (!isLoggedInHome()) {
      var visible = sortedVisibleSections();
      if (!visible.length) {
        state.sections = CFG.cloneDefaults().filter(function (s) {
          var dt = sectionDisplayType(s);
          var sid = String(s.id || '');
          if (dt === 'recent' || dt === 'projects') return false;
          if (dt === 'template' && sid.indexOf('saved') !== -1) return false;
          return true;
        });
        state.serverConfigured = false;
      }
    }
    renderSectionManager();
    renderSections();
  }

  function init() {
    renderStudioRecos();
    renderHeroChips();
    renderQuickTools();
    renderSectionManager();
    renderSections();
    bindHeroAndTools();
    bindPlanDropdown();
    bindSectionDrawer();
    updateStudioRecoHeadline();
  }

  function updateStudioRecoHeadline() {
    var el = document.getElementById('yai-home-studio-recos-title');
    if (!el) return;
    var cfg = global.YooYStudio || {};
    var name = (cfg.user && cfg.user.name) ? String(cfg.user.name).trim() : '';
    var loggedIn = !!cfg.loggedIn;
    if (loggedIn && name && name.toLowerCase() !== 'guest') {
      el.textContent = name + '님의 아이디어를 완벽한 결과물로 완성하세요';
      return;
    }
    el.textContent = '당신의 아이디어를 완벽한 결과물로 완성하세요';
  }

  global.YooYHomeDashboard = {
    init: init,
    onFeed: onFeed,
    updateCredits: updateCreditCard,
    getSections: function () { return state.sections.slice(); },
    storeRemixShell: storeRemixShell,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : globalThis);
