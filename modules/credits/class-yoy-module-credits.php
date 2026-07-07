<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Credits extends YooY_Module_Base {

    private YooY_Credits_Service $service;

    public function id(): string { return 'credits'; }
    public function name(): string { return 'Credits'; }
    public function description(): string { return 'Credit balance, ledger, and plan management.'; }
    public function version(): string { return '2.1.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->service = new YooY_Credits_Service();
    }

    public function register_rest_routes(): void {
        $auth = 'is_user_logged_in';

        $this->register_route('/balance', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'balance'],
            'permission_callback' => $auth,
        ]);

        $this->register_route('/overview', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'overview'],
            'permission_callback' => $auth,
        ]);

        $this->register_route('/transactions', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'transactions'],
            'permission_callback' => $auth,
        ]);

        $this->register_route('/plans', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'plans'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function balance(): WP_REST_Response {
        $user_id = $this->current_user_id();
        return $this->success($this->service->overview($user_id));
    }

    public function overview(): WP_REST_Response {
        $user_id = $this->current_user_id();
        $plans   = class_exists('YooY_Credits_Plans') ? YooY_Credits_Plans::merged() : [];
        $current = $this->service->get_user_plan_id($user_id);

        foreach ($plans as &$plan) {
            $plan['is_current']   = ($plan['id'] ?? '') === $current;
            $plan['action']       = class_exists('YooY_Credits_Plans')
                ? YooY_Credits_Plans::compare_action($current, (string) ($plan['id'] ?? ''))
                : 'upgrade';
            $plan['button_label'] = $this->button_label($plan);
        }
        unset($plan);

        return $this->success([
            'account'      => $this->service->overview($user_id),
            'plans'        => $plans,
            'transactions' => $this->service->ledger($user_id),
            'billing'      => array_merge(
                class_exists('YooY_Credits_Plans') ? YooY_Credits_Plans::billing_config() : [],
                $this->service->billing_overview($user_id)
            ),
            'is_admin'     => current_user_can('manage_options'),
        ]);
    }

    public function transactions(): WP_REST_Response {
        $user_id = $this->current_user_id();
        return $this->success(['transactions' => $this->service->ledger($user_id)]);
    }

    public function plans(): WP_REST_Response {
        $plans = class_exists('YooY_Credits_Plans') ? YooY_Credits_Plans::merged() : [];
        $billing = class_exists('YooY_Credits_Plans') ? YooY_Credits_Plans::billing_config() : [];
        return $this->success(['plans' => $plans, 'billing' => $billing]);
    }

    private function button_label(array $plan): string {
        $action = $plan['action'] ?? 'upgrade';
        if ($action === 'current') {
            return 'Current Plan';
        }
        if ($action === 'downgrade') {
            return 'Downgrade';
        }
        if (($plan['id'] ?? '') === 'free') {
            return 'Free Plan';
        }
        return 'Upgrade';
    }
}
