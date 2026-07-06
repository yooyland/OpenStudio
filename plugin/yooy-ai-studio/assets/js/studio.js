(function () {
  'use strict';

  var Core = window.YooYCore;
  if (!Core) return;

  var currentRoute = 'home';
  var loaded = {};

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function $all(sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  }

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = str || '';
    return d.innerHTML;
  }

  function route(name) {
    currentRoute = name;

    $all('[data-page]').forEach(function (el) {
      if (name === 'home') {
        el.classList.toggle('is-hidden', el.dataset.page !== 'home' && el.classList.contains('yai-hero'));
        el.classList.toggle('is-active', false);
      } else {
        if (el.dataset.page === 'home') el.classList.add('is-hidden');
        else el.classList.toggle('is-active', el.dataset.page === name);
      }
    });

    $all('.yai-nav button, .yai-actions button').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.dataset.route === name);
    });

    hydratePage(name);
  }

  function hydratePage(name) {
    if (loaded[name]) return;
    loaded[name] = true;

    switch (name) {
      case 'home': loadShowcase(); break;
      case 'projects': loadProjects(); break;
      case 'prompt-library': loadPrompts(); break;
      case 'market': loadMarketplace(); break;
      case 'community': loadCommunity(); break;
      case 'works':
        loaded[name] = false;
        loadWorks();
        loaded[name] = true;
        break;
      case 'credits': loadCredits(); break;
      case 'settings': loadSettings(); break;
      case 'video':
        loadVideoStudio();
        break;
      case 'image':
        loadImageStudio();
        break;
      case 'music':
        loadMusicStudio();
        break;
      case 'voice':
        loadVoiceStudio();
        break;
      case 'avatar':
        loadAvatarStudio();
        break;
      case 'writing':
        loadGenerator(name);
        break;
    }
  }

  function loadShowcase() {
    var el = $('#yai-showcase');
    if (!el) return;

    Core.gallery.showcase().then(function (res) {
      var items = (res.data && res.data.items) || [];
      if (!items.length) {
        el.innerHTML = '<article><span>Community</span><h3>공개 작품이 없습니다</h3><p>Gallery에서 Community 공유하면 Showcase에 표시됩니다.</p></article>';
        return;
      }
      el.innerHTML = items.map(function (item) {
        return '<article><span>' + esc(item.type) + '</span><h3>' + esc(item.title) + '</h3><p>' + esc(item.prompt) + '</p></article>';
      }).join('');
    }).catch(function () {
      el.innerHTML = '<article><span>Error</span><h3>Showcase unavailable</h3></article>';
    });
  }

  function loadProjects() {
    var el = $('#yai-projects-list');
    if (!el) return;

    Core.projects.list().then(function (res) {
      var projects = (res.data && res.data.projects) || [];
      el.innerHTML = projects.map(function (p) {
        return '<div class="yai-card"><strong>' + esc(p.title) + '</strong><span>' + esc(p.type) + ' · ' + esc(String(p.items)) + ' items</span></div>';
      }).join('');
    });
  }

  function loadPrompts() {
    var el = $('#yai-prompts');
    if (!el) return;

    Promise.all([Core.prompts.list(), Core.prompts.presets()]).then(function (results) {
      var official = (results[0].data && results[0].data.official) || [];
      var presets = (results[1].data && results[1].data.presets) || [];

      var html = '<h3>한국 컨텍스트 프리셋</h3><div class="yai-tags">';
      html += presets.map(function (p) {
        return '<span class="yai-tag">' + esc(p.label) + '</span>';
      }).join('');
      html += '</div><h3>공식 프롬프트</h3>';
      html += official.map(function (p) {
        return '<div class="yai-card"><strong>' + esc(p.title) + '</strong><p>' + esc(p.prompt) + '</p></div>';
      }).join('');
      el.innerHTML = html;
    });
  }

  function loadMarketplace() {
    var el = $('#yai-marketplace');
    if (!el) return;

    Core.marketplace.items().then(function (res) {
      var items = (res.data && res.data.items) || [];
      el.innerHTML = items.map(function (item) {
        var price = item.price === 0 ? 'Free' : item.price.toLocaleString() + ' KRW';
        return '<div class="yai-card"><strong>' + esc(item.title) + '</strong><span>' + esc(item.creator) + ' · ' + esc(price) + ' · ★' + esc(String(item.rating)) + '</span></div>';
      }).join('');
    });
  }

  function loadCommunity() {
    var el = $('#yai-community');
    if (!el) return;

    Core.community.feed().then(function (res) {
      var feed = (res.data && res.data.feed) || [];
      el.innerHTML = feed.map(function (item) {
        return '<div class="yai-card"><strong>' + esc(item.title) + '</strong><span>' + esc(item.creator) + ' · ' + esc(item.type_label) + ' · ♥' + esc(String(item.likes)) + '</span></div>';
      }).join('');
    });
  }

  function loadWorks() {
    var el = $('#yai-works');
    if (!el) return;

    if (window.YooYGallery) {
      window.YooYGallery.mount(el);
      return;
    }

    Core.gallery.works().then(function (res) {
      var works = (res.data && res.data.works) || [];
      if (!works.length) {
        el.innerHTML = '<p class="yai-empty">아직 저장된 작업물이 없습니다. 생성 후 My Works에 추가하세요.</p>';
        return;
      }
      el.innerHTML = works.map(function (w) {
        return '<div class="yai-card"><strong>' + esc(w.title || 'Work') + '</strong></div>';
      }).join('');
    });
  }

  function loadCredits() {
    var el = $('#yai-credits-panel');
    if (!el) return;

    Promise.all([Core.credits.balance(), Core.credits.plans()]).then(function (results) {
      var balance = results[0].data || {};
      var plans = (results[1].data && results[1].data.plans) || [];

      var html = '<div class="yai-credits-hero"><strong>' + (balance.unlimited ? '∞' : balance.balance) + '</strong> Credits</div>';
      html += '<div class="yai-plan-grid">';
      html += plans.map(function (plan) {
        return '<div class="yai-card"><strong>' + esc(plan.name) + '</strong><span>' + esc(String(plan.credits)) + ' credits · ' + esc(plan.price_krw === 0 ? 'Free' : plan.price_krw.toLocaleString() + ' KRW') + '</span></div>';
      }).join('');
      html += '</div>';
      el.innerHTML = html;
    });
  }

  function loadSettings() {
    var el = $('#yai-settings');
    if (!el) return;

    Core.settings.get().then(function (res) {
      var s = (res.data && res.data.settings) || {};
      el.innerHTML =
        '<div class="yai-card"><strong>Korean Context</strong><span>' + (s.korean_context ? 'Enabled' : 'Disabled') + '</span></div>' +
        '<div class="yai-card"><strong>Default Provider</strong><span>' + esc(s.default_provider) + '</span></div>' +
        '<div class="yai-card"><strong>Auto Save</strong><span>' + (s.auto_save ? 'On' : 'Off') + '</span></div>' +
        '<div class="yai-card"><strong>Quality</strong><span>' + esc(s.quality) + '</span></div>';
    });
  }

  function loadVoiceStudio() {
    var el = $('#yai-voice-studio');
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';
    if (window.YooYVoiceStudio) {
      window.YooYVoiceStudio.mount(el);
    } else {
      el.innerHTML = '<p class="yai-empty">Voice Studio module loading...</p>';
    }
  }

  function loadAvatarStudio() {
    var el = $('#yai-avatar-studio');
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';
    if (window.YooYAvatarStudio) {
      window.YooYAvatarStudio.mount(el);
    } else {
      el.innerHTML = '<p class="yai-empty">Avatar Studio module loading...</p>';
    }
  }

  function loadMusicStudio() {
    var el = $('#yai-music-studio');
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';
    if (window.YooYMusicStudio) {
      window.YooYMusicStudio.mount(el);
    } else {
      el.innerHTML = '<p class="yai-empty">Music Studio module loading...</p>';
    }
  }

  function loadImageStudio() {
    var el = $('#yai-image-studio');
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';
    if (window.YooYImageStudio) {
      window.YooYImageStudio.mount(el);
    } else {
      el.innerHTML = '<p class="yai-empty">Image Studio module loading...</p>';
    }
  }

  function loadVideoStudio() {
    var el = $('#yai-video-studio');
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';
    if (window.YooYVideoStudio) {
      window.YooYVideoStudio.mount(el);
    } else {
      el.innerHTML = '<p class="yai-empty">Video Studio module loading...</p>';
    }
  }

  function loadGenerator(type) {
    var el = $('#yai-gen-' + type);
    if (!el || el.dataset.ready) return;
    el.dataset.ready = '1';

    el.innerHTML =
      '<textarea class="yai-prompt-input" placeholder="' + esc(type) + ' 프롬프트를 입력하세요."></textarea>' +
      '<button class="yai-generate-btn" type="button">Generate</button>' +
      '<div class="yai-result"></div>';

    var btn = el.querySelector('.yai-generate-btn');
    var input = el.querySelector('.yai-prompt-input');
    var result = el.querySelector('.yai-result');

    btn.addEventListener('click', function () {
      var prompt = input.value.trim();
      if (!prompt) return;

      btn.disabled = true;
      btn.textContent = 'Generating...';
      result.innerHTML = '';

      Core.router.generate({ type: type, prompt: prompt, provider: 'mock' }).then(function (res) {
        var data = res.data || {};
        result.innerHTML =
          '<div class="yai-card"><strong>Job: ' + esc(data.job_id) + '</strong>' +
          '<p>Provider: ' + esc(data.provider) + ' · Credits: ' + esc(String(data.credits_used)) + '</p>' +
          (data.output && data.output.url ? '<img src="' + esc(data.output.url) + '" alt="result" class="yai-result-img">' : '') +
          '</div>';
      }).catch(function (err) {
        result.innerHTML = '<p class="yai-error">' + esc(err.message) + '</p>';
      }).finally(function () {
        btn.disabled = false;
        btn.textContent = 'Generate';
      });
    });
  }

  function loadProfile() {
    if (!Core.config.loggedIn) return;

    Core.credits.balance().then(function (res) {
      var data = res.data || {};
      var el = $('#yai-credits');
      if (el) {
        el.textContent = data.unlimited ? 'Credits: ∞ Admin' : 'Credits: ' + data.balance;
      }
    }).catch(function () {});
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-route]');
    if (!btn) return;
    e.preventDefault();
    route(btn.dataset.route);
  });

  loadShowcase();
  loadProfile();

  window.YooYStudioRoute = route;
})();
