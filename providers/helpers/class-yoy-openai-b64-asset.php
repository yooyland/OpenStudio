<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('YooY_Asset_Generator') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
    require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
}

/**
 * Converts OpenAI gpt-image-1 b64_json responses into WordPress media assets.
 */
final class YooY_OpenAI_B64_Asset {

    /**
     * Ensure a job has displayable assets; materialize from raw.data[].b64_json when needed.
     */
    public static function finalize_job(array $job, int $user_id, string $job_id = '', string $format = 'png'): array {
        if (class_exists('YooY_Asset_Generator') && YooY_Asset_Generator::has_displayable_asset($job)) {
            $job['raw'] = self::strip_b64_from_raw($job['raw'] ?? null, self::allow_debug_raw());
            return $job;
        }

        $raw     = is_array($job['raw'] ?? null) ? $job['raw'] : [];
        $items   = self::extract_b64_items($raw);
        $has_b64 = !empty($items);

        if (!$has_b64) {
            return $job;
        }

        $job_id = $job_id !== '' ? $job_id : (string) ($job['job_id'] ?? ('img_' . wp_generate_uuid4()));
        $images = [];
        $debug  = [
            'has_b64_json'    => true,
            'decoded_bytes'   => 0,
            'upload_file'     => '',
            'attachment_id'   => 0,
            'image_url'       => '',
            'thumbnail_url'   => '',
            'gallery_item_id' => '',
        ];

        foreach ($items as $index => $item) {
            $saved = self::save_b64_to_media((string) $item['b64_json'], $user_id, $job_id, (int) $index, $format);
            $debug = array_merge($debug, $saved['debug'] ?? []);

            if (!empty($saved['error'])) {
                $job['status'] = YooY_Job_Status::FAILED;
                $job['error']  = $saved['error'];
                $job['progress'] = 0;
                $job['raw'] = self::strip_b64_from_raw($raw, self::allow_debug_raw());
                $job['meta'] = self::merge_debug_meta($job, $debug);
                return $job;
            }

            if (!empty($saved['url']) || !empty($saved['attachment_id'])) {
                $images[] = [
                    'url'            => $saved['url'] ?? '',
                    'thumbnail'      => $saved['thumbnail'] ?? ($saved['url'] ?? ''),
                    'attachment_id'  => (int) ($saved['attachment_id'] ?? 0),
                    'revised_prompt' => $item['revised_prompt'] ?? null,
                ];
            }
        }

        if (empty($images)) {
            $job['status'] = YooY_Job_Status::FAILED;
            $job['error']  = 'Failed to save OpenAI image to WordPress uploads.';
            $job['progress'] = 0;
            $job['raw'] = self::strip_b64_from_raw($raw, self::allow_debug_raw());
            $job['meta'] = self::merge_debug_meta($job, $debug);
            return $job;
        }

        $output = self::build_output($images, $format);
        $job['images']      = $images;
        $job['output']      = $output;
        $job['image_count'] = count($images);
        $job['status']      = YooY_Job_Status::COMPLETED;
        $job['error']       = null;
        $job['progress']    = 100;
        $job['raw']         = self::strip_b64_from_raw($raw, self::allow_debug_raw());
        $job['meta']        = self::merge_debug_meta($job, $debug);

        return $job;
    }

