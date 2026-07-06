<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-avatar-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';

final class YooY_Mock_Avatar_Provider implements YooY_Avatar_Provider_Interface {

    public function id(): string { return 'mock'; }
    public function name(): string { return 'Mock Avatar'; }

    public function models(): array {
        return [
            ['id' => 'mock-avatar-v1', 'name' => 'Mock Avatar v1', 'type' => 'talking_head'],
            ['id' => 'mock-avatar-v2', 'name' => 'Mock Avatar v2 (Full Body)', 'type' => 'full_body'],
        ];
    }

    public function generate(array $params): array {
        $job_id = $params['job_id'] ?? ('avt_' . wp_generate_uuid4());
        $ratio  = $params['aspect_ratio'] ?? '16:9';
        [$w, $h] = $ratio === '9:16' ? [720, 1280] : [1280, 720];
        $preview = YooY_Asset_Generator::svg_data_uri($w, $h, 'YooY Avatar');
        $thumb   = YooY_Asset_Generator::svg_data_uri((int) ($w / 4), (int) ($h / 4), 'Avatar', '#1a1a1a', '#ffd76a');

        return [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'mock-avatar-v1',
            'script'       => $params['script'] ?? '',
            'duration'     => (int) ($params['duration'] ?? 30),
            'progress'     => 100,
            'output'       => [
                'video_url'    => $preview,
                'thumbnail'    => $thumb,
                'subtitle_url' => null,
                'format'       => 'mp4',
                'mime'         => 'image/svg+xml',
            ],
            'avatar'       => $params['avatar_id'] ?? 'default',
            'voice'        => $params['voice_id'] ?? 'ko_female_01',
            'lip_sync'     => !empty($params['lip_sync']),
            'expression'   => $params['expression'] ?? 'neutral',
            'gesture'      => $params['gesture'] ?? 'natural',
            'camera'       => $params['camera'] ?? 'medium',
            'emotion'      => $params['emotion'] ?? 'confident',
            'background'   => $params['background'] ?? 'studio',
            'scene'        => $params['scene_id'] ?? 'default',
            'credits_used' => $this->estimate_credits($params),
            'meta'         => ['mock' => true, 'preview_mode' => true, 'korean_context' => !empty($params['korean_context'])],
        ];
    }

    public function status(string $job_id): array {
        return ['job_id' => $job_id, 'status' => YooY_Job_Status::COMPLETED, 'progress' => 100];
    }

    private function estimate_credits(array $params): int {
        $duration = (int) ($params['duration'] ?? 30);
        return 30 + (int) floor($duration / 10) * 5;
    }
}
