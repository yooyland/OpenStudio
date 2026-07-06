<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Storyboard {

    private const META_KEY = 'yoy_video_storyboard';

    public function get(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        if (is_array($stored) && !empty($stored)) {
            return $stored;
        }
        return $this->default_storyboard();
    }

    public function save(int $user_id, array $data): array {
        $board = [
            'id'         => sanitize_text_field($data['id'] ?? ('sb_' . wp_generate_uuid4())),
            'title'      => sanitize_text_field($data['title'] ?? 'Storyboard'),
            'frames'     => $this->sanitize_frames($data['frames'] ?? []),
            'total_duration' => 0,
            'updated_at' => gmdate('c'),
        ];

        foreach ($board['frames'] as $frame) {
            $board['total_duration'] += (float) ($frame['duration'] ?? 0);
        }

        update_user_meta($user_id, self::META_KEY, $board);
        return $board;
    }

    public function add_frame(int $user_id, array $frame): array {
        $board = $this->get($user_id);
        $board['frames'][] = [
            'id'        => sanitize_text_field($frame['id'] ?? ('frame_' . wp_generate_uuid4())),
            'shot'      => sanitize_text_field($frame['shot'] ?? 'medium'),
            'prompt'    => sanitize_textarea_field($frame['prompt'] ?? ''),
            'duration'  => (float) ($frame['duration'] ?? 3),
            'camera'    => sanitize_text_field($frame['camera'] ?? 'static'),
            'notes'     => sanitize_textarea_field($frame['notes'] ?? ''),
        ];
        return $this->save($user_id, $board);
    }

    public function to_generate_payload(int $user_id): array {
        $board  = $this->get($user_id);
        $prompt = implode(' → ', array_map(fn($f) => $f['prompt'], $board['frames']));
        return [
            'storyboard_id' => $board['id'],
            'prompt'        => $prompt,
            'duration'      => (int) ceil($board['total_duration']),
            'frames'        => $board['frames'],
        ];
    }

    private function default_storyboard(): array {
        return [
            'id'    => 'sb_default',
            'title' => 'New Storyboard',
            'frames' => [
                ['id' => 'f1', 'shot' => 'wide', 'prompt' => '오프닝 와이드 샷, 브랜드 분위기 설정', 'duration' => 3, 'camera' => 'static', 'notes' => ''],
                ['id' => 'f2', 'shot' => 'medium', 'prompt' => '제품/주제 중심 미디엄 샷', 'duration' => 5, 'camera' => 'dolly_in', 'notes' => ''],
                ['id' => 'f3', 'shot' => 'close', 'prompt' => '클로즈업 디테일, 감정/텍스처 강조', 'duration' => 4, 'camera' => 'zoom_in', 'notes' => ''],
                ['id' => 'f4', 'shot' => 'wide', 'prompt' => 'CTA 엔딩, 로고/자막 공간', 'duration' => 3, 'camera' => 'static', 'notes' => '한국어 자막'],
            ],
            'total_duration' => 15,
            'updated_at'     => gmdate('c'),
        ];
    }

    private function sanitize_frames(array $frames): array {
        $out = [];
        foreach ($frames as $frame) {
            if (!is_array($frame)) continue;
            $out[] = [
                'id'       => sanitize_text_field($frame['id'] ?? ('frame_' . wp_generate_uuid4())),
                'shot'     => sanitize_text_field($frame['shot'] ?? 'medium'),
                'prompt'   => sanitize_textarea_field($frame['prompt'] ?? ''),
                'duration' => (float) ($frame['duration'] ?? 3),
                'camera'   => sanitize_text_field($frame['camera'] ?? 'static'),
                'notes'    => sanitize_textarea_field($frame['notes'] ?? ''),
            ];
        }
        return $out;
    }
}
