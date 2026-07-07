<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-image-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-image/class-mock-image-provider.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-provider-guard.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-openai-b64-asset.php';

final class YooY_OpenAI_Image_Provider implements YooY_Image_Provider_Interface {

    private string $api_key;

    public function __construct() {
        $this->api_key = YooY_Secrets::get_api_key('yoy_openai_api_key');
    }

    public function id(): string { return 'openai'; }
    public function name(): string { return 'GPT Image'; }

    public function models(): array {
        return [
            ['id' => 'dall-e-3', 'name' => 'DALL·E 3', 'max_count' => 1, 'resolutions' => ['1024x1024', '1024x1792', '1792x1024']],
            ['id' => 'gpt-image-1', 'name' => 'GPT Image 1', 'max_count' => 4, 'resolutions' => ['auto', '1024x1024', '1024x1536', '1536x1024']],
        ];
    }

    public function generate(array $params): array {
        YooY_Provider_Guard::require_key($this->name(), $this->api_key, $params);
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->generate(array_merge($params, [
                'provider' => $this->id(),
                'model'    => $params['model'] ?? 'gpt-image-1',
            ]));
        }

        $job_id = $params['job_id'] ?? ('img_' . wp_generate_uuid4());
        $model  = $params['model'] ?? 'gpt-image-1';
        $size   = $this->resolve_size($params, $model);
        $body   = $this->build_generation_body($params, $model, $size);

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
        if (!is_array($data)) {
            $data = [];
        }

        $user_id = (int) ($params['user_id'] ?? get_current_user_id());
        $format  = sanitize_text_field($params['output_format'] ?? 'png');
        $images  = $this->parse_response_images($data, $user_id, $job_id, $format);
        $output  = $this->build_output_from_images($images, $format);

        $job = [
            'job_id'       => $job_id,
            'status'       => !empty($images) ? YooY_Job_Status::COMPLETED : YooY_Job_Status::FAILED,
            'provider'     => $this->id(),
            'model'        => $model,
            'prompt'       => $params['prompt'] ?? '',
            'images'       => $images,
            'output'       => $output,
            'image_count'  => count($images),
            'size'         => $size,
            'error'        => null,
            'credits_used' => empty($images) ? 0 : (10 * count($images)),
            'raw'          => $data,
            'meta'         => [],
        ];

        if (current_user_can('manage_options')) {
            $job['meta']['openai_debug'] = [
                'request'  => $body,
                'response' => $this->sanitize_debug_response($data),
            ];
        }

        $job = YooY_OpenAI_B64_Asset::finalize_job($job, $user_id, $job_id, $format);

        if (($job['status'] ?? '') === YooY_Job_Status::FAILED && empty($job['error'])) {
            $job['error'] = 'OpenAI returned no displayable image asset.';
        }
        if (($job['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $job['credits_used'] = 10 * max(1, count($job['images'] ?? []));
        }

        return YooY_Job_Normalizer::normalize($job, 'image');
    }

    private function build_generation_body(array $params, string $model, string $size): array {
        $body = [
            'model'  => $model,
            'prompt' => $params['prompt'] ?? '',
            'n'      => min(4, max(1, (int) ($params['image_count'] ?? 1))),
            'size'   => $size,
        ];

        if ($model === 'gpt-image-1') {
            $body['quality']        = $this->map_openai_quality((string) ($params['quality'] ?? 'standard'), $model);
            $body['background']     = $this->map_openai_background((string) ($params['background'] ?? 'studio_white'));
            $body['output_format']  = sanitize_text_field($params['output_format'] ?? 'png');
        } elseif ($model === 'dall-e-3') {
            $body['quality'] = $this->map_openai_quality((string) ($params['quality'] ?? 'standard'), $model);
        }

        if (!empty($params['negative_prompt'])) {
            $body['prompt'] .= '. Avoid: ' . $params['negative_prompt'];
        }

        return $body;
    }

    private function map_openai_quality(string $quality, string $model): string {
        if ($model === 'dall-e-3') {
            return $quality === 'hd' ? 'hd' : 'standard';
        }
        switch ($quality) {
            case 'hd':
                return 'high';
            case 'draft':
                return 'low';
            case 'standard':
            default:
                return 'medium';
        }
    }

    private function map_openai_background(string $background): string {
        return $background === 'transparent' ? 'transparent' : 'opaque';
    }

    private function sanitize_debug_response($data): array {
        if (!is_array($data)) {
            return [];
        }
        if (!empty($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $idx => $item) {
                if (!is_array($item) || empty($item['b64_json'])) {
                    continue;
                }
                $len = strlen((string) $item['b64_json']);
                $data['data'][$idx]['b64_json'] = '[base64 omitted, ' . $len . ' chars]';
            }
        }
        return $data;
    }

    public function edit(array $params): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->edit(array_merge($params, ['provider' => $this->id()]));
        }

