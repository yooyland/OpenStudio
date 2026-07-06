<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-image-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-image/class-mock-image-provider.php';

final class YooY_Topview_Image_Provider implements YooY_Image_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_topview_api_key', '');
    }

    public function id(): string { return 'topview'; }
    public function name(): string { return 'Topview Image'; }

    public function models(): array {
        return [
            ['id' => 'topview-product', 'name' => 'Product Image', 'max_count' => 4, 'resolutions' => ['1024', '2048']],
            ['id' => 'topview-banner', 'name' => 'Banner / Ad', 'max_count' => 2, 'resolutions' => ['1024', '1920']],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            $mock = (new YooY_Mock_Image_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'topview-product',
            ]));
            $mock['meta']['commercial_style'] = 'korean_ecommerce';
            $mock['meta']['topview'] = true;
            return $mock;
        }

        $job_id = $params['job_id'] ?? ('img_' . wp_generate_uuid4());

        $response = wp_remote_post('https://api.topview.ai/v1/image/generate', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'prompt'        => $params['prompt'] ?? '',
                'reference_url' => $params['reference_url'] ?? '',
                'aspect_ratio'  => $params['aspect_ratio'] ?? '1:1',
                'resolution'    => $params['resolution'] ?? '1024',
                'style'         => $params['style'] ?? 'commercial',
                'count'         => (int) ($params['image_count'] ?? 1),
                'language'      => 'ko',
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'job_id'       => $data['task_id'] ?? $job_id,
            'status'       => $data['status'] ?? 'processing',
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'topview-product',
            'prompt'       => $params['prompt'] ?? '',
            'images'       => $data['images'] ?? [],
            'credits_used' => 8 * (int) ($params['image_count'] ?? 1),
            'raw'          => $data,
        ];
    }

    public function edit(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->edit(array_merge($params, ['provider' => $this->id()]));
        }

        $response = wp_remote_post('https://api.topview.ai/v1/image/edit', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'source_url' => $params['source_url'] ?? '',
                'prompt'     => $params['prompt'] ?? '',
                'mode'       => $params['mode'] ?? 'edit',
                'mask_url'   => $params['mask_url'] ?? '',
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'job_id'   => $data['task_id'] ?? ('imgedit_' . wp_generate_uuid4()),
            'status'   => $data['status'] ?? 'completed',
            'provider' => $this->id(),
            'mode'     => $params['mode'] ?? 'edit',
            'output'   => $data['output'] ?? [],
            'credits_used' => 10,
            'raw'      => $data,
        ];
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->status($job_id);
        }

        $response = wp_remote_get('https://api.topview.ai/v1/image/status/' . $job_id, [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
        ]);

        if (is_wp_error($response)) {
            return ['job_id' => $job_id, 'status' => 'error', 'error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?: ['job_id' => $job_id, 'status' => 'unknown'];
    }
}
