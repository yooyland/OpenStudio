<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_DIR . 'includes/helpers/yoy-ui-icons.php';

$user     = wp_get_current_user();
$is_admin = current_user_can('manage_options');
$is_logged_in = is_user_logged_in();
$login_url    = esc_url(wp_login_url(get_permalink()));
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

$nav_items = [
    ['route' => 'home',           'label' => 'Home',           'icon' => 'home'],
    ['route' => 'projects',       'label' => 'Projects',       'icon' => 'projects'],
    ['route' => 'video',          'label' => 'Video',          'icon' => 'video'],
    ['route' => 'image',          'label' => 'Image',          'icon' => 'image'],
    ['route' => 'music',          'label' => 'Music',          'icon' => 'music'],
    ['route' => 'voice',          'label' => 'Voice',          'icon' => 'voice'],
    ['route' => 'avatar',         'label' => 'Avatar',         'icon' => 'avatar'],
    ['route' => 'writing',        'label' => 'Writing',        'icon' => 'writing'],
    ['route' => 'prompt-library', 'label' => 'Prompt Library', 'icon' => 'prompts'],
    ['route' => 'import',         'label' => 'Import',         'icon' => 'upload'],
    ['route' => 'works',          'label' => 'Gallery',        'icon' => 'gallery'],
    ['route' => 'community',      'label' => 'Community',      'icon' => 'community'],
    ['route' => 'market',         'label' => 'Marketplace',    'icon' => 'market'],
    ['route' => 'credits',        'label' => 'Credits',        'icon' => 'credits'],
    ['route' => 'billing',        'label' => 'Billing',        'icon' => 'credits'],
    ['route' => 'settings',       'label' => 'Settings',       'icon' => 'settings'],
];

