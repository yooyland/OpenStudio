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
        $user_id  = (int) ($params['user_id'] ?? get_current_user_id());

        [$w, $h] = $this->dimensions($ratio);
        $base    = sanitize_file_name($job_id);
        $label   = mb_substr(trim($prompt), 0, 60) ?: 'YooY Video';
        $asset   = YooY_Asset_Generator::import_mock_image($base, $w, $h, $label, $user_id);

        if (empty($asset['url'])) {
            return YooY_Job_Normalizer::normalize([
                'job_id'   => $job_id,
                'status'   => YooY_Job_Status::FAILED,
                'provider' => $params['provider'] ?? $this->id(),
                'prompt'   => $prompt,
                'error'    => 'Generation completed but no output asset was returned.',
            ], 'video');
        }

        $url   = $asset['url'];
        $thumb = $asset['thumbnail'] ?: $url;

        return YooY_Job_Normalizer::normalize([
            'job_id'          => $job_id,
            'provider_job_id' => $job_id,
            'status'          => YooY_Job_Status::COMPLETED,
            'provider'        => $params['provider'] ?? $this->id(),
            'model'           => $params['model'] ?? 'mock-v1',
            'prompt'          => $prompt,
            'duration'        => $duration,
            'aspect_ratio'    => $ratio,
            'progress'        => 100,
            'stage'           => 'completed',
            'output'          => [
                'url'           => $url,
                'thumbnail'     => $thumb,
                'format'        => 'mp4',
                'duration_sec'  => $duration,
                'mime'          => 'image/png',
                'attachment_id' => (int) ($asset['attachment_id'] ?? 0),
            ],
            'credits_used'    => $this->estimate_credits($params),
            'meta'            => [
                'mock' => true,
                'preview_mode' => true,
                'korean_context' => !empty($params['korean_context']),
                'reference_url' => $params['reference_url'] ?? '',
                'reference_assets' => $params['reference_assets'] ?? [],
            ],
        ], 'video');
    }

    public function status(string $job_id): array {
        return YooY_Job_Normalizer::normalize([
            'job_id'          => $job_id,
            'provider_job_id' => $job_id,
            'status'          => YooY_Job_Status::COMPLETED,
            'progress'        => 100,
            'stage'           => 'completed',
        ], 'video');
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
