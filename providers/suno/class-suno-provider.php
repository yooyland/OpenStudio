<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-music-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-music/class-mock-music-provider.php';

final class YooY_Suno_Provider implements YooY_Music_Provider_Interface {

    private string $api_key;
    private string $api_base;

    public function __construct() {
        $this->api_key  = (string) get_option('yoy_suno_api_key', '');
        $this->api_base = (string) get_option('yoy_suno_api_base', 'https://api.sunoapi.org/api/v1');
    }

    public function id(): string { return 'suno'; }
    public function name(): string { return 'Suno'; }

    public function models(): array {
        return [
            ['id' => 'chirp-v3-5', 'name' => 'Suno v3.5', 'max_duration' => 240],
            ['id' => 'chirp-v4', 'name' => 'Suno v4', 'max_duration' => 480],
            ['id' => 'chirp-v4-5', 'name' => 'Suno v4.5', 'max_duration' => 480],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Music_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'chirp-v4',
            ]));
        }

        $job_id = $params['job_id'] ?? ('mus_' . wp_generate_uuid4());
        $mode   = !empty($params['lyrics']) ? 'custom' : 'description';

        $body = [
            'custom_mode' => $mode === 'custom',
            'prompt'      => $this->build_style_prompt($params),
            'title'       => $params['title'] ?? 'YooY Track',
            'make_instrumental' => ($params['vocal'] ?? '') === 'instrumental',
            'model'       => $params['model'] ?? 'chirp-v4',
        ];

        if ($mode === 'custom') {
            $body['prompt'] = $this->build_style_prompt($params);
            $body['tags']     = $this->build_tags($params);
            $body['lyrics']   = $params['lyrics'] ?? '';
        }

        if (!empty($params['reference_url'])) {
            $body['continue_clip_id'] = $params['reference_clip_id'] ?? null;
            $body['continue_at']      = $params['continue_at'] ?? 0;
        }

        $response = wp_remote_post(trailingslashit($this->api_base) . 'generate', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'job_id'       => $data['id'] ?? $data['task_id'] ?? $job_id,
            'status'       => $data['status'] ?? 'processing',
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'chirp-v4',
            'title'        => $params['title'] ?? '',
            'progress'     => 0,
            'output'       => null,
            'credits_used' => 25,
            'raw'          => $data,
        ];
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Music_Provider())->status($job_id);
        }

        $response = wp_remote_get(trailingslashit($this->api_base) . 'generate/' . $job_id, [
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
        ]);

        if (is_wp_error($response)) {
            return ['job_id' => $job_id, 'status' => 'error', 'error' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true) ?: ['job_id' => $job_id, 'status' => 'unknown'];
    }

    private function build_style_prompt(array $params): string {
        $parts = [];
        if (!empty($params['genre'])) $parts[] = $params['genre'];
        if (!empty($params['mood'])) $parts[] = $params['mood'];
        if (!empty($params['tempo'])) $parts[] = $params['tempo'] . ' BPM';
        if (!empty($params['instrument'])) $parts[] = $params['instrument'];
        if (!empty($params['negative_prompt'])) $parts[] = 'avoid: ' . $params['negative_prompt'];
        if (!empty($params['style_prompt'])) return $params['style_prompt'];
        return implode(', ', $parts) ?: ($params['prompt'] ?? 'pop song');
    }

    private function build_tags(array $params): string {
        $tags = array_filter([
            $params['genre'] ?? '',
            $params['mood'] ?? '',
            $params['instrument'] ?? '',
            $params['vocal'] ?? '',
        ]);
        return implode(', ', $tags);
    }
}
