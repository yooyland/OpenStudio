<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-video-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-video/class-mock-video-provider.php';

final class YooY_Runway_Provider implements YooY_Video_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_runway_api_key', '');
    }

    public function id(): string { return 'runway'; }
    public function name(): string { return 'Runway'; }

    public function models(): array {
        return [
            ['id' => 'gen-3-alpha', 'name' => 'Gen-3 Alpha', 'max_duration' => 10, 'resolutions' => ['720p', '1080p']],
            ['id' => 'gen-4-turbo', 'name' => 'Gen-4 Turbo', 'max_duration' => 16, 'resolutions' => ['720p', '1080p', '4k']],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Video_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'gen-3-alpha',
            ]));
        }

        $job_id = $params['job_id'] ?? ('vid_' . wp_generate_uuid4());

        $response = wp_remote_post('https://api.dev.runwayml.com/v1/generations', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'X-Runway-Version' => '2024-11-06',
            ],
            'body' => wp_json_encode([
                'promptText' => $params['prompt'] ?? '',
                'model'      => $params['model'] ?? 'gen-3-alpha',
                'duration'   => (int) ($params['duration'] ?? 5),
                'ratio'      => $this->map_ratio($params['aspect_ratio'] ?? '16:9'),
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return YooY_Job_Normalizer::normalize([
            'job_id'       => $body['id'] ?? $job_id,
            'status'       => YooY_Job_Status::normalize((string) ($body['status'] ?? YooY_Job_Status::QUEUED)),
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'gen-3-alpha',
            'prompt'       => $params['prompt'] ?? '',
            'progress'     => 0,
            'output'       => null,
            'credits_used' => 50,
            'raw'          => $body,
        ], 'video');
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Video_Provider())->status($job_id);
        }

        $response = wp_remote_get('https://api.dev.runwayml.com/v1/generations/' . $job_id, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'X-Runway-Version' => '2024-11-06',
            ],
        ]);

        if (is_wp_error($response)) {
            return YooY_Job_Normalizer::normalize([
                'job_id' => $job_id,
                'status' => YooY_Job_Status::FAILED,
                'error'  => $response->get_error_message(),
            ], 'video');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true) ?: [];
        $vendor = YooY_Job_Normalizer::from_vendor('runway', $body);

        return YooY_Job_Normalizer::normalize([
            'job_id'   => $job_id,
            'status'   => $vendor['status'],
            'provider' => $this->id(),
            'progress' => $vendor['progress'],
            'output'   => $vendor['output'],
            'error'    => $vendor['error'],
            'raw'      => $body,
        ], 'video');
    }

    private function map_ratio(string $ratio): string {
        switch ($ratio) {
            case '9:16':
                return '9:16';
            case '1:1':
                return '1:1';
            case '4:5':
                return '4:5';
            default:
                return '16:9';
        }
    }
}
