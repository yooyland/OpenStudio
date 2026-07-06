<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Marketplace extends YooY_Module_Base {

    public function id(): string { return 'marketplace'; }
    public function name(): string { return 'Marketplace'; }
    public function description(): string { return 'Prompt templates, guides, and creator marketplace.'; }
    public function version(): string { return '1.0.0'; }

    public function register_rest_routes(): void {
        $this->register_route('/items', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'items'],
            'permission_callback' => '__return_true',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'item'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function items(): WP_REST_Response {
        return $this->success(['items' => $this->catalog()]);
    }

    public function item(WP_REST_Request $request): WP_REST_Response {
        $id    = sanitize_text_field($request->get_param('id'));
        $items = $this->catalog();

        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $this->success(['item' => $item]);
            }
        }

        return $this->error('Item not found.', 404);
    }

    private function catalog(): array {
        $stored = get_option('yoy_marketplace_catalog', []);
        return is_array($stored) ? $stored : [];
    }
}
