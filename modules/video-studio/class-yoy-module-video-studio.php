<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Video_Studio extends YooY_Module_Base {

    private YooY_Video_API_Router $router;
    private YooY_Video_Generator $generator;
    private YooY_Video_Canvas $canvas;
    private YooY_Video_Templates $templates;
    private YooY_Video_Advanced $advanced;
    private YooY_Video_Gallery $gallery;
    private YooY_Video_History $history;
    private YooY_Video_Prompt_Reuse $prompt_reuse;
    private YooY_Video_Settings $settings;
    private YooY_Video_Storyboard $storyboard;

    public function id(): string { return 'video-studio'; }
    public function name(): string { return 'Video Studio'; }
    public function description(): string { return 'Runway/Topview-inspired AI Video Studio with Generator, Canvas, Templates, Storyboard, and API Router.'; }
    public function version(): string { return '2.0.0'; }

    public function run_generate(int $user_id, array $params): array {
        return $this->generator->generate($user_id, $params);
    }

    public function poll_job(int $user_id, string $provider, string $job_id): array {
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
        $public = '__return_true';

        $this->register_route('/config', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'config'], 'permission_callback' => $public,
        ]);

        // Generator
        $this->register_route('/generate', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'generate'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/generate/options', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'generate_options'], 'permission_callback' => $public,
        ]);

        // Canvas
        $this->register_route('/canvas', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_canvas'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_canvas'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/canvas/scene', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'add_scene'], 'permission_callback' => $auth,
        ]);

        // Templates
        $this->register_route('/templates', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_templates'], 'permission_callback' => $public,
        ]);
        $this->register_route('/templates/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_template'], 'permission_callback' => $public,
        ]);
        $this->register_route('/templates/(?P<id>[a-zA-Z0-9_-]+)/apply', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'apply_template'], 'permission_callback' => $auth,
        ]);

        // Advanced
        $this->register_route('/advanced', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'advanced_options'], 'permission_callback' => $public,
        ]);
        $this->register_route('/advanced/apply', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'advanced_apply'], 'permission_callback' => $auth,
        ]);

        // Gallery
        $this->register_route('/gallery', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_gallery'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_gallery'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/gallery/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_gallery'], 'permission_callback' => $auth,
        ]);

        // History
        $this->register_route('/history', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_history'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/history/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_history'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/history/clear', [
            'methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'clear_history'], 'permission_callback' => $auth,
        ]);

        // Prompt Reuse
        $this->register_route('/prompt-reuse', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'prompt_reuse'], 'permission_callback' => $auth,
        ]);

        // Settings
        $this->register_route('/settings', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_settings'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_settings'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/settings/schema', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'settings_schema'], 'permission_callback' => $public,
        ]);

        // Storyboard
        $this->register_route('/storyboard', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_storyboard'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_storyboard'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/storyboard/frame', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'add_storyboard_frame'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/storyboard/generate', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'generate_from_storyboard'], 'permission_callback' => $auth,
        ]);

        // API Router
        $this->register_route('/router/providers', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'router_providers'], 'permission_callback' => $public,
        ]);
        $this->register_route('/router/status', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'router_status'], 'permission_callback' => $auth,
        ]);

        $this->register_route('/credits', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'credits_balance'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/credits/estimate', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'credits_estimate'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/jobs/(?P<id>[a-zA-Z0-9_-]+)/poll', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'poll_job'], 'permission_callback' => $auth,
        ]);
    }

    public function enqueue_assets(): void {
        if (!is_singular()) return;
        global $post;
        if (!$post instanceof WP_Post || !has_shortcode($post->post_content, 'yoy_ai_studio')) return;

        $base = YOY_AI_STUDIO_URL . 'assets/modules/video-studio/';
        wp_enqueue_style('yoy-video-studio', $base . 'video-studio.css', ['yoy-ai-studio'], $this->version());
        wp_enqueue_script('yoy-video-api', $base . 'video-api.js', ['yoy-ai-studio-core'], $this->version(), true);
        wp_enqueue_script('yoy-video-studio', $base . 'video-studio.js', ['yoy-video-api'], $this->version(), true);
    }

    public function config(): WP_REST_Response {
        return $this->success([
            'studio'   => ['name' => 'YooY Video Studio', 'version' => $this->version()],
            'tabs'     => ['generator', 'canvas', 'templates', 'advanced', 'gallery', 'history', 'storyboard', 'settings'],
            'providers'=> $this->router->providers(),
            'settings' => $this->settings->schema(),
        ]);
    }

    public function generate(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            $params  = array_merge($this->settings->get($user_id), $request->get_json_params() ?: []);
            $result  = $this->generator->generate($user_id, $params);
            return $this->success($result, 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function generate_options(): WP_REST_Response {
        return $this->success($this->generator->options());
    }

    public function get_canvas(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['canvas' => $this->canvas->get($user_id)]);
    }

    public function save_canvas(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['canvas' => $this->canvas->save($user_id, $request->get_json_params() ?: [])]);
    }

    public function add_scene(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['canvas' => $this->canvas->add_scene($user_id, $request->get_json_params() ?: [])]);
    }

    public function list_templates(WP_REST_Request $request): WP_REST_Response {
        return $this->success([
            'categories' => $this->templates->categories(),
            'templates'  => $this->templates->list(['category' => $request->get_param('category')]),
        ]);
    }

    public function get_template(WP_REST_Request $request): WP_REST_Response {
        $template = $this->templates->get(sanitize_text_field($request->get_param('id')));
        if (!$template) return $this->error('Template not found.', 404);
        return $this->success(['template' => $template]);
    }

    public function apply_template(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            $applied = $this->templates->apply(sanitize_text_field($request->get_param('id')));
            return $this->success(['applied' => $applied]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function advanced_options(): WP_REST_Response {
        return $this->success([
            'options' => $this->advanced->options(),
            'presets' => $this->advanced->presets(),
        ]);
    }

    public function advanced_apply(WP_REST_Request $request): WP_REST_Response {
        return $this->success(['advanced' => $this->advanced->apply($request->get_json_params() ?: [])]);
    }

    public function list_gallery(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['items' => $this->gallery->list($user_id)]);
    }

    public function save_gallery(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['item' => $this->gallery->save($user_id, $request->get_json_params() ?: [])], 201);
    }

    public function delete_gallery(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        $ok = $this->gallery->remove($user_id, sanitize_text_field($request->get_param('id')));
        return $ok ? $this->success(['deleted' => true]) : $this->error('Item not found.', 404);
    }

    public function list_history(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['history' => $this->history->list($user_id)]);
    }

    public function get_history(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        $item = $this->history->get($user_id, sanitize_text_field($request->get_param('id')));
        if (!$item) return $this->error('History item not found.', 404);
        return $this->success(['item' => $item]);
    }

    public function clear_history(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        $this->history->clear($user_id);
        return $this->success(['cleared' => true]);
    }

    public function prompt_reuse(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            $payload = $this->prompt_reuse->remix($user_id, $request->get_json_params() ?: []);
            return $this->success(['reuse' => $payload]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function get_settings(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['settings' => $this->settings->get($user_id)]);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['settings' => $this->settings->update($user_id, $request->get_json_params() ?: [])]);
    }

    public function settings_schema(): WP_REST_Response {
        return $this->success($this->settings->schema());
    }

    public function get_storyboard(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['storyboard' => $this->storyboard->get($user_id)]);
    }

    public function save_storyboard(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['storyboard' => $this->storyboard->save($user_id, $request->get_json_params() ?: [])]);
    }

    public function add_storyboard_frame(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['storyboard' => $this->storyboard->add_frame($user_id, $request->get_json_params() ?: [])]);
    }

    public function generate_from_storyboard(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            $payload = array_merge(
                $this->settings->get($user_id),
                $this->storyboard->to_generate_payload($user_id),
                $request->get_json_params() ?: []
            );
            $result = $this->generator->generate($user_id, $payload);
            return $this->success($result, 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function router_providers(): WP_REST_Response {
        return $this->success(['providers' => $this->router->providers()]);
    }

    public function router_status(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: [];
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;

        $status = $this->generator->poll_and_finalize(
            $user_id,
            sanitize_text_field($params['provider'] ?? 'mock'),
            sanitize_text_field($params['job_id'] ?? '')
        );
        return $this->success(['status' => $status]);
    }

    public function credits_balance(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        $credits = new YooY_Video_Credits();
        return $this->success($credits->service()->snapshot($user_id));
    }

    public function credits_estimate(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success($this->generator->estimate($user_id, $request->get_json_params() ?: []));
    }

    public function poll_job(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            $params = $request->get_json_params() ?: [];
            $status = $this->generator->poll_and_finalize(
                $user_id,
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
        require_once $dir . 'class-video-credits.php';
        require_once $dir . 'class-video-api-router.php';
        require_once $dir . 'class-video-history.php';
        require_once $dir . 'class-video-gallery.php';
        require_once $dir . 'class-video-templates.php';
        require_once $dir . 'class-video-canvas.php';
        require_once $dir . 'class-video-advanced.php';
        require_once $dir . 'class-video-settings.php';
        require_once $dir . 'class-video-storyboard.php';
        require_once $dir . 'class-video-prompt-reuse.php';
        require_once $dir . 'class-video-generator.php';

        $this->router       = new YooY_Video_API_Router();
        $this->history      = new YooY_Video_History();
        $this->gallery      = new YooY_Video_Gallery();
        $this->templates    = new YooY_Video_Templates();
        $this->canvas       = new YooY_Video_Canvas();
        $this->advanced     = new YooY_Video_Advanced();
        $this->settings     = new YooY_Video_Settings();
        $this->storyboard   = new YooY_Video_Storyboard();
        $this->prompt_reuse = new YooY_Video_Prompt_Reuse($this->history, $this->templates, $this->gallery);
        $this->generator    = new YooY_Video_Generator($this->router, $this->history, $this->gallery);
    }
}
