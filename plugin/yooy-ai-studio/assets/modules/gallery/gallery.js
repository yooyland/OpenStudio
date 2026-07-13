(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core || !Core.gallery || typeof Core.gallery.item !== 'function') return;

  var TYPE_ICONS = {
    video: '🎬', image: '🖼', music: '🎵', writing: '📝', translation: '🌐', avatar: '👤', voice: '🎙'
  };

  var STUDIO_ROUTES = {
    'video-studio': 'video',
    'image-studio': 'image',
    'music-studio': 'music',
    'voice-studio': 'voice',
    'avatar-studio': 'avatar',
    'writing-studio': 'writing',
    'translator-studio': 'translator'
  };

  var state = { items: [], filter: 'all', selected: null, editing: false };

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = str == null ? '' : String(str);
    return d.innerHTML;
  }

  function toast(msg) {
    var el = document.createElement('div');
    el.className = 'ygl-toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function () { el.remove(); }, 2600);
  }

  function notifyUpdated() {
    if (Core.notifyGalleryUpdated) Core.notifyGalleryUpdated();
    document.dispatchEvent(new CustomEvent('yoy:gallery:updated'));
  }

  function formatDate(iso) {
    if (!iso) return '-';
    try { return new Date(iso).toLocaleString('ko-KR'); } catch (e) { return iso; }
  }

  function typeLabel(type) {
    var map = { video: '영상', image: '이미지', music: '음악', writing: '글', translation: '번역', avatar: '아바타', voice: '음성' };
    return map[type] || type || 'Work';
  }

  var SOURCE_TYPE_BADGE = {
    text: 'TEXT', file: 'FILE', website: 'WEB', image: 'OCR',
    audio: 'AUDIO', video: 'VIDEO', youtube: 'YOUTUBE'
  };

  function translationSourceBadge(item) {
    var key = String((item && (item.source_type || (item.meta && item.meta.source_type))) || 'text').toLowerCase();
    return SOURCE_TYPE_BADGE[key] || 'TEXT';
  }

  function cardTypeBadge(item) {
    if (item.type === 'translation') {
      return translationSourceBadge(item) + ' · ' + (item.type_label || typeLabel(item.type));
    }
    return item.type_label || typeLabel(item.type);
  }

  function galleryImg(item, opts) {
    opts = opts || {};
    if (global.YooYGalleryImage && typeof global.YooYGalleryImage.imgTag === 'function') {
      return global.YooYGalleryImage.imgTag(item, opts);
    }
    var src = (global.YooYGalleryImage && global.YooYGalleryImage.pickUrl)
      ? global.YooYGalleryImage.pickUrl(item, opts.size || 'large')
      : (item.display_url || item.large_url || item.full_url || item.image_url || item.thumbnail_url || '');
    if (!src) return '';
    return '<img src="' + esc(src) + '" alt="" class="yai-gallery-img" loading="' + (opts.lazy === false ? 'eager' : 'lazy') + '" decoding="async">';
  }

  function thumbHtml(item) {
    if (item.asset_missing) return '<span class="ygl-thumb-missing">Asset missing</span>';
    var type = item.type || 'image';
    if (type === 'video' || type === 'avatar') {
      var videoUrl = global.YooYGalleryImage
        ? global.YooYGalleryImage.pickUrl(item, 'large')
        : (item.large_url || item.full_url || item.image_url || item.asset_url || '');
      return '<video src="' + esc(videoUrl) + '" muted loop playsinline></video>';
    }
    if (type === 'image') {
      return galleryImg(item, { size: 'large', className: 'yai-gallery-img' });
    }
    if (type === 'music' || type === 'voice') {
      return galleryImg(item, { size: 'thumb', className: 'yai-gallery-img' }) +
        '<span class="ygl-thumb-icon">' + (TYPE_ICONS[type] || '📁') + '</span>';
    }
    if (type === 'writing') return '<span class="ygl-thumb-icon">📝</span>';
    if (type === 'translation') return '<span class="ygl-thumb-icon">🌐</span>';
    return '<span class="ygl-thumb-icon">' + (TYPE_ICONS[type] || '📁') + '</span>';
  }

  function previewHtml(item) {
    if (item.asset_missing) {
      return '<div class="ygl-thumb-missing ygl-thumb-missing--preview">Image asset is missing.</div>';
    }
    var type = item.type;
    if (type === 'writing') {
      return '<div class="ygl-text-preview">' + esc(item.user_prompt || item.prompt || '') + '</div>';
    }
    if (type === 'translation') {
      var translated = item.translated_text || (item.meta && item.meta.translated_text) || '';
      var source = item.user_prompt || item.prompt || '';
      return '<div class="ygl-text-preview">' +
        '<div class="ygl-muted" style="margin-bottom:8px">' + esc(source) + '</div>' +
        '<strong>' + esc(translated || '번역 결과 없음') + '</strong></div>';
    }
    var url = global.YooYGalleryImage
      ? global.YooYGalleryImage.pickUrl(item, 'full')
      : (item.full_url || item.original_url || item.asset_url || item.image_url || item.output_url || '');
    if (url && (type === 'video' || type === 'avatar')) {
      return '<video src="' + esc(url) + '" controls autoplay></video>';
    }
    if (url && type === 'image') {
      return galleryImg(item, { size: 'full', lazy: false, className: 'yai-gallery-img yai-gallery-img--preview' });
    }
    if (url && (type === 'music' || type === 'voice')) {
      return '<audio src="' + esc(url) + '" controls autoplay></audio>';
    }
    return '<div class="ygl-text-preview">' + esc(item.user_prompt || item.prompt || '미리보기 없음') + '</div>';
  }

  function closeDetail() {
    var overlay = document.querySelector('.ygl-drawer-overlay');
    if (overlay) overlay.remove();
    state.selected = null;
    state.editing = false;
    document.body.classList.remove('ygl-drawer-open');
  }

  function updateItemInState(updated) {
    state.items = state.items.map(function (i) { return i.id === updated.id ? updated : i; });
    state.selected = updated;
  }

  function metaRow(label, value) {
    return '<div class="ygl-meta-row"><dt>' + esc(label) + '</dt><dd>' + esc(String(value == null ? '—' : value)) + '</dd></div>';
  }

  function actionBtn(label, action, cls) {
    return '<button type="button" class="ygl-btn' + (cls ? ' ' + cls : '') + '" data-ygl-action="' + esc(action) + '">' + esc(label) + '</button>';
  }

  function drawerActionsHtml(item) {
    return '<div class="ygl-action-groups">' +
      '<div class="ygl-action-group"><h4>기본</h4><div class="ygl-actions">' +
        actionBtn('다운로드', 'download', 'ygl-btn-primary') +
        actionBtn('제목/설명 수정', 'edit-meta') +
        actionBtn('삭제', 'delete', 'ygl-btn-danger') +
      '</div></div>' +
      '<div class="ygl-action-group"><h4>창작</h4><div class="ygl-actions">' +
        actionBtn('프롬프트 재사용', 'regenerate') +
        actionBtn('같은 설정 재생성', 'regenerate') +
        actionBtn('Edit Studio', 'edit-studio') +
        actionBtn('Reference로 사용', 'reference') +
      '</div></div>' +
      '<div class="ygl-action-group"><h4>관리</h4><div class="ygl-actions">' +
        actionBtn('Project에 추가', 'project') +
        actionBtn('Project 이동', 'project-move') +
        actionBtn('복제', 'duplicate') +
        actionBtn(item.favorite ? '즐겨찾기 해제' : '즐겨찾기', 'favorite') +
        actionBtn(item.public ? '비공개' : '공개', item.public ? 'private' : 'public') +
      '</div></div>' +
      '<div class="ygl-action-group"><h4>공유/판매</h4><div class="ygl-actions">' +
        actionBtn('Gallery 공개', 'public') +
        actionBtn('Community 공유', 'community') +
        actionBtn('공유 링크 복사', 'share') +
        actionBtn('Marketplace 등록', 'marketplace-modal') +
      '</div></div></div>';
  }

  function drawerHtml(item) {
    var refs = (item.reference_assets || []).length
      ? '<ul class="ygl-ref-list">' + item.reference_assets.map(function (r) {
          return '<li>' + esc(r.url || r.label || 'Reference') + '</li>';
        }).join('') + '</ul>'
      : '<p class="ygl-muted">없음</p>';

    return '<aside class="ygl-drawer" role="dialog" aria-label="작품 상세">' +
      '<button type="button" class="ygl-close" data-ygl-close aria-label="닫기">×</button>' +
      '<div class="ygl-drawer-preview">' + previewHtml(item) + '</div>' +
      '<div class="ygl-drawer-body">' +
        '<div class="ygl-drawer-head">' +
          '<input class="ygl-title-input" id="ygl-title-input" value="' + esc(item.title || '') + '" readonly>' +
          '<textarea class="ygl-desc-input" id="ygl-desc-input" readonly placeholder="설명 없음">' + esc(item.description || '') + '</textarea>' +
          '<div class="ygl-edit-actions" id="ygl-edit-actions" hidden>' +
            '<button type="button" class="ygl-btn ygl-btn-primary" data-ygl-action="save-meta">저장</button>' +
            '<button type="button" class="ygl-btn" data-ygl-action="cancel-edit">취소</button>' +
          '</div>' +
        '</div>' +
        '<dl class="ygl-meta">' +
          metaRow('타입', item.type_label || typeLabel(item.type)) +
          (item.type === 'translation' ? metaRow('Source', translationSourceBadge(item)) : '') +
          metaRow('Provider', item.provider_label || item.provider) +
          metaRow('Model', item.model || '—') +
          metaRow('생성 시간', item.created_label || formatDate(item.created_at)) +
          metaRow('Credits', String(item.credits_used || 0)) +
          metaRow('Project', item.project_title || item.project_id || '—') +
          metaRow('Visibility', item.visibility || (item.public ? 'public' : 'private')) +
          metaRow('Marketplace', item.marketplace_status || 'none') +
        '</dl>' +
        '<div class="ygl-prompt-block"><small>User Prompt</small><div class="ygl-prompt-box">' + esc(item.user_prompt || item.prompt || '—') + '</div></div>' +
        (item.optimized_prompt ? '<div class="ygl-prompt-block"><small>Optimized Prompt</small><div class="ygl-prompt-box">' + esc(item.optimized_prompt) + '</div></div>' : '') +
        '<div class="ygl-prompt-block"><small>Reference Assets</small>' + refs + '</div>' +
        (Core.config && Core.config.isAdmin ? (
          '<div class="ygl-image-debug"><h4>Image Debug (Admin)</h4><dl class="ygl-meta">' +
            metaRow('attachment_id', item.attachment_id || 0) +
            metaRow('original_url', item.original_url || '—') +
            metaRow('full_url', item.full_url || '—') +
            metaRow('large_url', item.large_url || '—') +
            metaRow('medium_large_url', item.medium_large_url || '—') +
            metaRow('thumbnail_url', item.thumbnail_url || '—') +
            metaRow('display_url', item.display_url || '—') +
            metaRow('natural size', (item.image_width || 0) + ' × ' + (item.image_height || 0)) +
            metaRow('srcset', item.srcset ? 'yes' : 'no') +
          '</dl></div>'
        ) : '') +
        drawerActionsHtml(item) +
      '</div></aside>';
  }

  function bindDrawer(overlay, item) {
    overlay.querySelector('[data-ygl-close]').addEventListener('click', closeDetail);
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) closeDetail();
    });

    overlay.querySelectorAll('[data-ygl-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        handleAction(btn.dataset.yglAction, item, overlay);
      });
    });

    document.addEventListener('keydown', function escDrawer(e) {
      if (e.key === 'Escape') {
        closeDetail();
        document.removeEventListener('keydown', escDrawer);
      }
    });
  }

  function setEditMode(overlay, on) {
    state.editing = on;
    var title = overlay.querySelector('#ygl-title-input');
    var desc = overlay.querySelector('#ygl-desc-input');
    var actions = overlay.querySelector('#ygl-edit-actions');
    if (title) title.readOnly = !on;
    if (desc) desc.readOnly = !on;
    if (actions) actions.hidden = !on;
  }

  function openDetail(id) {
    if (!id) return;
    Core.gallery.item(id).then(function (res) {
      var item = (res.data && res.data.item) || null;
      if (!item) return;
      state.selected = item;
      closeDetail();

      var overlay = document.createElement('div');
      overlay.className = 'ygl-drawer-overlay';
      overlay.innerHTML = drawerHtml(item);
      document.body.appendChild(overlay);
      document.body.classList.add('ygl-drawer-open');
      bindDrawer(overlay, item);
    }).catch(function (err) {
      toast(err.message || '상세 정보를 불러올 수 없습니다.');
    });
  }

  function openMarketplaceModal(item, overlay) {
    var modal = document.createElement('div');
    modal.className = 'ygl-market-modal';
    modal.innerHTML =
      '<div class="ygl-market-panel yai-form-grid yai-form-grid--2">' +
      '<h3 class="yai-form-span-2">Marketplace 등록</h3>' +
      '<label class="yai-field yai-form-span-2"><span>판매 제목</span><input id="ygl-mkt-title" value="' + esc(item.title || '') + '"></label>' +
      '<label class="yai-field yai-form-span-2"><span>설명</span><textarea id="ygl-mkt-desc" rows="3">' + esc(item.description || '') + '</textarea></label>' +
      '<label class="yai-field"><span>가격 (KRW)</span><input type="number" id="ygl-mkt-price" value="0" min="0"></label>' +
      '<label class="yai-field"><span>카테고리</span><input id="ygl-mkt-cat" value="general"></label>' +
      '<label class="yai-field yai-form-span-2"><span>태그 (쉼표 구분)</span><input id="ygl-mkt-tags" placeholder="광고,제품,이미지"></label>' +
      '<label class="yai-field"><span>라이선스</span><input id="ygl-mkt-license" value="standard"></label>' +
      '<label class="ygl-check yai-form-span-2"><input type="checkbox" id="ygl-mkt-prompt"> Prompt 공개</label>' +
      '<label class="ygl-check yai-form-span-2"><input type="checkbox" id="ygl-mkt-ref"> Reference 공개</label>' +
      '<label class="ygl-check yai-form-span-2"><input type="checkbox" id="ygl-mkt-dl"> 원본 다운로드 허용</label>' +
      '<div class="ygl-market-actions yai-form-span-2">' +
        '<button type="button" class="ygl-btn ygl-btn-primary" id="ygl-mkt-save">등록</button>' +
        '<button type="button" class="ygl-btn" id="ygl-mkt-cancel">취소</button>' +
      '</div></div>';
    overlay.appendChild(modal);

    function closeMarketModal() {
      modal.remove();
      if (overlay.classList.contains('ygl-market-host')) {
        overlay.remove();
      }
    }

    modal.querySelector('#ygl-mkt-cancel').addEventListener('click', closeMarketModal);
    modal.querySelector('#ygl-mkt-save').addEventListener('click', function () {
      var tags = (modal.querySelector('#ygl-mkt-tags').value || '').split(',').map(function (t) { return t.trim(); }).filter(Boolean);
      Core.gallery.marketplace(item.id, {
        title: modal.querySelector('#ygl-mkt-title').value,
        description: modal.querySelector('#ygl-mkt-desc').value,
        price: parseInt(modal.querySelector('#ygl-mkt-price').value, 10) || 0,
        category: modal.querySelector('#ygl-mkt-cat').value,
        tags: tags,
        license: modal.querySelector('#ygl-mkt-license').value,
        prompt_public: modal.querySelector('#ygl-mkt-prompt').checked,
        reference_public: modal.querySelector('#ygl-mkt-ref').checked,
        allow_download: modal.querySelector('#ygl-mkt-dl').checked
      }).then(function (res) {
        var updated = (res.data && res.data.item) || item;
        updateItemInState(updated);
        closeMarketModal();
        toast('Marketplace draft가 생성되었습니다.');
        notifyUpdated();
      }).catch(function (err) { toast(err.message); });
    });
  }

  function openMarketplace(id) {
    if (!id) return;
    Core.gallery.item(id).then(function (res) {
      var item = (res.data && res.data.item) || null;
      if (!item) {
        toast('작품을 찾을 수 없습니다.');
        return;
      }
      var host = document.createElement('div');
      host.className = 'ygl-drawer-overlay ygl-market-host';
      document.body.appendChild(host);
      host.addEventListener('click', function (e) {
        if (e.target === host) host.remove();
      });
      openMarketplaceModal(item, host);
    }).catch(function (err) {
      toast(err.message || 'Marketplace 등록을 시작할 수 없습니다.');
    });
  }

  function routeToStudio(item) {
    var payload = {
      studio: item.studio,
      type: item.type,
      prompt: item.user_prompt || item.prompt || '',
      user_prompt: item.user_prompt || item.prompt || '',
      optimized_prompt: item.optimized_prompt || '',
      provider: item.provider,
      model: item.model,
      settings: item.settings || {},
      reference_assets: item.reference_assets || []
    };
    try {
      sessionStorage.setItem('yoy_regenerate', JSON.stringify(payload));
      if (item.reference_assets && item.reference_assets.length) {
        sessionStorage.setItem('yoy_reference_asset', JSON.stringify(item.reference_assets[0]));
      }
    } catch (e) { /* ignore */ }
    var route = STUDIO_ROUTES[item.studio] || item.type || 'image';
    if (global.YooYStudioRoute) global.YooYStudioRoute(route);
    else {
      var nav = document.querySelector('[data-route="' + route + '"]');
      if (nav) nav.click();
    }
    closeDetail();
    toast(route + ' 스튜디오로 이동합니다.');
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

      case 'edit-meta':
        setEditMode(overlay, true);
        break;

      case 'cancel-edit':
        setEditMode(overlay, false);
        break;

      case 'save-meta':
        Core.gallery.update(item.id, {
          title: overlay.querySelector('#ygl-title-input').value,
          description: overlay.querySelector('#ygl-desc-input').value
        }).then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          setEditMode(overlay, false);
          toast('저장되었습니다.');
          notifyUpdated();
          var root = document.querySelector('.ygl-root');
          if (root) renderGrid(root.parentElement || root);
        }).catch(function (err) { toast(err.message); });
        break;

      case 'copy':
        Core.gallery.copy(item.id).then(function (res) {
          var prompt = (res.data && res.data.prompt) || '';
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(prompt).then(function () { toast('Prompt 복사됨'); });
          }
        }).catch(function (err) { toast(err.message); });
        break;

      case 'regenerate':
      case 'edit-studio':
        if (action === 'regenerate') {
          Core.gallery.regenerate(item.id).then(function (res) {
            var payload = res.data || {};
            try { sessionStorage.setItem('yoy_regenerate', JSON.stringify(payload)); } catch (e) { /* ignore */ }
            routeToStudio(Object.assign({}, item, payload));
          }).catch(function () { routeToStudio(item); });
        } else {
          routeToStudio(item);
        }
        break;

      case 'reference':
        Core.gallery.useAsReference(item.id, { studio: item.studio || (item.type + '-studio') }).then(function (res) {
          var asset = (res.data && res.data.asset) || res.asset;
          if (asset) {
            try { sessionStorage.setItem('yoy_reference_asset', JSON.stringify(asset)); } catch (e) { /* ignore */ }
          }
          routeToStudio(item);
        }).catch(function (err) { toast(err.message); });
        break;

      case 'favorite':
        Core.gallery.favorite(item.id).then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          toast(updated.favorite ? '즐겨찾기 추가' : '즐겨찾기 해제');
          notifyUpdated();
        }).catch(function (err) { toast(err.message); });
        break;

      case 'public':
      case 'private':
        Core.gallery.visibility(item.id, action === 'public').then(function (res) {
          var updated = (res.data && res.data.item) || item;
          updateItemInState(updated);
          toast(action === 'public' ? '공개로 설정' : '비공개로 설정');
          notifyUpdated();
        }).catch(function (err) { toast(err.message); });
        break;

      case 'community':
        Core.gallery.community(item.id).then(function () {
          toast('Community에 공유했습니다.');
          notifyUpdated();
        }).catch(function (err) { toast(err.message); });
        break;

      case 'share':
        Core.gallery.share(item.id).then(function (res) {
          var data = res.data || {};
          var copy = data.url || data.text || '';
          if (copy && navigator.clipboard) {
            navigator.clipboard.writeText(copy).then(function () {
              toast(data.text && !data.url ? '번역문 복사됨' : '공유 링크 복사됨');
            });
          } else if (!copy) {
            toast('공유할 내용이 없습니다.');
          }
        }).catch(function (err) { toast(err.message); });
        break;

      case 'marketplace-modal':
        openMarketplaceModal(item, overlay);
        break;

      case 'project':
      case 'project-move':
        if (global.YooYStudioPickProject) {
          global.YooYStudioPickProject(item.id);
          return;
        }
        Core.gallery.project(item.id).then(function () {
          toast('Project에 추가했습니다.');
          notifyUpdated();
        }).catch(function (err) { toast(err.message); });
        break;

      case 'duplicate':
        Core.gallery.duplicate(item.id).then(function () {
          toast('작품을 복제했습니다.');
          notifyUpdated();
          load(document.querySelector('.ygl-root') && document.querySelector('.ygl-root').parentElement);
        }).catch(function (err) { toast(err.message); });
        break;

      case 'delete':
        if (!confirm('이 작품을 삭제하시겠습니까?')) return;
        Core.gallery.remove(item.id).then(function () {
          state.items = state.items.filter(function (i) { return i.id !== item.id; });
          closeDetail();
          var root = document.querySelector('.ygl-root');
          if (root) renderGrid(root.parentElement || root);
          toast('삭제되었습니다.');
          notifyUpdated();
        }).catch(function (err) { toast(err.message); });
        break;
    }
  }

  function cardQuickMenu(item) {
    return '<div class="ygl-card-menu" data-ygl-menu="' + esc(item.id) + '">' +
      '<button type="button" class="ygl-card-menu-btn" data-ygl-menu-toggle="' + esc(item.id) + '" aria-label="메뉴">⋯</button>' +
      '<div class="ygl-card-menu-pop" hidden>' +
        '<button type="button" data-ygl-quick="open">열기</button>' +
        '<button type="button" data-ygl-quick="regenerate">재사용</button>' +
        '<button type="button" data-ygl-quick="download">다운로드</button>' +
        '<button type="button" data-ygl-quick="share">공유</button>' +
        '<button type="button" data-ygl-quick="marketplace-modal">판매</button>' +
        '<button type="button" data-ygl-quick="delete">삭제</button>' +
      '</div></div>';
  }

  function renderGrid(root) {
    var gridEl = root.querySelector ? root.querySelector('.ygl-grid') : null;
    if (!gridEl) return;

    var filtered = state.filter === 'all'
      ? state.items
      : state.items.filter(function (i) { return i.type === state.filter; });

    if (!filtered.length) {
      gridEl.innerHTML = '<div class="ygl-empty">저장된 생성물이 없습니다.<br>스튜디오에서 생성하면 자동으로 저장됩니다.</div>';
      return;
    }

    gridEl.innerHTML = filtered.map(function (item) {
      return '<article class="ygl-card' + (item.favorite ? ' is-fav' : '') + '" data-ygl-id="' + esc(item.id) + '">' +
        '<div class="ygl-thumb">' + thumbHtml(item) +
        '<span class="ygl-type-badge">' + esc(cardTypeBadge(item)) + '</span>' +
        (item.favorite ? '<span class="ygl-fav-badge">★</span>' : '') +
        '<div class="ygl-card-hover">' +
          '<button type="button" data-ygl-hover="open">열기</button>' +
          '<button type="button" data-ygl-hover="regenerate">재사용</button>' +
          '<button type="button" data-ygl-hover="share">공유</button>' +
          '<button type="button" data-ygl-hover="marketplace-modal">판매</button>' +
          '<button type="button" data-ygl-hover="delete">삭제</button>' +
        '</div></div>' +
        cardQuickMenu(item) +
        '<div class="ygl-card-body"><strong>' + esc(item.title || '작품') + '</strong>' +
        '<span>' + esc(formatDate(item.created_at)) + '</span></div></article>';
    }).join('');

    gridEl.querySelectorAll('.ygl-card').forEach(function (card) {
      card.addEventListener('click', function (e) {
        if (e.target.closest('[data-ygl-menu-toggle]') || e.target.closest('.ygl-card-menu-pop') || e.target.closest('.ygl-card-hover')) return;
        openDetail(card.dataset.yglId);
      });
      card.querySelectorAll('[data-ygl-hover]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var action = btn.dataset.yglHover;
          if (action === 'open') {
            openDetail(card.dataset.yglId);
            return;
          }
          var it = state.items.find(function (x) { return x.id === card.dataset.yglId; });
          if (!it) return;
          if (action === 'delete' || action === 'download' || action === 'share') {
            handleAction(action, it, document.body);
            return;
          }
          openDetail(it.id);
          setTimeout(function () {
            var ov = document.querySelector('.ygl-drawer-overlay');
            if (ov) handleAction(action, it, ov);
          }, 300);
        });
      });
      var toggle = card.querySelector('[data-ygl-menu-toggle]');
      if (toggle) {
        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          var pop = card.querySelector('.ygl-card-menu-pop');
          if (pop) pop.hidden = !pop.hidden;
        });
      }
      card.querySelectorAll('[data-ygl-quick]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var it = state.items.find(function (x) { return x.id === card.dataset.yglId; });
          if (!it) return;
          if (btn.dataset.yglQuick === 'open') openDetail(it.id);
          else handleAction(btn.dataset.yglQuick, it, document.body);
          var pop = card.querySelector('.ygl-card-menu-pop');
          if (pop) pop.hidden = true;
        });
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
      { id: 'translation', label: '번역' },
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

  function load(root) {
    var grid = root.querySelector('.ygl-grid');
    if (!grid) return;
    grid.innerHTML = '<div class="ygl-loading">갤러리 불러오는 중...</div>';
    Core.gallery.items().then(function (res) {
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
      '<span class="ygl-auto-hint">작품은 생성 시 자동 저장됩니다</span>' +
      '<button type="button" class="ygl-sync" data-ygl-sync title="갤러리 새로고침">새로고침</button>' +
      '</div>' +
      '<div class="ygl-grid"></div></div>';

    renderFilters(el);
    load(el);

    el.querySelector('[data-ygl-sync]').addEventListener('click', function () {
      Core.gallery.sync().then(function () {
        return Core.gallery.items();
      }).then(function (res) {
        state.items = (res.data && res.data.items) || [];
        renderGrid(el);
        toast('갤러리를 새로고침했습니다.');
      }).catch(function (err) { toast(err.message); });
    });

    document.addEventListener('yoy:gallery:updated', function () {
      if (el && el.querySelector('.ygl-root')) load(el);
    });
  }

  global.YooYGallery = {
    mount: mount,
    reload: reload,
    openDetail: openDetail,
    closeDetail: closeDetail,
    openMarketplace: openMarketplace
  };
})(window);
