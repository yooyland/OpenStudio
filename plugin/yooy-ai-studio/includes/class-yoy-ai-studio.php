<?php
if (!defined('ABSPATH')) exit;

final class YooY_AI_Studio {

    private static ?self $instance = null;

    private YooY_Core_Engine $core;

    public static function instance(?YooY_Core_Engine $core = null): self {
        if (self::$instance === null) {
            self::$instance = new self($core ?? YooY_Core_Engine::instance());
        }
        return self::$instance;
    }

    private function __construct(YooY_Core_Engine $core) {
        $this->core = $core;

        add_shortcode('yoy_ai_studio', [$this, 'render_studio']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function core(): YooY_Core_Engine {
        return $this->core;
    }

    public function enqueue_assets(): void {
        if (!$this->should_load_assets()) {
            return;
        }

        wp_enqueue_style(
            'yoy-ai-studio',
            YOY_AI_STUDIO_URL . 'assets/css/studio.css',
            [],
            YOY_AI_STUDIO_VERSION
        );

        wp_enqueue_script(
            'yoy-ai-studio-core',
            YOY_AI_STUDIO_URL . 'assets/js/core.js',
            [],
            YOY_AI_STUDIO_VERSION,
            true
        );

        wp_enqueue_style(
            'yoy-gallery',
            YOY_AI_STUDIO_URL . 'assets/modules/gallery/gallery.css',
            ['yoy-ai-studio'],
            YOY_AI_STUDIO_VERSION
        );

        wp_enqueue_script(
            'yoy-gallery-api',
            YOY_AI_STUDIO_URL . 'assets/modules/gallery/gallery-api.js',
            ['yoy-ai-studio-core'],
            YOY_AI_STUDIO_VERSION,
            true
        );

        wp_enqueue_script(
            'yoy-gallery',
            YOY_AI_STUDIO_URL . 'assets/modules/gallery/gallery.js',
            ['yoy-gallery-api'],
            YOY_AI_STUDIO_VERSION,
            true
        );

        wp_enqueue_script(
            'yoy-ai-studio',
            YOY_AI_STUDIO_URL . 'assets/js/studio.js',
            ['yoy-ai-studio-core', 'yoy-gallery-api', 'yoy-gallery'],
            YOY_AI_STUDIO_VERSION,
            true
        );

        $user = wp_get_current_user();

        wp_localize_script('yoy-ai-studio-core', 'YooYStudio', [
            'restUrl'   => esc_url_raw(rest_url('yoy-ai-studio/v1')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'version'   => YOY_AI_STUDIO_VERSION,
            'loggedIn'  => is_user_logged_in(),
            'user'      => [
                'id'    => $user->ID,
                'name'  => $user->display_name ?: 'Guest',
                'email' => $user->user_email ?: '',
            ],
            'modules'   => $this->core->registry()->ids(),
            'routes'    => $this->nav_routes(),
        ]);
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'toplevel_page_yoy-ai-studio') {
            return;
        }

        wp_enqueue_style(
            'yoy-ai-studio-admin',
            YOY_AI_STUDIO_URL . 'assets/css/admin.css',
            [],
            YOY_AI_STUDIO_VERSION
        );
    }

    public function register_admin_menu(): void {
        add_menu_page(
            'YooY AI Studio',
            'YooY AI Studio',
            'manage_options',
            'yoy-ai-studio',
            [$this, 'render_admin'],
            'dashicons-superhero',
            58
        );
    }

    public function render_admin(): void {
        $status  = $this->core->status();
        $modules = $this->core->registry()->configs();
        ?>
        <div class="wrap yoy-admin">
            <h1>YooY AI Studio — Core Engine</h1>
            <p class="description">모든 Module을 연결하는 중심 엔진 관리 콘솔</p>

            <div class="yoy-admin-grid">
                <div class="yoy-admin-card">
                    <h2>Engine Status</h2>
                    <table class="widefat striped">
                        <tbody>
                            <tr><th>Engine</th><td><?php echo esc_html($status['engine']); ?></td></tr>
                            <tr><th>Version</th><td><?php echo esc_html($status['version']); ?></td></tr>
                            <tr><th>Modules</th><td><?php echo esc_html((string) $status['modules']); ?></td></tr>
                            <tr><th>REST Base</th><td><code><?php echo esc_html($status['rest_base']); ?></code></td></tr>
                            <tr><th>Providers Dir</th><td><?php echo $status['providers'] ? '✓ Connected' : '✗ Missing'; ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="yoy-admin-card">
                    <h2>Loaded Modules</h2>
                    <table class="widefat striped">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Version</th><th>REST</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules as $module) : ?>
                                <tr>
                                    <td><code><?php echo esc_html($module['id']); ?></code></td>
                                    <td><?php echo esc_html($module['name']); ?></td>
                                    <td><?php echo esc_html($module['version']); ?></td>
                                    <td><code>/<?php echo esc_html($module['routes']); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_studio(): string {
        ob_start();
        include YOY_AI_STUDIO_DIR . 'templates/studio-shell.php';
        return (string) ob_get_clean();
    }

    private function should_load_assets(): bool {
        if (is_admin()) {
            return false;
        }

        global $post;
        return $post instanceof WP_Post && has_shortcode($post->post_content, 'yoy_ai_studio');
    }

    private function nav_routes(): array {
        return [
            ['id' => 'home', 'label' => 'Home', 'module' => null],
            ['id' => 'projects', 'label' => 'Projects', 'module' => 'projects'],
            ['id' => 'video', 'label' => 'Video', 'module' => 'video-studio'],
            ['id' => 'image', 'label' => 'Image', 'module' => 'image-studio'],
            ['id' => 'music', 'label' => 'Music', 'module' => 'music-studio'],
            ['id' => 'voice', 'label' => 'Voice', 'module' => 'voice-studio'],
            ['id' => 'avatar', 'label' => 'Avatar', 'module' => 'avatar-studio'],
            ['id' => 'writing', 'label' => 'Writing', 'module' => 'ai-router'],
            ['id' => 'prompt-library', 'label' => 'Prompts', 'module' => 'prompt-library'],
            ['id' => 'market', 'label' => 'Market', 'module' => 'marketplace'],
            ['id' => 'community', 'label' => 'Community', 'module' => 'community'],
            ['id' => 'works', 'label' => 'Gallery', 'module' => 'gallery'],
            ['id' => 'credits', 'label' => 'Credits', 'module' => 'credits'],
            ['id' => 'settings', 'label' => 'Settings', 'module' => 'settings'],
        ];
    }
}
