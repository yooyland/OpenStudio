<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-video-provider.php';

final class YooY_Runway_Provider implements YooY_Video_Provider_Interface {

    public function id(): string { return 'runway'; }
    public function name(): string { return 'Runway'; }

    public function models(): array {
        return [
            ['id' => 'gen-3-alpha', 'name' => 'Gen-3 Alpha', 'max_duration' => 10, 'resolutions' => ['720p', '1080p']],
            ['id' => 'gen-4-turbo', 'name' => 'Gen-4 Turbo', 'max_duration' => 16, 'resolutions' => ['720p', '1080p', '4k']],
        ];
    }

    public function generate(array $params): array {
        return YooY_Job_Normalizer::normalize([
            'job_id'          => $params['job_id'] ?? ('vid_' . wp_generate_uuid4()),
            'status'          => YooY_Job_Status::FAILED,
            'provider'        => $this->id(),
            'model'           => $params['model'] ?? 'gen-3-alpha',
            'prompt'          => $params['prompt'] ?? '',
            'provider_job_id' => '',
            'error'           => 'Runway real generation is not implemented/configured yet.',
            'meta'            => [
                'bridge_unimplemented' => true,
                'reference_url' => $params['reference_url'] ?? '',
                'reference_assets' => $params['reference_assets'] ?? [],
            ],
        ], 'video');
    }

    public function status(string $job_id): array {
        return YooY_Job_Normalizer::normalize([
            'job_id'          => $job_id,
            'status'          => YooY_Job_Status::FAILED,
            'provider'        => $this->id(),
            'provider_job_id' => $job_id,
            'error'           => 'Runway real generation is not implemented/configured yet.',
            'meta'            => ['bridge_unimplemented' => true],
        ], 'video');
    }
}
