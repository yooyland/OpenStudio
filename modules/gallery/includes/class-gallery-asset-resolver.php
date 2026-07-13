<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('YooY_Asset_Generator') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
    require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
}

final class YooY_Gallery_Asset_Resolver {

    public static function enrich(array $item): array {
        $type = $item['type'] ?? 'image';
        $manifest = class_exists('YooY_Asset_Generator')
            ? YooY_Asset_Generator::build_media_manifest($item)
            : [];

        $attachment_id = (int) ($manifest['attachment_id'] ?? $item['attachment_id'] ?? 0);
        $images = is_array($manifest['images'] ?? null) ? $manifest['images'] : [];

        $full_url = (string) ($manifest['full_url'] ?? $manifest['original_url'] ?? $manifest['url'] ?? '');
        $large_url = (string) ($manifest['large_url'] ?? $full_url);
        $medium_large_url = (string) ($manifest['medium_large_url'] ?? $large_url);
        $thumbnail_url = (string) ($manifest['thumbnail_url'] ?? $manifest['thumbnail'] ?? '');

        if ($thumbnail_url === '' && $large_url !== '') {
            $thumbnail_url = $large_url;
        }
        if ($full_url === '' && $large_url !== '') {
            $full_url = $large_url;
        }

        $display_url = $large_url !== '' ? $large_url : $full_url;

        $item['attachment_id']    = $attachment_id;
        $item['images']           = $images;
        $item['original_url']     = (string) ($manifest['original_url'] ?? $full_url);
        $item['full_url']         = $full_url;
        $item['large_url']        = $large_url;
        $item['medium_large_url'] = $medium_large_url;
        $item['medium_url']       = (string) ($manifest['medium_url'] ?? $medium_large_url);
        $item['thumbnail_url']    = $thumbnail_url;
        $item['thumbnail']        = $thumbnail_url;
        $item['image_url']        = $full_url;
        $item['output_url']       = $full_url;
        $item['asset_url']        = $full_url;
        $item['display_url']      = $display_url;
        $item['srcset']           = (string) ($manifest['srcset'] ?? '');
        $item['sizes']            = (string) ($manifest['sizes'] ?? '(max-width: 768px) 100vw, 33vw');
        $item['image_width']      = (int) ($manifest['width'] ?? 0);
        $item['image_height']     = (int) ($manifest['height'] ?? 0);

        $needs_asset = in_array($type, ['image', 'video', 'avatar', 'music', 'voice'], true);
        // translation / writing are text works — never mark asset_missing for empty media URLs.
        $item['asset_missing'] = $needs_asset && $full_url === '' && $thumbnail_url === '';

        return $item;
    }
}
