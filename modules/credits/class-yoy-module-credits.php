<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Credits extends YooY_Module_Base {

    private YooY_Credits_Service $service;

    public function id(): string { return 'credits'; }
    public function name(): string { return 'Credits'; }
    public function description(): string { return 'Credit balance, ledger, and plan management.'; }
    public function version(): string { return '2.0.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->service = new YooY_Credits_Service();
    }

    public function register_rest_routes(): void {
        $this->register_route('/balance', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'balance'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/transactions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'transactions'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/plans', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'plans'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function balance(): WP_REST_Response {
        $user_id = $this->current_user_id();
        return $this->success($this->service->snapshot($user_id));
    }

    public function transactions(): WP_REST_Response {
        $user_id = $this->current_user_id();
        return $this->success(['transactions' => $this->service->ledger($user_id)]);
    }

    public function plans(): WP_REST_Response {
        return $this->success([
            'plans' => [
                ['id' => 'free', 'name' => 'Free', 'credits' => 100, 'price_krw' => 0],
                ['id' => 'starter', 'name' => 'Starter', 'credits' => 500, 'price_krw' => 9900],
                ['id' => 'creator', 'name' => 'Creator', 'credits' => 2000, 'price_krw' => 29900],
                ['id' => 'pro', 'name' => 'Pro', 'credits' => 5000, 'price_krw' => 59900],
                ['id' => 'business', 'name' => 'Business', 'credits' => 15000, 'price_krw' => 149000],
            ],
        ]);
    }
}
