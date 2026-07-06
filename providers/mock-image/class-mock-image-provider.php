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
        $url = YooY_Asset_Generator::svg_data_uri($w, $h, ucfirst($mode) . ' Result', '#12121a', '#ffd76a');

        $result = [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $this->id(),
            'model'        => $params['model'] ?? 'mock-image-v1',
            'mode'         => $mode,
            'prompt'       => $params['prompt'] ?? '',
            'source'       => $params['source_url'] ?? '',
            'output'       => ['url' => $url, 'thumbnail' => YooY_Asset_Generator::svg_data_uri((int) ($w / 4), (int) ($h / 4), $mode, '#1a1a1a', '#d8a63a')],
            'images'       => [['url' => $url, 'thumbnail' => $url]],
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
        $prompt = $params['prompt'] ?? '';
        $count  = min(4, max(1, (int) ($params['image_count'] ?? 1)));
        $size   = $this->resolve_size($params);
        [$w, $h] = array_map('intval', explode('x', $size));
        $images = [];

        for ($i = 0; $i < $count; $i++) {
            $label = 'YooY Image ' . ($i + 1);
            $url   = YooY_Asset_Generator::svg_data_uri($w, $h, $label);
            $thumb = YooY_Asset_Generator::svg_data_uri((int) max(64, $w / 4), (int) max(64, $h / 4), 'Thumb ' . ($i + 1), '#1a1a1a', '#ffd76a');
            $images[] = [
                'url'       => $url,
                'thumbnail' => $thumb,
                'width'     => $w,
                'height'    => $h,
                'seed'      => $params['seed'] ?? random_int(1, 999999),
            ];
        }

        return [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $params['provider'] ?? $this->id(),
            'model'        => $params['model'] ?? 'mock-image-v1',
            'prompt'       => $prompt,
            'images'       => $images,
            'image_count'  => $count,
            'credits_used' => $this->estimate_credits($params),
            'meta'         => ['mock' => true, 'korean_context' => !empty($params['korean_context'])],
        ];
    }

    private function resolve_size(array $params): string {
        $resolution = (int) ($params['resolution'] ?? 1024);
        $ratio      = $params['aspect_ratio'] ?? '1:1';
        $base       = $resolution;

        return match ($ratio) {
            '16:9' => (int) round($base * 16 / 9) . 'x' . $base,
            '9:16' => $base . 'x' . (int) round($base * 16 / 9),
            '4:5'  => $base . 'x' . (int) round($base * 5 / 4),
            '3:2'  => (int) round($base * 3 / 2) . 'x' . $base,
            '2:3'  => $base . 'x' . (int) round($base * 3 / 2),
            default => $base . 'x' . $base,
        };
    }

    private function estimate_credits(array $params): int {
        $count   = (int) ($params['image_count'] ?? 1);
        $quality = $params['quality'] ?? 'standard';
        $base    = ['draft' => 5, 'standard' => 10, 'hd' => 20][$quality] ?? 10;
        return $base * $count;
    }
}
