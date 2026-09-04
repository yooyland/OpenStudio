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

  var state = { items: [], filter: 'all', selected: null, editing: false, query: '', sort: 'newest', historyMode: false };

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

  function remixCta(type) {
    switch (type) {
      case 'video': return '이 영상처럼 만들기';
      case 'music': return '이 스타일로 만들기';
      case 'voice': return '이어서 만들기';
      case 'writing': return '이 형식으로 쓰기';
      case 'translation': return '이 형식으로 번역하기';
      case 'avatar': return '이 캐릭터로 만들기';
      default: return '따라 만들기';
    }
  }

  function cardLayoutClass(item) {
    var t = item.type || 'image';
    var ar = String((item.aspect_ratio || (item.settings && item.settings.aspect_ratio) || '')).toLowerCase();
    if (t === 'video' || t === 'music' || t === 'voice') return ' ygl-card--wide';
    if (t === 'writing' || t === 'translation') return ' ygl-card--doc';
    if (ar.indexOf('16') === 0 || ar === 'landscape' || ar === 'wide') return ' ygl-card--wide';
    if (t === 'image' || t === 'avatar') return ' ygl-card--portrait';
    return '';
  }

  function thumbHtml(item) {
    if (item.asset_missing) return '<span class="ygl-thumb-missing">Asset missing</span>';
    var type = item.type || 'image';
    if (type === 'video' || type === 'avatar') {
      var poster = galleryImg(item, { size: 'thumb', className: 'yai-gallery-img' });
      if (!poster) {
        var videoUrl = global.YooYGalleryImage
          ? global.YooYGalleryImage.pickUrl(item, 'thumb')
          : (item.thumbnail_url || item.large_url || '');
        poster = videoUrl ? '<img src="' + esc(videoUrl) + '" alt="" class="yai-gallery-img" loading="lazy">' : '';
      }
      return poster + '<span class="ygl-thumb-play" aria-hidden="true">▶</span>';
    }
    if (type === 'image') {
      return galleryImg(item, { size: 'thumb', className: 'yai-gallery-img' });
    }
    if (type === 'music' || type === 'voice') {
      return galleryImg(item, { size: 'thumb', className: 'yai-gallery-img' }) +
        '<span class="ygl-thumb-wave" aria-hidden="true"></span>' +
        '<span class="ygl-thumb-icon">' + (TYPE_ICONS[type] || '📁') + '</span>';
    }
    if (type === 'writing') {
      return '<span class="ygl-thumb-icon">📝</span><span class="ygl-thumb-excerpt">' +
        esc(String((item.meta && (item.meta.content || item.meta.body)) || item.content || item.text || item.user_prompt || item.prompt || item.title || '').slice(0, 80)) + '</span>';
    }
    if (type === 'translation') {
      return '<span class="ygl-thumb-icon">🌐</span><span class="ygl-thumb-excerpt">' +
        esc(String(item.translated_text || item.prompt || item.title || '').slice(0, 80)) + '</span>';
    }
    return '<span class="ygl-thumb-icon">' + (TYPE_ICONS[type] || '📁') + '</span>';
  }

  function previewHtml(item) {
    if (item.asset_missing) {
      return '<div class="ygl-thumb-missing ygl-thumb-missing--preview">Image asset is missing.</div>';
    }
    var type = item.type;
    if (type === 'writing') {
      var body = (item.meta && (item.meta.content || item.meta.body)) || item.content || item.text || item.user_prompt || item.prompt || '';
      return '<div class="ygl-text-preview" style="white-space:pre-wrap">' + esc(String(body || '내용 없음')) + '</div>';
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

  function lineageHtml(item) {
    var parts = [];
    if (item.template_title || item.template_id) {
      parts.push(esc(item.template_title || '템플릿'));
    }
    if (item.remix_source_title || item.remix_source_id || item.source_gallery_id) {
      parts.push('따라 만들기');
    }
    if (item.studio || item.type) {
      parts.push(esc(item.type_label || typeLabel(item.type)));
    }
    parts.push('현재 작품');
    if (parts.length < 2) return '';
    return '<p class="ygl-lineage">' + parts.join(' → ') + '</p>';
  }

  function relatedHtml(item) {
    var pid = item.project_id || (item.meta && item.meta.project_id) || '';
    var src = item.remix_source_id || item.source_gallery_id || '';
    var related = state.items.filter(function (x) {
      if (!x || x.id === item.id) return false;
      var xpid = x.project_id || (x.meta && x.meta.project_id) || '';
      if (pid && xpid && pid === xpid) return true;
      if (src && (x.id === src || x.remix_source_id === item.id || x.source_gallery_id === item.id)) return true;
      return false;
    }).slice(0, 6);
    if (!related.length) return '';
    return '<div class="ygl-related"><h4>관련 작품</h4><div class="ygl-related-list">' +
      related.map(function (r) {
        return '<button type="button" class="ygl-related-item" data-ygl-related="' + esc(r.id) + '">' +
          esc(r.title || typeLabel(r.type)) + '</button>';
      }).join('') + '</div></div>';
  }

  function publicationOf(item) {
    var pub = item && item.publication ? item.publication : null;
    var community = pub ? !!pub.community : !!(item && item.community_shared);
    var marketStatus = String((item && item.marketplace_status) || 'none');
    var marketplace = pub
      ? !!pub.marketplace
      : !!(item && item.marketplace) && marketStatus !== 'none' && marketStatus !== 'delisted';
    return { community: community, marketplace: marketplace };
  }

  function publicationBadgesHtml(item) {
    var pub = publicationOf(item);
    var bits = [];
    if (pub.community) bits.push('<span class="ygl-pub-badge ygl-pub-badge--community">Community</span>');
    if (pub.marketplace) bits.push('<span class="ygl-pub-badge ygl-pub-badge--market">Marketplace</span>');
    if (!bits.length && !(item && item.public)) bits.push('<span class="ygl-pub-badge ygl-pub-badge--private">비공개</span>');
    return bits.length ? '<div class="ygl-pub-badges">' + bits.join('') + '</div>' : '';
  }

  function publicationStateHtml(item) {
    var pub = publicationOf(item);
    return '<section class="ygl-pub-state" aria-label="공개 상태">' +
      '<h4>공개 상태</h4>' +
      '<div class="ygl-pub-state-rows">' +
        '<div class="ygl-pub-state-row"><span>Community</span><strong>' + (pub.community ? '공개 중' : '비공개') + '</strong></div>' +
        '<div class="ygl-pub-state-row"><span>Marketplace</span><strong>' + (pub.marketplace ? '등록됨' : '미등록') + '</strong></div>' +
      '</div>' +
      '<div class="ygl-actions ygl-actions--pub">' +
        actionBtn('공개하기', 'publish-sheet', 'ygl-btn-primary') +
        actionBtn('공개 관리', 'publish-manage') +
      '</div></section>';
  }

  function drawerActionsHtml(item) {
    return '<div class="ygl-action-groups">' +
      '<div class="ygl-action-group ygl-action-group--primary"><div class="ygl-actions ygl-actions--stack">' +
        actionBtn(remixCta(item.type), 'regenerate', 'ygl-btn-primary') +
        actionBtn('공개하기', 'publish-sheet', 'ygl-btn-primary') +
      '</div></div>' +
      publicationStateHtml(item) +
      '<div class="ygl-action-group"><div class="ygl-actions">' +
        actionBtn('프로젝트에 추가', 'project') +
        actionBtn('다운로드', 'download') +
        actionBtn('복제', 'duplicate') +
        actionBtn('제목 수정', 'edit-meta') +
        actionBtn('작품 삭제', 'delete', 'ygl-btn-danger') +
      '</div></div></div>';
  }

  function drawerHtml(item) {
    var refs = (item.reference_assets || []).length
      ? '<ul class="ygl-ref-list">' + item.reference_assets.map(function (r) {
          return '<li>' + esc(r.url || r.label || 'Reference') + '</li>';
        }).join('') + '</ul>'
      : '<p class="ygl-muted">없음</p>';

    return '<aside class="ygl-drawer ygl-drawer--detail" role="dialog" aria-modal="true" aria-label="작품 상세">' +
      '<button type="button" class="ygl-close" data-ygl-close aria-label="닫기">×</button>' +
      '<header class="ygl-detail-head">' +
        '<p class="ygl-muted">' + esc(item.type_label || typeLabel(item.type)) + ' · ' + esc(item.created_label || formatDate(item.created_at)) + '</p>' +
        '<input class="ygl-title-input" id="ygl-title-input" value="' + esc(item.title || '') + '" readonly>' +
        lineageHtml(item) +
      '</header>' +
      '<div class="ygl-drawer-preview">' + previewHtml(item) + '</div>' +
      '<div class="ygl-drawer-body">' +
        '<textarea class="ygl-desc-input" id="ygl-desc-input" readonly placeholder="설명 없음">' + esc(item.description || '') + '</textarea>' +
        '<div class="ygl-edit-actions" id="ygl-edit-actions" hidden>' +
          '<button type="button" class="ygl-btn ygl-btn-primary" data-ygl-action="save-meta">저장</button>' +
          '<button type="button" class="ygl-btn" data-ygl-action="cancel-edit">취소</button>' +
        '</div>' +
        (item.user_prompt || item.prompt
          ? '<div class="ygl-prompt-block"><small>프롬프트</small><div class="ygl-prompt-box">' + esc(item.user_prompt || item.prompt) + '</div></div>'
          : '') +
        (item.project_title ? '<p class="ygl-muted">프로젝트 · ' + esc(item.project_title) + '</p>' : '') +
        relatedHtml(item) +
        drawerActionsHtml(item) +
        '<details class="ygl-tech">' +
          '<summary>세부 정보</summary>' +
          '<dl class="ygl-meta">' +
            metaRow('Provider', item.provider_label || item.provider || '—') +
            metaRow('Model', item.model || '—') +
            metaRow('Credits', String(item.credits_used || 0)) +
            metaRow('ID', item.id || '—') +
            metaRow('Visibility', item.visibility || (item.public ? 'public' : 'private')) +
            (item.type === 'translation' ? metaRow('Source', translationSourceBadge(item)) : '') +
          '</dl>' +
          (item.optimized_prompt ? '<div class="ygl-prompt-block"><small>Optimized Prompt</small><div class="ygl-prompt-box">' + esc(item.optimized_prompt) + '</div></div>' : '') +
          '<div class="ygl-prompt-block"><small>Reference Assets</small>' + refs + '</div>' +
        '</details>' +
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

    overlay.querySelectorAll('[data-ygl-related]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        openDetail(btn.dataset.yglRelated);
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

      try {
        if (global.YooYAIAssistant && typeof global.YooYAIAssistant.setSelectedAsset === 'function') {
          var thumb = '';
          if (global.YooYGalleryImage && typeof global.YooYGalleryImage.pickUrl === 'function') {
            thumb = global.YooYGalleryImage.pickUrl(item, 'thumb') || '';
          }
          global.YooYAIAssistant.setSelectedAsset({
            gallery_id: item.id,
            type: item.type || 'image',
            title: item.title || '',
            thumbnail: thumb || item.thumbnail || item.url || '',
            url: item.url || thumb || '',
            studio: item.studio || item.type || '',
            public_safe: !!(item.public || (item.visibility === 'public'))
          });
        }
      } catch (eCtx) { /* ignore */ }

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

  function closePublishHost(host) {
    if (!host) return;
    host.remove();
    document.body.classList.remove('ygl-publish-open');
  }

  function openPublishSheet(item, opts) {
    opts = opts || {};
    if (!item || !item.id) return;
    var pub = publicationOf(item);
    var host = document.createElement('div');
    host.className = 'ygl-drawer-overlay ygl-publish-host';
    host.setAttribute('role', 'dialog');
    host.setAttribute('aria-modal', 'true');
    host.setAttribute('aria-label', '작품 공개');
    host.innerHTML =
      '<div class="ygl-publish-sheet" tabindex="-1">' +
        '<button type="button" class="ygl-close" data-ygl-pub-close aria-label="닫기">×</button>' +
        '<h3>이 작품을 어디에 공개할까요?</h3>' +
        '<div class="ygl-publish-preview">' + previewHtml(item) + '</div>' +
        '<p class="ygl-muted">' + esc(item.title || '작품') + '</p>' +
        '<div class="ygl-publish-targets">' +
          '<button type="button" class="ygl-btn ygl-btn-primary" data-ygl-pub-target="community"' +
            (pub.community ? ' disabled' : '') + '>' +
            (pub.community ? 'Community 공유됨' : 'Community에 공유') + '</button>' +
          '<button type="button" class="ygl-btn ygl-btn-primary" data-ygl-pub-target="marketplace"' +
            (pub.marketplace ? ' disabled' : '') + '>' +
            (pub.marketplace ? 'Marketplace 등록됨' : 'Marketplace에 등록') + '</button>' +
          (!pub.community && !pub.marketplace
            ? '<button type="button" class="ygl-btn" data-ygl-pub-target="both">둘 다</button>'
            : '') +
        '</div>' +
        (opts.manage
          ? '<div class="ygl-publish-manage">' +
              (pub.community
                ? '<button type="button" class="ygl-btn" data-ygl-pub-target="unshare-community">Community에서 내리기</button>'
                : '') +
              (pub.marketplace
                ? '<button type="button" class="ygl-btn" data-ygl-pub-target="delist-marketplace">Marketplace 등록 해제</button>'
                : '') +
            '</div>'
          : '') +
        '<button type="button" class="ygl-btn" data-ygl-pub-close>닫기</button>' +
      '</div>';
    document.body.appendChild(host);
    document.body.classList.add('ygl-publish-open');
    var sheet = host.querySelector('.ygl-publish-sheet');
    if (sheet) sheet.focus();

    function onKey(e) {
      if (e.key === 'Escape') {
        closePublishHost(host);
        document.removeEventListener('keydown', onKey);
      }
    }
    document.addEventListener('keydown', onKey);

    host.addEventListener('click', function (e) {
      if (e.target === host || e.target.closest('[data-ygl-pub-close]')) {
        closePublishHost(host);
        document.removeEventListener('keydown', onKey);
      }
    });

    host.querySelectorAll('[data-ygl-pub-target]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var target = btn.getAttribute('data-ygl-pub-target');
        if (target === 'community' || target === 'both') {
          openCommunityPublish(item, host, target === 'both');
          return;
        }
        if (target === 'marketplace') {
          openMarketplaceModal(item, host);
          return;
        }
        if (target === 'unshare-community') {
          Core.gallery.unshareCommunity(item.id).then(function (res) {
            var updated = (res.data && res.data.item) || item;
            updateItemInState(updated);
            closePublishHost(host);
            toast('Community에서 내렸습니다. Gallery 작품은 그대로입니다.');
            notifyUpdated();
          }).catch(function (err) { toast(err.message); });
          return;
        }
        if (target === 'delist-marketplace') {
          Core.gallery.delistMarketplace(item.id).then(function (res) {
            var updated = (res.data && res.data.item) || item;
            updateItemInState(updated);
            closePublishHost(host);
            toast('Marketplace 등록을 해제했습니다. Gallery 작품은 그대로입니다.');
            notifyUpdated();
          }).catch(function (err) { toast(err.message); });
        }
      });
    });
  }

  function openCommunityPublish(item, host, continueToMarket) {
    var panel = document.createElement('div');
    panel.className = 'ygl-publish-panel';
    panel.innerHTML =
      '<h3>Community에 공유</h3>' +
      '<div class="ygl-publish-preview">' + previewHtml(item) + '</div>' +
      '<label class="yai-field"><span>한 줄 소개</span>' +
        '<input id="ygl-comm-caption" maxlength="120" value="' + esc(item.title || '') + '"></label>' +
      '<label class="yai-field"><span>공개 범위</span>' +
        '<input value="전체 공개" readonly></label>' +
      '<div class="ygl-market-actions">' +
        '<button type="button" class="ygl-btn ygl-btn-primary" id="ygl-comm-save">Community에 공유</button>' +
        '<button type="button" class="ygl-btn" id="ygl-comm-cancel">취소</button>' +
      '</div>';
    var sheet = host.querySelector('.ygl-publish-sheet');
    if (sheet) {
      sheet.innerHTML = '';
      sheet.appendChild(panel);
    } else {
      host.appendChild(panel);
    }

    panel.querySelector('#ygl-comm-cancel').addEventListener('click', function () {
      closePublishHost(host);
    });
    panel.querySelector('#ygl-comm-save').addEventListener('click', function () {
      var caption = (panel.querySelector('#ygl-comm-caption').value || '').trim();
      Core.gallery.community(item.id, { caption: caption }).then(function (res) {
        var updated = (res.data && res.data.item) || item;
        updateItemInState(updated);
        toast('Community에 공유했습니다.');
        notifyUpdated();
        if (continueToMarket) {
          openMarketplaceModal(updated, host);
          return;
        }
        closePublishHost(host);
      }).catch(function (err) { toast(err.message); });
    });
  }

  function openMarketplaceModal(item, overlay) {
    var modal = document.createElement('div');
    modal.className = 'ygl-market-modal';
    modal.innerHTML =
      '<div class="ygl-market-panel yai-form-grid yai-form-grid--2">' +
      '<h3 class="yai-form-span-2">Marketplace에 등록</h3>' +
      '<div class="yai-form-span-2 ygl-publish-preview">' + previewHtml(item) + '</div>' +
      '<label class="yai-field yai-form-span-2"><span>등록 제목</span><input id="ygl-mkt-title" value="' + esc(item.title || '') + '"></label>' +
      '<label class="yai-field yai-form-span-2"><span>설명</span><textarea id="ygl-mkt-desc" rows="3">' + esc(item.description || '') + '</textarea></label>' +
      '<label class="yai-field"><span>카테고리</span><input id="ygl-mkt-cat" value="general"></label>' +
      '<label class="yai-field"><span>표시 가격 (KRW, 참고)</span><input type="number" id="ygl-mkt-price" value="0" min="0"></label>' +
      '<label class="yai-field yai-form-span-2"><span>라이선스 표기</span><input id="ygl-mkt-license" value="standard"></label>' +
      '<p class="yai-form-span-2 ygl-muted">결제·구매 기능은 아직 없습니다. 카탈로그 등록만 진행됩니다.</p>' +
      '<div class="ygl-market-actions yai-form-span-2">' +
        '<button type="button" class="ygl-btn ygl-btn-primary" id="ygl-mkt-save">Marketplace에 등록</button>' +
        '<button type="button" class="ygl-btn" id="ygl-mkt-cancel">취소</button>' +
      '</div></div>';
    overlay.appendChild(modal);

    function closeMarketModal() {
      modal.remove();
      if (overlay.classList.contains('ygl-market-host') || overlay.classList.contains('ygl-publish-host')) {
        closePublishHost(overlay);
      }
    }

    modal.querySelector('#ygl-mkt-cancel').addEventListener('click', closeMarketModal);
    modal.querySelector('#ygl-mkt-save').addEventListener('click', function () {
      Core.gallery.marketplace(item.id, {
        title: modal.querySelector('#ygl-mkt-title').value,
        description: modal.querySelector('#ygl-mkt-desc').value,
        price: parseInt(modal.querySelector('#ygl-mkt-price').value, 10) || 0,
        category: modal.querySelector('#ygl-mkt-cat').value,
        license: modal.querySelector('#ygl-mkt-license').value,
        prompt_public: false,
        reference_public: false,
        allow_download: false
      }).then(function (res) {
        var updated = (res.data && res.data.item) || item;
        updateItemInState(updated);
        closeMarketModal();
        toast('Marketplace에 등록했습니다.');
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
      openPublishSheet(item, { manage: true });
    }).catch(function (err) {
      toast(err.message || '작품을 불러올 수 없습니다.');
    });
  }

  function openPublish(id) {
    if (!id) return;
    Core.gallery.item(id).then(function (res) {
      var item = (res.data && res.data.item) || null;
      if (!item) {
        toast('작품을 찾을 수 없습니다.');
        return;
      }
      openPublishSheet(item);
    }).catch(function (err) {
      toast(err.message || '작품을 불러올 수 없습니다.');
    });
  }

  function routeToStudio(item) {
    if (global.YooYHomeDashboard && typeof global.YooYHomeDashboard.storeRemixShell === 'function') {
      global.YooYHomeDashboard.storeRemixShell(item);
    } else {
      try {
        sessionStorage.setItem('yoy_home_remix', JSON.stringify({
          source: 'gallery_remix',
          gallery_id: item.id,
          id: item.id,
          type: item.type,
          studio: item.studio,
          prompt: item.user_prompt || item.prompt || '',
          thumbnail_url: item.thumbnail_url || '',
          preview_url: item.display_url || item.thumbnail_url || '',
          project_id: item.project_id || '',
          reference_assets: item.reference_assets || [],
          content_type: item.type || ''
        }));
        sessionStorage.setItem('yoy_regenerate', sessionStorage.getItem('yoy_home_remix'));
      } catch (e) { /* ignore */ }
    }
    var route = STUDIO_ROUTES[item.studio] || item.type || 'image';
    if (global.YooYStudioRoute) global.YooYStudioRoute(route);
    else {
      var nav = document.querySelector('[data-route="' + route + '"]');
      if (nav) nav.click();
    }
    closeDetail();
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
        openPublishSheet(item);
        break;

      case 'publish':
      case 'publish-sheet':
        openPublishSheet(item);
        break;

      case 'publish-manage':
        openPublishSheet(item, { manage: true });
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
        openPublishSheet(item);
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
        var pubDel = publicationOf(item);
        var warn = '이 작품을 Gallery에서 삭제하시겠습니까?';
        if (pubDel.community || pubDel.marketplace) {
          warn = '이 작품은 Community 또는 Marketplace에 공개되어 있습니다.\n작품을 삭제하면 공개 게시물도 더 이상 사용할 수 없습니다.\n계속할까요?';
        }
        if (!confirm(warn)) return;
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
      '<button type="button" class="ygl-card-menu-btn" data-ygl-menu-toggle="' + esc(item.id) + '" aria-label="더보기" aria-haspopup="true" aria-expanded="false">⋯</button>' +
      '<div class="ygl-card-menu-pop" hidden role="menu">' +
        '<button type="button" role="menuitem" data-ygl-quick="open">상세 보기</button>' +
        '<button type="button" role="menuitem" data-ygl-quick="publish">공개하기</button>' +
        '<button type="button" role="menuitem" data-ygl-quick="project">프로젝트에 추가</button>' +
        '<button type="button" role="menuitem" data-ygl-quick="download">다운로드</button>' +
        '<button type="button" role="menuitem" data-ygl-quick="duplicate">복제</button>' +
        '<button type="button" role="menuitem" data-ygl-quick="delete">작품 삭제</button>' +
      '</div></div>';
  }

  function renderGrid(root) {
    var gridEl = root.querySelector ? root.querySelector('.ygl-grid') : null;
    if (!gridEl) return;

    var filtered = state.filter === 'all'
      ? state.items
      : state.filter === 'project'
        ? state.items.filter(function (i) { return !!(i.project_id || (i.meta && i.meta.project_id)); })
        : state.items.filter(function (i) { return i.type === state.filter; });

    if (!filtered.length) {
      gridEl.innerHTML = '<div class="ygl-empty"><h3>아직 만든 작품이 없습니다.</h3>' +
        '<p>Composer나 템플릿으로 첫 작품을 만들어 보세요.</p>' +
        '<button type="button" class="ygl-btn ygl-btn-primary" data-ygl-empty-create>첫 작품 만들기</button></div>';
      var emptyBtn = gridEl.querySelector('[data-ygl-empty-create]');
      if (emptyBtn) {
        emptyBtn.addEventListener('click', function () {
          if (global.YooYStudioRoute) global.YooYStudioRoute('home');
        });
      }
      return;
    }

    gridEl.innerHTML = filtered.map(function (item) {
      return '<article class="ygl-card' + (item.favorite ? ' is-fav' : '') + cardLayoutClass(item) + '" data-ygl-id="' + esc(item.id) + '" tabindex="0" role="button" aria-label="' + esc((item.title || '작품') + ' 상세 보기') + '">' +
        '<div class="ygl-thumb">' + thumbHtml(item) +
        '<span class="ygl-type-badge">' + esc(cardTypeBadge(item)) + '</span>' +
        (item.favorite ? '<span class="ygl-fav-badge">★</span>' : '') +
        publicationBadgesHtml(item) +
        '<div class="ygl-card-hover">' +
          '<button type="button" data-ygl-hover="regenerate">' + esc(remixCta(item.type)) + '</button>' +
        '</div></div>' +
        cardQuickMenu(item) +
        '<div class="ygl-card-body"><strong>' + esc(item.title || '작품') + '</strong>' +
        '<span>' + esc(formatDate(item.created_at)) + '</span></div></article>';
    }).join('');

    try {
      if (global.YooYOnboarding && typeof global.YooYOnboarding.maybeShowGalleryIntro === 'function') {
        global.YooYOnboarding.maybeShowGalleryIntro(root);
      }
    } catch (obG) { /* ignore */ }

    gridEl.querySelectorAll('.ygl-card').forEach(function (card) {
      card.addEventListener('click', function (e) {
        if (e.target.closest('[data-ygl-menu-toggle]') || e.target.closest('.ygl-card-menu-pop') || e.target.closest('.ygl-card-hover')) return;
        openDetail(card.dataset.yglId);
      });
      card.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        if (e.target.closest('[data-ygl-menu-toggle]') || e.target.closest('.ygl-card-menu-pop') || e.target.closest('button')) return;
        e.preventDefault();
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
          if (action === 'regenerate') {
            handleAction('regenerate', it, document.body);
            return;
          }
        });
      });
      var toggle = card.querySelector('[data-ygl-menu-toggle]');
      if (toggle) {
        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          var pop = card.querySelector('.ygl-card-menu-pop');
          var open = pop && pop.hidden;
          document.querySelectorAll('.ygl-card-menu-pop').forEach(function (other) {
            if (other !== pop) other.hidden = true;
          });
          if (pop) pop.hidden = !open;
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
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
      { id: 'image', label: '이미지' },
      { id: 'video', label: '영상' },
      { id: 'writing', label: '글' },
      { id: 'music', label: '음악' },
      { id: 'voice', label: '음성' },
      { id: 'avatar', label: '아바타' },
      { id: 'translation', label: '번역' },
      { id: 'project', label: '프로젝트' }
    ];

    var filtersEl = root.querySelector('.ygl-filters');
    if (!filtersEl) return;
    filtersEl.innerHTML = filters.map(function (f) {
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

  function itemQueryParams() {
    var params = { sort: state.sort || 'newest' };
    if (state.query) params.q = state.query;
    if (state.filter && state.filter !== 'all' && state.filter !== 'project') params.type = state.filter;
    return params;
  }

  function load(root) {
    var grid = root.querySelector('.ygl-grid') || root.querySelector('.ygl-history');
    if (!grid) return;
    grid.innerHTML = '<div class="ygl-loading">갤러리 불러오는 중...</div>';
    Core.gallery.items(itemQueryParams()).then(function (res) {
      state.items = (res.data && res.data.items) || [];
      if (state.historyMode) renderHistory(root);
      else renderGrid(root);
    }).catch(function () {
      grid.innerHTML = '<div class="ygl-empty">갤러리를 불러올 수 없습니다.</div>';
    });
  }

  function renderHistory(root) {
    var list = root.querySelector('.ygl-history');
    if (!list) return;
    if (!state.items.length) {
      list.innerHTML = '<div class="ygl-empty"><h3>아직 생성 기록이 없습니다.</h3><p>생성 활동이 Gallery 작품과 연결되면 여기에 표시됩니다.</p></div>';
      return;
    }
    list.innerHTML = '<ul class="ygl-history-list">' + state.items.map(function (item) {
      return '<li class="ygl-history-item">' +
        '<button type="button" class="ygl-history-open" data-ygl-id="' + esc(item.id) + '">' +
        '<strong>' + esc(item.title || '작품') + '</strong>' +
        '<span>' + esc(typeLabel(item.type)) + ' · ' + esc(formatDate(item.created_at)) + '</span></button></li>';
    }).join('') + '</ul>';
    list.querySelectorAll('[data-ygl-id]').forEach(function (btn) {
      btn.addEventListener('click', function () { openDetail(btn.dataset.yglId); });
    });
  }

  function reload(root) {
    if (root) load(root);
    else {
      var el = document.querySelector('.ygl-root');
      if (el && el.parentElement) load(el.parentElement);
    }
  }

  function bindSearchSort(el) {
    var search = el.querySelector('[data-ygl-search]');
    var sort = el.querySelector('[data-ygl-sort]');
    if (search) {
      search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          state.query = search.value || '';
          load(el);
        }
      });
      search.addEventListener('change', function () {
        state.query = search.value || '';
        load(el);
      });
    }
    if (sort) {
      sort.addEventListener('change', function () {
        state.sort = sort.value || 'newest';
        load(el);
      });
    }
  }

  function mount(el, opts) {
    if (!el) return;
    opts = opts || {};
    state.historyMode = !!opts.historyMode;
    state.filter = 'all';
    state.query = '';
    state.sort = 'newest';

    // Phase 9 — Assistant may prefill Gallery search / type filter via sessionStorage.
    try {
      var aq = sessionStorage.getItem('yoy_assistant_gallery_query');
      var at = sessionStorage.getItem('yoy_assistant_gallery_type');
      if (aq) {
        state.query = aq;
        sessionStorage.removeItem('yoy_assistant_gallery_query');
      }
      if (at && at !== 'all') {
        state.filter = at;
        sessionStorage.removeItem('yoy_assistant_gallery_type');
      }
    } catch (ePend) { /* ignore */ }

    if (state.historyMode) {
      el.innerHTML =
        '<div class="ygl-root ygl-root--history">' +
        '<p class="ygl-muted">생성·활동 기록입니다. 작품 본문은 Gallery에 있습니다.</p>' +
        '<div class="ygl-history"></div></div>';
      load(el);
      return;
    }

    el.innerHTML =
      '<div class="ygl-root">' +
      '<div class="ygl-toolbar">' +
      '<div class="ygl-filters"></div>' +
      '<label class="ygl-search"><span class="ygl-sr">검색</span>' +
      '<input type="search" data-ygl-search placeholder="제목, 프롬프트, 유형" aria-label="Gallery 검색" value="' +
      String(state.query || '').replace(/"/g, '&quot;') + '"></label>' +
      '<label class="ygl-sort"><span class="ygl-sr">정렬</span>' +
      '<select data-ygl-sort aria-label="정렬">' +
      '<option value="newest">최근 생성</option>' +
      '<option value="oldest">오래된 순</option>' +
      '<option value="updated">최근 수정</option>' +
      '</select></label>' +
      '<button type="button" class="ygl-sync" data-ygl-sync title="갤러리 새로고침">새로고침</button>' +
      '</div>' +
      '<div class="ygl-grid"></div></div>';

    renderFilters(el);
    bindSearchSort(el);
    load(el);

    el.querySelector('[data-ygl-sync]').addEventListener('click', function () {
      Core.gallery.sync().then(function () {
        return Core.gallery.items(itemQueryParams());
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
    openMarketplace: openMarketplace,
    openPublish: openPublish
  };
})(window);
