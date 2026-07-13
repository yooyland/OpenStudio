<?php
if (!defined('ABSPATH')) exit;

final class YooY_Credits_Service {

    private const BALANCE_KEY  = 'yoy_credits_balance';
    private const LEDGER_KEY   = 'yoy_credits_ledger';
    private const PLAN_KEY     = 'yoy_credits_plan';
    private const RENEWAL_KEY  = 'yoy_credits_renewal_at';
    private const BILLING_KEY  = 'yoy_billing_orders';

    public function balance(int $user_id): int {
        $balance = (int) get_user_meta($user_id, self::BALANCE_KEY, true);
        if ($balance === 0 && !metadata_exists('user', $user_id, self::BALANCE_KEY)) {
            $plan    = $this->get_user_plan($user_id);
            $balance = $this->is_unlimited($user_id) ? 999999 : (int) ($plan['credits'] ?? 100);
            update_user_meta($user_id, self::BALANCE_KEY, $balance);
        }
        return $balance;
    }

    public function grant_welcome_bonus(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        $flag = get_user_meta($user_id, 'yoy_welcome_bonus_granted', true);
        if ($flag === '1') {
            return;
        }

        $plan_credits = 100;
        if (class_exists('YooY_Credits_Plans')) {
            $free = YooY_Credits_Plans::get('free');
            if (is_array($free)) {
                $plan_credits = (int) ($free['credits'] ?? 100);
            }
        }

        update_user_meta($user_id, self::PLAN_KEY, 'free');
        update_user_meta($user_id, self::BALANCE_KEY, $plan_credits);
        update_user_meta($user_id, self::RENEWAL_KEY, gmdate('c', strtotime('+30 days')));
        update_user_meta($user_id, 'yoy_welcome_bonus_granted', '1');

        $this->append_ledger($user_id, [
            'id'            => 'tx_welcome_' . wp_generate_uuid4(),
            'type'          => 'grant',
            'amount'        => $plan_credits,
            'label'         => 'Welcome bonus',
            'module'        => 'credits',
            'studio'        => 'credits',
            'provider'      => '',
            'status'        => 'completed',
            'balance_after' => $plan_credits,
            'created_at'    => gmdate('c'),
        ]);
    }

    public function is_unlimited(int $user_id): bool {
        return user_can($user_id, 'manage_options');
    }

    public function get_user_plan_id(int $user_id): string {
        if ($this->is_unlimited($user_id)) {
            return 'business';
        }
        $plan = sanitize_text_field(get_user_meta($user_id, self::PLAN_KEY, true));
        if ($plan === '') {
            return 'free';
        }
        return class_exists('YooY_Credits_Plans') && YooY_Credits_Plans::get($plan) ? $plan : 'free';
    }

    public function get_user_plan(int $user_id): array {
        $id = $this->get_user_plan_id($user_id);
        if (class_exists('YooY_Credits_Plans')) {
            $plan = YooY_Credits_Plans::get($id);
            if ($plan) {
                return $plan;
            }
        }
        return ['id' => 'free', 'name' => 'Free', 'tier' => 'Free', 'credits' => 100];
    }

    public function set_user_plan(int $user_id, string $plan_id, bool $grant_credits = false): array {
        $plan_id = sanitize_text_field($plan_id);
        if (!class_exists('YooY_Credits_Plans') || !YooY_Credits_Plans::get($plan_id)) {
            throw new Exception('Invalid plan.');
        }

        update_user_meta($user_id, self::PLAN_KEY, $plan_id);
        $renewal = gmdate('c', strtotime('+30 days'));
        update_user_meta($user_id, self::RENEWAL_KEY, $renewal);

        if ($grant_credits) {
            $plan = YooY_Credits_Plans::get($plan_id);
            $this->adjust_balance($user_id, (int) ($plan['credits'] ?? 0), 'Plan upgrade: ' . ($plan['name'] ?? $plan_id), 'credits', [
                'studio' => 'credits',
                'status' => 'completed',
            ]);
        }

        return $this->overview($user_id);
    }

    public function renewal_at(int $user_id): string {
        $raw = get_user_meta($user_id, self::RENEWAL_KEY, true);
        return is_string($raw) ? $raw : '';
    }

    public function can_afford(int $user_id, int $cost): bool {
        if ($this->is_unlimited($user_id)) {
            return true;
        }
        return $this->balance($user_id) >= $cost;
    }

