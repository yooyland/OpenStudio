<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Generator {

    private const STALL_TIMEOUT_SEC = 90;
    private const MAX_JOB_AGE_SEC   = 600;

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
        $payload['user_id'] = $user_id;
        $payload['prompt'] = $this->apply_korean_context($prompt, $payload);
        $resolution = YooY_Provider_Resolver::apply($payload, 'video', $user_id);

        $estimate = $this->credits->estimate($payload);
        if (!$this->credits->can_afford($user_id, $payload)) {
            if (($payload['provider'] ?? 'mock') !== 'mock') {
                throw new Exception('Provider is connected but billing or credits are unavailable.');
            }
            throw new Exception('Insufficient credits. Required: ' . $estimate);
        }

        $internal_job_id = 'vid_' . wp_generate_uuid4();
        $payload['job_id'] = $internal_job_id;

        $result = $this->router->route($payload);
        $provider_job_id = $this->extract_provider_job_id($result);

        $result['job_id'] = $internal_job_id;
        $result['provider_job_id'] = $provider_job_id;
        $result['prompt'] = $payload['prompt'];

        if ($this->requires_provider_job_id($result, $resolution) && $provider_job_id === '') {
            $result = $this->fail_result($result, 'Provider job id missing.');
        }

        if (YooY_Job_Status::is_terminal($result['status'] ?? '')) {
            $result = YooY_Job_Normalizer::ensure_output_or_fail($result);
        }

        $result = YooY_Provider_Resolver::annotate($result, $resolution);
        $result['stage'] = YooY_Job_Normalizer::derive_stage($result, (string) ($result['status'] ?? ''));
        $result['progress_updated_at'] = gmdate('c');

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

        if (!empty($params['auto_save']) && ($entry['status'] ?? '') === YooY_Job_Status::COMPLETED
            && class_exists('YooY_Asset_Generator') && YooY_Asset_Generator::has_displayable_asset($entry)) {
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
        $existing = $this->history->get($user_id, $job_id);
        if (!$existing) {
            return null;
        }

        if (YooY_Job_Status::is_terminal($existing['status'] ?? '')) {
            return $existing;
        }

        $timeout = $this->check_job_timeout($existing);
        if ($timeout) {
            return $this->history->add($user_id, $timeout);
        }

        $poll_provider = sanitize_text_field($existing['provider_used'] ?? $existing['provider'] ?? $provider);
        $provider_job_id = (string) ($existing['provider_job_id'] ?? '');

        if ($this->requires_provider_job_id($existing) && $provider_job_id === '') {
            return $this->history->add($user_id, $this->fail_result($existing, 'Provider job id missing.'));
        }

        $status = $this->router->status($poll_provider, $provider_job_id !== '' ? $provider_job_id : $job_id);
        $status = $this->merge_poll_result($existing, $status, $job_id, $poll_provider, $provider_job_id);

        if (!YooY_Job_Status::is_terminal($status['status'] ?? '')) {
            return $this->history->add($user_id, array_merge($existing, $status, [
                'studio' => 'video-studio',
                'type'   => 'video',
            ]));
        }

        $status = YooY_Job_Normalizer::ensure_output_or_fail($status);

        if (!empty($existing['credits']['deducted'])) {
            return $this->history->add($user_id, array_merge($existing, $status, [
                'studio' => 'video-studio',
                'type'   => 'video',
            ]));
        }

        $estimate = $this->credits->estimate($existing);
        if (($status['status'] ?? '') === YooY_Job_Status::COMPLETED
            && class_exists('YooY_Asset_Generator') && YooY_Asset_Generator::has_displayable_asset($status)) {
            $credit_info = $this->credits->deduct($user_id, (int) ($status['credits_used'] ?? $estimate), 'Video: ' . mb_substr($status['prompt'] ?? '', 0, 40));
            $status['credits'] = $credit_info;
            $status['credits_used'] = $credit_info['deducted'] ?: (int) ($status['credits_used'] ?? $estimate);
            $this->gallery->auto_save($user_id, $status, true);
            if (function_exists('yoy_gallery_capture')) {
                yoy_gallery_capture($user_id, $status, 'video', 'video-studio');
            }
        } elseif (($status['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $status['status'] = YooY_Job_Status::FAILED;
            $status['error'] = 'Generation completed but no output asset was returned.';
            $status['stage'] = 'failed';
        }

        return $this->history->add($user_id, array_merge($existing, $status, [
            'studio' => 'video-studio',
            'type'   => 'video',
        ]));
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
            'provider'       => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'auto'),
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
            'reference_assets' => $this->normalize_reference_assets($params),
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

    private function normalize_reference_assets(array $params): array {
        if (!class_exists('YooY_Reference_Asset_Service')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'reference-assets/includes/class-reference-asset-service.php';
        }
        $assets = YooY_Reference_Asset_Service::normalize_payload_list($params['reference_assets'] ?? []);
        if (empty($assets) && !empty($params['reference_url'])) {
            $assets[] = [
                'url' => esc_url_raw($params['reference_url']),
                'asset_type' => 'image',
                'role' => 'image',
            ];
        }
        return $assets;
    }

    private function extract_provider_job_id(array $result): string {
        if (!empty($result['provider_job_id'])) {
            return (string) $result['provider_job_id'];
        }
        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        if (!empty($meta['provider_job_id'])) {
            return (string) $meta['provider_job_id'];
        }
        $raw = $result['raw'] ?? null;
        if (is_array($raw) && !empty($raw['id'])) {
            return (string) $raw['id'];
        }
        return '';
    }

    private function requires_provider_job_id(array $job, ?array $resolution = null): bool {
        if (YooY_Job_Status::is_terminal($job['status'] ?? '')) {
            return false;
        }
        $provider = sanitize_text_field($job['provider_used'] ?? $job['provider'] ?? '');
        if ($provider === 'mock' || $provider === '') {
            return false;
        }
        $meta = is_array($job['meta'] ?? null) ? $job['meta'] : [];
        if (!empty($meta['mock']) || !empty($meta['preview_mode'])) {
            return false;
        }
        if ($resolution !== null && !empty($resolution['is_mock'])) {
            return false;
        }
        return true;
    }

    private function fail_result(array $job, string $message): array {
        $job['status'] = YooY_Job_Status::FAILED;
        $job['error'] = $message;
        $job['stage'] = 'failed';
        $job['progress'] = 0;
        return $job;
    }

    private function check_job_timeout(array $existing): ?array {
        $now = time();
        $created_at = strtotime((string) ($existing['created_at'] ?? ''));
        if ($created_at > 0 && ($now - $created_at) >= self::MAX_JOB_AGE_SEC) {
            return array_merge($existing, $this->fail_result($existing, 'Job timed out waiting for provider.'));
        }

        $progress = (int) ($existing['progress'] ?? 0);
        if ($progress !== 0) {
            return null;
        }

        $progress_updated_at = strtotime((string) ($existing['progress_updated_at'] ?? $existing['created_at'] ?? ''));
        if ($progress_updated_at > 0 && ($now - $progress_updated_at) >= self::STALL_TIMEOUT_SEC) {
            return array_merge($existing, $this->fail_result($existing, 'Job timed out: no progress from provider.'));
        }

        return null;
    }

    private function merge_poll_result(array $existing, array $status, string $job_id, string $poll_provider, string $provider_job_id): array {
        $prev_progress = (int) ($existing['progress'] ?? 0);
        $new_progress = (int) ($status['progress'] ?? 0);

        $status['job_id'] = $job_id;
        $status['provider_job_id'] = $provider_job_id;
        $status['prompt'] = $existing['prompt'] ?? $status['prompt'] ?? '';
        $status['provider'] = $poll_provider;
        $status['provider_used'] = $poll_provider;
        $status['stage'] = YooY_Job_Normalizer::derive_stage($status, (string) ($status['status'] ?? ''));

        if ($new_progress !== $prev_progress) {
            $status['progress_updated_at'] = gmdate('c');
        } else {
            $status['progress_updated_at'] = $existing['progress_updated_at'] ?? $existing['created_at'] ?? gmdate('c');
        }

        return $status;
    }
}
