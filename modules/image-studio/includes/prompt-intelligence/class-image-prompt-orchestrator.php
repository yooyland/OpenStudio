<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-image-input-normalizer.php';
require_once __DIR__ . '/class-image-subject-scene-extractor.php';
require_once __DIR__ . '/class-image-quality-escalator.php';
require_once __DIR__ . '/class-studio-intent-analyzer.php';
require_once __DIR__ . '/class-studio-creative-brief-builder.php';
require_once __DIR__ . '/class-image-art-direction.php';
require_once __DIR__ . '/class-image-domain-prompt-composer.php';
require_once __DIR__ . '/class-studio-prompt-validator.php';
require_once __DIR__ . '/class-image-visual-qa.php';

/**
 * Premium Image Prompt Orchestration Layer.
 *
 * Pipeline:
 * Input Normalizer → Intent Analyzer → Subject/Scene Extractor → Quality Escalator
 * → Art Direction Preset Selector → Domain Prompt Composer → Negative Guidance Injector
 * → Final Prompt Builder → (Provider/Quality Selector outside) → Visual QA Lite → Title Generator (Gallery)
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
        // 1) Input Normalizer
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

        // 2) Intent Analyzer
        $intent = $this->analyzer->analyze($work, $hint);
        $intent['raw_user_request'] = $raw_user_request;

        // 3) Subject / Scene Extractor
        $scene = YooY_Image_Subject_Scene_Extractor::extract($work, $intent);
        if ($scene['subject'] !== '') {
            $intent['primary_subject'] = $scene['subject'];
        }

        // 4) Creative brief + 5) Quality Escalator
        $brief = $this->brief_builder->build($intent);
        $brief['raw_user_request'] = $raw_user_request;
        if (!empty($hint['primary_subject'])) {
            $brief['primary_subject'] = sanitize_text_field((string) $hint['primary_subject']);
        }
        $escalated = YooY_Image_Quality_Escalator::escalate($brief, $normalized, $scene);
        $brief = $escalated['brief'];

        // 6) Art Direction Preset Selector
        $preset = YooY_Image_Art_Direction::resolve_preset($brief);
        $brief['art_direction_preset'] = $preset;

        // 7) Domain Prompt Composer (+ 8 Negative Guidance Injector via finalize)
        $rewrite_count = 0;
        $composed = $this->composer->compose($brief, $params);
        // Force canonical premium preset id on output.
        $composed['preset'] = $preset;
        $composed['art_direction'] = $preset;
        if (!empty($escalated['bias_lines'])) {
            $prompt = (string) $composed['prompt'];
            $esc = 'QUALITY ESCALATOR (' . $escalated['tier'] . '): ' . implode('; ', $escalated['bias_lines']);
            if (stripos($prompt, 'QUALITY ESCALATOR') === false) {
                $composed['prompt'] = $esc . '. ' . $prompt;
            }
        }
        // Genre negatives already merged in domain composer finalize; ensure genre extras once more.
        $genre_neg = YooY_Image_Art_Direction::genre_negatives($preset);
        if ($genre_neg) {
            $neg = (string) ($composed['negative_prompt'] ?? '');
            $extra = implode(', ', $genre_neg);
            if ($extra !== '' && strpos($neg, $genre_neg[0]) === false) {
                $composed['negative_prompt'] = $neg !== '' ? ($neg . ', ' . $extra) : $extra;
            }
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
            $validation = $this->validator->validate($brief, $composed['prompt'], $composed['domain']);
        }

        // 9) Final Prompt Builder meta
        $quality = $this->validator->score($brief, $composed['prompt'], $validation);

        // Title preview (Gallery remains SoT on save)
        $title_preview = '';
        if (class_exists('YooY_Gallery_Title_Service')) {
            $title_preview = YooY_Gallery_Title_Service::resolve([
                'user_prompt'    => $raw_user_request,
                'prompt'         => $composed['prompt'],
                'intent_domain'  => $composed['domain'],
                'type'           => 'image',
            ]);
        }

        // 10) Visual QA Lite (pre-generation prompt QA; post-gen reuses same schema)
        $visual_qa = YooY_Image_Visual_QA::assess([
            'user_prompt'            => $raw_user_request,
            'final_prompt'           => $composed['prompt'],
            'intent_domain'          => $composed['domain'],
            'art_direction'          => $preset,
            'preset'                 => $preset,
            'display_title'          => $title_preview,
            'composer_quality_score' => (int) ($quality['score'] ?? 0),
        ]);

        // 11) Provider/Quality Selector hints (applied by Image Generator normalize)
        $provider_quality = [
            'generation_mode' => 'premium',
            'quality'         => ($escalated['tier'] === 'premium' || empty($params['generation_mode']) || ($params['generation_mode'] ?? '') === 'premium')
                ? 'hd'
                : (string) ($params['quality'] ?? 'standard'),
            'prefer_large_size' => true,
        ];
        if (($params['generation_mode'] ?? '') === 'fast') {
            $provider_quality['generation_mode'] = 'fast';
            $provider_quality['quality'] = 'standard';
            $provider_quality['prefer_large_size'] = false;
        }

        return [
            'raw_user_request'   => $raw_user_request,
            'normalized'         => $normalized,
            'intent'             => $intent,
            'scene'              => $scene,
            'quality_escalator'  => [
                'tier'     => $escalated['tier'],
                'escalate' => $escalated['escalate'],
                'reasons'  => $escalated['reasons'],
            ],
            'creative_brief'     => $brief,
            'composed_prompt'    => $composed['prompt'],
            'negative_prompt'    => $composed['negative_prompt'],
            'intent_domain'      => $composed['domain'],
            'preset'             => $preset,
            'art_direction'      => $preset,
            'validation'         => $validation,
            'quality'            => $quality,
            'visual_qa'          => $visual_qa,
            'title_preview'      => $title_preview,
            'provider_quality'   => $provider_quality,
            'rewrite_count'      => $rewrite_count,
            'prompt_version'     => 'spi-image-orch-1',
            'pipeline'           => [
                'input_normalizer',
                'intent_analyzer',
                'subject_scene_extractor',
                'quality_escalator',
                'art_direction_preset_selector',
                'domain_prompt_composer',
                'negative_guidance_injector',
                'final_prompt_builder',
                'provider_quality_selector',
                'visual_qa_lite',
                'title_generator',
            ],
            'blocked'            => empty($validation['ok']) || (($quality['score'] ?? 0) < 60),
        ];
    }
}
