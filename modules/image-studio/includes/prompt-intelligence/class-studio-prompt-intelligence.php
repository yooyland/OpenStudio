<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-studio-intent-analyzer.php';
require_once __DIR__ . '/class-studio-creative-brief-builder.php';
require_once __DIR__ . '/class-image-domain-prompt-composer.php';
require_once __DIR__ . '/class-studio-prompt-validator.php';

/**
 * Studio Prompt Intelligence orchestrator (Image-first, reusable).
 * No new Store — pure transform layer.
 */
final class YooY_Studio_Prompt_Intelligence {

    private YooY_Studio_Intent_Analyzer $analyzer;
    private YooY_Studio_Creative_Brief_Builder $brief_builder;
    private YooY_Image_Domain_Prompt_Composer $image_composer;
    private YooY_Studio_Prompt_Validator $validator;

    public function __construct() {
        $this->analyzer       = new YooY_Studio_Intent_Analyzer();
        $this->brief_builder  = new YooY_Studio_Creative_Brief_Builder();
        $this->image_composer = new YooY_Image_Domain_Prompt_Composer();
        $this->validator      = new YooY_Studio_Prompt_Validator();
    }

    /**
     * @param string               $raw_user_request
     * @param array<string, mixed> $params May include creative_brief, intent_domain, settings.
     * @return array<string, mixed>
     */
    public function run_for_image(string $raw_user_request, array $params = []): array {
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

        $intent = $this->analyzer->analyze($raw_user_request, $hint);
        $brief  = $this->brief_builder->build($intent);

        // Prefer incoming brief fields without overriding primary subject when present
        if (!empty($hint['primary_subject'])) {
            $brief['primary_subject'] = sanitize_text_field((string) $hint['primary_subject']);
        }

        $rewrite_count = 0;
        $composed = $this->image_composer->compose($brief, $params);
        $validation = $this->validator->validate($brief, $composed['prompt'], $composed['domain']);

        while (empty($validation['ok']) && !empty($validation['rewrite']) && $rewrite_count < 2) {
            $rewrite_count++;
            // Force domain-safe recompose
            if (($validation['code'] ?? '') === 'unrelated_product_injection' || ($validation['code'] ?? '') === 'prompt_domain_mismatch') {
                $brief['wants_product'] = false;
                if (($brief['content_domain'] ?? '') === 'politics' || !empty($brief['wants_political'])) {
                    $brief['wants_political'] = true;
                    $brief['content_domain'] = 'politics';
                    $brief['ad_subtype'] = 'political_advertisement';
                }
            }
            $composed = $this->image_composer->compose($brief, $params);
            $validation = $this->validator->validate($brief, $composed['prompt'], $composed['domain']);
        }

        $quality = $this->validator->score($brief, $composed['prompt'], $validation);

        if (defined('YOOY_DEBUG') && YOOY_DEBUG && class_exists('YooY_System_Log')) {
            YooY_System_Log::write('info', 'prompt_intelligence', [
                'raw_user_request' => mb_substr($raw_user_request, 0, 200),
                'intent_domain'    => $brief['content_domain'] ?? '',
                'quality_score'    => $quality['score'] ?? 0,
                'rewrite_count'    => $rewrite_count,
                'provider'         => $params['provider'] ?? '',
                'model'            => $params['model'] ?? '',
            ]);
        }

        return [
            'raw_user_request' => $raw_user_request,
            'intent'           => $intent,
            'creative_brief'   => $brief,
            'composed_prompt'  => $composed['prompt'],
            'negative_prompt'  => $composed['negative_prompt'],
            'intent_domain'    => $composed['domain'],
            'validation'       => $validation,
            'quality'          => $quality,
            'rewrite_count'    => $rewrite_count,
            'prompt_version'   => 'spi-image-1',
            'blocked'          => empty($validation['ok']) || (($quality['score'] ?? 0) < 60),
        ];
    }
}
