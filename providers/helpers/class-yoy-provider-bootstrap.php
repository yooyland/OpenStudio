<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-provider-guard.php';

/**
 * Bootstraps studio routers from YooY_Provider_Catalog definitions.
 */
final class YooY_Provider_Bootstrap {

    public static function register_video(array &$registry): void {
        self::load_type('video', $registry, 'YooY_Video_Provider_Interface', [
            'mock'   => ['file' => 'mock-video/class-mock-video-provider.php', 'class' => 'YooY_Mock_Video_Provider'],
            'runway' => ['file' => 'runway/class-runway-provider.php', 'class' => 'YooY_Runway_Provider'],
        ], 'YooY_Bridge_Video_Provider');
    }

    public static function register_image(array &$registry): void {
        self::load_type('image', $registry, 'YooY_Image_Provider_Interface', [
            'mock'      => ['file' => 'mock-image/class-mock-image-provider.php', 'class' => 'YooY_Mock_Image_Provider'],
            'openai'    => ['file' => 'openai-image/class-openai-image-provider.php', 'class' => 'YooY_OpenAI_Image_Provider'],
            'replicate' => ['file' => 'replicate-image/class-replicate-image-provider.php', 'class' => 'YooY_Replicate_Image_Provider'],
        ], 'YooY_Bridge_Image_Provider');
    }

    public static function register_music(array &$registry): void {
        self::load_type('music', $registry, 'YooY_Music_Provider_Interface', [
            'mock' => ['file' => 'mock-music/class-mock-music-provider.php', 'class' => 'YooY_Mock_Music_Provider'],
            'suno' => ['file' => 'suno/class-suno-provider.php', 'class' => 'YooY_Suno_Provider'],
        ], 'YooY_Bridge_Music_Provider');
    }

    public static function register_voice(array &$registry): void {
        self::load_type('voice', $registry, 'YooY_Voice_Provider_Interface', [
            'mock'       => ['file' => 'mock-voice/class-mock-voice-provider.php', 'class' => 'YooY_Mock_Voice_Provider'],
            'elevenlabs' => ['file' => 'elevenlabs/class-elevenlabs-provider.php', 'class' => 'YooY_ElevenLabs_Provider'],
        ], 'YooY_Bridge_Voice_Provider');
    }

    public static function register_avatar(array &$registry): void {
        self::load_type('avatar', $registry, 'YooY_Avatar_Provider_Interface', [
            'mock'   => ['file' => 'mock-avatar/class-mock-avatar-provider.php', 'class' => 'YooY_Mock_Avatar_Provider'],
            'heygen' => ['file' => 'heygen/class-heygen-provider.php', 'class' => 'YooY_HeyGen_Provider'],
        ], 'YooY_Bridge_Avatar_Provider');
    }

    /**
     * Translation providers for Translator Studio (OpenAI + Mock).
     * Does not touch image/video/music/voice registries.
     */
    public static function register_translation(array &$registry): void {
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-translation-provider.php';

        $mock_file = YOY_AI_STUDIO_PROVIDERS_DIR . 'mock-translator/class-mock-translation-provider.php';
        if (file_exists($mock_file)) {
            require_once $mock_file;
            if (class_exists('YooY_Mock_Translation_Provider')) {
                $mock = new YooY_Mock_Translation_Provider();
                $registry['mock'] = $mock;
                $registry['mock-translator'] = $mock;
            }
        }

        $openai_file = YOY_AI_STUDIO_PROVIDERS_DIR . 'openai-translator/class-openai-translation-provider.php';
        if (file_exists($openai_file)) {
            require_once $openai_file;
            if (class_exists('YooY_OpenAI_Translation_Provider')) {
                $openai = new YooY_OpenAI_Translation_Provider();
                $registry['openai'] = $openai;
                $registry['openai-translator'] = $openai;
            }
        }
    }

    public static function catalog_providers(string $studio): array {
        if (!class_exists('YooY_Provider_Catalog')) {
            return [];
        }
        $list = [];
        foreach (YooY_Provider_Catalog::for_studio($studio) as $id => $def) {
            $route_id = YooY_Provider_Catalog::route_id($id);
            $list[] = [
                'id'         => $id,
                'route_id'   => $route_id,
                'name'       => $def['name'],
                'catalog_id' => $id,
            ];
        }
        return $list;
    }

    private static function load_type(string $type, array &$registry, string $interface, array $native, string $bridge_class): void {
        if (!class_exists('YooY_Provider_Catalog')) {
            return;
        }

        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'bridges/class-yoy-bridge-video-provider.php';
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'bridges/class-yoy-bridge-image-provider.php';
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'bridges/class-yoy-bridge-music-provider.php';
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'bridges/class-yoy-bridge-voice-provider.php';
        require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'bridges/class-yoy-bridge-avatar-provider.php';

        $bridges = [];
        foreach (YooY_Provider_Catalog::for_studio($type) as $id => $def) {
            $route_id = YooY_Provider_Catalog::route_id($id);
            if (($def['impl'] ?? '') === 'mock' && $route_id === 'mock') {
                continue;
            }
            if (($def['impl'] ?? '') === 'bridge') {
                $bridges[$id] = $def;
            }
        }

        foreach ($native as $route_id => $info) {
            $file = YOY_AI_STUDIO_PROVIDERS_DIR . $info['file'];
            if (!file_exists($file)) {
                continue;
            }
            require_once $file;
            if (class_exists($info['class'])) {
                $registry[$route_id] = new $info['class']();
            }
        }

        foreach (YooY_Provider_Catalog::for_studio($type) as $id => $def) {
            if (($def['impl'] ?? '') !== 'bridge') {
                continue;
            }
            if (!class_exists($bridge_class)) {
                continue;
            }
            $instance = new $bridge_class($id, $def);
            if ($instance instanceof $interface) {
                $registry[$id] = $instance;
            }
        }

        // Catalog aliases (e.g. flux -> replicate) as selectable ids
        foreach (YooY_Provider_Catalog::for_studio($type) as $id => $def) {
            $route_id = YooY_Provider_Catalog::route_id($id);
            if ($id === $route_id || !isset($registry[$route_id])) {
                continue;
            }
            if (!isset($registry[$id])) {
                $registry[$id] = $registry[$route_id];
            }
        }
    }
}
