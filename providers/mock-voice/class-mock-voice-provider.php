<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-voice-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';

final class YooY_Mock_Voice_Provider implements YooY_Voice_Provider_Interface {

    public function id(): string { return 'mock'; }
    public function name(): string { return 'Mock Voice'; }

    public function models(): array {
        return [
            ['id' => 'mock-tts-v1', 'name' => 'Mock TTS v1'],
            ['id' => 'mock-tts-v2', 'name' => 'Mock TTS v2 (Multilingual)'],
        ];
    }

    public function speak(array $params): array {
        $job_id = $params['job_id'] ?? ('vce_' . wp_generate_uuid4());
        $text   = $params['text'] ?? '';

        return [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'mock-tts-v1',
            'voice_id'     => $params['voice_id'] ?? 'ko_female_warm',
            'text'         => $text,
            'duration_est' => $this->estimate_duration($text, (float) ($params['speed'] ?? 1.0)),
            'output'       => [
                'audio_url' => YooY_Asset_Generator::silent_audio_data_uri(),
                'format'    => 'mp3',
            ],
            'settings'     => [
                'emotion'  => $params['emotion'] ?? 'neutral',
                'language' => $params['language'] ?? 'ko',
                'speed'    => (float) ($params['speed'] ?? 1.0),
                'pitch'    => (float) ($params['pitch'] ?? 0),
            ],
            'credits_used' => $this->estimate_credits($text),
            'meta'         => ['mock' => true],
        ];
    }

    public function clone_voice(array $params): array {
        $name = $params['clone_name'] ?? 'Cloned Voice';
        return [
            'job_id'    => 'vclone_' . wp_generate_uuid4(),
            'status'    => YooY_Job_Status::COMPLETED,
            'provider'  => $this->id(),
            'voice_id'  => 'clone_' . substr(wp_generate_uuid4(), 0, 8),
            'name'      => $name,
            'cloned'    => true,
            'meta'      => ['mock' => true],
        ];
    }

    public function status(string $job_id): array {
        return ['job_id' => $job_id, 'status' => YooY_Job_Status::COMPLETED, 'progress' => 100];
    }

    private function estimate_duration(string $text, float $speed): float {
        $chars = mb_strlen(preg_replace('/\[pause:[^\]]+\]/', '', $text));
        return round(($chars / 10) / max(0.5, $speed), 1);
    }

    private function estimate_credits(string $text): int {
        return max(5, (int) ceil(mb_strlen($text) / 100) * 3);
    }
}