    public function deduct(int $user_id, int $cost, string $label = 'Generation', string $module = 'core', array $meta = []): array {
        if ($this->is_unlimited($user_id)) {
            return ['balance' => 999999, 'unlimited' => true, 'deducted' => 0];
        }

        $balance = $this->balance($user_id);
        if ($balance < $cost) {
            throw new Exception('Insufficient credits. Required: ' . $cost . ', Available: ' . $balance);
        }

        $new_balance = $balance - $cost;
        update_user_meta($user_id, self::BALANCE_KEY, $new_balance);
        $this->append_ledger($user_id, [
            'id'            => 'tx_' . wp_generate_uuid4(),
            'type'          => 'deduct',
            'amount'        => -$cost,
            'label'         => $label,
            'module'        => $module,
            'studio'        => sanitize_text_field($meta['studio'] ?? $module),
            'provider'      => sanitize_text_field($meta['provider'] ?? ''),
            'status'        => sanitize_text_field($meta['status'] ?? 'completed'),
            'balance_after' => $new_balance,
            'created_at'    => gmdate('c'),
        ]);

        return ['balance' => $new_balance, 'unlimited' => false, 'deducted' => $cost];
    }

    public function append_ledger(int $user_id, array $entry): void {
        $ledger = get_user_meta($user_id, self::LEDGER_KEY, true);
        $ledger = is_array($ledger) ? $ledger : [];

        if (!isset($entry['balance_after'])) {
            $entry['balance_after'] = $this->balance($user_id);
        }
        if (!isset($entry['studio'])) {
            $entry['studio'] = sanitize_text_field($entry['module'] ?? 'core');
        }
        if (!isset($entry['status'])) {
            $entry['status'] = 'completed';
        }

        array_unshift($ledger, $entry);
        update_user_meta($user_id, self::LEDGER_KEY, array_slice($ledger, 0, 200));
    }

    public function ledger(int $user_id): array {
        $stored = get_user_meta($user_id, self::LEDGER_KEY, true);
        if (is_array($stored) && !empty($stored)) {
            return $this->normalize_ledger($stored, $user_id);
        }

        $balance = $this->balance($user_id);
        $seed = [
            [
                'id'            => 'tx_welcome',
                'type'          => 'grant',
                'amount'        => $balance,
                'label'         => 'Welcome bonus',
                'module'        => 'credits',
                'studio'        => 'credits',
                'provider'      => '',
                'status'        => 'completed',
                'balance_after' => $balance,
                'created_at'    => gmdate('c'),
            ],
        ];
        update_user_meta($user_id, self::LEDGER_KEY, $seed);
        return $seed;
    }

    public function snapshot(int $user_id): array {
        $plan = $this->get_user_plan($user_id);
        return [
            'balance'   => $this->balance($user_id),
            'unlimited' => $this->is_unlimited($user_id),
            'plan'      => $plan['id'] ?? 'free',
            'plan_name' => $plan['name'] ?? 'Free',
            'tier'      => $plan['tier'] ?? 'Free',
            'currency'  => 'KRW',
        ];
    }

    public function overview(int $user_id): array {
        $plan    = $this->get_user_plan($user_id);
        $usage   = $this->monthly_usage($user_id);
        $balance = $this->balance($user_id);
        $renewal = $this->renewal_at($user_id);

        return array_merge($this->snapshot($user_id), [
            'monthly_usage'     => $usage,
            'remaining'         => $this->is_unlimited($user_id) ? 999999 : $balance,
            'renewal_at'        => $renewal,
            'renewal_label'     => $this->format_renewal($renewal),
            'plan_features'     => $plan['features'] ?? [],
            'plan_credits'      => (int) ($plan['credits'] ?? 100),
            'plan_price_krw'    => (int) ($plan['price_krw'] ?? 0),
            'plan_yearly_price_krw' => (int) ($plan['yearly_price_krw'] ?? 0),
        ]);
    }

    public function billing_orders(int $user_id): array {
        $stored = get_user_meta($user_id, self::BILLING_KEY, true);
        return is_array($stored) ? array_slice($stored, 0, 50) : [];
    }

