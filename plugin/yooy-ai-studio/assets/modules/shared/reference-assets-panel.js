(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core || !Core.referenceAssets) return;

  var STUDIO_PROFILES = {
    'image-studio': { accept: 'image/png,image/jpeg,image/webp', roles: ['image', 'logo', 'character', 'product', 'color_palette'], max: 8 },
    'video-studio': { accept: 'image/*,video/*', roles: ['image', 'video', 'logo', 'product'], max: 6 },
    'music-studio': { accept: 'audio/*,.txt,.md', roles: ['audio', 'voice', 'lyrics'], max: 4 },
    'voice-studio': { accept: 'audio/*,.txt,.pdf,.docx', roles: ['audio', 'voice', 'script', 'document'], max: 3 },
    'writing-studio': { accept: '.pdf,.docx,.txt,.md,text/plain', roles: ['document', 'script'], max: 5 }
  };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function badge(type) {
    return '<span class="yra-badge">' + esc(type || 'asset') + '</span>';
  }

  function thumbHtml(asset) {
    var url = asset.thumbnail_url || asset.url || '';
    var type = (asset.asset_type || asset.role || '').toLowerCase();
    if (type === 'video' && url) {
      return '<video src="' + esc(url) + '" muted></video>';
    }
    if (type === 'audio' || type === 'voice') {
      return '<span>♪</span>';
    }
    if (type === 'document' || type === 'lyrics' || type === 'script') {
      return '<span>📄</span>';
    }
    if (url) {
      return '<img src="' + esc(url) + '" alt="">';
    }
    return '<span>◇</span>';
  }

  function create(options) {
    options = options || {};
    var studio = options.studio || 'image-studio';
    var profile = STUDIO_PROFILES[studio] || STUDIO_PROFILES['image-studio'];
    var state = {
      assets: (options.assets || []).slice(),
      collapsed: options.collapsed !== false,
      uploading: false,
      progress: 0,
      projectId: options.projectId || ''
    };

    var root = document.createElement('div');
    root.className = 'yra-panel' + (state.collapsed ? ' is-collapsed' : '');

    function emit() {
      if (typeof options.onChange === 'function') {
        options.onChange(state.assets.slice());
      }
    }

    function setAssets(list) {
      state.assets = (list || []).slice(0, profile.max);
      render();
      emit();
    }

    function addAsset(asset) {
      if (!asset || !asset.url) return;
      if (state.assets.length >= profile.max) {
        state.assets = state.assets.slice(0, profile.max - 1);
      }
      state.assets.unshift(asset);
      state.assets = state.assets.slice(0, profile.max);
      render();
      emit();
    }

    function removeAsset(id) {
      Core.referenceAssets.remove(id).catch(function () {});
      state.assets = state.assets.filter(function (a) { return a.id !== id; });
      render();
      emit();
    }

    function uploadFile(file) {
      if (!file || state.uploading) return;
      if (state.assets.length >= profile.max) return;
      state.uploading = true;
      state.progress = 10;
      render();
      var reader = new FileReader();
      reader.onload = function (ev) {
        state.progress = 55;
        render();
        Core.referenceAssets.upload({
          title: file.name,
          filename: file.name,
          file_base64: ev.target.result,
          mime_type: file.type,
          studio: studio,
          project_id: state.projectId,
          role: guessRole(file)
        }).then(function (res) {
          addAsset((res.data && res.data.asset) || res.asset);
        }).catch(function (err) {
          alert(err.message || 'Upload failed');
        }).finally(function () {
          state.uploading = false;
          state.progress = 0;
          render();
        });
      };
      reader.readAsDataURL(file);
    }

    function guessRole(file) {
      var mime = (file.type || '').toLowerCase();
      if (mime.indexOf('video') === 0) return 'video';
      if (mime.indexOf('audio') === 0) return 'audio';
      if (mime.indexOf('image') === 0) return 'image';
      var name = (file.name || '').toLowerCase();
      if (name.indexOf('.txt') > -1 || name.indexOf('.md') > -1) return 'document';
      if (name.indexOf('.pdf') > -1 || name.indexOf('.docx') > -1) return 'document';
      return profile.roles[0] || 'image';
    }

    function openPicker(kind) {
      closePicker();
      var picker = document.createElement('div');
      picker.className = 'yra-picker';
      picker.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center">' +
        '<strong style="color:#d8a63a">' + esc(kind === 'gallery' ? 'Gallery' : kind === 'import' ? 'Import Library' : 'Project Assets') + '</strong>' +
        '<button type="button" class="yra-src-btn" data-yra-close>닫기</button></div>' +
        '<div class="yra-picker-body"><p class="yra-empty">불러오는 중…</p></div>';
      document.body.appendChild(picker);
      picker.querySelector('[data-yra-close]').addEventListener('click', closePicker);

      var body = picker.querySelector('.yra-picker-body');
      if (kind === 'gallery' && Core.gallery) {
        Core.gallery.items({ sync: 1 }).then(function (res) {
          var items = (res.data && res.data.items) || [];
          if (!items.length) {
            body.innerHTML = '<p class="yra-empty">Gallery 항목이 없습니다.</p>';
            return;
          }
          body.innerHTML = '<div class="yra-picker-grid">' + items.map(function (item) {
            var thumb = item.thumbnail_url || item.image_url || item.output_url || '';
            return '<button type="button" class="yra-picker-card" data-gid="' + esc(item.id) + '">' +
              (thumb ? '<img src="' + esc(thumb) + '" alt="">' : '<div style="height:72px;display:flex;align-items:center;justify-content:center">◇</div>') +
              '<span>' + esc(item.title || item.type || 'Item') + '</span></button>';
          }).join('') + '</div>';
          body.querySelectorAll('[data-gid]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              Core.referenceAssets.fromGallery({
                gallery_id: btn.getAttribute('data-gid'),
                studio: studio,
                project_id: state.projectId
              }).then(function (res) {
                addAsset((res.data && res.data.asset) || res.asset);
                closePicker();
              }).catch(function (e) { alert(e.message); });
            });
          });
        });
        return;
      }

      if (kind === 'import' && Core.importEngine) {
        Core.importEngine.history(40).then(function (res) {
          var items = (res.data && res.data.history) || [];
          if (!items.length) {
            body.innerHTML = '<p class="yra-empty">Import Library가 비어 있습니다.</p>';
            return;
          }
          body.innerHTML = '<div class="yra-picker-grid">' + items.map(function (item) {
            return '<button type="button" class="yra-picker-card" data-iid="' + esc(item.id) + '">' +
              '<div style="height:72px;display:flex;align-items:center;justify-content:center">' + esc(item.type || 'file') + '</div>' +
              '<span>' + esc(item.filename || item.title || item.id) + '</span></button>';
          }).join('') + '</div>';
          body.querySelectorAll('[data-iid]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              Core.referenceAssets.fromImport(btn.getAttribute('data-iid'), {
                studio: studio,
                project_id: state.projectId
              }).then(function (res) {
                addAsset((res.data && res.data.asset) || res.asset);
                closePicker();
              }).catch(function (e) { alert(e.message); });
            });
          });
        });
        return;
      }

      if (kind === 'project' && Core.projects) {
        var loadProject = state.projectId
          ? Core.projects.get(state.projectId)
          : Core.projects.list().then(function (res) {
            var list = (res.data && res.data.projects) || [];
            if (!list.length) throw new Error('프로젝트가 없습니다.');
            state.projectId = list[0].id;
            return Core.projects.get(state.projectId);
          });
        Promise.resolve(loadProject).then(function (res) {
          var project = (res.data && res.data.project) || res.project || res.data || {};
          var assets = [].concat(project.assets || [], project.reference_assets || []);
          if (!assets.length) {
            body.innerHTML = '<p class="yra-empty">프로젝트 에셋이 없습니다.</p>';
            return;
          }
          body.innerHTML = '<div class="yra-picker-grid">' + assets.map(function (item) {
            var thumb = item.thumbnail || item.url || '';
            return '<button type="button" class="yra-picker-card" data-aid="' + esc(item.id) + '">' +
              (thumb && thumb.indexOf('http') === 0 ? '<img src="' + esc(thumb) + '" alt="">' : '<div style="height:72px;display:flex;align-items:center;justify-content:center">◇</div>') +
              '<span>' + esc(item.title || item.type || 'Asset') + '</span></button>';
          }).join('') + '</div>';
          body.querySelectorAll('[data-aid]').forEach(function (btn) {
            btn.addEventListener('click', function () {
              Core.referenceAssets.fromProject({
                project_id: state.projectId,
                asset_id: btn.getAttribute('data-aid'),
                studio: studio
              }).then(function (res) {
                addAsset((res.data && res.data.asset) || res.asset);
                closePicker();
              }).catch(function (e) { alert(e.message); });
            });
          });
        }).catch(function (e) {
          body.innerHTML = '<p class="yra-empty">' + esc(e.message || '프로젝트를 불러올 수 없습니다.') + '</p>';
        });
      }
    }

    function closePicker() {
      var p = document.querySelector('.yra-picker');
      if (p && p.parentNode) p.parentNode.removeChild(p);
    }

    function render() {
      root.innerHTML =
        '<div class="yra-head" data-yra-toggle>' +
          '<h3>REFERENCE ASSETS</h3>' +
          '<span class="yra-count">' + state.assets.length + '/' + profile.max + '</span>' +
        '</div>' +
        '<div class="yra-body">' +
          '<div class="yra-dropzone" data-yra-drop>' +
            (state.uploading ? '업로드 중…' : '드래그 앤 드롭 또는 클릭하여 업로드') +
            (state.uploading ? '<div class="yra-progress"><div class="yra-progress-bar" style="width:' + state.progress + '%"></div></div>' : '') +
          '</div>' +
          '<input type="file" data-yra-file hidden multiple accept="' + esc(profile.accept) + '">' +
          '<div class="yra-sources">' +
            '<button type="button" class="yra-src-btn" data-yra-src="gallery">Gallery</button>' +
            '<button type="button" class="yra-src-btn" data-yra-src="import">Import Library</button>' +
            '<button type="button" class="yra-src-btn" data-yra-src="project">Current Project</button>' +
          '</div>' +
          '<div class="yra-list">' +
            (state.assets.length ? state.assets.map(itemHtml).join('') : '<p class="yra-empty">참조 에셋이 없습니다.</p>') +
          '</div>' +
        '</div>';

      root.querySelector('[data-yra-toggle]').addEventListener('click', function () {
        state.collapsed = !state.collapsed;
        root.classList.toggle('is-collapsed', state.collapsed);
      });

      var drop = root.querySelector('[data-yra-drop]');
      var fileInput = root.querySelector('[data-yra-file]');
      drop.addEventListener('click', function () { fileInput.click(); });
      fileInput.addEventListener('change', function () {
        Array.prototype.forEach.call(fileInput.files || [], uploadFile);
        fileInput.value = '';
      });
      drop.addEventListener('dragover', function (e) {
        e.preventDefault();
        drop.classList.add('is-dragover');
      });
      drop.addEventListener('dragleave', function () { drop.classList.remove('is-dragover'); });
      drop.addEventListener('drop', function (e) {
        e.preventDefault();
        drop.classList.remove('is-dragover');
        Array.prototype.forEach.call(e.dataTransfer.files || [], uploadFile);
      });

      root.querySelectorAll('[data-yra-src]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          openPicker(btn.getAttribute('data-yra-src'));
        });
      });

      root.querySelectorAll('[data-yra-remove]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          removeAsset(btn.getAttribute('data-yra-remove'));
        });
      });

      root.querySelectorAll('[data-yra-rename]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-yra-rename');
          var asset = state.assets.find(function (a) { return a.id === id; });
          var title = global.prompt('에셋 이름', asset ? asset.title : '');
          if (!title) return;
          Core.referenceAssets.update(id, { title: title }).then(function (res) {
            var updated = (res.data && res.data.asset) || res.asset;
            state.assets = state.assets.map(function (a) {
              return a.id === id ? Object.assign({}, a, updated) : a;
            });
            render();
            emit();
          });
        });
      });
    }

    function itemHtml(asset) {
      return '<article class="yra-item">' +
        '<div class="yra-thumb">' + thumbHtml(asset) + '</div>' +
        '<div class="yra-meta"><strong>' + esc(asset.title || 'Reference') + '</strong>' +
          '<small>' + badge(asset.role || asset.asset_type) + esc(asset.source || 'upload') + '</small></div>' +
        '<div class="yra-actions">' +
          '<button type="button" data-yra-rename="' + esc(asset.id) + '">이름</button>' +
          '<button type="button" data-yra-remove="' + esc(asset.id) + '">삭제</button>' +
        '</div></article>';
    }

    render();

    try {
      var pending = global.sessionStorage.getItem('yoy_reference_asset');
      if (pending) {
        var parsed = JSON.parse(pending);
        global.sessionStorage.removeItem('yoy_reference_asset');
        if (parsed && parsed.url) {
          addAsset(parsed);
        }
      }
    } catch (e) { /* ignore */ }

    return {
      element: root,
      getAssets: function () { return state.assets.slice(); },
      setAssets: setAssets,
      setProjectId: function (id) { state.projectId = id || ''; },
      refresh: function () {
        return Core.referenceAssets.list({ studio: studio, project_id: state.projectId }).then(function (res) {
          setAssets((res.data && res.data.assets) || []);
        });
      },
      destroy: function () {
        closePicker();
        if (root.parentNode) root.parentNode.removeChild(root);
      }
    };
  }

  function mount(container, options) {
    var panel = create(options);
    if (container) container.appendChild(panel.element);
    return panel;
  }

  function applyToSettings(settings, assets) {
    settings = settings || {};
    settings.reference_assets = assets || [];
    if (assets && assets[0]) {
      settings.reference_url = assets[0].url || '';
    }
    return settings;
  }

  global.YooYReferenceAssetsPanel = {
    create: create,
    mount: mount,
    applyToSettings: applyToSettings,
    profiles: STUDIO_PROFILES
  };
})(window);
