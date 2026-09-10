<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-image-input-normalizer.php';
require_once __DIR__ . '/class-image-subject-scene-extractor.php';
require_once __DIR__ . '/class-image-quality-escalator.php';
require_once __DIR__ . '/class-image-prompt-fidelity.php';
require_once __DIR__ . '/class-studio-intent-analyzer.php';
require_once __DIR__ . '/class-studio-creative-brief-builder.php';
require_once __DIR__ . '/class-image-art-direction.php';
require_once __DIR__ . '/class-image-domain-prompt-composer.php';
require_once __DIR__ . '/class-studio-prompt-validator.php';
require_once __DIR__ . '/class-image-visual-qa.php';

/**
 * Premium Image Prompt Orchestration Layer.
 *
 * Priority: P1 user subject/action/scene → P2 user style → P3 refs → P4 art direction → P5 defaults → P6 negatives.
 */
final class YooY_Image_Prompt_Orchestrator {

    private YooY_Studio_Intent_Analyzer $analyzer;
    private YooY_Studio_Creative_Brief_Builder $brief_builder;
    private YooY_Image_Domain_Prompt_Composer $composer;
    private YooY_Studio_Prompt_Validator $validator;

    public function __construct() {
        $this->analyzer      = new YooY_Studio_Intent_Analyzer();
        $this->brief_builder = new YooY_Studio_Creative_Brief_Builder();
        $this->composer      = new YooY_Image_Domain_Prompt_Composer();
        $this->validator     = new YooY_Studio_Prompt_Validator();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function run(string $raw_user_request, array $params = []): array {
        try {
            return $this->run_pipeline($raw_user_request, $params);
        } catch (Exception $e) {
            // Failsafe: never block generation because orchestration failed.
            $safe = trim($raw_user_request);
            return [
                'raw_user_request'  => $raw_user_request,
                'normalized'        => ['raw' => $raw_user_request, 'normalized' => $safe, 'is_short' => mb_strlen($safe) < 40],
                'intent'            => [],
                'scene'             => [],
                'quality_escalator' => ['tier' => 'refined', 'escalate' => true, 'reasons' => ['orchestration_failsafe']],
                'creative_brief'    => ['content_domain' => 'general', 'raw_user_request' => $raw_user_request, 'primary_subject' => mb_substr($safe, 0, 120)],
                'composed_prompt'   => $safe . '. Contemporary refined professional visual, clear subject fidelity, tasteful lighting and depth.',
                'negative_prompt'   => implode(', ', class_exists('YooY_Image_Art_Direction') ? YooY_Image_Art_Direction::common_negatives() : ['low quality', 'blurry']),
                'intent_domain'     => 'general',
                'preset'            => 'GENERAL_PHOTOREAL_PREMIUM',
                'art_direction'     => 'GENERAL_PHOTOREAL_PREMIUM',
                'validation'        => ['ok' => true, 'code' => 'failsafe'],
                'quality'           => ['score' => 70],
                'visual_qa'         => ['score' => 60, 'pass' => true, 'mode' => 'prompt_metadata_qa', 'suggest_premium_retry' => false],
                'title_preview'     => '',
                'provider_quality'  => ['generation_mode' => 'premium', 'quality' => 'hd', 'prefer_large_size' => true],
                'rewrite_count'     => 0,
                'prompt_version'    => 'spi-image-orch-2',
                'pipeline'          => ['failsafe'],
                'blocked'           => false,
                'orchestration_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function run_pipeline(string $raw_user_request, array $params): array {
        $normalized = YooY_Image_Input_Normalizer::normalize($raw_user_request);
        $work = $normalized['normalized'] !== '' ? $normalized['normalized'] : $raw_user_request;

        $hint = [];
        if (!empty($params['creative_brief']) && is_array($params['creative_brief'])) {
            $hint = $params['creative_brief'];
        }
        if (!empty($params['intent_domain'])) {
            $hint['intent_domain'] = sanitize_key((string) $params['intent_domain']);
        }
        if (!empty($params['project_context']) && is_array($params['project_context'])) {
            $hint['project_context'] = $params['project_context'];
        }
        // Retry modes (user-confirmed only): premium_retry vs variation.
        $retry_mode = sanitize_key((string) ($params['retry_mode'] ?? ''));
        if ($retry_mode === '' && !empty($params['premium_retry'])) {
            $retry_mode = 'premium_retry';
        }

        $intent = $this->analyzer->analyze($work, $hint);
        $intent['raw_user_request'] = $raw_user_request;

        $scene = YooY_Image_Subject_Scene_Extractor::extract($work, $intent);
        if ($scene['subject'] !== '') {
            $intent['primary_subject'] = $scene['subject'];
        }

        $brief = $this->brief_builder->build($intent);
        $brief['raw_user_request'] = $raw_user_request;
        if (!empty($hint['primary_subject'])) {
            $brief['primary_subject'] = sanitize_text_field((string) $hint['primary_subject']);
        }
        $escalated = YooY_Image_Quality_Escalator::escalate($brief, $normalized, $scene);
        $brief = $escalated['brief'];

        $preset = YooY_Image_Art_Direction::resolve_preset($brief);
        $brief['art_direction_preset'] = $preset;

        $rewrite_count = 0;
        $composed = $this->composer->compose($brief, $params);
        $composed['preset'] = $preset;
        $composed['art_direction'] = $preset;

        // P1 fidelity lock first — art direction may enhance but not replace.
        $lock = YooY_Image_Prompt_Fidelity::lock_block($scene, $brief, $raw_user_request);
        $composed['prompt'] = $lock . '. ' . (string) $composed['prompt'];

        if ($retry_mode === 'premium_retry') {
            $composed['prompt'] = 'RETRY MODE: same concept, stronger refinement and premium art direction only — do not change core subject/action/setting. '
                . (string) $composed['prompt'];
        } elseif ($retry_mode === 'variation') {
            $composed['prompt'] = 'RETRY MODE: same concept, different composition and camera direction — keep subject fidelity. '
                . (string) $composed['prompt'];
        }

        if (!empty($escalated['bias_lines'])) {
            $prompt = (string) $composed['prompt'];
            if (stripos($prompt, 'QUALITY ESCALATOR') === false) {
                $composed['prompt'] = 'QUALITY ESCALATOR: ' . implode('; ', array_slice($escalated['bias_lines'], 0, 3))
                    . '. ' . $prompt;
            }
        }

        $genre_neg = YooY_Image_Art_Direction::genre_negatives($preset);
        if ($genre_neg) {
            $neg = (string) ($composed['negative_prompt'] ?? '');
            $extra = implode(', ', $genre_neg);
            if ($extra !== '' && strpos($neg, $genre_neg[0]) === false) {
                $composed['negative_prompt'] = $neg !== '' ? ($neg . ', ' . $extra) : $extra;
            }
        }

        // Product/beauty: blank packaging when user did not ask for typography.
        $raw_l = mb_strtolower($raw_user_request);
        if (in_array($composed['domain'] ?? '', ['product', 'beauty', 'ecommerce'], true)
            && !preg_match('/텍스트|로고|타이포|글자|label|logo|typography|text\s*on/u', $raw_l)) {
            $composed['prompt'] = rtrim((string) $composed['prompt'], '. ')
                . '. PACKAGING: blank unbranded packaging — no invented logos, no invented Korean/English label text, no random typography';
            $composed['negative_prompt'] = trim((string) ($composed['negative_prompt'] ?? '') . ', invented logos, invented brand names, Korean characters on packaging, English label text, random typography', ' ,');
        }

        $validation = $this->validator->validate($brief, $composed['prompt'], $composed['domain']);
        while (empty($validation['ok']) && !empty($validation['rewrite']) && $rewrite_count < 2) {
            $rewrite_count++;
            if (($validation['code'] ?? '') === 'unrelated_product_injection' || ($validation['code'] ?? '') === 'prompt_domain_mismatch') {
                $brief['wants_product'] = false;
                if (($brief['content_domain'] ?? '') === 'politics' || !empty($brief['wants_political'])) {
                    $brief['wants_political'] = true;
                    $brief['content_domain'] = 'politics';
                }
            }
            $composed = $this->composer->compose($brief, $params);
            $composed['preset'] = $preset;
            $composed['art_direction'] = $preset;
            $composed['prompt'] = $lock . '. ' . (string) $composed['prompt'];
            $validation = $this->validator->validate($brief, $composed['prompt'], $composed['domain']);
        }

        $composed['prompt'] = YooY_Image_Prompt_Fidelity::compress_bloat((string) $composed['prompt']);
        $quality = $this->validator->score($brief, $composed['prompt'], $validation);

        $title_preview = '';
        if (class_exists('YooY_Gallery_Title_Service')) {
            $title_preview = YooY_Gallery_Title_Service::resolve([
                'user_prompt'   => $raw_user_request,
                'prompt'        => '', // never title from orchestrated final prompt
                'intent_domain' => $composed['domain'],
                'type'          => 'image',
            ]);
        }

        $visual_qa = YooY_Image_Visual_QA::assess([
            'user_prompt'            => $raw_user_request,
            'final_prompt'           => $composed['prompt'],
            'intent_domain'          => $composed['domain'],
            'art_direction'          => $preset,
            'preset'                 => $preset,
            'display_title'          => $title_preview,
            'composer_quality_score' => (int) ($quality['score'] ?? 0),
        ]);

        $provider_quality = [
            'generation_mode'   => 'premium',
            'quality'           => 'hd',
            'prefer_large_size' => true,
        ];
        if (($params['generation_mode'] ?? '') === 'fast') {
            $provider_quality['generation_mode'] = 'fast';
            $provider_quality['quality'] = 'standard';
            $provider_quality['prefer_large_size'] = false;
        }

        return [
            'raw_user_request'  => $raw_user_request,
            'normalized'        => $normalized,
            'intent'            => $intent,
            'scene'             => $scene,
            'quality_escalator' => [
                'tier'     => $escalated['tier'],
                'escalate' => $escalated['escalate'],
                'reasons'  => $escalated['reasons'],
            ],
            'creative_brief'    => $brief,
            'composed_prompt'   => $composed['prompt'],
            'negative_prompt'   => $composed['negative_prompt'],
            'intent_domain'     => $composed['domain'],
            'preset'            => $preset,
            'art_direction'     => $preset,
            'validation'        => $validation,
            'quality'           => $quality,
            'visual_qa'         => $visual_qa,
            'title_preview'     => $title_preview,
            'provider_quality'  => $provider_quality,
            'rewrite_count'     => $rewrite_count,
            'retry_mode'        => $retry_mode,
            'prompt_version'    => 'spi-image-orch-2',
            'pipeline'          => [
                'input_normalizer',
                'intent_analyzer',
                'subject_scene_extractor',
                'quality_escalator',
                'art_direction_preset_selector',
                'domain_prompt_composer',
                'prompt_fidelity_lock',
                'negative_guidance_injector',
                'prompt_bloat_compressor',
                'final_prompt_builder',
                'provider_quality_selector',
                'prompt_metadata_qa',
                'title_generator',
            ],
            'blocked'           => false, // never block generation on soft QA
        ];
    }
}
