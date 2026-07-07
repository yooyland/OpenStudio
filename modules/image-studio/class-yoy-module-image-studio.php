<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Image_Studio extends YooY_Module_Base {

    private YooY_Image_API_Router $router;
    private YooY_Image_Generator $generator;
    private YooY_Image_Settings $settings;
    private YooY_Image_History $history;
    private YooY_Image_Gallery $gallery;
    private YooY_Image_Prompt_Reuse $prompt_reuse;
    private YooY_Image_Upload $upload;
    private YooY_Image_Edit $editor;
    private YooY_Image_Credits $credits;

    public function id(): string { return 'image-studio'; }
    public function name(): string { return 'Image Studio'; }
    public function description(): string { return 'Topview/GPT Image-inspired AI Image Studio with full generation and editing pipeline.'; }
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
        $auth   = 'is_user_logged_in';
        $public = '__return_true';

        $this->register_route('/config', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'config'], 'permission_callback' => $public,
        ]);

        // Generate
        $this->register_route('/generate', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'generate'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/generate/options', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'generate_options'], 'permission_callback' => $public,
        ]);

        // Reference Image
        $this->register_route('/reference', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_references'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'upload_reference'], 'permission_callback' => $auth],
        ]);

        // Settings
        $this->register_route('/settings', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_settings'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_settings'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/settings/schema', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'settings_schema'], 'permission_callback' => $public,
        ]);

        // Prompt History
        $this->register_route('/history', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_history'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/history/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_history'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/history/clear', [
            'methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'clear_history'], 'permission_callback' => $auth,
        ]);

        // Edit / Upscale / Inpaint / Outpaint
        $this->register_route('/edit', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'edit'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/upscale', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'upscale'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/inpaint', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'inpaint'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/outpaint', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'outpaint'], 'permission_callback' => $auth,
        ]);

        // Prompt Reuse
        $this->register_route('/prompt-reuse', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'prompt_reuse'], 'permission_callback' => $auth,
        ]);

        // Gallery
        $this->register_route('/gallery', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_gallery'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_gallery'], 'permission_callback' => $auth],
        ]);
        $this->register_route('/gallery/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_gallery'], 'permission_callback' => $auth,
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

        $base = YOY_AI_STUDIO_URL . 'assets/modules/image-studio/';
        wp_enqueue_style('yoy-image-studio', $base . 'image-studio.css', ['yoy-ai-studio'], $this->version());
        wp_enqueue_script('yoy-image-api', $base . 'image-api.js', ['yoy-ai-studio-core'], $this->version(), true);
        wp_enqueue_script('yoy-image-studio-smart-auto', $base . 'image-studio-smart-auto.js', [], $this->version(), true);
        wp_enqueue_script('yoy-image-studio', $base . 'image-studio.js', ['yoy-image-api', 'yoy-image-studio-smart-auto'], $this->version(), true);
    }

    public function config(): WP_REST_Response {
        return $this->success([
            'studio'    => ['name' => 'YooY Image Studio', 'version' => $this->version()],
            'tabs'      => ['generate', 'edit', 'gallery', 'history', 'settings'],
            'providers' => $this->router->providers(),
            'schema'    => $this->settings->schema(),
        ]);
    }

    public function generate(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            $params  = array_merge($this->settings->get($user_id), $request->get_json_params() ?: []);
            return $this->success($this->generator->generate($user_id, $params), 201);
        } catch (Exception $e) {
            return $this->from_exception($e);
        }
    }

    public function generate_options(): WP_REST_Response {
        return $this->success($this->generator->options());
    }

    public function list_references(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;
        return $this->success(['references' => $this->upload->list($user_id)]);
    }

    public function upload_reference(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            $ref = $this->upload->save_reference($user_id, $request->get_json_params() ?: []);
            return $this->success(['reference' => $ref], 201);
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

    public function edit(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            return $this->success($this->editor->edit($user_id, $request->get_json_params() ?: []), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function upscale(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            return $this->success($this->editor->upscale($user_id, $request->get_json_params() ?: []), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function inpaint(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            return $this->success($this->editor->inpaint($user_id, $request->get_json_params() ?: []), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function outpaint(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            return $this->success($this->editor->outpaint($user_id, $request->get_json_params() ?: []), 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function prompt_reuse(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;
            return $this->success(['reuse' => $this->prompt_reuse->remix($user_id, $request->get_json_params() ?: [])]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
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
        return $this->success($this->credits->service()->snapshot($user_id));
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
            return $this->from_exception($e);
        }
    }

    public function router_providers(): WP_REST_Response {
        return $this->success(['providers' => $this->router->providers()]);
    }

    private function boot_services(): void {
        $dir = dirname(__FILE__) . '/includes/';
        require_once $dir . 'class-image-api-router.php';
        require_once $dir . 'class-image-settings.php';
        require_once $dir . 'class-image-history.php';
        require_once $dir . 'class-image-gallery.php';
        require_once $dir . 'class-image-upload.php';
        require_once $dir . 'class-image-prompt-reuse.php';
        require_once $dir . 'class-image-edit.php';
        require_once $dir . 'class-image-credits.php';
        require_once $dir . 'class-image-generator.php';

        $this->router       = new YooY_Image_API_Router();
        $this->credits      = new YooY_Image_Credits();
        $this->history      = new YooY_Image_History();
        $this->gallery      = new YooY_Image_Gallery();
        $this->settings     = new YooY_Image_Settings();
        $this->upload       = new YooY_Image_Upload();
        $this->prompt_reuse = new YooY_Image_Prompt_Reuse($this->history, $this->gallery);
        $this->editor       = new YooY_Image_Edit($this->router, $this->history, $this->gallery, $this->credits);
        $this->generator    = new YooY_Image_Generator($this->router, $this->history, $this->gallery, $this->credits);
    }
}
