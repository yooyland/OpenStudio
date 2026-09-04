/**
 * Phase 8 — centralized Credits / Plan UX helpers.
 * Server (YooY_Credits_Service) remains authoritative.
 */
(function (global) {
  'use strict';

  var lastAccount = null;
  var lowWarned = false;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function fmt(n) {
    var x = Number(n);
    if (!isFinite(x)) return '0';
    try { return x.toLocaleString('ko-KR'); } catch (e) { return String(x); }
  }

  function isLoggedIn() {
    var S = global.YooYStudio || {};
    var C = global.YooYCore || {};
    return !!(S.loggedIn || (C.config && C.config.loggedIn));
  }

  function billingReady(billing) {
    billing = billing || (lastAccount && lastAccount.billing) || {};
    return !!(billing.payment_ready || billing.woocommerce_active);
  }

  function applyShell(acc) {
    if (!acc) return;
    lastAccount = acc;
    var bal = acc.unlimited ? '∞' : fmt(acc.remaining != null ? acc.remaining : acc.balance);
    var top = document.getElementById('yai-top-credits');
    if (top && isLoggedIn()) {
      top.textContent = bal + ' 크레딧';
    }
    if (global.YooYHomeDashboard && typeof global.YooYHomeDashboard.updateCredits === 'function') {
      global.YooYHomeDashboard.updateCredits(acc);
    }
    var profile = document.getElementById('yai-credits');
    if (profile) profile.textContent = '크레딧: ' + bal;

    var planBtn = document.getElementById('yai-pro-plan-btn');
    if (planBtn) {
      var pname = acc.plan_name || acc.tier || 'Free';
      planBtn.childNodes[0] && planBtn.childNodes[0].nodeType === 3
        ? (planBtn.childNodes[0].textContent = pname + ' 플랜 ')
        : null;
      // Keep chevron: rewrite text carefully
      var chev = planBtn.querySelector('[aria-hidden="true"]');
      planBtn.textContent = '';
      planBtn.appendChild(document.createTextNode(pname + ' 플랜 '));
      if (chev) planBtn.appendChild(chev);
      else {
        var span = document.createElement('span');
        span.setAttribute('aria-hidden', 'true');
        span.textContent = '▾';
        planBtn.appendChild(span);
      }
    }

    var balEl = document.getElementById('yai-plan-dropdown-balance');
    if (balEl) balEl.textContent = bal + ' 크레딧';

    maybeLowCredit(acc);
  }

  function maybeLowCredit(acc) {
    if (!acc || acc.unlimited || lowWarned) return;
    var bal = Number(acc.remaining != null ? acc.remaining : acc.balance);
    if (!isFinite(bal) || bal > 20 || bal <= 0) return;
    lowWarned = true;
    toast('잔여 크레딧이 얼마 남지 않았습니다.', false);
  }

  function toast(msg, isError) {
    if (global.YooYHomeBottomComposer && typeof global.YooYHomeBottomComposer.toast === 'function') {
      global.YooYHomeBottomComposer.toast(msg);
      return;
    }
    var existing = document.getElementById('yai-credits-toast');
    if (existing) existing.remove();
    var el = document.createElement('div');
    el.id = 'yai-credits-toast';
    el.className = 'yai-credits-toast' + (isError ? ' is-error' : '');
    el.setAttribute('role', 'status');
    el.textContent = msg;
    document.body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-visible'); });
    setTimeout(function () {
      el.classList.remove('is-visible');
      setTimeout(function () { if (el.parentNode) el.remove(); }, 280);
    }, 3200);
  }

  function refresh() {
    if (!isLoggedIn()) return Promise.resolve(null);
    var Core = global.YooYCore;
    if (!Core || !Core.credits || typeof Core.credits.overview !== 'function') {
      if (Core && Core.credits && typeof Core.credits.balance === 'function') {
        return Core.credits.balance().then(function (res) {
          var d = (res && res.data) || res || {};
          var acc = d.account || d;
          applyShell(acc);
          return acc;
        }).catch(function () { return null; });
      }
      return Promise.resolve(null);
    }
    return Core.credits.overview().then(function (res) {
      var d = (res && res.data) || {};
      var acc = d.account || {};
      if (d.billing) acc.billing = d.billing;
      applyShell(acc);
      try {
        document.dispatchEvent(new CustomEvent('yoy:credits:refreshed', { detail: { account: acc } }));
      } catch (e) { /* ignore */ }
      return acc;
    }).catch(function () { return null; });
  }

  function notifySpent(used, balance) {
    var u = Number(used);
    if (!isFinite(u) || u <= 0) {
      refresh();
      return;
    }
    var rem = balance != null ? fmt(balance) : null;
    toast(fmt(u) + ' 크레딧 사용' + (rem != null ? ' · 잔여 ' + rem : ''), false);
    refresh();
  }

  function closeInsufficient() {
    var el = document.getElementById('yai-credits-insufficient');
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }

  function showInsufficient(opts) {
    opts = opts || {};
    closeInsufficient();
    var required = opts.required != null ? Number(opts.required) : null;
    var balance = opts.balance != null ? Number(opts.balance) : (lastAccount ? Number(lastAccount.balance) : null);
    var billing = (lastAccount && lastAccount.billing) || {};
    var canCheckout = billingReady(billing);

    var overlay = document.createElement('div');
    overlay.id = 'yai-credits-insufficient';
    overlay.className = 'yai-credits-insufficient';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', 'yai-credits-insufficient-title');

    var context =
      (balance != null && isFinite(balance) ? '<p>현재 <strong>' + esc(fmt(balance)) + ' 크레딧</strong>' : '<p>') +
      (required != null && isFinite(required) ? ' · 필요 <strong>' + esc(fmt(required)) + ' 크레딧</strong>' : '') +
      '</p>';

    var primary = canCheckout
      ? '<button type="button" class="yai-btn yai-btn--gold" data-ci-action="plans">플랜 보기</button>'
      : '<button type="button" class="yai-btn yai-btn--gold" data-ci-action="plans">플랜 보기</button>';

    overlay.innerHTML =
      '<div class="yai-credits-insufficient__dialog">' +
        '<h3 id="yai-credits-insufficient-title">크레딧이 부족합니다.</h3>' +
        context +
        '<p class="yai-credits-insufficient__note">입력한 내용과 설정은 그대로 유지됩니다.</p>' +
        '<div class="yai-credits-insufficient__actions">' +
          primary +
          '<button type="button" class="yai-btn yai-btn--outline" data-ci-action="close">취소</button>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);
    var focusBtn = overlay.querySelector('[data-ci-action="plans"]') || overlay.querySelector('[data-ci-action="close"]');
    if (focusBtn) focusBtn.focus();

    function onKey(e) {
      if (e.key === 'Escape') {
        closeInsufficient();
        document.removeEventListener('keydown', onKey);
      }
    }
    document.addEventListener('keydown', onKey);

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        closeInsufficient();
        document.removeEventListener('keydown', onKey);
        return;
      }
      var btn = e.target.closest('[data-ci-action]');
      if (!btn) return;
      var act = btn.getAttribute('data-ci-action');
      closeInsufficient();
      document.removeEventListener('keydown', onKey);
      if (act === 'plans' && typeof global.YooYStudioRoute === 'function') {
        global.YooYStudioRoute('credits');
      }
    });
  }

  function isInsufficientError(err) {
    if (!err) return false;
    var code = err.code || (err.details && err.details.code) || (err.details && err.details.reason) || '';
    var msg = String(err.message || err || '');
    if (code === 'insufficient_credits' || code === 'billing_unavailable') return true;
    return /insufficient credits|not enough credits|크레딧이 부족/i.test(msg);
  }

  function handleGenerationError(err, fallbackBalance, fallbackEstimate) {
    if (!isInsufficientError(err)) return false;
    var d = err && err.details ? err.details : {};
    var required = (d.debug && d.debug.estimate) || fallbackEstimate;
    var balance = fallbackBalance;
    showInsufficient({ required: required, balance: balance });
    refresh();
    return true;
  }

  function estimateLabel(est, balance, unlimited) {
    if (unlimited) return '예상 — · 잔액 ∞';
    var e = Number(est) || 0;
    var b = balance != null ? Number(balance) : null;
    var parts = [];
    if (e > 0) parts.push('예상 ' + fmt(e) + ' 크레딧');
    if (b != null && isFinite(b)) parts.push('잔액 ' + fmt(b));
    return parts.join(' · ') || '크레딧';
  }

  function init() {
    document.addEventListener('yoy:credits:updated', function (ev) {
      var detail = (ev && ev.detail) || {};
      if (detail.account) applyShell(detail.account);
      else if (detail.balance != null || detail.used != null) {
        notifySpent(detail.used, detail.balance);
      } else {
        refresh();
      }
    });

    document.addEventListener('yoy:creation-success', function (ev) {
      var detail = (ev && ev.detail) || {};
      if (detail.creditsUsed != null || detail.balance != null) {
        notifySpent(detail.creditsUsed, detail.balance);
      } else {
        refresh();
      }
    });

    document.addEventListener('yoy:gallery:updated', function () {
      refresh();
    });

    if (isLoggedIn()) refresh();
  }

  global.YooYCreditsUI = {
    init: init,
    refresh: refresh,
    applyShell: applyShell,
    toast: toast,
    notifySpent: notifySpent,
    showInsufficient: showInsufficient,
    handleGenerationError: handleGenerationError,
    isInsufficientError: isInsufficientError,
    estimateLabel: estimateLabel,
    fmt: fmt,
    getAccount: function () { return lastAccount; }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(typeof window !== 'undefined' ? window : globalThis);
