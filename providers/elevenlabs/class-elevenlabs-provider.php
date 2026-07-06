<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-voice-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-voice/class-mock-voice-provider.php';

final class YooY_ElevenLabs_Provider implements YooY_Voice_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_elevenlabs_api_key', '');
    }

    public function id(): string { return 'elevenlabs'; }
    public function name(): string { return 'ElevenLabs'; }

    public function models(): array {
        return [
            ['id' => 'eleven_multilingual_v2', 'name' => 'Multilingual v2'],
            ['id' => 'eleven_turbo_v2_5', 'name' => 'Turbo v2.5'],
            ['id' => 'eleven_flash_v2_5', 'name' => 'Flash v2.5'],
        ];
    }

    public function speak(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Voice_Provider())->speak(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'eleven_multilingual_v2',
            ]));
        }

        $job_id   = $params['job_id'] ?? ('vce_' . wp_generate_uuid4());
        $voice_id = $params['voice_id'] ?? '21m00Tcm4TlvDq8ikWAM';
        $text     = $params['processed_text'] ?? $params['text'] ?? '';

        $response = wp_remote_post('https://api.elevenlabs.io/v1/text-to-speech/' . $voice_id, [
            'timeout' => 90,
            'headers' => [
                'xi-api-key'    => $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'audio/mpeg',
            ],
            'body' => wp_json_encode([
                'text'     => $text,
                'model_id' => $params['model'] ?? 'eleven_multilingual_v2',
                'voice_settings' => [
                    'stability'         => ($params['stability'] ?? 50) / 100,
                    'similarity_boost'  => ($params['similarity'] ?? 75) / 100,
                    'style'             => ($params['style_exaggeration'] ?? 0) / 100,
                    'use_speaker_boost' => !empty($params['speaker_boost']),
                ],
            ]),
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $audio_body = wp_remote_retrieve_body($response);
        $url        = $this->save_audio($job_id, $audio_body);

        return [
            'job_id'       => $job_id,
            'status'       => 'completed',
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'eleven_multilingual_v2',
            'voice_id'     => $voice_id,
            'text'         => $params['text'] ?? '',
            'output'       => ['audio_url' => $url, 'format' => 'mp3'],
            'credits_used' => max(5, (int) ceil(mb_strlen($text) / 100) * 3),
        ];
    }

    public function clone_voice(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Voice_Provider())->clone_voice($params);
        }

        $boundary = wp_generate_uuid4();
        $body     = $this->build_clone_multipart($boundary, $params);

        $response = wp_remote_post('https://api.elevenlabs.io/v1/voices/add', [
            'timeout' => 120,
            'headers' => [
                'xi-api-key'     => $this->api_key,
                'Content-Type'   => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) throw new Exception($response->get_error_message());

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'job_id'   => 'vclone_' . wp_generate_uuid4(),
            'status'   => 'completed',
            'provider' => $this->id(),
            'voice_id' => $data['voice_id'] ?? '',
            'name'     => $params['clone_name'] ?? 'Cloned Voice',
            'cloned'   => true,
            'raw'      => $data,
        ];
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') return (new YooY_Mock_Voice_Provider())->status($job_id);
        return ['job_id' => $job_id, 'status' => 'completed', 'progress' => 100];
    }

    private function save_audio(string $job_id, string $binary): string {
        $upload_dir = wp_upload_dir();
        $filename   = 'yoy-tts-' . $job_id . '.mp3';
        $filepath   = $upload_dir['path'] . '/' . $filename;
        file_put_contents($filepath, $binary);
        return $upload_dir['url'] . '/' . $filename;
    }

    private function build_clone_multipart(string $boundary, array $params): string {
        $parts = '';
        $parts .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"name\"\r\n\r\n" . ($params['clone_name'] ?? 'Clone') . "\r\n";
        if (!empty($params['clone_description'])) {
            $parts .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"description\"\r\n\r\n" . $params['clone_description'] . "\r\n";
        }
        if (!empty($params['sample_base64'])) {
            $decoded = base64_decode(preg_replace('#^data:audio/[^;]+;base64,#', '', $params['sample_base64']));
            $parts .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"files\"; filename=\"sample.mp3\"\r\nContent-Type: audio/mpeg\r\n\r\n" . $decoded . "\r\n";
        }
        $parts .= "--{$boundary}--\r\n";
        return $parts;
    }
}