        $job_id = $params['job_id'] ?? ('imgedit_' . wp_generate_uuid4());
        $mode = $params['mode'] ?? 'edit';
        if (in_array($mode, ['inpaint', 'outpaint', 'edit'], true)) {
            $endpoint = 'edits';
        } else {
            $endpoint = 'edits';
        }

        $response = wp_remote_post('https://api.openai.com/v1/images/' . $endpoint, [
            'timeout' => 120,
            'headers' => ['Authorization' => 'Bearer ' . $this->api_key],
            'body'    => [
                'image'  => $params['source_url'] ?? '',
                'prompt' => $params['prompt'] ?? '',
                'n'      => 1,
                'size'   => $this->resolve_size($params, $params['model'] ?? 'gpt-image-1'),
            ],
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            $data = [];
        }

        $user_id = (int) ($params['user_id'] ?? get_current_user_id());
        $format  = sanitize_text_field($params['output_format'] ?? 'png');
        $images  = $this->parse_response_images($data, $user_id, $job_id, $format);
        $output  = $this->build_output_from_images($images, $format);

        $job = [
            'job_id'   => $job_id,
            'status'   => !empty($images) ? YooY_Job_Status::COMPLETED : YooY_Job_Status::FAILED,
            'provider' => $this->id(),
            'mode'     => $params['mode'] ?? 'edit',
            'prompt'   => $params['prompt'] ?? '',
            'output'   => $output,
            'images'   => $images,
            'error'    => null,
            'credits_used' => empty($images) ? 0 : 12,
            'raw'      => $data,
        ];
        $job = YooY_OpenAI_B64_Asset::finalize_job($job, $user_id, $job_id, $format);

        return YooY_Job_Normalizer::normalize($job, 'image');
    }

    public function status(string $job_id): array {
        if ($this->api_key === '') {
            return (new YooY_Mock_Image_Provider())->status($job_id);
        }
        return ['job_id' => $job_id, 'status' => 'completed', 'progress' => 100];
    }

    private function resolve_size(array $params, string $model): string {
        $requested = !empty($params['size']) ? (string) $params['size'] : '';
        if (class_exists('YooY_Image_Size_Resolver')) {
            $mapped = YooY_Image_Size_Resolver::resolve(
                $this->id(),
                (string) ($params['catalog_provider'] ?? $this->id()),
                $model,
                (string) ($params['aspect_ratio'] ?? '1:1'),
                (string) ($params['resolution'] ?? '1024'),
                $requested
            );
            return (string) $mapped['size'];
        }
        if ($requested !== '') {
            return $requested;
        }
        return $this->legacy_map_size($params, $model);
    }

    private function legacy_map_size(array $params, string $model): string {
        $ratio = $params['aspect_ratio'] ?? '1:1';
        if ($model === 'gpt-image-1') {
            switch ($ratio) {
                case '1:1':
                    return '1024x1024';
                case '16:9':
                case '3:2':
                    return '1536x1024';
                case '9:16':
                case '4:5':
                case '2:3':
                    return '1024x1536';
                default:
                    return 'auto';
            }
        }
        $res = $params['resolution'] ?? '1024';
        if ($ratio === '16:9') {
            return '1792x1024';
        }
        if ($ratio === '9:16') {
            return '1024x1792';
        }
        return $res . 'x' . $res;
    }

    private function parse_response_images(array $data, int $user_id, string $job_id, string $format): array {
        $mime = $this->mime_for_format($format);
        $ext  = $this->ext_for_format($format);
        $items = $this->collect_response_items($data);
        $images = [];

        foreach ($items as $index => $item) {
            $asset = $this->materialize_image_item($item, $user_id, $job_id, (int) $index, $ext, $mime);
            if (!empty($asset['url']) || !empty($asset['attachment_id'])) {
                $images[] = $asset;
            }
        }

        return $images;
    }

