<?php
if (!defined('ABSPATH')) exit;

/**
 * Resolves and validates image provider ↔ model pairings.
 */
final class YooY_Image_Model_Resolver {

    public static function default_for(string $routed_provider, string $catalog_provider = ''): string {
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $routed_provider  = sanitize_text_field($routed_provider);

        if ($routed_provider === 'mock' || $catalog_provider === 'mock-image') {
            return 'mock-image-v1';
        }

        $map = self::default_map();
        if ($catalog_provider !== '' && isset($map[$catalog_provider])) {
            return $map[$catalog_provider];
        }
        if (isset($map[$routed_provider])) {
            return $map[$routed_provider];
        }

        return 'gpt-image-1';
    }

    public static function resolve(string $routed_provider, string $catalog_provider, string $requested_model = ''): array {
        $routed_provider  = sanitize_text_field($routed_provider);
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $requested_model  = sanitize_text_field($requested_model);
        $default          = self::default_for($routed_provider, $catalog_provider);
        $allowed          = self::allowed_models($routed_provider, $catalog_provider);

        if ($requested_model === '' || !in_array($requested_model, $allowed, true)) {
            return [
                'model'     => $default,
                'requested' => $requested_model,
                'corrected' => $requested_model !== '' && $requested_model !== $default,
            ];
        }

        return [
            'model'     => $requested_model,
            'requested' => $requested_model,
            'corrected' => false,
        ];
    }

    public static function validate(string $routed_provider, string $catalog_provider, string $model): void {
        $routed_provider  = sanitize_text_field($routed_provider);
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $model            = sanitize_text_field($model);

        if ($model === '') {
            self::throw_mismatch($routed_provider, $catalog_provider, $model, 'Model is required.');
        }

        if (!self::is_compatible($routed_provider, $catalog_provider, $model)) {
            self::throw_mismatch(
                $routed_provider,
                $catalog_provider,
                $model,
                sprintf(
                    'Model "%s" is not valid for provider "%s".',
                    $model,
                    $catalog_provider !== '' ? $catalog_provider : $routed_provider
                )
            );
        }

        $allowed = self::allowed_models($routed_provider, $catalog_provider);
        if (!in_array($model, $allowed, true)) {
            self::throw_mismatch(
                $routed_provider,
                $catalog_provider,
                $model,
                sprintf('Model "%s" is not supported by this provider.', $model)
            );
        }
    }

    public static function allowed_models(string $routed_provider, string $catalog_provider = ''): array {
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $routed_provider  = sanitize_text_field($routed_provider);

        if ($routed_provider === 'mock' || $catalog_provider === 'mock-image') {
            return ['mock-image-v1', 'mock-image-v2'];
        }

        $from_provider = self::models_from_provider_instance($routed_provider);
        if (!empty($from_provider)) {
            return $from_provider;
        }

        $map = self::allowed_map();
        if ($catalog_provider !== '' && isset($map[$catalog_provider])) {
            return $map[$catalog_provider];
        }
        if (isset($map[$routed_provider])) {
            return $map[$routed_provider];
        }

        return [$default = self::default_for($routed_provider, $catalog_provider)];
    }

    public static function is_mock_model(string $model): bool {
        return strpos($model, 'mock-') === 0;
    }

    private static function is_compatible(string $routed_provider, string $catalog_provider, string $model): bool {
        $is_mock_provider = ($routed_provider === 'mock' || $catalog_provider === 'mock-image');
        $is_mock_model    = self::is_mock_model($model);

        if ($is_mock_provider) {
            return $is_mock_model;
        }
        if ($is_mock_model) {
            return false;
        }

        if ($routed_provider === 'openai' || $catalog_provider === 'openai') {
            return $model === 'gpt-image-1';
        }

        return true;
    }

    private static function default_map(): array {
        return [
            'openai'       => 'gpt-image-1',
            'mock'         => 'mock-image-v1',
            'mock-image'   => 'mock-image-v1',
            'replicate'    => 'flux-schnell',
            'flux'         => 'flux-schnell',
            'gemini-image' => 'gemini-image-v1',
            'stability'    => 'stable-diffusion-xl',
            'ideogram'     => 'ideogram-v2',
        ];
    }

    private static function allowed_map(): array {
        return [
            'openai'       => ['gpt-image-1'],
            'mock'         => ['mock-image-v1', 'mock-image-v2'],
            'mock-image'   => ['mock-image-v1', 'mock-image-v2'],
            'replicate'    => ['flux-schnell', 'flux-dev', 'flux-pro'],
            'flux'         => ['flux-schnell', 'flux-dev', 'flux-pro'],
            'gemini-image' => ['gemini-image-v1'],
            'stability'    => ['stable-diffusion-xl', 'stable-diffusion'],
            'ideogram'     => ['ideogram-v2'],
        ];
    }

    private static function models_from_provider_instance(string $routed_provider): array {
        if (!class_exists('YooY_Image_API_Router')) {
            return [];
        }

        try {
            $router = new YooY_Image_API_Router();
            foreach ($router->providers() as $entry) {
                $route_id = (string) ($entry['route_id'] ?? $entry['id'] ?? '');
                $id       = (string) ($entry['id'] ?? '');
                if ($route_id !== $routed_provider && $id !== $routed_provider) {
                    continue;
                }
                $models = [];
                foreach (($entry['models'] ?? []) as $model) {
                    if (is_array($model) && !empty($model['id'])) {
                        $models[] = (string) $model['id'];
                    } elseif (is_string($model) && $model !== '') {
                        $models[] = $model;
                    }
                }
                return array_values(array_unique($models));
            }
        } catch (Exception $e) {
            return [];
        }

        return [];
    }

    private static function normalize_catalog_id(string $id): string {
        $id = sanitize_text_field($id);
        if ($id === 'auto' || $id === '') {
            return '';
        }
        if (class_exists('YooY_Provider_Resolver')) {
            $aliases = [
                'openai-image' => 'openai',
                'gpt-image'    => 'openai',
                'dalle'        => 'openai',
                'dall-e'       => 'openai',
            ];
            if (isset($aliases[$id])) {
                return $aliases[$id];
            }
        }
        return $id;
    }

    private static function throw_mismatch(string $routed_provider, string $catalog_provider, string $model, string $message): void {
        $context = [
            'provider_requested' => $catalog_provider !== '' ? $catalog_provider : $routed_provider,
            'provider_resolved'  => $routed_provider,
            'model_requested'    => $model,
            'reason'             => 'provider_model_mismatch',
            'missing_fields'     => ['model'],
        ];
        if (class_exists('YooY_Generation_Exception')) {
            throw new YooY_Generation_Exception('provider_validation', 'provider_model_mismatch', $message, $context);
        }
        throw new Exception($message);
    }
}