    /**
     * @return array{attachment_id:int,url:string,thumbnail:string,error:string,debug:array}
     */
    public static function save_b64_to_media(string $b64, int $user_id, string $job_id, int $index, string $format = 'png'): array {
        $debug = [
            'has_b64_json'  => $b64 !== '',
            'decoded_bytes' => 0,
            'upload_file'   => '',
            'attachment_id' => 0,
            'image_url'     => '',
            'thumbnail_url' => '',
        ];

        $binary = self::decode_base64($b64);
        if ($binary === '') {
            return [
                'attachment_id' => 0,
                'url'           => '',
                'thumbnail'     => '',
                'error'         => 'OpenAI image base64 decode failed.',
                'debug'         => $debug,
            ];
        }

        $debug['decoded_bytes'] = strlen($binary);
        $ext  = self::ext_for_format($format);
        $mime = self::mime_for_format($format);
        $slug = sanitize_file_name('openai-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $job_id) . '-' . $index);
        if ($slug === '') {
            $slug = 'openai-' . wp_generate_uuid4();
        }
        $filename = $slug . '.' . $ext;

        if (!function_exists('wp_upload_bits')) {
            return [
                'attachment_id' => 0,
                'url'           => '',
                'thumbnail'     => '',
                'error'         => 'Failed to save OpenAI image to WordPress uploads.',
                'debug'         => $debug,
            ];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_upload_bits($filename, null, $binary);
        if (!empty($upload['error']) || empty($upload['file'])) {
            return [
                'attachment_id' => 0,
                'url'           => '',
                'thumbnail'     => '',
                'error'         => 'Failed to save OpenAI image to WordPress uploads.',
                'debug'         => array_merge($debug, ['upload_error' => (string) ($upload['error'] ?? 'unknown')]),
            ];
        }

        $debug['upload_file'] = (string) $upload['file'];
        $checked = wp_check_filetype($upload['file']);
        if (!empty($checked['type'])) {
            $mime = $checked['type'];
        }

        $author = $user_id > 0 ? $user_id : get_current_user_id();
        $attachment = [
            'post_mime_type' => $mime,
            'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => $author > 0 ? $author : 0,
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        if (is_wp_error($attach_id) || !$attach_id) {
            return [
                'attachment_id' => 0,
                'url'           => '',
                'thumbnail'     => '',
                'error'         => 'Failed to save OpenAI image to WordPress uploads.',
                'debug'         => $debug,
            ];
        }

        $metadata = wp_generate_attachment_metadata((int) $attach_id, $upload['file']);
        if (is_array($metadata) && $metadata !== []) {
            wp_update_attachment_metadata((int) $attach_id, $metadata);
        }

        $resolved = class_exists('YooY_Asset_Generator')
            ? YooY_Asset_Generator::resolve_attachment((int) $attach_id)
            : ['attachment_id' => (int) $attach_id, 'url' => '', 'thumbnail' => ''];

        if (($resolved['url'] ?? '') === '') {
            $url = wp_get_attachment_url((int) $attach_id);
            $resolved['url'] = is_string($url) ? $url : '';
            $thumb = wp_get_attachment_image_url((int) $attach_id, 'medium');
            $resolved['thumbnail'] = is_string($thumb) ? $thumb : $resolved['url'];
        }

        if (($resolved['url'] ?? '') === '') {
            return [
                'attachment_id' => 0,
                'url'           => '',
                'thumbnail'     => '',
                'error'         => 'Failed to save OpenAI image to WordPress uploads.',
                'debug'         => $debug,
            ];
        }

        $debug['attachment_id'] = (int) $attach_id;
        $debug['image_url']     = (string) $resolved['url'];
        $debug['thumbnail_url'] = (string) ($resolved['thumbnail'] ?? $resolved['url']);

        return [
            'attachment_id' => (int) $attach_id,
            'url'           => (string) $resolved['url'],
            'thumbnail'     => (string) ($resolved['thumbnail'] ?? $resolved['url']),
            'error'         => '',
            'debug'         => $debug,
        ];
    }

    public static function extract_b64_items(array $raw): array {
        $items = [];
        foreach (($raw['data'] ?? []) as $row) {
            if (is_array($row) && !empty($row['b64_json'])) {
                $items[] = $row;
            }
        }
        if (!empty($raw['images']) && is_array($raw['images'])) {
            foreach ($raw['images'] as $img) {
                if (is_array($img) && !empty($img['b64_json'])) {
                    $items[] = $img;
                }
            }
        }
        if (!empty($raw['output']) && is_array($raw['output']) && !empty($raw['output']['b64_json'])) {
            $items[] = ['b64_json' => $raw['output']['b64_json']];
        }
        return $items;
    }

    public static function build_output(array $images, string $format): array {
        $urls = [];
        foreach ($images as $img) {
            $url = $img['url'] ?? '';
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        $primary = $urls[0] ?? '';
        $thumb   = $images[0]['thumbnail'] ?? $primary;
        return [
            'primary'   => $primary,
            'urls'      => $urls,
            'thumbnail' => $thumb ?: $primary,
            'mime'      => self::mime_for_format($format),
        ];
    }

    public static function strip_b64_from_raw($raw, bool $keep_for_debug = false) {
        if (!is_array($raw)) {
            return $raw;
        }
        if ($keep_for_debug) {
            return $raw;
        }
        if (!empty($raw['data']) && is_array($raw['data'])) {
            foreach ($raw['data'] as $idx => $item) {
                if (is_array($item) && isset($item['b64_json'])) {
                    $len = strlen((string) $item['b64_json']);
                    unset($raw['data'][$idx]['b64_json']);
                    $raw['data'][$idx]['b64_json_omitted'] = $len;
                }
            }
        }
        if (!empty($raw['output']['b64_json'])) {
            unset($raw['output']['b64_json']);
        }
        return $raw;
    }

    public static function sanitize_job_for_response(array $job): array {
        $debug = self::allow_debug_raw();
        if (isset($job['raw'])) {
            $job['raw'] = self::strip_b64_from_raw($job['raw'], $debug);
        }
        if (!$debug && isset($job['meta']['openai_debug']['response'])) {
            $job['meta']['openai_debug']['response'] = self::strip_b64_from_raw(
                $job['meta']['openai_debug']['response'],
                false
            );
        }
        return $job;
    }

    private static function decode_base64(string $b64): string {
        $b64 = trim($b64);
        if ($b64 === '') {
            return '';
        }
        if (strpos($b64, 'base64,') !== false) {
            $b64 = substr($b64, strrpos($b64, 'base64,') + 7);
        }
        $b64 = preg_replace('/\s+/', '', $b64);
        $binary = base64_decode($b64, true);
        if ($binary !== false && $binary !== '') {
            return $binary;
        }
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $binary = base64_decode($b64, true);
        return ($binary !== false && $binary !== '') ? $binary : '';
    }

    private static function allow_debug_raw(): bool {
        return current_user_can('manage_options')
            && (defined('WP_DEBUG') && WP_DEBUG);
    }

    private static function merge_debug_meta(array $job, array $debug): array {
        $meta = is_array($job['meta'] ?? null) ? $job['meta'] : [];
        if (current_user_can('manage_options')) {
            $meta['openai_asset_debug'] = $debug;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[YooY OpenAI Asset] ' . wp_json_encode($debug));
            }
        }
        return $meta;
    }

    private static function mime_for_format(string $format): string {
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

    private static function ext_for_format(string $format): string {
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
