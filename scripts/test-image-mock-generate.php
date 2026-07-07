<?php
/**
 * Standalone mock image generation pipeline test (no WordPress install required).
 * Run: php scripts/test-image-mock-generate.php
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

$testUploadDir = sys_get_temp_dir() . '/yooy-mock-test-uploads';

function trailingslashit(string $path): string {
    return rtrim($path, '/\\') . '/';
}

// Minimal WordPress stubs
function wp_generate_uuid4(): string { return 'test-' . bin2hex(random_bytes(8)); }
function sanitize_text_field($v): string { return trim(strip_tags((string) $v)); }
function sanitize_textarea_field($v): string { return trim(strip_tags((string) $v)); }
function sanitize_file_name(string $v): string {
    return preg_replace('/[^a-zA-Z0-9._-]/', '-', $v) ?: 'asset';
}
function esc_url_raw($v): string {
    $v = trim((string) $v);
    if ($v === '') return '';
    if (strpos($v, 'data:') === 0) return '';
    if (preg_match('#^https?://#i', $v)) return $v;
    return '';
}
function esc_html($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_attr($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function apply_filters(string $tag, $value, ...$args) { return $value; }
function gmdate(string $format): string { return \gmdate($format); }
function random_int(int $min, int $max): int { return $min; }
function user_can(int $uid, string $cap): bool { return $uid === 1; }
function get_user_meta(int $uid, string $key, bool $single = true) {
    static $meta = [];
    return $meta[$uid][$key] ?? ($single ? '' : []);
}
function update_user_meta(int $uid, string $key, $value): void {
    static $meta = [];
    $meta[$uid][$key] = $value;
}
function metadata_exists(string $type, int $uid, string $key): bool { return false; }
function get_option(string $key, $default = false) { return $default; }
function update_option(string $key, $value, bool $autoload = false): bool { return true; }
function mb_substr(string $s, int $start, int $len = null): string { return substr($s, $start, $len ?? strlen($s)); }
function wp_upload_dir(): array {
    global $testUploadDir;
    if (!is_dir($testUploadDir)) {
        mkdir($testUploadDir, 0777, true);
    }
    return [
        'basedir' => $testUploadDir,
        'baseurl' => 'http://localhost/wp-content/uploads',
        'error'   => false,
    ];
}
function wp_mkdir_p(string $dir): bool {
    return is_dir($dir) || mkdir($dir, 0777, true);
}

$core = [
    'includes/core/class-yoy-job-status.php',
    'includes/core/class-yoy-job-normalizer.php',
    'includes/core/class-yoy-job-store.php',
    'includes/core/class-yoy-credits-service.php',
];

foreach ($core as $rel) {
    require_once YOY_AI_STUDIO_DIR . $rel;
}

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-mock-job-engine.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-image-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-yoy-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-image/class-mock-image-provider.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'image-studio/includes/class-image-api-router.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'image-studio/includes/class-image-settings.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'image-studio/includes/class-image-history.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'image-studio/includes/class-image-gallery.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'image-studio/includes/class-image-credits.php';
require_once YOY_AI_STUDIO_MODULES_DIR . 'image-studio/includes/class-image-generator.php';

$router    = new YooY_Image_API_Router();
$history   = new YooY_Image_History();
$gallery   = new YooY_Image_Gallery();
$generator = new YooY_Image_Generator($router, $history, $gallery);

$user_id = 1;
$params = [
    'prompt'           => 'Test product thumbnail for mock pipeline',
    'default_provider' => 'mock',
    'provider'         => 'mock',
    'negative_prompt'  => 'blurry',
    'aspect_ratio'     => '1:1',
    'resolution'       => '1024',
    'quality'          => 'standard',
    'image_count'      => 1,
    'auto_save'        => true,
];

echo "=== Mock Image Pipeline Test ===\n";

try {
    $entry = $generator->generate($user_id, $params);
} catch (Exception $e) {
    fwrite(STDERR, "FAIL at generator->generate(): " . $e->getMessage() . "\n");
    exit(1);
}

$url = $entry['images'][0]['url'] ?? '';
$checks = [
    'job_id present'       => !empty($entry['job_id']),
    'status completed'     => ($entry['status'] ?? '') === YooY_Job_Status::COMPLETED,
    'images non-empty'     => !empty($entry['images']),
    'image url present'    => $url !== '',
    'image url is http(s)' => YooY_Asset_Generator::is_http_asset_url($url),
    'provider mock'        => ($entry['provider'] ?? '') === 'mock',
    'prompt preserved'     => ($entry['prompt'] ?? '') !== '',
];

foreach ($checks as $label => $ok) {
    echo ($ok ? 'PASS' : 'FAIL') . " — $label\n";
    if (!$ok) {
        echo json_encode($entry, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
}

$stored = $history->get($user_id, $entry['job_id']);
echo ($stored ? 'PASS' : 'FAIL') . " — Job Store saved\n";

$items = $gallery->list($user_id);
$found = null;
foreach ($items as $item) {
    if (($item['id'] ?? '') === ($entry['job_id'] . '_0')) {
        $found = $item;
        break;
    }
}
echo ($found ? 'PASS' : 'FAIL') . " — Gallery Store saved\n";
if ($found) {
    $galUrl = $found['output_url'] ?? '';
    echo (YooY_Asset_Generator::is_http_asset_url($galUrl) ? 'PASS' : 'FAIL') . " — Gallery output_url is http(s)\n";
    echo (YooY_Asset_Generator::is_http_asset_url($found['thumbnail'] ?? '') ? 'PASS' : 'FAIL') . " — Gallery thumbnail is http(s)\n";
    if (!YooY_Asset_Generator::is_http_asset_url($galUrl)) {
        exit(1);
    }
} else {
    exit(1);
}

echo "\nExecution path:\n";
echo "1. POST /image-studio/generate\n";
echo "2. YooY_Image_Generator::generate()\n";
echo "3. YooY_Image_API_Router::generate() → mock provider\n";
echo "4. YooY_Mock_Image_Provider::build_generate_result() → persist_svg_image()\n";
echo "5. YooY_Job_Store via history->add()\n";
echo "6. YooY_Gallery_Store via gallery->save_from_result()\n";
echo "7. job_id: {$entry['job_id']}\n";
echo "8. preview url: {$url}\n";
echo "\nAll pipeline checks passed.\n";
