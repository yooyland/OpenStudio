<?php
if (!defined('ABSPATH')) exit;

/**
 * Resolves and validates image provider ↔ model ↔ size pairings.
 */
final class YooY_Image_Size_Resolver {

    public static function resolve(
        string $routed_provider,
        string $catalog_provider,
        string $model,
        string $aspect_ratio = '1:1',
        string $resolution = '1024',
        string $requested_size = ''
    ): array {
        $routed_provider  = sanitize_text_field($routed_provider);
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $model            = sanitize_text_field($model);
        $aspect_ratio     = sanitize_text_field($aspect_ratio);
        $resolution       = sanitize_text_field($resolution);
        $requested_size   = sanitize_text_field($requested_size);

        $original = $requested_size !== '' ? $requested_size : self::size_from_ratio($aspect_ratio, $resolution, $routed_provider, $catalog_provider, $model);
        $mapped   = self::map_size($routed_provider, $catalog_provider, $model, $aspect_ratio, $resolution, $original);

        return [
            'size'            => $mapped['size'],
            'requested'       => $requested_size !== '' ? $requested_size : $original,
            'original'        => $original,
            'corrected'       => $mapped['size'] !== $original,
            'aspect_ratio'    => $aspect_ratio,
            'resolution'      => $resolution,
        ];
    }

    public static function validate(
        string $routed_provider,
        string $catalog_provider,
        string $model,
        string $size
    ): void {
        $routed_provider  = sanitize_text_field($routed_provider);
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $model            = sanitize_text_field($model);
        $size             = sanitize_text_field($size);

        if ($size === '') {
            self::throw_mismatch($routed_provider, $catalog_provider, $model, $size, 'Image size is required.');
        }

        if (!self::is_compatible($routed_provider, $catalog_provider, $model, $size)) {
            self::throw_mismatch(
                $routed_provider,
                $catalog_provider,
                $model,
                $size,
                sprintf('Size "%s" is not valid for %s / %s.', $size, $catalog_provider ?: $routed_provider, $model)
            );
        }
    }

    public static function allowed_sizes(string $routed_provider, string $catalog_provider = '', string $model = ''): array {
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $routed_provider  = sanitize_text_field($routed_provider);
        $model            = sanitize_text_field($model);

        if ($routed_provider === 'mock' || $catalog_provider === 'mock-image') {
            return ['512x512', '1024x1024', '1024x1792', '1792x1024', '1536x1024', '1024x1536'];
        }

        if (self::is_gpt_image_1($routed_provider, $catalog_provider, $model)) {
            return ['auto', '1024x1024', '1024x1536', '1536x1024'];
        }

        if (self::is_dalle_3($routed_provider, $catalog_provider, $model)) {
            return ['1024x1024', '1024x1792', '1792x1024'];
        }

        return ['512x512', '1024x1024', '1024x1792', '1792x1024', '1536x1024', '1024x1536'];
    }

    public static function aspect_ratios_for(string $routed_provider, string $catalog_provider = '', string $model = ''): array {
        if (self::is_gpt_image_1($routed_provider, $catalog_provider, $model)) {
            return ['1:1', '16:9', '9:16', '4:5', '3:2', '2:3'];
        }
        return ['1:1', '16:9', '9:16', '4:5', '3:2', '2:3'];
    }

    public static function map_aspect_to_size(
        string $routed_provider,
        string $catalog_provider,
        string $model,
        string $aspect_ratio
    ): string {
        $mapped = self::map_size($routed_provider, $catalog_provider, $model, $aspect_ratio, '1024', '');
        return $mapped['size'];
    }

    private static function map_size(
        string $routed_provider,
        string $catalog_provider,
        string $model,
        string $aspect_ratio,
        string $resolution,
        string $requested_size
    ): array {
        if (self::is_gpt_image_1($routed_provider, $catalog_provider, $model)) {
            return ['size' => self::map_gpt_image_1($aspect_ratio, $requested_size)];
        }

        if ($requested_size !== '' && self::is_compatible($routed_provider, $catalog_provider, $model, $requested_size)) {
            return ['size' => $requested_size];
        }

        return ['size' => self::size_from_ratio($aspect_ratio, $resolution, $routed_provider, $catalog_provider, $model)];
    }

    private static function map_gpt_image_1(string $aspect_ratio, string $requested_size): string {
        $allowed = ['auto', '1024x1024', '1024x1536', '1536x1024'];

        if ($requested_size !== '' && in_array($requested_size, $allowed, true)) {
            return $requested_size;
        }

        if ($requested_size === '1024x1792') {
            return '1024x1536';
        }
        if ($requested_size === '1792x1024') {
            return '1536x1024';
        }

        switch ($aspect_ratio) {
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

    private static function size_from_ratio(
        string $aspect_ratio,
        string $resolution,
        string $routed_provider,
        string $catalog_provider,
        string $model
    ): string {
        if (self::is_gpt_image_1($routed_provider, $catalog_provider, $model)) {
            return self::map_gpt_image_1($aspect_ratio, '');
        }

        $base = max(512, (int) $resolution);
        if ($base <= 0) {
            $base = 1024;
        }

        switch ($aspect_ratio) {
            case '16:9':
                return ($base === 1792 ? '1792x1024' : (int) round($base * 16 / 9) . 'x' . $base);
            case '9:16':
                return ($base === 1792 ? '1024x1792' : $base . 'x' . (int) round($base * 16 / 9));
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

    private static function is_compatible(
        string $routed_provider,
        string $catalog_provider,
        string $model,
        string $size
    ): bool {
        if (self::is_gpt_image_1($routed_provider, $catalog_provider, $model)) {
            return in_array($size, ['auto', '1024x1024', '1024x1536', '1536x1024'], true);
        }

        if (self::is_dalle_3($routed_provider, $catalog_provider, $model)) {
            return in_array($size, ['1024x1024', '1024x1792', '1792x1024'], true);
        }

        return (bool) preg_match('/^\d+x\d+$/', $size);
    }

    private static function is_gpt_image_1(string $routed_provider, string $catalog_provider, string $model): bool {
        if ($model === 'gpt-image-1') {
            return true;
        }
        if ($model !== '' && $model !== 'gpt-image-1') {
            return false;
        }
        return ($routed_provider === 'openai' || $catalog_provider === 'openai');
    }

    private static function is_dalle_3(string $routed_provider, string $catalog_provider, string $model): bool {
        return $model === 'dall-e-3'
            && ($routed_provider === 'openai' || $catalog_provider === 'openai');
    }

    private static function normalize_catalog_id(string $id): string {
        $id = sanitize_text_field($id);
        if ($id === 'auto' || $id === '') {
            return '';
        }
        $aliases = [
            'openai-image' => 'openai',
            'gpt-image'    => 'openai',
            'dalle'        => 'openai',
            'dall-e'       => 'openai',
        ];
        return isset($aliases[$id]) ? $aliases[$id] : $id;
    }

    private static function throw_mismatch(
        string $routed_provider,
        string $catalog_provider,
        string $model,
        string $size,
        string $message
    ): void {
        $context = [
            'provider_requested' => $catalog_provider !== '' ? $catalog_provider : $routed_provider,
            'provider_resolved'  => $routed_provider,
            'model'              => $model,
            'size_requested'     => $size,
            'reason'             => 'provider_size_mismatch',
            'missing_fields'     => ['size'],
        ];
        if (class_exists('YooY_Generation_Exception')) {
            throw new YooY_Generation_Exception('provider_validation', 'provider_size_mismatch', $message, $context);
        }
        throw new Exception($message);
    }
}
