/**
 * YooY Creative Canvas v1 — visual AI workflow board (Project-scoped).
 * Gallery SoT: nodes store gallery_id only.
 */
(function (global) {
  'use strict';

  var Core = global.YooYAIStudioCore;
  var saveTimer = null;
  var state = {
    projectId: '',
    canvas: null,
    selectedId: '',
    dragging: null,
    panning: false,
    panX: 0,
    panY: 0,
    zoom: 1,
    lastX: 0,
    lastY: 0,
    dirty: false
  };

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function toast(msg, isErr) {
    if (global.showToast) global.showToast(msg, !!isErr);
    else if (console && console.log) console.log(msg);
  }

  function api() {
    return Core && Core.projects ? Core.projects : null;
  }

  function pickThumb(meta, item) {
    if (global.YooYGalleryImage && item && typeof global.YooYGalleryImage.pickUrl === 'function') {
      return global.YooYGalleryImage.pickUrl(item, 'card')
        || global.YooYGalleryImage.pickUrl(item, 'large')
        || global.YooYGalleryImage.pickUrl(item, 'full')
        || '';
    }
    meta = meta || {};
    return meta.thumbnail_url || meta.large_url || meta.full_url || '';
  }

  function scheduleSave(root) {
    state.dirty = true;
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(function () { persist(root); }, 700);
  }

  function persist(root) {
    var projects = api();
    if (!projects || !state.projectId || !state.canvas) return Promise.resolve();
    state.canvas.viewport = { x: state.panX, y: state.panY, zoom: state.zoom };
    var status = root && root.querySelector('[data-ycc-status]');
    if (status) status.textContent = '저장 중…';
    return projects.saveCanvas(state.projectId, state.canvas).then(function (res) {
      state.canvas = (res.data && res.data.canvas) || state.canvas;
      state.dirty = false;
      if (status) status.textContent = '저장됨';
    }).catch(function (err) {
      if (status) status.textContent = '저장 실패';
      toast((err && err.message) || 'Canvas 저장에 실패했습니다.', true);
    });
  }

  function load(projectId) {
    var projects = api();
    if (!projects) return Promise.reject(new Error('Projects API 없음'));
    state.projectId = projectId;
    return projects.getCanvas(projectId).then(function (res) {
      state.canvas = (res.data && res.data.canvas) || null;
      if (state.canvas && state.canvas.viewport) {
        state.panX = state.canvas.viewport.x || 0;
        state.panY = state.canvas.viewport.y || 0;
        state.zoom = state.canvas.viewport.zoom || 1;
      }
      return state.canvas;
    });
  }

  function nodeLabel(n) {
    var meta = n.metadata_json || {};
    if (n.node_type === 'prompt') return n.prompt_text || 'Prompt';
    if (n.node_type === 'note') return n.note_text || 'Note';
    return meta.title || n.prompt_text || n.note_text || n.node_type;
  }

  function renderNode(n) {
    var meta = n.metadata_json || {};
    var title = esc(nodeLabel(n)).slice(0, 80);
    var typeLabel = {
      prompt: 'Prompt',
      reference_image: 'Reference',
      generated_image: 'Result',
      note: 'Note',
      group: 'Group',
      action: 'Action',
      studio_handoff: 'Studio'
    }[n.node_type] || n.node_type;
    var media = '';
    if (n.node_type === 'generated_image' || n.node_type === 'reference_image') {
      var src = esc(meta.thumbnail_url || '');
      media = src
        ? '<div class="ycc-node__media"><img src="' + src + '" alt="" loading="lazy" decoding="async"></div>'
        : '<div class="ycc-node__media ycc-node__media--empty">이미지</div>';
    }
    var body = '';
    if (n.node_type === 'prompt' || n.node_type === 'note') {
      body = '<p class="ycc-node__body">' + esc((n.prompt_text || n.note_text || '').slice(0, 140)) + '</p>';
    }
    var sel = state.selectedId === n.node_id ? ' is-selected' : '';
    return '<article class="ycc-node ycc-node--' + esc(n.node_type) + sel + '" data-node-id="' + esc(n.node_id) + '" ' +
      'style="left:' + n.x + 'px;top:' + n.y + 'px;width:' + n.width + 'px;z-index:' + (n.z_index || 1) + '">' +
      '<header class="ycc-node__head"><span>' + esc(typeLabel) + '</span></header>' +
      media +
      '<h4 class="ycc-node__title">' + title + '</h4>' +
      body +
      '</article>';
  }

  function edgePath(from, to) {
    var x1 = from.x + from.width / 2;
    var y1 = from.y + Math.min(from.height || 160, 120);
    var x2 = to.x + to.width / 2;
    var y2 = to.y;
    var mid = (y1 + y2) / 2;
    return 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + mid + ' ' + x2 + ',' + mid + ' ' + x2 + ',' + y2;
  }

  function renderEdges() {
    if (!state.canvas) return '';
    var map = {};
    (state.canvas.nodes || []).forEach(function (n) { map[n.node_id] = n; });
    var paths = (state.canvas.edges || []).map(function (e) {
      var a = map[e.from_node_id];
      var b = map[e.to_node_id];
      if (!a || !b) return '';
      return '<path class="ycc-edge" d="' + edgePath(a, b) + '" data-rel="' + esc(e.relation_type || '') + '"></path>';
    }).join('');
    return '<svg class="ycc-edges" aria-hidden="true">' + paths + '</svg>';
  }

  function paintBoard(root) {
    var board = root.querySelector('[data-ycc-board]');
    if (!board || !state.canvas) return;
    var world = root.querySelector('[data-ycc-world]');
    if (!world) return;
    world.style.transform = 'translate(' + state.panX + 'px,' + state.panY + 'px) scale(' + state.zoom + ')';
    world.innerHTML = renderEdges() + (state.canvas.nodes || []).map(renderNode).join('');
    var zoomLabel = root.querySelector('[data-ycc-zoom-label]');
    if (zoomLabel) zoomLabel.textContent = Math.round(state.zoom * 100) + '%';
  }

  function openInspector(root) {
    var panel = root.querySelector('[data-ycc-inspector]');
    if (!panel) return;
    var n = (state.canvas.nodes || []).find(function (x) { return x.node_id === state.selectedId; });
    if (!n) {
      panel.innerHTML = '<p class="ycc-muted">노드를 선택하세요.</p>';
      return;
    }
    var actions = '';
    if (n.node_type === 'generated_image' || n.node_type === 'reference_image') {
      actions +=
        '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-ycc-act="view-original">원본 보기</button>' +
        '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-act="studio">Studio에서 이어 만들기</button>' +
        '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-act="gallery">Gallery에서 보기</button>' +
        '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-act="publish">공개하기</button>';
    }
    if (n.node_type === 'prompt') {
      actions +=
        '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-ycc-act="studio-prompt">Image Studio로 생성</button>' +
        '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-act="edit-prompt">편집</button>';
    }
    if (n.node_type === 'note') {
      actions += '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-act="edit-note">편집</button>' +
        '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-act="to-prompt">Prompt로 변환</button>';
    }
    actions += '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-act="delete-node">노드 삭제</button>';
    panel.innerHTML =
      '<div class="ycc-inspector__type">' + esc(n.node_type) + '</div>' +
      '<h3>' + esc(nodeLabel(n)) + '</h3>' +
      (n.linked_gallery_id ? '<p class="ycc-muted">gallery_id · ' + esc(n.linked_gallery_id) + '</p>' : '') +
      '<div class="ycc-inspector__actions">' + actions + '</div>';
  }

  function addNode(type, extras) {
    extras = extras || {};
    var projects = api();
    if (!projects || !state.projectId) return;
    var payload = Object.assign({
      node_type: type,
      x: 120 + Math.random() * 80 - state.panX / state.zoom,
      y: 120 + Math.random() * 60 - state.panY / state.zoom,
      width: type === 'prompt' || type === 'note' ? 260 : 220,
      height: type === 'prompt' || type === 'note' ? 160 : 260,
      prompt_text: extras.prompt_text || '',
      note_text: extras.note_text || '',
      linked_gallery_id: extras.gallery_id || extras.linked_gallery_id || '',
      metadata_json: extras.metadata_json || {}
    }, extras);
    return projects.addCanvasNode(state.projectId, payload).then(function (res) {
      state.canvas = (res.data && res.data.canvas) || state.canvas;
      toast('노드를 추가했습니다.');
      return state.canvas;
    });
  }

  function runAction(act, root) {
    var n = (state.canvas.nodes || []).find(function (x) { return x.node_id === state.selectedId; });
    if (!n) return;
    if (act === 'delete-node') {
      api().removeCanvasNode(state.projectId, n.node_id).then(function (res) {
        state.canvas = (res.data && res.data.canvas) || state.canvas;
        state.selectedId = '';
        paintBoard(root);
        openInspector(root);
      });
      return;
    }
    if (act === 'edit-prompt' || act === 'edit-note') {
      var cur = act === 'edit-prompt' ? (n.prompt_text || '') : (n.note_text || '');
      var next = global.prompt(act === 'edit-prompt' ? 'Prompt 편집' : 'Note 편집', cur);
      if (next == null) return;
      var patch = act === 'edit-prompt' ? { prompt_text: next } : { note_text: next };
      api().updateCanvasNode(state.projectId, n.node_id, patch).then(function (res) {
        state.canvas = (res.data && res.data.canvas) || state.canvas;
        paintBoard(root);
        openInspector(root);
      });
      return;
    }
    if (act === 'to-prompt') {
      api().addCanvasNode(state.projectId, {
        node_type: 'prompt',
        prompt_text: n.note_text || '',
        x: n.x + 40,
        y: n.y + 40
      }).then(function (res) {
        state.canvas = (res.data && res.data.canvas) || state.canvas;
        paintBoard(root);
      });
      return;
    }
    if (act === 'studio-prompt' || act === 'studio') {
      try {
        if (n.prompt_text) {
          sessionStorage.setItem('yoy_home_prompt', n.prompt_text);
          sessionStorage.setItem('yoy_home_original_prompt', n.prompt_text);
        }
        if (n.linked_gallery_id) {
          sessionStorage.setItem('yoy_reference_asset', JSON.stringify({
            gallery_id: n.linked_gallery_id,
            title: (n.metadata_json && n.metadata_json.title) || '',
            thumbnail_url: (n.metadata_json && n.metadata_json.thumbnail_url) || '',
            source: 'canvas'
          }));
        }
        sessionStorage.setItem('yoy_assistant_project_id', state.projectId);
        sessionStorage.setItem('yoy_canvas_return', JSON.stringify({
          project_id: state.projectId,
          from_node_id: n.node_id
        }));
      } catch (e) { /* ignore */ }
      if (global.YooYStudioRoute) global.YooYStudioRoute('image', { source_context: 'canvas' });
      else if (global.YooYAIStudio && global.YooYAIStudio.route) global.YooYAIStudio.route('image');
      return;
    }
    if (act === 'view-original' && n.linked_gallery_id && global.YooYOriginalImageViewer) {
      if (Core && Core.gallery && Core.gallery.item) {
        Core.gallery.item(n.linked_gallery_id).then(function (res) {
          var item = (res.data && res.data.item) || { id: n.linked_gallery_id, full_url: (n.metadata_json || {}).thumbnail_url };
          global.YooYOriginalImageViewer.openFromItem(item);
        }).catch(function () {
          global.YooYOriginalImageViewer.open({ url: (n.metadata_json || {}).thumbnail_url, title: nodeLabel(n) });
        });
      }
      return;
    }
    if (act === 'gallery' && n.linked_gallery_id && global.YooYGallery) {
      if (global.YooYStudioRoute) global.YooYStudioRoute('works');
      global.YooYGallery.openDetail(n.linked_gallery_id);
      return;
    }
    if (act === 'publish' && n.linked_gallery_id && global.YooYGallery && global.YooYGallery.openPublish) {
      global.YooYGallery.openPublish(n.linked_gallery_id);
    }
  }

  function bind(root) {
    root.addEventListener('click', function (e) {
      var add = e.target.closest('[data-ycc-add]');
      if (add) {
        var t = add.getAttribute('data-ycc-add');
        if (t === 'prompt') {
          var p = global.prompt('Prompt 텍스트', '');
          if (p == null) return;
          addNode('prompt', { prompt_text: p }).then(function () { paintBoard(root); });
        } else if (t === 'note') {
          var note = global.prompt('Note', '');
          if (note == null) return;
          addNode('note', { note_text: note }).then(function () { paintBoard(root); });
        } else if (t === 'fit') {
          state.panX = 0; state.panY = 0; state.zoom = 1;
          paintBoard(root);
          scheduleSave(root);
        } else if (t === 'zoom-in') {
          state.zoom = Math.min(2.5, state.zoom * 1.15);
          paintBoard(root);
          scheduleSave(root);
        } else if (t === 'zoom-out') {
          state.zoom = Math.max(0.35, state.zoom / 1.15);
          paintBoard(root);
          scheduleSave(root);
        }
        return;
      }
      var actBtn = e.target.closest('[data-ycc-act]');
      if (actBtn) {
        runAction(actBtn.getAttribute('data-ycc-act'), root);
        return;
      }
      var node = e.target.closest('[data-node-id]');
      if (node) {
        state.selectedId = node.getAttribute('data-node-id');
        paintBoard(root);
        openInspector(root);
      }
    });

    var stage = root.querySelector('[data-ycc-stage]');
    if (!stage) return;

    stage.addEventListener('pointerdown', function (e) {
      var node = e.target.closest('[data-node-id]');
      if (node && e.button === 0) {
        state.dragging = {
          id: node.getAttribute('data-node-id'),
          ox: e.clientX,
          oy: e.clientY
        };
        var n = (state.canvas.nodes || []).find(function (x) { return x.node_id === state.dragging.id; });
        if (n) {
          state.dragging.nx = n.x;
          state.dragging.ny = n.y;
        }
        stage.setPointerCapture(e.pointerId);
        return;
      }
      if (e.button === 0 || e.button === 1) {
        state.panning = true;
        state.lastX = e.clientX;
        state.lastY = e.clientY;
        stage.setPointerCapture(e.pointerId);
      }
    });
    stage.addEventListener('pointermove', function (e) {
      if (state.dragging) {
        var dx = (e.clientX - state.dragging.ox) / state.zoom;
        var dy = (e.clientY - state.dragging.oy) / state.zoom;
        var n = (state.canvas.nodes || []).find(function (x) { return x.node_id === state.dragging.id; });
        if (n) {
          n.x = state.dragging.nx + dx;
          n.y = state.dragging.ny + dy;
          paintBoard(root);
        }
        return;
      }
      if (state.panning) {
        state.panX += e.clientX - state.lastX;
        state.panY += e.clientY - state.lastY;
        state.lastX = e.clientX;
        state.lastY = e.clientY;
        paintBoard(root);
      }
    });
    function endDrag() {
      if (state.dragging) {
        scheduleSave(root);
      } else if (state.panning) {
        scheduleSave(root);
      }
      state.dragging = null;
      state.panning = false;
    }
    stage.addEventListener('pointerup', endDrag);
    stage.addEventListener('pointercancel', endDrag);
    stage.addEventListener('wheel', function (e) {
      e.preventDefault();
      var factor = e.deltaY < 0 ? 1.08 : 1 / 1.08;
      state.zoom = Math.max(0.35, Math.min(2.5, state.zoom * factor));
      paintBoard(root);
      scheduleSave(root);
    }, { passive: false });

    root.addEventListener('keydown', function (e) {
      if (e.key === 'Delete' || e.key === 'Backspace') {
        if (state.selectedId && document.activeElement && document.activeElement.tagName === 'INPUT') return;
        if (state.selectedId) runAction('delete-node', root);
      }
    });
  }

  function shellHtml() {
    return '<div class="ycc-root" tabindex="0">' +
      '<div class="ycc-toolbar">' +
        '<div class="ycc-toolbar__left">' +
          '<strong>Creative Canvas</strong>' +
          '<span class="ycc-muted" data-ycc-status>준비됨</span>' +
        '</div>' +
        '<div class="ycc-toolbar__right">' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-add="prompt">+ Prompt</button>' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-add="note">+ Note</button>' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-add="zoom-out">−</button>' +
          '<span data-ycc-zoom-label>100%</span>' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-add="zoom-in">+</button>' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ycc-add="fit">화면 맞춤</button>' +
        '</div>' +
      '</div>' +
      '<div class="ycc-layout">' +
        '<div class="ycc-stage" data-ycc-stage>' +
          '<div class="ycc-board" data-ycc-board><div class="ycc-world" data-ycc-world></div></div>' +
        '</div>' +
        '<aside class="ycc-inspector" data-ycc-inspector><p class="ycc-muted">노드를 선택하세요.</p></aside>' +
      '</div>' +
      '<p class="ycc-hint">드래그로 노드 이동 · 빈 공간 드래그로 팬 · 휠로 줌 · Gallery 작품은 gallery_id로만 연결됩니다.</p>' +
    '</div>';
  }

  function mount(container, projectId) {
    if (!container || !projectId) return;
    container.innerHTML = shellHtml();
    var root = container.querySelector('.ycc-root');
    bind(root);
    load(projectId).then(function () {
      // Enrich thumbs from workspace works if available
      var works = (global.YooYAIStudio && global.YooYAIStudio._workspaceWorks) || [];
      if (state.canvas && works.length) {
        state.canvas.nodes.forEach(function (n) {
          if (!n.linked_gallery_id) return;
          var w = works.find(function (x) {
            return String(x.id) === String(n.linked_gallery_id) || String(x.gallery_id || '') === String(n.linked_gallery_id);
          });
          if (w) {
            n.metadata_json = n.metadata_json || {};
            n.metadata_json.title = w.title || n.metadata_json.title;
            n.metadata_json.thumbnail_url = pickThumb(n.metadata_json, w) || n.metadata_json.thumbnail_url;
          }
        });
      }
      paintBoard(root);
      openInspector(root);
    }).catch(function (err) {
      container.innerHTML = '<div class="yai-empty"><h3>Canvas를 불러오지 못했습니다</h3><p>' +
        esc((err && err.message) || '') + '</p></div>';
    });
  }

  function addGalleryToCanvas(projectId, galleryId, opts) {
    opts = opts || {};
    var projects = api();
    if (!projects || !projectId || !galleryId) {
      return Promise.reject(new Error('프로젝트 또는 작품 정보가 없습니다.'));
    }
    return projects.addCanvasNode(projectId, {
      node_type: opts.node_type || 'generated_image',
      linked_gallery_id: galleryId,
      x: opts.x || 320,
      y: opts.y || 160,
      metadata_json: opts.metadata_json || {}
    });
  }

  global.YooYCreativeCanvas = {
    mount: mount,
    addGalleryToCanvas: addGalleryToCanvas,
    load: load
  };
})(window);
