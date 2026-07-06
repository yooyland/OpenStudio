<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_Generator {

    private YooY_Avatar_API_Router $router;
    private YooY_Avatar_History $history;
    private YooY_Avatar_Gallery $gallery;
    private YooY_Avatar_Subtitle $subtitle;
    private YooY_Avatar_Catalog $catalog;

    public function __construct(
        YooY_Avatar_API_Router $router,
        YooY_Avatar_History $history,
        YooY_Avatar_Gallery $gallery,
        YooY_Avatar_Subtitle $subtitle,
        YooY_Avatar_Catalog $catalog
    ) {
        $this->router    = $router;
        $this->history   = $history;
        $this->gallery   = $gallery;
        $this->subtitle  = $subtitle;
        $this->catalog   = $catalog;
    }

    public function generate(int $user_id, array $params): array {
        $payload = $this->normalize($params);

        if (empty($payload['script'])) {
            throw new Exception('Script is required.');
        }

        if (!empty($payload['korean_context']) && $payload['subtitle_language'] === 'ko') {
            $payload['script'] = $this->apply_korean_context($payload['script'], $payload);
        }

        $subtitle_data = $this->subtitle->generate($payload);
        $payload['subtitle'] = $subtitle_data;

        $result = $this->router->generate($payload);

        if (!empty($subtitle_data['enabled']) && isset($result['output'])) {
            $result['output']['subtitle_srt'] = $subtitle_data['srt'];
            $result['output']['subtitle_tracks'] = $subtitle_data['tracks'];
        }

        $entry = $this->history->add($user_id, array_merge($result, [
            'script'           => $payload['script'],
            'avatar_id'        => $payload['avatar_id'],
            'voice_id'         => $payload['voice_id'],
            'expression'       => $payload['expression'],
            'gesture'          => $payload['gesture'],
            'camera'           => $payload['camera'],
            'emotion'          => $payload['emotion'],
            'background'       => $payload['background'],
            'scene_id'         => $payload['scene_id'],
            'lip_sync'         => $payload['lip_sync'],
            'subtitle_enabled' => $payload['subtitle_enabled'],
        ]));

        if (function_exists('yoy_gallery_capture')) {
            yoy_gallery_capture($user_id, $entry, 'avatar', 'avatar-studio');
        }

        if (!empty($params['auto_save'])) {
            $this->gallery->auto_save($user_id, $entry);
        }

        return $entry;
    }

    public function options(): array {
        return [
            'avatars'      => $this->catalog->avatars(),
            'voices'       => $this->catalog->voices(),
            'expressions'  => $this->catalog->expressions(),
            'gestures'     => $this->catalog->gestures(),
            'cameras'      => $this->catalog->cameras(),
            'emotions'     => $this->catalog->emotions(),
            'backgrounds'  => $this->catalog->backgrounds(),
            'scenes'       => $this->catalog->scenes(),
            'aspect_ratios'=> ['16:9', '9:16', '1:1'],
            'durations'    => [15, 30, 60, 120],
        ];
    }

    private function normalize(array $params): array {
        return [
            'provider'         => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'mock'),
            'model'            => sanitize_text_field($params['model'] ?? $params['default_model'] ?? 'mock-avatar-v1'),
            'avatar_id'        => sanitize_text_field($params['avatar_id'] ?? 'ko_female_01'),
            'voice_id'         => sanitize_text_field($params['voice_id'] ?? 'ko_female_warm'),
            'script'           => sanitize_textarea_field($params['script'] ?? ''),
            'lip_sync'         => !isset($params['lip_sync']) || !empty($params['lip_sync']),
            'expression'       => sanitize_text_field($params['expression'] ?? 'friendly'),
            'gesture'          => sanitize_text_field($params['gesture'] ?? 'natural'),
            'camera'           => sanitize_text_field($params['camera'] ?? 'medium'),
            'emotion'          => sanitize_text_field($params['emotion'] ?? 'confident'),
            'subtitle_enabled' => !isset($params['subtitle_enabled']) || !empty($params['subtitle_enabled']),
            'subtitle_language'=> sanitize_text_field($params['subtitle_language'] ?? 'ko'),
            'subtitle_style'   => sanitize_text_field($params['subtitle_style'] ?? 'default'),
            'background'       => sanitize_text_field($params['background'] ?? 'studio'),
            'scene_id'         => sanitize_text_field($params['scene_id'] ?? 'product_intro'),
            'aspect_ratio'     => sanitize_text_field($params['aspect_ratio'] ?? '16:9'),
            'duration'         => min(120, max(15, (int) ($params['duration'] ?? 30))),
            'korean_context'   => !empty($params['korean_context']),
            'auto_save'        => !isset($params['auto_save']) || !empty($params['auto_save']),
        ];
    }

    private function apply_korean_context(string $script, array $params): string {
        foreach ($this->catalog->scenes() as $scene) {
            if ($scene['id'] === ($params['scene_id'] ?? '')) {
                return $script;
            }
        }
        return $script;
    }
}
