<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('YooY_Asset_Generator') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
    require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
}

final class YooY_Gallery_Asset_Resolver {

    public static function enrich(array $item): array {
        $type = $item['type'] ?? 'image';
        $attachment_id = (int) ($item['attachment_id'] ?? 0);
        $image_url = self::clean_url($item['image_url'] ?? $item['output_url'] ?? $item['url'] ?? '');
        $thumbnail_url = self::clean_url($item['thumbnail_url'] ?? $item['thumbnail'] ?? '');

        if ($attachment_id > 0 && class_exists('YooY_Asset_Generator')) {
            $resolved = YooY_Asset_Generator::resolve_attachment($attachment_id);
            if ($resolved['url'] !== '') {
                $image_url = $resolved['url'];
            }
            if ($resolved['thumbnail'] !== '') {
                $thumbnail_url = $resolved['thumbnail'];
            }
        }

        if ($thumbnail_url === '' && $image_url !== '') {
            $thumbnail_url = $image_url;
        }
        if ($image_url === '' && $thumbnail_url !== '') {
            $image_url = $thumbnail_url;
        }

        $item['attachment_id'] = $attachment_id;
        $item['image_url']     = $image_url;
        $item['thumbnail_url'] = $thumbnail_url;
        $item['output_url']    = $image_url ?: self::clean_url($item['output_url'] ?? '');
        $item['thumbnail']     = $thumbnail_url ?: self::clean_url($item['thumbnail'] ?? '');

        $needs_asset = in_array($type, ['image', 'video', 'avatar', 'music', 'voice'], true);
        $item['asset_missing'] = $needs_asset && $image_url === '' && $thumbnail_url === '';

        return $item;
    }

    private static function clean_url($url): string {
        if (!class_exists('YooY_Asset_Generator')) {
            $url = is_string($url) ? trim($url) : '';
            return $url;
        }
        return YooY_Asset_Generator::sanitize_asset_url($url);
    }
}
