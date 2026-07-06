<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Settings {

    private const META_KEY = 'yoy_video_settings';

    public function get(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        if (is_array($stored) && !empty($stored)) {
            return array_merge($this->defaults(), $stored);
        }
        return $this->defaults();
    }

    public function update(int $user_id, array $data): array {
        $current = $this->get($user_id);
        $allowed = array_keys($this->defaults());

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $current[$key] = $this->sanitize($key, $data[$key]);
            }
        }

        update_user_meta($user_id, self::META_KEY, $current);
        return $current;
    }

    public function schema(): array {
        return [
            'aspect_ratios' => [
                ['id' => '16:9', 'label' => '16:9 Landscape', 'platform' => 'YouTube, TV'],
                ['id' => '9:16', 'label' => '9:16 Vertical', 'platform' => 'Shorts, Reels, TikTok'],
                ['id' => '1:1', 'label' => '1:1 Square', 'platform' => 'Instagram, 쇼핑몰'],
                ['id' => '4:5', 'label' => '4:5 Portrait', 'platform' => 'Instagram Feed'],
            ],
            'durations' => [3, 5, 10, 15, 30, 60],
            'qualities' => [
                ['id' => 'draft', 'label' => 'Draft', 'credits' => 20],
                ['id' => 'standard', 'label' => 'Standard', 'credits' => 50],
                ['id' => 'pro', 'label' => 'Pro', 'credits' => 100],
            ],
            'fps_options' => [24, 30, 60],
        ];
    }

    private function defaults(): array {
        return [
            'default_provider'  => 'mock',
            'default_model'     => 'mock-v1',
            'aspect_ratio'      => '16:9',
            'duration'          => 5,
            'quality'           => 'standard',
            'fps'               => 30,
            'camera_motion'     => 'static',
            'style'             => 'cinematic',
            'mode'              => 'text-to-video',
            'korean_context'    => true,
            'auto_save'         => true,
            'subtitle_space'    => true,
            'default_negative'  => 'blurry, low quality, distorted, watermark',
        ];
    }

    private function sanitize(string $key, $value) {
        return match ($key) {
            'duration', 'fps' => (int) $value,
            'korean_context', 'auto_save', 'subtitle_space' => (bool) $value,
            'default_negative' => sanitize_textarea_field((string) $value),
            default => sanitize_text_field((string) $value),
        };
    }
}
