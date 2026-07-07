(function (global) {
  'use strict';

  var deps = {};
  var providerView = 'providers';
  var providerFilter = 'all';
  var providerStudio = '';
  var providerSearch = '';
  var providersCache = [];
  var providersSummary = {};
  var mockCollapsed = true;
  var logsDrawerId = null;
  var helpPopover = null;

  var HELP_TEXTS = {
    mode: 'Auto는 키·테스트 상태에 따라 자동 선택, Live는 실제 API를 사용, Mock은 샌드박스 미리보기만 사용합니다. 처음에는 Auto를 권장합니다.',
    priority: '여러 AI 제공업체를 사용할 수 있을 때 우선적으로 선택되는 순서입니다. 숫자가 높을수록 먼저 사용됩니다.',
    enabled: '비활성화하면 생성 요청에서 이 제공업체가 사용되지 않습니다. 일시 중지할 때 끄세요.',
    billing: '제공업체 결제·크레딧 상태입니다. 차단(Blocked)이면 Live 생성이 제한될 수 있습니다.',
    api_key: 'AI 제공업체에서 발급받은 인증 키입니다. 외부에 노출되지 않으며 암호화되어 저장됩니다.',
    studio_default: '선택한 스튜디오의 기본 제공업체로 설정합니다. Auto 대신 이 제공업체가 우선 사용됩니다.',
    test: 'API Key가 정상적으로 동작하는지 확인합니다. 테스트에 성공해야 실제 생성에 사용할 수 있습니다.',
    latency: '마지막 연결 테스트 응답 시간입니다. 네트워크·API 상태를 빠르게 확인할 수 있습니다.',
    success_rate: '최근 작업 기준 성공 비율입니다. 실패가 많으면 로그를 확인하세요.',
    provider_status: '연결·테스트·키 설정 상태를 나타냅니다. Connected는 실제 생성에 사용 가능합니다.',
    routing: 'Auto 라우팅에서 이 제공업체가 실제 생성에 사용되는지 나타냅니다. Connected·Ready면 사용 가능, Needs Test면 연결 테스트가 필요합니다.',
    model: '이 제공업체에서 사용할 기본 Model입니다. 스튜디오별로 다를 수 있습니다.'
  };

  function helpIcon(key) {
    if (!HELP_TEXTS[key]) return '';
    return '<button type="button" class="yai-help-btn" aria-label="도움말" data-help="' + esc(key) + '" tabindex="0">?</button>';
  }

  function labelRow(label, key, controlHtml) {
    return '<label class="yai-ops-field-row"><span class="yai-ops-field-label">' + esc(label) + helpIcon(key) + '</span>' + controlHtml + '</label>';
  }

  function billingLabel(value) {
    var map = { unknown: '알 수 없음', available: '사용 가능', blocked: '차단됨' };
    return map[value] || value;
  }

  function modeLabel(value) {
    if (value === 'real') return 'Live (실제 실행)';
    if (value === 'mock') return 'Mock (모의 실행)';
    if (value === 'disabled') return 'Disabled (비활성화)';
    return 'Auto';
  }

  function testStatusLabel(p) {
    var map = {
      passed: '연결됨',
      failed: '실패',
      unsupported: '미지원',
      not_tested: '테스트 필요'
    };
    return map[p.last_test_status] || map.not_tested;
  }

  function routingLabel(p) {
    var map = {
      used_by_auto: 'Auto 사용',
      ready: '사용 가능',
      needs_test: '테스트 필요',
      test_failed: '테스트 실패',
      test_unsupported: '테스트 미지원',
      disabled: '비활성화',
      mock_mode: 'Mock 모드',
      not_configured: '미설정',
      not_used: '미사용',
      bridge_unimplemented: '구현 필요'
    };
    return p.routing_label_ko || map[p.routing_status] || p.routing_label || '—';
  }

  function studioDefaultsList(p) {
    var studios = p.supports || p.studios || [];
    var defaults = p.studio_defaults || {};
    if (!studios.length) return '—';
    return studios.map(function (st) {
      return st + (defaults[st] ? ' ✓' : '');
    }).join(', ');
  }

  function modelOptionsForProvider(p) {
    var models = p.allowed_models || [];
    if (!models.length && p.model) models = [p.model];
    if (!models.length) models = ['default'];
    return models;
  }

  function esc(s) {
    return deps.esc ? deps.esc(s) : String(s == null ? '' : s);
  }

  function toast(msg, isErr) {
    if (deps.setMsg) deps.setMsg(msg, isErr, 'yai-ops-msg');
  }

  function relTime(ts) {
    if (!ts) return 'Never';
    var d = new Date(ts);
    if (isNaN(d.getTime())) return String(ts);
    var diff = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (diff < 60) return diff + ' sec ago';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
    return Math.floor(diff / 86400) + ' d ago';
  }

  function successLabel(p) {
    if (p.success_rate == null) return '—';
    return p.success_rate + '%';
  }

  function latencyLabel(p) {
    if (p.last_test_ms) return p.last_test_ms + 'ms';
    return '—';
  }

  function matchesFilter(p) {
    if (providerStudio && (p.studios || []).indexOf(providerStudio) === -1) return false;
    if (providerSearch) {
      var q = providerSearch.toLowerCase();
      if ((p.name || '').toLowerCase().indexOf(q) === -1 && (p.id || '').toLowerCase().indexOf(q) === -1) return false;
    }
    var g = p.health_group || 'configured';
    if (providerFilter === 'all') return true;
    if (providerFilter === 'connected') return g === 'connected';
    if (providerFilter === 'not_tested') return g === 'configured' || g === 'api_missing';
    if (providerFilter === 'failed') return g === 'error';
    if (providerFilter === 'unsupported') return g === 'unsupported';
    if (providerFilter === 'disabled') return g === 'disabled';
    if (providerFilter === 'mock') return g === 'mock';
    if (providerFilter === 'image' || providerFilter === 'video' || providerFilter === 'music' || providerFilter === 'voice' || providerFilter === 'writing') {
      return (p.studios || []).indexOf(providerFilter) !== -1;
    }
    return true;
  }

  function filteredProviders() {
    return providersCache.filter(matchesFilter);
  }

  function groupProviders(list) {
    var groups = {
      connected: [],
      configured: [],
      error: [],
      unsupported: [],
      disabled: [],
      mock: []
    };
    list.forEach(function (p) {
      var g = p.health_group || 'configured';
      if (g === 'connected') groups.connected.push(p);
      else if (g === 'error') groups.error.push(p);
      else if (g === 'unsupported') groups.unsupported.push(p);
      else if (g === 'disabled') groups.disabled.push(p);
      else if (g === 'mock') groups.mock.push(p);
      else groups.configured.push(p);
    });
    return groups;
  }

  function badgeHtml(p) {
    var badge = p.status_badge || { label: 'UNKNOWN', tone: 'pending' };
    var labelMap = {
      CONNECTED: '연결됨',
      'NOT TESTED': '테스트 필요',
      FAILED: '실패',
      UNSUPPORTED: '미지원',
      DISABLED: '비활성화',
      MOCK: '모의 실행',
      'API MISSING': 'API 없음',
      'TEST PASSED': '테스트 통과'
    };
    var label = labelMap[badge.label] || badge.label;
    return '<span class="yai-ops-pill yai-ops-pill--' + esc(badge.tone) + '">' + esc(label) + '</span>';
  }

  function providerCardHtml(p) {
    var id = p.id;
    var disabled = !p.enabled;
    var showWarning = p.health_group !== 'connected' && p.error_reason;
    return '<article class="yai-ops-provider yai-ops-provider--' + esc(p.health_group || 'configured') + '" data-pid="' + esc(id) + '" id="yai-ops-provider-' + esc(id) + '">' +
      '<div class="yai-ops-provider-head">' + deps.providerLogo(id) +
      '<div class="yai-ops-provider-meta"><h3>' + esc(p.name) + '</h3><small>' + esc((p.studios || []).join(', ')) + '</small></div>' +
      badgeHtml(p) + '</div>' +
      '<div class="yai-ops-provider-metrics yai-ops-provider-metrics--v2">' +
      '<div class="yai-ops-provider-metric"><b>Provider</b><span>' + esc(p.provider_label || p.name) + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>Model' + helpIcon('model') + '</b><span>' + esc(p.model || 'default') + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>Mode</b><span>' + esc(p.mode_label || modeLabel(p.mode)) + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>Priority' + helpIcon('priority') + '</b><span>' + esc(String(p.priority != null ? p.priority : 50)) + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>API Key' + helpIcon('api_key') + '</b><span>' + (p.has_key ? esc(p.key_masked || '********') : '미설정') + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>테스트 상태' + helpIcon('test') + '</b><span class="yai-ops-last-test">' + esc(testStatusLabel(p)) + ' · ' + esc(p.last_test_relative || relTime(p.last_test_at)) + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>Latency' + helpIcon('latency') + '</b><span class="yai-ops-latency">' + esc(latencyLabel(p)) + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>라우팅' + helpIcon('routing') + '</b><span>' + esc(routingLabel(p)) + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>Studio Default' + helpIcon('studio_default') + '</b><span>' + esc(studioDefaultsList(p)) + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>Billing' + helpIcon('billing') + '</b><span>' + esc(p.billing_label || billingLabel(p.billing_status || 'unknown')) + '</span></div>' +
      '</div>' +
      (showWarning ? '<p class="yai-ops-provider-warning">' + esc(p.error_reason) + '</p>' : '') +
      '<div class="yai-ops-provider-foot">' +
      '<div class="yai-btn-group">' +
      deps.btnSecondary('설정', 'class="yai-ops-config-p" data-id="' + esc(id) + '"') +
      deps.btnPrimary('연결 테스트', 'class="yai-ops-test-p" data-id="' + esc(id) + '"' + (p.has_key ? '' : ' disabled title="API Key를 먼저 저장하세요"')) +
      deps.btnSecondary('로그', 'class="yai-ops-logs-p" data-id="' + esc(id) + '"') +
      (disabled ? deps.btnPrimary('활성화', 'class="yai-ops-enable-p" data-id="' + esc(id) + '"') : deps.btnDanger('비활성화', 'class="yai-ops-disable-p" data-id="' + esc(id) + '"')) +
      '</div></div></article>';
  }

  function sectionBlock(title, emoji, description, list, collapsible, collapsed) {
    if (!list.length) return '';
    var bodyId = 'yai-ops-group-' + title.replace(/\s+/g, '-').toLowerCase();
    var head = '<div class="yai-ops-provider-section-hdr' + (collapsible ? ' is-collapsible' : '') + '"' +
      (collapsible ? ' data-collapse="' + bodyId + '"' : '') + '>' +
      '<div><h3><span>' + emoji + '</span> ' + esc(title) + ' <em>(' + list.length + ')</em></h3>' +
      (description ? '<p class="yai-ops-section-desc">' + esc(description) + '</p>' : '') + '</div>' +
      (collapsible ? '<button type="button" class="yai-ops-collapse-btn" aria-expanded="' + (!collapsed) + '">' + (collapsed ? '펼치기' : '접기') + '</button>' : '') +
      '</div>';
    var grid = '<div class="yai-ops-provider-grid' + (collapsed ? ' is-collapsed' : '') + '" id="' + bodyId + '">' +
      list.map(providerCardHtml).join('') + '</div>';
    return '<section class="yai-ops-provider-section">' + head + grid + '</section>';
  }

  function summaryHtml(summary) {
    summary = summary || {};
    return '<div class="yai-ops-grid yai-ops-grid--7 yai-ops-provider-summary">' +
      deps.kpi('연결됨', summary.connected || 0, '사용 가능', 'yai-ops-kpi--gold') +
      deps.kpi('테스트 필요', summary.need_test || 0, '설정됨') +
      deps.kpi('실패', summary.failed || 0, '오류', 'yai-ops-kpi--fail') +
      deps.kpi('미지원', summary.unsupported || 0, '테스트 미지원', 'yai-ops-kpi--warn') +
      deps.kpi('비활성화', summary.disabled || 0, 'Disabled') +
      deps.kpi('Mock', summary.mock || 0, '샌드박스') +
      deps.kpi('전체', summary.total || 0, '카탈로그') +
      '</div>';
  }

  function filterBarHtml() {
    var filters = [
      ['all', '전체'], ['connected', '연결됨'], ['not_tested', '미테스트'], ['failed', '실패'],
      ['unsupported', '미지원'], ['disabled', '비활성화'], ['mock', 'Mock'],
      ['image', 'Image'], ['video', 'Video'], ['music', 'Music'], ['voice', 'Voice'], ['writing', 'Writing']
    ];
    return '<div class="yai-ops-provider-toolbar">' +
      '<div class="yai-ops-filter-bar">' + filters.map(function (f) {
        return '<button type="button" class="yai-ops-filter-btn' + (providerFilter === f[0] ? ' is-active' : '') + '" data-filter="' + f[0] + '">' + esc(f[1]) + '</button>';
      }).join('') + '</div>' +
      '<input type="search" class="yai-ops-search" id="yai-ops-provider-search" placeholder="제공업체 검색…" value="' + esc(providerSearch) + '">' +
      '</div>';
  }

  function subNavHtml() {
    var tabs = [
      ['providers', 'AI 제공업체'],
      ['monitoring', '모니터링'],
      ['logs', '로그']
    ];
    return '<div class="yai-ops-subnav">' + tabs.map(function (t) {
      return '<button type="button" class="yai-ops-subnav-btn' + (providerView === t[0] ? ' is-active' : '') + '" data-pview="' + t[0] + '">' + esc(t[1]) + '</button>';
    }).join('') + '</div>';
  }

  function providersPanelHtml(list, groups) {
    return sectionBlock('연결됨 · 사용 가능', '🟢', '연결 테스트를 통과하고 실제 생성에 사용할 수 있는 제공업체입니다.', groups.connected, false, false) +
      sectionBlock('설정됨 · 테스트 필요', '🟡', 'API Key가 저장되었지만 연결 테스트가 필요하거나 아직 사용할 수 없습니다.', groups.configured, false, false) +
      sectionBlock('테스트 실패', '🔴', '연결 테스트에 실패했습니다. API Key와 엔드포인트를 확인하세요.', groups.error, false, false) +
      sectionBlock('테스트 미지원', '🟠', 'API Key는 저장되었지만 이 제공업체는 자동 연결 테스트를 지원하지 않습니다.', groups.unsupported, false, false) +
      sectionBlock('비활성화', '⚪', '관리자가 비활성화한 제공업체입니다. Auto 라우팅에서 제외됩니다.', groups.disabled, false, false) +
      sectionBlock('Mock 제공업체', '⚫', '샌드박스 미리보기용 모의 제공업체입니다.', groups.mock, true, mockCollapsed) +
      (list.length ? '' : deps.empty('일치하는 제공업체 없음', '다른 필터나 검색어를 시도해 보세요.'));
  }

  function monitoringPanelHtml(list) {
    if (!list.length) return deps.empty('No providers', 'Adjust filters to see monitoring data.');
    var html = '<div class="yai-ops-provider-grid yai-ops-provider-grid--4">';
    list.slice(0, 12).forEach(function (p) {
      html += '<article class="yai-ops-card yai-ops-monitor-card" data-monitor="' + esc(p.id) + '"><h3>' + esc(p.name) + '</h3>' +
        '<p class="yai-ops-muted">Loading monitoring…</p></article>';
    });
    html += '</div>';
    return html;
  }

  function logsPanelHtml(list) {
    var html = '<div class="yai-ops-logs-panel"><div class="yai-ops-form-grid" style="margin-bottom:16px">' +
      '<label>Provider<select id="yai-ops-logs-provider"><option value="">All providers</option>' +
      providersCache.map(function (p) {
        return '<option value="' + esc(p.id) + '"' + (logsDrawerId === p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>';
      }).join('') + '</select></label></div>' +
      '<div id="yai-ops-logs-stream"><p class="yai-ops-toast">Loading logs…</p></div></div>';
    return html;
  }

  function modalShell() {
    return '<div class="yai-ops-modal" id="yai-ops-config-modal" hidden><div class="yai-ops-modal-backdrop" data-close-modal></div>' +
      '<div class="yai-ops-modal-panel" role="dialog" aria-modal="true"><header class="yai-ops-modal-head"><h3 id="yai-ops-config-title">Configure Provider</h3>' +
      '<button type="button" class="yai-ops-modal-close" data-close-modal>&times;</button></header>' +
      '<div class="yai-ops-modal-body" id="yai-ops-config-body"></div></div></div>' +
      '<div class="yai-ops-drawer" id="yai-ops-logs-drawer" hidden><div class="yai-ops-drawer-backdrop" data-close-drawer></div>' +
      '<aside class="yai-ops-drawer-panel"><header><h3 id="yai-ops-logs-title">Provider Logs</h3>' +
      '<button type="button" class="yai-ops-modal-close" data-close-drawer>&times;</button></header>' +
      '<div class="yai-ops-drawer-body" id="yai-ops-logs-body"></div></aside></div>';
  }

  function ensureOverlayShell() {
    if (document.getElementById('yai-ops-config-modal')) return;
    var wrap = document.createElement('div');
    wrap.innerHTML = modalShell();
    while (wrap.firstChild) {
      document.body.appendChild(wrap.firstChild);
    }
    bindProviderActions(document);
  }

  function studioDefaultsHtml(p) {
    var studios = p.supports || p.studios || [];
    var defaults = p.studio_defaults || {};
    if (!studios.length) return '';
    return '<div class="yai-ops-studio-defaults"><p class="yai-ops-field-label">Studio Default' + helpIcon('studio_default') + '</p>' +
      studios.map(function (st) {
        var checked = defaults[st] ? ' checked' : '';
        return '<label class="yai-ops-check-row"><input type="checkbox" class="yai-ops-studio-check" data-studio="' + esc(st) + '"' + checked + '> ' + esc(st) + ' 기본값</label>';
      }).join('') + '</div>';
  }

  function openConfigureModal(p) {
    ensureOverlayShell();
    var modal = document.getElementById('yai-ops-config-modal');
    var body = document.getElementById('yai-ops-config-body');
    var title = document.getElementById('yai-ops-config-title');
    if (!modal || !body) return;
    title.textContent = (p.name || p.id) + ' 설정';
    body.innerHTML =
      '<div class="yai-ops-provider-config is-open" data-id="' + esc(p.id) + '">' +
      (p.has_key ? '<p class="yai-ops-key-masked">저장된 API Key: <code>' + esc(p.key_masked || '********') + '</code></p>' : '') +
      labelRow('Mode', 'mode', '<select class="yai-ops-mode" data-id="' + esc(p.id) + '">' +
        ['auto', 'real', 'mock'].map(function (m) {
          return '<option value="' + m + '"' + (p.mode === m ? ' selected' : '') + '>' + esc(modeLabel(m)) + '</option>';
        }).join('') + '</select>') +
      labelRow('Priority', 'priority', '<input type="number" class="yai-ops-priority" data-id="' + esc(p.id) + '" min="0" max="1000" value="' + esc(String(p.priority != null ? p.priority : 50)) + '">') +
      labelRow('Billing', 'billing', '<select class="yai-ops-billing" data-id="' + esc(p.id) + '">' +
        ['unknown', 'available', 'blocked'].map(function (b) {
          return '<option value="' + b + '"' + ((p.billing_status || 'unknown') === b ? ' selected' : '') + '>' + esc(billingLabel(b)) + '</option>';
        }).join('') + '</select>') +
      labelRow('활성화', 'enabled', '<input type="checkbox" class="yai-ops-enabled" data-id="' + esc(p.id) + '"' + (p.enabled !== false ? ' checked' : '') + '>') +
      labelRow('API Key', 'api_key', '<input type="password" class="yai-ops-key" data-id="' + esc(p.id) + '" placeholder="' + esc(p.has_key ? '새 API Key (변경 시에만 입력)' : 'API Key 입력') + '" autocomplete="off">') +
      labelRow('Model', 'model', '<select class="yai-ops-model" data-id="' + esc(p.id) + '">' +
        modelOptionsForProvider(p).map(function (m) {
          return '<option value="' + esc(m) + '"' + ((p.model || '') === m ? ' selected' : '') + '>' + esc(m) + '</option>';
        }).join('') + '</select>') +
      studioDefaultsHtml(p) +
      '<div class="yai-btn-group">' + deps.btnPrimary('저장', 'class="yai-ops-save-p" data-id="' + esc(p.id) + '"') + '</div>' +
      '</div>';
    modal.hidden = false;
    bindProviderActions(modal);
    bindHelpTooltips(modal);
  }

  function applyProvidersPayload(data) {
    if (!data) return;
    if (data.providers) providersCache = data.providers;
    if (data.summary) providersSummary = data.summary;
    if (data.provider) refreshProviderInCache(data.provider);
  }

  function bindHelpTooltips(scope) {
    var root = scope || document;
    root.querySelectorAll('.yai-help-btn').forEach(function (btn) {
      if (btn.dataset.helpBound) return;
      btn.dataset.helpBound = '1';
      var key = btn.getAttribute('data-help');
      var text = HELP_TEXTS[key] || '';
      function showHelp(e) {
        if (e) e.stopPropagation();
        hideHelpPopover();
        helpPopover = document.createElement('div');
        helpPopover.className = 'yai-help-popover';
        helpPopover.setAttribute('role', 'tooltip');
        helpPopover.textContent = text;
        document.body.appendChild(helpPopover);
        var rect = btn.getBoundingClientRect();
        helpPopover.style.left = Math.min(rect.left, window.innerWidth - 340) + 'px';
        helpPopover.style.top = (rect.bottom + 8) + 'px';
      }
      function hideHelpPopover() {
        if (helpPopover && helpPopover.parentNode) {
          helpPopover.parentNode.removeChild(helpPopover);
        }
        helpPopover = null;
      }
      btn.addEventListener('mouseenter', showHelp);
      btn.addEventListener('focus', showHelp);
      btn.addEventListener('mouseleave', hideHelpPopover);
      btn.addEventListener('blur', hideHelpPopover);
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (helpPopover) hideHelpPopover();
        else showHelp(e);
      });
    });
  }

  function hideHelpPopoverGlobal() {
    var pop = document.querySelector('.yai-help-popover');
    if (pop && pop.parentNode) pop.parentNode.removeChild(pop);
    helpPopover = null;
  }

  function closeModal() {
    hideHelpPopoverGlobal();
    var modal = document.getElementById('yai-ops-config-modal');
    if (modal) modal.hidden = true;
  }

  function openLogsDrawer(id) {
    var drawer = document.getElementById('yai-ops-logs-drawer');
    var body = document.getElementById('yai-ops-logs-body');
    var title = document.getElementById('yai-ops-logs-title');
    var p = providersCache.find(function (x) { return x.id === id; }) || { id: id, name: id };
    if (!drawer || !body) return;
    logsDrawerId = id;
    title.textContent = (p.name || id) + ' Logs';
    body.innerHTML = '<p class="yai-ops-toast">Loading…</p>';
    drawer.hidden = false;
    deps.Core.admin.providerLogs(id).then(function (res) {
      body.innerHTML = renderLogEntries((res.data && res.data.entries) || []);
    }).catch(function (e) {
      body.innerHTML = '<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>';
    });
  }

  function closeDrawer() {
    var drawer = document.getElementById('yai-ops-logs-drawer');
    if (drawer) drawer.hidden = true;
    logsDrawerId = null;
  }

  function renderLogEntries(entries) {
    if (!entries.length) return deps.empty('No logs', 'Provider activity will appear here.');
    var html = '<div class="yai-ops-log-list">';
    entries.forEach(function (entry) {
      var lvl = (entry.level || 'info').toLowerCase();
      html += '<article class="yai-ops-log-entry yai-ops-log-entry--' + esc(lvl) + '">' +
        '<header><strong>' + esc(entry.message || 'Event') + '</strong><span>' + esc(entry.created_at || '') + '</span></header>';
      if (entry.context && Object.keys(entry.context).length) {
        var ctxId = 'yai-log-ctx-' + Math.random().toString(36).slice(2);
        html += '<details class="yai-ops-log-details"><summary>Raw JSON</summary>' +
          '<pre class="yai-ops-log-pre" id="' + ctxId + '">' + esc(JSON.stringify(entry.context, null, 2)) + '</pre></details>';
      }
      html += '</article>';
    });
    return html + '</div>';
  }

  function updateProviderCard(p) {
    var card = document.getElementById('yai-ops-provider-' + p.id);
    if (!card) return false;
    var tmp = document.createElement('div');
    tmp.innerHTML = providerCardHtml(p);
    var fresh = tmp.firstChild;
    card.replaceWith(fresh);
    bindProviderActions(fresh.parentNode || document);
    return true;
  }

  function refreshProviderInCache(p) {
    if (!p || !p.id) return;
    for (var i = 0; i < providersCache.length; i++) {
      if (providersCache[i].id === p.id) {
        providersCache[i] = p;
        return;
      }
    }
  }

  function bindProviderActions(scope) {
    var root = scope || document;
    root.querySelectorAll('.yai-ops-config-p').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        var p = providersCache.find(function (x) { return x.id === btn.dataset.id; });
        if (!p) {
          toast('제공업체를 찾을 수 없습니다.', true);
          return;
        }
        deps.Core.admin.getProvider(p.id).then(function (res) {
          var fresh = (res.data && res.data.provider) || p;
          refreshProviderInCache(fresh);
          openConfigureModal(fresh);
        }).catch(function () {
          openConfigureModal(p);
        });
      });
    });
    root.querySelectorAll('.yai-ops-test-p').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        var id = btn.dataset.id;
        var card = document.getElementById('yai-ops-provider-' + id);
        btn.disabled = true;
        var old = btn.textContent;
        btn.textContent = '테스트 중…';
        deps.Core.admin.testProvider(id).then(function (res) {
          var data = res.data || {};
          if (data.provider) {
            refreshProviderInCache(data.provider);
            updateProviderCard(data.provider) || renderProviders();
          } else {
            loadProviders();
          }
          if (data.status === 'unsupported') {
            toast(data.message || '자동 연결 테스트를 지원하지 않습니다.', true);
            return;
          }
          toast(data.message || '연결 테스트에 성공했습니다.');
        }).catch(function (e) {
          loadProviders();
          toast(e.message, true);
        }).finally(function () {
          btn.disabled = false;
          btn.textContent = old;
        });
      });
    });
    root.querySelectorAll('.yai-ops-disable-p').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        var id = btn.dataset.id;
        if (!global.confirm('이 제공업체를 비활성화할까요? Live 생성 및 Auto 라우팅에서 제외됩니다.')) return;
        deps.Core.admin.disableProvider(id).then(function (res) {
          var p = (res.data && res.data.provider) || null;
          if (p) { refreshProviderInCache(p); updateProviderCard(p) || renderProviders(); }
          else renderProviders();
          toast('제공업체가 비활성화되었습니다.');
        }).catch(function (e) { toast(e.message, true); });
      });
    });
    root.querySelectorAll('.yai-ops-enable-p').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        deps.Core.admin.enableProvider(btn.dataset.id).then(function (res) {
          var p = (res.data && res.data.provider) || null;
          if (p) { refreshProviderInCache(p); updateProviderCard(p) || renderProviders(); }
          else renderProviders();
          toast('제공업체가 활성화되었습니다.');
        }).catch(function (e) { toast(e.message, true); });
      });
    });
    root.querySelectorAll('.yai-ops-logs-p').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () { openLogsDrawer(btn.dataset.id); });
    });
    root.querySelectorAll('.yai-ops-save-p').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        var id = btn.dataset.id;
        var panel = btn.closest('.yai-ops-provider-config') || document.getElementById('yai-ops-config-body');
        var payload = { mode: panel.querySelector('.yai-ops-mode').value };
        var pri = panel.querySelector('.yai-ops-priority');
        var bill = panel.querySelector('.yai-ops-billing');
        var keyEl = panel.querySelector('.yai-ops-key');
        var enabledEl = panel.querySelector('.yai-ops-enabled');
        if (pri) payload.priority = parseInt(pri.value, 10) || 50;
        if (bill) payload.billing_status = bill.value;
        if (enabledEl) payload.enabled = !!enabledEl.checked;
        if (keyEl && keyEl.value.trim()) payload.api_key = keyEl.value.trim();
        var modelEl = panel.querySelector('.yai-ops-model');
        if (modelEl) payload.model = modelEl.value;
        var studioDefaults = {};
        panel.querySelectorAll('.yai-ops-studio-check').forEach(function (cb) {
          studioDefaults[cb.dataset.studio] = !!cb.checked;
        });
        payload.studio_defaults = studioDefaults;
        var saveBtn = btn;
        saveBtn.disabled = true;
        var oldSave = saveBtn.textContent;
        saveBtn.textContent = '저장 중…';
        deps.Core.admin.saveProvider(id, payload).then(function (res) {
          applyProvidersPayload(res.data || {});
          closeModal();
          renderProviders();
          toast('설정이 저장되었습니다.');
        }).catch(function (e) {
          toast(e.message || '저장에 실패했습니다.', true);
        }).finally(function () {
          saveBtn.disabled = false;
          saveBtn.textContent = oldSave;
        });
      });
    });
    root.querySelectorAll('.yai-ops-studio-default').forEach(function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = '1';
      btn.addEventListener('click', function () {
        deps.Core.admin.setProviderStudioDefault(btn.dataset.id, btn.dataset.studio).then(function () {
          renderProviders();
          toast('Default provider updated for ' + btn.dataset.studio + '.');
        }).catch(function (e) { toast(e.message, true); });
      });
    });
    root.querySelectorAll('[data-close-modal]').forEach(function (el) {
      if (el.dataset.boundClose) return;
      el.dataset.boundClose = '1';
      el.addEventListener('click', closeModal);
    });
    root.querySelectorAll('[data-close-drawer]').forEach(function (el) {
      if (el.dataset.boundClose) return;
      el.dataset.boundClose = '1';
      el.addEventListener('click', closeDrawer);
    });
  }

  function bindChrome() {
    document.querySelectorAll('.yai-ops-filter-btn').forEach(function (btn) {
      if (btn.dataset.boundFilter) return;
      btn.dataset.boundFilter = '1';
      btn.addEventListener('click', function () {
        providerFilter = btn.dataset.filter || 'all';
        renderProviders();
      });
    });
    var search = document.getElementById('yai-ops-provider-search');
    if (search) {
      search.addEventListener('input', function () {
        providerSearch = search.value.trim();
        renderProviders();
      });
    }
    document.querySelectorAll('.yai-ops-subnav-btn').forEach(function (btn) {
      if (btn.dataset.boundSubnav) return;
      btn.dataset.boundSubnav = '1';
      btn.addEventListener('click', function () {
        providerView = btn.dataset.pview || 'providers';
        renderProviders();
      });
    });
    document.querySelectorAll('.yai-ops-provider-section-hdr.is-collapsible').forEach(function (hdr) {
      hdr.addEventListener('click', function (e) {
        if (e.target.closest('.yai-ops-collapse-btn') || e.target === hdr) {
          var id = hdr.getAttribute('data-collapse');
          var grid = document.getElementById(id);
          if (!grid) return;
          mockCollapsed = !grid.classList.contains('is-collapsed');
          grid.classList.toggle('is-collapsed');
          var btn = hdr.querySelector('.yai-ops-collapse-btn');
          if (btn) {
            btn.textContent = grid.classList.contains('is-collapsed') ? 'Show' : 'Hide';
            btn.setAttribute('aria-expanded', grid.classList.contains('is-collapsed') ? 'false' : 'true');
          }
        }
      });
    });
    var logsSelect = document.getElementById('yai-ops-logs-provider');
    if (logsSelect) {
      logsSelect.addEventListener('change', function () {
        logsDrawerId = logsSelect.value || null;
        loadLogsStream(logsDrawerId);
      });
      loadLogsStream(logsDrawerId);
    }
    document.querySelectorAll('[data-monitor]').forEach(function (card) {
      var id = card.getAttribute('data-monitor');
      deps.Core.admin.providerMonitoring(id).then(function (res) {
        var d = res.data || {};
        card.innerHTML = '<h3>' + esc((providersCache.find(function (p) { return p.id === id; }) || {}).name || id) + '</h3>' +
          '<div class="yai-ops-provider-metrics yai-ops-provider-metrics--v2">' +
          '<div class="yai-ops-provider-metric"><b>Requests</b><span>' + esc(String(d.request_count || 0)) + '</span></div>' +
          '<div class="yai-ops-provider-metric"><b>Success</b><span>' + esc(d.success_rate != null ? d.success_rate + '%' : '—') + '</span></div>' +
          '<div class="yai-ops-provider-metric"><b>Latency</b><span>' + esc(d.avg_latency_ms ? d.avg_latency_ms + 'ms' : '—') + '</span></div>' +
          '<div class="yai-ops-provider-metric"><b>Errors</b><span>' + esc(String(d.failed_count || 0)) + '</span></div>' +
          '</div>' +
          (d.usage ? deps.barChart('Usage', d.usage.labels || [], d.usage.values || []) : '');
      }).catch(function () {
        card.innerHTML = '<h3>' + esc(id) + '</h3><p class="yai-ops-muted">Monitoring unavailable.</p>';
      });
    });
  }

  function loadLogsStream(providerId) {
    var stream = document.getElementById('yai-ops-logs-stream');
    if (!stream) return;
    stream.innerHTML = '<p class="yai-ops-toast">Loading…</p>';
    var req = providerId ? deps.Core.admin.providerLogs(providerId) : deps.Core.admin.logs();
    req.then(function (res) {
      var entries = (res.data && res.data.entries) || [];
      if (!entries.length && res.data && res.data.system_logs) {
        entries = (res.data.system_logs || []).map(function (l) {
          return { type: 'system', level: l.level, message: l.message, context: l.context || {}, created_at: l.created_at };
        });
      }
      stream.innerHTML = renderLogEntries(entries);
    }).catch(function (e) {
      stream.innerHTML = '<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>';
    });
  }

  function renderProviders() {
    var list = filteredProviders();
    var groups = groupProviders(list);
    var panel = '';
    if (providerView === 'monitoring') panel = monitoringPanelHtml(list);
    else if (providerView === 'logs') panel = logsPanelHtml(list);
    else panel = providersPanelHtml(list, groups);

    deps.body(
      deps.sectionOpen('AI 제공업체', '제공업체 설정, 상태 모니터링, 로그 확인', deps.btnPrimary('연결된 항목 일괄 테스트', 'id="yai-ops-test-all"')) +
      summaryHtml(providersSummary) +
      subNavHtml() +
      filterBarHtml() +
      panel +
      '<p class="yai-ops-toast" id="yai-ops-msg"></p>' +
      deps.sectionClose()
    );
    ensureOverlayShell();
    bindChrome();
    bindProviderActions(document);
    bindHelpTooltips(document);
    var testAll = document.getElementById('yai-ops-test-all');
    if (testAll) {
      testAll.addEventListener('click', function () {
        toast('설정된 제공업체 테스트 중…');
        var targets = providersCache.filter(function (p) {
          return p.health_group !== 'mock' && p.has_key;
        }).slice(0, 8);
        var chain = Promise.resolve();
        targets.forEach(function (p) {
          chain = chain.then(function () { return deps.Core.admin.testProvider(p.id); });
        });
        chain.then(function () { toast('일괄 테스트가 완료되었습니다.'); loadProviders(); }).catch(function (e) { toast(e.message, true); loadProviders(); });
      });
    }
  }

  function loadProviders() {
    deps.body('<p class="yai-ops-toast">제공업체 불러오는 중…</p>');
    deps.Core.admin.providers().then(function (res) {
      applyProvidersPayload(res.data || {});
      if (!providersCache.length) {
        deps.body(deps.sectionOpen('AI 제공업체', '제공업체 설정, 상태 모니터링, 로그 확인', '') +
          deps.empty('제공업체 없음', '제공업체 카탈로그가 비어 있습니다.') + deps.sectionClose());
        return;
      }
      renderProviders();
    }).catch(function (e) {
      deps.body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>');
    });
  }

  global.YooYOpsProviderUI = {
    init: function (d) { deps = d || {}; },
    loadProviders: loadProviders,
    renderProviders: renderProviders
  };
})(window);
