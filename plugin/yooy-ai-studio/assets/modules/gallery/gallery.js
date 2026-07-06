(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core || !Core.gallery) return;

  var TYPE_ICONS = {
    video: '🎬', image: '🖼', music: '🎵', writing: '📝', avatar: '👤', voice: '🎙'
  };

  var STUDIO_ROUTES = {
    'video-studio': 'video',
    'image-studio': 'image',
    'music-studio': 'music',
    'voice-studio': 'voice',
    'avatar-studio': 'avatar',
    'writing-studio': 'writing'
  };

  var state = { items: [], filter: 'all', selected: null };

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  function toast(msg) {
    var el = document.createElement('div');
    el.className = 'ygl-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 2600);
  }

  function formatDate(iso) {
    if (!iso) return '-';
    try {
      return new Date(iso).toLocaleString('ko-KR');
    } catch (e) {
      return iso;
    }
  }

  function thumbHtml(item) {
    var url = item.thumbnail || item.output_url || '';
    var type = item.type || 'image';
    if (url && (type === 'video' || type === 'avatar')) {
      return '<video src="' + esc(url) + '" muted loop playsinline></video>';
    }
    if (url && type === 'image') {
      return '<img src="' + esc(url) + '" alt="" loading="lazy">';
    }
    if (url && (type === 'music' || type === 'voice')) {
      return '<img src="' + esc(url) + '" alt="" loading="lazy" onerror="this.style.display=\'none\'">' +
        '<span class="ygl-thumb-icon">' + (TYPE_ICONS[type] || '📁') + '</span>';
    }
    if (type === 'writing') {
      return '<span class="ygl-thumb-icon">📝</span>';
    }
    return '<span class="ygl-thumb-icon">' + (TYPE_ICONS[type] || '📁') + '</span>';
  }

  function renderGrid(root) {
    var filtered = state.filter === 'all'
      ? state.items
      : state.items.filter(function (i) { return i.type === state.filter; });

    if (!filtered.length) {
      root.querySelector('.ygl-grid').innerHTML =
        '<div class="ygl-empty">저장된 생성물이 없습니다.<br>스튜디오에서 생성하면 자동으로 저장됩니다.</div>';
      return;
    }

    root.querySelector('.ygl-grid').innerHTML = filtered.map(function (item) {
      return '<article class="ygl-card' + (item.favorite ? ' is-fav' : '') + '" data-ygl-id="' + esc(item.id) + '">' +
        '<div class="ygl-thumb">' + thumbHtml(item) +
        '<span class="ygl-type-badge">' + esc(item.type_label || item.type) + '</span>' +
        (item.favorite ? '<span class="ygl-fav-badge">★</span>' : '') +
        '</div>' +
        '<div class="ygl-card-body"><strong>' + esc(item.title || 'Untitled') + '</strong>' +
        '<span>' + esc(formatDate(item.created_at)) + '</span></div></article>';
    }).join('');

    root.querySelectorAll('.ygl-card').forEach(function (card) {
      card.addEventListener('click', function () {
        openDetail(card.dataset.yglId);
      });
    });
  }

  function renderFilters(root) {
    var filters = [
      { id: 'all', label: '전체' },
      { id: 'video', label: '영상' },
      { id: 'image', label: '이미지' },
      { id: 'music', label: '음악' },
      { id: 'writing', label: '글' },
      { id: 'avatar', label: '아바타' },
      { id: 'voice', label: '음성' }
    ];

    root.querySelector('.ygl-filters').innerHTML = filters.map(function (f) {
      return '<button type="button" class="ygl-filter' + (state.filter === f.id ? ' is-active' : '') +
        '" data-ygl-filter="' + f.id + '">' + esc(f.label) + '</button>';
    }).join('');

    root.querySelectorAll('[data-ygl-filter]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.filter = btn.dataset.yglFilter;
        renderFilters(root);
        renderGrid(root);
      });
    });
  }

  function previewHtml(item) {
    var url = item.output_url || '';
    var type = item.type;
    if (type === 'writing') {
      return '<div class="ygl-text-preview">' + esc(item.prompt || '') + '</div>';
    }
    if (url && (type === 'video' || type === 'avatar')) {
      return '<video src="' + esc(url) + '" controls autoplay></video>';
    }
    if (url && type === 'image') {
      return '<img src="' + esc(url) + '" alt="">';
    }
    if (url && (type === 'music' || type === 'voice')) {
      return '<audio src="' + esc(url) + '" controls autoplay></audio>';
    }
    return '<div class="ygl-text-preview">' + esc(item.prompt || '미리보기 없음') + '</div>';
  }

  function closeDetail() {
    var overlay = document.querySelector('.ygl-overlay');
    if (overlay) overlay.remove();
    state.selected = null;
  }

  function updateItemInState(updated) {
    state.items = state.items.map(function (i) {
      return i.id === updated.id ? updated : i;
    });
    state.selected = updated;
  }

  function openDetail(id) {
    Core.gallery.item(id).then(function (res) {
      var item = (res.data && res.data.item) || null;
      if (!item) return;
      state.selected = item;
      closeDetail();

      var overlay = document.createElement('div');
      overlay.className = 'ygl-overlay';
      overlay.innerHTML =
        '<div class="ygl-modal" style="position:relative">' +
        '<button type="button" class="ygl-close" data-ygl-close>×</button>' +
        '<div class="ygl-modal-preview">' + previewHtml(item) + '</div>' +
        '<div class="ygl-modal-side">' +
        '<h3>' + esc(item.title || 'Untitled') + '</h3>' +
        '<dl class="ygl-meta">' +
        metaRow('타입', item.type_label || item.type) +
        metaRow('사용 API', item.provider_label || item.provider) +
        metaRow('사용 모델', item.model || '-') +
        metaRow('생성 시간', item.created_label || formatDate(item.created_at)) +
        metaRow('사용 Credits', String(item.credits_used || 0)) +
        '</dl>' +
        '<div><small style="color:#888">원본 Prompt</small>' +
        '<div class="ygl-prompt-box">' + esc(item.prompt || '-') + '</div></div>' +
        '<div class="ygl-actions">' +
        '<button type="button" class="ygl-btn ygl-btn-primary" data-ygl-action="download">다운로드</button>' +
        '<button type="button" class="ygl-btn" data-ygl-action="copy">복사</button>' +
        '<button type="button" class="ygl-btn" data-ygl-action="regenerate">재생성</button>' +
        '<button type="button" class="ygl-btn' + (item.favorite ? ' is-active' : '') + '" data-ygl-action="favorite">즐겨찾기</button>' +
        '<button type="button" class="ygl-btn' + (item.public ? ' is-active' : '') + '" data-ygl-action="public">공개</button>' +
        '<button type="button" class="ygl-btn' + (!item.public ? ' is-active' : '') + '" data-ygl-action="private">비공개</button>' +
        '<button type="button" class="ygl-btn' + (item.marketplace ? ' is-active' : '') + '" data-ygl-action="marketplace">Marketplace 등록</button>' +
        '<button type="button" class="ygl-btn' + (item.community_shared ? ' is-active' : '') + '" data-ygl-action="community">Community 공유</button>' +
        '<button type="button" class="ygl-btn ygl-btn-danger" data-ygl-action="delete">삭제</button>' +
        '</div></div></div>';

      document.body.appendChild(overlay);

      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeDetail();
      });
      overlay.querySelector('[data-ygl-close]').addEventListener('click', closeDetail);

      overlay.querySelectorAll('[data-ygl-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          handleAction(btn.dataset.yglAction, item, overlay);
        });
      });
    }).catch(function (err) {
      toast(err.message || '상세 정보를 불러올 수 없습니다.');
    });
  }

  function metaRow(label, value) {
    return '<div class="ygl-meta-row"><dt>' + esc(label) + '</dt><dd>' + esc(String(value)) + '</dd></div>';
  }

  function handleAction(action, item, overlay) {
    switch (action) {
      case 'download':
        Core.gallery.download(item.id).then(function (res) {
          var info = res.data || {};
          if (info.url) {
            var a = document.createElement('a');
            a.href = info.url;
            a.download = info.filename || 'download';
            a.target = '_blank';
            a.click();
            toast('다운로드를 시작합니다.');
          }
        }).catch(function (err) { toast(err.message); });
        break;

      case 'copy':
        Core.gallery.copy(item.id).then(function (res) {
          var prompt = (res.data && res.data.prompt) || '';
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(prompt).then(function () {
              toast('Prompt가 클립보드에 복사되었습니다.');
            });
          } else {
            toast(prompt);
          }
        }).catch(function (err) { toast(err.message); });
        break;

      case 'regenerate':
        Core.gallery.regenerate(item.id).then(function (res) {
          var payload = res.data || {};
          try {
            sessionStorage.setItem('yoy_regenerate', JSON.stringify(payload));
          } catch (e) { /* ignore */ }
          var route = STUDIO_ROUTES[payload.studio] || payload.type;
          if (global.YooYStudioRoute) {
            global.YooYStudioRoute(route);
          } else {
            var nav = document.querySelector('[data-route="' + route + '"]');
            if (nav) nav.click();
          }
          closeDetail();
          toast((payload.studio || route) + ' 스튜디오로 이동합니다.');
        }).catch(function (err) { toast(err.message); });
        break;

      case 'favorite':
        Core.gallery.favorite(item.id).then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          refreshModal(overlay, updated);
          var root = document.querySelector('.ygl-root');
          if (root) renderGrid(root);
          toast(updated.favorite ? '즐겨찾기에 추가했습니다.' : '즐겨찾기를 해제했습니다.');
        }).catch(function (err) { toast(err.message); });
        break;

      case 'public':
        Core.gallery.visibility(item.id, true).then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          refreshModal(overlay, updated);
          toast('공개로 설정했습니다.');
        }).catch(function (err) { toast(err.message); });
        break;

      case 'private':
        Core.gallery.visibility(item.id, false).then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          refreshModal(overlay, updated);
          toast('비공개로 설정했습니다.');
        }).catch(function (err) { toast(err.message); });
        break;

      case 'marketplace':
        Core.gallery.marketplace(item.id).then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          refreshModal(overlay, updated);
          toast('Marketplace에 등록했습니다.');
        }).catch(function (err) { toast(err.message); });
        break;

      case 'community':
        Core.gallery.community(item.id).then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          refreshModal(overlay, updated);
          toast('Community에 공유했습니다.');
        }).catch(function (err) { toast(err.message); });
        break;

      case 'delete':
        if (!confirm('이 생성물을 삭제하시겠습니까?')) return;
        Core.gallery.remove(item.id).then(function () {
          state.items = state.items.filter(function (i) { return i.id !== item.id; });
          closeDetail();
          var root = document.querySelector('.ygl-root');
          if (root) renderGrid(root);
          toast('삭제되었습니다.');
        }).catch(function (err) { toast(err.message); });
        break;
    }
  }

  function refreshModal(overlay, item) {
    var favBtn = overlay.querySelector('[data-ygl-action="favorite"]');
    var pubBtn = overlay.querySelector('[data-ygl-action="public"]');
    var privBtn = overlay.querySelector('[data-ygl-action="private"]');
    var mktBtn = overlay.querySelector('[data-ygl-action="marketplace"]');
    var commBtn = overlay.querySelector('[data-ygl-action="community"]');
    if (favBtn) favBtn.classList.toggle('is-active', !!item.favorite);
    if (pubBtn) pubBtn.classList.toggle('is-active', !!item.public);
    if (privBtn) privBtn.classList.toggle('is-active', !item.public);
    if (mktBtn) mktBtn.classList.toggle('is-active', !!item.marketplace);
    if (commBtn) commBtn.classList.toggle('is-active', !!item.community_shared);
  }

  function load(root) {
    var grid = root.querySelector('.ygl-grid');
    if (!grid) return;
    grid.innerHTML = '<div class="ygl-loading">갤러리 불러오는 중...</div>';
    Core.gallery.items({ sync: true }).then(function (res) {
      state.items = (res.data && res.data.items) || [];
      renderGrid(root);
    }).catch(function () {
      grid.innerHTML = '<div class="ygl-empty">갤러리를 불러올 수 없습니다.</div>';
    });
  }

  function reload(root) {
    if (root) load(root);
    else {
      var el = document.querySelector('.ygl-root');
      if (el && el.parentElement) load(el.parentElement);
    }
  }

  function mount(el) {
    if (!el) return;
    el.innerHTML =
      '<div class="ygl-root">' +
      '<div class="ygl-toolbar">' +
      '<div class="ygl-filters"></div>' +
      '<button type="button" class="ygl-sync" data-ygl-sync>동기화</button>' +
      '</div>' +
      '<div class="ygl-grid"></div></div>';

    renderFilters(el);
    load(el);

    el.querySelector('[data-ygl-sync]').addEventListener('click', function () {
      Core.gallery.sync().then(function (res) {
        state.items = (res.data && res.data.items) || [];
        renderGrid(el);
        toast('모든 스튜디오 생성물을 동기화했습니다.');
      }).catch(function (err) { toast(err.message); });
    });
  }

  global.YooYGallery = { mount: mount, reload: reload };
})(window);
