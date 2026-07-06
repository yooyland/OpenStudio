<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_Edit {

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

    public function edit(int $user_id, array $params): array {
        return $this->run($user_id, 'edit', $params);
    }

    public function upscale(int $user_id, array $params): array {
        $params['mode'] = 'upscale';
        $params['prompt'] = $params['prompt'] ?? 'Upscale image to higher resolution, preserve details';
        return $this->run($user_id, 'upscale', $params);
    }

    public function inpaint(int $user_id, array $params): array {
        $params['mode'] = 'inpaint';
        if (empty($params['prompt'])) throw new Exception('Inpaint prompt is required.');
        return $this->run($user_id, 'inpaint', $params);
    }

    public function outpaint(int $user_id, array $params): array {
        $params['mode'] = 'outpaint';
        $params['prompt'] = $params['prompt'] ?? 'Extend image naturally, maintain style consistency';
        return $this->run($user_id, 'outpaint', $params);
    }

    private function run(int $user_id, string $mode, array $params): array {
        $source = esc_url_raw($params['source_url'] ?? '');
        if ($source === '') throw new Exception('Source image URL is required.');

        $payload = [
            'mode'         => $mode,
            'source_url'   => $source,
            'prompt'       => sanitize_textarea_field($params['prompt'] ?? ''),
            'mask_url'     => esc_url_raw($params['mask_url'] ?? ''),
            'provider'     => sanitize_text_field($params['provider'] ?? 'mock'),
            'aspect_ratio' => sanitize_text_field($params['aspect_ratio'] ?? '1:1'),
            'resolution'   => sanitize_text_field($params['resolution'] ?? '1024'),
        ];

        $estimate = $this->credits->estimate($payload);
        if (!$this->credits->can_afford($user_id, $payload)) {
            throw new Exception('Insufficient credits. Required: ' . $estimate);
        }

        $result = $this->router->edit($payload);
        $credit_info = $this->credits->deduct($user_id, (int) ($result['credits_used'] ?? $estimate), 'Image ' . ucfirst($mode));
        $result['credits'] = $credit_info;
        $result['credits_used'] = $credit_info['deducted'] ?: (int) ($result['credits_used'] ?? $estimate);

        $entry = $this->history->add($user_id, array_merge($result, ['studio' => 'image-studio', 'mode' => $mode]));

        if (!empty($params['auto_save'])) {
            $this->gallery->save($user_id, [
                'id'         => $entry['job_id'] ?? $entry['id'],
                'title'      => ucfirst($mode) . ' result',
                'prompt'     => $payload['prompt'],
                'output_url' => $result['output']['primary'] ?? $result['output']['url'] ?? ($result['images'][0]['url'] ?? ''),
                'thumbnail'  => $result['output']['thumbnail'] ?? ($result['images'][0]['thumbnail'] ?? ''),
                'provider'   => $result['provider'] ?? 'mock',
                'model'      => $result['model'] ?? '',
                'credits_used' => $result['credits_used'],
            ]);
            if (function_exists('yoy_gallery_capture')) {
                yoy_gallery_capture($user_id, $entry, 'image', 'image-studio');
            }
        }

        return $entry;
    }
}
