<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-avatar-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-avatar/class-mock-avatar-provider.php';

final class YooY_Vidu_Provider implements YooY_Avatar_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_vidu_api_key', '');
    }

    public function id(): string { return 'vidu'; }
    public function name(): string { return 'Vidu'; }

    public function models(): array {
        return [
            ['id' => 'vidu-avatar', 'name' => 'Vidu Avatar', 'type' => 'character'],
            ['id' => 'vidu-scene', 'name' => 'Vidu Scene', 'type' => 'scene'],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            $mock = (new YooY_Mock_Avatar_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'vidu-avatar',
            ]));
            $mock['meta']['vidu_scene'] = $params['scene_id'] ?? null;
            return $mock;
        }

        $job_id = $params['job_id'] ?? ('avt_' . wp_generate_uuid4());

        $response = wp_remote_post('https://api.vidu.com/ent/v2/avatar/generate', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'prompt'     => $params['script'] ?? '',
                'avatar_id'  => $params['avatar_id'] ?? '',
                'scene'      => $params['scene_id'] ?? '',
                'camera'     => $params['camera'] ?? 'medium',
                'emotion'    => $params['emotion'] ?? 'neutral',
                'gesture'    => $params['gesture'] ?? 'natural',
                'background' => $params['background'] ?? 'studio',
                'lip_sync'   => !empty($params['lip_sync']),
                'subtitle'   => !empty($params['subtitle_enabled']),
            ]),
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'job_id'   => $data['task_id'] ?? $job_id,
            'status'   => $data['state'] ?? 'processing',
            'provider' => $this->id(),
            'progress' => 0,
            'output'   => null,
            'credits_used' => 35,
            'raw'      => $data,
        ];
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') return (new YooY_Mock_Avatar_Provider())->status($job_id);

        $response = wp_remote_get('https://api.vidu.com/ent/v2/tasks/' . $job_id . '/creations', [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
        ]);

        if (is_wp_error($response)) {
            return ['job_id' => $job_id, 'status' => 'error', 'error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?: ['job_id' => $job_id, 'status' => 'unknown'];
    }
}
