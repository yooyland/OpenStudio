<?php
/**
 * Standalone Projects store CRUD smoke test (no WordPress install required).
 * Run: php scripts/test-projects-store.php
 */

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    fwrite(STDERR, "PHP 7.4+ required\n");
    exit(1);
}

$meta = [];

function sanitize_text_field($str) {
    return is_scalar($str) ? trim(strip_tags((string) $str)) : '';
}

function sanitize_textarea_field($str) {
    return is_scalar($str) ? trim((string) $str) : '';
}

function esc_url_raw($url) {
    return filter_var((string) $url, FILTER_SANITIZE_URL) ?: '';
}

function wp_generate_uuid4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_user_meta($user_id, $key, $single = false) {
    global $meta;
    $uid = (int) $user_id;
    if (!isset($meta[$uid][$key])) {
        return $single ? '' : [];
    }
    return $single ? $meta[$uid][$key] : [$meta[$uid][$key]];
}

function update_user_meta($user_id, $key, $value) {
    global $meta;
    $uid = (int) $user_id;
    $meta[$uid][$key] = $value;
    return true;
}

require_once dirname(__DIR__) . '/modules/projects/includes/class-project-store.php';

$user_id = 42;
$store = new YooY_Project_Store();

$created = $store->create($user_id, [
    'title'       => 'Hotfix Test Project',
    'description' => 'Created by test-projects-store.php',
    'type'        => 'image',
    'visibility'  => 'private',
    'status'      => 'active',
    'assets'      => [],
]);

if (empty($created['id'])) {
    fwrite(STDERR, "FAIL: create did not return id\n");
    exit(1);
}

$project_id = $created['id'];
$list = $store->list($user_id);
$found = false;
foreach ($list as $item) {
    if (($item['id'] ?? '') === $project_id) {
        $found = true;
        break;
    }
}

if (!$found) {
    fwrite(STDERR, "FAIL: GET list missing created project\n");
    exit(1);
}

$with_asset = $store->add_asset($user_id, $project_id, [
    'gallery_id' => 'work_test_1',
    'type'       => 'image',
    'title'      => 'Sample Work',
    'url'        => 'https://example.com/a.png',
    'thumbnail'  => 'https://example.com/a-thumb.png',
]);

if (!$with_asset || (int) ($with_asset['asset_count'] ?? 0) !== 1) {
    fwrite(STDERR, "FAIL: add_asset did not increment asset_count\n");
    exit(1);
}

if (empty($with_asset['thumbnail_url'])) {
    fwrite(STDERR, "FAIL: thumbnail_url not set from first asset\n");
    exit(1);
}

$store2 = new YooY_Project_Store();
$persisted = $store2->get($user_id, $project_id);
if (!$persisted || ($persisted['title'] ?? '') !== 'Hotfix Test Project') {
    fwrite(STDERR, "FAIL: persistence check after new store instance\n");
    exit(1);
}

$updated = $store->update($user_id, $project_id, ['title' => 'Updated Title']);
if (!$updated || ($updated['title'] ?? '') !== 'Updated Title') {
    fwrite(STDERR, "FAIL: update title\n");
    exit(1);
}

if (!$store->delete($user_id, $project_id)) {
    fwrite(STDERR, "FAIL: delete\n");
    exit(1);
}

if ($store->get($user_id, $project_id) !== null) {
    fwrite(STDERR, "FAIL: project still exists after delete\n");
    exit(1);
}

echo json_encode([
    'ok'               => true,
    'created_id'       => $project_id,
    'asset_count'      => 1,
    'thumbnail_url'    => $with_asset['thumbnail_url'],
    'persistence'      => true,
    'update'           => true,
    'delete'           => true,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
