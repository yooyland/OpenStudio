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
        $payload['prompt'] = $this->apply_korean_context($prompt, $payload);
        $payload['prompt'] = $this->apply_style_modifiers($payload);
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

        if (!class_exists('YooY_OpenAI_B64_Asset') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
            require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-openai-b64-asset.php';
        }
        $save_started = microtime(true);
        if (class_exists('YooY_OpenAI_B64_Asset')) {
            $result = YooY_OpenAI_B64_Asset::finalize_job(
                $result,
                $user_id,
                (string) ($result['job_id'] ?? ''),
                sanitize_text_field($payload['output_format'] ?? 'png')
            );
        }
        $perf['image_save_ms'] = (int) round((microtime(true) - $save_started) * 1000);

        $result = YooY_Job_Normalizer::ensure_output_or_fail($result);
        $result = YooY_Provider_Resolver::annotate($result, $resolution);

        if (YooY_Job_Status::is_terminal($result['status'] ?? '')) {
            $credit_info = $this->credits->deduct($user_id, (int) ($result['credits_used'] ?? $estimate), 'Image: ' . mb_substr($prompt, 0, 40));
            $result['credits_used'] = $credit_info['deducted'] ?: (int) ($result['credits_used'] ?? $estimate);
            $result['credits'] = $credit_info;
        }

        $entry = $this->history->add($user_id, array_merge($result, [
            'type'           => 'image',
            'studio'         => 'image-studio',
            'user_prompt'    => sanitize_textarea_field($params['user_prompt'] ?? $prompt),
            'optimized_prompt' => sanitize_textarea_field($params['optimized_prompt'] ?? $payload['prompt'] ?? ''),
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

        if (class_exists('YooY_OpenAI_B64_Asset')) {
            return YooY_OpenAI_B64_Asset::sanitize_job_for_response($entry);
        }

        return $entry;
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
                $status['status'] = YooY_Job_Status::FAILED;
                $status['error'] = 'Job has no provider reference and no output.';
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
            'lighting'       => sanitize_text_field($params['lighting'] ?? 'studio'),
            'composition'    => sanitize_text_field($params['composition'] ?? 'center'),
            'style'          => sanitize_text_field($params['style'] ?? 'commercial'),
            'background'     => sanitize_text_field($params['background'] ?? 'studio_white'),
            'color_palette'  => sanitize_text_field($params['color_palette'] ?? 'neutral'),
            'product_type'   => sanitize_text_field($params['product_type'] ?? 'general'),
            'brand_tone'     => sanitize_text_field($params['brand_tone'] ?? 'premium'),
            'seed'           => (int) ($params['seed'] ?? -1),
            'image_count'    => $image_count,
            'reference_url'  => esc_url_raw($params['reference_url'] ?? ''),
            'reference_assets' => $this->normalize_reference_assets($params),
            'korean_context' => !empty($params['korean_context']),
            'auto_save'      => array_key_exists('auto_save', $params) ? !empty($params['auto_save']) : true,
            'async'          => !empty($params['async']),
            'user_prompt'    => sanitize_textarea_field($params['user_prompt'] ?? $params['prompt'] ?? ''),
            'optimized_prompt' => sanitize_textarea_field($params['optimized_prompt'] ?? ''),
        ];
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
}
