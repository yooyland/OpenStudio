/**
 * Shared Create UX — recommendations + Prompt Coach.
 * Reuses AI Assistant REST. No auto-generate.
 */
(function (global) {
  'use strict';

  var Core = global.YooYCore;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function activeProjectId() {
    if (global.YooYActiveProject && global.YooYActiveProject.getId) {
      return global.YooYActiveProject.getId() || '';
    }
    return '';
  }

  function renderRecCards(el, cards, onPick) {
    if (!el) return;
    if (!cards || !cards.length) {
      el.innerHTML = '<p class="yai-muted">추천을 불러오는 중…</p>';
      return;
    }
    el.innerHTML = cards.map(function (c) {
      return '<button type="button" class="yai-create-ux__card" data-rec-id="' + esc(c.id) + '">' +
        '<span class="yai-create-ux__card-cat">' + esc(c.category || '추천') + '</span>' +
        '<strong>' + esc(c.title || '') + '</strong>' +
        '<span class="yai-create-ux__card-desc">' + esc(c.description || '') + '</span>' +
      '</button>';
    }).join('');

    el.onclick = function (e) {
      var btn = e.target.closest('[data-rec-id]');
      if (!btn) return;
      var id = btn.getAttribute('data-rec-id');
      var found = cards.find(function (c) { return c.id === id; });
      if (found && typeof onPick === 'function') onPick(found);
    };
  }

  function loadRecommendations(el, onPick, attempt) {
    if (!el) return Promise.resolve([]);
    attempt = attempt || 0;
    if (!Core || !Core.assistant) {
      if (attempt < 40) {
        return new Promise(function (resolve) {
          setTimeout(function () {
            resolve(loadRecommendations(el, onPick, attempt + 1));
          }, 100);
        });
      }
      el.innerHTML = '<p class="yai-muted">AI Assistant를 준비 중입니다. <button type="button" class="yai-text-btn" data-route="assistant">직접 열기</button></p>';
      return Promise.resolve([]);
    }
    var pid = activeProjectId();
    return Core.assistant.recommendations({ project_id: pid || undefined }).then(function (res) {
      var cards = (res.data && res.data.cards) || [];
      renderRecCards(el, cards, onPick);
      return cards;
    }).catch(function () {
      el.innerHTML = '<p class="yai-muted">추천을 불러오지 못했습니다.</p>';
      return [];
    });
  }

  function renderCoachPanel(panel, data, onApply) {
    if (!panel) return;
    if (!data || !data.composed) {
      panel.hidden = true;
      panel.innerHTML = '';
      return;
    }
    panel.hidden = false;
    var fields = data.fields || {};
    var fieldHtml = Object.keys(fields).map(function (k) {
      return '<li><strong>' + esc(k) + '</strong> ' + esc(fields[k]) + '</li>';
    }).join('');
    panel.innerHTML =
      '<div class="yai-create-ux__coach-head"><strong>AI Prompt Coach</strong><span>승인 후 적용 · 자동 생성 없음</span></div>' +
      '<p class="yai-create-ux__coach-text">' + esc(data.composed) + '</p>' +
      (fieldHtml ? '<ul class="yai-create-ux__coach-fields">' + fieldHtml + '</ul>' : '') +
      '<div class="yai-create-ux__coach-actions">' +
        '<button type="button" class="yai-btn yai-btn--gold" data-coach-apply>승인하고 적용</button>' +
        '<button type="button" class="yai-btn yai-btn--outline" data-coach-dismiss>닫기</button>' +
      '</div>';

    panel.onclick = function (e) {
      if (e.target.closest('[data-coach-dismiss]')) {
        panel.hidden = true;
        panel.innerHTML = '';
        return;
      }
      if (e.target.closest('[data-coach-apply]')) {
        if (typeof onApply === 'function') onApply(data.composed, data.studio || 'image');
        panel.hidden = true;
      }
    };
  }

  function composePrompt(seed, panel, onApply) {
    var prompt = String(seed || '').trim();
    if (!prompt) {
      if (panel) {
        panel.hidden = false;
        panel.innerHTML = '<p class="yai-muted">보완할 한 줄을 입력해 주세요.</p>';
      }
      return Promise.resolve(null);
    }
    if (!Core || !Core.assistant) return Promise.resolve(null);
    var pid = activeProjectId();
    return Core.assistant.compose({
      prompt: prompt,
      project_id: pid || undefined
    }).then(function (res) {
      var data = res.data || {};
      renderCoachPanel(panel, data, onApply);
      return data;
    }).catch(function () {
      if (panel) {
        panel.hidden = false;
        panel.innerHTML = '<p class="yai-muted">Prompt 보완에 실패했습니다.</p>';
      }
      return null;
    });
  }

  global.YooYCreateUX = {
    loadRecommendations: loadRecommendations,
    composePrompt: composePrompt,
    renderCoachPanel: renderCoachPanel
  };
})(typeof window !== 'undefined' ? window : this);
