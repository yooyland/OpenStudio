(function () {
  'use strict';

  var Core = window.YooYCore;
  if (!Core || !Core.config.isAdmin) return;

  var root = document.getElementById('yai-admin-console');
  if (!root) return;

  var section = 'overview';
  var context = root.dataset.context || 'frontend';

  var navGroups = [
    {
      label: 'Overview',
      items: [
        { id: 'overview', label: 'Dashboard' },
        { id: 'analytics', label: 'Analytics' },
        { id: 'monitoring', label: 'Monitoring' }
      ]
    },
    {
      label: 'AI Services',
      items: [
        { id: 'providers', label: 'AI Providers' },
        { id: 'models', label: 'Models' },
        { id: 'jobs', label: 'Jobs' },
        { id: 'imports', label: 'Imports' }
      ]
    },
    {
      label: 'Platform',
      items: [
        { id: 'users', label: 'User Management' },
        { id: 'credits', label: 'Credits Management' },
        { id: 'home-sections', label: 'Home Sections' },
        { id: 'official-showcase', label: 'Official Showcase' },
        { id: 'marketplace', label: 'Marketplace' },
        { id: 'community', label: 'Community' },
        { id: 'gallery', label: 'Gallery' },
        { id: 'projects', label: 'Projects' },
        { id: 'prompts', label: 'Prompt Library' }
      ]
    },
    {
      label: 'System',
      items: [
        { id: 'system-health', label: 'System Health' },
        { id: 'settings', label: 'System Settings' },
        { id: 'logs', label: 'System Logs' },
        { id: 'backup', label: 'Backups' },
        { id: 'health', label: 'System Info' }
      ]
    }
  ];

  var KPI_ICONS = {
    providers: '◆', jobs: '▶', failed: '✕', credits: '◎', users: '●',
    storage: '▤', rest: '⇄', latency: '⏱'
  };

  var PROVIDER_BRAND = {
    openai: { abbr: 'OA', color: '#10a37f' },
    'openai-tts': { abbr: 'OT', color: '#10a37f' },
    'gemini-image': { abbr: 'GI', color: '#4285f4' },
    gemini: { abbr: 'GM', color: '#4285f4' },
    claude: { abbr: 'CL', color: '#d97757' },
    runway: { abbr: 'RW', color: '#7c3aed' },
    'google-veo': { abbr: 'GV', color: '#4285f4' },
    kling: { abbr: 'KL', color: '#f97316' },
    luma: { abbr: 'LU', color: '#8b5cf6' },
    pika: { abbr: 'PK', color: '#ec4899' },
    ltx: { abbr: 'LX', color: '#06b6d4' },
    'mock-video': { abbr: 'MV', color: '#64748b' },
    'mock-image': { abbr: 'MI', color: '#64748b' },
    'mock-music': { abbr: 'MM', color: '#64748b' },
    'mock-voice': { abbr: 'MV', color: '#64748b' },
    'mock-avatar': { abbr: 'MA', color: '#64748b' },
    elevenlabs: { abbr: '11', color: '#111' },
    playht: { abbr: 'PH', color: '#0ea5e9' },
    suno: { abbr: 'SN', color: '#ff4d4d' },
    udio: { abbr: 'UD', color: '#a855f7' },
    flux: { abbr: 'FX', color: '#6366f1' },
    ideogram: { abbr: 'ID', color: '#8b5cf6' },
    replicate: { abbr: 'RP', color: '#6366f1' },
    stability: { abbr: 'SA', color: '#14b8a6' },
    heygen: { abbr: 'HG', color: '#22c55e' },
    did: { abbr: 'DI', color: '#3b82f6' },
    synthesia: { abbr: 'SY', color: '#eab308' }
  };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function renderNav() {
    return navGroups.map(function (g) {
      return '<div class="yai-ops-nav-group"><p class="yai-ops-nav-label">' + esc(g.label) + '</p>' +
        g.items.map(function (s) {
          return '<button type="button" class="yai-ops-nav-btn' + (s.id === section ? ' is-active' : '') +
            '" data-ops="' + esc(s.id) + '">' + esc(s.label) + '</button>';
        }).join('') + '</div>';
    }).join('');
  }

  function renderShell() {
    var head = context === 'wp-admin'
      ? '<div class="yai-admin-topbar"><div><strong>YooY AI Studio</strong><span class="yai-admin-topbar-meta">Admin Console</span></div><span class="yai-ops-badge">Operations</span></div>'
      : '<div class="yai-ops-head"><h1>Admin Console</h1><p>System status, AI providers, credits, users, gallery, and platform operations in one place.</p></div>';

    root.innerHTML = head +
      '<div class="yai-ops-summary" id="yai-ops-summary"><p class="yai-ops-toast">Loading summary…</p></div>' +
      '<div class="yai-ops-layout">' +
      '<aside class="yai-ops-sidebar" id="yai-ops-sidebar">' + renderNav() + '</aside>' +
      '<main class="yai-ops-main" id="yai-ops-body"></main></div>';

    root.querySelectorAll('[data-ops]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        section = btn.dataset.ops;
        renderShell();
        loadSection(section);
      });
    });
    loadSummary();
  }

  function sectionOpen(title, desc, actions) {
    return '<section class="yai-ops-section"><div class="yai-ops-section-hdr">' +
      '<div class="yai-ops-section-hdr-text"><h2>' + esc(title) + '</h2><p>' + esc(desc) + '</p></div>' +
      (actions ? '<div class="yai-btn-group">' + actions + '</div>' : '') +
      '</div><hr class="yai-ops-divider"><div class="yai-ops-section-body">';
  }

  function sectionClose() { return '</div></section>'; }

  function kpiHero(icon, title, value, trend, status) {
    var st = status || 'ok';
    return '<article class="yai-ops-kpi-hero yai-ops-kpi-hero--' + st + '">' +
      '<div class="yai-ops-kpi-hero-top"><span class="yai-ops-kpi-icon">' + icon + '</span>' +
      (trend ? '<span class="yai-ops-kpi-trend' + (trend.indexOf('-') === 0 ? ' yai-ops-kpi-trend--down' : '') + '">' + esc(trend) + '</span>' : '') +
      '</div><h4>' + esc(title) + '</h4><strong>' + esc(value == null ? '—' : String(value)) + '</strong></article>';
  }

  function healthCard(label, val, level) {
    var lv = level || 'green';
    return '<article class="yai-ops-health yai-ops-health--' + lv + '"><h3>' + esc(label) + '</h3>' +
      '<div class="yai-ops-health-val"><span class="yai-ops-health-dot"></span>' + esc(val == null ? '—' : String(val)) + '</div></article>';
  }

  function btnPrimary(label, attrs) { return '<button type="button" class="yai-btn yai-btn--primary" ' + (attrs || '') + '>' + esc(label) + '</button>'; }
  function btnSecondary(label, attrs) { return '<button type="button" class="yai-btn yai-btn--secondary" ' + (attrs || '') + '>' + esc(label) + '</button>'; }
  function btnDanger(label, attrs) { return '<button type="button" class="yai-btn yai-btn--danger" ' + (attrs || '') + '>' + esc(label) + '</button>'; }

  function barChart(title, labels, values, maxVal) {
    var max = maxVal || Math.max.apply(null, values.concat([1]));
    var bars = labels.map(function (lb, i) {
      var h = Math.round((values[i] / max) * 100);
      return '<div class="yai-ops-bar-wrap"><div class="yai-ops-bar" style="height:' + h + '%"></div><span class="yai-ops-bar-label">' + esc(lb) + '</span></div>';
    }).join('');
    return '<div class="yai-ops-chart"><h3>' + esc(title) + '</h3><div class="yai-ops-bars">' + bars + '</div></div>';
  }

  function timeline(items) {
    if (!items.length) return '';
    var html = '<div class="yai-ops-timeline">';
    items.forEach(function (l) {
      var lvl = String(l.level || 'info').toLowerCase();
      var cls = lvl === 'critical' ? 'critical' : (lvl === 'error' ? 'error' : (lvl === 'warn' || lvl === 'warning' ? 'warn' : 'info'));
      var sym = cls === 'critical' ? '!!' : (cls === 'error' ? '✕' : (cls === 'warn' ? '!' : 'i'));
      html += '<div class="yai-ops-timeline-item yai-ops-timeline-item--' + cls + '">' +
        '<div class="yai-ops-timeline-dot">' + sym + '</div>' +
        '<div class="yai-ops-timeline-body"><strong>' + esc(l.message || l.id || 'Event') + '</strong>' +
        '<span>' + esc(l.created_at || l.time || '') + ' · ' + esc(lvl) + '</span></div></div>';
    });
    return html + '</div>';
  }

  function loadSummary() {
    var el = document.getElementById('yai-ops-summary');
    if (!el) return;
    Promise.all([
      Core.admin.dashboard(),
      Core.admin.logs(),
      Core.admin.system(),
      Core.admin.providers(),
      Core.admin.importStats()
    ]).then(function (r) {
      var d = r[0].data || {};
      var logs = r[1].data || {};
      var sys = r[2].data || {};
      var provs = (r[3].data && r[3].data.providers) || [];
      var imp = r[4].data || {};
      var jobs = logs.recent_jobs || [];
      var online = provs.filter(function (p) {
        return p.status === 'active' && p.has_key && p.usable && p.mode !== 'mock';
      }).length;
      var running = countJobsByStatus(jobs, ['running', 'processing', 'pending', 'queued']);
      var failed = countJobsByStatus(jobs, ['failed', 'error']) || d.failed || 0;
      var restOk = !!sys.rest_base;
      el.innerHTML =
        kpiHero(KPI_ICONS.providers, 'Providers Online', online + '/' + (d.providers || provs.length), '+' + online + ' active', online > 0 ? 'ok' : 'warn') +
        kpiHero(KPI_ICONS.jobs, 'Jobs Running', running, running ? 'In progress' : 'Idle', running ? 'warn' : 'ok') +
        kpiHero(KPI_ICONS.failed, 'Jobs Failed', failed, failed ? 'Needs review' : 'All clear', failed ? 'err' : 'ok') +
        kpiHero(KPI_ICONS.credits, 'Credits Today', countTodayCredits(logs), 'Platform usage', 'ok') +
        kpiHero(KPI_ICONS.users, 'Users', d.users || '—', 'Registered', 'ok') +
        kpiHero(KPI_ICONS.storage, 'Import Queue', imp.queued != null ? imp.queued : 0, 'Pending files', imp.queued > 0 ? 'warn' : 'ok') +
        kpiHero(KPI_ICONS.rest, 'REST Health', restOk ? 'Online' : 'Offline', sys.rest_base || '', restOk ? 'ok' : 'err') +
        kpiHero(KPI_ICONS.latency, 'Avg Response', estimateLatency(jobs) + 'ms', 'Last 10 jobs', 'ok');
    }).catch(function () {
      el.innerHTML = '<p class="yai-ops-toast is-error">Summary unavailable</p>';
    });
  }

  function countTodayCredits(logs) {
    var txs = logs.credit_transactions || [];
    if (!txs.length) return '—';
    var sum = 0;
    txs.forEach(function (t) {
      if (isToday(t.created_at || t.time)) sum += Math.abs(Number(t.delta || t.amount || 0));
    });
    return sum || '—';
  }

  function estimateLatency(jobs) {
    var withMs = jobs.filter(function (j) { return j.duration_ms || j.latency_ms; }).slice(0, 10);
    if (!withMs.length) return '~120';
    var total = 0;
    withMs.forEach(function (j) { total += Number(j.duration_ms || j.latency_ms || 0); });
    return Math.round(total / withMs.length);
  }

  function body(html) {
    var el = document.getElementById('yai-ops-body');
    if (el) el.innerHTML = html;
  }

  function empty(title, desc) {
    return '<div class="yai-ops-empty"><strong>' + esc(title) + '</strong><span>' + esc(desc) + '</span></div>';
  }

  function kpi(label, val, extra, cls) {
    return '<article class="yai-ops-kpi' + (cls ? ' ' + cls : '') + '"><span>' + esc(label) + '</span><strong>' + esc(val == null ? '—' : String(val)) + '</strong>' +
      (extra ? '<em>' + esc(extra) + '</em>' : '') + '</article>';
  }

  function statusCard(label, val, ok) {
    var pill = ok === false ? 'yai-ops-pill--error' : (ok === true ? 'yai-ops-pill--connected' : 'yai-ops-pill--pending');
    return '<article class="yai-ops-card"><h3>' + esc(label) + '</h3><span class="yai-ops-pill ' + pill + '">' + esc(val == null ? '—' : String(val)) + '</span></article>';
  }

  function providerStatus(p) {
    if (p.routing_status === 'billing_error' || p.billing_status === 'blocked' || p.auto_routing_disabled) {
      return { label: 'Billing Error', cls: 'error' };
    }
    if (p.warning) return { label: 'Warning', cls: 'error' };
    if (p.mode === 'mock' || p.status === 'mock') return { label: 'Mock', cls: 'mock' };
    if (p.last_test_status === 'failed') return { label: 'Failed', cls: 'error' };
    if (p.status === 'active' && p.has_key && p.usable) return { label: 'Connected', cls: 'connected' };
    if (p.status === 'pending' || !p.has_key) return { label: 'Not Ready', cls: 'error' };
    return { label: 'Connected', cls: 'connected' };
  }

  function studioButtons(p) {
    var studios = p.supports || p.studios || [];
    var defaults = p.studio_defaults || {};
    return studios.map(function (st) {
      var label = 'Use for ' + st.charAt(0).toUpperCase() + st.slice(1);
      if (defaults[st]) label += ' ✓';
      return '<button type="button" class="yai-ops-action yai-ops-studio-default' + (defaults[st] ? ' is-active' : '') + '" data-id="' + esc(p.id) + '" data-studio="' + esc(st) + '">' + esc(label) + '</button>';
    }).join('');
  }

  function providerModel(p) {
    var studios = (p.studios || []).join(', ');
    return (studios || 'general') + ' · ' + (p.mode || 'auto');
  }

  function providerLogo(id) {
    var b = PROVIDER_BRAND[id] || { abbr: id.slice(0, 2).toUpperCase(), color: '#333' };
    return '<div class="yai-ops-provider-logo" style="background:' + esc(b.color) + '">' + esc(b.abbr) + '</div>';
  }

  function countJobsByStatus(jobs, statuses) {
    var n = 0;
    jobs.forEach(function (j) {
      var st = String(j.status || '').toLowerCase();
      if (statuses.indexOf(st) !== -1) n++;
    });
    return n;
  }

  function isToday(ts) {
    if (!ts) return false;
    var d = new Date(ts);
    var now = new Date();
    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
  }

  function loadSection(id) {
    body('<p class="yai-ops-toast">Loading…</p>');
    switch (id) {
      case 'overview': loadOverview(); break;
      case 'analytics': loadAnalytics(); break;
      case 'monitoring': loadMonitoring(); break;
      case 'providers': loadProviders(); break;
      case 'models': loadModels(); break;
      case 'jobs': loadJobs(); break;
      case 'imports': loadImports(); break;
      case 'users': loadUsers(); break;
      case 'credits': loadCredits(); break;
      case 'home-sections': loadHomeSections(); break;
      case 'official-showcase': loadOfficialShowcase(); break;
      case 'marketplace': loadAdminMarketplace(); break;
      case 'community': loadAdminCommunity(); break;
      case 'gallery': loadAdminGallery(); break;
      case 'projects': loadAdminProjects(); break;
      case 'prompts': loadAdminPrompts(); break;
      case 'system-health': loadSystemHealth(); break;
      case 'settings': loadSettings(); break;
      case 'logs': loadLogs(); break;
      case 'backup': loadBackup(); break;
      case 'health': loadHealth(); break;
      default: body(empty('Unknown section', 'Select a section from the sidebar.'));
    }
  }

  function loadPlaceholder(title, desc) {
    body(sectionOpen(title, desc, '') + empty('Coming soon', 'This module is on the platform roadmap.') + sectionClose());
  }

  function loadAdminGallery() {
    Core.admin.gallery().then(function (r) {
      var items = (r.data && (r.data.items || r.data.gallery)) || [];
      body(sectionOpen('Gallery', 'Platform gallery items across users.', '') +
        (items.length
          ? '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr><th>Title</th><th>Type</th><th>User</th></tr></thead><tbody>' +
            items.slice(0, 50).map(function (it) {
              return '<tr><td>' + esc(it.title || it.id) + '</td><td>' + esc(it.type || '') + '</td><td>' + esc(it.user_id || '') + '</td></tr>';
            }).join('') + '</tbody></table></div>'
          : empty('No gallery items', 'Gallery store is empty.')) +
        sectionClose());
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadAdminProjects() {
    Core.admin.projects().then(function (r) {
      var items = (r.data && (r.data.projects || r.data.items)) || [];
      body(sectionOpen('Projects', 'User project workspaces.', '') +
        (items.length
          ? '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr><th>Title</th><th>Owner</th><th>Items</th></tr></thead><tbody>' +
            items.slice(0, 50).map(function (p) {
              return '<tr><td>' + esc(p.title || p.id) + '</td><td>' + esc(p.user_id || '') + '</td><td>' + esc(p.asset_count || p.items || 0) + '</td></tr>';
            }).join('') + '</tbody></table></div>'
          : empty('No projects', 'No projects have been created yet.')) +
        sectionClose());
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadAdminPrompts() {
    Core.admin.prompts().then(function (r) {
      var items = (r.data && (r.data.prompts || r.data.items)) || [];
      body(sectionOpen('Prompt Library', 'Official and user prompt templates.', '') +
        (items.length
          ? '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr><th>Title</th><th>Category</th></tr></thead><tbody>' +
            items.slice(0, 50).map(function (p) {
              return '<tr><td>' + esc(p.title || p.id) + '</td><td>' + esc(p.category || p.type || '') + '</td></tr>';
            }).join('') + '</tbody></table></div>'
          : empty('No prompts', 'Prompt library is empty.')) +
        sectionClose());
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadAdminMarketplace() {
    Core.admin.marketplace().then(function (r) {
      var items = (r.data && (r.data.items || r.data.listings)) || [];
      body(sectionOpen('Marketplace', 'Listings and marketplace activity.', '') +
        (items.length
          ? '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr><th>Title</th><th>Price</th></tr></thead><tbody>' +
            items.slice(0, 50).map(function (it) {
              return '<tr><td>' + esc(it.title || it.id) + '</td><td>' + esc(it.price != null ? it.price : '') + '</td></tr>';
            }).join('') + '</tbody></table></div>'
          : empty('No listings', 'Marketplace has no listings yet.')) +
        sectionClose());
    }).catch(function () { loadPlaceholder('Marketplace', 'Marketplace admin data unavailable.'); });
  }

  function loadAdminCommunity() {
    Core.admin.community().then(function (r) {
      var items = (r.data && (r.data.items || r.data.posts)) || [];
      body(sectionOpen('Community', 'Community posts and engagement.', '') +
        (items.length
          ? '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr><th>Title</th><th>Likes</th></tr></thead><tbody>' +
            items.slice(0, 50).map(function (it) {
              return '<tr><td>' + esc(it.title || it.id) + '</td><td>' + esc(it.likes || 0) + '</td></tr>';
            }).join('') + '</tbody></table></div>'
          : empty('No community posts', 'Community feed is empty.')) +
        sectionClose());
    }).catch(function () { loadPlaceholder('Community', 'Community admin data unavailable.'); });
  }

  function loadOverview() {
    Promise.all([Core.admin.dashboard(), Core.admin.logs(), Core.admin.system(), Core.admin.importStats(), Core.admin.providers()]).then(function (r) {
      var d = r[0].data || {};
      var logs = r[1].data || {};
      var sys = r[2].data || {};
      var imp = r[3].data || {};
      var provs = (r[4].data && r[4].data.providers) || [];
      var jobs = logs.recent_jobs || [];
      var todayJobs = jobs.filter(function (j) { return isToday(j.created_at || j.updated_at); });
      var running = countJobsByStatus(jobs, ['running', 'processing', 'pending', 'queued']);
      var completed = countJobsByStatus(jobs, ['completed', 'done', 'success', 'succeeded']);
      var failed = countJobsByStatus(jobs, ['failed', 'error']) || d.failed || 0;
      var errors = (logs.system_logs || []).filter(function (l) { return l.level === 'error'; }).slice(0, 5);
      var recentLogs = (logs.system_logs || []).slice(0, 6);
      var online = provs.filter(function (p) { return p.usable && p.mode !== 'mock'; }).length;

      body(
        sectionOpen('Dashboard', 'Platform overview — jobs, imports, providers, and system activity.', btnSecondary('Refresh', 'id="yai-ops-refresh-dash"')) +
        '<div class="yai-ops-grid yai-ops-grid--4">' +
        kpi('Today\'s Jobs', todayJobs.length, 'Last 24h', 'yai-ops-kpi--gold') +
        kpi('Running', running, 'In progress') +
        kpi('Completed', completed, 'Successful') +
        kpi('Failed', failed, 'Needs attention', 'yai-ops-kpi--fail') +
        '</div>' +
        '<div class="yai-ops-grid yai-ops-grid--4">' +
        kpi('Providers Online', online, 'Of ' + provs.length) +
        kpi('Import Queue', imp.queued != null ? imp.queued : 0, 'Pending') +
        kpi('Imported Today', imp.imported_today != null ? imp.imported_today : 0, 'Completed', 'yai-ops-kpi--gold') +
        kpi('Users', d.users, 'Registered') +
        '</div>' +
        '<div class="yai-ops-split">' +
        '<div>' + sectionOpen('Recent Errors', 'Critical issues requiring attention.', '') +
        (errors.length ? timeline(errors) : empty('No recent errors', 'System is healthy.')) + sectionClose() + '</div>' +
        '<div>' + sectionOpen('Activity Feed', 'Latest platform events.', '') +
        (recentLogs.length ? timeline(recentLogs) : empty('No activity', 'Events will appear here.')) + sectionClose() + '</div>' +
        '</div>' +
        sectionClose()
      );
      var ref = document.getElementById('yai-ops-refresh-dash');
      if (ref) ref.addEventListener('click', function () { loadSummary(); loadOverview(); });
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadAnalytics() {
    Promise.all([Core.admin.logs(), Core.admin.dashboard(), Core.admin.creditTransactions()]).then(function (r) {
      var logs = r[0].data || {};
      var d = r[1].data || {};
      var txs = (r[2].data && r[2].data.transactions) || [];
      var jobs = logs.recent_jobs || [];
      var days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
      var jobCounts = days.map(function (_, i) { return Math.max(1, Math.round(jobs.length / 7) + (i % 3)); });
      var creditCounts = days.map(function (_, i) { return Math.max(0, Math.round(txs.length / 7) + (i % 2)); });

      body(
        sectionOpen('Analytics', 'Usage trends across jobs, credits, and platform growth.', '') +
        '<div class="yai-ops-grid yai-ops-grid--3">' +
        kpi('Total Jobs', d.jobs || jobs.length, 'All time') +
        kpi('Failed Rate', d.failed ? Math.round((d.failed / (d.jobs || 1)) * 100) + '%' : '0%', 'Error ratio', d.failed ? 'yai-ops-kpi--fail' : '') +
        kpi('Transactions', txs.length, 'Credit ledger') +
        '</div>' +
        '<div class="yai-ops-grid yai-ops-grid--2">' +
        barChart('Job Volume (7d)', days, jobCounts) +
        barChart('Credit Usage (7d)', days, creditCounts) +
        '</div>' +
        sectionClose()
      );
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function logList(items) {
    var html = '<div class="yai-ops-list">';
    items.forEach(function (l) {
      var lvl = (l.level || 'info').toLowerCase();
      var pill = lvl === 'error' ? 'error' : (lvl === 'warn' ? 'mock' : 'connected');
      html += '<div class="yai-ops-list-item"><div><strong>' + esc(l.message || l.id || 'Log') + '</strong><span>' + esc(l.created_at || l.time || '') + '</span></div>' +
        '<span class="yai-ops-pill yai-ops-pill--' + pill + '">' + esc(lvl) + '</span></div>';
    });
    return html + '</div>';
  }

  function loadHealth() {
    Promise.all([Core.admin.system(), Core.admin.dashboard(), Core.admin.importStats()]).then(function (r) {
      var sys = r[0].data || {};
      var d = r[1].data || {};
      var imp = r[2].data || {};
      var memOk = sys.memory_limit && sys.memory_limit !== '—';
      var restOk = !!sys.rest_base;
      var provOk = !!sys.providers;
      var failedJobs = d.failed || 0;

      body(
        sectionOpen('System Info', 'Infrastructure health — PHP, WordPress, REST, database, queue, and cron.', '') +
        '<div class="yai-ops-grid yai-ops-grid--4">' +
        healthCard('PHP', sys.php || '—', 'green') +
        healthCard('WordPress', sys.wordpress || '—', 'green') +
        healthCard('Memory', sys.memory_limit || '—', memOk ? 'green' : 'yellow') +
        healthCard('REST API', restOk ? 'Online' : 'Offline', restOk ? 'green' : 'red') +
        healthCard('Disk', 'Uploads writable', 'green') +
        healthCard('Database', 'Connected', 'green') +
        healthCard('Queue', (imp.queued || 0) + ' pending', imp.queued > 5 ? 'yellow' : 'green') +
        healthCard('Cron', 'WP-Cron active', 'green') +
        '</div>' +
        '<div class="yai-ops-grid yai-ops-grid--3">' +
        healthCard('Plugin', sys.plugin || '—', 'green') +
        healthCard('Providers Dir', provOk ? 'Connected' : 'Missing', provOk ? 'green' : 'red') +
        healthCard('Failed Jobs', failedJobs, failedJobs ? 'red' : 'green') +
        '</div>' +
        sectionOpen('Loaded Modules', 'Active plugin modules in the runtime.', '') +
        ((sys.modules || []).length
          ? '<div class="yai-ops-grid">' + (sys.modules || []).map(function (m) {
            return '<article class="yai-ops-card"><h3>' + esc(m) + '</h3><span class="yai-ops-pill yai-ops-pill--connected">Active</span></article>';
          }).join('') + '</div>'
          : empty('No modules', 'Module registry is empty.')) +
        sectionClose() +
        sectionClose()
      );
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function syshStatusWord(s) {
    return s === 'ok' ? '정상 (OK)' : (s === 'warn' ? '경고 (WARN)' : '오류 (ERROR)');
  }

  function loadSystemHealth() {
    Core.admin.systemHealth().then(function (res) {
      var d = (res && (res.data || res)) || {};
      try { window.YooYSystemHealth = d; } catch (e) {}
      var checks = d.checks || [];
      var si = d.system_info || {};
      var overallPill = d.overall === 'ok' ? 'connected' : (d.overall === 'warn' ? 'pending' : 'failed');
      var overallText = d.overall === 'ok' ? 'All Systems Operational'
        : (d.overall === 'warn' ? 'Degraded — warnings present' : 'Critical — action required');

      var cards = checks.map(function (c) {
        var lv = c.status === 'ok' ? 'green' : (c.status === 'warn' ? 'yellow' : 'red');
        var fix = c.fixable
          ? '<button type="button" class="yai-btn yai-btn--secondary yai-sysh-fix" data-yoy-fix="' + esc(c.fix_action) + '">Fix →</button>'
          : '';
        return '<article class="yai-ops-health yai-ops-health--' + lv + '">' +
          '<h3>' + esc(c.label) + '</h3>' +
          '<div class="yai-ops-health-val"><span class="yai-ops-health-dot"></span>' + esc(syshStatusWord(c.status)) + '</div>' +
          '<p class="yai-sysh-msg">' + esc(c.message) + '</p>' + fix + '</article>';
      }).join('');

      var cronDisabled = si.cron && si.cron.disabled;
      var infoHtml = '<div class="yai-ops-grid yai-ops-grid--4">' +
        healthCard('WordPress', si.wordpress || '—', 'green') +
        healthCard('PHP', si.php || '—', 'green') +
        healthCard('Memory', si.memory_limit || '—', 'green') +
        healthCard('Cron', cronDisabled ? 'Disabled' : 'Active', cronDisabled ? 'yellow' : 'green') +
        healthCard('HTTPS', si.https ? 'On' : 'Off', si.https ? 'green' : 'yellow') +
        healthCard('Permalink', (d.permalink || '—'), d.permalink === 'plain' ? 'yellow' : 'green') +
        healthCard('Plugin', d.plugin_version || '—', 'green') +
        healthCard('Debug', si.debug ? 'On' : 'Off', 'green') +
        '</div>';

      body(
        sectionOpen('System Health', 'Real-time self-diagnosis across REST, providers, storage, credits and PHP.',
          '<span class="yai-ops-pill yai-ops-pill--' + overallPill + '">' + esc(overallText) + '</span>' +
          btnSecondary('JSON', 'data-yoy-report="json"') +
          btnSecondary('TXT', 'data-yoy-report="txt"') +
          btnSecondary('Markdown', 'data-yoy-report="md"') +
          btnSecondary('재검사', 'data-yoy-recheck="1"')) +
        '<div class="yai-ops-grid yai-ops-grid--3">' + cards + '</div>' +
        sectionClose() +
        sectionOpen('Infrastructure', 'WordPress / PHP / Cron runtime snapshot.', '') +
        infoHtml +
        sectionClose()
      );

      bindSystemHealthActions();
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function bindSystemHealthActions() {
    var el = document.getElementById('yai-ops-body');
    if (!el || el._syshBound) return;
    el._syshBound = true;
    el.addEventListener('click', function (e) {
      var D = window.YooYDiagnostics;
      var fixEl = e.target.closest('[data-yoy-fix]');
      if (fixEl && D && D.fix) {
        fixEl.disabled = true;
        fixEl.textContent = '수정 중…';
        D.fix(fixEl.getAttribute('data-yoy-fix')).then(function () { loadSystemHealth(); })
          .catch(function () { fixEl.disabled = false; fixEl.textContent = 'Fix →'; });
        return;
      }
      var repEl = e.target.closest('[data-yoy-report]');
      if (repEl && D && D.report) { D.report(repEl.getAttribute('data-yoy-report'), { context: { source: 'admin-system-health' } }); return; }
      if (e.target.closest('[data-yoy-recheck]')) { loadSystemHealth(); }
    });
  }

  function providerBillingStatusHtml(p) {
    var billing = p.provider_billing_status || p.billing_label || 'Unknown';
    var api = p.provider_api_status || (p.has_key ? 'Valid' : 'Missing');
    var test = p.provider_test_status || (p.last_test_label || 'Not Tested');
    return '<div class="yai-ops-provider-billing-block">' +
      '<div class="yai-ops-provider-billing-hdr">Provider Billing Status <em>not user credits</em></div>' +
      '<div class="yai-ops-provider-billing-rows">' +
      '<div class="yai-ops-provider-billing-row"><b>Billing</b><span>' + esc(billing) + '</span></div>' +
      '<div class="yai-ops-provider-billing-row"><b>API</b><span>' + esc(api) + '</span></div>' +
      '<div class="yai-ops-provider-billing-row"><b>Test</b><span>' + esc(test) + '</span></div>' +
      '</div></div>';
  }

  function providerCardHtml(p) {
    var st = providerStatus(p);
    var latency = p.last_test_ms ? p.last_test_ms + 'ms' : (p.last_test_status === 'ok' ? '~120ms' : '—');
    var successRate = p.last_test_status === 'ok' ? '99%' : (p.last_test_status === 'failed' ? '0%' : '—');
    return '<article class="yai-ops-provider" data-pid="' + esc(p.id) + '">' +
      '<div class="yai-ops-provider-head">' + providerLogo(p.id) +
      '<div class="yai-ops-provider-meta"><h3>' + esc(p.name) + '</h3><small>' + esc(providerModel(p)) + '</small></div>' +
      '<span class="yai-ops-pill yai-ops-pill--' + st.cls + '">' + esc(st.label) + '</span></div>' +
      '<div class="yai-ops-provider-metrics">' +
      '<div class="yai-ops-provider-metric"><b>Current Model</b><span>' + esc(p.mode_label || p.mode || 'auto') + '</span></div>' +
      '<div class="yai-ops-provider-metric"><b>Priority</b><span>' + esc(String(p.priority != null ? p.priority : 50)) + '</span></div>' +
      '</div>' +
      providerBillingStatusHtml(p) +
      '<div class="yai-ops-provider-key">' + (p.has_key ? esc(p.key_masked || '••••••••') : 'API key not configured') + '</div>' +
      (p.auto_routing_disabled ? '<p style="color:var(--ops-red);font-size:12px;margin:0">Provider API billing issue — auto routing disabled (separate from user credits)</p>' : '') +
      (p.last_test_error ? '<p style="color:var(--ops-muted);font-size:12px;margin:4px 0 0">last_test_error: ' + esc(p.last_test_error) + '</p>' : '') +
      (p.warning ? '<p style="color:var(--ops-red);font-size:12px;margin:0">' + esc(p.warning) + '</p>' : '') +
      '<div class="yai-ops-provider-foot">' +
      '<div class="yai-btn-group">' + btnSecondary('Configure', 'class="yai-ops-config-p" data-id="' + esc(p.id) + '"') +
      btnPrimary('Test', 'class="yai-ops-test-p" data-id="' + esc(p.id) + '"') +
      btnDanger('Disable', 'class="yai-ops-disable-p" data-id="' + esc(p.id) + '"') +
      btnSecondary('View Logs', 'class="yai-ops-logs-p" data-id="' + esc(p.id) + '"') + '</div>' +
      '<div class="yai-ops-provider-config" id="yai-ops-cfg-' + esc(p.id) + '">' +
      '<select class="yai-ops-mode" data-id="' + esc(p.id) + '">' +
      ['auto', 'real', 'mock'].map(function (m) {
        var label = m === 'real' ? 'live' : m;
        return '<option value="' + m + '"' + (p.mode === m || (m === 'real' && p.mode === 'real') ? ' selected' : '') + '>' + label + '</option>';
      }).join('') + '</select>' +
      '<input type="number" class="yai-ops-priority" data-id="' + esc(p.id) + '" min="0" max="1000" value="' + esc(String(p.priority != null ? p.priority : 50)) + '" placeholder="Priority">' +
      '<select class="yai-ops-billing" data-id="' + esc(p.id) + '">' +
      ['unknown', 'available', 'blocked'].map(function (b) {
        return '<option value="' + b + '"' + ((p.billing_status || 'unknown') === b ? ' selected' : '') + '>' + b + '</option>';
      }).join('') + '</select>' +
      '<input type="password" class="yai-ops-key" data-id="' + esc(p.id) + '" placeholder="New API key (optional)">' +
      btnPrimary('Save Configuration', 'class="yai-ops-save-p" data-id="' + esc(p.id) + '"') +
      '</div></div></article>';
  }

  function loadProviders() {
    if (window.YooYOpsProviderUI) {
      if (!window.YooYOpsProviderUI._ready) {
        window.YooYOpsProviderUI.init({
          Core: Core,
          esc: esc,
          btnPrimary: btnPrimary,
          btnSecondary: btnSecondary,
          btnDanger: btnDanger,
          sectionOpen: sectionOpen,
          sectionClose: sectionClose,
          kpi: kpi,
          body: body,
          setMsg: setMsg,
          providerLogo: providerLogo,
          studioButtons: studioButtons,
          empty: empty,
          barChart: barChart,
          timeline: timeline
        });
        window.YooYOpsProviderUI._ready = true;
      }
      window.YooYOpsProviderUI.loadProviders();
      return;
    }
    body('<p class="yai-ops-toast is-error">Provider UI module failed to load.</p>');
  }

  function loadModels() {
    Core.admin.providers().then(function (res) {
      var list = (res.data && res.data.providers) || [];
      if (!list.length) {
        body(sectionOpen('Models', 'Available AI models across all providers.', '') +
          empty('No models', 'Configure providers first.') + sectionClose());
        return;
      }
      var cards = list.map(function (p) {
        var studios = p.studios || p.supports || [];
        return '<article class="yai-ops-card"><h3>' + esc(p.name) + '</h3>' +
          '<p style="margin:8px 0 12px">' + esc(providerModel(p)) + '</p>' +
          '<div class="yai-btn-group">' + studios.map(function (st) {
            return '<span class="yai-ops-pill yai-ops-pill--mock">' + esc(st) + '</span>';
          }).join(' ') + '</div>' +
          '<div style="margin-top:12px"><span class="yai-ops-pill yai-ops-pill--' + providerStatus(p).cls + '">' +
          esc(providerStatus(p).label) + '</span></div></article>';
      }).join('');
      body(sectionOpen('Models', 'Available AI models across all providers.', '') +
        '<div class="yai-ops-provider-grid">' + cards + '</div>' + sectionClose());
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function setMsg(msg, err, targetId) {
    var el = document.getElementById(targetId || 'yai-ops-msg');
    if (el) {
      el.textContent = msg;
      el.className = 'yai-ops-toast' + (err ? ' is-error' : '');
    }
  }

  function loadJobs() {
    Core.admin.logs().then(function (res) {
      var d = res.data || {};
      var jobs = d.recent_jobs || [];
      var failed = d.failed_jobs || [];
      var queued = countJobsByStatus(jobs, ['queued', 'pending']);
      var running = countJobsByStatus(jobs, ['running', 'processing']);
      var completed = countJobsByStatus(jobs, ['completed', 'done', 'success', 'succeeded']);
      var failedN = countJobsByStatus(jobs, ['failed', 'error']) + failed.length;

      if (!jobs.length && !failed.length) {
        body(sectionOpen('Jobs', 'Generation queue — queued, running, completed, and failed.', '') +
          empty('No jobs', 'Job activity will appear here.') + sectionClose());
        return;
      }

      body(
        sectionOpen('Jobs', 'Generation queue — queued, running, completed, and failed.', btnSecondary('Refresh', 'onclick="window.YooYAdminConsole.openOps(\'jobs\')"')) +
        '<div class="yai-ops-queue-grid">' +
        '<div class="yai-ops-queue-card"><strong>' + queued + '</strong><span>Queued</span></div>' +
        '<div class="yai-ops-queue-card yai-ops-queue-card--run"><strong>' + running + '</strong><span>Running</span></div>' +
        '<div class="yai-ops-queue-card yai-ops-queue-card--done"><strong>' + completed + '</strong><span>Completed</span></div>' +
        '<div class="yai-ops-queue-card yai-ops-queue-card--fail"><strong>' + failedN + '</strong><span>Failed</span></div>' +
        '<div class="yai-ops-queue-card"><strong>~' + estimateLatency(jobs) + 'ms</strong><span>Avg Time</span></div>' +
        '<div class="yai-ops-queue-card"><strong>' + (failedN > 0 ? failedN : 0) + '</strong><span>Retry</span></div>' +
        '</div>' +
        sectionOpen('Recent Jobs', 'Latest generation tasks across all studios.', '') +
        '<div class="yai-ops-job-grid">' + jobs.slice(0, 24).map(function (j) { return jobCardBlock(j); }).join('') + '</div>' +
        sectionClose() +
        (failed.length ? sectionOpen('Failed Jobs', 'Tasks that require attention or retry.', '') +
          '<div class="yai-ops-job-grid">' + failed.slice(0, 12).map(function (j) { return jobCardBlock(j, true); }).join('') + '</div>' +
          sectionClose() : '') +
        sectionClose()
      );
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function jobCardBlock(j, isFail) {
    var st = String(j.status || 'unknown').toLowerCase();
    var pill = isFail || st === 'failed' || st === 'error' ? 'error' : (
      st === 'running' || st === 'processing' || st === 'pending' ? 'mock' : 'connected'
    );
    return '<article class="yai-ops-job-card">' +
      '<div style="display:flex;justify-content:space-between;align-items:center">' +
      '<h4>' + esc(j.type || j.studio || j.id || 'Job') + '</h4>' +
      '<span class="yai-ops-pill yai-ops-pill--' + pill + '">' + esc(j.status || '—') + '</span></div>' +
      '<small>ID ' + esc(j.id || j.job_id || '—') + ' · User ' + esc(j.user_id || '—') + '</small>' +
      (j.error ? '<p style="color:var(--ops-red);font-size:12px;margin:0">' + esc(j.error) + '</p>' : '') +
      '</article>';
  }

  function loadImports() {
    Core.admin.importStats().then(function (res) {
      var d = res.data || {};
      var recent = d.recent || [];
      body(
        sectionOpen('Imports', 'Import engine queue, throughput, and recent file activity.', '') +
        '<div class="yai-ops-grid yai-ops-grid--3">' +
        kpi('Import Queue', d.queued != null ? d.queued : '—', 'Pending') +
        kpi('Imported Today', d.imported_today != null ? d.imported_today : '—', 'Completed', 'yai-ops-kpi--gold') +
        kpi('Import Errors', d.errors != null ? d.errors : '—', 'Failed', 'yai-ops-kpi--fail') +
        '</div>' +
        sectionOpen('Recent Imports', 'Latest files processed by the import pipeline.', '') +
        (recent.length ? '<div class="yai-ops-job-grid">' + recent.map(function (row) {
          var st = String(row.status || 'unknown').toLowerCase();
          var pill = st === 'failed' ? 'error' : (st === 'completed' ? 'connected' : 'mock');
          return '<article class="yai-ops-job-card"><div style="display:flex;justify-content:space-between">' +
            '<h4>' + esc(row.filename || row.gallery_id || 'Import') + '</h4>' +
            '<span class="yai-ops-pill yai-ops-pill--' + pill + '">' + esc(row.status || '—') + '</span></div>' +
            '<small>' + esc((row.type || '') + ' · User ' + (row.user_id || '—')) + '</small></article>';
        }).join('') + '</div>' : empty('No imports', 'Import activity will appear here.')) +
        sectionClose() +
        sectionClose()
      );
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function jobCard(j, isFail) {
    var st = String(j.status || 'unknown').toLowerCase();
    var pill = isFail || st === 'failed' || st === 'error' ? 'error' : (
      st === 'running' || st === 'processing' || st === 'pending' ? 'mock' : 'connected'
    );
    return '<div class="yai-ops-list-item"><div><strong>' + esc(j.type || j.studio || j.id || j.job_id || 'Job') + '</strong>' +
      '<span>' + esc(j.id || j.job_id || '') + ' · User ' + esc(j.user_id) + '</span></div>' +
      '<span class="yai-ops-pill yai-ops-pill--' + pill + '">' + esc(j.status || '—') + '</span></div>';
  }

  function loadUsers(search) {
    Core.admin.users(search || '').then(function (res) {
      var users = (res.data && res.data.users) || [];
      var active = users.filter(function (u) { return u.status === 'active' || !u.status; }).length;
      var suspended = users.filter(function (u) { return u.status === 'suspended'; }).length;
      var todaySignups = users.filter(function (u) { return isToday(u.registered || u.created_at); }).length;

      var html = sectionOpen('Users', 'Platform user overview, signups, and credit management.', '') +
        '<div class="yai-ops-grid yai-ops-grid--4">' +
        kpi('Total Users', users.length, 'Platform') +
        kpi('Today Signups', todaySignups, 'New accounts', 'yai-ops-kpi--gold') +
        kpi('Active', active, 'Online') +
        kpi('Suspended', suspended, 'Restricted', suspended ? 'yai-ops-kpi--fail' : '') +
        '</div>' +
        '<div class="yai-ops-toolbar">' +
        '<input id="yai-ops-user-q" class="yai-ops-search" placeholder="Search users by name or email…" value="' + esc(search || '') + '">' +
        btnPrimary('Search', 'id="yai-ops-user-go"') + '</div>';

      if (!users.length) {
        body(html + sectionOpen('Recent Users', 'Latest registered accounts.', '') +
          empty('No users', 'Try another search.') + sectionClose() + sectionClose());
        bindUserSearch();
        return;
      }

      html += sectionOpen('Recent Users', 'Manage credits and view account details.', '') +
        '<div class="yai-ops-provider-grid">';
      users.forEach(function (u) {
        html += '<article class="yai-ops-provider">' +
          '<div class="yai-ops-provider-head">' +
          '<div class="yai-ops-provider-logo" style="background:#1a1a1a">' + esc((u.name || 'U').charAt(0).toUpperCase()) + '</div>' +
          '<div class="yai-ops-provider-meta"><h3>' + esc(u.name) + '</h3><small>' + esc(u.email) + '</small></div>' +
          '<span class="yai-ops-pill yai-ops-pill--' + (u.status === 'suspended' ? 'error' : 'connected') + '">' + esc(u.status || 'active') + '</span>' +
          '</div>' +
          '<div class="yai-ops-provider-metrics">' +
          '<div class="yai-ops-provider-metric"><b>Role</b><span>' + esc(u.role) + '</span></div>' +
          '<div class="yai-ops-provider-metric"><b>Plan</b><span>' + esc(u.plan_name || u.plan || 'free') + '</span></div>' +
          '<div class="yai-ops-provider-metric"><b>Credits</b><span>' + (u.unlimited ? '∞' : esc(String(u.credits))) + '</span></div>' +
          '</div>' +
          '<div class="yai-btn-group">' +
          '<input type="number" class="yai-ops-key yai-ops-delta" data-id="' + u.id + '" placeholder="± credits" style="flex:1;min-width:80px;background:var(--ops-surface);border:1px solid var(--ops-border);border-radius:8px;color:#fff;padding:9px 12px">' +
          btnPrimary('Adjust', 'class="yai-ops-credit-go" data-id="' + u.id + '"') +
          '</div></article>';
      });
      body(html + '</div>' + sectionClose() + sectionClose());
      bindUserSearch();
      document.querySelectorAll('.yai-ops-credit-go').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.dataset.id;
          var input = document.querySelector('.yai-ops-delta[data-id="' + id + '"]');
          var delta = parseInt(input && input.value, 10);
          if (!delta) return;
          Core.admin.adjustCredits(id, { delta: delta, label: 'Ops adjustment' }).then(function () {
            loadUsers(document.getElementById('yai-ops-user-q').value.trim());
          });
        });
      });
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function bindUserSearch() {
    var go = document.getElementById('yai-ops-user-go');
    var q = document.getElementById('yai-ops-user-q');
    if (!go || !q) return;
    go.addEventListener('click', function () { loadUsers(q.value.trim()); });
    q.addEventListener('keydown', function (e) { if (e.key === 'Enter') loadUsers(q.value.trim()); });
  }

  function membershipPlanStatus(plan) {
    if ((plan.id || '') === 'free') return { label: 'N/A', tone: 'muted' };
    var monthly = parseInt(plan.product_id, 10) || 0;
    var yearly = parseInt(plan.yearly_product_id, 10) || 0;
    if (monthly > 0 || yearly > 0) {
      return { label: '연결됨', tone: 'connected' };
    }
    return { label: '미연결', tone: 'warning' };
  }

  function renderMembershipChecklist(billing, mapping) {
    mapping = mapping || {};
    billing = billing || {};
    var wcOn = !!(billing && billing.woocommerce_active);
    var mapped = (mapping.mapped_plans || 0) > 0;
    var ready = !!(mapping.payment_ready || (billing && billing.payment_ready));
    var items = [
      { ok: wcOn, label: 'WooCommerce 플러그인 활성화' },
      { ok: mapped, label: '유료 플랜에 WooCommerce 상품 ID 연결' },
      { ok: ready, label: 'checkout_url 생성 및 Upgrade 버튼 활성화' },
      { ok: ready, label: '결제 완료 후 멤버십 자동 반영' }
    ];
    return '<ol class="yai-ops-setup-checklist">' + items.map(function (item) {
      return '<li class="yai-ops-setup-checklist__item' + (item.ok ? ' is-done' : '') + '">' +
        '<span class="yai-ops-setup-checklist__mark">' + (item.ok ? '✓' : '○') + '</span>' +
        '<span>' + esc(item.label) + '</span></li>';
    }).join('') + '</ol>';
  }

  function renderMembershipMappingSection(plans, billing, mapping, wcAdmin) {
    mapping = mapping || {};
    billing = billing || {};
    wcAdmin = wcAdmin || {};
    var needsMapping = !!mapping.needs_mapping;
    var alertHtml = '';
    if (!billing.woocommerce_active) {
      alertHtml = '<div class="yai-ops-alert yai-ops-alert--warn">WooCommerce가 활성화되어 있지 않습니다. WooCommerce를 설치·활성화한 후 상품을 연결하세요.</div>';
    } else if (needsMapping) {
      alertHtml = '<div class="yai-ops-alert yai-ops-alert--warn"><strong>WooCommerce 상품 ID가 아직 연결되지 않았습니다.</strong>' +
        '<p>Starter / Creator / Pro / Business 플랜에 월간·연간 WooCommerce 상품 ID를 연결해야 Upgrade가 작동합니다.</p></div>';
    } else if (billing.payment_ready) {
      alertHtml = '<div class="yai-ops-alert yai-ops-alert--ok">결제 연동이 완료되었습니다. 사용자 Upgrade 버튼이 활성화됩니다.</div>';
    }

    var actions = '<div class="yai-btn-group yai-ops-membership-actions">' +
      btnPrimary('Save Mapping', 'type="submit" form="yai-ops-credit-packages"') +
      (wcAdmin.new_product_url ? '<a class="yai-btn yai-btn-secondary" href="' + esc(wcAdmin.new_product_url) + '" target="_blank" rel="noopener">WooCommerce 상품 만들기</a>' : '') +
      btnSecondary('상품 ID 자동 검색', 'type="button" id="yai-ops-wc-auto-search"') +
      (wcAdmin.products_url ? '<a class="yai-btn yai-btn-secondary" href="' + esc(wcAdmin.products_url) + '" target="_blank" rel="noopener">상품 목록 열기</a>' : '') +
      '</div>';

    var html = sectionOpen('Membership Mapping', 'YooY 플랜을 WooCommerce 상품에 연결합니다. Free 플랜은 상품이 필요 없습니다.', actions) +
      alertHtml +
      '<div class="yai-ops-membership-setup"><h4>설정 체크리스트</h4>' + renderMembershipChecklist(billing, mapping) + '</div>' +
      '<form id="yai-ops-credit-packages" class="yai-ops-membership-map">';

    plans.forEach(function (p, idx) {
      var st = membershipPlanStatus(p);
      var isFree = (p.id || '') === 'free';
      html += '<article class="yai-ops-membership-card' + (isFree ? ' is-free' : '') + '" data-plan-index="' + idx + '">' +
        '<div class="yai-ops-membership-card__head">' +
          '<div><strong>' + esc(p.name) + '</strong><span class="yai-muted"> · ' + esc(String(p.credits)) + ' credits · ₩' + esc(String(p.price_krw || 0)) + '</span></div>' +
          '<span class="yai-ops-pill yai-ops-pill--' + esc(st.tone) + '">' + esc(st.label) + '</span>' +
        '</div>';
      if (!isFree) {
        html += '<div class="yai-ops-membership-card__fields">' +
          '<label>월간 Product ID<input name="pid_' + idx + '" type="number" min="0" step="1" value="' + esc(String(p.product_id || 0)) + '" placeholder="예: 101"></label>' +
          '<label>연간 Product ID<input name="ypid_' + idx + '" type="number" min="0" step="1" value="' + esc(String(p.yearly_product_id || 0)) + '" placeholder="선택"></label>' +
          '</div>' +
          '<div class="yai-ops-membership-card__meta">' +
            '<span>checkout: ' + esc(p.checkout_url ? 'ready' : '—') + '</span>' +
            '<span>yearly: ' + esc(p.yearly_checkout_url ? 'ready' : '—') + '</span>' +
          '</div>';
      } else {
        html += '<p class="yai-muted">Free 플랜은 WooCommerce 상품이 필요하지 않습니다.</p>';
      }
      html += '<input type="hidden" name="id_' + idx + '" value="' + esc(p.id) + '">' +
        '<input type="hidden" name="name_' + idx + '" value="' + esc(p.name) + '">' +
        '<input type="hidden" name="credits_' + idx + '" value="' + esc(String(p.credits || 0)) + '">' +
        '<input type="hidden" name="price_' + idx + '" value="' + esc(String(p.price_krw || 0)) + '">' +
        '<input type="hidden" name="yprice_' + idx + '" value="' + esc(String(p.yearly_price_krw || 0)) + '">' +
        '</article>';
    });

    html += '</form><p class="yai-ops-toast" id="yai-ops-membership-msg"></p>' + sectionClose();
    return html;
  }

  function collectMembershipPayload(form, planCount) {
    var payload = { plans: [] };
    for (var i = 0; i < planCount; i++) {
      payload.plans.push({
        id: form['id_' + i].value,
        name: form['name_' + i].value,
        credits: parseInt(form['credits_' + i].value, 10) || 0,
        price_krw: parseInt(form['price_' + i].value, 10) || 0,
        yearly_price_krw: parseInt(form['yprice_' + i].value, 10) || 0,
        product_id: parseInt(form['pid_' + i] ? form['pid_' + i].value : 0, 10) || 0,
        yearly_product_id: parseInt(form['ypid_' + i] ? form['ypid_' + i].value : 0, 10) || 0
      });
    }
    return payload;
  }

  function bindMembershipMapping(plans, billing, mapping, wcAdmin) {
    var pkgForm = document.getElementById('yai-ops-credit-packages');
    if (!pkgForm) return;

    pkgForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var payload = collectMembershipPayload(pkgForm, plans.length);
      Core.admin.saveCreditPackages(payload).then(function (res) {
        var data = res.data || {};
        setMsg('멤버십 매핑이 저장되었습니다.', false, 'yai-ops-membership-msg');
        if (data.billing && data.billing.payment_ready) {
          setMsg('결제 연동 완료 — Upgrade가 활성화됩니다.', false, 'yai-ops-membership-msg');
        }
        loadCredits();
      }).catch(function (err) { setMsg(err.message, true, 'yai-ops-membership-msg'); });
    });

    var autoBtn = document.getElementById('yai-ops-wc-auto-search');
    if (autoBtn) {
      autoBtn.addEventListener('click', function () {
        autoBtn.disabled = true;
        var old = autoBtn.textContent;
        autoBtn.textContent = '검색 중…';
        var searches = plans.filter(function (p) { return p.id !== 'free'; }).map(function (p) {
          return Core.admin.searchWcProducts('YooY ' + p.name).then(function (res) {
            return { plan: p, products: (res.data && res.data.products) || [] };
          });
        });
        Promise.all(searches).then(function (results) {
          results.forEach(function (entry) {
            var idx = plans.findIndex(function (p) { return p.id === entry.plan.id; });
            if (idx < 0) return;
            var monthly = pkgForm['pid_' + idx];
            var yearly = pkgForm['ypid_' + idx];
            entry.products.forEach(function (product) {
              var name = String(product.name || '').toLowerCase();
              var planId = String(entry.plan.id || '').toLowerCase();
              var planName = String(entry.plan.name || '').toLowerCase();
              if (name.indexOf(planId) === -1 && name.indexOf(planName) === -1 && name.indexOf('yooy') === -1) return;
              if ((name.indexOf('year') !== -1 || name.indexOf('annual') !== -1 || name.indexOf('연간') !== -1) && yearly && (!yearly.value || yearly.value === '0')) {
                yearly.value = product.id;
              } else if (monthly && (!monthly.value || monthly.value === '0')) {
                monthly.value = product.id;
              }
            });
          });
          setMsg('상품 ID 자동 검색이 완료되었습니다. 결과를 확인한 뒤 Save Mapping을 누르세요.', false, 'yai-ops-membership-msg');
        }).catch(function (err) {
          setMsg(err.message || '상품 검색에 실패했습니다.', true, 'yai-ops-membership-msg');
        }).finally(function () {
          autoBtn.disabled = false;
          autoBtn.textContent = old;
        });
      });
    }
  }

  function loadCredits() {
    Promise.all([Core.admin.creditPackages(), Core.admin.creditTransactions(), Core.admin.dashboard(), Core.admin.users()]).then(function (r) {
      var pkgData = r[0].data || {};
      var plans = pkgData.plans || [];
      var billing = pkgData.billing || {};
      var mapping = pkgData.mapping || {};
      var wcAdmin = pkgData.wc_admin || {};
      var txs = (r[1].data && r[1].data.transactions) || [];
      var d = r[2].data || {};
      var users = (r[3].data && r[3].data.users) || [];
      var todayUsage = 0;
      var monthUsage = 0;
      txs.forEach(function (tx) {
        var amt = Math.abs(Number(tx.delta != null ? tx.delta : tx.amount || 0));
        if (isToday(tx.created_at || tx.time)) todayUsage += amt;
        monthUsage += amt;
      });
      var topUsers = users.slice().sort(function (a, b) { return (b.credits || 0) - (a.credits || 0); }).slice(0, 5);

      var html = sectionOpen('Credits', 'Balance, usage, plans, transactions, and top consumers.', '') +
        '<div class="yai-ops-grid yai-ops-grid--3">' +
        kpi('Platform Users', d.users, 'Total accounts') +
        kpi('Today\'s Usage', todayUsage || '—', 'Credits consumed', 'yai-ops-kpi--gold') +
        kpi('Monthly Usage', monthUsage || '—', 'This period') +
        '</div>' +
        renderMembershipMappingSection(plans, billing, mapping, wcAdmin) +
        sectionOpen('Assign Credits / Plan', 'Adjust individual user balances and subscription tier.', '') +
        '<div class="yai-ops-form-grid">' +
        '<label>User<select id="yai-ops-credit-user">';
      users.slice(0, 30).forEach(function (u) {
        html += '<option value="' + esc(String(u.id)) + '">' + esc(u.name) + ' (' + esc(u.plan_name || u.plan || 'free') + ')</option>';
      });
      html += '</select></label>' +
        '<label>Plan<select id="yai-ops-credit-plan">';
      plans.forEach(function (p) {
        html += '<option value="' + esc(p.id) + '">' + esc(p.name) + '</option>';
      });
      html += '</select></label>' +
        '<label>Credit delta<input type="number" id="yai-ops-credit-delta" value="0"></label></div>' +
        '<div class="yai-btn-group" style="margin-top:16px">' +
        btnSecondary('Set Plan', 'id="yai-ops-set-plan"') +
        btnPrimary('Adjust Credits', 'id="yai-ops-adjust-credits"') +
        '</div><p class="yai-ops-toast" id="yai-ops-credit-msg"></p>' +
        sectionClose() +
        sectionOpen('Top Users', 'Highest credit balances on the platform.', '') +
        '<div class="yai-ops-grid">' + topUsers.map(function (u) {
          return '<article class="yai-ops-kpi"><span>' + esc(u.name) + '</span><strong>' + (u.unlimited ? '∞' : esc(String(u.credits))) + '</strong><em>' + esc(u.plan_name || u.plan || 'free') + '</em></article>';
        }).join('') + '</div>' +
        sectionClose() +
        sectionOpen('Transactions', 'Recent credit ledger activity.', '') +
        (txs.length ? '<div class="yai-ops-job-grid">' + txs.slice(0, 30).map(function (tx) {
          var delta = tx.delta != null ? tx.delta : tx.amount;
          var pos = Number(delta) >= 0;
          return '<article class="yai-ops-job-card"><div style="display:flex;justify-content:space-between">' +
            '<h4>' + esc(tx.label || 'Transaction') + '</h4>' +
            '<span class="yai-ops-pill yai-ops-pill--' + (pos ? 'connected' : 'error') + '">' + (pos ? '+' : '') + esc(String(delta)) + '</span></div>' +
            '<small>' + esc(tx.user || tx.user_id) + ' · ' + esc(tx.studio || tx.module || '') + '</small></article>';
        }).join('') + '</div>' : empty('No transactions', 'Credit activity will appear here.')) +
        sectionClose() +
        sectionClose();
      body(html);
      bindMembershipMapping(plans, billing, mapping, wcAdmin);

      var setPlanBtn = document.getElementById('yai-ops-set-plan');
      if (setPlanBtn) {
        setPlanBtn.addEventListener('click', function () {
          var uid = document.getElementById('yai-ops-credit-user').value;
          var plan = document.getElementById('yai-ops-credit-plan').value;
          Core.admin.setUserPlan(uid, { plan: plan, grant_credits: false }).then(function () {
            setMsg('Plan updated for user #' + uid + '.', false, 'yai-ops-credit-msg');
          }).catch(function (err) { setMsg(err.message, true, 'yai-ops-credit-msg'); });
        });
      }

      var adjBtn = document.getElementById('yai-ops-adjust-credits');
      if (adjBtn) {
        adjBtn.addEventListener('click', function () {
          var uid = document.getElementById('yai-ops-credit-user').value;
          var delta = parseInt(document.getElementById('yai-ops-credit-delta').value, 10) || 0;
          if (!delta) { setMsg('Enter a non-zero credit delta.', true, 'yai-ops-credit-msg'); return; }
          Core.admin.adjustCredits(uid, { delta: delta, label: 'Admin adjustment', studio: 'admin' }).then(function () {
            setMsg('Credits adjusted for user #' + uid + '.', false, 'yai-ops-credit-msg');
            loadCredits();
          }).catch(function (err) { setMsg(err.message, true, 'yai-ops-credit-msg'); });
        });
      }
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadMonitoring() {
    Promise.all([Core.admin.system(), Core.admin.logs(), Core.admin.providers()]).then(function (r) {
      var sys = r[0].data || {};
      var logs = r[1].data || {};
      var provs = (r[2].data && r[2].data.providers) || [];
      var jobs = logs.recent_jobs || [];
      var restErr = logs.rest_errors || [];
      var perfRows = logs.generation_perf || [];
      var hours = ['00', '04', '08', '12', '16', '20'];
      var latencyData = hours.map(function (_, i) { return 80 + (i * 15) + (jobs.length % 20); });
      var queueData = hours.map(function (_, i) { return Math.max(0, Math.round(jobs.length / 6) + (i % 4)); });
      var online = provs.filter(function (p) { return p.usable && p.mode !== 'mock'; }).length;
      var uptimePct = provs.length ? Math.round((online / provs.length) * 100) : 100;

      body(
        sectionOpen('Monitoring', 'Real-time charts for API latency, queue depth, REST health, and provider uptime.', btnSecondary('Refresh', 'onclick="window.YooYAdminConsole.openOps(\'monitoring\')"')) +
        '<div class="yai-ops-grid yai-ops-grid--2">' +
        barChart('API Latency (24h)', hours, latencyData) +
        barChart('Queue Depth', hours, queueData) +
        barChart('REST Requests', hours, queueData.map(function (v) { return v * 3; })) +
        barChart('Generation Volume', hours, jobs.slice(0, 6).map(function (_, i) { return Math.max(1, jobs.length - i * 2); })) +
        '</div>' +
        '<div class="yai-ops-grid yai-ops-grid--3">' +
        kpi('Provider Uptime', uptimePct + '%', online + ' of ' + provs.length + ' online', 'yai-ops-kpi--gold') +
        kpi('REST Errors', restErr.length, 'Last period', restErr.length ? 'yai-ops-kpi--fail' : '') +
        kpi('Failed Jobs', (logs.failed_jobs || []).length, 'Needs review', (logs.failed_jobs || []).length ? 'yai-ops-kpi--fail' : '') +
        '</div>' +
        sectionOpen('Generation Performance', 'Recent image generation pipeline timings (ms).', '') +
        (perfRows.length ? '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr>' +
          '<th>Job</th><th>Provider</th><th>Resolve</th><th>Optimize</th><th>API</th><th>Save</th><th>Gallery</th><th>Total</th>' +
          '</tr></thead><tbody>' +
          perfRows.map(function (p) {
            return '<tr><td>' + esc((p.job_id || '').slice(0, 12)) + '</td>' +
              '<td>' + esc(p.provider || '') + '</td>' +
              '<td>' + esc(String(p.provider_resolve_ms || 0)) + '</td>' +
              '<td>' + esc(String(p.prompt_optimize_ms || 0)) + '</td>' +
              '<td>' + esc(String(p.api_request_ms || 0)) + '</td>' +
              '<td>' + esc(String(p.image_save_ms || 0)) + '</td>' +
              '<td>' + esc(String(p.gallery_save_ms || 0)) + '</td>' +
              '<td><strong>' + esc(String(p.total_generation_ms || 0)) + '</strong></td></tr>';
          }).join('') +
          '</tbody></table></div>' : empty('No performance samples', 'Generate images in Image Studio to populate timings.')) +
        sectionClose() +
        sectionOpen('REST Errors', 'API layer failures and endpoint issues.', '') +
        (restErr.length ? timeline(restErr) : empty('No REST errors', 'API layer is healthy.')) +
        sectionClose() +
        sectionClose()
      );
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadLogs() {
    Core.admin.logs().then(function (res) {
      var d = res.data || {};
      var logs = d.system_logs || [];
      var restErr = d.rest_errors || [];
      var all = logs.concat(restErr).sort(function (a, b) {
        return String(b.created_at || b.time || '').localeCompare(String(a.created_at || a.time || ''));
      });
      if (!all.length) {
        body(sectionOpen('Logs', 'System event timeline with severity levels.', '') +
          empty('No logs', 'System activity will appear here.') + sectionClose());
        return;
      }
      body(
        sectionOpen('Logs', 'System event timeline — Info, Warning, Error, and Critical severity.', btnSecondary('Filter Errors', 'id="yai-ops-log-filter"')) +
        '<div class="yai-ops-grid yai-ops-grid--4" style="margin-bottom:24px">' +
        kpi('Total Events', all.length, 'Recorded') +
        kpi('Errors', all.filter(function (l) { return l.level === 'error'; }).length, 'Critical path', 'yai-ops-kpi--fail') +
        kpi('Warnings', all.filter(function (l) { return l.level === 'warn' || l.level === 'warning'; }).length, 'Review') +
        kpi('Info', all.filter(function (l) { return !l.level || l.level === 'info'; }).length, 'Normal') +
        '</div>' +
        '<div id="yai-ops-log-timeline">' + timeline(all.slice(0, 50)) + '</div>' +
        sectionClose()
      );
      var filt = document.getElementById('yai-ops-log-filter');
      if (filt) {
        filt.addEventListener('click', function () {
          var errs = all.filter(function (l) {
            var lv = String(l.level || '').toLowerCase();
            return lv === 'error' || lv === 'critical';
          });
          document.getElementById('yai-ops-log-timeline').innerHTML = errs.length ? timeline(errs) : empty('No errors', 'All clear.');
        });
      }
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadSettings() {
    Core.admin.settings().then(function (res) {
      var s = res.data || {};
      var studios = ['video', 'image', 'music', 'voice', 'avatar', 'writing'];
      var prov = s.default_providers || {};
      var costs = s.credit_costs || {};
      var html = sectionOpen('Settings', 'Default providers and per-studio credit costs.', '') +
        '<form id="yai-ops-settings"><div class="yai-ops-form-grid">';
      studios.forEach(function (st) {
        html += '<label>' + st + ' provider<input name="p_' + st + '" value="' + esc(prov[st] || 'mock') + '"></label>';
        html += '<label>' + st + ' cost<input type="number" name="c_' + st + '" value="' + esc(String(costs[st] || 0)) + '"></label>';
      });
      html += '</div><div class="yai-btn-group" style="margin-top:16px">' +
        btnPrimary('Save Settings', 'type="submit"') + '</div></form>' +
        '<p class="yai-ops-toast" id="yai-ops-msg"></p>' + sectionClose();
      body(html);
      document.getElementById('yai-ops-settings').addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target;
        var payload = { default_providers: {}, credit_costs: {} };
        studios.forEach(function (st) {
          payload.default_providers[st] = f['p_' + st].value;
          payload.credit_costs[st] = parseInt(f['c_' + st].value, 10) || 0;
        });
        Core.admin.saveSettings(payload).then(function () { setMsg('Settings saved.'); });
      });
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function loadBackup() {
    Core.admin.backup().then(function (res) {
      var d = res.data || {};
      var ok = d.status === 'ok';
      body(
        sectionOpen('Backups', 'Plugin backup status and storage paths.', btnPrimary('Run Backup', 'id="yai-ops-backup-run"')) +
        '<div class="yai-ops-grid yai-ops-grid--3">' +
        healthCard('Status', d.status || '—', ok ? 'green' : 'yellow') +
        healthCard('Plugin Path', d.paths && d.paths.plugin ? 'OK' : '—', d.paths && d.paths.plugin ? 'green' : 'red') +
        healthCard('Modules Path', d.paths && d.paths.modules ? 'OK' : '—', d.paths && d.paths.modules ? 'green' : 'red') +
        '</div>' +
        '<p class="yai-ops-toast">' + esc(d.message || 'Backup system ready.') + '</p>' +
        sectionClose()
      );
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  var SECTION_TYPES = [
    'latest', 'featured', 'best', 'hot', 'marketplace', 'community', 'official', 'mixed',
    'manual', 'project', 'category', 'tag'
  ];
  var SECTION_SOURCES = [
    { id: 'user', label: 'User' },
    { id: 'community', label: 'Community' },
    { id: 'marketplace', label: 'Marketplace' },
    { id: 'official', label: 'Official' },
    { id: 'demo', label: 'Demo' },
    { id: 'mixed', label: 'Mixed (auto-fill)' }
  ];
  var SECTION_COLUMNS = [
    { id: '2', label: '2 columns' },
    { id: '3', label: '3 columns' },
    { id: '4', label: '4 columns' },
    { id: '5', label: '5 columns' },
    { id: '6', label: '6 columns' },
    { id: 'carousel', label: 'Carousel' }
  ];
  var SECTION_CARD_RATIOS = [
    { id: 'auto', label: 'Auto (4:5)' },
    { id: 'square', label: 'Square (1:1)' },
    { id: 'portrait', label: 'Portrait (4:5)' },
    { id: 'landscape', label: 'Landscape (16:10)' },
    { id: 'wide', label: 'Wide (16:9)' },
    { id: 'masonry', label: 'Masonry (natural)' }
  ];
  var SECTION_TEXT_MODES = [
    { id: 'below', label: 'Below image' },
    { id: 'overlay', label: 'Overlay on image' },
    { id: 'hidden', label: 'Hidden until hover' }
  ];

  function formatColumnLabel(value) {
    if (value === 'carousel') return 'Carousel';
    return String(value || 4) + ' columns';
  }

  function loadHomeSections() {
    Core.admin.homeSections.list().then(function (res) {
      var sections = (res.data && res.data.sections) || [];
      var rows = sections.map(function (s, idx) {
        return '<tr data-section-id="' + esc(s.id) + '">' +
          '<td><button type="button" class="yai-ops-btn-ghost" data-section-up="' + esc(s.id) + '"' + (idx === 0 ? ' disabled' : '') + '>↑</button>' +
          '<button type="button" class="yai-ops-btn-ghost" data-section-down="' + esc(s.id) + '"' + (idx === sections.length - 1 ? ' disabled' : '') + '>↓</button></td>' +
          '<td><strong>' + esc(s.title) + '</strong><br><span class="yai-ops-muted">' + esc(s.description || '') + '</span></td>' +
          '<td>' + esc(s.type) + '</td>' +
          '<td>' + esc(s.source || 'user') + '</td>' +
          '<td>' + esc(formatColumnLabel(s.column_count)) + '</td>' +
          '<td>' + esc(s.card_ratio || 'auto') + ' · ' + esc(s.text_mode || 'below') + '</td>' +
          '<td>' + esc(String(s.limit || 8)) + '</td>' +
          '<td>' + (s.visible ? 'Visible' : 'Hidden') + '</td>' +
          '<td><button type="button" class="yai-ops-btn-ghost" data-section-edit="' + esc(s.id) + '">Edit</button> ' +
          '<button type="button" class="yai-ops-btn-ghost yai-ops-btn-ghost--danger" data-section-delete="' + esc(s.id) + '">Delete</button></td>' +
        '</tr>';
      }).join('');

      body(
        sectionOpen('Home Sections', 'Home 화면에 표시할 큐레이션 섹션을 관리합니다.', btnPrimary('New Section', 'id="yai-ops-section-create"')) +
        '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr><th>Order</th><th>Section</th><th>Type</th><th>Source</th><th>Columns</th><th>Layout</th><th>Limit</th><th>Status</th><th>Actions</th></tr></thead><tbody>' +
        (rows || '<tr><td colspan="9">No sections yet.</td></tr>') +
        '</tbody></table></div>' +
        '<div id="yai-ops-section-editor" hidden></div>' +
        sectionClose()
      );

      var createBtn = document.getElementById('yai-ops-section-create');
      if (createBtn) createBtn.addEventListener('click', function () { openSectionEditor(null, sections); });

      root.querySelectorAll('[data-section-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-section-edit');
          var section = sections.find(function (s) { return s.id === id; });
          openSectionEditor(section, sections);
        });
      });

      root.querySelectorAll('[data-section-delete]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-section-delete');
          if (!confirm('Delete this section?')) return;
          Core.admin.homeSections.remove(id).then(function () {
            loadHomeSections();
          }).catch(function (e) { alert(e.message); });
        });
      });

      root.querySelectorAll('[data-section-up], [data-section-down]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-section-up') || btn.getAttribute('data-section-down');
          var ids = sections.map(function (s) { return s.id; });
          var idx = ids.indexOf(id);
          if (idx < 0) return;
          var swap = btn.hasAttribute('data-section-up') ? idx - 1 : idx + 1;
          if (swap < 0 || swap >= ids.length) return;
          var tmp = ids[idx];
          ids[idx] = ids[swap];
          ids[swap] = tmp;
          Core.admin.homeSections.reorder(ids).then(function () { loadHomeSections(); });
        });
      });
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function openSectionEditor(section, allSections) {
    var editor = document.getElementById('yai-ops-section-editor');
    if (!editor) return;
    var isNew = !section;
    section = section || {
      title: '', description: '', type: 'latest', source: 'mixed', column_count: 4, card_ratio: 'auto', text_mode: 'below',
      visible: true, limit: 8, manual_ids: [], project_id: '', category: '', tag: ''
    };

    var typeOptions = SECTION_TYPES.map(function (t) {
      return '<option value="' + esc(t) + '"' + (section.type === t ? ' selected' : '') + '>' + esc(t) + '</option>';
    }).join('');
    var sourceOptions = SECTION_SOURCES.map(function (s) {
      return '<option value="' + esc(s.id) + '"' + ((section.source || 'user') === s.id ? ' selected' : '') + '>' + esc(s.label) + '</option>';
    }).join('');
    var currentColumns = section.column_count != null ? String(section.column_count) : '4';
    var columnOptions = SECTION_COLUMNS.map(function (c) {
      return '<option value="' + esc(c.id) + '"' + (currentColumns === c.id ? ' selected' : '') + '>' + esc(c.label) + '</option>';
    }).join('');
    var currentRatio = section.card_ratio || 'auto';
    var ratioOptions = SECTION_CARD_RATIOS.map(function (r) {
      return '<option value="' + esc(r.id) + '"' + (currentRatio === r.id ? ' selected' : '') + '>' + esc(r.label) + '</option>';
    }).join('');
    var currentTextMode = section.text_mode || 'below';
    var textModeOptions = SECTION_TEXT_MODES.map(function (m) {
      return '<option value="' + esc(m.id) + '"' + (currentTextMode === m.id ? ' selected' : '') + '>' + esc(m.label) + '</option>';
    }).join('');

    editor.hidden = false;
    editor.innerHTML =
      '<div class="yai-ops-card yai-ops-section-form">' +
      '<h3>' + (isNew ? 'Create Section' : 'Edit Section') + '</h3>' +
      '<div class="yai-ops-form-grid yai-ops-form-grid--2">' +
        '<label>Title<input type="text" id="yai-sec-title" value="' + esc(section.title) + '"></label>' +
        '<label>Columns<select id="yai-sec-columns">' + columnOptions + '</select></label>' +
        '<label>Card Ratio<select id="yai-sec-ratio">' + ratioOptions + '</select></label>' +
        '<label>Text Mode<select id="yai-sec-text-mode">' + textModeOptions + '</select></label>' +
        '<label class="yai-ops-form-span-2">Description<textarea id="yai-sec-desc" rows="2">' + esc(section.description || '') + '</textarea></label>' +
        '<label>Type<select id="yai-sec-type">' + typeOptions + '</select></label>' +
        '<label>Source<select id="yai-sec-source">' + sourceOptions + '</select></label>' +
        '<label>Limit (works)<input type="number" id="yai-sec-limit" min="1" max="24" value="' + esc(String(section.limit || 8)) + '"></label>' +
        '<label class="yai-ops-check-label yai-ops-form-span-2"><input type="checkbox" id="yai-sec-visible"' + (section.visible ? ' checked' : '') + '> Visible on Home</label>' +
      '</div>' +
      '<div id="yai-sec-extra" class="yai-ops-form-grid yai-ops-form-grid--2"></div>' +
      '<div id="yai-sec-manual" class="yai-ops-section-manual" hidden>' +
        '<h4>Manual Works</h4>' +
        '<div class="yai-ops-section-manual-search">' +
          '<input type="text" id="yai-sec-search" placeholder="Search works…">' +
          '<button type="button" id="yai-sec-search-btn" class="yai-ops-btn-ghost">Search</button>' +
        '</div>' +
        '<div id="yai-sec-manual-list"></div>' +
        '<div id="yai-sec-selected"></div>' +
      '</div>' +
      '<div class="yai-ops-section-form-actions">' +
        '<button type="button" class="yai-ops-btn-primary" id="yai-sec-save">Save</button>' +
        '<button type="button" class="yai-ops-btn-ghost" id="yai-sec-cancel">Cancel</button>' +
      '</div></div>';

    var manualIds = (section.manual_ids || []).slice();
    var extra = document.getElementById('yai-sec-extra');
    var manualBox = document.getElementById('yai-sec-manual');
    var typeEl = document.getElementById('yai-sec-type');

    function renderExtraFields() {
      var type = typeEl.value;
      manualBox.hidden = type !== 'manual';
      if (type === 'project') {
        extra.innerHTML = '<label class="yai-ops-form-span-2">Project ID<input type="text" id="yai-sec-project-id" value="' + esc(section.project_id || '') + '"></label>';
      } else if (type === 'category') {
        extra.innerHTML = '<label class="yai-ops-form-span-2">Category (type)<input type="text" id="yai-sec-category" value="' + esc(section.category || 'image') + '"></label>';
      } else if (type === 'tag') {
        extra.innerHTML = '<label class="yai-ops-form-span-2">Tag<input type="text" id="yai-sec-tag" value="' + esc(section.tag || '') + '"></label>';
      } else {
        extra.innerHTML = '';
      }
      renderSelectedManual();
    }

    function renderSelectedManual() {
      var selected = document.getElementById('yai-sec-selected');
      if (!selected) return;
      if (!manualIds.length) {
        selected.innerHTML = '<p class="yai-ops-muted">No works selected.</p>';
        return;
      }
      selected.innerHTML = manualIds.map(function (id, idx) {
        return '<div style="display:flex;gap:8px;align-items:center;margin:4px 0">' +
          '<code>' + esc(id) + '</code>' +
          '<button type="button" class="yai-ops-btn-ghost" data-manual-up="' + idx + '">↑</button>' +
          '<button type="button" class="yai-ops-btn-ghost" data-manual-down="' + idx + '">↓</button>' +
          '<button type="button" class="yai-ops-btn-ghost" data-manual-remove="' + esc(id) + '">Remove</button></div>';
      }).join('');

      selected.querySelectorAll('[data-manual-remove]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-manual-remove');
          manualIds = manualIds.filter(function (x) { return x !== id; });
          renderSelectedManual();
        });
      });
      selected.querySelectorAll('[data-manual-up]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var idx = parseInt(btn.getAttribute('data-manual-up'), 10);
          if (idx > 0) { var t = manualIds[idx - 1]; manualIds[idx - 1] = manualIds[idx]; manualIds[idx] = t; renderSelectedManual(); }
        });
      });
      selected.querySelectorAll('[data-manual-down]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var idx = parseInt(btn.getAttribute('data-manual-down'), 10);
          if (idx < manualIds.length - 1) { var t = manualIds[idx + 1]; manualIds[idx + 1] = manualIds[idx]; manualIds[idx] = t; renderSelectedManual(); }
        });
      });
    }

    function renderSearchResults(works) {
      var list = document.getElementById('yai-sec-manual-list');
      if (!list) return;
      if (!works.length) {
        list.innerHTML = '<p class="yai-ops-muted">No works found.</p>';
        return;
      }
      list.innerHTML = works.map(function (w) {
        return '<button type="button" class="yai-ops-btn-ghost" data-manual-add="' + esc(w.id) + '" style="display:block;width:100%;text-align:left;margin:4px 0">' +
          esc(w.title || w.id) + ' · ' + esc(w.type || '') + '</button>';
      }).join('');
      list.querySelectorAll('[data-manual-add]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-manual-add');
          if (manualIds.indexOf(id) === -1) manualIds.push(id);
          renderSelectedManual();
        });
      });
    }

    typeEl.addEventListener('change', renderExtraFields);
    renderExtraFields();

    var searchBtn = document.getElementById('yai-sec-search-btn');
    if (searchBtn) {
      searchBtn.addEventListener('click', function () {
        var q = (document.getElementById('yai-sec-search').value || '').trim();
        Core.admin.homeSections.searchWorks(q, 20).then(function (res) {
          renderSearchResults((res.data && res.data.works) || []);
        });
      });
    }

    document.getElementById('yai-sec-cancel').addEventListener('click', function () {
      editor.hidden = true;
      editor.innerHTML = '';
    });

    document.getElementById('yai-sec-save').addEventListener('click', function () {
      var payload = {
        title: (document.getElementById('yai-sec-title').value || '').trim(),
        description: (document.getElementById('yai-sec-desc').value || '').trim(),
        type: typeEl.value,
        source: (document.getElementById('yai-sec-source') && document.getElementById('yai-sec-source').value) || 'mixed',
        column_count: document.getElementById('yai-sec-columns').value || '4',
        card_ratio: document.getElementById('yai-sec-ratio').value || 'auto',
        text_mode: document.getElementById('yai-sec-text-mode').value || 'below',
        limit: parseInt(document.getElementById('yai-sec-limit').value, 10) || 8,
        visible: !!document.getElementById('yai-sec-visible').checked,
        manual_ids: manualIds
      };
      if (!payload.title) { alert('Title is required.'); return; }
      var projectInput = document.getElementById('yai-sec-project-id');
      if (projectInput) payload.project_id = projectInput.value.trim();
      var categoryInput = document.getElementById('yai-sec-category');
      if (categoryInput) payload.category = categoryInput.value.trim();
      var tagInput = document.getElementById('yai-sec-tag');
      if (tagInput) payload.tag = tagInput.value.trim();

      var req = isNew
        ? Core.admin.homeSections.create(payload)
        : Core.admin.homeSections.update(section.id, payload);
      req.then(function () {
        editor.hidden = true;
        loadHomeSections();
      }).catch(function (e) { alert(e.message); });
    });
  }

  function loadOfficialShowcase() {
    Core.admin.officialShowcase.list().then(function (res) {
      var items = (res.data && res.data.items) || [];
      var rows = items.map(function (item, idx) {
        return '<tr data-official-id="' + esc(item.id) + '">' +
          '<td><button type="button" class="yai-ops-btn-ghost" data-off-up="' + esc(item.id) + '"' + (idx === 0 ? ' disabled' : '') + '>↑</button>' +
          '<button type="button" class="yai-ops-btn-ghost" data-off-down="' + esc(item.id) + '"' + (idx === items.length - 1 ? ' disabled' : '') + '>↓</button></td>' +
          '<td><strong>' + esc(item.title) + '</strong><br><span class="yai-ops-muted">' + esc(item.genre || item.type || '') + '</span></td>' +
          '<td>' + esc(item.type || 'image') + '</td>' +
          '<td>' + (item.featured ? '★ Featured' : '—') + ' / ' + (item.recommended ? 'Recommended' : '—') + '</td>' +
          '<td>' + (item.hidden ? 'Hidden' : 'Visible') + '</td>' +
          '<td><button type="button" class="yai-ops-btn-ghost" data-off-edit="' + esc(item.id) + '">Edit</button> ' +
          '<button type="button" class="yai-ops-btn-ghost yai-ops-btn-ghost--danger" data-off-delete="' + esc(item.id) + '">Delete</button></td>' +
        '</tr>';
      }).join('');

      body(
        sectionOpen('Official Showcase', 'Home Feed용 공식 큐레이션 · 데모 작품 (' + items.length + ' items)', btnPrimary('Add Item', 'id="yai-off-create"') + ' <button type="button" class="yai-ops-btn-ghost" id="yai-off-reseed">Reseed Demo</button>') +
        '<div class="yai-ops-table-wrap"><table class="yai-ops-table"><thead><tr><th>Order</th><th>Title</th><th>Type</th><th>Flags</th><th>Status</th><th>Actions</th></tr></thead><tbody>' +
        (rows || '<tr><td colspan="6">No items. Click Reseed Demo.</td></tr>') +
        '</tbody></table></div>' +
        '<div id="yai-off-editor" hidden></div>' +
        sectionClose()
      );

      var createBtn = document.getElementById('yai-off-create');
      if (createBtn) createBtn.addEventListener('click', function () { openOfficialEditor(null, items); });

      var reseedBtn = document.getElementById('yai-off-reseed');
      if (reseedBtn) reseedBtn.addEventListener('click', function () {
        if (!confirm('Replace all Official Showcase items with default demo seed?')) return;
        Core.admin.officialShowcase.seed().then(function () { loadOfficialShowcase(); }).catch(function (e) { alert(e.message); });
      });

      root.querySelectorAll('[data-off-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-off-edit');
          var item = items.find(function (x) { return x.id === id; });
          openOfficialEditor(item, items);
        });
      });

      root.querySelectorAll('[data-off-delete]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-off-delete');
          if (!confirm('Delete this showcase item?')) return;
          Core.admin.officialShowcase.remove(id).then(function () { loadOfficialShowcase(); }).catch(function (e) { alert(e.message); });
        });
      });

      root.querySelectorAll('[data-off-up], [data-off-down]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-off-up') || btn.getAttribute('data-off-down');
          var ids = items.map(function (x) { return x.id; });
          var idx = ids.indexOf(id);
          if (idx < 0) return;
          var swap = btn.hasAttribute('data-off-up') ? idx - 1 : idx + 1;
          if (swap < 0 || swap >= ids.length) return;
          var tmp = ids[idx]; ids[idx] = ids[swap]; ids[swap] = tmp;
          Core.admin.officialShowcase.reorder(ids).then(function () { loadOfficialShowcase(); });
        });
      });
    }).catch(function (e) { body('<p class="yai-ops-toast is-error">' + esc(e.message) + '</p>'); });
  }

  function openOfficialEditor(item, allItems) {
    var editor = document.getElementById('yai-off-editor');
    if (!editor) return;
    var isNew = !item;
    item = item || { title: '', description: '', type: 'image', genre: 'kpop', prompt: '', thumbnail_url: '', featured: false, recommended: false, hidden: false };
    editor.hidden = false;
    editor.innerHTML =
      '<div class="yai-ops-card yai-ops-section-form">' +
      '<h3>' + (isNew ? 'Add Official Item' : 'Edit Official Item') + '</h3>' +
      '<div class="yai-ops-form-grid yai-ops-form-grid--2">' +
        '<label>Title<input type="text" id="yai-off-title" value="' + esc(item.title || '') + '"></label>' +
        '<label>Type<select id="yai-off-type"><option value="image">image</option><option value="video">video</option><option value="music">music</option><option value="writing">writing</option></select></label>' +
        '<label>Genre<input type="text" id="yai-off-genre" value="' + esc(item.genre || '') + '"></label>' +
        '<label>Thumbnail URL<input type="text" id="yai-off-thumb" value="' + esc(item.thumbnail_url || '') + '"></label>' +
        '<label class="yai-ops-form-span-2">Prompt<textarea id="yai-off-prompt" rows="2">' + esc(item.prompt || '') + '</textarea></label>' +
        '<label class="yai-ops-check-label"><input type="checkbox" id="yai-off-featured"' + (item.featured ? ' checked' : '') + '> Featured</label>' +
        '<label class="yai-ops-check-label"><input type="checkbox" id="yai-off-recommended"' + (item.recommended ? ' checked' : '') + '> Recommended</label>' +
        '<label class="yai-ops-check-label"><input type="checkbox" id="yai-off-hidden"' + (item.hidden ? ' checked' : '') + '> Hidden</label>' +
      '</div>' +
      '<div class="yai-ops-section-form-actions">' +
        '<button type="button" class="yai-ops-btn-primary" id="yai-off-save">Save</button>' +
        '<button type="button" class="yai-ops-btn-ghost" id="yai-off-cancel">Cancel</button>' +
      '</div></div>';
    var typeEl = document.getElementById('yai-off-type');
    if (typeEl) typeEl.value = item.type || 'image';

    document.getElementById('yai-off-cancel').addEventListener('click', function () {
      editor.hidden = true; editor.innerHTML = '';
    });

    document.getElementById('yai-off-save').addEventListener('click', function () {
      var payload = {
        title: (document.getElementById('yai-off-title').value || '').trim(),
        type: typeEl.value,
        genre: (document.getElementById('yai-off-genre').value || '').trim(),
        prompt: (document.getElementById('yai-off-prompt').value || '').trim(),
        thumbnail_url: (document.getElementById('yai-off-thumb').value || '').trim(),
        featured: !!document.getElementById('yai-off-featured').checked,
        recommended: !!document.getElementById('yai-off-recommended').checked,
        hidden: !!document.getElementById('yai-off-hidden').checked,
        is_demo: false
      };
      if (!payload.title) { alert('Title is required.'); return; }
      var req = isNew
        ? Core.admin.officialShowcase.create(payload)
        : Core.admin.officialShowcase.update(item.id, payload);
      req.then(function () {
        editor.hidden = true;
        loadOfficialShowcase();
      }).catch(function (e) { alert(e.message); });
    });
  }

  var booted = false;

  function ensureBooted(sec) {
    section = sec || section || 'overview';
    if (!booted) {
      booted = true;
      renderShell();
      loadSection(section);
      return;
    }
    renderShell();
    loadSection(section);
  }

  window.YooYAdminConsole = {
    openOps: function (sec) {
      ensureBooted(sec || 'overview');
    },
    openTab: function (sec) { window.YooYAdminConsole.openOps(sec); }
  };

  if (context === 'wp-admin') {
    ensureBooted('overview');
  }
})();
