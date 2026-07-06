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
        $prompt = sanitize_textarea_field($params['prompt'] ?? '');
        if ($prompt === '') throw new Exception('Prompt is required.');

        $payload = $this->normalize($params);
        $payload['prompt'] = $this->apply_korean_context($prompt, $payload);
        $payload['prompt'] = $this->apply_style_modifiers($payload);

        $estimate = $this->credits->estimate($payload);
        if (!$this->credits->can_afford($user_id, $payload)) {
            throw new Exception('Insufficient credits. Required: ' . $estimate);
        }

        $result = $this->router->generate($payload);

        if (YooY_Job_Status::is_terminal($result['status'] ?? '')) {
            $credit_info = $this->credits->deduct($user_id, (int) ($result['credits_used'] ?? $estimate), 'Image: ' . mb_substr($prompt, 0, 40));
            $result['credits_used'] = $credit_info['deducted'] ?: (int) ($result['credits_used'] ?? $estimate);
            $result['credits'] = $credit_info;
        }

        $entry = $this->history->add($user_id, array_merge($result, [
            'studio'         => 'image-studio',
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

        if (!empty($params['auto_save']) && ($entry['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $this->gallery->save_from_result($user_id, $entry);
            $this->capture_gallery_items($user_id, $entry);
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
        $status = $this->router->status($provider, $job_id);
        if (!YooY_Job_Status::is_terminal($status['status'] ?? '')) {
            $this->history->add($user_id, array_merge($status, ['studio' => 'image-studio']));
            return $status;
        }

        $existing = $this->history->get($user_id, $job_id);
        if ($existing && !empty($existing['credits']['deducted'])) {
            return $this->history->add($user_id, $status);
        }

        $estimate = $this->credits->estimate($existing ?? $status);
        if (($status['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $credit_info = $this->credits->deduct($user_id, (int) ($status['credits_used'] ?? $estimate), 'Image: ' . mb_substr($status['prompt'] ?? '', 0, 40));
            $status['credits'] = $credit_info;
            $status['credits_used'] = $credit_info['deducted'] ?: (int) ($status['credits_used'] ?? $estimate);
            $this->gallery->save_from_result($user_id, $status);
            $this->capture_gallery_items($user_id, $status);
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
            yoy_gallery_capture($user_id, array_merge($entry, [
                'job_id'     => ($entry['job_id'] ?? '') . '_' . $i,
                'images'     => [$img],
                'image_count'=> 1,
                'thumbnail'  => $img['thumbnail'] ?? $img['url'] ?? '',
                'output_url' => $img['url'] ?? '',
            ]), 'image', 'image-studio');
        }
    }

    private function normalize(array $params): array {
        return [
            'provider'       => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'mock'),
            'model'          => sanitize_text_field($params['model'] ?? $params['default_model'] ?? 'mock-image-v1'),
            'prompt'         => sanitize_textarea_field($params['prompt'] ?? ''),
            'negative_prompt'=> sanitize_textarea_field($params['negative_prompt'] ?? ''),
            'aspect_ratio'   => sanitize_text_field($params['aspect_ratio'] ?? '1:1'),
            'resolution'     => sanitize_text_field($params['resolution'] ?? '1024'),
            'quality'        => sanitize_text_field($params['quality'] ?? 'standard'),
            'lighting'       => sanitize_text_field($params['lighting'] ?? 'studio'),
            'composition'    => sanitize_text_field($params['composition'] ?? 'center'),
            'style'          => sanitize_text_field($params['style'] ?? 'commercial'),
            'background'     => sanitize_text_field($params['background'] ?? 'studio_white'),
            'color_palette'  => sanitize_text_field($params['color_palette'] ?? 'neutral'),
            'product_type'   => sanitize_text_field($params['product_type'] ?? 'general'),
            'brand_tone'     => sanitize_text_field($params['brand_tone'] ?? 'premium'),
            'seed'           => (int) ($params['seed'] ?? -1),
            'image_count'    => min(4, max(1, (int) ($params['image_count'] ?? 1))),
            'reference_url'  => esc_url_raw($params['reference_url'] ?? ''),
            'korean_context' => !empty($params['korean_context']),
            'auto_save'      => array_key_exists('auto_save', $params) ? !empty($params['auto_save']) : true,
            'async'          => !empty($params['async']),
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
}
