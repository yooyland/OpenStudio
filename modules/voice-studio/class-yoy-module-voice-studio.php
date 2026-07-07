<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Voice_Studio extends YooY_Module_Base {

    private YooY_Voice_API_Router $router;
    private YooY_Voice_Generator $generator;
    private YooY_Voice_Clone $clone;
    private YooY_Voice_Settings $settings;
    private YooY_Voice_Catalog $catalog;
    private YooY_Voice_Advanced $advanced;
    private YooY_Voice_Pause $pause;
    private YooY_Voice_History $history;
    private YooY_Voice_Gallery $gallery;

    public function id(): string { return 'voice-studio'; }
    public function name(): string { return 'Voice Studio'; }
    public function description(): string { return 'ElevenLabs-inspired TTS with Voice Clone, Emotion, Speed, Pitch, and Pause.'; }
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
        $this->register_route('/speak', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'speak'], 'permission_callback' => $auth]);
        $this->register_route('/clone', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'clone_voice'], 'permission_callback' => $auth]);
        $this->register_route('/options', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'options'], 'permission_callback' => $pub]);
        $this->register_route('/voices', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'voices'], 'permission_callback' => $pub]);
        $this->register_route('/emotions', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'emotions'], 'permission_callback' => $pub]);
        $this->register_route('/languages', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'languages'], 'permission_callback' => $pub]);
        $this->register_route('/advanced', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'advanced'], 'permission_callback' => $pub]);
        $this->register_route('/pause/insert', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'insert_pause'], 'permission_callback' => $auth]);
        $this->register_route('/settings', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_settings'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_settings'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/history', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_history'], 'permission_callback' => $auth]);
        $this->register_route('/gallery', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_gallery'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_gallery'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/gallery/(?P<id>[a-zA-Z0-9_-]+)', ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_gallery'], 'permission_callback' => $auth]);
        $this->register_route('/router/providers', ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'router_providers'], 'permission_callback' => $pub]);
        $this->register_route('/router/status', ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'router_status'], 'permission_callback' => $auth]);
    }

    public function enqueue_assets(): void {
        if (!is_singular()) return;
        global $post;
        if (!$post instanceof WP_Post || !has_shortcode($post->post_content, 'yoy_ai_studio')) return;
        $base = YOY_AI_STUDIO_URL . 'assets/modules/voice-studio/';
        wp_enqueue_style('yoy-voice-studio', $base . 'voice-studio.css', ['yoy-ai-studio'], $this->version());
        wp_enqueue_script('yoy-voice-api', $base . 'voice-api.js', ['yoy-ai-studio-core'], $this->version(), true);
        wp_enqueue_script('yoy-voice-studio', $base . 'voice-studio.js', ['yoy-voice-api', 'yoy-reference-assets-panel'], $this->version(), true);
    }

    public function config(): WP_REST_Response {
        return $this->success([
            'studio'    => ['name' => 'YooY Voice Studio', 'version' => $this->version()],
            'tabs'      => ['tts', 'clone', 'gallery', 'history', 'advanced'],
            'providers' => $this->router->providers(),
        ]);
    }

    public function speak(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = $this->require_user();
            if ($uid instanceof WP_REST_Response) return $uid;
            $params = array_merge($this->settings->get($uid), $request->get_json_params() ?: []);
            return $this->success($this->generator->speak($uid, $params), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function clone_voice(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = $this->require_user();
            if ($uid instanceof WP_REST_Response) return $uid;
            return $this->success($this->clone->clone($uid, $request->get_json_params() ?: []), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function options(WP_REST_Request $request): WP_REST_Response {
        $uid = get_current_user_id();
        return $this->success($this->generator->options($this->catalog, $uid));
    }

    public function voices(): WP_REST_Response {
        $uid = get_current_user_id();
        $voices = $this->catalog->voices();
        if ($uid) $voices = array_merge($voices, $this->catalog->cloned_voices($uid));
        return $this->success(['voices' => $voices]);
    }

    public function emotions(): WP_REST_Response { return $this->success(['emotions' => $this->catalog->emotions()]); }
    public function languages(): WP_REST_Response { return $this->success(['languages' => $this->catalog->languages()]); }
    public function advanced(): WP_REST_Response { return $this->success(['advanced' => $this->advanced->schema()]); }

    public function insert_pause(WP_REST_Request $request): WP_REST_Response {
        $p = $request->get_json_params() ?: [];
        $text = $this->pause->insert_pause(
            sanitize_textarea_field($p['text'] ?? ''),
            (float) ($p['seconds'] ?? 0.5),
            (int) ($p['position'] ?? -1)
        );
        return $this->success(['text' => $text]);
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
        require_once $dir . 'class-voice-api-router.php';
        require_once $dir . 'class-voice-catalog.php';
        require_once $dir . 'class-voice-pause.php';
        require_once $dir . 'class-voice-advanced.php';
        require_once $dir . 'class-voice-settings.php';
        require_once $dir . 'class-voice-history.php';
        require_once $dir . 'class-voice-gallery.php';
        require_once $dir . 'class-voice-clone.php';
        require_once $dir . 'class-voice-generator.php';

        $this->router    = new YooY_Voice_API_Router();
        $this->catalog   = new YooY_Voice_Catalog();
        $this->settings  = new YooY_Voice_Settings();
        $this->advanced  = new YooY_Voice_Advanced();
        $this->pause     = new YooY_Voice_Pause();
        $this->history   = new YooY_Voice_History();
        $this->gallery   = new YooY_Voice_Gallery();
        $this->clone     = new YooY_Voice_Clone($this->router, $this->catalog);
        $this->generator = new YooY_Voice_Generator($this->router, $this->history, $this->gallery, $this->pause, $this->advanced);
    }
}
