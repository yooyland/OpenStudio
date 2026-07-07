<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';

final class YooY_Import_Thumbnail_Generator {

    public static function generate(string $type, array $media, array $extracted, string $label): array {
        $attachment_id = (int) ($media['attachment_id'] ?? 0);
        $url           = (string) ($media['url'] ?? '');
        $thumb         = (string) ($media['thumbnail'] ?? '');

        if ($type === 'image' && $attachment_id > 0) {
            $resolved = YooY_Asset_Generator::resolve_attachment($attachment_id);
            return [
                'thumbnail_url' => $resolved['thumbnail'] ?: $resolved['url'],
                'attachment_id' => $attachment_id,
            ];
        }

        if ($type === 'image' && $url !== '') {
            return ['thumbnail_url' => $url, 'attachment_id' => $attachment_id];
        }

        $svg_label = mb_substr($label, 0, 24);
        $placeholder = self::placeholder_uri($type, $svg_label, $extracted);
        if ($placeholder === '') {
            return ['thumbnail_url' => $thumb ?: $url, 'attachment_id' => 0];
        }

        $stored = YooY_Asset_Generator::import_from_data_uri($placeholder, 'yooy-thumb-' . wp_generate_uuid4() . '.png', (int) ($media['user_id'] ?? 0));
        if (!empty($stored['thumbnail'])) {
            return [
                'thumbnail_url' => $stored['thumbnail'],
                'attachment_id' => (int) ($stored['attachment_id'] ?? 0),
            ];
        }

        return ['thumbnail_url' => $thumb ?: $url, 'attachment_id' => $attachment_id];
    }

    private static function placeholder_uri(string $type, string $label, array $extracted): string {
        switch ($type) {
            case 'video':
                return YooY_Asset_Generator::svg_data_uri(640, 360, '▶ ' . $label, '#0a0a12', '#7c3aed');
            case 'music':
                return YooY_Asset_Generator::waveform_data_uri($label);
            case 'voice':
                return YooY_Asset_Generator::svg_data_uri(640, 200, '🎙 ' . $label, '#0d0d0d', '#22c55e');
            case 'writing':
                $pages = (int) ($extracted['page_count'] ?? 1);
                return YooY_Asset_Generator::svg_data_uri(480, 640, 'PDF ' . $pages . 'p', '#111827', '#f59e0b');
            default:
                return YooY_Asset_Generator::svg_data_uri(512, 512, $label);
        }
    }
}
