<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-ai-router-dispatcher.php';

final class YooY_Module_AI_Router extends YooY_Module_Base {

    private YooY_AI_Router_Dispatcher $dispatcher;
    private YooY_Credits_Service $credits;

    public function id(): string { return 'ai-router'; }
    public function name(): string { return 'AI Router'; }
    public function description(): string { return 'Unified provider routing with normalized job lifecycle.'; }
    public function version(): string { return '2.0.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->dispatcher = new YooY_AI_Router_Dispatcher($core);
        $this->credits    = new YooY_Credits_Service();
    }

    public function register_rest_routes(): void {
        $this->register_route('/providers', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'providers'],
            'permission_callback' => '__return_true',
        ]);

        $this->register_route('/route', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'route'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/jobs/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'job_status'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/estimate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'estimate'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    public function providers(WP_REST_Request $request): WP_REST_Response {
        $providers = [];
        if (is_dir(YOY_AI_STUDIO_PROVIDERS_DIR)) {
            foreach (glob(YOY_AI_STUDIO_PROVIDERS_DIR . '*/provider.php') as $file) {
                $meta = include $file;
                if (is_array($meta)) $providers[] = $meta;
            }
        }

        return $this->success([
            'providers' => $providers,
            'default'   => 'mock',
            'statuses'  => YooY_Job_Status::all(),
        ]);
    }

    public function route(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;

            $payload = [
                'type'     => sanitize_text_field($request->get_param('type') ?: 'image'),
                'prompt'   => sanitize_textarea_field($request->get_param('prompt') ?: ''),
                'provider' => sanitize_text_field($request->get_param('provider') ?: 'mock'),
                'job_id'   => 'job_' . wp_generate_uuid4(),
            ];

            if ($payload['prompt'] === '') {
                return $this->error('Prompt is required.');
            }

            $result = $this->dispatcher->dispatch($user_id, $payload);
            return $this->success($result, ($result['status'] ?? '') === YooY_Job_Status::COMPLETED ? 200 : 202);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function job_status(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;

            $type     = sanitize_text_field($request->get_param('type') ?: 'image');
            $provider = sanitize_text_field($request->get_param('provider') ?: 'mock');
            $job_id   = sanitize_text_field($request->get_param('id'));

            $result = $this->dispatcher->status($user_id, $type, $provider, $job_id);
            return $this->success($result);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function estimate(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) return $user_id;

        $type = sanitize_text_field($request->get_param('type') ?: 'image');
        $body = $request->get_json_params() ?: [];

        if ($type === 'image') {
            $module = $this->core->module('image-studio');
            if ($module instanceof YooY_Module_Image_Studio) {
                return $this->success($module->estimate_credits($user_id, $body));
            }
        }

        $costs = ['video' => 50, 'image' => 10, 'music' => 20, 'voice' => 15, 'avatar' => 30, 'writing' => 5];
        $cost  = $costs[$type] ?? 10;

        return $this->success(array_merge($this->credits->snapshot($user_id), [
            'estimate'   => $cost,
            'can_afford' => $this->credits->can_afford($user_id, $cost),
        ]));
    }
}
