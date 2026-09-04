<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Settings extends YooY_Module_Base {

    public function id(): string { return 'settings'; }
    public function name(): string { return 'Settings'; }
    public function description(): string { return 'Studio settings, provider config, and user preferences.'; }
    public function version(): string { return '1.0.0'; }

    public function register_rest_routes(): void {
        $this->register_route('', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_settings'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update_settings'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);

        $this->register_route('/global', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'global_settings'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    public function get_settings(): WP_REST_Response {
        $user_id  = $this->current_user_id();
        $settings = get_user_meta($user_id, 'yoy_studio_settings', true);

        if (!is_array($settings) || empty($settings)) {
            $settings = $this->default_user_settings();
        }

        return $this->success(['settings' => $settings]);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response {
        $user_id  = $this->current_user_id();
        $incoming = $request->get_json_params();
        if (!is_array($incoming)) {
            $incoming = array();
        }
        $current  = get_user_meta($user_id, 'yoy_studio_settings', true);
        $current  = is_array($current) ? $current : $this->default_user_settings();

        $allowed = array('korean_context', 'auto_save', 'notifications', 'quality');
        $updated = $current;

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $incoming)) {
                continue;
            }
            if ($key === 'quality') {
                $q = sanitize_text_field((string) $incoming[$key]);
                if (!in_array($q, array('standard', 'high'), true)) {
                    return $this->error('지원하지 않는 품질 설정입니다.', 400);
                }
                $updated[$key] = $q;
                continue;
            }
            $updated[$key] = (bool) $incoming[$key];
        }

        // Keep legacy key if present; do not expose in user MY UI.
        if (!isset($updated['default_provider'])) {
            $updated['default_provider'] = isset($current['default_provider']) ? $current['default_provider'] : 'mock';
        }

        update_user_meta($user_id, 'yoy_studio_settings', $updated);

        return $this->success(array(
            'settings' => $updated,
            'message'  => '설정이 저장되었습니다.',
        ));
    }

    public function global_settings(): WP_REST_Response {
        return $this->success([
            'settings' => [
                'site_name'        => get_bloginfo('name'),
                'default_provider' => get_option('yoy_default_provider', 'mock'),
                'credits_enabled'  => (bool) get_option('yoy_credits_enabled', true),
                'marketplace_enabled' => (bool) get_option('yoy_marketplace_enabled', true),
                'community_enabled'   => (bool) get_option('yoy_community_enabled', true),
            ],
        ]);
    }

    private function default_user_settings(): array {
        return [
            'korean_context'    => true,
            'default_provider' => 'mock',
            'auto_save'         => true,
            'notifications'     => true,
            'quality'           => 'standard',
        ];
    }
}
