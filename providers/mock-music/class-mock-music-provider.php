<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-music-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';

final class YooY_Mock_Music_Provider implements YooY_Music_Provider_Interface {

    public function id(): string { return 'mock'; }
    public function name(): string { return 'Mock Music'; }

    public function models(): array {
        return [
            ['id' => 'mock-music-v1', 'name' => 'Mock Music v1', 'max_duration' => 240],
            ['id' => 'mock-music-v2', 'name' => 'Mock Music v2 (Extended)', 'max_duration' => 480],
        ];
    }

    public function generate(array $params): array {
        $job_id   = $params['job_id'] ?? ('mus_' . wp_generate_uuid4());
        $title    = $params['title'] ?? $this->derive_title($params);
        $duration = min(240, max(30, (int) ($params['duration'] ?? 120)));
        $genre    = $params['genre'] ?? 'pop';
        $vocal    = $params['vocal'] ?? 'female';

        return [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'mock-music-v1',
            'title'        => $title,
            'duration'     => $duration,
            'genre'        => $genre,
            'mood'         => $params['mood'] ?? 'upbeat',
            'tempo'        => (int) ($params['tempo'] ?? 120),
            'vocal'        => $vocal,
            'language'     => $params['language'] ?? 'ko',
            'progress'     => 100,
            'output'       => [
                'audio_url'    => YooY_Asset_Generator::silent_audio_data_uri(),
                'cover_url'    => YooY_Asset_Generator::audio_data_uri($title),
                'waveform_url' => YooY_Asset_Generator::waveform_data_uri('Waveform'),
                'format'       => 'mp3',
                'duration_sec' => $duration,
            ],
            'lyrics'       => $params['lyrics'] ?? '',
            'structure'    => $params['structure'] ?? [],
            'credits_used' => $this->estimate_credits($params),
            'meta'         => ['mock' => true, 'korean_context' => !empty($params['korean_context'])],
        ];
    }

    public function status(string $job_id): array {
        return ['job_id' => $job_id, 'status' => YooY_Job_Status::COMPLETED, 'progress' => 100];
    }

    private function derive_title(array $params): string {
        if (!empty($params['title'])) return $params['title'];
        $genre = $params['genre'] ?? 'K-Pop';
        return $genre . ' Track ' . substr(wp_generate_uuid4(), 0, 4);
    }

    private function estimate_credits(array $params): int {
        $duration = (int) ($params['duration'] ?? 120);
        return 20 + (int) floor($duration / 30) * 5;
    }
}
