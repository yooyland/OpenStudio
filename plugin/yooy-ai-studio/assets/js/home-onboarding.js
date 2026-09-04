/**
 * Phase 7 — lightweight first-use onboarding (Home + success + intros).
 * No multi-step wizard. Guest never sees authenticated onboarding.
 */
(function (global) {
  'use strict';

  var state = {
    onboarding_seen: false,
    first_creation_done: false,
    gallery_intro_seen: false,
    project_intro_seen: false
  };
  var enabled = false;
  var starterCredits = { available: false, amount: 0 };
  var prompts = [];
  var successShown = false;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function isLoggedIn() {
    var S = global.YooYStudio || {};
    return !!S.loggedIn;
  }

  function loadFromLocalize() {
    var S = global.YooYStudio || {};
    var ob = S.onboarding || {};
    enabled = !!ob.enabled && isLoggedIn();
    if (ob.state && typeof ob.state === 'object') {
      state.onboarding_seen = !!ob.state.onboarding_seen;
      state.first_creation_done = !!ob.state.first_creation_done;
      state.gallery_intro_seen = !!ob.state.gallery_intro_seen;
      state.project_intro_seen = !!ob.state.project_intro_seen;
    }
    starterCredits = ob.starter_credits || starterCredits;
    prompts = Array.isArray(ob.prompts) ? ob.prompts : [];
  }

  function syncLocalize() {
    var S = global.YooYStudio || {};
    if (!S.onboarding) S.onboarding = {};
    S.onboarding.enabled = enabled;
    S.onboarding.state = {
      onboarding_seen: state.onboarding_seen,
      first_creation_done: state.first_creation_done,
      gallery_intro_seen: state.gallery_intro_seen,
      project_intro_seen: state.project_intro_seen
    };
  }

  function onboardingUrl() {
    var S = global.YooYStudio || {};
    var path = '/core/onboarding';
    if (S.restRouteUrl) {
      return String(S.restRouteUrl).replace(/\/?$/, '') + path;
    }
    return String(S.restUrl || '').replace(/\/?$/, '') + path;
  }

  function patchFlags(flags) {
    if (!enabled) return Promise.resolve();
    Object.keys(flags || {}).forEach(function (k) {
      state[k] = !!flags[k];
    });
    syncLocalize();
    var S = global.YooYStudio || {};
    return fetch(onboardingUrl(), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': S.nonce || ''
      },
      body: JSON.stringify(flags)
    }).then(function (r) {
      return r.json().catch(function () { return {}; });
    }).then(function (json) {
      if (json && json.data && json.data.state) {
        state.onboarding_seen = !!json.data.state.onboarding_seen;
        state.first_creation_done = !!json.data.state.first_creation_done;
        state.gallery_intro_seen = !!json.data.state.gallery_intro_seen;
        state.project_intro_seen = !!json.data.state.project_intro_seen;
        syncLocalize();
      }
    }).catch(function () { /* offline dismiss still local */ });
  }

  function fillComposer(text) {
    var ta = document.getElementById('yai-home-prompt');
    if (!ta) return;
    ta.value = String(text || '');
    try {
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    } catch (e) { /* ignore */ }
    ta.focus();
    var composer = document.getElementById('yai-home-bottom-composer');
    if (composer) {
      try { composer.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e2) { /* ignore */ }
    }
    if (global.YooYHomeBottomComposer && typeof global.YooYHomeBottomComposer.setPrompt === 'function') {
      global.YooYHomeBottomComposer.setPrompt(text);
    }
  }

  function scrollToStudioRecos() {
    var el = document.querySelector('.yai-hd-studio-recos');
    if (el) {
      try { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) { /* ignore */ }
    }
  }

  function focusComposer() {
    fillComposer('');
    var ta = document.getElementById('yai-home-prompt');
    if (ta) ta.focus();
  }

  function dismissHomePanel() {
    patchFlags({ onboarding_seen: true });
    var host = document.getElementById('yai-home-onboarding');
    if (host) {
      host.hidden = true;
      host.innerHTML = '';
    }
    document.body.classList.remove('yai-first-use');
  }

  function creditsLine() {
    if (!starterCredits.available || !starterCredits.amount) {
      return '<p class="yai-ob-panel__credits">작업할 때마다 필요한 만큼 크레딧이 사용됩니다.</p>';
    }
    return '<p class="yai-ob-panel__credits">첫 작품을 만들 수 있는 크레딧이 준비되어 있어요. ' +
      '<span class="yai-ob-panel__credits-note">작업할 때마다 필요한 만큼 크레딧이 사용됩니다.</span></p>';
  }

  function promptsHtml() {
    if (!prompts.length) return '';
    return '<div class="yai-ob-prompts" role="group" aria-label="시작 예시">' +
      prompts.map(function (p, i) {
        return '<button type="button" class="yai-ob-prompt" data-ob-prompt="' + i + '">' +
          esc(p.label || p.prompt) + '</button>';
      }).join('') +
      '</div>';
  }

  function renderHomePanel() {
    if (!enabled || state.onboarding_seen) return;
    var host = document.getElementById('yai-home-onboarding');
    if (!host) return;

    host.hidden = false;
    host.innerHTML =
      '<aside class="yai-ob-panel" role="region" aria-labelledby="yai-ob-title">' +
        '<div class="yai-ob-panel__main">' +
          '<h2 id="yai-ob-title">첫 작품을 만들어볼까요?</h2>' +
          '<p class="yai-ob-panel__lead">아래에서 원하는 Studio를 고르거나,<br>하단에 만들고 싶은 것을 입력해보세요.</p>' +
          creditsLine() +
          promptsHtml() +
          '<div class="yai-ob-panel__actions">' +
            '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-ob-action="studios">Studio 선택하기</button>' +
            '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ob-action="compose">직접 입력하기</button>' +
          '</div>' +
        '</div>' +
        '<button type="button" class="yai-ob-panel__dismiss" data-ob-action="dismiss" aria-label="안내 닫기">닫기</button>' +
      '</aside>';

    document.body.classList.add('yai-first-use');

    host.querySelectorAll('[data-ob-action]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var act = btn.getAttribute('data-ob-action');
        if (act === 'dismiss') {
          dismissHomePanel();
          return;
        }
        if (act === 'studios') {
          scrollToStudioRecos();
          return;
        }
        if (act === 'compose') {
          focusComposer();
        }
      });
    });

    host.querySelectorAll('[data-ob-prompt]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var idx = parseInt(btn.getAttribute('data-ob-prompt'), 10);
        var item = prompts[idx];
        if (!item) return;
        fillComposer(item.prompt || item.label || '');
      });
    });
  }

  function removeSuccessBanner() {
    var el = document.getElementById('yai-ob-success');
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }

  function routeTo(name) {
    if (typeof global.YooYStudioRoute === 'function') {
      global.YooYStudioRoute(name);
      return;
    }
    try {
      document.dispatchEvent(new CustomEvent('yoy:route', { detail: { route: name } }));
    } catch (e) { /* ignore */ }
  }

  function notifyFirstSuccess(opts) {
    opts = opts || {};
    if (!enabled || state.first_creation_done || successShown) return;
    successShown = true;
    patchFlags({ first_creation_done: true, onboarding_seen: true });
    dismissHomePanel();
    removeSuccessBanner();

    var banner = document.createElement('div');
    banner.id = 'yai-ob-success';
    banner.className = 'yai-ob-success';
    banner.setAttribute('role', 'status');
    banner.innerHTML =
      '<div class="yai-ob-success__inner">' +
        '<p class="yai-ob-success__title">첫 작품이 완성됐어요 🎉</p>' +
        '<div class="yai-ob-success__actions">' +
          '<button type="button" class="yai-btn yai-btn--gold yai-btn--sm" data-ob-next="gallery">Gallery에서 보기</button>' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ob-next="again">비슷하게 하나 더 만들기</button>' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-ob-next="project">프로젝트에 추가</button>' +
        '</div>' +
        '<button type="button" class="yai-ob-success__close" data-ob-next="close" aria-label="닫기">×</button>' +
      '</div>';
    document.body.appendChild(banner);

    banner.querySelectorAll('[data-ob-next]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var next = btn.getAttribute('data-ob-next');
        if (next === 'gallery') {
          routeTo('gallery');
        } else if (next === 'again') {
          /* stay on current studio — reuse if available */
          var reuse = document.querySelector('[data-yis-action="reuse"]');
          if (reuse) reuse.click();
        } else if (next === 'project') {
          var proj = document.querySelector('[data-yis-action="project"]');
          if (proj) proj.click();
          else routeTo('projects');
        }
        removeSuccessBanner();
      });
    });
  }

  function maybeShowGalleryIntro(root) {
    if (!enabled || state.gallery_intro_seen || !root) return;
    if (root.querySelector('.yai-ob-callout--gallery')) return;
    var mount = root.querySelector('.ygl-root') || root;
    var box = document.createElement('div');
    box.className = 'yai-ob-callout yai-ob-callout--gallery';
    box.setAttribute('role', 'note');
    box.innerHTML =
      '<p>만든 작품은 Gallery에 자동으로 모입니다.</p>' +
      '<button type="button" class="yai-ob-callout__dismiss" aria-label="안내 닫기">확인</button>';
    mount.insertBefore(box, mount.firstChild);
    box.querySelector('.yai-ob-callout__dismiss').addEventListener('click', function () {
      patchFlags({ gallery_intro_seen: true });
      if (box.parentNode) box.parentNode.removeChild(box);
    });
  }

  function maybeShowProjectIntro(root) {
    if (!enabled || state.project_intro_seen || !root) return;
    if (root.querySelector('.yai-ob-callout--project')) return;
    var box = document.createElement('div');
    box.className = 'yai-ob-callout yai-ob-callout--project';
    box.setAttribute('role', 'note');
    box.innerHTML =
      '<p>관련 작품을 하나의 프로젝트로 묶어 관리할 수 있어요.</p>' +
      '<button type="button" class="yai-ob-callout__dismiss" aria-label="안내 닫기">확인</button>';
    root.insertBefore(box, root.firstChild);
    box.querySelector('.yai-ob-callout__dismiss').addEventListener('click', function () {
      patchFlags({ project_intro_seen: true });
      if (box.parentNode) box.parentNode.removeChild(box);
    });
  }

  function init() {
    loadFromLocalize();
    if (!enabled) return;
    renderHomePanel();
    document.addEventListener('yoy:creation-success', function (ev) {
      notifyFirstSuccess((ev && ev.detail) || {});
    });
  }

  global.YooYOnboarding = {
    init: init,
    notifyFirstSuccess: notifyFirstSuccess,
    maybeShowGalleryIntro: maybeShowGalleryIntro,
    maybeShowProjectIntro: maybeShowProjectIntro,
    fillComposer: fillComposer,
    getState: function () { return Object.assign({}, state); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : globalThis);
