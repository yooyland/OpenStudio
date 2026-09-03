<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_DIR . 'includes/helpers/yoy-ui-icons.php';

$user     = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$is_logged_in = is_user_logged_in();
$login_url    = esc_url(wp_login_url(get_permalink()));
$register_url = esc_url(wp_registration_url());
$logout_url   = esc_url(wp_logout_url(get_permalink()));
$plan_label   = 'Guest';
$plan_id       = 'free';
$crystal_class = 'gray';
$crystal_label = 'Obsidian Crystal';
$crystal_diamond = '';
if ($is_logged_in && class_exists('YooY_Credits_Service')) {
    $credits_svc = new YooY_Credits_Service();
    $plan_info   = $credits_svc->get_user_plan($user->ID);
    $plan_label  = $plan_info['tier'] ?? 'Free';
    $plan_id     = $plan_info['id'] ?? 'free';
}
$crystal_map = [
    'free'     => ['gray', 'Obsidian Crystal'],
    'starter'  => ['green', 'Emerald Crystal'],
    'creator'  => ['blue', 'Sapphire Crystal'],
    'pro'      => ['purple', 'Amethyst Crystal'],
    'business' => ['gold', 'Golden Diamond Crystal'],
];
if (isset($crystal_map[$plan_id])) {
    $crystal_class = $crystal_map[$plan_id][0];
    $crystal_label = $crystal_map[$plan_id][1];
}
if ($plan_id === 'business') {
    $crystal_diamond = ' yai-crystal--diamond';
}

$nav_sections = [
    [
        'label' => '',
        'items' => [
            ['route' => 'home',      'label' => 'Home',         'icon' => 'home'],
            ['route' => 'assistant', 'label' => 'AI Assistant', 'icon' => 'spark'],
            ['route' => 'templates', 'label' => 'Templates',    'icon' => 'image'],
            ['route' => 'projects',  'label' => 'Projects',     'icon' => 'projects'],
        ],
    ],
    [
        'label' => 'Create',
        'items' => [
            ['route' => 'image',      'label' => 'Image',      'icon' => 'image'],
            ['route' => 'video',      'label' => 'Video',      'icon' => 'video'],
            ['route' => 'writing',    'label' => 'Writing',    'icon' => 'writing'],
            ['route' => 'music',      'label' => 'Music',      'icon' => 'music'],
            ['route' => 'voice',      'label' => 'Voice',      'icon' => 'voice'],
            ['route' => 'avatar',     'label' => 'Avatar',     'icon' => 'avatar'],
            ['route' => 'translator', 'label' => 'Translator', 'icon' => 'translate'],
        ],
    ],
    [
        'label' => 'My Works',
        'items' => [
            ['route' => 'works',   'label' => 'Gallery', 'icon' => 'gallery'],
            ['route' => 'history', 'label' => 'History', 'icon' => 'chart'],
        ],
    ],
    [
        'label' => 'Publish',
        'items' => [
            ['route' => 'market',    'label' => 'Marketplace', 'icon' => 'market'],
            ['route' => 'community', 'label' => 'Community',   'icon' => 'community'],
        ],
    ],
    [
        'label' => 'Account',
        'items' => [
            ['route' => 'credits',  'label' => 'Credits',  'icon' => 'credits'],
            ['route' => 'settings', 'label' => 'Settings', 'icon' => 'settings'],
        ],
    ],
];

$user_initials = 'G';
$user_email    = '';
if ($is_logged_in) {
    $user_email = (string) $user->user_email;
    $name_src   = trim((string) ($user->display_name ?: $user->user_login));
    if ($name_src !== '') {
        $parts = preg_split('/\s+/', $name_src);
        if (is_array($parts) && count($parts) >= 2) {
            $user_initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        } else {
            $user_initials = strtoupper(mb_substr($name_src, 0, 2));
        }
    }
}

