(function (global) {
  'use strict';

  var Core = global.YooYCore;
  if (!Core) return;

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function cfg() {
    var c = Core.config || {};
    return {
      restUrl: (c.restUrl || '').replace(/\/$/, ''),
      nonce: c.nonce || (global.wpApiSettings && global.wpApiSettings.nonce) || ''
    };
  }

  function statusPill(status) {
    var s = String(status || 'queued').toLowerCase();
    var cls = 'yie-pill--wait';
    if (s === 'completed') cls = 'yie-pill--ok';
    if (s === 'failed') cls = 'yie-pill--fail';
    return '<span class="yie-pill ' + cls + '">' + esc(s) + '</span>';
  }

  function renderRow(item) {
    return '<div class="yie-row">' +
      '<div><strong>' + esc(item.filename || item.title || '—') + '</strong>' +
      '<small>' + esc((item.type || '') + ' · ' + (item.source || '')) + '</small></div>' +
      statusPill(item.status) +
      '</div>';
  }

  function mount(root) {
    if (!root || root.dataset.mounted === '1') return;
    root.dataset.mounted = '1';

    root.innerHTML =
      '<div class="yie-wrap">' +
        '<header class="yie-head"><h1>Import</h1>' +
        '<p>외부 에셋을 YooY Gallery Store에 등록합니다. 생성물과 동일한 Store·Gallery·Projects 파이프라인을 사용합니다.</p></header>' +
        '<div class="yie-grid">' +
          '<section class="yie-card">' +
            '<h2>Drag & Drop / Upload</h2>' +
            '<div class="yie-drop" id="yie-drop">' +
              '<strong>파일을 여기에 놓으세요</strong>' +
              '<span>Image · Video · Music · Voice · PDF · DOCX · TXT</span>' +
            '</div>' +
            '<div class="yie-actions">' +
              '<button type="button" class="yie-btn yie-btn--gold" id="yie-pick">Upload Files</button>' +
              '<button type="button" class="yie-btn" id="yie-folder">Import Folder</button>' +
            '</div>' +
            '<input type="file" id="yie-file" class="yie-hidden-input" multiple accept=".png,.jpg,.jpeg,.webp,.svg,.mp4,.mov,.avi,.mkv,.webm,.mp3,.wav,.flac,.aac,.pdf,.docx,.txt">' +
            '<input type="file" id="yie-folder-input" class="yie-hidden-input" multiple webkitdirectory directory>' +
            '<div class="yie-options">' +
              '<label>Origin<select id="yie-origin"><option value="Imported">Imported</option><option value="External">External</option><option value="Folder">Folder</option></select></label>' +
              '<label>Project ID (optional)<input id="yie-project" placeholder="proj_..."></label>' +
              '<label>New Project Title (optional)<input id="yie-new-project" placeholder="My Imported Project"></label>' +
            '</div>' +
            '<div class="yie-msg" id="yie-msg"></div>' +
          '</section>' +
          '<section class="yie-card">' +
            '<h2>Import Queue</h2><div class="yie-list" id="yie-queue"><em style="color:#666">No queued imports.</em></div>' +
          '</section>' +
          '<section class="yie-card" style="grid-column:1/-1">' +
            '<h2>Import History</h2><div class="yie-list" id="yie-history"><em style="color:#666">Loading…</em></div>' +
          '</section>' +
        '</div>' +
      '</div>';

    var drop = root.querySelector('#yie-drop');
    var fileInput = root.querySelector('#yie-file');
    var folderInput = root.querySelector('#yie-folder-input');
    var msg = root.querySelector('#yie-msg');

    function setMsg(text, kind) {
      msg.textContent = text || '';
      msg.className = 'yie-msg' + (kind ? ' yie-msg--' + kind : '');
    }

    function refresh() {
      Core.importEngine.queue().then(function (res) {
        var list = (res.data && res.data.queue) || [];
        var el = root.querySelector('#yie-queue');
        el.innerHTML = list.length ? list.map(renderRow).join('') : '<em style="color:#666">Queue is empty.</em>';
      });
      Core.importEngine.history().then(function (res) {
        var list = (res.data && res.data.history) || [];
        var el = root.querySelector('#yie-history');
        el.innerHTML = list.length ? list.map(renderRow).join('') : '<em style="color:#666">No imports yet.</em>';
      }).catch(function () {
        root.querySelector('#yie-history').innerHTML = '<em style="color:#666">No imports yet.</em>';
      });
    }

    function optionsPayload() {
      return {
        origin: root.querySelector('#yie-origin').value,
        project_id: root.querySelector('#yie-project').value.trim(),
        new_project_title: root.querySelector('#yie-new-project').value.trim(),
        source: 'upload'
      };
    }

    function uploadFiles(files, source) {
      if (!files || !files.length) return;
      setMsg('Importing ' + files.length + ' file(s)…');
      var opts = optionsPayload();
      opts.source = source || 'upload';
      if (source === 'folder') opts.origin = 'Folder';

      Core.importEngine.uploadFiles(files, opts).then(function (res) {
        var results = (res.data && res.data.results) || [];
        var ok = results.filter(function (r) { return r.status === 'completed'; }).length;
        setMsg('Imported ' + ok + ' / ' + files.length + ' asset(s).', 'ok');
        refresh();
        if (global.YooYCore && ok > 0) {
          try { global.dispatchEvent(new CustomEvent('yooy:import-complete')); } catch (e) {}
        }
      }).catch(function (err) {
        setMsg(err.message || 'Import failed.', 'error');
        refresh();
      });
    }

    root.querySelector('#yie-pick').addEventListener('click', function () { fileInput.click(); });
    root.querySelector('#yie-folder').addEventListener('click', function () { folderInput.click(); });

    fileInput.addEventListener('change', function () {
      uploadFiles(fileInput.files, 'upload');
      fileInput.value = '';
    });
    folderInput.addEventListener('change', function () {
      uploadFiles(folderInput.files, 'folder');
      folderInput.value = '';
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) {
        e.preventDefault();
        drop.classList.add('is-drag');
      });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
      drop.addEventListener(ev, function (e) {
        e.preventDefault();
        drop.classList.remove('is-drag');
      });
    });
    drop.addEventListener('drop', function (e) {
      var dt = e.dataTransfer;
      if (dt && dt.files && dt.files.length) uploadFiles(dt.files, 'drag');
    });
    drop.addEventListener('click', function () { fileInput.click(); });

    refresh();
  }

  global.YooYImportEngine = { mount: mount };
})(window);
