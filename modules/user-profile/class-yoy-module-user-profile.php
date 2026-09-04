<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_User_Profile extends YooY_Module_Base {

    public function id(): string { return 'user-profile'; }
    public function name(): string { return 'User Profile'; }
    public function description(): string { return 'User identity, preferences, and studio profile.'; }
    public function version(): string { return '1.1.0'; }

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
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_account'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);
    }

    public function me(): WP_REST_Response {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return $this->error('로그인이 필요합니다.', 401);
        }

        return $this->success([
            'profile' => $this->profile_payload($user),
        ]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        if (!$user_id) {
            return $this->error('로그인이 필요합니다.', 401);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = [];
        }

        if (array_key_exists('bio', $params)) {
            $bio = sanitize_textarea_field((string) $params['bio']);
            if (function_exists('mb_strlen') && mb_strlen($bio) > 500) {
                return $this->error('소개는 500자 이내로 입력해 주세요.', 400);
            }
            if (strlen($bio) > 2000) {
                return $this->error('소개는 500자 이내로 입력해 주세요.', 400);
            }
            update_user_meta($user_id, 'yoy_bio', $bio);
        }

        if (array_key_exists('locale', $params)) {
            $locale = sanitize_text_field((string) $params['locale']);
            $allowed_locales = array('ko_KR', 'en_US');
            if ($locale !== '' && !in_array($locale, $allowed_locales, true)) {
                return $this->error('지원하지 않는 언어 설정입니다.', 400);
            }
            if ($locale !== '') {
                update_user_meta($user_id, 'yoy_locale', $locale);
            }
        }

        if (array_key_exists('display_name', $params)) {
            $name = trim(sanitize_text_field((string) $params['display_name']));
            if ($name === '') {
                return $this->error('표시 이름을 입력해 주세요.', 400);
            }
            if (function_exists('mb_strlen') && mb_strlen($name) > 60) {
                return $this->error('표시 이름은 60자 이내로 입력해 주세요.', 400);
            }
            if (strlen($name) > 120) {
                return $this->error('표시 이름은 60자 이내로 입력해 주세요.', 400);
            }
            $result = wp_update_user(array(
                'ID'           => $user_id,
                'display_name' => $name,
            ));
            if (is_wp_error($result)) {
                return $this->error('프로필을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.', 400);
            }
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return $this->error('계정을 찾을 수 없습니다.', 404);
        }

        return $this->success(array(
            'profile' => $this->profile_payload($user),
            'message' => '프로필이 저장되었습니다.',
        ));
    }

    /**
     * Soft-gated account deletion via WordPress wp_delete_user.
     * Requires JSON body: { "confirm": "DELETE" }
     */
    public function delete_account(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        if (!$user_id) {
            return $this->error('로그인이 필요합니다.', 401);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }
        $confirm = '';
        if (isset($params['confirm'])) {
            $confirm = (string) $params['confirm'];
        } elseif ($request->get_param('confirm')) {
            $confirm = (string) $request->get_param('confirm');
        }
        if ($confirm !== 'DELETE') {
            return $this->error('계정 삭제를 확인하려면 DELETE를 입력해야 합니다.', 400);
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return $this->error('계정을 찾을 수 없습니다.', 404);
        }

        if (user_can($user_id, 'manage_options')) {
            $admins = get_users(array(
                'role'   => 'administrator',
                'fields' => 'ID',
                'number' => 3,
            ));
            if (is_array($admins) && count($admins) <= 1) {
                return $this->error('유일한 관리자 계정은 삭제할 수 없습니다.', 403);
            }
        }

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user($user_id);
        if (!$deleted) {
            return $this->error('계정을 삭제하지 못했습니다. 지원팀에 문의해 주세요.', 500);
        }

        wp_logout();

        return $this->success(array(
            'deleted'  => true,
            'message'  => '계정이 삭제되었습니다.',
            'redirect' => home_url('/'),
        ));
    }

    /**
     * @param WP_User $user
     * @return array
     */
    private function profile_payload($user) {
        $avatar = get_avatar_url($user->ID, array('size' => 96));
        if (!is_string($avatar)) {
            $avatar = '';
        }

        return array(
            'id'           => (int) $user->ID,
            'display_name' => (string) $user->display_name,
            'email'        => (string) $user->user_email,
            'avatar'       => $avatar,
            'role'         => user_can($user->ID, 'manage_options') ? 'admin' : 'creator',
            'locale'       => get_user_meta($user->ID, 'yoy_locale', true) ?: 'ko_KR',
            'bio'          => (string) (get_user_meta($user->ID, 'yoy_bio', true) ?: ''),
            'preferences'  => $this->get_preferences((int) $user->ID),
            'account'      => array(
                'can_delete'         => !($this->is_sole_administrator((int) $user->ID)),
                'email_editable'     => false,
                'password_reset_url' => esc_url_raw(wp_lostpassword_url()),
                'privacy_policy_url' => esc_url_raw((string) get_privacy_policy_url()),
                'logout_url'         => esc_url_raw(wp_logout_url(get_permalink())),
            ),
        );
    }

    private function is_sole_administrator(int $user_id): bool {
        if (!user_can($user_id, 'manage_options')) {
            return false;
        }
        $admins = get_users(array(
            'role'   => 'administrator',
            'fields' => 'ID',
            'number' => 3,
        ));
        return is_array($admins) && count($admins) <= 1;
    }

    private function get_preferences(int $user_id): array {
        $stored = get_user_meta($user_id, 'yoy_preferences', true);
        if (is_array($stored) && !empty($stored)) {
            return $stored;
        }

        return array(
            'default_generator' => 'video',
            'korean_context'    => true,
            'auto_save_works'   => true,
            'theme'             => 'dark',
        );
    }
}
