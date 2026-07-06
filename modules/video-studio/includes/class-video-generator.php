<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Generator {

    private YooY_Video_API_Router $router;
    private YooY_Video_History $history;
    private YooY_Video_Gallery $gallery;
    private YooY_Video_Credits $credits;

    public function __construct(
        YooY_Video_API_Router $router,
        YooY_Video_History $history,
        YooY_Video_Gallery $gallery,
        ?YooY_Video_Credits $credits = null
    ) {
        $this->router  = $router;
        $this->history = $history;
        $this->gallery = $gallery;
        $this->credits = $credits ?? new YooY_Video_Credits();
    }

    public function generate(int $user_id, array $params): array {
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        if ($prompt === '') throw new Exception('Prompt is required.');

        $payload = $this->normalize_params($params);
        $payload['prompt'] = $this->apply_korean_context($prompt, $payload);

        $estimate = $this->credits->estimate($payload);
        if (!$this->credits->can_afford($user_id, $payload)) {
            throw new Exception('Insufficient credits. Required: ' . $estimate);
        }

        $result = $this->router->route($payload);

        if (YooY_Job_Status::is_terminal($result['status'] ?? '')) {
            $credit_info = $this->credits->deduct($user_id, (int) ($result['credits_used'] ?? $estimate), 'Video: ' . mb_substr($prompt, 0, 40));
            $result['credits_used'] = $credit_info['deducted'] ?: (int) ($result['credits_used'] ?? $estimate);
            $result['credits'] = $credit_info;
        }

        $entry = $this->history->add($user_id, array_merge($result, [
            'type'         => 'video',
            'studio'       => 'video-studio',
            'aspect_ratio' => $payload['aspect_ratio'],
            'duration'     => $payload['duration'],
            'quality'      => $payload['quality'],
            'style'        => $payload['style'],
            'camera_motion'=> $payload['camera_motion'],
            'mode'         => $payload['mode'],
            'estimate'     => $estimate,
        ]));

        if (!empty($params['auto_save']) && ($entry['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $this->gallery->auto_save($user_id, $entry, true);
            if (function_exists('yoy_gallery_capture')) {
                yoy_gallery_capture($user_id, $entry, 'video', 'video-studio');
            }
        }

        return $entry;
    }

    public function estimate(int $user_id, array $params): array {
        $payload = $this->normalize_params($params);
        $cost    = $this->credits->estimate($payload);
        return array_merge($this->credits->service()->snapshot($user_id), [
            'estimate'   => $cost,
            'can_afford' => $this->credits->can_afford($user_id, $payload),
        ]);
    }

    public function poll_and_finalize(int $user_id, string $provider, string $job_id): ?array {
        $status = $this->router->status($provider, $job_id);
        if (!YooY_Job_Status::is_terminal($status['status'] ?? '')) {
            $this->history->add($user_id, array_merge($status, ['studio' => 'video-studio']));
            return $status;
        }

        $existing = $this->history->get($user_id, $job_id);
        if ($existing && !empty($existing['credits']['deducted'])) {
            return $this->history->add($user_id, $status);
        }

        $estimate = $this->credits->estimate($existing ?? $status);
        if (($status['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $credit_info = $this->credits->deduct($user_id, (int) ($status['credits_used'] ?? $estimate), 'Video: ' . mb_substr($status['prompt'] ?? '', 0, 40));
            $status['credits'] = $credit_info;
            $status['credits_used'] = $credit_info['deducted'] ?: (int) ($status['credits_used'] ?? $estimate);
            $this->gallery->auto_save($user_id, $status, true);
            if (function_exists('yoy_gallery_capture')) {
                yoy_gallery_capture($user_id, $status, 'video', 'video-studio');
            }
        }

        return $this->history->add($user_id, array_merge($status, ['studio' => 'video-studio']));
    }

    public function options(): array {
        return [
            'modes' => [
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
            'qualities' => [
                ['id' => 'draft', 'label' => 'Draft', 'credits' => 20],
                ['id' => 'standard', 'label' => 'Standard', 'credits' => 50],
                ['id' => 'pro', 'label' => 'Pro', 'credits' => 100],
            ],
            'durations' => [3, 5, 10, 15, 30],
            'aspect_ratios' => ['16:9', '9:16', '1:1', '4:5'],
        ];
    }

    private function normalize_params(array $params): array {
        return [
            'provider'       => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'mock'),
            'model'          => sanitize_text_field($params['model'] ?? $params['default_model'] ?? 'mock-v1'),
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
            'auto_save'      => array_key_exists('auto_save', $params) ? !empty($params['auto_save']) : true,
        ];
    }

    private function apply_korean_context(string $prompt, array $params): string {
        if (empty($params['korean_context'])) return $prompt;
        $context = '한국 시장 최적화 영상. ';
        if (($params['style'] ?? '') === 'commercial') {
            $context .= '한국 광고 톤, 짧은 임팩트, 제품/브랜드 중심. ';
        }
        return $context . $prompt;
    }
}
