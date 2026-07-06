<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-avatar-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-avatar/class-mock-avatar-provider.php';

final class YooY_HeyGen_Provider implements YooY_Avatar_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_heygen_api_key', '');
    }

    public function id(): string { return 'heygen'; }
    public function name(): string { return 'HeyGen'; }

    public function models(): array {
        return [
            ['id' => 'heygen-v2', 'name' => 'HeyGen Avatar v2', 'type' => 'talking_head'],
            ['id' => 'heygen-studio', 'name' => 'HeyGen Studio', 'type' => 'studio'],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Avatar_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'heygen-v2',
            ]));
        }

        $job_id = $params['job_id'] ?? ('avt_' . wp_generate_uuid4());

        $response = wp_remote_post('https://api.heygen.com/v2/video/generate', [
            'timeout' => 120,
            'headers' => [
                'X-Api-Key'    => $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'video_inputs' => [[
                    'character' => [
                        'type'       => 'avatar',
                        'avatar_id'  => $params['avatar_id'] ?? '',
                        'avatar_style' => $params['expression'] ?? 'normal',
                    ],
                    'voice' => [
                        'type'       => 'text',
                        'input_text' => $params['script'] ?? '',
                        'voice_id'   => $params['voice_id'] ?? '',
                    ],
                    'background' => ['type' => $params['background'] ?? 'color', 'value' => '#1a1a1a'],
                ]],
                'dimension' => ['width' => 1280, 'height' => 720],
            ]),
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'job_id'   => $data['data']['video_id'] ?? $job_id,
            'status'   => 'processing',
            'provider' => $this->id(),
            'progress' => 0,
            'output'   => null,
            'credits_used' => 40,
            'raw'      => $data,
        ];
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') return (new YooY_Mock_Avatar_Provider())->status($job_id);

        $response = wp_remote_get('https://api.heygen.com/v1/video_status.get?video_id=' . $job_id, [
            'headers' => ['X-Api-Key' => $this->api_key],
        ]);

        if (is_wp_error($response)) {
            return ['job_id' => $job_id, 'status' => 'error', 'error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?: ['job_id' => $job_id, 'status' => 'unknown'];
    }
}