    private function collect_response_items(array $data): array {
        $items = [];

        foreach (($data['data'] ?? []) as $row) {
            if (is_array($row)) {
                $items[] = $row;
            }
        }

        if (!empty($data['images']) && is_array($data['images'])) {
            foreach ($data['images'] as $img) {
                if (is_string($img) && $img !== '') {
                    $items[] = ['url' => $img];
                } elseif (is_array($img)) {
                    $items[] = $img;
                }
            }
        }

        if (!empty($data['output'])) {
            $output = $data['output'];
            if (is_string($output) && $output !== '') {
                $items[] = ['url' => $output];
            } elseif (is_array($output)) {
                if (!empty($output['url'])) {
                    $items[] = ['url' => $output['url']];
                }
                if (!empty($output['b64_json'])) {
                    $items[] = ['b64_json' => $output['b64_json']];
                }
                foreach (($output['urls'] ?? []) as $url) {
                    if (is_string($url) && $url !== '') {
                        $items[] = ['url' => $url];
                    }
                }
                foreach ($output as $maybe) {
                    if (is_string($maybe) && strpos($maybe, 'http') === 0) {
                        $items[] = ['url' => $maybe];
                    }
                }
            }
        }

        return $items;
    }

    private function materialize_image_item(array $item, int $user_id, string $job_id, int $index, string $ext, string $mime): array {
        $basename = sanitize_file_name('openai-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $job_id) . '-' . $index);
        if ($basename === '') {
            $basename = 'openai-' . wp_generate_uuid4();
        }
        $filename = $basename . '.' . $ext;
        $revised  = $item['revised_prompt'] ?? null;

        if (!empty($item['b64_json'])) {
            $saved = YooY_OpenAI_B64_Asset::save_b64_to_media(
                (string) $item['b64_json'],
                $user_id,
                $job_id,
                $index,
                $ext === 'jpg' ? 'jpeg' : ($ext === 'webp' ? 'webp' : 'png')
            );
            if (!empty($saved['error'])) {
                return [];
            }
            if (!empty($saved['url'])) {
                return array_merge([
                    'url'           => $saved['url'],
                    'thumbnail'     => $saved['thumbnail'] ?? $saved['url'],
                    'attachment_id' => (int) ($saved['attachment_id'] ?? 0),
                ], ['revised_prompt' => $revised]);
            }
            return [];
        }

        if (!empty($item['url'])) {
            $remote = YooY_Asset_Generator::sanitize_asset_url((string) $item['url']);
            if ($remote !== '' && YooY_Asset_Generator::is_http_asset_url($remote)) {
                $stored = YooY_Asset_Generator::import_from_url($remote, $filename, $user_id, $mime);
                if (!empty($stored['url'])) {
                    return array_merge($stored, ['revised_prompt' => $revised]);
                }
                return [
                    'url'            => $remote,
                    'thumbnail'      => $remote,
                    'attachment_id'  => 0,
                    'revised_prompt' => $revised,
                ];
            }
        }

        return [];
    }

    private function build_output_from_images(array $images, string $format): array {
        if (empty($images)) {
            return ['urls' => [], 'primary' => '', 'mime' => $this->mime_for_format($format), 'thumbnail' => ''];
        }

        $urls = [];
        foreach ($images as $img) {
            $url = $img['url'] ?? '';
            if ($url === '' && !empty($img['attachment_id'])) {
                $resolved = YooY_Asset_Generator::resolve_attachment((int) $img['attachment_id']);
                $url = $resolved['url'] ?? '';
            }
            if ($url !== '') {
                $urls[] = $url;
            }
        }

        $primary = $urls[0] ?? '';
        $thumb   = $images[0]['thumbnail'] ?? $primary;
        if ($thumb === '' && !empty($images[0]['attachment_id'])) {
            $resolved = YooY_Asset_Generator::resolve_attachment((int) $images[0]['attachment_id']);
            $thumb = $resolved['thumbnail'] ?? $resolved['url'] ?? $primary;
        }

        return [
            'primary'   => $primary,
            'urls'      => $urls,
            'thumbnail' => $thumb ?: $primary,
            'mime'      => $this->mime_for_format($format),
        ];
    }

    private function mime_for_format(string $format): string {
        switch (strtolower($format)) {
            case 'jpeg':
            case 'jpg':
                return 'image/jpeg';
            case 'webp':
                return 'image/webp';
            default:
                return 'image/png';
        }
    }

    private function ext_for_format(string $format): string {
        switch (strtolower($format)) {
            case 'jpeg':
            case 'jpg':
                return 'jpg';
            case 'webp':
                return 'webp';
            default:
                return 'png';
        }
    }
}
