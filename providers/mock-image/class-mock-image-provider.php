<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-image-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-yoy-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-mock-job-engine.php';

final class YooY_Mock_Image_Provider implements YooY_Image_Provider_Interface, YooY_Provider_Interface {

    private YooY_Mock_Job_Engine $jobs;

    public function __construct() {
        $this->jobs = new YooY_Mock_Job_Engine();
    }

    public function id(): string { return 'mock'; }
    public function name(): string { return 'Mock Image'; }
    public function types(): array { return ['image']; }

    public function models(): array {
        return [
            ['id' => 'mock-image-v1', 'name' => 'Mock Image v1', 'max_count' => 4, 'resolutions' => ['512', '1024', '1536', '2048']],
            ['id' => 'mock-image-v2', 'name' => 'Mock Image v2 (HD)', 'max_count' => 4, 'resolutions' => ['1024', '1536', '2048']],
        ];
    }

    public function generate(array $params): array {
        $job_id = $params['job_id'] ?? ('img_' . wp_generate_uuid4());
        $async  = !empty($params['async']);

        $completed = $this->build_generate_result($job_id, $params);

        if ($async) {
            $this->jobs->create($job_id, $params, fn() => $completed);
            $this->jobs->register_result($job_id, $completed);
            return YooY_Job_Normalizer::normalize([
                'job_id'       => $job_id,
                'status'       => YooY_Job_Status::QUEUED,
                'provider'     => $this->id(),
                'model'        => $params['model'] ?? 'mock-image-v1',
                'prompt'       => $params['prompt'] ?? '',
                'progress'     => 0,
                'credits_used' => $this->estimate_credits($params),
                'meta'         => ['mock' => true, 'async' => true],
            ], 'image');
        }

        $this->jobs->complete_immediately($job_id, $completed);
        return YooY_Job_Normalizer::normalize($completed, 'image');
    }

    public function edit(array $params): array {
        $mode   = $params['mode'] ?? 'edit';
        $job_id = $params['job_id'] ?? ('imgedit_' . wp_generate_uuid4());
        $size   = $this->resolve_size($params);
        [$w, $h] = array_map('intval', explode('x', $size));
        $user_id = (int) ($params['user_id'] ?? get_current_user_id());
        $base   = sanitize_file_name($job_id . '_edit');
        $label  = mb_substr(trim($params['prompt'] ?? ''), 0, 60) ?: (ucfirst($mode) . ' Result');
        $asset  = YooY_Asset_Generator::import_mock_image($base, $w, $h, $label, $user_id);
        if (empty($asset['url'])) {
            return YooY_Job_Normalizer::normalize([
                'job_id'   => $job_id,
                'status'   => YooY_Job_Status::FAILED,
                'provider' => $this->id(),
                'error'    => 'Generation completed but no output asset was returned.',
            ], 'image');
        }

        $url   = $asset['url'];
        $thumb = $asset['thumbnail'] ?: $url;

        $result = [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'mock-image-v1',
            'mode'         => $mode,
            'prompt'       => $params['prompt'] ?? '',
            'source'       => $params['source_url'] ?? '',
            'output'       => ['url' => $url, 'thumbnail' => $thumb],
            'images'       => [[
                'url'           => $url,
                'thumbnail'     => $thumb,
                'attachment_id' => (int) ($asset['attachment_id'] ?? 0),
            ]],
            'credits_used' => ['edit' => 8, 'upscale' => 15, 'inpaint' => 12, 'outpaint' => 12][$mode] ?? 10,
            'meta'         => ['mock' => true],
        ];

        $this->jobs->complete_immediately($job_id, $result);
        return YooY_Job_Normalizer::normalize($result, 'image');
    }

    public function status(string $job_id): array {
        $polled = $this->jobs->poll($job_id);
        return YooY_Job_Normalizer::normalize(array_merge($polled, ['type' => 'image']), 'image');
    }

    private function build_generate_result(string $job_id, array $params): array {
        $prompt  = $params['prompt'] ?? '';
        $count   = min(4, max(1, (int) ($params['image_count'] ?? 1)));
        $size    = $this->resolve_size($params);
        [$w, $h] = array_map('intval', explode('x', $size));
        $user_id = (int) ($params['user_id'] ?? get_current_user_id());
        $images  = [];

        for ($i = 0; $i < $count; $i++) {
            $base  = sanitize_file_name($job_id . '_' . $i);
            $label = mb_substr(trim($prompt), 0, 60) ?: ('YooY Image ' . ($i + 1));
            $asset = YooY_Asset_Generator::import_mock_image($base, $w, $h, $label, $user_id);
            if (empty($asset['url'])) {
                continue;
            }
            $images[] = [
                'url'           => $asset['url'],
                'thumbnail'     => $asset['thumbnail'] ?: $asset['url'],
                'attachment_id' => (int) ($asset['attachment_id'] ?? 0),
                'width'         => $w,
                'height'        => $h,
                'seed'          => $params['seed'] ?? random_int(1, 999999),
            ];
        }

        return [
            'job_id'       => $job_id,
            'status'       => empty($images) ? YooY_Job_Status::FAILED : YooY_Job_Status::COMPLETED,
            'provider'     => $params['provider'] ?? $this->id(),
            'model'        => $params['model'] ?? 'mock-image-v1',
            'prompt'       => $prompt,
            'images'       => $images,
            'image_count'  => count($images),
            'error'        => empty($images) ? 'Generation completed but no output asset was returned.' : null,
            'credits_used' => $this->estimate_credits($params),
            'meta'         => ['mock' => true, 'korean_context' => !empty($params['korean_context'])],
        ];
    }

    private function resolve_size(array $params): string {
        $resolution = (int) ($params['resolution'] ?? 1024);
        $ratio      = $params['aspect_ratio'] ?? '1:1';
        $base       = $resolution;

        switch ($ratio) {
            case '16:9':
                return (int) round($base * 16 / 9) . 'x' . $base;
            case '9:16':
                return $base . 'x' . (int) round($base * 16 / 9);
            case '4:5':
                return $base . 'x' . (int) round($base * 5 / 4);
            case '3:2':
                return (int) round($base * 3 / 2) . 'x' . $base;
            case '2:3':
                return $base . 'x' . (int) round($base * 3 / 2);
            default:
                return $base . 'x' . $base;
        }
    }

    private function estimate_credits(array $params): int {
        $count   = (int) ($params['image_count'] ?? 1);
        $quality = $params['quality'] ?? 'standard';
        $base    = ['draft' => 5, 'standard' => 10, 'hd' => 20][$quality] ?? 10;
        return $base * $count;
    }
}
