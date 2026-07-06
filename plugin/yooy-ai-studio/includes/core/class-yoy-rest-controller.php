<?php
if (!defined('ABSPATH')) exit;

final class YooY_REST_Controller {

    private YooY_Core_Engine $core;

    public function __construct(YooY_Core_Engine $core) {
        $this->core = $core;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('yoy-ai-studio/v1', '/core/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/modules', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'modules'],
            'permission_callback' => '__return_true',
        ]);

        foreach ($this->core->registry()->all() as $module) {
            $module->register_rest_routes();
        }
    }

    public function status(): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $this->core->status(),
        ], 200);
    }

    public function modules(): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $this->core->registry()->configs(),
        ], 200);
    }
}
