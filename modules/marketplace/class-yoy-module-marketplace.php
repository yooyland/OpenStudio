<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Marketplace extends YooY_Module_Base {

    public function id(): string { return 'marketplace'; }
    public function name(): string { return 'Marketplace'; }
    public function description(): string { return 'Prompt templates, guides, and creator marketplace.'; }
    public function version(): string { return '1.1.0'; }

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
        $id = sanitize_text_field($request->get_param('id'));
        foreach ($this->catalog() as $item) {
            if (($item['id'] ?? '') === $id || ($item['gallery_id'] ?? '') === $id) {
                return $this->success(['item' => $item]);
            }
        }
        return $this->error('Item not found.', 404);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array {
        $stored = get_option('yoy_marketplace_catalog', []);
        if (!is_array($stored)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($stored as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            // Legacy gallery listings used status=draft; treat as listed unless delisted.
            if ($status === 'delisted') {
                continue;
            }
            $gallery_id = (string) ($row['gallery_id'] ?? '');
            if ($gallery_id === '') {
                continue;
            }
            if (isset($seen[$gallery_id])) {
                continue;
            }
            if ($this->is_demo_or_placeholder($row)) {
                continue;
            }
            $seen[$gallery_id] = true;
            $out[] = $this->sanitize_listing($row);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sanitize_listing(array $row): array {
        $thumb = (string) ($row['thumbnail_url'] ?? $row['display_url'] ?? $row['thumbnail'] ?? $row['image_url'] ?? '');
        $creator = (string) ($row['creator_name'] ?? $row['creator'] ?? 'Creator');

        return [
            'id'            => (string) ($row['id'] ?? ''),
            'gallery_id'    => (string) ($row['gallery_id'] ?? ''),
            'title'         => (string) ($row['title'] ?? 'Work'),
            'description'   => (string) ($row['description'] ?? ''),
            'type'          => (string) ($row['type'] ?? 'image'),
            'category'      => (string) ($row['category'] ?? 'general'),
            'license'       => (string) ($row['license'] ?? ''),
            'price'         => (int) ($row['price'] ?? 0),
            'status'        => 'listed',
            'thumbnail'     => esc_url_raw($thumb),
            'thumbnail_url' => esc_url_raw($thumb),
            'display_url'   => esc_url_raw($thumb),
            'image_url'     => esc_url_raw($thumb),
            'creator'       => $creator,
            'creator_name'  => $creator,
            'created_at'    => (string) ($row['created_at'] ?? ''),
            // Catalog metadata only — no checkout/purchase path in Phase 6.
            'commerce'      => false,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function is_demo_or_placeholder(array $row): bool {
        if (!empty($row['is_demo'])) {
            return true;
        }
        $url = (string) ($row['thumbnail_url'] ?? $row['display_url'] ?? $row['thumbnail'] ?? '');
        if ($url === '') {
            return true;
        }
        return strpos($url, 'placeholder') !== false
            || strpos($url, 'placehold.co') !== false
            || strpos($url, 'official-showcase/thumbs') !== false;
    }
}
