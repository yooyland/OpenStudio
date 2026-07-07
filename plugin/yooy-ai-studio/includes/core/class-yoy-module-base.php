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

    protected function error($message_or_detail, int $status = 400): WP_REST_Response {
        if (is_array($message_or_detail)) {
            $body = class_exists('YooY_Rest_Error')
                ? YooY_Rest_Error::format($message_or_detail)
                : array_merge(['success' => false, 'module' => $this->id()], $message_or_detail);
            if (!isset($body['module'])) {
                $body['module'] = $this->id();
            }
            return new WP_REST_Response($body, $status);
        }

        $body = class_exists('YooY_Rest_Error')
            ? YooY_Rest_Error::format([
                'stage'   => $status === 401 ? 'authentication' : 'request_failed',
                'code'    => $status === 401 ? 'login_required' : 'error',
                'message' => (string) $message_or_detail,
                'reason'  => (string) $message_or_detail,
                'module'  => $this->id(),
            ])
            : [
                'success' => false,
                'module'  => $this->id(),
                'error'   => (string) $message_or_detail,
            ];

        return new WP_REST_Response($body, $status);
    }

    protected function from_exception(Exception $e): WP_REST_Response {
        if ($e instanceof YooY_Generation_Exception) {
            $body = $e->to_detail();
            $body['module'] = $this->id();
            return new WP_REST_Response($body, 400);
        }

        return $this->error([
            'stage'   => 'server_error',
            'code'    => 'exception',
            'message' => $e->getMessage(),
            'reason'  => $e->getMessage(),
            'debug'   => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ]);
    }

    protected function current_user_id(): int {
        return get_current_user_id();
    }

    protected function require_user() {
        $user_id = $this->current_user_id();
        if ($user_id === 0) {
            return $this->error([
                'stage'   => 'authentication',
                'code'    => 'login_required',
                'message' => 'Login required.',
                'reason'  => 'not_logged_in',
            ], 401);
        }
        return $user_id;
    }
}
