/**
 * YooY Runtime Contract — admin/dev boot checks for canonical globals.
 * Never invents alternate globals; only verifies / soft-aliases existing SoT objects.
 * Developer diagnostics: console only for admin/debug. Users see Korean fallback copy.
 */
(function (global) {
  'use strict';

  var USER_FALLBACK = '일부 기능을 불러오지 못했습니다. 잠시 후 새로고침해 주세요.';
  var lastReport = null;
  var lastToastKey = '';
  var lastToastAt = 0;

  function cfg() {
    var core = global.YooYCore;
    return (core && core.config) || global.YooYStudio || {};
  }

  function isDevAudience() {
    var c = cfg();
    if (c.isAdmin || c.debug) return true;
    if (global.YOOY_DEBUG) return true;
    try {
      return !!(global.YooYCore && typeof global.YooYCore.debug === 'function' && global.YooYCore.debug());
    } catch (e) {
      return false;
    }
  }

  function pathOk(root, path) {
    if (!root) return false;
    var parts = String(path || '').split('.');
    var cur = root;
    for (var i = 0; i < parts.length; i++) {
      if (!cur || (typeof cur !== 'object' && typeof cur !== 'function')) return false;
      cur = cur[parts[i]];
    }
    return cur != null;
  }

  function resolve(path) {
    var parts = String(path || '').split('.');
    var cur = global;
    for (var i = 0; i < parts.length; i++) {
      if (!cur) return null;
      cur = cur[parts[i]];
    }
    return cur;
  }

  /** Soft-heal: alias only, never a second Projects client. */
  function healAliases() {
    var core = global.YooYCore;
    if (core && core.projects && !global.YooYProjectsAPI) {
      try { global.YooYProjectsAPI = core.projects; } catch (e) { /* ignore */ }
    }
    if (global.YooYProjectsAPI && core && !core.projects) {
      try { core.projects = global.YooYProjectsAPI; } catch (e2) { /* ignore */ }
    }
  }

  function projectsApiOk() {
    return !!(global.YooYProjectsAPI && typeof global.YooYProjectsAPI.getCanvas === 'function')
      || !!(global.YooYCore && global.YooYCore.projects && typeof global.YooYCore.projects.getCanvas === 'function');
  }

  function projectsListOk() {
    return !!(global.YooYProjectsAPI && typeof global.YooYProjectsAPI.list === 'function')
      || !!(global.YooYCore && global.YooYCore.projects && typeof global.YooYCore.projects.list === 'function');
  }

  /**
   * Boot-critical contracts (every Studio shell page).
   * @return {{ok:boolean, missing:string[], checks:Object}}
   */
  function verifyBoot() {
    healAliases();
    var checks = {
      YooYCore: !!global.YooYCore,
      'YooYCore.projects': !!(global.YooYCore && global.YooYCore.projects),
      YooYProjectsAPI: projectsApiOk(),
      YooYOriginalImageViewer: !!(global.YooYOriginalImageViewer && typeof global.YooYOriginalImageViewer.open === 'function'),
      YooYStudio: !!global.YooYStudio
    };
    var missing = [];
    Object.keys(checks).forEach(function (k) {
      if (!checks[k]) missing.push(k);
    });
    if (missing.indexOf('YooYProjectsAPI') !== -1 && checks['YooYCore.projects']) {
      missing = missing.filter(function (m) { return m !== 'YooYProjectsAPI'; });
      checks.YooYProjectsAPI = true;
      healAliases();
    }
    var report = {
      ok: missing.length === 0,
      scope: 'boot',
      missing: missing,
      checks: checks,
      at: new Date().toISOString()
    };
    lastReport = report;
    publish(report);
    return report;
  }

  /**
   * Page-scoped extras — only what that route needs.
   * @param {string} page route id
   * @return {{ok:boolean, missing:string[], checks:Object, page:string}}
   */
  function verifyPage(page) {
    healAliases();
    var id = String(page || '');
    var required = [];

    if (id === 'project-detail' || id === 'projects') {
      required.push('YooYCore.projects');
      required.push('YooYProjectsAPI');
    }
    if (id === 'project-detail') {
      required.push('YooYCreativeCanvas');
    }
    if (id === 'works' || id === 'history' || id === 'image' || id === 'project-detail') {
      required.push('YooYOriginalImageViewer');
    }
    if (id === 'works' || id === 'history') {
      required.push('YooYGallery');
    }
    if (id === 'image') required.push('YooYImageStudio');
    if (id === 'video') required.push('YooYVideoStudio');
    if (id === 'music') required.push('YooYMusicStudio');
    if (id === 'voice') required.push('YooYVoiceStudio');
    if (id === 'avatar') required.push('YooYAvatarStudio');
    if (id === 'translator') required.push('YooYTranslatorStudio');
    if (id === 'assistant') required.push('YooYAIAssistant');

    var checks = {};
    var missing = [];
    required.forEach(function (name) {
      var ok = false;
      if (name === 'YooYProjectsAPI') {
        ok = projectsListOk() || projectsApiOk();
      } else if (name.indexOf('.') !== -1) {
        ok = pathOk(global, name);
      } else {
        var g = resolve(name);
        ok = !!g;
        if (ok && name === 'YooYCreativeCanvas') {
          ok = typeof g.mount === 'function';
        }
        if (ok && name === 'YooYOriginalImageViewer') {
          ok = typeof g.open === 'function';
        }
        if (ok && name === 'YooYGallery') {
          ok = typeof g.mount === 'function' || typeof g.reload === 'function' || typeof g.openPublish === 'function';
        }
        if (ok && /Studio$|Assistant$/.test(name)) {
          ok = typeof g.mount === 'function';
        }
      }
      checks[name] = ok;
      if (!ok) missing.push(name);
    });

    var report = {
      ok: missing.length === 0,
      scope: 'page:' + id,
      page: id,
      missing: missing,
      checks: checks,
      at: new Date().toISOString()
    };
    lastReport = report;
    publish(report);
    return report;
  }

  function publish(report) {
    if (!report || report.ok) return;

    if (isDevAudience() && global.console && console.error) {
      console.error('[YooYRuntimeContract] missing required globals', {
        scope: report.scope,
        page: report.page || null,
        missing: report.missing,
        checks: report.checks,
        hint: 'Check wp_enqueue deps / script load order. Canonical: YooYCore, YooYCore.projects, YooYProjectsAPI alias, YooYOriginalImageViewer.'
      });
    }

    var key = (report.scope || '') + '|' + (report.missing || []).join(',');
    var now = Date.now();
    if (key === lastToastKey && (now - lastToastAt) < 8000) return;
    lastToastKey = key;
    lastToastAt = now;

    try {
      if (typeof global.showToast === 'function') {
        global.showToast(USER_FALLBACK, true);
      } else if (typeof global.YooYStudioToast === 'function') {
        global.YooYStudioToast(USER_FALLBACK, true);
      }
    } catch (e) { /* ignore */ }
  }

  function userFallbackHtml(title) {
    return '<div class="yai-empty yai-empty--contract">' +
      '<h3>' + String(title || '화면을 불러오지 못했습니다') + '</h3>' +
      '<p class="yai-muted">' + USER_FALLBACK + '</p>' +
      '<button type="button" class="yai-btn yai-btn--gold" data-yoy-contract-reload>새로고침</button>' +
    '</div>';
  }

  function bindReload(root) {
    if (!root || !root.querySelector) return;
    var btn = root.querySelector('[data-yoy-contract-reload]');
    if (btn && !btn.__yoyBound) {
      btn.__yoyBound = true;
      btn.addEventListener('click', function () {
        try { global.location.reload(); } catch (e) { /* ignore */ }
      });
    }
  }

  function renderFallback(el, title) {
    if (!el) return;
    el.innerHTML = userFallbackHtml(title);
    bindReload(el);
  }

  function getLastReport() {
    return lastReport;
  }

  global.YooYRuntimeContract = {
    verifyBoot: verifyBoot,
    verifyPage: verifyPage,
    healAliases: healAliases,
    userFallbackHtml: userFallbackHtml,
    bindReload: bindReload,
    renderFallback: renderFallback,
    userMessage: USER_FALLBACK,
    getLastReport: getLastReport,
    isDevAudience: isDevAudience
  };
})(window);
