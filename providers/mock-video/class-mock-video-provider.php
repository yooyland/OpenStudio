<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-video-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';

final class YooY_Mock_Video_Provider implements YooY_Video_Provider_Interface {

    public function id(): string { return 'mock'; }
    public function name(): string { return 'Mock Video'; }

    public function models(): array {
        return [
            ['id' => 'mock-v1', 'name' => 'Mock Video v1', 'max_duration' => 30, 'resolutions' => ['720p', '1080p']],
        ];
    }

    public function generate(array $params): array {
        $job_id   = $params['job_id'] ?? ('vid_' . wp_generate_uuid4());
        $prompt   = $params['prompt'] ?? '';
        $ratio    = $params['aspect_ratio'] ?? '16:9';
        $duration = (int) ($params['duration'] ?? 5);

        [$w, $h] = $this->dimensions($ratio);
        $preview = YooY_Asset_Generator::svg_data_uri($w, $h, 'YooY Video');
        $thumb   = YooY_Asset_Generator::svg_data_uri((int) ($w / 4), (int) ($h / 4), 'Preview', '#1a1a1a', '#ffd76a');

        return [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'mock-v1',
            'prompt'       => $prompt,
            'duration'     => $duration,
            'aspect_ratio' => $ratio,
            'progress'     => 100,
            'output'       => [
                'url'          => $preview,
                'thumbnail'    => $thumb,
                'format'       => 'mp4',
                'duration_sec' => $duration,
                'mime'         => 'image/svg+xml',
            ],
            'credits_used' => $this->estimate_credits($params),
            'meta'         => ['mock' => true, 'preview_mode' => true, 'korean_context' => !empty($params['korean_context'])],
        ];
    }

    public function status(string $job_id): array {
        return ['job_id' => $job_id, 'status' => YooY_Job_Status::COMPLETED, 'progress' => 100];
    }

    private function dimensions(string $ratio): array {
        switch ($ratio) {
            case '9:16':
                return [720, 1280];
            case '1:1':
                return [1080, 1080];
            default:
                return [1280, 720];
        }
    }

    private function estimate_credits(array $params): int {
        $duration = (int) ($params['duration'] ?? 5);
        $quality  = $params['quality'] ?? 'standard';
        $base     = ['draft' => 20, 'standard' => 50, 'pro' => 100][$quality] ?? 50;
        return $base + ($duration > 5 ? ($duration - 5) * 5 : 0);
    }
}
