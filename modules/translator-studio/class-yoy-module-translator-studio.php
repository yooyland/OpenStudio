<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Translator_Studio extends YooY_Module_Base {

    /** @var YooY_Translator_API_Router */
    private $router;

    /** @var YooY_Translator_Service */
    private $service;

    /** @var YooY_Translator_Credits */
    private $credits;

    public function id(): string {
        return 'translator-studio';
    }

    public function name(): string {
        return 'Translator Studio';
    }

    public function description(): string {
        return 'Context-aware text translation with modes, history, and My Works integration.';
    }

    public function version(): string {
        return '1.1.0';
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
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'config'],
            'permission_callback' => $pub,
        ]);
        $this->register_route('/languages', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'languages'],
            'permission_callback' => $pub,
        ]);
        $this->register_route('/modes', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'modes'],
            'permission_callback' => $pub,
        ]);
        $this->register_route('/providers', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'providers'],
            'permission_callback' => $pub,
        ]);
        $this->register_route('/translate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'translate'],
            'permission_callback' => $auth,
        ]);
        $this->register_route('/history', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'history'],
            'permission_callback' => $auth,
        ]);
        $this->register_route('/history/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'history_item'],
            'permission_callback' => $auth,
        ]);
        $this->register_route('/history/(?P<id>[a-zA-Z0-9_-]+)/reopen', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'reopen'],
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
        $base = YOY_AI_STUDIO_URL . 'assets/modules/translator-studio/';
        $dir  = YOY_AI_STUDIO_DIR . 'assets/modules/translator-studio/';
        $ver  = defined('YOY_AI_STUDIO_VERSION') ? YOY_AI_STUDIO_VERSION : $this->version();

        $css = $dir . 'translator-studio.css';
        $api = $dir . 'translator-api.js';
        $js  = $dir . 'translator-studio.js';

        wp_enqueue_style(
            'yoy-translator-studio',
            $base . 'translator-studio.css',
            ['yoy-ai-studio'],
            file_exists($css) ? (string) filemtime($css) : $ver
        );
        wp_enqueue_script(
            'yoy-translator-api',
            $base . 'translator-api.js',
            ['yoy-ai-studio-core'],
            file_exists($api) ? (string) filemtime($api) : $ver,
            true
        );
        wp_enqueue_script(
            'yoy-translator-studio',
            $base . 'translator-studio.js',
            ['yoy-translator-api'],
            file_exists($js) ? (string) filemtime($js) : $ver,
            true
        );
    }

    public function config(): WP_REST_Response {
        return $this->success([
            'studio' => [
                'name'    => 'YooY Translator Studio',
                'version' => $this->version(),
                'phase'   => 'gallery-credits',
            ],
            'max_chars' => YooY_Translator_Validator::MAX_CHARS,
            'default_provider' => 'auto',
            'openai_ready' => $this->router->openai_ready(),
            'providers' => $this->router->providers(),
            'modes'     => $this->service->modes(),
            'languages' => $this->service->languages(),
            'features'  => [
                'history'   => true,
                'gallery'   => true,
                'projects'  => true,
                'credits'   => true,
                'tts'       => false,
                'upload'    => false,
            ],
            'credits' => [
                'chars_per_credit' => YooY_Translator_Credits::CHARS_PER_CREDIT,
                'min_credits'      => YooY_Translator_Credits::MIN_CREDITS,
                'mock_cost'        => 0,
            ],
        ]);
    }

    public function languages(): WP_REST_Response {
        return $this->success(['languages' => $this->service->languages()]);
    }

    public function modes(): WP_REST_Response {
        return $this->success(['modes' => $this->service->modes()]);
    }

    public function providers(): WP_REST_Response {
        return $this->success(['providers' => $this->router->providers()]);
    }

    public function translate(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = $this->require_user();
            if ($uid instanceof WP_REST_Response) {
                return $uid;
            }
            $params = $request->get_json_params();
            if (!is_array($params)) {
                $params = [];
            }
            $result = $this->service->translate((int) $uid, $params);
            return $this->success($result, 200);
        } catch (YooY_Translator_Exception $e) {
            return $this->error($e->to_rest_detail(), $e->http_status());
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    public function history(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) {
            return $uid;
        }
        $limit = (int) ($request->get_param('limit') ?? 50);
        return $this->success([
            'items' => $this->service->history((int) $uid, max(1, min(100, $limit))),
        ]);
    }

    public function history_item(WP_REST_Request $request): WP_REST_Response {
        $uid = $this->require_user();
        if ($uid instanceof WP_REST_Response) {
            return $uid;
        }
        $id = sanitize_text_field((string) $request->get_param('id'));
        $payload = $this->service->reopen((int) $uid, $id);
        if (!$payload) {
            return $this->error('History item not found.', 404);
        }
        return $this->success(['item' => $payload]);
    }

    public function reopen(WP_REST_Request $request): WP_REST_Response {
        return $this->history_item($request);
    }

    private function boot_services(): void {
        $dir = dirname(__FILE__) . '/includes/';
        require_once $dir . 'class-translator-validator.php';
        require_once $dir . 'class-translator-credits.php';
        require_once $dir . 'class-translator-api-router.php';
        require_once $dir . 'class-translator-gallery.php';
        require_once $dir . 'class-translator-service.php';

        $this->router  = new YooY_Translator_API_Router();
        $this->credits = new YooY_Translator_Credits();
        $this->service = new YooY_Translator_Service($this->router, $this->credits);
    }
}
