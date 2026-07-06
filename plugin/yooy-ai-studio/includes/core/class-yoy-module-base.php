<?php
if (!defined('ABSPATH')) exit;

abstract class YooY_Module_Base implements YooY_Module_Interface {

    protected YooY_Core_Engine $core;

    public function init(YooY_Core_Engine $core): void {
        $this->core = $core;
    }

    public function get_config(): array {
        return [
            'id'          => $this->id(),
            'name'        => $this->name(),
            'description' => $this->description(),
            'version'     => $this->version(),
            'routes'      => $this->rest_namespace() . '/' . $this->id(),
        ];
    }

    protected function rest_namespace(): string {
        return 'yoy-ai-studio/v1';
    }

    protected function register_route(string $route, array $args): void {
        register_rest_route($this->rest_namespace(), '/' . $this->id() . $route, $args);
    }

    protected function success($data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'module'  => $this->id(),
            'data'    => $data,
        ], $status);
    }

    protected function error(string $message, int $status = 400): WP_REST_Response {
        return new WP_REST_Response([
            'success' => false,
            'module'  => $this->id(),
            'error'   => $message,
        ], $status);
    }

    protected function current_user_id(): int {
        return get_current_user_id();
    }

    protected function require_user(): int|WP_REST_Response {
        $user_id = $this->current_user_id();
        if ($user_id === 0) {
            return $this->error('Login required.', 401);
        }
        return $user_id;
    }
}
