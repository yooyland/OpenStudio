<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase 7 — lightweight first-use onboarding state (user_meta only).
 */
final class YooY_Onboarding {

    const META_KEY = 'yoy_onboarding';

    /** @var string[] */
    private static $flag_keys = array(
        'onboarding_seen',
        'first_creation_done',
        'gallery_intro_seen',
        'project_intro_seen',
    );

    public static function register(): void {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes(): void {
        register_rest_route(
            'yoy-ai-studio/v1',
            '/core/onboarding',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array(__CLASS__, 'rest_get'),
                    'permission_callback' => 'is_user_logged_in',
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array(__CLASS__, 'rest_patch'),
                    'permission_callback' => 'is_user_logged_in',
                ),
            )
        );
    }

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array {
        return array(
            'onboarding_seen'      => false,
            'first_creation_done'  => false,
            'gallery_intro_seen'   => false,
            'project_intro_seen'   => false,
        );
    }

    /**
     * Payload for wp_localize_script / REST.
     *
     * @return array<string, mixed>
     */
    public static function payload_for_current_user(): array {
        if (!is_user_logged_in()) {
            return array(
                'enabled'         => false,
                'state'           => self::defaults(),
                'starter_credits' => array(
                    'available' => false,
                    'amount'    => 0,
                ),
                'prompts'         => array(),
            );
        }

        $user_id = get_current_user_id();
        $state   = self::get_state($user_id);
        $amount  = self::free_plan_credits();
        $granted = get_user_meta($user_id, 'yoy_welcome_bonus_granted', true) === '1';

        return array(
            'enabled'         => true,
            'state'           => $state,
            'starter_credits' => array(
                'available' => $granted || $amount > 0,
                'amount'    => $amount,
                'granted'   => $granted,
            ),
            'prompts'         => self::starter_prompts(),
        );
    }

    /**
     * @return array<string, bool>
     */
    public static function get_state(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($stored) || empty($stored)) {
            if (self::user_gallery_count($user_id) > 0) {
                $returning = array(
                    'onboarding_seen'     => true,
                    'first_creation_done' => true,
                    'gallery_intro_seen'  => true,
                    'project_intro_seen'  => false,
                );
                update_user_meta($user_id, self::META_KEY, $returning);
                return $returning;
            }
            return self::defaults();
        }

        $out = self::defaults();
        foreach (self::$flag_keys as $key) {
            if (array_key_exists($key, $stored)) {
                $out[$key] = !empty($stored[$key]);
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $incoming
     * @return array<string, bool>
     */
    public static function patch_state(int $user_id, array $incoming): array {
        $current = self::get_state($user_id);
        foreach (self::$flag_keys as $key) {
            if (array_key_exists($key, $incoming)) {
                $current[$key] = !empty($incoming[$key]);
            }
        }
        update_user_meta($user_id, self::META_KEY, $current);
        return $current;
    }

    public static function rest_get(): WP_REST_Response {
        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => self::payload_for_current_user(),
            ),
            200
        );
    }

    public static function rest_patch(WP_REST_Request $request): WP_REST_Response {
        $user_id  = get_current_user_id();
        $incoming = $request->get_json_params();
        if (!is_array($incoming)) {
            $incoming = array();
        }
        $state = self::patch_state($user_id, $incoming);
        $payload = self::payload_for_current_user();
        $payload['state'] = $state;

        return new WP_REST_Response(
            array(
                'success' => true,
                'data'    => $payload,
            ),
            200
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    public static function starter_prompts(): array {
        return array(
            array(
                'label'  => '내 제품 광고 이미지 만들어줘',
                'prompt' => '내 제품 광고 이미지 만들어줘',
            ),
            array(
                'label'  => '사진으로 10초 쇼츠 만들어줘',
                'prompt' => '사진으로 10초 쇼츠 만들어줘',
            ),
            array(
                'label'  => '블로그 소개글 써줘',
                'prompt' => '블로그 소개글 써줘',
            ),
            array(
                'label'  => '잔잔한 브랜드 BGM 만들어줘',
                'prompt' => '잔잔한 브랜드 BGM 만들어줘',
            ),
        );
    }

    private static function free_plan_credits(): int {
        if (class_exists('YooY_Credits_Plans')) {
            $catalog = YooY_Credits_Plans::catalog();
            if (isset($catalog['free']['credits'])) {
                return (int) $catalog['free']['credits'];
            }
        }
        return 100;
    }

    private static function user_gallery_count(int $user_id): int {
        $items = get_user_meta($user_id, 'yoy_gallery_items', true);
        if (!is_array($items)) {
            return 0;
        }
        return count($items);
    }
}
