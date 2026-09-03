/**
 * Shared Studio simple / Advanced fold helper.
 * localStorage only — no new settings backend.
 */
(function (global) {
  'use strict';

  var PREFIX = 'yoy_studio_adv_open_';

  function isOpen(studioId) {
    try {
      return global.localStorage.getItem(PREFIX + studioId) === '1';
    } catch (e) {
      return false;
    }
  }

  function setOpen(studioId, open) {
    try {
      global.localStorage.setItem(PREFIX + studioId, open ? '1' : '0');
    } catch (e) { /* ignore */ }
  }

  function summaryLabel(open) {
    return open ? '고급 설정 ▴' : '고급 설정 ▾';
  }

  function detailsHtml(studioId, innerHtml) {
    var open = isOpen(studioId);
    return '<details class="yai-studio-adv" data-studio-adv="' + studioId + '"' + (open ? ' open' : '') + '>' +
      '<summary class="yai-studio-adv__summary">' + summaryLabel(open) + '</summary>' +
      '<div class="yai-studio-adv__body">' + (innerHtml || '') + '</div>' +
      '</details>';
  }

  function bind(root) {
    if (!root) return;
    var nodes = root.querySelectorAll('[data-studio-adv]');
    for (var i = 0; i < nodes.length; i++) {
      (function (el) {
        if (el.dataset.yaiAdvBound === '1') return;
        el.dataset.yaiAdvBound = '1';
        el.addEventListener('toggle', function () {
          var id = el.getAttribute('data-studio-adv') || 'studio';
          setOpen(id, el.open);
          var sum = el.querySelector('.yai-studio-adv__summary');
          if (sum) sum.textContent = summaryLabel(el.open);
        });
      })(nodes[i]);
    }
  }

  function headerHtml(title, desc) {
    return '<div class="yai-studio-head">' +
      '<h2 class="yai-studio-head__title">' + String(title || '') + '</h2>' +
      (desc ? '<p class="yai-studio-head__desc">' + String(desc || '') + '</p>' : '') +
      '</div>';
  }

  function providerOptionLabel(id, name) {
    var pid = String(id || '');
    if (!pid || pid === 'auto') return 'YooY 추천';
    return String(name || id);
  }

  global.YooYStudioSimpleMode = {
    isOpen: isOpen,
    setOpen: setOpen,
    detailsHtml: detailsHtml,
    bind: bind,
    headerHtml: headerHtml,
    providerOptionLabel: providerOptionLabel,
    summaryLabel: summaryLabel
  };
})(typeof window !== 'undefined' ? window : this);
