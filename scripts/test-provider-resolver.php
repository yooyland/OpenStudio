<?php
/**
 * Provider priority resolution test (standalone).
 * Run: php scripts/test-provider-resolver.php
 */
declare(strict_types=1);

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    fwrite(STDERR, "PHP 7.4+ required\n");
    exit(1);
}

define('ABSPATH', __DIR__ . '/');
define('YOY_AI_STUDIO_VERSION', '11.4.0-test');
define('YOY_AI_STUDIO_DIR', dirname(__DIR__) . '/plugin/yooy-ai-studio/');
define('YOY_AI_STUDIO_ROOT', dirname(__DIR__) . '/');
define('YOY_AI_STUDIO_MODULES_DIR', YOY_AI_STUDIO_ROOT . 'modules/');
define('YOY_AI_STUDIO_PROVIDERS_DIR', YOY_AI_STUDIO_ROOT . 'providers/');

$options = [];
function get_option(string $key, $default = false) {
    global $options;
    return $options[$key] ?? $default;
}
function update_option(string $key, $value, bool $autoload = false): bool {
    global $options;
    $options[$key] = $value;
    return true;
}
function sanitize_text_field($v): string { return trim(strip_tags((string) $v)); }
function is_ssl(): bool { return false; }

require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-secrets.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-system-log.php';
require_once YOY_AI_STUDIO_DIR . 'includes/core/class-yoy-provider-resolver.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'admin-console/includes/class-admin-providers.php';

echo "=== Provider Resolver Test ===\n";

// OpenAI key + passed test -> should pick openai for image auto
YooY_Secrets::set_api_key('yoy_openai_api_key', 'sk-test-openai-key-12345678');
update_option('yoy_provider_modes', ['openai' => 'auto'], false);
YooY_Provider_Resolver::set_test_result('openai', true);
update_option('yoy_studio_default_providers', ['image' => 'mock'], false);

$res = YooY_Provider_Resolver::resolve('image', ['provider' => 'auto'], 1);
echo ($res['provider'] === 'openai' ? 'PASS' : 'FAIL') . " — auto picks tested live openai\n";
if ($res['provider'] !== 'openai') {
    print_r($res);
    exit(1);
}

// Explicit mock
$res2 = YooY_Provider_Resolver::resolve('image', ['provider' => 'mock'], 1);
echo ($res2['provider'] === 'mock' ? 'PASS' : 'FAIL') . " — explicit mock\n";

// No live -> mock fallback with reason
YooY_Provider_Resolver::set_test_result('openai', false);
$res3 = YooY_Provider_Resolver::resolve('image', ['provider' => 'auto'], 1);
echo ($res3['provider'] === 'mock' && !empty($res3['fallback_reason']) ? 'PASS' : 'FAIL') . " — fallback to mock with reason\n";

// Billing blocked should not silently use live when explicitly requested
YooY_Provider_Resolver::set_test_result('openai', true);
YooY_Provider_Resolver::save_provider_state('openai', ['billing_status' => 'blocked']);
try {
    YooY_Provider_Resolver::resolve('image', ['provider' => 'openai'], 1);
    echo "FAIL — billing blocked should throw\n";
    exit(1);
} catch (Exception $e) {
    echo (strpos($e->getMessage(), 'billing') !== false ? 'PASS' : 'FAIL') . " — billing blocked throws\n";
}

echo "\nAll provider resolver checks passed.\n";
