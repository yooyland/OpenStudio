<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_Generator {

    private YooY_Voice_API_Router $router;
    private YooY_Voice_History $history;
    private YooY_Voice_Gallery $gallery;
    private YooY_Voice_Pause $pause;
    private YooY_Voice_Advanced $advanced;

    public function __construct(
        YooY_Voice_API_Router $router,
        YooY_Voice_History $history,
        YooY_Voice_Gallery $gallery,
        YooY_Voice_Pause $pause,
        YooY_Voice_Advanced $advanced
    ) {
        $this->router   = $router;
        $this->history  = $history;
        $this->gallery  = $gallery;
        $this->pause    = $pause;
        $this->advanced = $advanced;
    }

    public function speak(int $user_id, array $params): array {
        $text = sanitize_textarea_field($params['text'] ?? '');
        if ($text === '') throw new Exception('Text is required.');

        $pause_data = $this->pause->process($text);
        $advanced   = $this->advanced->apply($params);

        $payload = array_merge($advanced, [
            'provider'       => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'auto'),
            'model'          => sanitize_text_field($params['model'] ?? $params['default_model'] ?? 'mock-tts-v1'),
            'voice_id'       => sanitize_text_field($params['voice_id'] ?? 'ko_female_warm'),
            'text'           => $text,
            'processed_text' => $pause_data['processed'],
            'pauses'         => $pause_data['pauses'],
            'emotion'        => sanitize_text_field($params['emotion'] ?? 'neutral'),
            'language'       => sanitize_text_field($params['language'] ?? 'ko'),
            'reference_assets' => $this->normalize_reference_assets($params),
            'reference_url'  => esc_url_raw($params['reference_url'] ?? ''),
        ]);

        $resolution = YooY_Provider_Resolver::apply($payload, 'voice', $user_id);

        $result = $this->router->speak($payload);
        $result = YooY_Job_Normalizer::ensure_output_or_fail($result);
        $result = YooY_Provider_Resolver::annotate($result, $resolution);
        $entry  = $this->history->add($user_id, array_merge($result, [
            'type'     => 'voice',
            'studio'   => 'voice-studio',
            'emotion'  => $payload['emotion'],
            'language' => $payload['language'],
            'speed'    => $payload['speed'],
            'pitch'    => $payload['pitch'],
        ]));

        if (!empty($params['auto_save']) && ($entry['status'] ?? '') === YooY_Job_Status::COMPLETED
            && class_exists('YooY_Asset_Generator') && YooY_Asset_Generator::has_displayable_asset($entry)) {
            $this->gallery->auto_save($user_id, $entry);
        }
        if (($entry['status'] ?? '') === YooY_Job_Status::COMPLETED
            && class_exists('YooY_Asset_Generator') && YooY_Asset_Generator::has_displayable_asset($entry)
            && function_exists('yoy_gallery_capture')) {
            yoy_gallery_capture($user_id, $entry, 'voice', 'voice-studio');
        }

        return $entry;
    }

    public function options(YooY_Voice_Catalog $catalog, int $user_id): array {
        return [
            'voices'    => array_merge($catalog->voices(), $catalog->cloned_voices($user_id)),
            'emotions'  => $catalog->emotions(),
            'languages' => $catalog->languages(),
            'advanced'  => $this->advanced->schema(),
            'pause_syntax' => '[pause:0.5s] or [pause:1s] — inline pause tags',
        ];
    }

    private function normalize_reference_assets(array $params): array {
        if (!class_exists('YooY_Reference_Asset_Service')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'reference-assets/includes/class-reference-asset-service.php';
        }
        return YooY_Reference_Asset_Service::normalize_payload_list($params['reference_assets'] ?? []);
    }
}
