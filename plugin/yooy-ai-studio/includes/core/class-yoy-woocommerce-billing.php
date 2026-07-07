<?php
if (!defined('ABSPATH')) exit;

/**
 * WooCommerce order → membership upgrade (server-side only).
 */
final class YooY_WooCommerce_Billing {

    private const ORDER_META_APPLIED = '_yoy_plan_applied';
    private const USER_ORDERS_KEY    = 'yoy_billing_orders';

    public static function register(): void {
        if (!self::wc_active()) {
            return;
        }

        $instance = new self();
        add_action('woocommerce_order_status_completed', [$instance, 'handle_order'], 20, 1);
        add_action('woocommerce_payment_complete', [$instance, 'handle_order'], 20, 1);
        add_action('woocommerce_thankyou', [$instance, 'flag_return_url'], 10, 1);
    }

    public static function wc_active(): bool {
        return class_exists('WooCommerce');
    }

    public static function payment_ready(): bool {
        if (!self::wc_active()) {
            return false;
        }
        foreach (YooY_Credits_Plans::merged() as $plan) {
            if ((int) ($plan['product_id'] ?? 0) > 0 || (int) ($plan['yearly_product_id'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    }

    public function handle_order($order_id): void {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return;
        }

        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        if ($order->get_meta(self::ORDER_META_APPLIED)) {
            return;
        }

        if (!in_array($order->get_status(), ['completed', 'processing'], true)) {
            return;
        }

        $user_id = (int) $order->get_user_id();
        if ($user_id <= 0) {
            return;
        }

        $plan = $this->resolve_plan_from_order($order);
        if (!$plan) {
            return;
        }

        $service = new YooY_Credits_Service();
        $current = $service->get_user_plan_id($user_id);
        $target  = (string) ($plan['id'] ?? '');

        if ($target === '' || !YooY_Credits_Plans::get($target)) {
            return;
        }

        $action = YooY_Credits_Plans::compare_action($current, $target);
        if ($action === 'downgrade') {
            return;
        }

        $service->set_user_plan($user_id, $target, true);
        $service->record_billing_order($user_id, $this->order_snapshot($order, $plan));

        $order->update_meta_data(self::ORDER_META_APPLIED, gmdate('c'));
        $order->update_meta_data('_yoy_plan_id', $target);
        $order->save();
    }

    public function flag_return_url($order_id): void {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return;
        }
        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order || !$order->get_meta(self::ORDER_META_APPLIED)) {
            return;
        }
        if (!headers_sent()) {
            // Client polls / refreshes credits after return.
        }
    }

    private function resolve_plan_from_order($order): ?array {
        $best_plan = null;
        $best_rank = -1;
        $order_map = array_flip(YooY_Credits_Plans::tier_order());

        foreach ($order->get_items() as $item) {
            $product_id = (int) $item->get_product_id();
            $variation  = (int) $item->get_variation_id();
            $plan       = YooY_Credits_Plans::plan_for_product($product_id);
            if (!$plan && $variation > 0) {
                $plan = YooY_Credits_Plans::plan_for_product($variation);
            }
            if (!$plan) {
                continue;
            }
            $rank = $order_map[$plan['id']] ?? -1;
            if ($rank > $best_rank) {
                $best_rank = $rank;
                $best_plan = $plan;
            }
        }

        return $best_plan;
    }

    private function order_snapshot($order, array $plan): array {
        return [
            'order_id'    => (int) $order->get_id(),
            'plan_id'     => (string) ($plan['id'] ?? ''),
            'plan_name'   => (string) ($plan['name'] ?? ''),
            'total'       => (float) $order->get_total(),
            'currency'    => (string) $order->get_currency(),
            'status'      => (string) $order->get_status(),
            'created_at'  => $order->get_date_created() ? $order->get_date_created()->date('c') : gmdate('c'),
            'invoice_url' => $order->get_view_order_url(),
        ];
    }
}
