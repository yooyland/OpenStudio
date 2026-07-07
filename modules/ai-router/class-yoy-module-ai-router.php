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
        $providers = class_exists('YooY_Provider_Catalog')
            ? YooY_Provider_Catalog::public_meta()
            : [];

        return $this->success([
            'providers' => $providers,
            'default'   => 'auto',
            'statuses'  => YooY_Job_Status::all(),
        ]);
    }

    public function route(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user();
            if ($user_id instanceof WP_REST_Response) return $user_id;

            $body = $request->get_json_params() ?: [];
            $payload = array_merge($body, [
                'type'     => sanitize_text_field($body['type'] ?? $request->get_param('type') ?: 'image'),
                'prompt'   => sanitize_textarea_field($body['prompt'] ?? $request->get_param('prompt') ?: ''),
                'provider' => sanitize_text_field($body['provider'] ?? $request->get_param('provider') ?: 'auto'),
                'job_id'   => 'job_' . wp_generate_uuid4(),
            ]);

            if (!empty($body['reference_assets']) && is_array($body['reference_assets'])) {
                if (!class_exists('YooY_Reference_Asset_Service')) {
                    require_once YOY_AI_STUDIO_MODULES_DIR . 'reference-assets/includes/class-reference-asset-service.php';
                }
                $payload['reference_assets'] = YooY_Reference_Asset_Service::normalize_payload_list($body['reference_assets']);
                if (!empty($payload['reference_assets'][0]['url'])) {
                    $payload['reference_url'] = esc_url_raw($payload['reference_assets'][0]['url']);
                }
            }

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
            $provider = sanitize_text_field($request->get_param('provider') ?: 'auto');
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
