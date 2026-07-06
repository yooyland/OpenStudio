<?php
if (!defined('ABSPATH')) exit;

$user = wp_get_current_user();
$core = YooY_Core_Engine::instance();
?>
<div class="yai-app" id="yai-app" data-version="<?php echo esc_attr(YOY_AI_STUDIO_VERSION); ?>">
    <aside class="yai-sidebar">
        <button class="yai-logo" data-route="home" type="button">
            <img src="https://yooyland.com/wp-content/uploads/2026/05/android-icon-monochrome.png" alt="YOY">
        </button>

        <nav class="yai-nav">
            <button data-route="projects" type="button">Projects</button>
            <button data-route="video" type="button">Video</button>
            <button data-route="image" type="button">Image</button>
            <button data-route="music" type="button">Music</button>
            <button data-route="voice" type="button">Voice</button>
            <button data-route="avatar" type="button">Avatar</button>
            <button data-route="writing" type="button">Writing</button>
            <button data-route="prompt-library" type="button">Prompts</button>
            <button data-route="market" type="button">Market</button>
            <button data-route="community" type="button">Community</button>
            <button data-route="works" type="button">My Works</button>
            <button data-route="credits" type="button">Credits</button>
            <button data-route="settings" type="button">Settings</button>
        </nav>

        <div class="yai-profile" id="yai-profile">
            <strong><?php echo esc_html($user->display_name ?: 'Guest'); ?></strong>
            <span><?php echo esc_html($user->user_email ?: 'Login required'); ?></span>
            <b id="yai-credits">Credits: —</b>
        </div>
    </aside>

    <main class="yai-main">
        <section class="yai-hero" data-page="home">
            <p class="yai-kicker">YooY AI Studio</p>
            <h1>오늘 무엇을 만들까요?</h1>
            <textarea id="yai-home-prompt" placeholder="한국 화장품 광고, 스마트스토어 제품 영상, K-pop 스타일 음악 등 원하는 작업을 입력하세요."></textarea>
            <div class="yai-actions">
                <button data-route="video" type="button">Video</button>
                <button data-route="image" type="button">Image</button>
                <button data-route="music" type="button">Music</button>
                <button data-route="writing" type="button">Writing</button>
            </div>

            <div class="yai-engine-status">
                <span>Core Engine v<?php echo esc_html($core->version()); ?></span>
                <span><?php echo esc_html((string) $core->registry()->count()); ?> modules loaded</span>
            </div>

            <h2>YooY Official Showcase</h2>
            <div class="yai-grid" id="yai-showcase" data-module="gallery">
                <article class="yai-skeleton"><span>Loading</span><h3>...</h3><p>...</p></article>
            </div>
        </section>

        <section class="yai-page" data-page="projects" data-module="projects">
            <h1>Projects</h1>
            <p class="yai-desc">프로젝트 워크스페이스 — 생성 히스토리와 작업을 관리합니다.</p>
            <div class="yai-panel" id="yai-projects-list"></div>
        </section>

        <section class="yai-page yai-page--video" data-page="video" data-module="video-studio">
            <div id="yai-video-studio"></div>
        </section>

        <section class="yai-page yai-page--image" data-page="image" data-module="image-studio">
            <div id="yai-image-studio"></div>
        </section>

        <section class="yai-page yai-page--music" data-page="music" data-module="music-studio">
            <div id="yai-music-studio"></div>
        </section>

        <section class="yai-page yai-page--voice" data-page="voice" data-module="voice-studio">
            <div id="yai-voice-studio"></div>
        </section>

        <section class="yai-page yai-page--avatar" data-page="avatar" data-module="avatar-studio">
            <div id="yai-avatar-studio"></div>
        </section>

        <section class="yai-page" data-page="writing" data-module="ai-router" data-type="writing">
            <h1>AI Writing Studio</h1>
            <div class="yai-generator" id="yai-gen-writing"></div>
        </section>

        <section class="yai-page" data-page="prompt-library" data-module="prompt-library">
            <h1>Prompt Library</h1>
            <p class="yai-desc">저장된 프롬프트, 공식 템플릿, 한국 컨텍스트 프리셋.</p>
            <div class="yai-panel" id="yai-prompts"></div>
        </section>

        <section class="yai-page" data-page="market" data-module="marketplace">
            <h1>Marketplace</h1>
            <p class="yai-desc">프롬프트 템플릿, 가이드, 크리에이터 마켓.</p>
            <div class="yai-panel" id="yai-marketplace"></div>
        </section>

        <section class="yai-page" data-page="community" data-module="community">
            <h1>Community</h1>
            <p class="yai-desc">크리에이터 피드, 공개 갤러리, 프롬프트 공유.</p>
            <div class="yai-panel" id="yai-community"></div>
        </section>

        <section class="yai-page" data-page="works" data-module="gallery">
            <h1>Gallery</h1>
            <p class="yai-desc">영상, 이미지, 음악, 글, 아바타, 음성 — 모든 생성물을 한곳에서 관리합니다.</p>
            <div class="yai-panel" id="yai-works"></div>
        </section>

        <section class="yai-page" data-page="credits" data-module="credits">
            <h1>Credits</h1>
            <p class="yai-desc">크레딧 잔액, 사용 내역, 플랜 비교.</p>
            <div class="yai-panel" id="yai-credits-panel"></div>
        </section>

        <section class="yai-page" data-page="settings" data-module="settings">
            <h1>Settings</h1>
            <p class="yai-desc">스튜디오 설정, Provider, 한국 컨텍스트 옵션.</p>
            <div class="yai-panel" id="yai-settings"></div>
        </section>
    </main>
</div>
