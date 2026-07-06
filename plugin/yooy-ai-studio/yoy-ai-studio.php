<?php
/**
 * Plugin Name: YooY AI Studio
 * Description: YooY Land AI Creator OS - Core Engine connecting AI Router, Credits, Gallery, Projects, and all modules.
 * Version: 11.2.0-dev
 * Author: YooY Land
 * Text Domain: yooy-ai-studio
 */

if (!defined('ABSPATH')) exit;

define('YOY_AI_STUDIO_VERSION', '11.2.0-dev');
define('YOY_AI_STUDIO_FILE', __FILE__);
define('YOY_AI_STUDIO_DIR', plugin_dir_path(__FILE__));
define('YOY_AI_STUDIO_URL', plugin_dir_url(__FILE__));
define('YOY_AI_STUDIO_ROOT', dirname(YOY_AI_STUDIO_DIR, 2));

$modules_dir = YOY_AI_STUDIO_ROOT . '/modules/';
$providers_dir = YOY_AI_STUDIO_ROOT . '/providers/';

if (!is_dir($modules_dir)) {
    $modules_dir = YOY_AI_STUDIO_DIR . 'modules/';
}
if (!is_dir($providers_dir)) {
    $providers_dir = YOY_AI_STUDIO_DIR . 'providers/';
}

define('YOY_AI_STUDIO_MODULES_DIR', trailingslashit($modules_dir));
define('YOY_AI_STUDIO_PROVIDERS_DIR', trailingslashit($providers_dir));

require_once YOY_AI_STUDIO_DIR . 'includes/core/interface-yoy-module.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-module-base.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-module-registry.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-job-status.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-job-normalizer.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-job-store.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-credits-service.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-studio-credits.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-core-engine.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-rest-controller.php';
require_once YOY_AI_STUDIO_DIR . 'includes/class-yoy-ai-studio.php';

add_action('plugins_loaded', function () {
    $core = YooY_Core_Engine::instance();
    $core->boot();

    $rest = new YooY_REST_Controller($core);
    $rest->register();

    YooY_AI_Studio::instance($core);
}, 5);
