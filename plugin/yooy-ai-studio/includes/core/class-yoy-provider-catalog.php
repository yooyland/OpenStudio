<?php
if (!defined('ABSPATH')) exit;

/**
 * Canonical provider catalog (user-facing). Topview is excluded.
 */
final class YooY_Provider_Catalog {

    public static function definitions(): array {
        return [
            // Video
            'runway' => [
                'name' => 'Runway', 'studios' => ['video'], 'option' => 'yoy_runway_api_key',
                'priority' => 95, 'type' => 'video', 'impl' => 'runway', 'real_impl' => false,
            ],
            'google-veo' => [
                'name' => 'Google Veo', 'studios' => ['video'], 'option' => 'yoy_google_veo_api_key',
                'priority' => 92, 'type' => 'video', 'impl' => 'bridge',
            ],
            'kling' => [
                'name' => 'Kling', 'studios' => ['video'], 'option' => 'yoy_kling_api_key',
                'priority' => 90, 'type' => 'video', 'impl' => 'bridge',
            ],
            'luma' => [
                'name' => 'Luma', 'studios' => ['video'], 'option' => 'yoy_luma_api_key',
                'priority' => 88, 'type' => 'video', 'impl' => 'bridge',
            ],
            'pika' => [
                'name' => 'Pika', 'studios' => ['video'], 'option' => 'yoy_pika_api_key',
                'priority' => 86, 'type' => 'video', 'impl' => 'bridge',
            ],
            'ltx' => [
                'name' => 'LTX Studio', 'studios' => ['video'], 'option' => 'yoy_ltx_api_key',
                'priority' => 84, 'type' => 'video', 'impl' => 'bridge',
            ],
            'mock-video' => [
                'name' => 'Mock Video', 'studios' => ['video'], 'option' => '',
                'priority' => 0, 'type' => 'video', 'impl' => 'mock', 'route_id' => 'mock',
            ],

            // Image
            'openai' => [
                'name' => 'OpenAI Image', 'studios' => ['image', 'writing'], 'option' => 'yoy_openai_api_key',
                'priority' => 95, 'type' => 'image', 'impl' => 'openai',
            ],
            'gemini-image' => [
                'name' => 'Gemini Image', 'studios' => ['image'], 'option' => 'yoy_gemini_api_key',
                'priority' => 90, 'type' => 'image', 'impl' => 'bridge',
            ],
            'flux' => [
                'name' => 'Flux', 'studios' => ['image'], 'option' => 'yoy_replicate_api_key',
                'priority' => 88, 'type' => 'image', 'impl' => 'replicate', 'route_id' => 'replicate',
            ],
            'replicate' => [
                'name' => 'Replicate', 'studios' => ['image'], 'option' => 'yoy_replicate_api_key',
                'priority' => 85, 'type' => 'image', 'impl' => 'replicate',
            ],
            'ideogram' => [
                'name' => 'Ideogram', 'studios' => ['image'], 'option' => 'yoy_ideogram_api_key',
                'priority' => 87, 'type' => 'image', 'impl' => 'bridge',
            ],
            'stability' => [
                'name' => 'Stability AI', 'studios' => ['image'], 'option' => 'yoy_stability_api_key',
                'priority' => 82, 'type' => 'image', 'impl' => 'bridge',
            ],
            'mock-image' => [
                'name' => 'Mock Image', 'studios' => ['image'], 'option' => '',
                'priority' => 0, 'type' => 'image', 'impl' => 'mock', 'route_id' => 'mock',
            ],

            // Music
            'suno' => [
                'name' => 'Suno', 'studios' => ['music'], 'option' => 'yoy_suno_api_key',
                'priority' => 95, 'type' => 'music', 'impl' => 'suno',
            ],
            'udio' => [
                'name' => 'Udio', 'studios' => ['music'], 'option' => 'yoy_udio_api_key',
                'priority' => 90, 'type' => 'music', 'impl' => 'bridge',
            ],
            'mock-music' => [
                'name' => 'Mock Music', 'studios' => ['music'], 'option' => '',
                'priority' => 0, 'type' => 'music', 'impl' => 'mock', 'route_id' => 'mock',
            ],

            // Voice
            'elevenlabs' => [
                'name' => 'ElevenLabs', 'studios' => ['voice'], 'option' => 'yoy_elevenlabs_api_key',
                'priority' => 95, 'type' => 'voice', 'impl' => 'elevenlabs',
            ],
            'openai-tts' => [
                'name' => 'OpenAI TTS', 'studios' => ['voice'], 'option' => 'yoy_openai_api_key',
                'priority' => 90, 'type' => 'voice', 'impl' => 'bridge',
            ],
            'playht' => [
                'name' => 'PlayHT', 'studios' => ['voice'], 'option' => 'yoy_playht_api_key',
                'priority' => 85, 'type' => 'voice', 'impl' => 'bridge',
            ],
            'mock-voice' => [
                'name' => 'Mock Voice', 'studios' => ['voice'], 'option' => '',
                'priority' => 0, 'type' => 'voice', 'impl' => 'mock', 'route_id' => 'mock',
            ],

            // Avatar
            'heygen' => [
                'name' => 'HeyGen', 'studios' => ['avatar'], 'option' => 'yoy_heygen_api_key',
                'priority' => 95, 'type' => 'avatar', 'impl' => 'heygen',
            ],
            'did' => [
                'name' => 'D-ID', 'studios' => ['avatar'], 'option' => 'yoy_did_api_key',
                'priority' => 90, 'type' => 'avatar', 'impl' => 'bridge',
            ],
            'synthesia' => [
                'name' => 'Synthesia', 'studios' => ['avatar'], 'option' => 'yoy_synthesia_api_key',
                'priority' => 88, 'type' => 'avatar', 'impl' => 'bridge',
            ],
            'mock-avatar' => [
                'name' => 'Mock Avatar', 'studios' => ['avatar'], 'option' => '',
                'priority' => 0, 'type' => 'avatar', 'impl' => 'mock', 'route_id' => 'mock',
            ],

            // Writing (admin only, no studio router)
            'gemini' => [
                'name' => 'Gemini', 'studios' => ['writing'], 'option' => 'yoy_gemini_api_key',
                'priority' => 80, 'type' => 'writing', 'impl' => 'bridge',
            ],
            'claude' => [
                'name' => 'Claude', 'studios' => ['writing'], 'option' => 'yoy_claude_api_key',
                'priority' => 78, 'type' => 'writing', 'impl' => 'bridge',
            ],

            // Translation (Translator Studio)
            'openai-translator' => [
                'name' => 'OpenAI Translator', 'studios' => ['translation'], 'option' => 'yoy_openai_api_key',
                'priority' => 95, 'type' => 'translation', 'impl' => 'openai-translator', 'route_id' => 'openai',
            ],
            'mock-translator' => [
                'name' => 'Mock Translator', 'studios' => ['translation'], 'option' => '',
                'priority' => 0, 'type' => 'translation', 'impl' => 'mock', 'route_id' => 'mock',
            ],
        ];
    }