    public function record_billing_order(int $user_id, array $order): void {
        $orders = $this->billing_orders($user_id);
        array_unshift($orders, array_merge($order, [
            'id' => 'bill_' . wp_generate_uuid4(),
            'recorded_at' => gmdate('c'),
        ]));
        update_user_meta($user_id, self::BILLING_KEY, array_slice($orders, 0, 50));

        $this->append_ledger($user_id, [
            'id'            => 'tx_' . wp_generate_uuid4(),
            'type'          => 'purchase',
            'amount'        => 0,
            'label'         => 'Membership: ' . ($order['plan_name'] ?? $order['plan_id'] ?? 'plan'),
            'module'        => 'billing',
            'studio'        => 'billing',
            'provider'      => 'woocommerce',
            'status'        => 'completed',
            'balance_after' => $this->balance($user_id),
            'created_at'    => gmdate('c'),
            'meta'          => [
                'order_id' => (int) ($order['order_id'] ?? 0),
                'total'    => (float) ($order['total'] ?? 0),
            ],
        ]);
    }

    public function billing_overview(int $user_id): array {
        $orders = $this->billing_orders($user_id);
        $purchases = array_values(array_filter($this->ledger($user_id), function ($tx) {
            return ($tx['type'] ?? '') === 'purchase' || ($tx['studio'] ?? '') === 'billing';
        }));

        return [
            'orders'           => $orders,
            'invoices'         => $orders,
            'credit_purchases' => array_slice($purchases, 0, 20),
            'can_cancel'       => false,
            'can_downgrade'    => $this->get_user_plan_id($user_id) !== 'free',
        ];
    }

    public function monthly_usage(int $user_id): array {
        $plan  = $this->get_user_plan($user_id);
        $limit = max((int) ($plan['credits'] ?? 100), (int) get_option('yoy_monthly_credit_limit', 50000));
        $used  = 0;
        $start = gmdate('Y-m-01\T00:00:00\Z');

        foreach ($this->ledger($user_id) as $tx) {
            $amount = (int) ($tx['amount'] ?? 0);
            $at     = $tx['created_at'] ?? '';
            if ($amount < 0 && $at >= $start) {
                $used += abs($amount);
            }
        }

        $remaining_month = max(0, $limit - $used);
        $pct = $limit > 0 ? min(100, (int) round(($used / $limit) * 100)) : 0;

        return [
            'used'      => $used,
            'limit'     => $limit,
            'remaining' => $remaining_month,
            'percent'   => $pct,
        ];
    }

    public function adjust_balance(int $user_id, int $delta, string $label, string $module = 'admin', array $meta = []): array {
        if ($this->is_unlimited($user_id) && $delta < 0) {
            return $this->snapshot($user_id);
        }

        $balance = max(0, $this->balance($user_id) + $delta);
        update_user_meta($user_id, self::BALANCE_KEY, $balance);

        $this->append_ledger($user_id, [
            'id'            => 'tx_' . wp_generate_uuid4(),
            'type'          => $delta >= 0 ? 'grant' : 'deduct',
            'amount'        => $delta,
            'label'         => $label,
            'module'        => $module,
            'studio'        => sanitize_text_field($meta['studio'] ?? $module),
            'provider'      => sanitize_text_field($meta['provider'] ?? ''),
            'status'        => sanitize_text_field($meta['status'] ?? 'completed'),
            'balance_after' => $balance,
            'created_at'    => gmdate('c'),
        ]);

        return $this->snapshot($user_id);
    }

    public function set_balance(int $user_id, int $balance, string $label = 'Admin set balance'): array {
        $current = $this->balance($user_id);
        return $this->adjust_balance($user_id, $balance - $current, $label, 'admin-console');
    }

    private function normalize_ledger(array $ledger, int $user_id): array {
        $running = $this->balance($user_id);
        $out     = [];
        foreach ($ledger as $tx) {
            if (!isset($tx['balance_after'])) {
                $tx['balance_after'] = $running;
            }
            if (!isset($tx['studio'])) {
                $tx['studio'] = $tx['module'] ?? 'core';
            }
            if (!isset($tx['provider'])) {
                $tx['provider'] = '';
            }
            if (!isset($tx['status'])) {
                $tx['status'] = 'completed';
            }
            $out[] = $tx;
            $running = max(0, (int) ($tx['balance_after'] ?? $running) - (int) ($tx['amount'] ?? 0));
        }
        return $out;
    }

    private function format_renewal(string $iso): string {
        if ($iso === '') {
            return '';
        }
        $ts = strtotime($iso);
        return $ts ? date_i18n('Y-m-d', $ts) : $iso;
    }
}
