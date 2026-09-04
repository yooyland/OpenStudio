/**
 * Phase 10 — MY account hub (profile / plan / credits / settings / help / account).
 * Reuses user-profile + settings + credits REST. No duplicate stores.
 */
(function (global) {
  'use strict';

  var SECTIONS = [
    { id: 'profile', label: '프로필' },
    { id: 'plan', label: '플랜 및 구독' },
    { id: 'credits', label: '크레딧' },
    { id: 'settings', label: '설정' },
    { id: 'help', label: '도움말' },
    { id: 'account', label: '계정' }
  ];

  var state = {
    section: 'profile',
    profile: null,
    settings: null,
    credits: null,
    editingProfile: false,
    busy: false
  };

  function Core() {
    return global.YooYCore || {};
  }

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

  function toast(msg, isErr) {
    if (global.YooYStudioToast) {
      global.YooYStudioToast(msg, !!isErr);
      return;
    }
    if (global.console) global.console.log('[MY]', msg);
  }

  function routeTo(name) {
    if (global.YooYStudioRoute) global.YooYStudioRoute(name);
  }

  function initialsFrom(name) {
    var src = String(name || 'U').trim();
    if (!src) return 'U';
    var parts = src.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) {
      return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }
    return src.charAt(0).toUpperCase();
  }

  function avatarHtml(profile, sizeClass) {
    var name = (profile && profile.display_name) || 'User';
    var alt = esc(name + ' 프로필');
    var ini = esc(initialsFrom(name));
    var url = profile && profile.avatar ? String(profile.avatar) : '';
    var cls = 'yai-my-avatar' + (sizeClass ? ' ' + sizeClass : '');
    if (url && /^https?:/i.test(url)) {
      return '<span class="' + cls + '" aria-hidden="true">' +
        '<img class="yai-my-avatar__img" src="' + esc(url) + '" alt="' + alt + '" width="64" height="64" decoding="async" data-yai-my-avatar="1">' +
        '<span class="yai-my-avatar__ini" hidden>' + ini + '</span>' +
        '</span>';
    }
    return '<span class="' + cls + '" aria-hidden="true"><span class="yai-my-avatar__ini">' + ini + '</span></span>';
  }

  function bindAvatarFallbacks(root) {
    if (!root) return;
    root.querySelectorAll('[data-yai-my-avatar]').forEach(function (img) {
      var ini = img.parentNode && img.parentNode.querySelector('.yai-my-avatar__ini');
      function fail() {
        if (!img.parentNode) return;
        img.parentNode.removeChild(img);
        if (ini) ini.hidden = false;
      }
      img.addEventListener('error', fail);
      if (img.complete && img.naturalWidth === 0) fail();
    });
  }

  function mountEl() {
    return document.getElementById('yai-my-account');
  }

  function setSection(id) {
    var ok = false;
    SECTIONS.forEach(function (s) { if (s.id === id) ok = true; });
    state.section = ok ? id : 'profile';
    try { sessionStorage.setItem('yoy_my_section', state.section); } catch (e) { /* ignore */ }
  }

  function readPendingSection() {
    var s = '';
    try {
      s = sessionStorage.getItem('yoy_my_section') || '';
      sessionStorage.removeItem('yoy_my_section');
    } catch (e) { s = ''; }
    if (s) setSection(s);
  }

  function summaryCards(profile, credits) {
    var plan = (credits && (credits.plan_name || credits.tier || credits.plan)) || 'Free';
    var bal = credits && credits.unlimited ? '∞' : fmt(credits && (credits.remaining != null ? credits.remaining : credits.balance));
    var status = profile && profile.role === 'admin' ? '관리자' : '정상';
    return '' +
      '<div class="yai-my-summary" role="group" aria-label="계정 요약">' +
        '<div class="yai-my-summary__item"><span>현재 플랜</span><strong>' + esc(plan) + '</strong></div>' +
        '<div class="yai-my-summary__item"><span>크레딧</span><strong>' + esc(String(bal)) + '</strong></div>' +
        '<div class="yai-my-summary__item"><span>계정 상태</span><strong>' + esc(status) + '</strong></div>' +
      '</div>';
  }

  function navHtml() {
    return '<nav class="yai-my-nav" aria-label="MY 메뉴">' +
      SECTIONS.map(function (s) {
        return '<button type="button" class="yai-my-nav__btn' + (state.section === s.id ? ' is-active' : '') +
          '" data-my-nav="' + s.id + '" aria-current="' + (state.section === s.id ? 'page' : 'false') + '">' +
          esc(s.label) + '</button>';
      }).join('') +
      '</nav>';
  }

  function heroHtml(profile, credits) {
    var plan = (credits && (credits.plan_name || credits.tier || credits.plan)) || 'Free';
    return '' +
      '<header class="yai-my-hero">' +
        avatarHtml(profile, 'yai-my-avatar--lg') +
        '<div class="yai-my-hero__meta">' +
          '<h1 class="yai-my-hero__name">' + esc((profile && profile.display_name) || 'User') + '</h1>' +
          '<p class="yai-my-hero__email">' + esc((profile && profile.email) || '') + '</p>' +
          '<p class="yai-my-hero__plan">' + esc(plan) + ' 플랜</p>' +
        '</div>' +
      '</header>';
  }

  function profileSection(profile) {
    if (state.editingProfile) {
      return '' +
        '<section class="yai-my-panel" aria-labelledby="yai-my-profile-title">' +
          '<h2 id="yai-my-profile-title">프로필</h2>' +
          '<p class="yai-my-lead">표시 이름과 소개를 수정할 수 있습니다. 이메일은 변경할 수 없습니다.</p>' +
          '<form class="yai-my-form" id="yai-my-profile-form">' +
            '<div class="yai-my-field">' +
              '<label for="yai-my-display-name">표시 이름</label>' +
              '<input id="yai-my-display-name" name="display_name" type="text" maxlength="60" required value="' + esc(profile.display_name || '') + '">' +
              '<p class="yai-field-error" id="yai-my-profile-error" hidden></p>' +
            '</div>' +
            '<div class="yai-my-field">' +
              '<label for="yai-my-bio">소개</label>' +
              '<textarea id="yai-my-bio" name="bio" rows="3" maxlength="500" placeholder="간단한 소개 (선택)">' + esc(profile.bio || '') + '</textarea>' +
            '</div>' +
            '<div class="yai-my-field">' +
              '<label>이메일</label>' +
              '<input type="email" value="' + esc(profile.email || '') + '" disabled aria-disabled="true">' +
              '<p class="yai-muted">이메일은 보안상 읽기 전용입니다.</p>' +
            '</div>' +
            '<div class="yai-my-actions">' +
              '<button type="submit" class="yai-btn yai-btn--gold" id="yai-my-profile-save">저장</button>' +
              '<button type="button" class="yai-btn yai-btn--outline" data-my-cancel-edit>취소</button>' +
            '</div>' +
          '</form>' +
        '</section>';
    }

    return '' +
      '<section class="yai-my-panel" aria-labelledby="yai-my-profile-title">' +
        '<div class="yai-my-panel__head">' +
          '<h2 id="yai-my-profile-title">프로필</h2>' +
          '<button type="button" class="yai-btn yai-btn--outline yai-btn--sm" data-my-edit-profile>수정</button>' +
        '</div>' +
        '<dl class="yai-my-dl">' +
          '<div><dt>표시 이름</dt><dd>' + esc(profile.display_name || '—') + '</dd></div>' +
          '<div><dt>이메일</dt><dd>' + esc(profile.email || '—') + '</dd></div>' +
          '<div><dt>소개</dt><dd>' + esc(profile.bio || '아직 소개가 없습니다.') + '</dd></div>' +
          '<div><dt>프로필 이미지</dt><dd>WordPress / Gravatar 연동 · 이미지 로드 실패 시 이니셜 표시</dd></div>' +
        '</dl>' +
      '</section>';
  }

  function planSection(credits) {
    var plan = (credits && (credits.plan_name || credits.tier || credits.plan)) || 'Free';
    var unlimited = !!(credits && credits.unlimited);
    var bal = unlimited ? '∞' : fmt(credits && (credits.remaining != null ? credits.remaining : credits.balance));
    var billing = (credits && credits.billing) || {};
    var paymentReady = !!(billing.payment_ready || billing.woocommerce_active);
    var action = paymentReady
      ? '<button type="button" class="yai-btn yai-btn--gold" data-route="credits">플랜 변경 · 업그레이드</button>'
      : '<p class="yai-muted">결제 연동이 준비되면 여기서 플랜을 변경할 수 있습니다.</p>' +
        '<button type="button" class="yai-btn yai-btn--outline" data-route="credits">플랜 자세히 보기</button>';

    return '' +
      '<section class="yai-my-panel" aria-labelledby="yai-my-plan-title">' +
        '<h2 id="yai-my-plan-title">플랜 및 구독</h2>' +
        '<p class="yai-my-lead">현재 적용 중인 플랜입니다. 갱신일·청구 정보는 실제 결제 연동이 있을 때만 표시됩니다.</p>' +
        '<div class="yai-my-plan-card">' +
          '<div><span class="yai-muted">현재 플랜</span><strong class="yai-my-plan-card__name">' + esc(plan) + '</strong></div>' +
          '<div><span class="yai-muted">잔여 크레딧</span><strong>' + esc(String(bal)) + '</strong></div>' +
          '<div><span class="yai-muted">상태</span><strong>' + (paymentReady ? '결제 연동됨' : '플랜 활성') + '</strong></div>' +
        '</div>' +
        '<div class="yai-my-actions">' + action + '</div>' +
      '</section>';
  }

  function creditsSection(credits) {
    var txs = (credits && credits.transactions) || [];
    var bal = credits && credits.unlimited ? '∞' : fmt(credits && (credits.remaining != null ? credits.remaining : credits.balance));
    var rows = txs.slice(0, 8).map(function (tx) {
      var amt = tx.amount != null ? tx.amount : tx.delta;
      var sign = Number(amt) >= 0 ? '+' : '';
      return '<li><span>' + esc(tx.label || tx.type || '사용') + '</span><strong>' + esc(sign + String(amt == null ? '—' : amt)) + '</strong></li>';
    }).join('');
    if (!rows) {
      rows = '<li class="yai-muted">최근 사용 내역이 없습니다.</li>';
    }
    return '' +
      '<section class="yai-my-panel" aria-labelledby="yai-my-credits-title">' +
        '<h2 id="yai-my-credits-title">크레딧</h2>' +
        '<p class="yai-my-stat"><span>현재 잔액</span><strong>' + esc(String(bal)) + '</strong></p>' +
        '<h3 class="yai-my-subhead">최근 사용</h3>' +
        '<ul class="yai-my-ledger">' + rows + '</ul>' +
        '<div class="yai-my-actions">' +
          '<button type="button" class="yai-btn yai-btn--gold" data-route="credits">크레딧 전체 보기</button>' +
        '</div>' +
      '</section>';
  }

  function settingsSection(settings) {
    settings = settings || {};
    var q = settings.quality || 'standard';
    return '' +
      '<section class="yai-my-panel" aria-labelledby="yai-my-settings-title">' +
        '<h2 id="yai-my-settings-title">설정</h2>' +
        '<p class="yai-my-lead">실제 저장되는 사용자 환경만 제공합니다.</p>' +
        '<form class="yai-my-form" id="yai-my-settings-form">' +
          '<label class="yai-my-toggle">' +
            '<input type="checkbox" name="korean_context" ' + (settings.korean_context !== false ? 'checked' : '') + '>' +
            '<span><strong>한국 맥락 강화</strong><em>생성 시 한국 맥락을 우선 반영합니다.</em></span>' +
          '</label>' +
          '<label class="yai-my-toggle">' +
            '<input type="checkbox" name="auto_save" ' + (settings.auto_save !== false ? 'checked' : '') + '>' +
            '<span><strong>자동 저장</strong><em>생성 결과를 Gallery에 자동으로 남깁니다.</em></span>' +
          '</label>' +
          '<label class="yai-my-toggle">' +
            '<input type="checkbox" name="notifications" ' + (settings.notifications !== false ? 'checked' : '') + '>' +
            '<span><strong>알림 받기</strong><em>중요 안내를 받을지 선택합니다. (알림 벨과 연동 예정)</em></span>' +
          '</label>' +
          '<div class="yai-my-field">' +
            '<label for="yai-my-quality">출력 품질</label>' +
            '<select id="yai-my-quality" name="quality">' +
              '<option value="standard"' + (q === 'standard' ? ' selected' : '') + '>표준</option>' +
              '<option value="high"' + (q === 'high' ? ' selected' : '') + '>고품질</option>' +
            '</select>' +
          '</div>' +
          '<p class="yai-muted">화면 테마는 YooY Studio 다크 디자인으로 고정되어 있습니다.</p>' +
          '<div class="yai-my-actions">' +
            '<button type="submit" class="yai-btn yai-btn--gold">설정 저장</button>' +
          '</div>' +
        '</form>' +
      '</section>';
  }

  function helpSection() {
    return '' +
      '<section class="yai-my-panel" aria-labelledby="yai-my-help-title">' +
        '<h2 id="yai-my-help-title">도움말</h2>' +
        '<div class="yai-my-help">' +
          '<article><h3>시작하기</h3><p>Home에서 아이디어를 입력하거나 AI Assistant에게 말해 보세요.</p>' +
            '<button type="button" class="yai-text-btn" data-route="home">Home으로</button></article>' +
          '<article><h3>Studio 사용</h3><p>Image · Video · Writing 등에서 작품을 만들고 Gallery에 저장됩니다.</p>' +
            '<button type="button" class="yai-text-btn" data-route="image">Image Studio</button></article>' +
          '<article><h3>Credits</h3><p>생성할 때마다 크레딧이 사용됩니다. 잔액과 플랜은 크레딧 화면에서 확인하세요.</p>' +
            '<button type="button" class="yai-text-btn" data-my-nav="credits">크레딧 요약</button></article>' +
          '<article><h3>Gallery / Projects</h3><p>작품은 Gallery에, 작업 묶음은 Projects에 모읍니다.</p>' +
            '<button type="button" class="yai-text-btn" data-route="works">Gallery로</button></article>' +
          '<article><h3>공개하기</h3><p>Community와 Marketplace에서 작품을 공유할 수 있습니다.</p>' +
            '<button type="button" class="yai-text-btn" data-route="community">Community</button></article>' +
          '<article><h3>계정 문제</h3><p>로그인·비밀번호·삭제 관련은 계정 섹션을 확인하세요.</p>' +
            '<button type="button" class="yai-text-btn" data-my-nav="account">계정으로</button></article>' +
        '</div>' +
        '<div class="yai-my-actions">' +
          '<button type="button" class="yai-btn yai-btn--outline" data-yai-panel="help">빠른 가이드 열기</button>' +
        '</div>' +
      '</section>';
  }

  function accountSection(profile) {
    var acc = (profile && profile.account) || {};
    var logout = acc.logout_url || (Core().config && Core().config.logoutUrl) || '#';
    var reset = acc.password_reset_url || '';
    var privacy = acc.privacy_policy_url || '';
    var canDelete = acc.can_delete !== false;

    return '' +
      '<section class="yai-my-panel" aria-labelledby="yai-my-account-title">' +
        '<h2 id="yai-my-account-title">계정</h2>' +
        '<dl class="yai-my-dl">' +
          '<div><dt>로그인 이메일</dt><dd>' + esc(profile.email || '—') + '</dd></div>' +
          '<div><dt>계정 유형</dt><dd>' + esc(profile.role === 'admin' ? '관리자' : '크리에이터') + '</dd></div>' +
        '</dl>' +
        '<div class="yai-my-actions yai-my-actions--stack">' +
          (reset ? '<a class="yai-btn yai-btn--outline" href="' + esc(reset) + '">비밀번호 재설정</a>' : '') +
          '<a class="yai-btn yai-btn--outline" href="' + esc(logout) + '">로그아웃</a>' +
          (privacy ? '<a class="yai-text-btn" href="' + esc(privacy) + '" target="_blank" rel="noopener">개인정보 처리방침</a>' : '') +
        '</div>' +
        '<div class="yai-my-danger">' +
          '<h3>계정 삭제</h3>' +
          '<p>계정을 삭제하면 복구할 수 없습니다. Gallery·Projects·크레딧 등 계정에 연결된 데이터가 WordPress 사용자 삭제 정책에 따라 제거됩니다.</p>' +
          (canDelete
            ? '<button type="button" class="yai-btn yai-btn--danger" data-my-delete-open>계정 삭제…</button>'
            : '<p class="yai-muted">유일한 관리자 계정은 삭제할 수 없습니다.</p>') +
        '</div>' +
        '<div class="yai-my-delete-dialog" id="yai-my-delete-dialog" hidden>' +
          '<p><strong>정말 삭제할까요?</strong></p>' +
          '<p>확인을 위해 아래에 <code>DELETE</code> 를 입력하세요.</p>' +
          '<label class="yai-sr-only" for="yai-my-delete-confirm">확인 문구</label>' +
          '<input id="yai-my-delete-confirm" type="text" autocomplete="off" placeholder="DELETE">' +
          '<div class="yai-my-actions">' +
            '<button type="button" class="yai-btn yai-btn--danger" data-my-delete-confirm>영구 삭제</button>' +
            '<button type="button" class="yai-btn yai-btn--outline" data-my-delete-cancel>취소</button>' +
          '</div>' +
        '</div>' +
      '</section>';
  }

  function sectionHtml() {
    var profile = state.profile || {};
    var credits = state.credits || {};
    var settings = state.settings || {};
    switch (state.section) {
      case 'plan': return planSection(credits);
      case 'credits': return creditsSection(credits);
      case 'settings': return settingsSection(settings);
      case 'help': return helpSection();
      case 'account': return accountSection(profile);
      case 'profile':
      default: return profileSection(profile);
    }
  }

  function render() {
    var el = mountEl();
    if (!el) return;
    if (!state.profile) {
      el.innerHTML = '<p class="yai-muted">불러오는 중…</p>';
      return;
    }
    el.innerHTML =
      '<div class="yai-my">' +
        heroHtml(state.profile, state.credits) +
        summaryCards(state.profile, state.credits) +
        '<div class="yai-my-layout">' +
          navHtml() +
          '<div class="yai-my-main" id="yai-my-main">' + sectionHtml() + '</div>' +
        '</div>' +
      '</div>';
    bindAvatarFallbacks(el);
    bindEvents(el);
  }

  function bindEvents(root) {
    root.querySelectorAll('[data-my-nav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setSection(btn.getAttribute('data-my-nav'));
        state.editingProfile = false;
        render();
        var main = document.getElementById('yai-my-main');
        if (main) main.focus();
      });
    });

    var edit = root.querySelector('[data-my-edit-profile]');
    if (edit) {
      edit.addEventListener('click', function () {
        state.editingProfile = true;
        render();
        var input = document.getElementById('yai-my-display-name');
        if (input) input.focus();
      });
    }

    var cancel = root.querySelector('[data-my-cancel-edit]');
    if (cancel) {
      cancel.addEventListener('click', function () {
        state.editingProfile = false;
        render();
      });
    }

    var form = root.querySelector('#yai-my-profile-form');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        saveProfile();
      });
    }

    var sform = root.querySelector('#yai-my-settings-form');
    if (sform) {
      sform.addEventListener('submit', function (e) {
        e.preventDefault();
        saveSettings(sform);
      });
    }

    var openDel = root.querySelector('[data-my-delete-open]');
    var dialog = root.querySelector('#yai-my-delete-dialog');
    if (openDel && dialog) {
      openDel.addEventListener('click', function () {
        dialog.hidden = false;
        var conf = document.getElementById('yai-my-delete-confirm');
        if (conf) conf.focus();
      });
    }
    var cancelDel = root.querySelector('[data-my-delete-cancel]');
    if (cancelDel && dialog) {
      cancelDel.addEventListener('click', function () {
        dialog.hidden = true;
      });
    }
    var confirmDel = root.querySelector('[data-my-delete-confirm]');
    if (confirmDel) {
      confirmDel.addEventListener('click', function () {
        deleteAccount();
      });
    }
  }

  function saveProfile() {
    if (state.busy) return;
    var nameEl = document.getElementById('yai-my-display-name');
    var bioEl = document.getElementById('yai-my-bio');
    var errEl = document.getElementById('yai-my-profile-error');
    var name = nameEl ? String(nameEl.value || '').trim() : '';
    if (!name) {
      if (errEl) {
        errEl.hidden = false;
        errEl.textContent = '표시 이름을 입력해 주세요.';
      }
      if (nameEl) nameEl.focus();
      return;
    }
    state.busy = true;
    var C = Core();
    C.profile.update({
      display_name: name,
      bio: bioEl ? bioEl.value : ''
    }).then(function (res) {
      state.busy = false;
      var p = res && res.data && res.data.profile;
      if (p) state.profile = p;
      state.editingProfile = false;
      syncShellName(p);
      toast((res && res.data && res.data.message) || '프로필이 저장되었습니다.');
      render();
    }).catch(function (err) {
      state.busy = false;
      var msg = (err && err.message) || '저장에 실패했습니다.';
      if (errEl) {
        errEl.hidden = false;
        errEl.textContent = msg;
      }
      toast(msg, true);
    });
  }

  function saveSettings(form) {
    if (state.busy) return;
    var fd = new FormData(form);
    var payload = {
      korean_context: !!form.querySelector('[name="korean_context"]').checked,
      auto_save: !!form.querySelector('[name="auto_save"]').checked,
      notifications: !!form.querySelector('[name="notifications"]').checked,
      quality: String(fd.get('quality') || 'standard')
    };
    state.busy = true;
    Core().settings.update(payload).then(function (res) {
      state.busy = false;
      state.settings = (res && res.data && res.data.settings) || payload;
      toast((res && res.data && res.data.message) || '설정이 저장되었습니다.');
      render();
    }).catch(function (err) {
      state.busy = false;
      toast((err && err.message) || '설정 저장에 실패했습니다.', true);
    });
  }

  function deleteAccount() {
    if (state.busy) return;
    var input = document.getElementById('yai-my-delete-confirm');
    var val = input ? String(input.value || '').trim() : '';
    if (val !== 'DELETE') {
      toast('확인을 위해 DELETE를 입력해 주세요.', true);
      if (input) input.focus();
      return;
    }
    state.busy = true;
    Core().profile.deleteAccount({ confirm: 'DELETE' }).then(function (res) {
      state.busy = false;
      var url = (res && res.data && res.data.redirect) || '/';
      toast('계정이 삭제되었습니다.');
      global.location.href = url;
    }).catch(function (err) {
      state.busy = false;
      toast((err && err.message) || '계정 삭제에 실패했습니다.', true);
    });
  }

  function syncShellName(profile) {
    if (!profile) return;
    var name = profile.display_name || '';
    ['yai-my-menu-name', 'yai-my-menu-head-name'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = name;
    });
  }

  function loadAll() {
    var el = mountEl();
    if (!el) return;
    var C = Core();
    if (!(C.config && C.config.loggedIn)) {
      el.innerHTML = '<div class="yai-my-guest"><h2>MY</h2><p>계정 정보는 로그인 후 확인할 수 있습니다.</p>' +
        '<a class="yai-btn yai-btn--gold yai-login-link" href="' + esc((C.config && C.config.loginUrl) || '#') + '">로그인</a></div>';
      return;
    }

    el.innerHTML = '<p class="yai-muted">불러오는 중…</p>';
    readPendingSection();

    Promise.all([
      C.profile.me(),
      C.settings.get(),
      C.credits.overview()
    ]).then(function (results) {
      var pref = results[0] && results[0].data && results[0].data.profile;
      var settings = results[1] && results[1].data && results[1].data.settings;
      var overview = results[2] && results[2].data;
      state.profile = pref || null;
      state.settings = settings || {};
      state.credits = Object.assign({}, (overview && overview.account) || {}, {
        transactions: (overview && overview.transactions) || [],
        billing: (overview && overview.billing) || {}
      });
      if (window.YooYCreditsUI && typeof window.YooYCreditsUI.applyShell === 'function' && overview && overview.account) {
        window.YooYCreditsUI.applyShell(Object.assign({}, overview.account, { billing: overview.billing }));
      }
      render();
    }).catch(function (err) {
      el.innerHTML = '<p class="yai-muted">계정 정보를 불러오지 못했습니다.</p><p class="yai-field-error">' +
        esc((err && err.message) || '') + '</p>' +
        '<button type="button" class="yai-btn yai-btn--outline" data-my-retry>다시 시도</button>';
      var retry = el.querySelector('[data-my-retry]');
      if (retry) retry.addEventListener('click', loadAll);
    });
  }

  global.YooYMyAccount = {
    mount: loadAll,
    openSection: function (id) {
      setSection(id);
      loadAll();
    }
  };
})(window);
