<?php
if (!defined('ABSPATH')) exit;

final class YooY_WP_Admin_Console {

    public static function register(): void {
        add_action('admin_menu', [self::class, 'register_menu'], 59);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'yoy-ai-studio',
            'Operations Center',
            'Operations Center',
            'manage_options',
            'yooy_admin_console',
            [self::class, 'render']
        );
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'yoy-ai-studio_page_yooy_admin_console') {
            return;
        }
        wp_enqueue_style(
            'yoy-admin-wp-console',
            YOY_AI_STUDIO_URL . 'assets/css/admin-wp-console.css',
            [],
            YOY_AI_STUDIO_VERSION
        );
        wp_enqueue_style(
            'yoy-admin-console',
            YOY_AI_STUDIO_URL . 'assets/css/admin-console.css',
            ['yoy-admin-wp-console'],
            YOY_AI_STUDIO_VERSION
        );
        wp_enqueue_script(
            'yoy-ai-studio-core',
            YOY_AI_STUDIO_URL . 'assets/js/core.js',
            [],
            YOY_AI_STUDIO_VERSION,
            true
        );
        wp_enqueue_script(
            'yoy-admin-console',
            YOY_AI_STUDIO_URL . 'assets/js/admin-console.js',
            ['yoy-ai-studio-core'],
            YOY_AI_STUDIO_VERSION,
            true
        );
        wp_enqueue_script(
            'yoy-admin-console-providers',
            YOY_AI_STUDIO_URL . 'assets/js/admin-console-providers.js',
            ['yoy-admin-console'],
            YOY_AI_STUDIO_VERSION,
            true
        );
        wp_localize_script('yoy-ai-studio-core', 'YooYStudio', [
            'restUrl'  => esc_url_raw(rest_url('yoy-ai-studio/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'version'  => YOY_AI_STUDIO_VERSION,
            'loggedIn' => is_user_logged_in(),
            'isAdmin'  => current_user_can('manage_options'),
            'user'     => [
                'id'    => get_current_user_id(),
                'name'  => wp_get_current_user()->display_name,
                'email' => wp_get_current_user()->user_email,
            ],
        ]);
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }
        ?>
        <div class="wrap yoy-wp-admin-console">
            <div id="yai-admin-console" class="yai-admin-root yai-ops-center" data-context="wp-admin"></div>
        </div>
        <?php
    }
}
