(function (global) {
  'use strict';

  var Core = global.YooYCore || null;
  var config = (Core && Core.config) || global.YooYStudio || {};

  var STATUS = {
    ok:    { dot: '\uD83D\uDFE2', label: '정상', tone: 'ok' },
    warn:  { dot: '\uD83D\uDFE1', label: '경고', tone: 'warn' },
    error: { dot: '\uD83D\uDD34', label: '오류', tone: 'error' }
  };

  function statusMeta(s) { return STATUS[s] || STATUS.error; }

  function esc(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  // Prioritised root-cause analysis per error code. NEVER blames the provider,
  // OpenAI or billing for an infrastructure/route problem.
  var CAUSES = {
    rest_no_route: {
      title: 'REST API Route를 찾을 수 없습니다',
      note: '이 오류는 OpenAI/공급업체/크레딧 문제가 아닙니다. WordPress REST 라우트 문제입니다.',
      causes: [
        { priority: 1, text: '플러그인 재활성화가 필요합니다.', fix: { action: 'flush_rewrite_rules', label: 'Fix — rewrite 재생성' } },
        { priority: 2, text: '퍼머링크 저장이 필요합니다 (설정 → 고유주소 → 변경사항 저장).', fix: { action: 'flush_rewrite_rules', label: 'Fix — rewrite 재생성' } },
        { priority: 3, text: 'REST 라우트 등록 실패 (모듈 로드 오류 가능).' },
        { priority: 4, text: '브라우저/CDN에 구버전 JS가 캐시됨 (강력 새로고침 Ctrl+Shift+R).' }
      ]
    },
    insufficient_provider_credit: {
      title: 'Provider API 크레딧 부족',
      note: 'Provider(예: Replicate) API 계정의 크레딧 문제이며 YooY 사용자 크레딧과는 별도입니다.',
      causes: [
        { priority: 1, text: 'Provider API 계정에 결제/크레딧을 충전하세요.' },
        { priority: 2, text: 'Auto 라우팅에서 해당 Provider를 제외하고 OpenAI를 사용하세요.', fix: { action: 'open_providers', label: 'Provider 설정 열기' } }
      ]
    },
    provider_not_tested: {
      title: 'Provider Test Connection 필요',
      note: '실 Provider는 사용 전 Test Connection을 통과해야 합니다.',
      causes: [
        { priority: 1, text: 'Operations Center에서 Test Connection을 실행하세요.', fix: { action: 'open_providers', label: 'Provider 설정 열기' } }
      ]
    },
    provider_not_configured: {
      title: 'Provider 미설정',
      note: 'API 키가 등록되지 않았습니다.',
      causes: [
        { priority: 1, text: 'Operations Center에서 API 키를 등록하세요.', fix: { action: 'open_providers', label: 'Provider 설정 열기' } }
      ]
    }
  };

  function analyzeError(err) {
    if (!err) return null;
    var code = err.code || (err.details && err.details.code) || '';
    var entry = CAUSES[code];
    if (!entry) return null;
    var details = err.details || {};
    return {
      code: code,
      title: entry.title,
      note: entry.note,
      causes: entry.causes.slice().sort(function (a, b) { return a.priority - b.priority; }),
      endpoint: details.endpoint || '',
      method: details.method || '',
      tried_wp_json: details.tried_wp_json || '',
      tried_rest_route: details.tried_rest_route || '',
      registered_similar: details.registered_similar || []
    };
  }

  // ---- Diagnostic report (JSON / TXT / Markdown) --------------------------
  function collect(extra) {
    var health = (Core && global.YooYSystemHealth) || null;
    return {
      generated_at: new Date().toISOString(),
      site: (config && config.site) || global.location.origin,
      plugin_version: (config && config.version) || '',
      asset_version: (config && config.version) || '',
      user_agent: global.navigator ? global.navigator.userAgent : '',
      rest_mode: (function () { try { return global.sessionStorage.getItem('yoyRestMode') || 'auto'; } catch (e) { return 'auto'; } })(),
      rest_health: global.YooYRestHealth || null,
      system_health: health,
      last_error: extra && extra.error ? errorToPlain(extra.error) : null,
      context: (extra && extra.context) || null
    };
  }

  function errorToPlain(err) {
    if (!err) return null;
    return {
      message: err.message || String(err),
      code: err.code || (err.details && err.details.code) || '',
      details: err.details || null
    };
  }

  function toText(report) {
    var lines = [];
    lines.push('YooY AI Studio — Diagnostic Report');
    lines.push('Generated: ' + report.generated_at);
    lines.push('Site: ' + report.site);
    lines.push('Plugin version: ' + report.plugin_version);
    lines.push('REST mode: ' + report.rest_mode);
    lines.push('');
    if (report.system_health && report.system_health.checks) {
      lines.push('System Check — overall: ' + report.system_health.overall);
      report.system_health.checks.forEach(function (c) {
        lines.push('  [' + c.status.toUpperCase() + '] ' + c.label + ' — ' + c.message);
      });
      lines.push('');
    }
    if (report.last_error) {
      lines.push('Last Error:');
      lines.push('  code: ' + report.last_error.code);
      lines.push('  message: ' + report.last_error.message);
      if (report.last_error.details) {
        lines.push('  details: ' + JSON.stringify(report.last_error.details));
      }
    }
    return lines.join('\n');
  }

  function toMarkdown(report) {
    var md = [];
    md.push('# YooY AI Studio — Diagnostic Report');
    md.push('');
    md.push('- **Generated:** ' + report.generated_at);
    md.push('- **Site:** ' + report.site);
    md.push('- **Plugin version:** ' + report.plugin_version);
    md.push('- **REST mode:** ' + report.rest_mode);
    md.push('');
    if (report.system_health && report.system_health.checks) {
      md.push('## System Check (overall: ' + report.system_health.overall + ')');
      md.push('');
      md.push('| Check | Status | Message |');
      md.push('| --- | --- | --- |');
      report.system_health.checks.forEach(function (c) {
        md.push('| ' + c.label + ' | ' + c.status + ' | ' + String(c.message).replace(/\|/g, '\\|') + ' |');
      });
      md.push('');
    }
    if (report.last_error) {
      md.push('## Last Error');
      md.push('');
      md.push('```json');
      md.push(JSON.stringify(report.last_error, null, 2));
      md.push('```');
    }
    return md.join('\n');
  }

  function download(format, extra) {
    var report = collect(extra);
    var content;
    var mime;
    var ext;
    if (format === 'txt') { content = toText(report); mime = 'text/plain'; ext = 'txt'; }
    else if (format === 'md') { content = toMarkdown(report); mime = 'text/markdown'; ext = 'md'; }
    else { content = JSON.stringify(report, null, 2); mime = 'application/json'; ext = 'json'; }
    var blob = new global.Blob([content], { type: mime + ';charset=utf-8' });
    var url = global.URL.createObjectURL(blob);
    var a = global.document.createElement('a');
    a.href = url;
    a.download = 'yooy-diagnostic-' + new Date().toISOString().replace(/[:.]/g, '-') + '.' + ext;
    global.document.body.appendChild(a);
    a.click();
    global.document.body.removeChild(a);
    global.setTimeout(function () { global.URL.revokeObjectURL(url); }, 2000);
    return report;
  }

  // ---- Run / cache --------------------------------------------------------
  var last = null;
  var running = null;

  function run(force) {
    if (!Core || !Core.systemCheck) {
      return global.Promise.reject(new Error('System check unavailable'));
    }
    if (!force && last) return global.Promise.resolve(last);
    if (running) return running;
    running = Core.systemCheck().then(function (res) {
      last = (res && (res.data || res)) || null;
      running = null;
      renderWidget();
      global.document.dispatchEvent(new global.CustomEvent('yoy:system:checked', { detail: last }));
      return last;
    }).catch(function (err) {
      running = null;
      throw err;
    });
    return running;
  }

  function fix(action) {
    if (action === 'open_providers' || action === 'open_credits') {
      // UI navigation fixes handled by the caller / router.
      if (global.YooYAdminConsole && action === 'open_providers') {
        try { global.YooYAdminConsole.openOps('providers'); } catch (e) {}
      }
      return global.Promise.resolve({ success: true, requires: 'navigate', action: action });
    }
    if (!Core || !Core.systemFix) {
      return global.Promise.reject(new Error('Fix unavailable'));
    }
    return Core.systemFix(action).then(function (res) {
      var data = (res && (res.data || res)) || {};
      if (data.requires === 'reload') {
        global.setTimeout(function () { global.location.reload(); }, 900);
      } else {
        run(true);
      }
      return data;
    });
  }

  // ---- Top-right always-visible status widget -----------------------------
  function overallMeta(report) {
    if (!report) return { dot: '\u26AA', label: 'Checking…', tone: 'idle' };
    var m = statusMeta(report.overall);
    var text = report.overall === 'ok' ? 'System Ready' : (report.overall === 'warn' ? 'System Warning' : 'System Error');
    return { dot: m.dot, label: text, tone: m.tone };
  }

  function widgetPanelHtml(report) {
    if (!report || !report.checks) {
      return '<div class="yoy-sys-panel-empty">진단 실행 중…</div>';
    }
    var rows = report.checks.map(function (c) {
      var m = statusMeta(c.status);
      var fixBtn = c.fixable
        ? '<button type="button" class="yoy-sys-fix" data-yoy-fix="' + esc(c.fix_action) + '">Fix →</button>'
        : '';
      return '<div class="yoy-sys-row yoy-sys-row--' + m.tone + '">' +
        '<span class="yoy-sys-row-dot">' + m.dot + '</span>' +
        '<span class="yoy-sys-row-label">' + esc(c.label) + '</span>' +
        '<span class="yoy-sys-row-msg">' + esc(c.message) + '</span>' +
        fixBtn +
        '</div>';
    }).join('');
    var footer = '<div class="yoy-sys-panel-foot">' +
      '<span>' + (report.overall === 'ok' ? 'Everything Ready' : (report.overall === 'warn' ? '주의가 필요합니다' : '문제를 해결하세요')) + '</span>' +
      '<span class="yoy-sys-report">' +
        '<button type="button" data-yoy-report="json">JSON</button>' +
        '<button type="button" data-yoy-report="txt">TXT</button>' +
        '<button type="button" data-yoy-report="md">MD</button>' +
        '<button type="button" data-yoy-recheck>재검사</button>' +
      '</span></div>';
    return rows + footer;
  }

  function renderWidget() {
    var doc = global.document;
    if (!doc) return;
    var host = doc.getElementById('yai-topbar-actions');
    if (!host) return;
    var btn = doc.getElementById('yoy-sys-status');
    if (!btn) {
      var wrap = doc.createElement('div');
      wrap.className = 'yoy-sys-widget';
      wrap.innerHTML =
        '<button type="button" id="yoy-sys-status" class="yoy-sys-status" aria-expanded="false">' +
          '<span class="yoy-sys-status-dot"></span>' +
          '<span class="yoy-sys-status-text">Checking…</span>' +
        '</button>' +
        '<div class="yoy-sys-panel" id="yoy-sys-panel" hidden></div>';
      host.insertBefore(wrap, host.firstChild);
      btn = doc.getElementById('yoy-sys-status');
      bindWidget(wrap);
    }
    var meta = overallMeta(last);
    var dot = btn.querySelector('.yoy-sys-status-dot');
    var text = btn.querySelector('.yoy-sys-status-text');
    btn.className = 'yoy-sys-status yoy-sys-status--' + meta.tone;
    if (dot) dot.textContent = meta.dot;
    if (text) text.textContent = meta.label;
    var panel = doc.getElementById('yoy-sys-panel');
    if (panel && !panel.hidden) {
      panel.innerHTML = widgetPanelHtml(last);
    }
  }

  function bindWidget(wrap) {
    var doc = global.document;
    wrap.addEventListener('click', function (e) {
      var toggle = e.target.closest('#yoy-sys-status');
      if (toggle) {
        var panel = doc.getElementById('yoy-sys-panel');
        if (panel) {
          var show = panel.hidden;
          panel.hidden = !show;
          toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
          if (show) { panel.innerHTML = widgetPanelHtml(last); }
        }
        return;
      }
      var fixBtn = e.target.closest('[data-yoy-fix]');
      if (fixBtn) {
        fixBtn.disabled = true;
        fixBtn.textContent = '수정 중…';
        fix(fixBtn.getAttribute('data-yoy-fix')).catch(function () {
          fixBtn.disabled = false;
          fixBtn.textContent = 'Fix →';
        });
        return;
      }
      var rep = e.target.closest('[data-yoy-report]');
      if (rep) { download(rep.getAttribute('data-yoy-report')); return; }
      if (e.target.closest('[data-yoy-recheck]')) { run(true); return; }
    });
    doc.addEventListener('click', function (e) {
      if (wrap.contains(e.target)) return;
      var panel = doc.getElementById('yoy-sys-panel');
      var toggle = doc.getElementById('yoy-sys-status');
      if (panel && !panel.hidden) {
        panel.hidden = true;
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  var YooYDiagnostics = {
    STATUS: STATUS,
    statusMeta: statusMeta,
    run: run,
    fix: fix,
    analyzeError: analyzeError,
    report: download,
    collect: collect,
    get last() { return last; }
  };

  global.YooYDiagnostics = YooYDiagnostics;

  // Auto-run once the shell is ready (logged-in Creator OS only).
  function boot() {
    if (!config || !config.loggedIn) return;
    renderWidget();
    run(false).catch(function () { renderWidget(); });
  }

  if (global.document && global.document.readyState !== 'loading') {
    boot();
  } else if (global.document) {
    global.document.addEventListener('DOMContentLoaded', boot);
  }
})(window);
