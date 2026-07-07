<?php
if (!defined('ABSPATH')) exit;

final class YooY_Asset_Generator {

    public static function svg_data_uri(int $width, int $height, string $label, string $bg = '#0d0d0d', string $fg = '#d8a63a'): string {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg_markup($width, $height, $label, $bg, $fg));
    }

    /**
     * Create a mock image as a WordPress media attachment (PNG) and return URLs.
     */
    public static function import_mock_image(string $basename, int $width, int $height, string $label, int $user_id = 0): array {
        $basename = sanitize_file_name($basename);
        if ($basename === '') {
            $basename = 'yooy-mock-' . wp_generate_uuid4();
        }

        $png = self::render_png_binary($width, $height, $label);
        if ($png !== '') {
            $stored = self::store_media_binary($png, $basename . '.png', 'image/png', $user_id);
            if (!empty($stored['url'])) {
                return $stored;
            }
        }

        $data_uri = self::svg_data_uri($width, $height, $label);
        return self::import_from_data_uri($data_uri, $basename . '.svg', $user_id);
    }

    /**
     * Import a data URI (base64 image) into the WordPress media library.
     */
    public static function import_from_data_uri(string $data_uri, string $filename, int $user_id = 0): array {
        $data_uri = trim($data_uri);
        if ($data_uri === '' || strpos($data_uri, 'data:') !== 0) {
            return [];
        }

        if (!preg_match('#^data:([^;]+);base64,(.+)$#', $data_uri, $matches)) {
            return [];
        }

        $mime = strtolower(trim($matches[1]));
        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            return [];
        }

        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml'], true)) {
            return [];
        }

        $ext = 'png';
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            $ext = 'jpg';
        } elseif ($mime === 'image/gif') {
            $ext = 'gif';
        } elseif ($mime === 'image/webp') {
            $ext = 'webp';
        } elseif ($mime === 'image/svg+xml') {
            $ext = 'svg';
        }

        $filename = sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)) . '.' . $ext;
        return self::store_media_binary($binary, $filename, $mime, $user_id);
    }

    /**
     * Persist mock SVG to uploads and return a public HTTP URL (legacy fallback).
     */
    public static function persist_svg_image(int $width, int $height, string $label, string $basename, string $bg = '#0d0d0d', string $fg = '#d8a63a'): string {
        if (!function_exists('wp_upload_dir')) {
            return self::svg_data_uri($width, $height, $label, $bg, $fg);
        }

        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return self::svg_data_uri($width, $height, $label, $bg, $fg);
        }

        $dir      = trailingslashit($upload['basedir']) . 'yooy-ai-studio/mock/';
        $url_base = trailingslashit($upload['baseurl']) . 'yooy-ai-studio/mock/';

        if (!wp_mkdir_p($dir)) {
            return self::svg_data_uri($width, $height, $label, $bg, $fg);
        }

        $filename = sanitize_file_name($basename) . '.svg';
        $path     = $dir . $filename;
        $svg      = self::svg_markup($width, $height, $label, $bg, $fg);

        if (@file_put_contents($path, $svg) === false) {
            return self::svg_data_uri($width, $height, $label, $bg, $fg);
        }

        return self::normalize_url_scheme($url_base . $filename);
    }

    public static function resolve_attachment(int $attachment_id): array {
        if ($attachment_id <= 0 || !function_exists('wp_get_attachment_url')) {
            return ['attachment_id' => 0, 'url' => '', 'thumbnail' => ''];
        }

        $url = wp_get_attachment_url($attachment_id);
        $url = is_string($url) ? self::normalize_url_scheme($url) : '';

        $thumb = '';
        if ($url !== '') {
            $thumb = wp_get_attachment_image_url($attachment_id, 'medium');
            if (!$thumb) {
                $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            }
            $thumb = is_string($thumb) ? self::normalize_url_scheme($thumb) : $url;
        }

        return [
            'attachment_id' => $attachment_id,
            'url'           => $url,
            'thumbnail'     => $thumb ?: $url,
        ];
    }

    public static function audio_data_uri(string $label = 'YooY Audio'): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">'
            . '<rect width="512" height="512" fill="#1a1a0a"/>'
            . '<circle cx="256" cy="256" r="120" fill="none" stroke="#d8a63a" stroke-width="8"/>'
            . '<text x="256" y="270" fill="#ffd76a" font-family="system-ui,sans-serif" font-size="20" text-anchor="middle">' . esc_html(mb_substr($label, 0, 16)) . '</text>'
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function silent_audio_data_uri(): string {
        return 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
    }

    public static function waveform_data_uri(string $label = 'Waveform'): string {
        return self::svg_data_uri(800, 120, $label, '#0d0d0d', '#ffd76a');
    }

    public static function normalize_url_scheme(string $url): string {
        $url = trim($url);
        if ($url === '' || !function_exists('set_url_scheme')) {
            return $url;
        }
        return set_url_scheme($url, is_ssl() ? 'https' : 'http');
    }

    public static function sanitize_asset_url($url): string {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return '';
        }
        if (strpos($url, 'data:image/') === 0) {
            return $url;
        }
        $clean = esc_url_raw($url);
        return is_string($clean) ? self::normalize_url_scheme($clean) : '';
    }

    public static function has_displayable_asset(array $payload): bool {
        if (!empty($payload['images']) && is_array($payload['images'])) {
            foreach ($payload['images'] as $img) {
                if (!empty($img['attachment_id']) && self::resolve_attachment((int) $img['attachment_id'])['url'] !== '') {
                    return true;
                }
                if (self::is_http_asset_url($img['url'] ?? '')) {
                    return true;
                }
            }
        }
        if (!empty($payload['attachment_id']) && self::resolve_attachment((int) $payload['attachment_id'])['url'] !== '') {
            return true;
        }
        $output = $payload['output'] ?? [];
        if (is_array($output) && self::is_http_asset_url($output['primary'] ?? '')) {
            return true;
        }
        if (is_array($output) && self::is_http_asset_url($output['url'] ?? '')) {
            return true;
        }
        if (is_array($output) && !empty($output['attachment_id']) && self::resolve_attachment((int) $output['attachment_id'])['url'] !== '') {
            return true;
        }
        return false;
    }

    public static function is_http_asset_url($url): bool {
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return false;
        }
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            return false;
        }
        $clean = esc_url_raw($url);
        return is_string($clean) && $clean !== '';
    }

    public static function import_from_binary(string $binary, string $filename, string $mime, int $user_id = 0): array {
        if ($binary === '') {
            return [];
        }
        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            $filename = 'yooy-import-' . wp_generate_uuid4();
        }
        return self::store_media_binary($binary, $filename, $mime, $user_id);
    }

    /**
     * Import raw base64 image data (OpenAI b64_json) into the media library.
     */
    public static function import_from_base64(string $b64, string $filename, string $mime, int $user_id = 0): array {
        if (!class_exists('YooY_OpenAI_B64_Asset') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
            require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-openai-b64-asset.php';
        }
        if (class_exists('YooY_OpenAI_B64_Asset')) {
            $format = 'png';
            if ($mime === 'image/jpeg') {
                $format = 'jpeg';
            } elseif ($mime === 'image/webp') {
                $format = 'webp';
            }
            $saved = YooY_OpenAI_B64_Asset::save_b64_to_media($b64, $user_id, 'import', 0, $format);
            if (!empty($saved['url'])) {
                return [
                    'attachment_id' => (int) ($saved['attachment_id'] ?? 0),
                    'url'           => (string) $saved['url'],
                    'thumbnail'     => (string) ($saved['thumbnail'] ?? $saved['url']),
                ];
            }
            return [];
        }

        $b64 = trim($b64);
        if ($b64 === '') {
            return [];
        }
        if (strpos($b64, 'base64,') !== false) {
            $b64 = substr($b64, strrpos($b64, 'base64,') + 7);
        }
        $b64 = preg_replace('/\s+/', '', $b64);
        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            $pad = strlen($b64) % 4;
            if ($pad > 0) {
                $b64 .= str_repeat('=', 4 - $pad);
            }
            $binary = base64_decode($b64, true);
        }
        if ($binary === false || $binary === '') {
            return [];
        }

        $filename = sanitize_file_name($filename);
        if ($filename === '') {
            $filename = 'yooy-import-' . wp_generate_uuid4() . '.png';
        }
        return self::import_from_binary($binary, $filename, $mime, $user_id);
    }

    /**
     * Download a remote image URL and import into the WordPress media library.
     */
    public static function import_from_url(string $url, string $filename, int $user_id = 0, string $mime = ''): array {
        $url = self::sanitize_asset_url($url);
        if ($url === '' || !self::is_http_asset_url($url)) {
            return [];
        }

        $response = wp_remote_get($url, [
            'timeout'   => 60,
            'sslverify' => true,
        ]);
        if (is_wp_error($response)) {
            return [];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return [];
        }

        $binary = wp_remote_retrieve_body($response);
        if (!is_string($binary) || $binary === '') {
            return [];
        }

        if ($mime === '') {
            $mime = wp_remote_retrieve_header($response, 'content-type');
            $mime = is_string($mime) ? sanitize_mime_type(strtok($mime, ';')) : '';
        }
        if ($mime === '' || strpos($mime, 'image/') !== 0) {
            $checked = wp_check_filetype($filename);
            $mime    = $checked['type'] ?? 'image/png';
        }
        if ($mime === '') {
            $mime = 'image/png';
        }

        return self::import_from_binary($binary, $filename, $mime, $user_id);
    }

    /**
     * Import an uploaded PHP file array ($_FILES item shape).
     */
    public static function import_from_upload(array $file, int $user_id = 0): array {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [];
        }

        $binary = @file_get_contents($file['tmp_name']);
        if ($binary === false || $binary === '') {
            return [];
        }

        $filename = sanitize_file_name($file['name'] ?? 'import.bin');
        $mime     = !empty($file['type']) ? sanitize_mime_type($file['type']) : '';
        if ($mime === '') {
            $checked = wp_check_filetype($filename);
            $mime    = $checked['type'] ?? 'application/octet-stream';
        }

        return self::import_from_binary($binary, $filename, $mime, $user_id);
    }

    /**
     * Import a local filesystem path (folder import).
     */
    public static function import_from_path(string $path, int $user_id = 0): array {
        if ($path === '' || !is_readable($path) || !is_file($path)) {
            return [];
        }

        $binary = @file_get_contents($path);
        if ($binary === false || $binary === '') {
            return [];
        }

        $filename = sanitize_file_name(basename($path));
        $checked  = wp_check_filetype($filename);
        $mime     = $checked['type'] ?? 'application/octet-stream';

        return self::import_from_binary($binary, $filename, $mime, $user_id);
    }

    private static function store_media_binary(string $binary, string $filename, string $mime, int $user_id): array {
        if (!function_exists('wp_upload_bits')) {
            return [];
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload = wp_upload_bits($filename, null, $binary);
        if (!empty($upload['error']) || empty($upload['file'])) {
            return [];
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
            return [];
        }

        $metadata = wp_generate_attachment_metadata((int) $attach_id, $upload['file']);
        if (is_array($metadata) && $metadata !== []) {
            wp_update_attachment_metadata((int) $attach_id, $metadata);
        }

        $resolved = self::resolve_attachment((int) $attach_id);
        if ($resolved['url'] === '') {
            return [];
        }

        return $resolved;
    }

    private static function render_png_binary(int $width, int $height, string $label): string {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }

        $width  = max(64, min(2048, $width));
        $height = max(64, min(2048, $height));
        $im     = imagecreatetruecolor($width, $height);
        if (!$im) {
            return '';
        }

        $bg    = imagecolorallocate($im, 13, 13, 13);
        $gold  = imagecolorallocate($im, 216, 166, 58);
        $light = imagecolorallocate($im, 255, 215, 106);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);
        imagerectangle($im, 8, 8, $width - 9, $height - 9, $gold);

        $font   = 5;
        $lines  = self::wrap_lines(mb_substr(trim($label), 0, 80), max(8, (int) ($width / 10)));
        $line_h = imagefontheight($font) + 4;
        $start  = (int) (($height - (count($lines) * $line_h)) / 2);

        foreach ($lines as $i => $line) {
            $text_width = imagefontwidth($font) * strlen($line);
            $x          = max(12, (int) (($width - $text_width) / 2));
            imagestring($im, $font, $x, $start + ($i * $line_h), $line, $light);
        }

        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        imagedestroy($im);

        return is_string($data) ? $data : '';
    }

    private static function svg_markup(int $width, int $height, string $label, string $bg, string $fg): string {
        $label = esc_html(mb_substr($label, 0, 48));
        $font  = 14;
        $lines = self::wrap_lines($label, 22);
        $y     = (int) ($height / 2) - (count($lines) * $font / 2);

        $text_nodes = '';
        foreach ($lines as $i => $line) {
            $ty = $y + ($i * ($font + 4));
            $text_nodes .= '<text x="50%" y="' . $ty . '" fill="' . esc_attr($fg) . '" font-family="system-ui,sans-serif" font-size="' . $font . '" text-anchor="middle" dominant-baseline="middle">' . $line . '</text>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
            . '<rect width="100%" height="100%" fill="' . esc_attr($bg) . '"/>'
            . '<rect x="8" y="8" width="' . ($width - 16) . '" height="' . ($height - 16) . '" fill="none" stroke="' . esc_attr($fg) . '" stroke-opacity="0.35" rx="12"/>'
            . $text_nodes
            . '</svg>';
    }

    private static function wrap_lines(string $text, int $max): array {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line  = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (mb_strlen($candidate) > $max && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines ?: ['YooY'];
    }
}