    public static function get(string $id): ?array {
        $all = self::definitions();
        return $all[$id] ?? null;
    }

    public static function for_studio(string $studio): array {
        $items = [];
        foreach (self::definitions() as $id => $meta) {
            if (!in_array($studio, $meta['studios'], true)) {
                continue;
            }
            $items[$id] = $meta;
        }
        return $items;
    }

    public static function route_id(string $catalog_id): string {
        $def = self::get($catalog_id);
        if (!$def) {
            return $catalog_id;
        }
        return (string) ($def['route_id'] ?? $catalog_id);
    }

    public static function list_for_studio(string $studio, array $registry = []): array {
        $list = [];
        foreach (self::for_studio($studio) as $id => $def) {
            $route_id = self::route_id($id);
            $provider = $registry[$id] ?? $registry[$route_id] ?? null;
            $entry = [
                'id'       => $id,
                'name'     => $def['name'],
                'route_id' => $route_id,
                'models'   => [],
                'is_mock'  => ($def['impl'] ?? '') === 'mock',
            ];
            if ($provider && method_exists($provider, 'models')) {
                $entry['models'] = $provider->models();
            }
            if ($provider && method_exists($provider, 'types')) {
                $entry['types'] = $provider->types();
            }
            if (class_exists('YooY_Provider_Resolver')) {
                $eval = YooY_Provider_Resolver::evaluate($id, $studio);
                $auto = YooY_Provider_Resolver::evaluate_for_auto($id, $studio);
                $state = YooY_Provider_Resolver::get_provider_state($id);
                $has_key = ($def['option'] ?? '') !== '' && class_exists('YooY_Secrets')
                    ? YooY_Secrets::has_api_key($def['option'])
                    : false;
                $status = self::ui_status($def, $eval);
                $entry['status']       = $status;
                $entry['status_label'] = self::ui_status_label($status);
                $entry['usable']       = !empty($eval['usable']);
                $entry['is_live']      = !empty($eval['is_live']);
                $entry['error_code']   = (string) ($eval['error_code'] ?? '');
                $entry['has_key']      = $has_key;
                $entry['last_test_status'] = (string) ($state['last_test_status'] ?? 'not_tested');
                $entry['auto_eligible'] = !empty($auto['usable']);
                $entry['auto_tier'] = (int) ($auto['tier'] ?? 0);
            }
            $list[] = $entry;
        }
        return $list;
    }

    private static function ui_status(array $def, array $eval): string {
        if (($def['impl'] ?? '') === 'mock') {
            return 'available';
        }
        if (!empty($eval['usable'])) {
            return 'connected';
        }
        $code = (string) ($eval['error_code'] ?? '');
        if ($code === 'provider_not_tested' || $code === 'provider_test_failed') {
            return 'not_tested';
        }
        if ($code === 'provider_not_configured') {
            return 'not_configured';
        }
        return 'unavailable';
    }

    private static function ui_status_label(string $status): string {
        switch ($status) {
            case 'connected':
                return 'Connected';
            case 'not_tested':
                return 'Not Tested';
            case 'available':
                return 'Available';
            case 'not_configured':
                return 'Not Configured';
            default:
                return 'Unavailable';
        }
    }

    public static function public_meta(): array {
        $items = [];
        foreach (self::definitions() as $id => $meta) {
            if (strpos($id, 'topview') !== false) {
                continue;
            }
            $has_key = ($meta['option'] ?? '') !== '' && class_exists('YooY_Secrets')
                ? YooY_Secrets::has_api_key($meta['option'])
                : false;
            $items[] = [
                'id'      => $id,
                'name'    => $meta['name'],
                'studios' => $meta['studios'],
                'type'    => $meta['type'],
                'status'  => ($meta['impl'] ?? '') === 'mock' ? 'mock' : ($has_key ? 'active' : 'pending'),
                'mock'    => ($meta['impl'] ?? '') === 'mock',
                'models'  => [],
            ];
        }
        return $items;
    }
}
