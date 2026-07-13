<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_Generator {

    private YooY_Image_API_Router $router;
    private YooY_Image_History $history;
    private YooY_Image_Gallery $gallery;
    private YooY_Image_Credits $credits;

    public function __construct(
        YooY_Image_API_Router $router,
        YooY_Image_History $history,
        YooY_Image_Gallery $gallery,
        ?YooY_Image_Credits $credits = null
    ) {
        $this->router  = $router;
        $this->history = $history;
        $this->gallery = $gallery;
        $this->credits = $credits ?? new YooY_Image_Credits();
    }

    public function generate(int $user_id, array $params): array {
        $started_at = microtime(true);
        $perf = [
            'provider_resolve_ms' => 0,
            'prompt_optimize_ms'  => 0,
            'api_request_ms'      => 0,
            'image_save_ms'       => 0,
            'gallery_save_ms'     => 0,
            'total_generation_ms' => 0,
        ];

        $requested_provider = sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'auto');
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        if ($prompt === '') {
            throw new YooY_Generation_Exception('request_validation', 'prompt_empty', 'Prompt is empty.', [
                'provider_requested' => $requested_provider,
                'provider_resolved'  => null,
                'reason'             => 'missing_prompt',
                'missing_fields'     => ['prompt'],
            ]);
        }

        $payload = $this->normalize($params);
        $payload['user_id'] = $user_id;
        $payload['user_prompt'] = sanitize_textarea_field($params['user_prompt'] ?? $prompt);
        $payload['optimized_prompt'] = sanitize_textarea_field($params['optimized_prompt'] ?? $params['prompt'] ?? '');

        $resolve_started = microtime(true);
        $resolution = YooY_Provider_Resolver::apply($payload, 'image', $user_id);
        $perf['provider_resolve_ms'] = (int) round((microtime(true) - $resolve_started) * 1000);

        $optimize_started = microtime(true);
        $payload = $this->apply_prompt_composer($payload, $params);
        $perf['prompt_optimize_ms'] = (int) round((microtime(true) - $optimize_started) * 1000);

        $estimate = $this->credits->estimate($payload);
        if (!$this->credits->can_afford($user_id, $payload)) {
            $is_live = ($payload['provider'] ?? 'mock') !== 'mock';
            throw new YooY_Generation_Exception('credit_check', $is_live ? 'billing_unavailable' : 'insufficient_credits', $is_live
                ? 'Provider is connected but billing or credits are unavailable.'
                : 'Not enough credits. Required: ' . $estimate, [
                'provider_requested' => $requested_provider,
                'provider_resolved'  => $payload['provider'] ?? null,
                'reason'             => $is_live ? 'billing_unavailable' : 'insufficient_credits',
                'debug'              => ['estimate' => $estimate],
            ]);
        }

        $api_started = microtime(true);
        $result = $this->router->generate($payload);
        $perf['api_request_ms'] = (int) round((microtime(true) - $api_started) * 1000);

        $result = $this->maybe_fallback_from_replicate_billing(
            $user_id,
            $payload,
            $resolution,
            $result,
            $requested_provider
        );
        $fallback_applied = (($result['fallback_reason'] ?? '') === 'replicate_insufficient_credit');

        if (!class_exists('YooY_OpenAI_B64_Asset') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
            require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-openai-b64-asset.php';
        }
        $save_started = microtime(true);
        if (!$fallback_applied && class_exists('YooY_OpenAI_B64_Asset')) {
            $result = YooY_OpenAI_B64_Asset::finalize_job(
                $result,
                $user_id,
                (string) ($result['job_id'] ?? ''),
                sanitize_text_field($payload['output_format'] ?? 'png')
            );
        }
        $perf['image_save_ms'] = (int) round((microtime(true) - $save_started) * 1000);

        $result = YooY_Job_Normalizer::ensure_output_or_fail($result);
        if (!$fallback_applied) {
            $result = YooY_Provider_Resolver::annotate($result, $resolution);
        }

        if (($result['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $credit_info = $this->credits->deduct($user_id, (int) ($result['credits_used'] ?? $estimate), 'Image: ' . mb_substr($prompt, 0, 40));
            $result['credits_used'] = $credit_info['deducted'] ?: (int) ($result['credits_used'] ?? $estimate);
            $result['credits'] = $credit_info;
        }

        $entry = $this->history->add($user_id, array_merge($result, [
            'type'           => 'image',
            'studio'         => 'image-studio',
            'user_prompt'    => sanitize_textarea_field($params['user_prompt'] ?? $prompt),
            'optimized_prompt' => sanitize_textarea_field($payload['optimized_prompt'] ?? $payload['prompt'] ?? ''),
            'aspect_ratio'   => $payload['aspect_ratio'],
            'resolution'     => $payload['resolution'],
            'style'          => $payload['style'],
            'lighting'       => $payload['lighting'],
            'composition'    => $payload['composition'],
            'negative_prompt'=> $payload['negative_prompt'],
            'seed'           => $payload['seed'],
            'quality'        => $payload['quality'],
            'image_count'    => $payload['image_count'],
            'estimate'       => $estimate,
            'creative_brief' => is_array($payload['creative_brief'] ?? null) ? $payload['creative_brief'] : [],
            'intent_domain'  => sanitize_key((string) ($payload['intent_domain'] ?? ($payload['composer_meta']['prompt_intelligence']['intent_domain'] ?? ''))),
            'prompt_version' => sanitize_text_field((string) ($payload['prompt_version'] ?? ($payload['composer_meta']['prompt_intelligence']['prompt_version'] ?? ''))),
            'composer_meta'  => is_array($payload['composer_meta'] ?? null) ? $payload['composer_meta'] : [],
        ]));

        if (($entry['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $gallery_started = microtime(true);
            $gallery_ids = $this->gallery->save_from_result($user_id, $entry);
            $perf['gallery_save_ms'] = (int) round((microtime(true) - $gallery_started) * 1000);
            if (class_exists('YooY_OpenAI_B64_Asset') && !empty($gallery_ids) && current_user_can('manage_options')) {
                $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];
                $asset_debug = is_array($meta['openai_asset_debug'] ?? null) ? $meta['openai_asset_debug'] : [];
                $asset_debug['gallery_item_id'] = (string) ($gallery_ids[0] ?? '');
                $meta['openai_asset_debug'] = $asset_debug;
                $entry['meta'] = $meta;
            }
        }

        $perf['total_generation_ms'] = (int) round((microtime(true) - $started_at) * 1000);
        $meta = is_array($entry['meta'] ?? null) ? $entry['meta'] : [];
        $meta['generation_perf'] = $perf;
        $entry['meta'] = $meta;
        $entry = $this->history->add($user_id, $entry);

        $this->record_provider_stats($entry, true, $fallback_applied, (int) ($perf['api_request_ms'] ?? 0));

        if (class_exists('YooY_OpenAI_B64_Asset')) {
            return YooY_OpenAI_B64_Asset::sanitize_job_for_response($entry);
        }

        return $entry;
    }

    /**
     * Records provider telemetry (daily request/success/fail + error log).
     *
     * @param array<string, mixed> $job
     */
    private function record_provider_stats(array $job, bool $count_request, bool $retry, int $latency_ms = 0): void {
        if (!class_exists('YooY_Provider_Stats')) {
            return;
        }
        $provider = (string) ($job['provider_used'] ?? $job['provider'] ?? '');
        if ($provider === '') {
            return;
        }
        YooY_Provider_Stats::record([
            'provider'         => $provider,
            'catalog_provider' => (string) ($job['catalog_provider'] ?? $provider),
            'model'            => (string) ($job['model'] ?? ''),
            'studio'           => 'image',
            'status'           => (string) ($job['status'] ?? ''),
            'error'            => (string) ($job['error'] ?? $job['user_message'] ?? ''),
            'error_type'       => (string) ($job['error_type'] ?? ''),
            'latency_ms'       => $latency_ms,
            'retry'            => $retry,
        ], $count_request);
    }

    public function estimate(int $user_id, array $params): array {
        $payload = $this->normalize($params);
        $cost    = $this->credits->estimate($payload);
        return array_merge($this->credits->service()->snapshot($user_id), [
            'estimate'    => $cost,
            'can_afford'  => $this->credits->can_afford($user_id, $payload),
        ]);
    }

    public function poll_and_finalize(int $user_id, string $provider, string $job_id): ?array {
        $existing = $this->history->get($user_id, $job_id);
        $status = $this->router->status($provider, $job_id);

        if (!YooY_Job_Status::is_terminal($status['status'] ?? '')) {
            $provider_job_id = (string) ($status['provider_job_id'] ?? '');
            $has_output = class_exists('YooY_Asset_Generator') && YooY_Asset_Generator::has_displayable_asset($status);
            if ($provider_job_id === '' && !$has_output) {
                $status = YooY_Job_Normalizer::enforce_pollable_state($status, 'image');
                return $this->history->add($user_id, array_merge($status, ['studio' => 'image-studio']));
            }

            $progress_at = strtotime((string) ($status['progress_updated_at'] ?? $status['updated_at'] ?? ''));
            if ($progress_at > 0 && (time() - $progress_at) > 30) {
                $status['status'] = YooY_Job_Status::FAILED;
                $status['error'] = 'Generation timed out (no progress for 30 seconds).';
                return $this->history->add($user_id, array_merge($status, ['studio' => 'image-studio']));
            }
        }

        $status = YooY_Job_Normalizer::ensure_output_or_fail($status);
        if (!YooY_Job_Status::is_terminal($status['status'] ?? '')) {
            $this->history->add($user_id, array_merge($status, ['studio' => 'image-studio']));
            return $status;
        }

        // Async job just reached a terminal state — record the outcome (no new request count).
        if (!$existing || YooY_Job_Status::is_terminal($existing['status'] ?? '') === false) {
            $this->record_provider_stats($status, false, false, (int) ($status['duration_ms'] ?? 0));
        }

        $existing = $existing ?: $this->history->get($user_id, $job_id);
        if ($existing && !empty($existing['credits']['deducted'])) {
            return $this->history->add($user_id, $status);
        }

        $estimate = $this->credits->estimate($existing ?? $status);
        if (($status['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $credit_info = $this->credits->deduct($user_id, (int) ($status['credits_used'] ?? $estimate), 'Image: ' . mb_substr($status['prompt'] ?? '', 0, 40));
            $status['credits'] = $credit_info;
            $status['credits_used'] = $credit_info['deducted'] ?: (int) ($status['credits_used'] ?? $estimate);
            $gallery_started = microtime(true);
            $this->gallery->save_from_result($user_id, $status);
            $this->capture_gallery_items($user_id, $status);
            $meta = is_array($status['meta'] ?? null) ? $status['meta'] : [];
            $perf = is_array($meta['generation_perf'] ?? null) ? $meta['generation_perf'] : [];
            $perf['gallery_save_ms'] = (int) round((microtime(true) - $gallery_started) * 1000);
            $meta['generation_perf'] = $perf;
            $status['meta'] = $meta;
        }

        return $this->history->add($user_id, array_merge($status, ['studio' => 'image-studio']));
    }

    public function options(): array {
        $settings = new YooY_Image_Settings();
        return $settings->schema();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function compose_prompt(int $user_id, array $params): array {
        $payload = $this->normalize($params);
        $payload['user_id'] = $user_id;
        $payload['user_prompt'] = sanitize_textarea_field($params['user_prompt'] ?? $params['prompt'] ?? '');

        $resolution = [];
        if ($user_id > 0) {
            $resolution = YooY_Provider_Resolver::apply($payload, 'image', $user_id);
            $payload['provider'] = sanitize_text_field((string) ($resolution['provider'] ?? $payload['provider']));
            $payload['model'] = sanitize_text_field((string) ($resolution['model'] ?? $payload['model'] ?? ''));
        }

        $composed = $this->run_prompt_composer($payload);
        $composed['resolved_provider'] = $this->describe_resolved_provider($resolution, $payload);
        return $composed;
    }

    /**
     * Human-readable summary of the provider/model that Auto resolved to, for the
     * generate panel ("OpenAI Image / gpt-image-1") and non-OpenAI warning.
     *
     * @param array<string, mixed> $resolution
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function describe_resolved_provider(array $resolution, array $payload): array {
        $provider = (string) ($resolution['provider'] ?? $payload['provider'] ?? 'auto');
        $catalog  = (string) ($resolution['catalog_provider'] ?? $provider);
        $model    = (string) ($resolution['model'] ?? $payload['model'] ?? '');
        $is_mock  = ($provider === 'mock') || (strpos($catalog, 'mock') !== false);
        $is_openai = ($provider === 'openai' || $catalog === 'openai');

        if ($is_openai) {
            $label = 'OpenAI Image' . ($model !== '' ? ' / ' . $model : '');
        } elseif ($is_mock) {
            $label = 'Mock Image' . ($model !== '' ? ' / ' . $model : '');
        } else {
            $names = [
                'replicate' => 'Replicate',
                'flux'      => 'Replicate (Flux)',
                'stability' => 'Stability',
                'ideogram'  => 'Ideogram',
                'gemini-image' => 'Gemini Image',
            ];
            $name = $names[$catalog] ?? ($names[$provider] ?? ucfirst($provider));
            $label = $name . ($model !== '' ? ' / ' . $model : '');
        }

        $warning = '';
        if (!$is_openai) {
            $warning = '현재 OpenAI가 아닌 다른 공급업체로 생성됩니다.';
        }

        return [
            'provider'         => $provider,
            'catalog_provider' => $catalog,
            'model'            => $model,
            'label'            => $label,
            'is_openai'        => $is_openai,
            'is_mock'          => $is_mock,
            'warning'          => $warning,
        ];
    }

    /**
     * Pre-generate provider health snapshot: which engine Auto will use, its
     * status, and the health of every image provider candidate.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function provider_health(int $user_id, array $params): array {
        $payload = $this->normalize($params);
        $payload['user_id'] = $user_id;

        $requested = (string) ($payload['provider'] ?? 'auto');
        $resolution = ['provider' => 'mock', 'catalog_provider' => 'mock-image', 'model' => 'mock-image-v1', 'requested_provider' => $requested];
        try {
            $resolution = YooY_Provider_Resolver::apply($payload, 'image', $user_id);
        } catch (Exception $e) {
            $resolution['resolve_error'] = $e->getMessage();
        }

        $desc = $this->describe_resolved_provider($resolution, $payload);
        $resolved_status = $this->provider_status_meta((string) ($resolution['catalog_provider'] ?? $resolution['provider'] ?? ''), $desc['is_mock']);

        return [
            'requested_provider' => $requested,
            'resolved'           => array_merge($desc, [
                'status'       => $resolved_status['status'],
                'status_label' => $resolved_status['label'],
                'status_tone'  => $resolved_status['tone'],
                'ok'           => $resolved_status['ok'],
                'detail'       => $resolved_status['detail'],
            ]),
            'candidates'         => $this->image_provider_candidates(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function image_provider_candidates(): array {
        $rows = [];
        foreach ($this->router->providers() as $p) {
            $id = (string) ($p['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $is_mock = (strpos($id, 'mock') !== false) || (($p['impl'] ?? '') === 'mock');
            $meta = $this->provider_status_meta($id, $is_mock);
            $today = class_exists('YooY_Provider_Stats') ? YooY_Provider_Stats::today($id) : ['requests' => 0, 'success' => 0, 'fail' => 0];
            $rows[] = [
                'id'           => $id,
                'name'         => (string) ($p['name'] ?? $id),
                'status'       => $meta['status'],
                'status_label' => $meta['label'],
                'status_tone'  => $meta['tone'],
                'ok'           => $meta['ok'],
                'detail'       => $meta['detail'],
                'today'        => $today,
            ];
        }
        return $rows;
    }

    /**
     * Derives a UI status (ready / billing_error / api_error / needs_test / mock ...)
     * for a catalog provider id, based on resolver state + evaluation.
     *
     * @return array<string, mixed>
     */
    private function provider_status_meta(string $provider_id, bool $is_mock = false): array {
        if ($provider_id === '' || $is_mock || $provider_id === 'mock' || strpos($provider_id, 'mock') !== false) {
            return ['status' => 'mock', 'label' => 'Mock (테스트)', 'tone' => 'muted', 'ok' => true, 'detail' => '실제 API 없이 Mock으로 생성합니다.'];
        }
        if (!class_exists('YooY_Provider_Resolver')) {
            return ['status' => 'unknown', 'label' => 'Unknown', 'tone' => 'pending', 'ok' => false, 'detail' => ''];
        }

        $state = YooY_Provider_Resolver::get_provider_state($provider_id);
        $eval  = YooY_Provider_Resolver::evaluate($provider_id, 'image');

        $billing_blocked = !empty($state['auto_routing_disabled']) || ($state['billing_status'] ?? '') === 'blocked';
        if ($billing_blocked) {
            return ['status' => 'billing_error', 'label' => 'Billing Error', 'tone' => 'error', 'ok' => false, 'detail' => 'Provider API 계정 결제 오류입니다. YooY 사용자 크레딧과는 별도입니다.'];
        }
        if (($state['last_test_error_type'] ?? '') === 'auth_error') {
            return ['status' => 'api_error', 'label' => 'API Error', 'tone' => 'error', 'ok' => false, 'detail' => 'API 키 인증 오류입니다.'];
        }
        if (!empty($eval['usable'])) {
            return ['status' => 'ready', 'label' => 'Ready', 'tone' => 'ok', 'ok' => true, 'detail' => ''];
        }

        $code = (string) ($eval['error_code'] ?? '');
        $map = [
            'provider_not_configured' => ['no_key', 'API Key 없음', 'warn'],
            'provider_not_tested'     => ['needs_test', '테스트 필요', 'warn'],
            'provider_test_failed'    => ['test_failed', 'Test Failed', 'error'],
            'provider_test_unsupported' => ['unsupported', '테스트 미지원', 'warn'],
            'provider_in_mock_mode'   => ['mock_mode', 'Mock 모드', 'muted'],
            'provider_disabled'       => ['disabled', '비활성화', 'muted'],
            'bridge_unimplemented'    => ['unimplemented', '구현 필요', 'warn'],
        ];
        if (isset($map[$code])) {
            return ['status' => $map[$code][0], 'label' => $map[$code][1], 'tone' => $map[$code][2], 'ok' => false, 'detail' => (string) ($eval['message'] ?? '')];
        }
        return ['status' => 'error', 'label' => 'Error', 'tone' => 'error', 'ok' => false, 'detail' => (string) ($eval['message'] ?? '')];
    }

    private function capture_gallery_items(int $user_id, array $entry): void {
        if (!function_exists('yoy_gallery_capture')) return;

        foreach (($entry['images'] ?? []) as $i => $img) {
            $url = $img['url'] ?? '';
            if (!class_exists('YooY_Asset_Generator') || !YooY_Asset_Generator::is_http_asset_url($url)) {
                continue;
            }
            yoy_gallery_capture($user_id, array_merge($entry, [
                'job_id'        => ($entry['job_id'] ?? '') . '_' . $i,
                'images'        => [$img],
                'image_count'   => 1,
                'thumbnail'     => $img['thumbnail'] ?? $img['url'] ?? '',
                'thumbnail_url' => $img['thumbnail'] ?? $img['url'] ?? '',
                'output_url'    => $img['url'] ?? '',
                'image_url'     => $img['url'] ?? '',
                'attachment_id' => (int) ($img['attachment_id'] ?? 0),
            ]), 'image', 'image-studio');
        }
    }

    private function normalize(array $params): array {
        $generation_mode = sanitize_text_field($params['generation_mode'] ?? 'fast');
        $quality = sanitize_text_field($params['quality'] ?? 'standard');
        $image_count = min(4, max(1, (int) ($params['image_count'] ?? 1)));
        if ($generation_mode === 'fast') {
            $quality = 'standard';
            $image_count = 1;
        }

        return [
            'provider'       => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'auto'),
            'model'          => sanitize_text_field($params['model'] ?? $params['default_model'] ?? ''),
            'prompt'         => sanitize_textarea_field($params['prompt'] ?? ''),
            'negative_prompt'=> sanitize_textarea_field($params['negative_prompt'] ?? ''),
            'aspect_ratio'   => sanitize_text_field($params['aspect_ratio'] ?? '1:1'),
            'resolution'     => sanitize_text_field($params['resolution'] ?? '1024'),
            'quality'        => $quality,
            'generation_mode'=> $generation_mode,
            'lighting'       => sanitize_text_field($params['lighting'] ?? 'auto'),
            'composition'    => sanitize_text_field($params['composition'] ?? 'auto'),
            'style'          => sanitize_text_field($params['style'] ?? 'auto'),
            'background'     => sanitize_text_field($params['background'] ?? 'auto'),
            'color_palette'  => sanitize_text_field($params['color_palette'] ?? 'auto'),
            'product_type'   => sanitize_text_field($params['product_type'] ?? 'auto'),
            'brand_tone'     => sanitize_text_field($params['brand_tone'] ?? 'auto'),
            'seed'           => (int) ($params['seed'] ?? -1),
            'image_count'    => $image_count,
            'reference_url'  => esc_url_raw($params['reference_url'] ?? ''),
            'reference_assets' => $this->normalize_reference_assets($params),
            'korean_context' => !empty($params['korean_context']),
            'auto_save'      => array_key_exists('auto_save', $params) ? !empty($params['auto_save']) : true,
            'async'          => !empty($params['async']),
            'user_prompt'    => sanitize_textarea_field($params['user_prompt'] ?? $params['prompt'] ?? ''),
            'optimized_prompt' => sanitize_textarea_field($params['optimized_prompt'] ?? ''),
            'smart_auto'     => !isset($params['smart_auto']) || !empty($params['smart_auto']),
            'mood'           => sanitize_text_field($params['mood'] ?? 'auto'),
            'camera'         => sanitize_text_field($params['camera'] ?? 'auto'),
            'lens'           => sanitize_text_field($params['lens'] ?? 'auto'),
            'camera_angle'   => sanitize_text_field($params['camera_angle'] ?? 'auto'),
            'depth_of_field' => sanitize_text_field($params['depth_of_field'] ?? 'auto'),
            'commercial'     => $this->normalize_commercial_flag($params),
            'commercial_mode'=> array_key_exists('commercial_mode', $params) ? !empty($params['commercial_mode']) : null,
            'creative_brief' => is_array($params['creative_brief'] ?? null) ? $params['creative_brief'] : [],
            'intent_domain'  => sanitize_key((string) ($params['intent_domain'] ?? '')),
            'raw_user_request' => sanitize_textarea_field($params['raw_user_request'] ?? $params['user_prompt'] ?? $params['prompt'] ?? ''),
            'prompt_version' => sanitize_text_field((string) ($params['prompt_version'] ?? '')),
        ];
    }

    /** @param array<string, mixed> $params */
    private function normalize_commercial_flag(array $params): bool {
        if (array_key_exists('commercial_mode', $params) && !array_key_exists('commercial', $params)) {
            return !empty($params['commercial_mode']);
        }
        if (array_key_exists('commercial', $params)) {
            return !empty($params['commercial']);
        }
        // Default on for Smart Auto commercial polish — domain composer still blocks product injection
        return true;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $raw_params
     * @return array<string, mixed>
     */
    private function apply_prompt_composer(array $payload, array $raw_params): array {
        $use_composer = !isset($raw_params['smart_auto']) || !empty($raw_params['smart_auto']);
        if (!$use_composer) {
            $payload['prompt'] = $this->apply_korean_context((string) $payload['prompt'], $payload);
            $payload['prompt'] = $this->apply_style_modifiers($payload);
            return $payload;
        }

        $composed = $this->run_prompt_composer($payload);
        $payload = array_merge($payload, $composed['settings']);
        $payload['prompt'] = $composed['prompt'];
        $payload['negative_prompt'] = $composed['negative_prompt'];
        $payload['optimized_prompt'] = $composed['canonical_prompt'];
        $payload['composer_meta'] = $composed['meta'];
        if (!empty($composed['creative_brief']) && is_array($composed['creative_brief'])) {
            $payload['creative_brief'] = $composed['creative_brief'];
            $payload['intent_domain'] = sanitize_key((string) ($composed['creative_brief']['content_domain'] ?? $payload['intent_domain'] ?? ''));
        }
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function run_prompt_composer(array $payload): array {
        if (!class_exists('YooY_Image_Prompt_Composer')) {
            require_once dirname(__FILE__) . '/prompt-engine/class-image-prompt-composer.php';
        }
        $composer = new YooY_Image_Prompt_Composer();
        return $composer->compose(array_merge($payload, [
            'user_prompt' => $payload['user_prompt'] ?? $payload['prompt'] ?? '',
            'smart_auto'  => true,
        ]));
    }

    private function apply_korean_context(string $prompt, array $params): string {
        if (empty($params['korean_context'])) return $prompt;
        $ctx = '한국 시장 최적화 이미지. ';
        if ($params['style'] === 'commercial' || $params['style'] === 'k-beauty') {
            $ctx .= '한국 광고/이커머스 스타일, 클린 타이포 공간. ';
        }
        return $ctx . $prompt;
    }

    private function apply_style_modifiers(array $params): string {
        $parts = [$params['prompt']];
        $parts[] = 'Lighting: ' . $params['lighting'];
        $parts[] = 'Composition: ' . $params['composition'];
        $parts[] = 'Style: ' . $params['style'];
        $parts[] = 'Background: ' . $params['background'];
        $parts[] = 'Color palette: ' . $params['color_palette'];
        $parts[] = 'Product type: ' . $params['product_type'];
        $parts[] = 'Brand tone: ' . $params['brand_tone'];
        if ($params['reference_url']) {
            $parts[] = 'Reference image style applied';
        }
        return implode('. ', $parts);
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

    private function maybe_fallback_from_replicate_billing(
        int $user_id,
        array &$payload,
        array $resolution,
        array $result,
        string $requested_provider
    ): array {
        $is_billing_failure = class_exists('YooY_Provider_Billing_Error')
            && YooY_Provider_Billing_Error::is_replicate_billing_failure($result);
        $is_incomplete = $this->is_replicate_request_incomplete($result);

        if (!$is_billing_failure && !$is_incomplete) {
            return $result;
        }

        $billing_msg = 'Insufficient credit';
        if ($is_billing_failure && class_exists('YooY_Provider_Billing_Error')) {
            $billing_msg = YooY_Provider_Billing_Error::detect_from_job($result) ?: 'Insufficient credit';
        } elseif ($is_incomplete) {
            $billing_msg = 'Replicate request returned no provider job id (insufficient credit or billing error).';
        }

        // Exclude Replicate/Flux from future auto routing until the provider billing is resolved.
        if (class_exists('YooY_Provider_Resolver')) {
            YooY_Provider_Resolver::mark_billing_error('replicate', $billing_msg);
        }

        $is_auto = ($requested_provider === 'auto' || $requested_provider === '');
        $strict = !empty($payload['strict_provider']);

        // Auto routing: silently retry with OpenAI Image when it is healthy.
        if ($is_auto && !$strict
            && class_exists('YooY_Provider_Resolver')
            && YooY_Provider_Resolver::is_usable('openai', 'image')) {
            $fallback = $this->run_openai_billing_fallback($user_id, $payload);
            if ($fallback !== null) {
                return $fallback;
            }
        }

        // No fallback available: fail immediately. Never leave a billing failure in running state.
        return $this->mark_replicate_billing_failed($result);
    }

    /**
     * Replicate accepted no work (empty provider_job_id, not terminal, no output).
     * Treated as a provider-side billing/availability failure.
     *
     * @param array<string, mixed> $result
     */
    private function is_replicate_request_incomplete(array $result): bool {
        $provider = strtolower((string) ($result['provider_used'] ?? $result['provider'] ?? ''));
        $catalog  = strtolower((string) ($result['catalog_provider'] ?? ''));
        if ($provider !== 'replicate' && $catalog !== 'replicate' && $catalog !== 'flux') {
            return false;
        }

        $provider_job_id = trim((string) ($result['provider_job_id'] ?? ''));
        if ($provider_job_id !== '') {
            return false;
        }

        $has_output = class_exists('YooY_Asset_Generator')
            && YooY_Asset_Generator::has_displayable_asset($result);
        if ($has_output) {
            return false;
        }

        $status = (string) ($result['status'] ?? '');
        // Completed with output already returned above; anything else with no job id is incomplete.
        return $status !== YooY_Job_Status::COMPLETED;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null Annotated OpenAI job, or null when the retry also failed.
     */
    private function run_openai_billing_fallback(int $user_id, array $payload): ?array {
        $fallback_payload = $payload;
        $fallback_payload['provider'] = 'openai';
        $fallback_resolution = YooY_Provider_Resolver::apply($fallback_payload, 'image', $user_id);
        $retry = $this->router->generate($fallback_payload);

        if (!class_exists('YooY_OpenAI_B64_Asset') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
            require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-openai-b64-asset.php';
        }
        if (class_exists('YooY_OpenAI_B64_Asset')) {
            $retry = YooY_OpenAI_B64_Asset::finalize_job(
                $retry,
                $user_id,
                (string) ($retry['job_id'] ?? ''),
                sanitize_text_field($fallback_payload['output_format'] ?? 'png')
            );
        }

        $retry = YooY_Job_Normalizer::ensure_output_or_fail($retry);
        if (($retry['status'] ?? '') === YooY_Job_Status::FAILED) {
            return null;
        }

        $fallback_resolution['fallback_reason'] = 'replicate_insufficient_credit';
        $fallback_resolution['warning'] = YooY_Provider_Billing_Error::user_message_fallback();
        $retry['user_credits_message'] = 'YooY 사용자 크레딧과는 별도입니다.';
        $retry['error_type'] = 'provider_billing_fallback';
        return YooY_Provider_Resolver::annotate($retry, $fallback_resolution);
    }

    /**
     * Force a Replicate billing failure to a terminal failed state with the
     * user-facing message required by the spec.
     *
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function mark_replicate_billing_failed(array $result): array {
        $lines = class_exists('YooY_Provider_Billing_Error')
            ? YooY_Provider_Billing_Error::provider_failure_lines('Replicate')
            : ['primary' => 'Replicate API 계정의 크레딧이 부족합니다.', 'user_credits_ok' => 'YooY 사용자 크레딧과는 별도입니다.'];

        $result['status'] = YooY_Job_Status::FAILED;
        $result['progress'] = 100;
        $result['error'] = $lines['primary'] . ' ' . $lines['user_credits_ok'];
        $result['user_message'] = $lines['primary'];
        $result['user_credits_message'] = $lines['user_credits_ok'];
        $result['error_type'] = 'provider_billing';
        return $result;
    }
}