$studio_quick = [
    ['route' => 'video',   'label' => 'Video',   'icon' => 'video'],
    ['route' => 'image',   'label' => 'Image',   'icon' => 'image'],
    ['route' => 'music',   'label' => 'Music',   'icon' => 'music'],
    ['route' => 'voice',   'label' => 'Voice',   'icon' => 'voice'],
    ['route' => 'avatar',  'label' => 'Avatar',  'icon' => 'avatar'],
    ['route' => 'writing', 'label' => 'Writing', 'icon' => 'writing'],
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

        <nav class="yai-nav">
            <?php foreach ($nav_items as $item) : ?>
                <button class="yai-nav-item" data-route="<?php echo esc_attr($item['route']); ?>" type="button">
                    <?php echo YooY_UI_Icons::svg($item['icon'], 20); ?>
                    <span><?php echo esc_html($item['label']); ?></span>
                </button>
            <?php endforeach; ?>

            <?php if ($is_admin) : ?>
                <button class="yai-nav-item yai-nav-item--admin" data-route="admin-console" type="button">
                    <?php echo YooY_UI_Icons::svg('admin', 20); ?>
                    <span>Operations Center</span>
                    <em class="yai-nav-admin-tag">Admin</em>
                </button>
            <?php endif; ?>
        </nav>

        <div class="yai-profile yai-profile--<?php echo esc_attr($plan_id); ?><?php echo $plan_id === 'business' ? ' yai-profile--business' : ''; ?>" id="yai-profile-card">
            <div class="yai-membership-crystal yai-crystal--<?php echo esc_attr($crystal_class); ?> yai-crystal--md<?php echo esc_attr($crystal_diamond); ?>" id="yai-membership-crystal" role="img" aria-label="<?php echo esc_attr($crystal_label); ?>">
                <span class="yai-crystal-core" aria-hidden="true"></span>
                <span class="yai-crystal-shine" aria-hidden="true"></span>
            </div>
            <div class="yai-profile-info">
                <?php if ($is_logged_in) : ?>
                    <strong><?php echo esc_html($user->display_name ?: 'User'); ?></strong>
                    <span class="yai-plan-label" id="yai-tier-badge"><?php echo esc_html($plan_label); ?></span>
                    <b id="yai-credits">Credits: —</b>
                    <small class="yai-usage-label" id="yai-monthly-usage">Monthly usage: —</small>
                    <button type="button" class="yai-btn yai-btn--gold yai-btn--sm" id="yai-upgrade-btn" data-route="credits">Upgrade</button>
                <?php else : ?>
                    <strong>Guest</strong>
                    <span>Login to start creating</span>
                    <a class="yai-btn yai-btn--gold yai-login-link" href="<?php echo $login_url; ?>">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <div class="yai-stage">
        <main class="yai-main" id="yai-main">
            <header class="yai-topbar yai-topbar--global" id="yai-global-topbar">
                <span class="yai-crumb" id="yai-topbar-title">YooY AI Studio</span>
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
                        <a class="yai-btn yai-btn--gold yai-login-link" href="<?php echo $login_url; ?>">Login</a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- HOME -->
            <section class="yai-view yai-view--home" data-page="home">
                <div class="yai-home-head">
                    <h1>오늘 무엇을 만들까요? ✨</h1>
                </div>

                <div class="yai-composer">
                    <textarea id="yai-home-prompt" rows="2" placeholder="오늘 무엇을 만들까요? 예: 고래 타고 대한민국 여행, 스마트스토어 제품 썸네일, K-POP 뮤직비디오..."></textarea>
                    <button class="yai-btn yai-btn--gold" id="yai-home-create" type="button"><?php echo YooY_UI_Icons::svg('spark', 16); ?> 만들기</button>
                </div>

                <div class="yai-preset-row" id="yai-home-presets" aria-label="한국형 프리셋"></div>

                <div class="yai-quick-row">
                    <?php foreach ($studio_quick as $item) : ?>
                        <button class="yai-quick-btn" data-route="<?php echo esc_attr($item['route']); ?>" type="button">
                            <?php echo YooY_UI_Icons::svg($item['icon'], 16); ?>
                            <?php echo esc_html($item['label']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="yai-stats-row">
                    <article class="yai-stat" data-stat="credits">
                        <div class="yai-stat-icon"><?php echo YooY_UI_Icons::svg('credits', 18); ?></div>
                        <div><span>Total Credits</span><strong id="yai-stat-credits">—</strong><small id="yai-stat-credits-delta"></small></div>
                    </article>
                    <article class="yai-stat" data-stat="usage">
                        <div class="yai-stat-icon"><?php echo YooY_UI_Icons::svg('chart', 18); ?></div>
                        <div><span>Monthly Usage</span><strong id="yai-stat-usage">—</strong><div class="yai-progress"><i id="yai-stat-usage-bar"></i></div></div>
                    </article>
                    <article class="yai-stat" data-stat="projects">
                        <div class="yai-stat-icon"><?php echo YooY_UI_Icons::svg('folder', 18); ?></div>
                        <div><span>Total Projects</span><strong id="yai-stat-projects">—</strong></div>
                    </article>
                    <article class="yai-stat" data-stat="jobs">
                        <div class="yai-stat-icon"><?php echo YooY_UI_Icons::svg('zap', 18); ?></div>
                        <div><span>Total Generations</span><strong id="yai-stat-jobs">—</strong></div>
                    </article>
                    <article class="yai-stat" data-stat="likes">
                        <div class="yai-stat-icon"><?php echo YooY_UI_Icons::svg('heart', 18); ?></div>
                        <div><span>Community Likes</span><strong id="yai-stat-likes">—</strong></div>
                    </article>
                </div>

                <div class="yai-home-dashboard">
                    <div class="yai-home-main">
                        <div class="yai-card-block yai-card-block--hero">
                            <div class="yai-block-head">
                                <h2>Recent Works</h2>
                                <button class="yai-text-btn" data-route="works" type="button">Gallery</button>
                            </div>
                            <div class="yai-block-body yai-works-grid" id="yai-home-works"></div>
                        </div>
                        <div class="yai-card-block yai-card-block--projects-strip">
                            <div class="yai-block-head">
                                <h2>Recent Projects</h2>
                                <button class="yai-text-btn" data-route="projects" type="button">View all</button>
                            </div>
                            <div class="yai-block-body yai-projects-strip" id="yai-home-projects"></div>
                        </div>
                    </div>
                    <aside class="yai-home-side">
                        <div class="yai-card-block yai-card-block--compact">
                            <div class="yai-block-head"><h2>Recent Activity</h2></div>
                            <div class="yai-block-body yai-timeline yai-timeline--compact" id="yai-home-jobs"></div>
                        </div>
                        <div class="yai-card-block yai-card-block--compact">
                            <div class="yai-block-head"><h2>Credit Usage</h2></div>
                            <div class="yai-block-body yai-usage-widget" id="yai-home-usage"></div>
                        </div>
                        <div class="yai-card-block yai-card-block--compact">
                            <div class="yai-block-head"><h2>Announcements</h2></div>
                            <div class="yai-block-body" id="yai-home-announcements"></div>
                        </div>
                    </aside>
                </div>

                <div class="yai-home-sections" id="yai-home-sections" aria-label="Curated home sections"></div>

                <div class="yai-home-discover">
                    <div class="yai-card-block">
                        <div class="yai-block-head"><h2>Marketplace 추천</h2><button class="yai-text-btn" data-route="market" type="button">전체 보기</button></div>
                        <div class="yai-block-body yai-discover-row" id="yai-home-market"></div>
                    </div>
                    <div class="yai-card-block">
                        <div class="yai-block-head"><h2>Community 인기작</h2><button class="yai-text-btn" data-route="community" type="button">전체 보기</button></div>
                        <div class="yai-block-body yai-discover-row" id="yai-home-community-trending"></div>
                    </div>
                </div>

                <div class="yai-showcase-section">
                    <div class="yai-block-head"><h2>YooY Official Showcase</h2><button class="yai-text-btn" data-route="community" type="button">Community</button></div>
                    <div class="yai-showcase-row" id="yai-showcase"></div>
                </div>
            </section>

            <!-- PAGES -->
            <section class="yai-view" data-page="projects">
                <header class="yai-page-head yai-page-head--row">
                    <div>
                        <h1>Projects</h1>
                        <p>프로젝트 워크스페이스 — 생성 히스토리와 작업을 관리합니다.</p>
                    </div>
                    <button type="button" class="yai-btn yai-btn--gold" id="yai-projects-create-btn" data-yai-create-project>Create</button>
                </header>
                <div id="yai-projects-list"></div>
            </section>
            <section class="yai-view" data-page="project-detail">
                <header class="yai-page-head yai-page-head--row">
                    <div>
                        <button type="button" class="yai-text-btn yai-back-btn" data-route="projects">← Projects</button>
                        <h1 id="yai-project-detail-title">Project</h1>
                        <p id="yai-project-detail-desc">프로젝트 작품을 관리합니다.</p>
                    </div>
                    <div class="yai-project-detail-actions">
                        <button type="button" class="yai-btn--outline" id="yai-project-detail-edit">Edit</button>
                        <button type="button" class="yai-btn--outline yai-btn--danger" id="yai-project-detail-delete">Delete</button>
                    </div>
                </header>
                <div class="yai-project-detail-cover" id="yai-project-detail-cover"></div>
                <div class="yai-project-detail-filters" id="yai-project-detail-filters"></div>
                <div class="yai-works-grid" id="yai-project-detail-works"></div>
            </section>
            <section class="yai-view yai-view--studio" data-page="video"><div id="yai-video-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="image"><div id="yai-image-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="music"><div id="yai-music-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="voice"><div id="yai-voice-studio"></div></section>
            <section class="yai-view yai-view--studio" data-page="avatar"><div id="yai-avatar-studio"></div></section>
            <section class="yai-view" data-page="writing"><header class="yai-page-head"><h1>Writing Studio</h1><p>블로그, 광고 카피, 스크립트 — AI 글쓰기</p></header><div class="yai-generator" id="yai-gen-writing"></div></section>
            <section class="yai-view" data-page="prompt-library"><header class="yai-page-head"><h1>Prompt Library</h1><p>저장된 프롬프트, 공식 템플릿, 한국 컨텍스트 프리셋.</p></header><div id="yai-prompts"></div></section>
            <section class="yai-view" data-page="import"><div id="yai-import-engine"></div></section>
            <section class="yai-view" data-page="market"><header class="yai-page-head"><h1>Marketplace</h1><p>프롬프트 템플릿, 가이드, 크리에이터 마켓.</p></header><div id="yai-marketplace"></div></section>
            <section class="yai-view" data-page="community"><header class="yai-page-head"><h1>Community</h1><p>크리에이터 피드, 공개 갤러리, 프롬프트 공유.</p></header><div id="yai-community"></div></section>
            <section class="yai-view" data-page="works"><header class="yai-page-head"><h1>Gallery</h1><p>생성 및 Import된 모든 에셋 — 영상, 이미지, 음악, 글, 아바타, 음성.</p></header><div id="yai-works"></div></section>
            <section class="yai-view" data-page="credits"><header class="yai-page-head"><h1>Credits</h1><p>크레딧 잔액, 사용 내역, 플랜 비교.</p></header><div id="yai-credits-panel"></div></section>
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
        <div class="yai-modal">
            <h3>Login required</h3>
            <p>Sign in with your WordPress account to create, import, publish, and manage credits.</p>
            <div class="yai-modal-actions">
                <button type="button" class="yai-btn--outline" data-yai-close-modal>Cancel</button>
                <a class="yai-btn yai-btn--gold yai-login-link" href="<?php echo $login_url; ?>">Login</a>
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
                    <li>Choose a Studio (Video, Image, Music, Voice, Avatar, Writing).</li>
                    <li>Import external assets from the Import page.</li>
                    <li>Save works to Gallery and organize them in Projects.</li>
                    <li>Upgrade your plan on the Credits page for more capacity.</li>
                </ul>
                <h4>Credits</h4>
                <p class="yai-muted">Each generation consumes credits based on studio type. View balance and upgrade options on the Credits page.</p>
                <button type="button" class="yai-btn--outline" data-route="credits">Open Credits</button>
            </div>
        </div>
    </div>
</div>
