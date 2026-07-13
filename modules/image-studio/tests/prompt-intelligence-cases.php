<?php
/**
 * CLI smoke tests for Studio Prompt Intelligence (Cases A–E).
 * Run: php modules/image-studio/tests/prompt-intelligence-cases.php
 */
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) { return is_string($s) ? trim($s) : ''; }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($s) { return is_string($s) ? trim($s) : ''; }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($s) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $s) ?? ''); }
}
if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) { return $value; }
}

require_once dirname(__DIR__) . '/includes/prompt-intelligence/class-studio-prompt-intelligence.php';
require_once dirname(__DIR__) . '/includes/prompt-engine/class-image-prompt-composer.php';

$cases = [
    'A' => [
        'input' => '한국 정치에서 이재명이 말하는 가장 중요한 것 광고',
        'expect_domain' => 'politics',
        'must' => ['political', 'lee jae-myung'],
        'forbid' => ['premium product photography', 'cosmetic', 'perfume', 'hero product'],
    ],
    'B' => [
        'input' => '프리미엄 향수 광고',
        'expect_domain' => 'product',
        'must' => ['product', 'perfume'],
        'forbid' => ['political editorial', 'lee jae-myung', 'election'],
    ],
    'C' => [
        'input' => '제주 관광 광고',
        'expect_domain' => 'travel',
        'must' => ['tourism', 'jeju'],
        'forbid' => ['cosmetic', 'perfume', 'product pedestal'],
    ],
    'D' => [
        'input' => '고래 가족 여행 광고',
        'expect_domain' => 'travel',
        'must' => ['tourism'],
        'forbid' => ['cosmetic bottle', 'perfume'],
    ],
    'E' => [
        'input' => '회사 소개 영상 썸네일',
        'expect_domain' => 'corporate',
        'must' => [],
        'forbid' => ['cosmetic bottle', 'perfume packshot'],
    ],
];

$intel = new YooY_Studio_Prompt_Intelligence();
$composer = new YooY_Image_Prompt_Composer();
$failed = 0;

foreach ($cases as $id => $case) {
    $run = $intel->run_for_image($case['input'], []);
    $brief = $run['creative_brief'] ?? [];
    $domain = (string) ($brief['content_domain'] ?? '');
    $prompt = mb_strtolower((string) ($run['composed_prompt'] ?? ''));
    $composed = $composer->compose([
        'user_prompt' => $case['input'],
        'prompt' => $case['input'],
        'smart_auto' => true,
        'generation_mode' => 'fast',
    ]);
    $final = mb_strtolower((string) ($composed['canonical_prompt'] ?? $composed['prompt'] ?? ''));
    $neg = mb_strtolower((string) ($composed['negative_prompt'] ?? ''));

    $ok = true;
    $notes = [];
    if ($domain !== $case['expect_domain']) {
        // corporate may fall to general/brand depending on keywords
        if (!($id === 'E' && in_array($domain, ['corporate', 'general', 'brand'], true))) {
            $ok = false;
            $notes[] = "domain got {$domain} expected {$case['expect_domain']}";
        }
    }
    foreach ($case['must'] as $m) {
        if ($m !== '' && mb_strpos($final, $m) === false && mb_strpos($prompt, $m) === false) {
            $ok = false;
            $notes[] = "missing must: {$m}";
        }
    }
    foreach ($case['forbid'] as $f) {
        if ($f !== '' && (mb_strpos($final, $f) !== false)) {
            $ok = false;
            $notes[] = "forbidden in final: {$f}";
        }
    }
    if ($id === 'A' && mb_strpos($neg, 'cosmetic') === false) {
        $ok = false;
        $notes[] = 'politics negative missing cosmetic guard';
    }

    $status = $ok ? 'PASS' : 'FAIL';
    if (!$ok) {
        $failed++;
    }
    echo "[{$status}] Case {$id} domain={$domain} score=" . ($run['quality']['score'] ?? '?') . "\n";
    echo "  final: " . mb_substr($final, 0, 160) . "…\n";
    if ($notes) {
        echo '  notes: ' . implode('; ', $notes) . "\n";
    }
}

echo $failed === 0 ? "\nALL PASS\n" : "\nFAILED: {$failed}\n";
exit($failed === 0 ? 0 : 1);
