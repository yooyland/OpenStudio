<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Music_Studio extends YooY_Module_Base {

    private YooY_Music_API_Router $router;
    private YooY_Music_Generator $generator;
    private YooY_Music_Settings $settings;
    private YooY_Music_Structure $structure;
    private YooY_Music_Reference $reference;
    private YooY_Music_History $history;
    private YooY_Music_Gallery $gallery;
    private YooY_Music_Prompt_Reuse $prompt_reuse;
    private YooY_Music_Credits $credits;

    public function id(): string { return 'music-studio'; }
    public function name(): string { return 'Music Studio'; }
    public function description(): string { return 'Suno-inspired AI Music Studio with Lyrics, Structure, Reference Song, and Credits.'; }
    public function version(): string { return '2.0.0'; }

    public function run_generate(int $user_id, array $params): array {
        return $this->generator->generate($user_id, $params);
    }

    public function poll_provider_job(int $user_id, string $provider, string $job_id): array {
        return $this->generator->poll_and_finalize($user_id, $provider, $job_id) ?? [];
    }

    public function estimate_credits(int $user_id, array $params): array {
        return $this->generator->estimate($user_id, $params);
    }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->boot_services();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
    }

    public function register_rest_routes(): void {
        $auth = 'is_user_logged_in';
        $pub  = '__return_true';

        $this->register_route('/config', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'config'], 'permission_callback' => $pub,
        ]);
        $this->register_route('/generate', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'generate'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/generate/options', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'generate_options'], 'permission_callback' => $pub,
        ]);
        $this->register_route('/structure', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'structures'], 'permission_callback' => $pub,
        ]);
        $this->register_route('/structure/(?P<id>[a-zA-Z0-9_-]+)/skeleton', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'structure_skeleton'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/reference', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_references'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_reference'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/settings', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_settings'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_settings'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/settings/schema', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'settings_schema'], 'permission_callback' => $pub,
        ]);
        $this->register_route('/advanced', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'advanced'], 'permission_callback' => $pub,
        ]);
        $this->register_route('/history', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_history'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/history/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_history'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/gallery', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_gallery'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_gallery'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/gallery/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_gallery'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/prompt-reuse', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'prompt_reuse'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/credits', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'credits_info'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/credits/estimate', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'credits_estimate'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/router/providers', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'router_providers'], 'permission_callback' => $pub,
        ]);
        $this->register_route('/router/status', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'router_status'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/jobs/(?P<id>[a-zA-Z0-9_-]+)/poll', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'poll_job'], 'permission_callback' => $auth,
        ]);
    }

    public function enqueue_assets(): void {
        if (!is_singular()) return;
        global $post;
        if (!$post instanceof WP_Post || !has_shortcode($post->post_content, 'yoy_ai_studio')) return;
        $base = YOY_AI_STUDIO_URL . 'assets/modules/music-studio/';
        wp_enqueue_style('yoy-music-studio', $base . 'music-studio.css', ['yoy-ai-studio'], $this->version());
        wp_enqueue_script('yoy-music-api', $base . 'music-api.js', ['yoy-ai-studio-core'], $this->version(), true);
        wp_enqueue_script('yoy-music-studio', $base . 'music-studio.js', ['yoy-music-api'], $this->version(), true);
    }

    public function config(): WP_REST_Response {
        return $this->success([
            'studio'    => ['name' => 'YooY Music Studio', 'version' => $this->version()],
            'tabs'      => ['create', 'gallery', 'history', 'advanced', 'settings'],
            'providers' => $this->router->providers(),
            'schema'    => $this->settings->schema(),
            'modes'     => [
                ['id' => 'custom', 'label' => 'Custom Mode'],
                ['id' => 'description', 'label' => 'Simple Mode'],
            ],
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

    public function generate_options(): WP_REST_Response { return $this->success($this->generator->options()); }
    public function structures(): WP_REST_Response { return $this->success(['templates' => $this->structure->templates()]); }

    public function structure_skeleton(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        $lang = sanitize_text_field($request->get_param('language') ?: 'ko');
        try {
            $lyrics = $this->structure->build_lyrics_skeleton(sanitize_text_field($request->get_param('id')), $lang);
            return $this->success(['lyrics' => $lyrics]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function list_references(): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        return $this->success(['references' => $this->reference->list($uid)]);
    }

    public function save_reference(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = $this->require_user();
            if ($uid instanceof WP_REST_Response) return $uid;
            return $this->success(['reference' => $this->reference->save($uid, $request->get_json_params() ?: [])], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
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

    public function settings_schema(): WP_REST_Response { return $this->success($this->settings->schema()); }

    public function advanced(): WP_REST_Response {
        return $this->success(['advanced' => $this->settings->schema()['advanced'] ?? []]);
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

    public function credits_info(): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        return $this->success($this->credits->service()->snapshot($uid));
    }

    public function credits_estimate(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;
        $params = array_merge($this->settings->get($uid), $request->get_json_params() ?: []);
        return $this->success($this->generator->estimate($uid, $params));
    }

    public function router_providers(): WP_REST_Response {
        return $this->success(['providers' => $this->router->providers()]);
    }

    public function router_status(WP_REST_Request $request): WP_REST_Response {
        $p = $request->get_json_params() ?: [];
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) return $uid;

        $status = $this->generator->poll_and_finalize(
            $uid,
            sanitize_text_field($p['provider'] ?? 'mock'),
            sanitize_text_field($p['job_id'] ?? '')
        );
        return $this->success(['status' => $status]);
    }

    public function poll_job(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = $this->require_user();
            if ($uid instanceof WP_REST_Response) return $uid;
            $params = $request->get_json_params() ?: [];
            $status = $this->generator->poll_and_finalize(
                $uid,
                sanitize_text_field($params['provider'] ?? 'mock'),
                sanitize_text_field($request->get_param('id'))
            );
            return $this->success(['job' => $status]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function boot_services(): void {
        $dir = dirname(__FILE__) . '/includes/';
        require_once $dir . 'class-music-api-router.php';
        require_once $dir . 'class-music-structure.php';
        require_once $dir . 'class-music-settings.php';
        require_once $dir . 'class-music-reference.php';
        require_once $dir . 'class-music-history.php';
        require_once $dir . 'class-music-gallery.php';
        require_once $dir . 'class-music-prompt-reuse.php';
        require_once $dir . 'class-music-credits.php';
        require_once $dir . 'class-music-generator.php';

        $this->router       = new YooY_Music_API_Router();
        $this->structure    = new YooY_Music_Structure();
        $this->settings     = new YooY_Music_Settings();
        $this->reference    = new YooY_Music_Reference();
        $this->history      = new YooY_Music_History();
        $this->gallery      = new YooY_Music_Gallery();
        $this->credits      = new YooY_Music_Credits();
        $this->prompt_reuse = new YooY_Music_Prompt_Reuse($this->history, $this->gallery);
        $this->generator    = new YooY_Music_Generator($this->router, $this->history, $this->gallery, $this->structure, $this->credits);
    }
}
