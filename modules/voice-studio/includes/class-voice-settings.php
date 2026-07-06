<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_Settings {

    private const META_KEY = 'yoy_voice_settings';

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
            'default_provider'   => 'mock',
            'default_model'      => 'mock-tts-v1',
            'voice_id'           => 'ko_female_warm',
            'text'               => '',
            'emotion'            => 'neutral',
            'language'           => 'ko',
            'speed'              => 1.0,
            'pitch'              => 0,
            'stability'          => 50,
            'similarity'         => 75,
            'style_exaggeration' => 0,
            'speaker_boost'      => true,
            'auto_save'          => true,
        ];
    }

    private function sanitize(string $key, $value) {
        switch ($key) {
            case 'speed':
            case 'pitch':
                return (float) $value;
            case 'stability':
            case 'similarity':
            case 'style_exaggeration':
                return (int) $value;
            case 'speaker_boost':
            case 'auto_save':
                return (bool) $value;
            case 'text':
                return sanitize_textarea_field((string) $value);
            default:
                return sanitize_text_field((string) $value);
        }
    }
}
