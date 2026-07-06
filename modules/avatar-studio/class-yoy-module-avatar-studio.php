<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Avatar_Studio extends YooY_Module_Base {

    private YooY_Avatar_API_Router $router;
    private YooY_Avatar_Generator $generator;
    private YooY_Avatar_Settings $settings;
    private YooY_Avatar_Catalog $catalog;
    private YooY_Avatar_Subtitle $subtitle;
    private YooY_Avatar_History $history;
    private YooY_Avatar_Gallery $gallery;
    private YooY_Avatar_Prompt_Reuse $prompt_reuse;

    public function id(): string { return 'avatar-studio'; }
    public function name(): string { return 'Avatar Studio'; }
    public function description(): string { return 'Vidu/HeyGen-inspired AI Avatar Studio with Lip Sync, Expression, Scene, and Subtitle.'; }
    public function version(): string { return '1.0.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->boot_services();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
    }

    public function register_rest_routes(): void {
        $auth = 'is_user_logged_in';
        $pub  = '__return_true';

        $this->register_route('/config', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'config'], 'permission_callback' => $pub]);
        $this->register_route('/generate', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'generate'], 'permission_callback' => $auth]);
        $this->register_route('/generate/options', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'options'], 'permission_callback' => $pub]);

        $this->register_route('/avatars', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'avatars'], 'permission_callback' => $pub]);
        $this->register_route('/voices', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'voices'], 'permission_callback' => $pub]);
        $this->register_route('/expressions', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'expressions'], 'permission_callback' => $pub]);
        $this->register_route('/gestures', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'gestures'], 'permission_callback' => $pub]);
        $this->register_route('/cameras', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'cameras'], 'permission_callback' => $pub]);
        $this->register_route('/emotions', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'emotions'], 'permission_callback' => $pub]);
        $this->register_route('/backgrounds', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'backgrounds'], 'permission_callback' => $pub]);
        $this->register_route('/scenes', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'scenes'], 'permission_callback' => $pub]);
        $this->register_route('/subtitle/preview', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'subtitle_preview'], 'permission_callback' => $auth]);

        $this->register_route('/settings', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_settings'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_settings'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/history', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_history'], 'permission_callback' => $auth]);
        $this->register_route('/history/(?P<id>[a-zA-Z0-9_-]+)', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_history'], 'permission_callback' => $auth]);
        $this->register_route('/gallery', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_gallery'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_gallery'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/gallery/(?P<id>[a-zA-Z0-9_-]+)', ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_gallery'], 'permission_callback' => $auth]);
        $this->register_route('/prompt-reuse', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'prompt_reuse'], 'permission_callback' => $auth]);
        $this->register_route('/router/providers', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'router_providers'], 'permission_callback' => $pub]);
        $this->register_route('/router/status', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'router_status'], 'permission_callback' => $auth]);
    }

    public function enqueue_assets(): void {
        if (!is_singular()) return;
        global $post;
        if (!$post instanceof WP_Post || !has_shortcode($post->post_content, 'yoy_ai_studio')) return;
        $base = YOY_AI_STUDIO_URL . 'assets/modules/avatar-studio/';
        wp_enqueue_style('yoy-avatar-studio', $base . 'avatar-studio.css', ['yoy-ai-studio'], $this->version());
        wp_enqueue_script('yoy-avatar-api', $base . 'avatar-api.js', ['yoy-ai-studio-core'], $this->version(), true);
        wp_enqueue_script('yoy-avatar-studio', $base . 'avatar-studio.js', ['yoy-avatar-api'], $this->version(), true);
    }

    public function config(): WP_REST_Response {
        return $this->success([
            'studio'    => ['name' => 'YooY Avatar Studio', 'version' => $this->version()],
            'tabs'      => ['create', 'scene', 'gallery', 'history', 'settings'],
            'providers' => $this->router->providers(),
            'options'   => $this->generator->options(),
        ]);
    }

    public function generate(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = $this->require_user();
            if ($uid instanceof WP_REST_Response) return $uid;
            $params = array_merge($this->settings->get($uid), $request->get_json_params() ?: []);
            return $this->success($this->generator->generate($uid, $params), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function options(): WP_REST_Response { return $this->success($this->generator->options()); }
    public function avatars(): WP_REST_Response { return $this->success(['avatars' => $this->catalog->avatars()]); }
    public function voices(): WP_REST_Response { return $this->success(['voices' => $this->catalog->voices()]); }
    public function expressions(): WP_REST_Response { return $this->success(['expressions' => $this->catalog->expressions()]); }
    public function gestures(): WP_REST_Response { return $this->success(['gestures' => $this->catalog->gestures()]); }
    public function cameras(): WP_REST_Response { return $this->success(['cameras' => $this->catalog->cameras()]); }
    public function emotions(): WP_REST_Response { return $this->success(['emotions' => $this->catalog->emotions()]); }
    public function backgrounds(): WP_REST_Response { return $this->success(['backgrounds' => $this->catalog->backgrounds()]); }
    public function scenes(): WP_REST_Response { return $this->success(['scenes' => $this->catalog->scenes()]); }

    public function subtitle_preview(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        $params = array_merge($this->settings->get($uid), $request->get_json_params() ?: []);
        return $this->success(['subtitle' => $this->subtitle->generate($params)]);
    }

    public function get_settings(): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        return $this->success(['settings' => $this->settings->get($uid)]);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        return $this->success(['settings' => $this->settings->update($uid, $request->get_json_params() ?: [])]);
    }

    public function list_history(): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        return $this->success(['history' => $this->history->list($uid)]);
    }

    public function get_history(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        $item = $this->history->get($uid, sanitize_text_field($request->get_param('id')));
        return $item ? $this->success(['item' => $item]) : $this->error('Not found.', 404);
    }

    public function list_gallery(): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        return $this->success(['items' => $this->gallery->list($uid)]);
    }

    public function save_gallery(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        return $this->success(['item' => $this->gallery->save($uid, $request->get_json_params() ?: [])], 201);
    }

    public function delete_gallery(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        $ok = $this->gallery->remove($uid, sanitize_text_field($request->get_param('id')));
        return $ok ? $this->success(['deleted' => true]) : $this->error('Not found.', 404);
    }

    public function prompt_reuse(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = $this->require_user();
            if ($uid instanceof WP_REST_Response) return $uid;
            return $this->success(['reuse' => $this->prompt_reuse->remix($uid, $request->get_json_params() ?: [])]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function router_providers(): WP_REST_Response {
        return $this->success(['providers' => $this->router->providers()]);
    }

    public function router_status(WP_REST_Request $request): WP_REST_Response {
        $p = $request->get_json_params() ?: [];
        return $this->success(['status' => $this->router->status(
            sanitize_text_field($p['provider'] ?? 'mock'),
            sanitize_text_field($p['job_id'] ?? '')
        )]);
    }

    private function boot_services(): void {
        $dir = dirname(__FILE__) . '/includes/';
        require_once $dir . 'class-avatar-api-router.php';
        require_once $dir . 'class-avatar-catalog.php';
        require_once $dir . 'class-avatar-settings.php';
        require_once $dir . 'class-avatar-subtitle.php';
        require_once $dir . 'class-avatar-history.php';
        require_once $dir . 'class-avatar-gallery.php';
        require_once $dir . 'class-avatar-prompt-reuse.php';
        require_once $dir . 'class-avatar-generator.php';

        $this->router       = new YooY_Avatar_API_Router();
        $this->catalog      = new YooY_Avatar_Catalog();
        $this->settings     = new YooY_Avatar_Settings();
        $this->subtitle     = new YooY_Avatar_Subtitle();
        $this->history      = new YooY_Avatar_History();
        $this->gallery      = new YooY_Avatar_Gallery();
        $this->prompt_reuse = new YooY_Avatar_Prompt_Reuse($this->history, $this->gallery, $this->catalog);
        $this->generator    = new YooY_Avatar_Generator($this->router, $this->history, $this->gallery, $this->subtitle, $this->catalog);
    }
}