$studio_quick = [
    ['route' => 'image',      'label' => 'Image',      'icon' => 'image'],
    ['route' => 'video',      'label' => 'Video',      'icon' => 'video'],
    ['route' => 'writing',    'label' => 'Writing',    'icon' => 'writing'],
    ['route' => 'music',      'label' => 'Music',      'icon' => 'music'],
    ['route' => 'voice',      'label' => 'Voice',      'icon' => 'voice'],
    ['route' => 'avatar',     'label' => 'Avatar',     'icon' => 'avatar'],
    ['route' => 'translator', 'label' => 'Translator', 'icon' => 'translate'],
];
?>
<div class="yai-app" id="yai-app" data-version="<?php echo esc_attr(YOY_AI_STUDIO_VERSION); ?>">
    <aside class="yai-sidebar">
        <div class="yai-sidebar-brand">
            <button class="yai-brand" data-route="home" type="button">
                <span class="yai-brand-mark">
                    <img src="https://yooyland.com/wp-content/uploads/2026/05/android-icon-monochrome.png" alt="">
                </span>
                <span class="yai-brand-text">
                    <span class="yai-brand-name">YooY Studio</span>
                    <span class="yai-brand-tag">Creator OS</span>
                </span>
            </button>
        </div>

        <nav class="yai-nav" aria-label="OpenStudio navigation">
            <?php foreach ($nav_sections as $section) : ?>
                <div class="yai-nav-section">
                    <?php if (!empty($section['label'])) : ?>
                        <div class="yai-nav-section__label"><?php echo esc_html($section['label']); ?></div>
                    <?php endif; ?>
                    <?php foreach ($section['items'] as $item) : ?>
                        <button class="yai-nav-item" data-route="<?php echo esc_attr($item['route']); ?>" type="button">
                            <?php echo YooY_UI_Icons::svg($item['icon'], 18); ?>
                            <span><?php echo esc_html($item['label']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($is_admin) : ?>
                <div class="yai-nav-section">
                    <div class="yai-nav-section__label">Admin</div>
                    <button class="yai-nav-item yai-nav-item--admin" data-route="admin-console" type="button">
                        <?php echo YooY_UI_Icons::svg('admin', 18); ?>
                        <span>Admin Console</span>
                    </button>
                </div>
            <?php endif; ?>
        </nav>

        <div class="yai-sidebar-footer">
            <?php if ($is_logged_in) : ?>
                <article class="yai-hd-credit-card" id="yai-sidebar-credit-card" aria-label="크레딧 현황">
                    <div class="yai-hd-credit-row yai-hd-credit-row--primary">
                        <span>잔여</span>
                        <strong id="yai-credit-remaining">—</strong>
                    </div>
                    <div class="yai-hd-credit-row yai-hd-credit-row--sub">
                        <span>전체 <strong id="yai-credit-total">—</strong></span>
                        <button type="button" class="yai-hd-credit-charge" data-route="credits">충전</button>
                    </div>
                    <span class="yai-sr-only" id="yai-sidebar-plan-name"><?php echo esc_html($plan_label); ?></span>
                </article>
                <nav class="yai-sidebar-util" aria-label="계정 메뉴">
                    <button type="button" data-route="settings"><?php echo YooY_UI_Icons::svg('settings', 16); ?> 설정</button>
                    <button type="button" data-yai-panel="help"><?php echo YooY_UI_Icons::svg('help', 16); ?> 도움말</button>
                    <a href="<?php echo $logout_url; ?>">로그아웃</a>
                </nav>
                <div class="yai-profile yai-profile--compact yai-profile--<?php echo esc_attr($plan_id); ?>" id="yai-profile-card" hidden aria-hidden="true">
                    <b class="yai-profile-credits" id="yai-credits">Credits: —</b>
                    <span class="yai-plan-label" id="yai-tier-badge"><?php echo esc_html($plan_label); ?></span>
                </div>
            <?php else : ?>
                <div class="yai-profile yai-profile--guest" id="yai-profile-card">
                    <div class="yai-profile-avatar yai-profile-avatar--guest" aria-hidden="true">?</div>
                    <div class="yai-profile-info">
                        <strong>Guest</strong>
                        <span class="yai-profile-email">Login to start creating</span>
                        <a class="yai-btn yai-btn--gold yai-btn--sm yai-register-link" href="<?php echo esc_url($register_url); ?>">회원가입</a>
                        <a class="yai-btn yai-btn--outline yai-btn--sm yai-login-link" href="<?php echo $login_url; ?>">로그인</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <div class="yai-stage">
        <main class="yai-main" id="yai-main">
            <header class="yai-topbar yai-topbar--global" id="yai-global-topbar">
                <div class="yai-topbar-nav" id="yai-topbar-nav">
                    <button type="button" class="yai-nav-chrome-btn yai-nav-chrome-btn--back" id="yai-nav-back" aria-label="뒤로" title="이전 화면으로" hidden style="display:none">
                        <span aria-hidden="true">←</span><span class="yai-nav-chrome-label">뒤로</span>
                    </button>
                    <span class="yai-crumb" id="yai-topbar-title">YooY AI Studio</span>
                    <button type="button" class="yai-nav-chrome-btn yai-nav-chrome-btn--reset" id="yai-nav-reset" aria-label="초기화" title="현재 작업 초기화" hidden>
                        <span aria-hidden="true">↻</span><span class="yai-nav-chrome-label">초기화</span>
                    </button>
                </div>
                <div class="yai-topbar-actions" id="yai-topbar-actions">
                    <?php if ($is_logged_in) : ?>
                        <button class="yai-topbar-user" data-route="credits" type="button" aria-label="Credits and plan">
                            <span class="yai-membership-crystal yai-crystal--<?php echo esc_attr($crystal_class); ?> yai-crystal--sm<?php echo esc_attr($crystal_diamond); ?>" id="yai-topbar-crystal" role="img" aria-label="<?php echo esc_attr($crystal_label); ?>">
                                <span class="yai-crystal-core" aria-hidden="true"></span>
                                <span class="yai-crystal-shine" aria-hidden="true"></span>
                            </span>
                            <span class="yai-pill" id="yai-top-credits">— Credits</span>
                        </button>
                        <button class="yai-icon-btn" type="button" data-yai-panel="notifications" aria-label="Notifications"><?php echo YooY_UI_Icons::svg('bell', 18); ?></button>
                        <button class="yai-icon-btn" type="button" data-yai-panel="help" aria-label="Help"><?php echo YooY_UI_Icons::svg('help', 18); ?></button>
                        <button class="yai-icon-btn" data-route="settings" type="button" aria-label="Settings"><?php echo YooY_UI_Icons::svg('settings', 18); ?></button>
                    <?php else : ?>
                        <a class="yai-btn yai-btn--outline yai-login-link" href="<?php echo $login_url; ?>">로그인</a>
                        <a class="yai-btn yai-btn--gold yai-register-link" href="<?php echo esc_url($register_url); ?>">회원가입</a>
                        <button class="yai-btn yai-btn--gold yai-btn--cta" type="button" data-yai-free-start>무료로 시작하기</button>
                    <?php endif; ?>
                </div>
            </header>

            <!-- HOME — Discovery + bottom composer (Phase 1) -->
            <section class="yai-view yai-view--home yai-hd-page" data-page="home">
                <div class="yai-hd-layout yai-hd-layout--full">
                    <div class="yai-hd-main">
                        <header class="yai-hd-greeting">
                            <div>
                                <h1 id="yai-hd-greeting-title">안녕하세요<?php echo $is_logged_in ? ', ' . esc_html($user->display_name ?: '크리에이터') . '님' : ''; ?>! 👋</h1>
                                <p class="yai-hero-sub">상상한 모든 것을 YooY Studio에서 현실로 만들어보세요.</p>
                            </div>
                            <div class="yai-hd-greeting__actions">
                                <?php if ($is_admin) : ?>
                                <button type="button" class="yai-btn yai-btn--outline yai-btn--sm yai-hd-section-manage-btn" id="yai-section-manage-btn">섹션 관리</button>
                                <?php endif; ?>
                                <?php if ($is_logged_in) : ?>
                                <div class="yai-hd-plan-wrap" id="yai-plan-dropdown-wrap">
                                    <button type="button" id="yai-pro-plan-btn" aria-haspopup="true" aria-expanded="false">Pro 플랜 <span aria-hidden="true">▾</span></button>
                                    <div class="yai-hd-plan-menu" id="yai-plan-dropdown-menu" role="menu" hidden>
                                        <span class="yai-hd-plan-menu__label">현재 플랜</span>
                                        <span class="yai-hd-plan-menu__current" id="yai-plan-dropdown-current"><?php echo esc_html($plan_label); ?></span>
                                        <button type="button" role="menuitem" data-route="billing" data-plan-action="upgrade">업그레이드</button>
                                        <button type="button" role="menuitem" data-route="billing" data-plan-action="downgrade">다운그레이드</button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </header>

                        <section class="yai-hd-studio-recos" aria-labelledby="yai-home-studio-recos-title">
                            <header class="yai-hd-section__head">
                                <div>
                                    <h2 id="yai-home-studio-recos-title">추천 Studio</h2>
                                    <p>원하는 결과를 고르면 됩니다. 모델·Provider를 몰라도 됩니다.</p>
                                </div>
                            </header>
                            <div class="yai-hd-studio-recos__row" id="yai-home-studio-recos"></div>
                        </section>

                        <div id="yai-home-sections-root" aria-label="홈 섹션"></div>

                        <section class="yai-hd-quick-block" aria-label="빠른 도구">
                            <h2>빠른 도구</h2>
                            <div class="yai-hd-quick-row" id="yai-home-quick-tools"></div>
                        </section>
                    </div>
                </div>

                <div class="yai-hd-drawer-overlay" id="yai-section-drawer-overlay" hidden></div>
                <aside class="yai-hd-drawer" id="yai-section-drawer" aria-label="섹션 관리" hidden>
                    <header class="yai-hd-drawer__head">
                        <h3>섹션 관리</h3>
                        <button type="button" class="yai-hd-drawer__close" id="yai-section-drawer-close" aria-label="닫기">×</button>
                    </header>
                    <div class="yai-hd-manager" id="yai-home-section-manager"></div>
                </aside>

                <!-- Legacy hooks for studio.js data loaders (hidden, non-visual) -->
                <div class="yai-sr-only" aria-hidden="true">
                    <div id="yai-home-recs"></div>
                    <div id="yai-home-projects"></div>
                    <div id="yai-home-works"></div>
                    <div id="yai-home-jobs"></div>
                    <div id="yai-home-sections"></div>
                    <div id="yai-home-market"></div>
                    <div id="yai-home-community-trending"></div>
                    <div id="yai-home-announcements"></div>
                    <div id="yai-showcase"></div>
                    <div id="yai-home-hero-chips"></div>
                    <strong id="yai-stat-credits"></strong>
                    <strong id="yai-stat-projects"></strong>
                    <strong id="yai-stat-works"></strong>
                    <strong id="yai-stat-likes"></strong>
                    <em id="yai-stat-credit-usage"></em>
                </div>
            </section>

            <!-- Fixed bottom creation composer (Home only) -->
            <div class="yai-home-composer" id="yai-home-bottom-composer" hidden data-home-composer>
                <div class="yai-home-composer__inner">
                    <div class="yai-home-composer__plus-wrap">
                        <button type="button" class="yai-home-composer__plus" id="yai-home-composer-plus" aria-label="첨부 메뉴" aria-expanded="false" aria-haspopup="true">+</button>
                        <div class="yai-home-composer__plus-menu" id="yai-home-composer-plus-menu" role="menu" hidden>
                            <button type="button" role="menuitem" data-home-attach="image">이미지 업로드</button>
                            <button type="button" role="menuitem" data-home-attach="file">파일 업로드</button>
                            <button type="button" role="menuitem" data-home-attach="url">URL 가져오기</button>
                        </div>
                    </div>
                    <div class="yai-home-composer__field">
                        <div class="yai-home-composer__chip-row" id="yai-home-attach-chip" hidden></div>
                        <label class="yai-sr-only" for="yai-home-prompt">만들고 싶은 것</label>
                        <textarea id="yai-home-prompt" rows="1" placeholder="무엇을 만들고 싶으신가요?"></textarea>
                        <div class="yai-home-url-row" id="yai-home-url-row" hidden>
                            <input type="url" id="yai-home-url-input" placeholder="https://..." autocomplete="off">
                            <button type="button" id="yai-home-url-go">가져오기</button>
                        </div>
                        <div class="yai-home-intent-choice" id="yai-home-intent-choice" hidden></div>
                        <p class="yai-home-composer__status" id="yai-home-composer-status" hidden></p>
                        <div class="yai-home-composer__pills">
                            <button type="button" class="yai-home-composer__pill" id="yai-home-coach" aria-pressed="false">✨ 프롬프트 자동보완</button>
                            <button type="button" class="yai-home-composer__pill is-on" id="yai-home-studio-auto" aria-pressed="true">◎ Studio 자동 선택</button>
                        </div>
                        <div class="yai-create-ux__coach" id="yai-home-coach-panel" hidden></div>
                    </div>
                    <button type="button" class="yai-home-composer__submit" id="yai-home-create">생성하기 →</button>
                </div>
                <p class="yai-home-composer__hint">예: 여름 바닷가 화장품 광고 이미지 만들어줘</p>
            </div>

            <!-- PAGES -->
            <section class="yai-view" data-page="projects">
                <header class="yai-page-head yai-page-head--row">
                    <div>
                        <h1>Projects</h1>
                        <p>OpenStudio의 중심 — Project 안에서 생성·관리합니다.</p>
                    </div>
                    <button type="button" class="yai-btn yai-btn--gold yai-create-project" id="yai-projects-create-btn" data-action="create-project" data-yai-create-project>새 프로젝트</button>
                </header>
                <div id="yai-projects-list"></div>
            </section>
            <section class="yai-view yai-view--workspace" data-page="project-detail">
                <header class="yai-page-head yai-page-head--row">
                    <div>
                        <button type="button" class="yai-text-btn yai-back-btn" data-route="projects">← Projects</button>
                        <p class="yai-eyebrow" id="yai-workspace-eyebrow">Project Workspace</p>
                        <h1 id="yai-project-detail-title">Project Workspace</h1>
                        <p id="yai-project-detail-desc">프로젝트 제작 공간</p>
                    </div>
                    <div class="yai-project-detail-actions">
                        <button type="button" class="yai-btn--outline" id="yai-workspace-tab-settings-btn" data-workspace-goto="settings">Settings</button>
                        <button type="button" class="yai-btn--outline yai-btn--danger" id="yai-project-detail-delete">Delete</button>
                    </div>
                </header>
                <nav class="yai-workspace-tabs" id="yai-workspace-tabs" aria-label="Workspace tabs"></nav>
                <div class="yai-workspace-panel" id="yai-workspace-panel">
                    <div class="yai-empty"><p>Loading workspace…</p></div>
                </div>
            </section>
            <section class="yai-view yai-view--studio yai-view--assistant" data-page="assistant"><div id="yai-assistant-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="video"><div id="yai-video-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="image"><div id="yai-image-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="music"><div id="yai-music-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="voice"><div id="yai-voice-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="avatar"><div id="yai-avatar-studio"></div></section>
            <section class="yai-view" data-page="writing"><header class="yai-page-head"><h1>Writing Studio</h1><p>블로그, 광고 카피, 스크립트 — AI 글쓰기</p></header><div class="yai-generator" id="yai-gen-writing"></div></section>
            <section class="yai-view yai-view--studio" data-page="translator"><div id="yai-translator-studio"></div></section>
            <section class="yai-view" data-page="templates">
                <div id="yai-templates-root" class="yai-ct-lib"></div>
            </section>
            <section class="yai-view" data-page="prompt-library"><header class="yai-page-head"><h1>Prompt Library</h1><p>저장된 프롬프트, 공식 템플릿, 한국 컨텍스트 프리셋.</p></header><div id="yai-prompts"></div></section>
            <section class="yai-view" data-page="import"><div id="yai-import-engine"></div></section>
            <section class="yai-view" data-page="market"><header class="yai-page-head"><h1>Marketplace</h1><p>판매·구매 공간 — Gallery와 분리된 Publish 영역입니다.</p></header><div id="yai-marketplace"></div></section>
            <section class="yai-view" data-page="community"><header class="yai-page-head"><h1>Community</h1><p>공유 공간 — Gallery와 다른 Publish 피드입니다.</p></header><div id="yai-community"></div></section>
            <section class="yai-view" data-page="works"><header class="yai-page-head"><h1>Gallery</h1><p>내가 만든 모든 작품 — 이어서 만들기 · 프로젝트에 추가 · 보관.</p></header><div id="yai-works"></div></section>
            <section class="yai-view" data-page="history">
                <header class="yai-page-head">
                    <h1>History</h1>
                    <p>생성·활동 기록입니다. 작품 본문은 Gallery에 있습니다.</p>
                </header>
                <div id="yai-history"></div>
            </section>
            <section class="yai-view" data-page="credits"><header class="yai-page-head"><h1>Credits</h1><p>Account 영역 — 잔액, 사용 내역, 플랜.</p></header><div id="yai-credits-panel"></div></section>
            <section class="yai-view" data-page="billing"><header class="yai-page-head"><h1>Billing</h1><p>현재 플랜, 결제 내역, 구독 관리.</p></header><div id="yai-billing-panel"></div></section>
            <section class="yai-view" data-page="settings"><header class="yai-page-head"><h1>Settings</h1><p>스튜디오 기본 설정과 한국 컨텍스트 옵션.</p></header><div class="yai-settings-grid" id="yai-settings"></div></section>

            <?php if ($is_admin) : ?>
            <section class="yai-view yai-view--admin" data-page="admin-console">
                <div id="yai-admin-console" class="yai-ops-center" data-context="frontend"></div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <div class="yai-overlay" id="yai-login-modal" hidden>
        <div class="yai-modal yai-modal--auth">
            <h3>로그인이 필요합니다</h3>
            <p>작품을 저장하고 계속 사용하려면 로그인 또는 회원가입이 필요합니다.</p>
            <p class="yai-muted">무료 가입 시 <strong>100 Credits</strong>와 Free 플랜이 제공됩니다.</p>
            <div class="yai-modal-actions yai-modal-actions--stack">
                <a class="yai-btn yai-btn--gold yai-login-link" href="<?php echo $login_url; ?>">로그인</a>
                <a class="yai-btn yai-btn--outline yai-register-link" href="<?php echo esc_url($register_url); ?>">무료 회원가입</a>
                <button type="button" class="yai-btn--outline" data-yai-close-modal>둘러보기 계속</button>
            </div>
        </div>
    </div>

    <div class="yai-overlay" id="yai-panel-notifications" hidden>
        <div class="yai-panel">
            <header><strong>Notifications</strong><button type="button" class="yai-icon-btn" data-yai-close-panel aria-label="Close">×</button></header>
            <div class="yai-panel-body" id="yai-notifications-list">
                <p class="yai-muted">No new notifications.</p>
            </div>
        </div>
    </div>

    <div class="yai-overlay" id="yai-panel-help" hidden>
        <div class="yai-panel yai-panel--wide">
            <header><strong>Help & Guide</strong><button type="button" class="yai-icon-btn" data-yai-close-panel aria-label="Close">×</button></header>
            <div class="yai-panel-body">
                <h4>Getting Started</h4>
                <ul class="yai-help-list">
                    <li>Home에서 AI Assistant 또는 Project로 시작하세요.</li>
                    <li>만들고 싶은 것을 말하면 Assistant가 Studio를 추천합니다.</li>
                    <li>Create에서 Image / Video / Writing 등으로 생성하세요.</li>
                    <li>결과는 Gallery에 저장되고 Project에 연결됩니다.</li>
                    <li>Credits는 Account 메뉴에서 확인합니다.</li>
                </ul>
                <h4>Credits</h4>
                <p class="yai-muted">Each generation consumes credits based on studio type. View balance and upgrade options on the Credits page.</p>
                <button type="button" class="yai-btn--outline" data-route="credits">Open Credits</button>
            </div>
        </div>
    </div>
</div>
