/**
 * Studio entry context from Home generate / remix.
 * Reuses sessionStorage keys already used by launchFromHome and Gallery regenerate.
 */
(function (global) {
  'use strict';

  var KEYS = {
    prompt: 'yoy_home_prompt',
    original: 'yoy_home_original_prompt',
    studio: 'yoy_home_studio',
    remix: 'yoy_home_remix',
    attachment: 'yoy_home_attachment',
    reference: 'yoy_reference_asset',
    regenerate: 'yoy_regenerate'
  };

  function readJson(key) {
    try {
      var raw = sessionStorage.getItem(key);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) {
      return null;
    }
  }

  function peek() {
    var prompt = '';
    var original = '';
    try {
      prompt = sessionStorage.getItem(KEYS.prompt) || '';
      original = sessionStorage.getItem(KEYS.original) || '';
    } catch (e1) { /* ignore */ }
    var remix = readJson(KEYS.remix);
    var attachment = readJson(KEYS.attachment);
    var reference = readJson(KEYS.reference);
    if (!reference && remix && remix.reference_assets && remix.reference_assets[0]) {
      reference = remix.reference_assets[0];
    }
    if (!reference && attachment && (attachment.url || attachment.preview)) {
      reference = {
        url: attachment.url || attachment.preview,
        title: attachment.title || attachment.name || '',
        gallery_id: attachment.gallery_id || '',
        source: attachment.source || 'home'
      };
    }
    if (!prompt && remix && remix.prompt) prompt = remix.prompt;
    if (!prompt && attachment && attachment.excerpt) prompt = attachment.excerpt;
    return {
      prompt: prompt,
      originalPrompt: original || prompt,
      remix: remix,
      attachment: attachment,
      reference: reference
    };
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function dismissBanner(el) {
    if (el && el.parentNode) el.parentNode.removeChild(el);
    try {
      sessionStorage.removeItem(KEYS.remix);
      sessionStorage.removeItem(KEYS.attachment);
    } catch (e) { /* ignore */ }
  }

  function renderBanner(container, ctx) {
    if (!container || !ctx) return;
    var remix = ctx.remix;
    var att = ctx.attachment;
    var ref = ctx.reference;
    if (!remix && !att && !ref) return;
    var existing = container.querySelector('[data-studio-handoff]');
    if (existing) existing.parentNode.removeChild(existing);

    var title = '첨부 자료';
    var sub = '';
    var thumb = '';
    if (remix) {
      title = '이 작품을 참고하여 새 작품을 만듭니다.';
      sub = remix.prompt ? String(remix.prompt).slice(0, 80) : (remix.type || '');
      thumb = remix.thumbnail_url || remix.preview_url || '';
    } else if (att && att.type === 'url') {
      title = '가져온 자료';
      sub = att.title || att.url || '';
    } else if (att) {
      title = att.type === 'image' ? '첨부 이미지' : '첨부 파일';
      sub = att.name || att.title || '';
      thumb = att.preview || att.url || '';
    } else if (ref) {
      title = '참고 작품';
      sub = ref.title || '';
      thumb = ref.url || '';
    }

    var bar = document.createElement('div');
    bar.className = 'yai-studio-handoff';
    bar.setAttribute('data-studio-handoff', '1');
    bar.innerHTML =
      (thumb ? '<span class="yai-studio-handoff__thumb"><img src="' + esc(thumb) + '" alt=""></span>' : '') +
      '<span class="yai-studio-handoff__copy"><strong>' + esc(title) + '</strong>' +
        (sub ? '<em>' + esc(sub) + '</em>' : '') + '</span>' +
      '<button type="button" class="yai-studio-handoff__close" data-handoff-dismiss aria-label="참고 제거">×</button>';
    var close = bar.querySelector('[data-handoff-dismiss]');
    if (close) {
      close.addEventListener('click', function (e) {
        e.preventDefault();
        dismissBanner(bar);
      });
    }
    container.insertBefore(bar, container.firstChild);
  }

  function consumePromptKeys() {
    try {
      sessionStorage.removeItem(KEYS.prompt);
      sessionStorage.removeItem(KEYS.original);
    } catch (e) { /* ignore */ }
  }

  function apply(studioRoute, container, onContext) {
    var ctx = peek();
    if (typeof onContext === 'function') onContext(ctx);
    if (ctx.reference && ctx.reference.url) {
      try {
        sessionStorage.setItem(KEYS.reference, JSON.stringify(ctx.reference));
      } catch (e2) { /* ignore */ }
    }
    renderBanner(container, ctx);
    return ctx;
  }

  global.YooYStudioHandoff = {
    peek: peek,
    apply: apply,
    consumePromptKeys: consumePromptKeys,
    renderBanner: renderBanner
  };
})(typeof window !== 'undefined' ? window : this);
