<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Canvas {

    private const META_KEY = 'yoy_video_canvas';

    public function get(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        if (is_array($stored) && !empty($stored)) {
            return $stored;
        }
        return $this->default_canvas();
    }

    public function save(int $user_id, array $data): array {
        $canvas = [
            'id'          => sanitize_text_field($data['id'] ?? ('canvas_' . wp_generate_uuid4())),
            'title'       => sanitize_text_field($data['title'] ?? 'Untitled Canvas'),
            'aspect_ratio'=> sanitize_text_field($data['aspect_ratio'] ?? '16:9'),
            'duration'    => (int) ($data['duration'] ?? 15),
            'scenes'      => $this->sanitize_scenes($data['scenes'] ?? []),
            'layers'      => $this->sanitize_layers($data['layers'] ?? []),
            'updated_at'  => gmdate('c'),
        ];
        update_user_meta($user_id, self::META_KEY, $canvas);
        return $canvas;
    }

    public function add_scene(int $user_id, array $scene): array {
        $canvas = $this->get($user_id);
        $canvas['scenes'][] = [
            'id'       => sanitize_text_field($scene['id'] ?? ('scene_' . wp_generate_uuid4())),
            'label'    => sanitize_text_field($scene['label'] ?? 'Scene'),
            'prompt'   => sanitize_textarea_field($scene['prompt'] ?? ''),
            'duration' => (float) ($scene['duration'] ?? 3),
            'start'    => (float) ($scene['start'] ?? 0),
        ];
        return $this->save($user_id, $canvas);
    }

    private function default_canvas(): array {
        return [
            'id'           => 'canvas_default',
            'title'        => 'New Video Canvas',
            'aspect_ratio' => '16:9',
            'duration'     => 15,
            'scenes'       => [
                ['id' => 'scene_1', 'label' => 'Opening', 'prompt' => '', 'duration' => 3, 'start' => 0],
                ['id' => 'scene_2', 'label' => 'Main', 'prompt' => '', 'duration' => 9, 'start' => 3],
                ['id' => 'scene_3', 'label' => 'CTA', 'prompt' => '', 'duration' => 3, 'start' => 12],
            ],
            'layers' => [
                ['id' => 'layer_bg', 'type' => 'background', 'visible' => true],
                ['id' => 'layer_video', 'type' => 'video', 'visible' => true],
                ['id' => 'layer_text', 'type' => 'text', 'visible' => true, 'content' => ''],
                ['id' => 'layer_subtitle', 'type' => 'subtitle', 'visible' => true, 'content' => ''],
            ],
            'updated_at' => gmdate('c'),
        ];
    }

    private function sanitize_scenes(array $scenes): array {
        $out = [];
        foreach ($scenes as $scene) {
            if (!is_array($scene)) continue;
            $out[] = [
                'id'       => sanitize_text_field($scene['id'] ?? ('scene_' . wp_generate_uuid4())),
                'label'    => sanitize_text_field($scene['label'] ?? 'Scene'),
                'prompt'   => sanitize_textarea_field($scene['prompt'] ?? ''),
                'duration' => (float) ($scene['duration'] ?? 3),
                'start'    => (float) ($scene['start'] ?? 0),
            ];
        }
        return $out;
    }

    private function sanitize_layers(array $layers): array {
        $out = [];
        foreach ($layers as $layer) {
            if (!is_array($layer)) continue;
            $out[] = [
                'id'      => sanitize_text_field($layer['id'] ?? ('layer_' . wp_generate_uuid4())),
                'type'    => sanitize_text_field($layer['type'] ?? 'video'),
                'visible' => !empty($layer['visible']),
                'content' => sanitize_textarea_field($layer['content'] ?? ''),
            ];
        }
        return $out;
    }
}
