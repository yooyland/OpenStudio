<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-image-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-image/class-mock-image-provider.php';

final class YooY_OpenAI_Image_Provider implements YooY_Image_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = (string) get_option('yoy_openai_api_key', '');
    }

    public function id(): string { return 'openai'; }
    public function name(): string { return 'GPT Image'; }

    public function models(): array {
        return [
            ['id' => 'dall-e-3', 'name' => 'DALL·E 3', 'max_count' => 1, 'resolutions' => ['1024', '1792']],
            ['id' => 'gpt-image-1', 'name' => 'GPT Image 1', 'max_count' => 4, 'resolutions' => ['1024', '2048']],
        ];
    }

    public function generate(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'dall-e-3',
            ]));
        }

        $job_id = $params['job_id'] ?? ('img_' . wp_generate_uuid4());
        $model  = $params['model'] ?? 'dall-e-3';
        $size   = $this->map_size($params);

        $body = [
            'model'  => $model,
            'prompt' => $params['prompt'] ?? '',
            'n'      => min(4, max(1, (int) ($params['image_count'] ?? 1))),
            'size'   => $size,
        ];

        if (!empty($params['negative_prompt'])) {
            $body['prompt'] .= '. Avoid: ' . $params['negative_prompt'];
        }

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $images = [];

        foreach (($data['data'] ?? []) as $item) {
            $images[] = [
                'url'       => $item['url'] ?? '',
                'thumbnail' => $item['url'] ?? '',
                'revised_prompt' => $item['revised_prompt'] ?? null,
            ];
        }

        return YooY_Job_Normalizer::normalize([
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'provider'     => $this->id(),
            'model'        => $model,
            'prompt'       => $params['prompt'] ?? '',
            'images'       => $images,
            'image_count'  => count($images),
            'credits_used' => 10 * count($images),
            'raw'          => $data,
        ], 'image');
    }

    public function edit(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->edit(array_merge($params, ['provider' => $this->id()]));
        }

        $job_id = $params['job_id'] ?? ('imgedit_' . wp_generate_uuid4());
        $endpoint = match ($params['mode'] ?? 'edit') {
            'inpaint', 'outpaint', 'edit' => 'edits',
            default => 'edits',
        };

        $response = wp_remote_post('https://api.openai.com/v1/images/' . $endpoint, [
            'timeout' => 120,
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
            'body'    => [
                'image'  => $params['source_url'] ?? '',
                'prompt' => $params['prompt'] ?? '',
                'n'      => 1,
                'size'   => $this->map_size($params),
            ],
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        return YooY_Job_Normalizer::normalize([
            'job_id'   => $job_id,
            'status'   => YooY_Job_Status::COMPLETED,
            'provider' => $this->id(),
            'mode'     => $params['mode'] ?? 'edit',
            'prompt'   => $params['prompt'] ?? '',
            'output'   => ['url' => $data['data'][0]['url'] ?? ''],
            'images'   => [['url' => $data['data'][0]['url'] ?? '', 'thumbnail' => $data['data'][0]['url'] ?? '']],
            'credits_used' => 12,
            'raw'      => $data,
        ], 'image');
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->status($job_id);
        }
        return ['job_id' => $job_id, 'status' => 'completed', 'progress' => 100];
    }

    private function map_size(array $params): string {
        $ratio = $params['aspect_ratio'] ?? '1:1';
        $res   = $params['resolution'] ?? '1024';

        if ($ratio === '16:9' || $ratio === '9:16') {
            return $res === '1792' ? '1792x1024' : '1024x1792';
        }
        return $res . 'x' . $res;
    }
}
