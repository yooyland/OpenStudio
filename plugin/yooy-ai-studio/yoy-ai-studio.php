<?php
/**
 * Plugin Name: YooY AI Studio
 * Description: YooY Land AI Creator OS - Core Engine connecting AI Router, Credits, Gallery, Projects, and all modules.
 * Version: 11.15.1
 * Requires PHP: 7.4
 * Author: YooY Land
 * Text Domain: yooy-ai-studio
 */

if (!defined('ABSPATH')) exit;

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html(
            'YooY AI Studio requires PHP 7.4 or higher. Current version: ' . PHP_VERSION
        );
        echo '</p></div>';
    });
    return;
}

define('YOY_AI_STUDIO_VERSION', '11.15.1');
define('YOY_AI_STUDIO_FILE', __FILE__);
define('YOY_AI_STUDIO_DIR', plugin_dir_path(__FILE__));
define('YOY_AI_STUDIO_URL', plugin_dir_url(__FILE__));
define('YOY_AI_STUDIO_ROOT', dirname(YOY_AI_STUDIO_DIR, 2));

/**
 * Resolve bundled or monorepo module/provider directories.
 * WordPress ZIP installs must prefer paths inside the plugin folder.
 */
function yoy_ai_studio_resolve_dir(string $subdir): string {
    $candidates = [
        YOY_AI_STUDIO_DIR . $subdir . '/',
        YOY_AI_STUDIO_ROOT . '/' . $subdir . '/',
    ];

    foreach ($candidates as $dir) {
        if (is_dir($dir)) {
            return trailingslashit($dir);
        }
    }

    return trailingslashit(YOY_AI_STUDIO_DIR . $subdir . '/');
}

define('YOY_AI_STUDIO_MODULES_DIR', yoy_ai_studio_resolve_dir('modules'));
define('YOY_AI_STUDIO_PROVIDERS_DIR', yoy_ai_studio_resolve_dir('providers'));

$core_files = [
    'includes/core/interface-yoy-module.php',
    'includes/core/class-yoy-module-base.php',
    'includes/core/class-yoy-rest-error.php',
    'includes/core/class-yoy-generation-exception.php',
    'includes/core/class-yoy-module-registry.php',
    'includes/core/class-yoy-job-status.php',
    'includes/core/class-yoy-provider-billing-error.php',
    'includes/core/class-yoy-job-normalizer.php',
    'includes/core/class-yoy-job-store.php',
    'includes/core/class-yoy-credits-service.php',
    'includes/core/class-yoy-credits-plans.php',
    'includes/core/class-yoy-image-model-resolver.php',
    'includes/core/class-yoy-image-size-resolver.php',
    'includes/core/class-yoy-studio-model-resolver.php',
    'includes/core/class-yoy-woocommerce-billing.php',
    'includes/core/class-yoy-secrets.php',
    'includes/core/class-yoy-provider-catalog.php',
    'includes/core/class-yoy-provider-resolver.php',
    'includes/core/class-yoy-provider-stats.php',
    'includes/core/class-yoy-system-log.php',
    'includes/core/class-yoy-system-diagnostics.php',
    'includes/helpers/yoy-ui-icons.php',
    'includes/core/class-yoy-studio-credits.php',
    'includes/core/class-yoy-core-engine.php',
    'includes/core/class-yoy-rest-controller.php',
    'includes/core/class-yoy-public-works-feed.php',
    'includes/core/class-yoy-user-welcome.php',
    'includes/class-yoy-ai-studio.php',
    'includes/admin/class-yoy-wp-admin-console.php',
    'official-showcase/class-yoy-official-showcase.php',
];

foreach ($core_files as $relative) {
    $path = YOY_AI_STUDIO_DIR . $relative;
    if (!is_readable($path)) {
        add_action('admin_notices', static function () use ($path) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html('YooY AI Studio: missing core file ' . $path);
            echo '</p></div>';
        });
        return;
    }
    require_once $path;
}

register_activation_hook(YOY_AI_STUDIO_FILE, static function () {
    if (class_exists('YooY_Official_Showcase')) {
        YooY_Official_Showcase::instance()->seed_if_empty();
    }
    // Ensure the /wp-json/ REST rewrite endpoint is registered on the host so
    // pretty-permalink REST calls resolve. Runs once on activation only.
    flush_rewrite_rules(false);
});

add_action('plugins_loaded', static function () {
    if (class_exists('YooY_Official_Showcase')) {
        YooY_Official_Showcase::instance()->seed_if_empty();
    }

    if (class_exists('YooY_Rest_Error')) {
        YooY_Rest_Error::register();
    }

    $core = YooY_Core_Engine::instance();
    $core->boot();

    $rest = new YooY_REST_Controller($core);
    $rest->register();

    YooY_AI_Studio::instance($core);
    YooY_WP_Admin_Console::register();

    if (class_exists('YooY_WooCommerce_Billing')) {
        YooY_WooCommerce_Billing::register();
    }

    if (class_exists('YooY_User_Welcome')) {
        YooY_User_Welcome::register();
    }
}, 5);
