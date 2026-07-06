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
            'provider'       => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'mock'),
            'model'          => sanitize_text_field($params['model'] ?? $params['default_model'] ?? 'mock-tts-v1'),
            'voice_id'       => sanitize_text_field($params['voice_id'] ?? 'ko_female_warm'),
            'text'           => $text,
            'processed_text' => $pause_data['processed'],
            'pauses'         => $pause_data['pauses'],
            'emotion'        => sanitize_text_field($params['emotion'] ?? 'neutral'),
            'language'       => sanitize_text_field($params['language'] ?? 'ko'),
        ]);

        $result = $this->router->speak($payload);
        $entry  = $this->history->add($user_id, array_merge($result, [
            'emotion'  => $payload['emotion'],
            'language' => $payload['language'],
            'speed'    => $payload['speed'],
            'pitch'    => $payload['pitch'],
        ]));

        if (!empty($params['auto_save'])) {
            $this->gallery->auto_save($user_id, $entry);
        }
        if (function_exists('yoy_gallery_capture')) {
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
}
