<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_User_Profile extends YooY_Module_Base {

    public function id(): string { return 'user-profile'; }
    public function name(): string { return 'User Profile'; }
    public function description(): string { return 'User identity, preferences, and studio profile.'; }
    public function version(): string { return '1.0.0'; }

    public function register_rest_routes(): void {
        $this->register_route('/me', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'me'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);
    }

    public function me(): WP_REST_Response {
        $user = wp_get_current_user();

        $profile = [
            'id'           => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'avatar'       => get_avatar_url($user->ID, ['size' => 96]),
            'role'         => user_can($user->ID, 'manage_options') ? 'admin' : 'creator',
            'locale'       => get_user_meta($user->ID, 'yoy_locale', true) ?: 'ko_KR',
            'bio'          => get_user_meta($user->ID, 'yoy_bio', true) ?: '',
            'preferences'  => $this->get_preferences($user->ID),
        ];

        return $this->success(['profile' => $profile]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        $bio     = sanitize_textarea_field($request->get_param('bio') ?: '');
        $locale  = sanitize_text_field($request->get_param('locale') ?: 'ko_KR');

        update_user_meta($user_id, 'yoy_bio', $bio);
        update_user_meta($user_id, 'yoy_locale', $locale);

        if ($request->get_param('display_name')) {
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => sanitize_text_field($request->get_param('display_name')),
            ]);
        }

        return $this->me();
    }

    private function get_preferences(int $user_id): array {
        $stored = get_user_meta($user_id, 'yoy_preferences', true);
        if (is_array($stored) && !empty($stored)) {
            return $stored;
        }

        return [
            'default_generator' => 'video',
            'korean_context'    => true,
            'auto_save_works'   => true,
            'theme'             => 'dark',
        ];
    }
}
