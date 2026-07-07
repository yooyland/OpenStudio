<?php
if (!defined('ABSPATH')) exit;

/**
 * Resolves and validates provider ↔ model pairings for all studios.
 */
final class YooY_Studio_Model_Resolver {

    public static function default_for(string $studio, string $routed_provider, string $catalog_provider = ''): string {
        if ($studio === 'image' && class_exists('YooY_Image_Model_Resolver')) {
            return YooY_Image_Model_Resolver::default_for($routed_provider, $catalog_provider);
        }

        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $routed_provider  = sanitize_text_field($routed_provider);
        $mock_id          = self::mock_catalog_id($studio);

        if ($routed_provider === 'mock' || $catalog_provider === $mock_id) {
            return self::mock_model($studio);
        }

        $map = self::default_map($studio);
        if ($catalog_provider !== '' && isset($map[$catalog_provider])) {
            return $map[$catalog_provider];
        }
        if (isset($map[$routed_provider])) {
            return $map[$routed_provider];
        }

        $from_provider = self::models_from_provider_instance($routed_provider, $studio);
        if (!empty($from_provider)) {
            return $from_provider[0];
        }

        return self::mock_model($studio);
    }

    public static function resolve(string $studio, string $routed_provider, string $catalog_provider, string $requested_model = ''): array {
        if ($studio === 'image' && class_exists('YooY_Image_Model_Resolver')) {
            return YooY_Image_Model_Resolver::resolve($routed_provider, $catalog_provider, $requested_model);
        }

        $routed_provider  = sanitize_text_field($routed_provider);
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $requested_model  = sanitize_text_field($requested_model);
        $default          = self::default_for($studio, $routed_provider, $catalog_provider);
        $allowed          = self::allowed_models($studio, $routed_provider, $catalog_provider);

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

    public static function validate(string $studio, string $routed_provider, string $catalog_provider, string $model): void {
        if ($studio === 'image' && class_exists('YooY_Image_Model_Resolver')) {
            YooY_Image_Model_Resolver::validate($routed_provider, $catalog_provider, $model);
            return;
        }

        $routed_provider  = sanitize_text_field($routed_provider);
        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $model            = sanitize_text_field($model);

        if ($model === '') {
            self::throw_mismatch($studio, $routed_provider, $catalog_provider, $model, 'Model is required.');
        }

        if (!self::is_compatible($studio, $routed_provider, $catalog_provider, $model)) {
            self::throw_mismatch(
                $studio,
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

        $allowed = self::allowed_models($studio, $routed_provider, $catalog_provider);
        if (!in_array($model, $allowed, true)) {
            self::throw_mismatch(
                $studio,
                $routed_provider,
                $catalog_provider,
                $model,
                sprintf('Model "%s" is not supported by this provider.', $model)
            );
        }
    }

    public static function allowed_models(string $studio, string $routed_provider, string $catalog_provider = ''): array {
        if ($studio === 'image' && class_exists('YooY_Image_Model_Resolver')) {
            return YooY_Image_Model_Resolver::allowed_models($routed_provider, $catalog_provider);
        }

        $catalog_provider = self::normalize_catalog_id($catalog_provider);
        $routed_provider  = sanitize_text_field($routed_provider);
        $mock_id          = self::mock_catalog_id($studio);

        if ($routed_provider === 'mock' || $catalog_provider === $mock_id) {
            return [self::mock_model($studio)];
        }

        $from_provider = self::models_from_provider_instance($routed_provider, $studio);
        if (!empty($from_provider)) {
            return $from_provider;
        }

        $map = self::allowed_map($studio);
        if ($catalog_provider !== '' && isset($map[$catalog_provider])) {
            return $map[$catalog_provider];
        }
        if (isset($map[$routed_provider])) {
            return $map[$routed_provider];
        }

        return [self::default_for($studio, $routed_provider, $catalog_provider)];
    }

    public static function is_mock_model(string $model): bool {
        return strpos($model, 'mock-') === 0;
    }

    private static function is_compatible(string $studio, string $routed_provider, string $catalog_provider, string $model): bool {
        $mock_id          = self::mock_catalog_id($studio);
        $is_mock_provider = ($routed_provider === 'mock' || $catalog_provider === $mock_id);
        $is_mock_model    = self::is_mock_model($model);

        if ($is_mock_provider) {
            return $is_mock_model;
        }
        if ($is_mock_model) {
            return false;
        }

        return true;
    }

    private static function mock_catalog_id(string $studio): string {
        $map = [
            'video'  => 'mock-video',
            'music'  => 'mock-music',
            'voice'  => 'mock-voice',
            'avatar' => 'mock-avatar',
            'image'  => 'mock-image',
        ];
        return $map[$studio] ?? 'mock';
    }

    private static function mock_model(string $studio): string {
        $map = [
            'video'  => 'mock-video-v1',
            'music'  => 'mock-music-v1',
            'voice'  => 'mock-voice-v1',
            'avatar' => 'mock-avatar-v1',
            'image'  => 'mock-image-v1',
        ];
        return $map[$studio] ?? 'mock-v1';
    }

    private static function default_map(string $studio): array {
        if ($studio === 'video') {
            return [
                'runway'     => 'gen-3-alpha',
                'mock'       => 'mock-video-v1',
                'mock-video' => 'mock-video-v1',
            ];
        }
        if ($studio === 'music') {
            return [
                'suno'       => 'chirp-v4',
                'mock'       => 'mock-music-v1',
                'mock-music' => 'mock-music-v1',
            ];
        }
        if ($studio === 'voice') {
            return [
                'elevenlabs' => 'eleven_multilingual_v2',
                'openai-tts' => 'tts-1',
                'mock'       => 'mock-voice-v1',
                'mock-voice' => 'mock-voice-v1',
            ];
        }
        if ($studio === 'avatar') {
            return [
                'heygen'      => 'avatar-v2',
                'mock'        => 'mock-avatar-v1',
                'mock-avatar' => 'mock-avatar-v1',
            ];
        }
        return [];
    }

    private static function allowed_map(string $studio): array {
        if ($studio === 'video') {
            return [
                'runway'     => ['gen-3-alpha', 'gen-4-turbo'],
                'mock'       => ['mock-video-v1'],
                'mock-video' => ['mock-video-v1'],
            ];
        }
        if ($studio === 'music') {
            return [
                'suno'       => ['chirp-v3-5', 'chirp-v4', 'chirp-v4-5'],
                'mock'       => ['mock-music-v1'],
                'mock-music' => ['mock-music-v1'],
            ];
        }
        if ($studio === 'voice') {
            return [
                'elevenlabs' => ['eleven_multilingual_v2', 'eleven_turbo_v2_5', 'eleven_flash_v2_5'],
                'openai-tts' => ['tts-1', 'tts-1-hd'],
                'mock'       => ['mock-voice-v1'],
                'mock-voice' => ['mock-voice-v1'],
            ];
        }
        if ($studio === 'avatar') {
            return [
                'heygen'      => ['avatar-v2'],
                'mock'        => ['mock-avatar-v1'],
                'mock-avatar' => ['mock-avatar-v1'],
            ];
        }
        return [];
    }

    private static function models_from_provider_instance(string $routed_provider, string $studio): array {
        $map = [
            'video' => [
                'runway' => ['runway/class-runway-provider.php', 'YooY_Runway_Provider'],
            ],
            'music' => [
                'suno' => ['suno/class-suno-provider.php', 'YooY_Suno_Provider'],
            ],
            'voice' => [
                'elevenlabs' => ['elevenlabs/class-elevenlabs-provider.php', 'YooY_ElevenLabs_Provider'],
            ],
            'avatar' => [
                'heygen' => ['heygen/class-heygen-provider.php', 'YooY_HeyGen_Provider'],
            ],
        ];
        if (!isset($map[$studio][$routed_provider])) {
            return [];
        }
        [$file, $class] = $map[$studio][$routed_provider];
        $path = defined('YOY_AI_STUDIO_PROVIDERS_DIR')
            ? YOY_AI_STUDIO_PROVIDERS_DIR . $file
            : '';
        if ($path === '' || !is_readable($path)) {
            return [];
        }
        require_once $path;
        if (!class_exists($class)) {
            return [];
        }
        try {
            $instance = new $class();
            if (!method_exists($instance, 'models')) {
                return [];
            }
            $models = [];
            foreach ($instance->models() as $model) {
                if (is_array($model) && !empty($model['id'])) {
                    $models[] = (string) $model['id'];
                } elseif (is_string($model) && $model !== '') {
                    $models[] = $model;
                }
            }
            return array_values(array_unique($models));
        } catch (Exception $e) {
            return [];
        }
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

    private static function throw_mismatch(string $studio, string $routed_provider, string $catalog_provider, string $model, string $message): void {
        $context = [
            'studio'             => $studio,
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
