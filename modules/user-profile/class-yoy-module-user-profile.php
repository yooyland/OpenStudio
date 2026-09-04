<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_User_Profile extends YooY_Module_Base {

    const AVATAR_META = 'yoy_avatar_attachment_id';
    const MAX_AVATAR_BYTES = 2097152; // 2MB

    public function id(): string { return 'user-profile'; }
    public function name(): string { return 'User Profile'; }
    public function description(): string { return 'User identity, preferences, and studio profile.'; }
    public function version(): string { return '1.2.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        add_filter('pre_get_avatar_url', array($this, 'filter_avatar_url'), 10, 3);
    }

    public function register_rest_routes(): void {
        $this->register_route('/me', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'me'),
                'permission_callback' => 'is_user_logged_in',
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array($this, 'update'),
                'permission_callback' => 'is_user_logged_in',
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'delete_account'),
                'permission_callback' => 'is_user_logged_in',
            ),
        ));

        $this->register_route('/me/avatar', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'upload_avatar'),
                'permission_callback' => 'is_user_logged_in',
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array($this, 'remove_avatar'),
                'permission_callback' => 'is_user_logged_in',
            ),
        ));
    }

    /**
     * Prefer local uploaded avatar over Gravatar when set.
     *
     * @param string|null $url
     * @param mixed       $id_or_email
     * @param array       $args
     * @return string|null
     */
    public function filter_avatar_url($url, $id_or_email, $args) {
        $user_id = $this->resolve_user_id($id_or_email);
        if ($user_id <= 0) {
            return $url;
        }
        $custom = $this->custom_avatar_url($user_id, isset($args['size']) ? (int) $args['size'] : 96);
        return $custom !== '' ? $custom : $url;
    }

    public function me(): WP_REST_Response {
        $user = wp_get_current_user();
        if (!$user || !$user->ID) {
            return $this->error('로그인이 필요합니다.', 401);
        }

        return $this->success(array(
            'profile' => $this->profile_payload($user),
        ));
    }

    public function update(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        if (!$user_id) {
            return $this->error('로그인이 필요합니다.', 401);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = array();
        }

        $user_update = array('ID' => $user_id);

        if (array_key_exists('display_name', $params)) {
            $name = trim(sanitize_text_field((string) $params['display_name']));
            if ($name === '') {
                return $this->error('표시 이름을 입력해 주세요.', 400);
            }
            if ($this->str_len($name) > 60) {
                return $this->error('표시 이름은 60자 이내로 입력해 주세요.', 400);
            }
            $user_update['display_name'] = $name;
        }

        if (array_key_exists('first_name', $params)) {
            $user_update['first_name'] = sanitize_text_field((string) $params['first_name']);
            if ($this->str_len($user_update['first_name']) > 60) {
                return $this->error('이름은 60자 이내로 입력해 주세요.', 400);
            }
        }

        if (array_key_exists('last_name', $params)) {
            $user_update['last_name'] = sanitize_text_field((string) $params['last_name']);
            if ($this->str_len($user_update['last_name']) > 60) {
                return $this->error('성은 60자 이내로 입력해 주세요.', 400);
            }
        }

        if (count($user_update) > 1) {
            $result = wp_update_user($user_update);
            if (is_wp_error($result)) {
                return $this->error('프로필을 저장하지 못했습니다. 잠시 후 다시 시도해 주세요.', 400);
            }
        }

        if (array_key_exists('bio', $params)) {
            $bio = sanitize_textarea_field((string) $params['bio']);
            if ($this->str_len($bio) > 500) {
                return $this->error('소개는 500자 이내로 입력해 주세요.', 400);
            }
            update_user_meta($user_id, 'yoy_bio', $bio);
        }

        // Email is intentionally not updated here — no verification flow in product.
        if (array_key_exists('email', $params) && (string) $params['email'] !== '') {
            return $this->error('이메일 변경은 현재 지원하지 않습니다. 지원팀에 문의해 주세요.', 400);
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

    public function upload_avatar(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        if (!$user_id) {
            return $this->error('로그인이 필요합니다.', 401);
        }

        $files = $request->get_file_params();
        if (empty($files['avatar']) || !is_array($files['avatar'])) {
            return $this->error('이미지 파일을 선택해 주세요.', 400);
        }

        $file = $files['avatar'];
        if (!empty($file['error'])) {
            return $this->error('업로드에 실패했습니다. 다른 이미지를 시도해 주세요.', 400);
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 || $size > self::MAX_AVATAR_BYTES) {
            return $this->error('이미지는 2MB 이하여야 합니다.', 400);
        }

        $check = wp_check_filetype_and_ext(
            isset($file['tmp_name']) ? $file['tmp_name'] : '',
            isset($file['name']) ? $file['name'] : ''
        );
        $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $ext = isset($check['ext']) ? strtolower((string) $check['ext']) : '';
        $type = isset($check['type']) ? (string) $check['type'] : '';
        if ($ext === '' || !in_array($ext, $allowed, true) || strpos($type, 'image/') !== 0) {
            return $this->error('JPG, PNG, GIF, WEBP 이미지만 사용할 수 있습니다.', 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload('avatar', 0);
        if (is_wp_error($attachment_id)) {
            return $this->error('이미지를 저장하지 못했습니다.', 400);
        }

        $attachment_id = (int) $attachment_id;
        $post = get_post($attachment_id);
        if (!$post || (int) $post->post_author !== $user_id) {
            // Ensure ownership: reassign if WP set differently under some hosts.
            wp_update_post(array(
                'ID'          => $attachment_id,
                'post_author' => $user_id,
            ));
        }

        $prev = (int) get_user_meta($user_id, self::AVATAR_META, true);
        update_user_meta($user_id, self::AVATAR_META, $attachment_id);
        if ($prev > 0 && $prev !== $attachment_id) {
            $this->maybe_delete_owned_attachment($user_id, $prev);
        }

        $user = get_userdata($user_id);
        return $this->success(array(
            'profile' => $this->profile_payload($user),
            'message' => '프로필 사진이 업데이트되었습니다.',
        ));
    }

    public function remove_avatar(): WP_REST_Response {
        $user_id = $this->current_user_id();
        if (!$user_id) {
            return $this->error('로그인이 필요합니다.', 401);
        }

        $prev = (int) get_user_meta($user_id, self::AVATAR_META, true);
        delete_user_meta($user_id, self::AVATAR_META);
        if ($prev > 0) {
            $this->maybe_delete_owned_attachment($user_id, $prev);
        }

        $user = get_userdata($user_id);
        return $this->success(array(
            'profile' => $this->profile_payload($user),
            'message' => '프로필 사진을 제거했습니다.',
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

        $avatar_id = (int) get_user_meta($user_id, self::AVATAR_META, true);

        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = wp_delete_user($user_id);
        if (!$deleted) {
            return $this->error('계정을 삭제하지 못했습니다. 지원팀에 문의해 주세요.', 500);
        }

        if ($avatar_id > 0) {
            wp_delete_attachment($avatar_id, true);
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
        $user_id = (int) $user->ID;
        $avatar_attachment = (int) get_user_meta($user_id, self::AVATAR_META, true);
        $avatar = $this->custom_avatar_url($user_id, 96);
        $source = 'initials';
        if ($avatar !== '') {
            $source = 'custom';
        } else {
            $gravatar = $this->gravatar_url_without_custom($user_id, 96);
            if ($gravatar !== '') {
                $avatar = $gravatar;
                $source = 'gravatar';
            }
        }

        $registered = '';
        if (!empty($user->user_registered)) {
            $registered = mysql2date('c', $user->user_registered, false);
        }

        return array(
            'id'           => $user_id,
            'display_name' => (string) $user->display_name,
            'first_name'   => (string) $user->first_name,
            'last_name'    => (string) $user->last_name,
            'email'        => (string) $user->user_email,
            'user_login'   => (string) $user->user_login,
            'registered_at'=> is_string($registered) ? $registered : '',
            'avatar'       => $avatar,
            'avatar_source'=> $source,
            'has_custom_avatar' => $avatar_attachment > 0,
            'role'         => user_can($user_id, 'manage_options') ? 'admin' : 'creator',
            'locale'       => get_user_meta($user_id, 'yoy_locale', true) ?: 'ko_KR',
            'bio'          => (string) (get_user_meta($user_id, 'yoy_bio', true) ?: ''),
            'preferences'  => $this->get_preferences($user_id),
            'account'      => array(
                'can_delete'         => !($this->is_sole_administrator($user_id)),
                'email_editable'     => false,
                'email_note'         => '이메일 변경은 인증 절차가 없어 현재 읽기 전용입니다.',
                'password_reset_url' => esc_url_raw(wp_lostpassword_url()),
                'privacy_policy_url' => esc_url_raw((string) get_privacy_policy_url()),
                'logout_url'         => esc_url_raw(wp_logout_url(get_permalink())),
            ),
        );
    }

    private function custom_avatar_url(int $user_id, int $size): string {
        $att = (int) get_user_meta($user_id, self::AVATAR_META, true);
        if ($att <= 0) {
            return '';
        }
        $src = wp_get_attachment_image_url($att, array($size, $size));
        if (!$src) {
            $src = wp_get_attachment_url($att);
        }
        return is_string($src) ? $src : '';
    }

    private function gravatar_url_without_custom(int $user_id, int $size): string {
        remove_filter('pre_get_avatar_url', array($this, 'filter_avatar_url'), 10);
        $url = get_avatar_url($user_id, array('size' => $size));
        add_filter('pre_get_avatar_url', array($this, 'filter_avatar_url'), 10, 3);
        return is_string($url) ? $url : '';
    }

    private function maybe_delete_owned_attachment(int $user_id, int $attachment_id): void {
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            return;
        }
        if ((int) $post->post_author !== $user_id) {
            return;
        }
        wp_delete_attachment($attachment_id, true);
    }

    /**
     * @param mixed $id_or_email
     */
    private function resolve_user_id($id_or_email): int {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }
        if (is_object($id_or_email) && isset($id_or_email->user_id)) {
            return (int) $id_or_email->user_id;
        }
        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? (int) $user->ID : 0;
        }
        return 0;
    }

    private function str_len(string $s): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($s);
        }
        return strlen($s);
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
