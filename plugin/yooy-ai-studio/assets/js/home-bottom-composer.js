/**
 * Home bottom creation composer — visibility, plus menu, auto-studio toggle.
 * Reuses #yai-home-prompt / #yai-home-create / #yai-home-coach bound in studio.js.
 */
(function (global) {
  'use strict';

  var studioAuto = true;

  function composerEl() {
    return document.getElementById('yai-home-bottom-composer');
  }

  function syncVisibility(routeName) {
    var el = composerEl();
    if (!el) return;
    var onHome = !routeName || routeName === 'home';
    if (!routeName) {
      var active = document.querySelector('.yai-view.is-active');
      onHome = !!(active && active.getAttribute('data-page') === 'home');
    }
    el.hidden = !onHome;
    if (!onHome) closePlusMenu();
  }

  function closePlusMenu() {
    var menu = document.getElementById('yai-home-composer-plus-menu');
    var btn = document.getElementById('yai-home-composer-plus');
    if (menu) menu.hidden = true;
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function toast(msg) {
    var host = document.getElementById('yai-main') || document.body;
    var old = host.querySelector('.yai-home-composer-toast');
    if (old) old.remove();
    var el = document.createElement('div');
    el.className = 'yai-nav-toast yai-home-composer-toast';
    el.setAttribute('role', 'status');
    el.textContent = msg;
    host.appendChild(el);
    setTimeout(function () { if (el.parentNode) el.remove(); }, 2600);
  }

  function autosizePrompt() {
    var ta = document.getElementById('yai-home-prompt');
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = Math.min(120, Math.max(44, ta.scrollHeight)) + 'px';
  }

  function bind() {
    var plus = document.getElementById('yai-home-composer-plus');
    var menu = document.getElementById('yai-home-composer-plus-menu');
    var autoBtn = document.getElementById('yai-home-studio-auto');
    var prompt = document.getElementById('yai-home-prompt');

    if (plus && plus.dataset.bound !== '1') {
      plus.dataset.bound = '1';
      plus.addEventListener('click', function (e) {
        e.preventDefault();
        if (!menu) return;
        var open = menu.hidden;
        menu.hidden = !open;
        plus.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    if (menu && menu.dataset.bound !== '1') {
      menu.dataset.bound = '1';
      menu.addEventListener('click', function (e) {
        var item = e.target.closest('[data-home-attach]');
        if (!item) return;
        e.preventDefault();
        closePlusMenu();
        toast('다음 단계에서 연결됩니다.');
      });
    }

    if (autoBtn && autoBtn.dataset.bound !== '1') {
      autoBtn.dataset.bound = '1';
      autoBtn.addEventListener('click', function () {
        studioAuto = !studioAuto;
        autoBtn.classList.toggle('is-on', studioAuto);
        autoBtn.setAttribute('aria-pressed', studioAuto ? 'true' : 'false');
        try {
          sessionStorage.setItem('yoy_home_studio_auto', studioAuto ? '1' : '0');
        } catch (err) { /* ignore */ }
      });
      try {
        studioAuto = sessionStorage.getItem('yoy_home_studio_auto') !== '0';
        autoBtn.classList.toggle('is-on', studioAuto);
        autoBtn.setAttribute('aria-pressed', studioAuto ? 'true' : 'false');
      } catch (e2) { /* ignore */ }
    }

    if (prompt && prompt.dataset.composerBound !== '1') {
      prompt.dataset.composerBound = '1';
      prompt.addEventListener('input', autosizePrompt);
      prompt.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          var create = document.getElementById('yai-home-create');
          if (create) create.click();
        }
      });
      autosizePrompt();
    }

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.yai-home-composer__plus-wrap')) closePlusMenu();
    });
  }

  function isStudioAuto() {
    return studioAuto;
  }

  global.YooYHomeBottomComposer = {
    sync: syncVisibility,
    bind: bind,
    isStudioAuto: isStudioAuto
  };

  function boot() {
    bind();
    syncVisibility();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(typeof window !== 'undefined' ? window : this);
