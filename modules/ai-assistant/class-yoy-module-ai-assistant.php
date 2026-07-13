<?php
if (!defined('ABSPATH')) exit;

/**
 * AI Assistant module — Central Intelligence entry for all Studios.
 * Reuses Gallery / Projects / Credits / AI Router. No new Store/Core/Router.
 */
final class YooY_Module_AI_Assistant extends YooY_Module_Base {

    /** @var YooY_Assistant_Service */
    private $service;

    public function id(): string {
        return 'ai-assistant';
    }

    public function name(): string {
        return 'AI Assistant';
    }

    public function description(): string {
        return 'Central Intelligence — conversation, recommendations, prompt compose, project context.';
    }

    public function version(): string {
        return '2.1.0';
    }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->service = new YooY_Assistant_Service();
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
    }

    public function register_rest_routes(): void {
        $auth = 'is_user_logged_in';
        $pub  = '__return_true';

        $this->register_route('/config', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'config'],
            'permission_callback' => $pub,
        ]);

        $this->register_route('/recommendations', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'recommendations'],
            'permission_callback' => $pub,
        ]);

        $this->register_route('/context', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'context'],
            'permission_callback' => $auth,
        ]);

        $this->register_route('/chat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'chat'],
            'permission_callback' => $auth,
        ]);

        $this->register_route('/compose', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'compose'],
            'permission_callback' => $auth,
        ]);
    }

    public function enqueue_assets(): void {
        if (!is_singular()) {
            return;
        }
        global $post;
        if (!$post instanceof WP_Post || !has_shortcode($post->post_content, 'yoy_ai_studio')) {
            return;
        }

        $base = YOY_AI_STUDIO_URL . 'assets/modules/ai-assistant/';
        $dir  = YOY_AI_STUDIO_DIR . 'assets/modules/ai-assistant/';
        $ver  = defined('YOY_AI_STUDIO_VERSION') ? YOY_AI_STUDIO_VERSION : $this->version();

        $css = $dir . 'ai-assistant.css';
        if (is_readable($css)) {
            wp_enqueue_style(
                'yoy-ai-assistant-font',
                'https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;600;700&display=swap',
                [],
                null
            );
            wp_enqueue_style(
                'yoy-ai-assistant',
                $base . 'ai-assistant.css',
                ['yoy-ai-studio', 'yoy-ai-assistant-font'],
                $ver . '.' . filemtime($css)
            );
        }

        $api = $dir . 'ai-assistant-api.js';
        if (is_readable($api)) {
            wp_enqueue_script(
                'yoy-ai-assistant-api',
                $base . 'ai-assistant-api.js',
                ['yoy-ai-studio-core'],
                $ver . '.' . filemtime($api),
                true
            );
        }

        $js = $dir . 'ai-assistant.js';
        if (is_readable($js)) {
            wp_enqueue_script(
                'yoy-ai-assistant',
                $base . 'ai-assistant.js',
                ['yoy-ai-assistant-api'],
                $ver . '.' . filemtime($js),
                true
            );
        }
    }

    public function config(): WP_REST_Response {
        return $this->success($this->service()->config());
    }

    public function recommendations(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        $project = sanitize_text_field((string) ($request->get_param('project_id') ?: ''));
        return $this->success($this->service()->recommendations($user_id, $project !== '' ? $project : null));
    }

    public function context(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) {
            return $user;
        }
        $project = sanitize_text_field((string) ($request->get_param('project_id') ?: ''));
        $studio  = sanitize_text_field((string) ($request->get_param('studio') ?: ''));
        return $this->success($this->service()->context(
            (int) $user,
            $project !== '' ? $project : null,
            $studio !== '' ? $studio : null
        ));
    }

    public function chat(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $message = sanitize_textarea_field((string) ($request->get_param('message') ?: ''));
        $project = sanitize_text_field((string) ($request->get_param('project_id') ?: ''));
        $studio  = sanitize_text_field((string) ($request->get_param('studio') ?: ''));
        $history = $request->get_param('history');
        if (!is_array($history)) {
            $history = [];
        }
        $brief = $request->get_param('brief');
        if (!is_array($brief)) {
            $brief = [];
        }

        return $this->success($this->service()->chat(
            (int) $user,
            $message,
            $project !== '' ? $project : null,
            $studio !== '' ? $studio : null,
            $history,
            $brief
        ));
    }

    public function compose(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) {
            return $user;
        }

        $prompt  = sanitize_textarea_field((string) ($request->get_param('prompt') ?: ''));
        $studio  = sanitize_text_field((string) ($request->get_param('studio') ?: ''));
        $project = sanitize_text_field((string) ($request->get_param('project_id') ?: ''));

        return $this->success($this->service()->compose(
            (int) $user,
            $prompt,
            $studio !== '' ? $studio : null,
            $project !== '' ? $project : null
        ));
    }

    private function service(): YooY_Assistant_Service {
        if (!$this->service) {
            $this->service = new YooY_Assistant_Service();
        }
        return $this->service;
    }
}
