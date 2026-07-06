<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-image-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-yoy-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-image/class-mock-image-provider.php';

final class YooY_Replicate_Image_Provider implements YooY_Image_Provider_Interface, YooY_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_replicate_api_key', '');
    }

    public function id(): string { return 'replicate'; }
    public function name(): string { return 'Replicate'; }
    public function types(): array { return ['image']; }

    public function models(): array {
        return [
            ['id' => 'flux-schnell', 'name' => 'FLUX Schnell', 'max_count' => 4, 'resolutions' => ['512', '1024']],
            ['id' => 'flux-dev', 'name' => 'FLUX Dev', 'max_count' => 4, 'resolutions' => ['1024', '1536']],
            ['id' => 'sdxl', 'name' => 'SDXL', 'max_count' => 4, 'resolutions' => ['1024', '1536', '2048']],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'flux-schnell',
            ]));
        }

        $job_id = $params['job_id'] ?? ('img_' . wp_generate_uuid4());
        $model  = $this->model_version($params['model'] ?? 'flux-schnell');

        $response = wp_remote_post('https://api.replicate.com/v1/predictions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Token ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'version' => $model,
                'input'   => [
                    'prompt' => $params['prompt'] ?? '',
                    'num_outputs' => min(4, max(1, (int) ($params['image_count'] ?? 1))),
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $vendor = YooY_Job_Normalizer::from_vendor('replicate', $body);

        $result = [
            'job_id'       => $body['id'] ?? $job_id,
            'status'       => $vendor['status'],
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'flux-schnell',
            'prompt'       => $params['prompt'] ?? '',
            'progress'     => $vendor['progress'],
            'output'       => $vendor['output'],
            'images'       => $this->images_from_output($vendor['output']),
            'credits_used' => 12 * max(1, (int) ($params['image_count'] ?? 1)),
            'error'        => $vendor['error'],
            'raw'          => $body,
        ];

        return YooY_Job_Normalizer::normalize($result, 'image');
    }

    public function edit(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->edit(array_merge($params, ['provider' => $this->id()]));
        }
        throw new Exception('Replicate image edit is not configured yet.');
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->status($job_id);
        }

        $response = wp_remote_get('https://api.replicate.com/v1/predictions/' . $job_id, [
            'headers' => ['Authorization' => 'Token ' . $this->api_key],
        ]);

        if (is_wp_error($response)) {
            return YooY_Job_Normalizer::normalize([
                'job_id' => $job_id,
                'status' => YooY_Job_Status::FAILED,
                'error'  => $response->get_error_message(),
            ], 'image');
        }

        $body   = json_decode(wp_remote_retrieve_body($response), true);
        $vendor = YooY_Job_Normalizer::from_vendor('replicate', $body);

        return YooY_Job_Normalizer::normalize([
            'job_id'   => $job_id,
            'status'   => $vendor['status'],
            'provider' => $this->id(),
            'progress' => $vendor['progress'],
            'output'   => $vendor['output'],
            'images'   => $this->images_from_output($vendor['output']),
            'error'    => $vendor['error'],
            'raw'      => $body,
        ], 'image');
    }

    private function model_version(string $model): string {
        return match ($model) {
            'flux-dev' => 'black-forest-labs/flux-dev',
            'sdxl'     => 'stability-ai/sdxl',
            default    => 'black-forest-labs/flux-schnell',
        };
    }

    private function images_from_output(?array $output): array {
        if (!$output) return [];
        $urls = $output['urls'] ?? [];
        if (empty($urls) && !empty($output['primary'])) {
            $urls = [$output['primary']];
        }
        $images = [];
        foreach ($urls as $url) {
            if (!$url) continue;
            $images[] = ['url' => $url, 'thumbnail' => $url];
        }
        return $images;
    }
}
