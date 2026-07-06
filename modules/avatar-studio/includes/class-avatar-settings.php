<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_Settings {

    private const META_KEY = 'yoy_avatar_settings';

    public function get(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) && !empty($stored) ? array_merge($this->defaults(), $stored) : $this->defaults();
    }

    public function update(int $user_id, array $data): array {
        $current = $this->get($user_id);
        foreach (array_keys($this->defaults()) as $key) {
            if (array_key_exists($key, $data)) $current[$key] = $this->sanitize($key, $data[$key]);
        }
        update_user_meta($user_id, self::META_KEY, $current);
        return $current;
    }

    private function defaults(): array {
        return [
            'default_provider'  => 'mock',
            'default_model'     => 'mock-avatar-v1',
            'avatar_id'         => 'ko_female_01',
            'voice_id'          => 'ko_female_warm',
            'script'            => '',
            'lip_sync'          => true,
            'expression'        => 'friendly',
            'gesture'           => 'natural',
            'camera'            => 'medium',
            'emotion'           => 'confident',
            'subtitle_enabled'  => true,
            'subtitle_language' => 'ko',
            'subtitle_style'    => 'default',
            'background'        => 'studio',
            'scene_id'          => 'product_intro',
            'aspect_ratio'      => '16:9',
            'duration'          => 30,
            'korean_context'    => true,
            'auto_save'         => true,
        ];
    }

    private function sanitize(string $key, $value) {
        return match ($key) {
            'duration' => (int) $value,
            'lip_sync', 'subtitle_enabled', 'korean_context', 'auto_save' => (bool) $value,
            'script' => sanitize_textarea_field((string) $value),
            default => sanitize_text_field((string) $value),
        };
    }
}
