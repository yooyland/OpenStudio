<?php
if (!defined('ABSPATH')) exit;

/**
 * Canonical credit tier definitions.
 */
final class YooY_Credits_Plans {

    public static function catalog(): array {
        return [
            'free' => [
                'id'          => 'free',
                'name'        => 'Free',
                'tier'        => 'Free',
                'credits'     => 100,
                'price_krw'   => 0,
                'product_id'  => 0,
                'features'    => [
                    '100 credits',
                    'Basic image generation',
                    'Limited gallery',
                    'Mock / low priority processing',
                    'Community browsing',
                ],
            ],
            'starter' => [
                'id'          => 'starter',
                'name'        => 'Starter',
                'tier'        => 'Starter',
                'credits'     => 500,
                'price_krw'   => 9900,
                'yearly_price_krw' => 99000,
                'product_id'  => 0,
                'yearly_product_id' => 0,
                'features'    => [
                    '500 credits',
                    'Image generation',
                    'Basic music / voice',
                    'Gallery save',
                    'Project management',
                ],
            ],
            'creator' => [
                'id'          => 'creator',
                'name'        => 'Creator',
                'tier'        => 'Creator',
                'credits'     => 2000,
                'price_krw'   => 29900,
                'yearly_price_krw' => 299000,
                'product_id'  => 0,
                'yearly_product_id' => 0,
                'features'    => [
                    '2,000 credits',
                    'Image, music, voice, writing',
                    'Video preview',
                    'Project folders',
                    'Community publish',
                    'Marketplace ready',
                ],
            ],
            'pro' => [
                'id'          => 'pro',
                'name'        => 'Pro',
                'tier'        => 'Pro',
                'credits'     => 5000,
                'price_krw'   => 59900,
                'yearly_price_krw' => 599000,
                'product_id'  => 0,
                'yearly_product_id' => 0,
                'features'    => [
                    '5,000 credits',
                    'Video generation',
                    'Advanced image settings',
                    'Voice / avatar tools',
                    'Priority processing',
                    'Commercial use support',
                ],
            ],
            'business' => [
                'id'          => 'business',
                'name'        => 'Business',
                'tier'        => 'Business',
                'credits'     => 15000,
                'price_krw'   => 149000,
                'yearly_price_krw' => 1490000,
                'product_id'  => 0,
                'yearly_product_id' => 0,
                'features'    => [
                    '15,000 credits',
                    'Team usage',
                    'Brand workspace',
                    'Admin controls',
                    'Higher limits',
                    'Marketplace seller tools',
                    'API / provider control',
                ],
            ],
        ];
    }

    public static function merged(): array {
        $catalog = self::catalog();
        $stored  = get_option('yoy_credit_packages', []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $by_id = [];
        foreach ($stored as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $by_id[sanitize_text_field($row['id'])] = $row;
        }

        $plans = [];
        foreach ($catalog as $id => $base) {
            $override = $by_id[$id] ?? [];
            $plans[$id] = array_merge($base, [
                'credits'    => (int) ($override['credits'] ?? $base['credits']),
                'price_krw'  => (int) ($override['price_krw'] ?? $base['price_krw']),
                'yearly_price_krw' => (int) ($override['yearly_price_krw'] ?? $base['yearly_price_krw'] ?? 0),
                'product_id' => (int) ($override['product_id'] ?? $base['product_id'] ?? 0),
                'yearly_product_id' => (int) ($override['yearly_product_id'] ?? $base['yearly_product_id'] ?? 0),
                'name'       => sanitize_text_field($override['name'] ?? $base['name']),
            ]);
            $plans[$id]['checkout_url'] = self::checkout_url((int) $plans[$id]['product_id']);
            $plans[$id]['yearly_checkout_url'] = self::checkout_url((int) $plans[$id]['yearly_product_id']);
            $plans[$id]['payment_ready'] = ((int) $plans[$id]['product_id']) > 0 || ((int) $plans[$id]['yearly_product_id']) > 0;
        }

        return array_values($plans);
    }

    public static function get(string $plan_id): ?array {
        foreach (self::merged() as $plan) {
            if (($plan['id'] ?? '') === $plan_id) {
                return $plan;
            }
        }
        return null;
    }

    public static function checkout_url(int $product_id): string {
        if ($product_id <= 0) {
            return '';
        }
        if (function_exists('wc_get_cart_url')) {
            return add_query_arg('add-to-cart', $product_id, wc_get_cart_url());
        }
        if (function_exists('get_permalink')) {
            $permalink = get_permalink($product_id);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }
        if (function_exists('wc_get_checkout_url')) {
            return add_query_arg('add-to-cart', $product_id, wc_get_checkout_url());
        }
        return '';
    }

    public static function tier_order(): array {
        return ['free', 'starter', 'creator', 'pro', 'business'];
    }

    public static function compare_action(string $current, string $target): string {
        $order = array_flip(self::tier_order());
        $cur   = $order[$current] ?? 0;
        $tgt   = $order[$target] ?? 0;
        if ($current === $target) {
            return 'current';
        }
        if ($tgt > $cur) {
            return 'upgrade';
        }
        return 'downgrade';
    }

    public static function plan_for_product(int $product_id): ?array {
        if ($product_id <= 0) {
            return null;
        }
        foreach (self::merged() as $plan) {
            if ((int) ($plan['product_id'] ?? 0) === $product_id) {
                return $plan;
            }
            if ((int) ($plan['yearly_product_id'] ?? 0) === $product_id) {
                return $plan;
            }
        }
        return null;
    }

    public static function billing_config(): array {
        $wc = class_exists('YooY_WooCommerce_Billing') && YooY_WooCommerce_Billing::wc_active();
        $ready = $wc && YooY_WooCommerce_Billing::payment_ready();
        $settings = get_option('yoy_billing_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return [
            'woocommerce_active' => $wc,
            'payment_ready'      => $ready,
            'providers'          => [
                ['id' => 'woocommerce', 'label' => 'WooCommerce', 'enabled' => $wc],
                ['id' => 'stripe', 'label' => 'Stripe', 'enabled' => !empty($settings['stripe'])],
                ['id' => 'paypal', 'label' => 'PayPal', 'enabled' => !empty($settings['paypal'])],
                ['id' => 'kakaopay', 'label' => 'KakaoPay', 'enabled' => !empty($settings['kakaopay'])],
                ['id' => 'naverpay', 'label' => 'Naver Pay', 'enabled' => !empty($settings['naverpay'])],
                ['id' => 'toss', 'label' => 'Toss Payments', 'enabled' => !empty($settings['toss'])],
            ],
            'primary' => 'woocommerce',
            'support_email' => sanitize_email($settings['support_email'] ?? get_option('admin_email')),
        ];
    }
}
