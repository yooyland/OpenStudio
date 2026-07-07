<?php
if (!defined('ABSPATH')) exit;

final class YooY_Reference_Asset_Store {

    private const META_KEY = 'yoy_reference_assets';
    private const MAX_ITEMS = 120;

    public function list(int $user_id, array $filters = []): array {
        $items = $this->all($user_id);
        $studio = sanitize_text_field($filters['studio'] ?? '');
        $project_id = sanitize_text_field($filters['project_id'] ?? '');
        $asset_type = sanitize_text_field($filters['asset_type'] ?? $filters['type'] ?? '');

        if ($studio !== '') {
            $items = array_values(array_filter($items, function ($item) use ($studio) {
                $studios = $item['studios'] ?? [];
                return empty($studios) || in_array($studio, $studios, true) || in_array('all', $studios, true);
            }));
        }
        if ($project_id !== '') {
            $items = array_values(array_filter($items, function ($item) use ($project_id) {
                return ($item['project_id'] ?? '') === $project_id;
            }));
        }
        if ($asset_type !== '') {
            $items = array_values(array_filter($items, function ($item) use ($asset_type) {
                return ($item['asset_type'] ?? '') === $asset_type;
            }));
        }

        return $items;
    }

    public function get(int $user_id, string $id): ?array {
        foreach ($this->all($user_id) as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    public function save(int $user_id, array $asset): array {
        $entry = $this->normalize($asset, $user_id);
        $items = $this->all($user_id);
        $found = false;
        foreach ($items as $idx => $existing) {
            if (($existing['id'] ?? '') === $entry['id']) {
                $items[$idx] = array_merge($existing, $entry, ['updated_at' => gmdate('c')]);
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($items, $entry);
        }
        $items = array_slice($items, 0, self::MAX_ITEMS);
        update_user_meta($user_id, self::META_KEY, $items);
        return $this->get($user_id, $entry['id']) ?: $entry;
    }

    public function update(int $user_id, string $id, array $patch): ?array {
        $items = $this->all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            if (isset($patch['title'])) {
                $items[$idx]['title'] = sanitize_text_field($patch['title']);
            }
            if (isset($patch['role'])) {
                $items[$idx]['role'] = self::sanitize_role((string) $patch['role']);
            }
            if (isset($patch['project_id'])) {
                $items[$idx]['project_id'] = sanitize_text_field($patch['project_id']);
            }
            $items[$idx]['updated_at'] = gmdate('c');
            update_user_meta($user_id, self::META_KEY, $items);
            return $items[$idx];
        }
        return null;
    }

    public function remove(int $user_id, string $id): bool {
        $items = $this->all($user_id);
        $before = count($items);
        $items = array_values(array_filter($items, function ($item) use ($id) {
            return ($item['id'] ?? '') !== $id;
        }));
        update_user_meta($user_id, self::META_KEY, $items);
        return count($items) < $before;
    }

    public function all(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($stored)) {
            return [];
        }
        $items = [];
        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = $this->normalize($item, $user_id);
        }
        return $items;
    }

    public static function allowed_roles(): array {
        return [
            'image', 'video', 'audio', 'voice', 'logo', 'character', 'product',
            'brand_guide', 'color_palette', 'document', 'lyrics', 'script',
        ];
    }

    public static function sanitize_role(string $role): string {
        $role = sanitize_text_field($role);
        return in_array($role, self::allowed_roles(), true) ? $role : 'image';
    }

    private function normalize(array $asset, int $user_id): array {
        $asset_type = sanitize_text_field($asset['asset_type'] ?? $asset['type'] ?? 'image');
        $mime = sanitize_text_field($asset['mime_type'] ?? $asset['mime'] ?? '');
        $url = esc_url_raw($asset['url'] ?? '');
        $thumb = esc_url_raw($asset['thumbnail_url'] ?? $asset['thumbnail'] ?? $url);

        return [
            'id'             => sanitize_text_field($asset['id'] ?? ('rasset_' . wp_generate_uuid4())),
            'user_id'        => $user_id,
            'title'          => sanitize_text_field($asset['title'] ?? 'Reference Asset'),
            'asset_type'     => $asset_type,
            'mime_type'      => $mime,
            'role'           => self::sanitize_role((string) ($asset['role'] ?? $asset_type)),
            'url'            => $url,
            'thumbnail_url'  => $thumb,
            'attachment_id'  => (int) ($asset['attachment_id'] ?? 0),
            'source'         => sanitize_text_field($asset['source'] ?? 'upload'),
            'source_id'      => sanitize_text_field($asset['source_id'] ?? ''),
            'studio'         => sanitize_text_field($asset['studio'] ?? ''),
            'studios'        => is_array($asset['studios'] ?? null) ? array_map('sanitize_text_field', $asset['studios']) : [],
            'project_id'     => sanitize_text_field($asset['project_id'] ?? ''),
            'created_at'     => $asset['created_at'] ?? gmdate('c'),
            'updated_at'     => $asset['updated_at'] ?? gmdate('c'),
            'meta'           => is_array($asset['meta'] ?? null) ? $asset['meta'] : [],
        ];
    }
}
