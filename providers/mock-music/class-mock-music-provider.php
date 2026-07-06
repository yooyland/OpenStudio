<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-music-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-mock-job-engine.php';

final class YooY_Mock_Music_Provider implements YooY_Music_Provider_Interface {

    private YooY_Mock_Job_Engine $jobs;

    public function __construct() {
        $this->jobs = new YooY_Mock_Job_Engine();
    }

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
        $completed = $this->build_result($job_id, $params);
        $async    = !isset($params['async']) || !empty($params['async']);

        if ($async) {
            $this->jobs->create($job_id, $params, fn() => $completed);
            $this->jobs->register_result($job_id, $completed);
            return [
                'job_id'       => $job_id,
                'status'       => YooY_Job_Status::QUEUED,
                'type'         => 'music',
                'provider'     => $this->id(),
                'model'        => $params['model'] ?? 'mock-music-v1',
                'prompt'       => $params['lyrics'] ?? $params['style_prompt'] ?? $params['prompt'] ?? '',
                'title'        => $completed['title'] ?? 'Track',
                'progress'     => 0,
                'credits_used' => $this->estimate_credits($params),
                'meta'         => ['mock' => true, 'async' => true],
            ];
        }

        $this->jobs->complete_immediately($job_id, $completed);
        return $completed;
    }

    public function status(string $job_id): array {
        $polled = $this->jobs->poll($job_id);
        if (($polled['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            return array_merge($polled, ['type' => 'music']);
        }
        return array_merge($polled, [
            'type'     => 'music',
            'provider' => $polled['provider'] ?? 'mock',
        ]);
    }

    private function build_result(string $job_id, array $params): array {
        $title    = $params['title'] ?? $this->derive_title($params);
        $duration = min(240, max(30, (int) ($params['duration'] ?? 120)));

        return [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'type'         => 'music',
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'mock-music-v1',
            'title'        => $title,
            'prompt'       => $params['lyrics'] ?? $params['style_prompt'] ?? $params['prompt'] ?? '',
            'duration'     => $duration,
            'genre'        => $params['genre'] ?? 'pop',
            'mood'         => $params['mood'] ?? 'upbeat',
            'tempo'        => (int) ($params['tempo'] ?? 120),
            'vocal'        => $params['vocal'] ?? 'female',
            'language'     => $params['language'] ?? 'ko',
            'progress'     => 100,
            'output'       => [
                'audio_url'    => YooY_Asset_Generator::silent_audio_data_uri(),
                'cover_url'    => YooY_Asset_Generator::audio_data_uri($title),
                'waveform_url' => YooY_Asset_Generator::waveform_data_uri('Waveform'),
                'primary'      => YooY_Asset_Generator::silent_audio_data_uri(),
                'format'       => 'mp3',
                'duration_sec' => $duration,
                'mime'         => 'audio/mpeg',
            ],
            'lyrics'       => $params['lyrics'] ?? '',
            'structure'    => $params['structure'] ?? [],
            'credits_used' => $this->estimate_credits($params),
            'meta'         => ['mock' => true, 'korean_context' => !empty($params['korean_context'])],
        ];
    }

    private function derive_title(array $params): string {
        if (!empty($params['title'])) return $params['title'];
        $genre = $params['genre'] ?? 'K-Pop';
        return $genre . ' Track ' . substr(wp_generate_uuid4(), 0, 4);
    }

    private function estimate_credits(array $params): int {
        $duration = (int) ($params['duration'] ?? 120);
        $quality  = ($params['audio_quality'] ?? 'standard') === 'high' ? 10 : 0;
        return 20 + (int) floor($duration / 30) * 5 + $quality;
    }
}
