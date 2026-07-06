<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Generator {

    private YooY_Video_API_Router $router;
    private YooY_Video_History $history;
    private YooY_Video_Gallery $gallery;

    public function __construct(YooY_Video_API_Router $router, YooY_Video_History $history, YooY_Video_Gallery $gallery) {
        $this->router  = $router;
        $this->history = $history;
        $this->gallery = $gallery;
    }

    public function generate(int $user_id, array $params): array {
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        if ($prompt === '') {
            throw new Exception('Prompt is required.');
        }

        $payload = $this->normalize_params($params);
        $payload['prompt'] = $this->apply_korean_context($prompt, $payload);

        $result = $this->router->route($payload);
        $entry  = $this->history->add($user_id, $result);
        $this->gallery->auto_save($user_id, $entry, !empty($params['auto_save']));
        if (function_exists('yoy_gallery_capture')) {
            yoy_gallery_capture($user_id, $entry, 'video', 'video-studio');
        }

        return $entry;
    }

    public function options(): array {
        return [
            'modes'          => [
                ['id' => 'text-to-video', 'label' => 'Text to Video'],
                ['id' => 'image-to-video', 'label' => 'Image to Video'],
                ['id' => 'video-to-video', 'label' => 'Video to Video'],
            ],
            'camera_motions' => [
                ['id' => 'static', 'label' => 'Static'],
                ['id' => 'pan_left', 'label' => 'Pan Left'],
                ['id' => 'pan_right', 'label' => 'Pan Right'],
                ['id' => 'zoom_in', 'label' => 'Zoom In'],
                ['id' => 'zoom_out', 'label' => 'Zoom Out'],
                ['id' => 'dolly_in', 'label' => 'Dolly In'],
                ['id' => 'orbit', 'label' => 'Orbit'],
            ],
            'styles' => [
                ['id' => 'cinematic', 'label' => '시네마틱'],
                ['id' => 'commercial', 'label' => '광고'],
                ['id' => 'documentary', 'label' => '다큐멘터리'],
                ['id' => 'anime', 'label' => '애니메이션'],
                ['id' => 'k-drama', 'label' => 'K-드라마'],
            ],
        ];
    }

    private function normalize_params(array $params): array {
        return [
            'provider'       => sanitize_text_field($params['provider'] ?? 'mock'),
            'model'          => sanitize_text_field($params['model'] ?? 'mock-v1'),
            'prompt'         => sanitize_textarea_field($params['prompt'] ?? ''),
            'negative_prompt'=> sanitize_textarea_field($params['negative_prompt'] ?? ''),
            'aspect_ratio'   => sanitize_text_field($params['aspect_ratio'] ?? '16:9'),
            'duration'       => min(60, max(3, (int) ($params['duration'] ?? 5))),
            'quality'        => sanitize_text_field($params['quality'] ?? 'standard'),
            'fps'            => (int) ($params['fps'] ?? 30),
            'camera_motion'  => sanitize_text_field($params['camera_motion'] ?? 'static'),
            'style'          => sanitize_text_field($params['style'] ?? 'cinematic'),
            'mode'           => sanitize_text_field($params['mode'] ?? 'text-to-video'),
            'korean_context' => !empty($params['korean_context']),
            'template_id'    => sanitize_text_field($params['template_id'] ?? ''),
            'reference_url'  => esc_url_raw($params['reference_url'] ?? ''),
            'storyboard_id'  => sanitize_text_field($params['storyboard_id'] ?? ''),
        ];
    }

    private function apply_korean_context(string $prompt, array $params): string {
        if (empty($params['korean_context'])) {
            return $prompt;
        }
        $context = '한국 시장 최적화 영상. ';
        if (($params['style'] ?? '') === 'commercial') {
            $context .= '한국 광고 톤, 짧은 임팩트, 제품/브랜드 중심. ';
        }
        return $context . $prompt;
    }
}
