<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-video-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-video/class-mock-video-provider.php';

final class YooY_Topview_Provider implements YooY_Video_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_topview_api_key', '');
    }

    public function id(): string { return 'topview'; }
    public function name(): string { return 'Topview'; }

    public function models(): array {
        return [
            ['id' => 'topview-v1', 'name' => 'Topview Commercial v1', 'max_duration' => 60, 'resolutions' => ['1080p']],
            ['id' => 'topview-ads', 'name' => 'Topview Ads', 'max_duration' => 30, 'resolutions' => ['1080p', '9:16']],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            $mock = (new YooY_Mock_Video_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'topview-v1',
            ]));
            $mock['meta']['topview_template'] = $params['template_id'] ?? null;
            $mock['meta']['commercial_style'] = 'korean_ecommerce';
            return $mock;
        }

        $job_id = $params['job_id'] ?? ('vid_' . wp_generate_uuid4());

        $response = wp_remote_post('https://api.topview.ai/v1/video/generate', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'prompt'      => $params['prompt'] ?? '',
                'template_id' => $params['template_id'] ?? null,
                'duration'    => (int) ($params['duration'] ?? 15),
                'aspect_ratio' => $params['aspect_ratio'] ?? '9:16',
                'language'    => 'ko',
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'job_id'       => $body['task_id'] ?? $job_id,
            'status'       => $body['status'] ?? 'processing',
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'topview-v1',
            'prompt'       => $params['prompt'] ?? '',
            'progress'     => 0,
            'output'       => null,
            'credits_used' => 40,
            'raw'          => $body,
        ];
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Video_Provider())->status($job_id);
        }

        $response = wp_remote_get('https://api.topview.ai/v1/video/status/' . $job_id, [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
        ]);

        if (is_wp_error($response)) {
            return ['job_id' => $job_id, 'status' => 'error', 'error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?: ['job_id' => $job_id, 'status' => 'unknown'];
    }
}
