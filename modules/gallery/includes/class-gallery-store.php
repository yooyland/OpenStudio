<?php
if (!defined('ABSPATH')) exit;

final class YooY_Gallery_Store {

    private const META_KEY = 'yoy_gallery_items';

    public function list(int $user_id, array $filters = []): array {
        $items = $this->get_all($user_id);
        $type  = sanitize_text_field($filters['type'] ?? '');
        $fav   = isset($filters['favorite']) ? (bool) $filters['favorite'] : null;

        if ($type !== '') {
            $items = array_values(array_filter($items, fn($i) => ($i['type'] ?? '') === $type));
        }
        if ($fav !== null) {
            $items = array_values(array_filter($items, fn($i) => !empty($i['favorite']) === $fav));
        }

        return $items;
    }

    public function get(int $user_id, string $id): ?array {
        foreach ($this->get_all($user_id) as $item) {
            if (($item['id'] ?? '') === $id) return $item;
        }
        return null;
    }

    public function save(int $user_id, array $item): array {
        $items = $this->get_all($user_id);
        $entry = $this->normalize($item);

        foreach ($items as $idx => $existing) {
            if (($existing['id'] ?? '') === $entry['id']) {
                $items[$idx] = array_merge($existing, $entry);
                update_user_meta($user_id, self::META_KEY, $items);
                return $items[$idx];
            }
        }

        array_unshift($items, $entry);
        $items = array_slice($items, 0, 500);
        update_user_meta($user_id, self::META_KEY, $items);
        return $entry;
    }

    public function update(int $user_id, string $id, array $data): ?array {
        $items = $this->get_all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $id) continue;
            $allowed = ['favorite', 'public', 'marketplace', 'community_shared', 'title'];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $data)) {
                    $items[$idx][$key] = $data[$key];
                }
            }
            $items[$idx]['updated_at'] = gmdate('c');
            update_user_meta($user_id, self::META_KEY, $items);
            return $items[$idx];
        }
        return null;
    }

    public function remove(int $user_id, string $id): bool {
        $items  = $this->get_all($user_id);
        $before = count($items);
        $items  = array_values(array_filter($items, fn($i) => ($i['id'] ?? '') !== $id));
        update_user_meta($user_id, self::META_KEY, $items);
        return count($items) < $before;
    }

    public function get_all(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public function set_all(int $user_id, array $items): void {
        update_user_meta($user_id, self::META_KEY, array_slice($items, 0, 500));
    }

    private function normalize(array $item): array {
        $type = sanitize_text_field($item['type'] ?? 'image');
        $prompt = sanitize_textarea_field($item['prompt'] ?? $item['script'] ?? $item['text'] ?? $item['lyrics'] ?? '');

        return [
            'id'               => sanitize_text_field($item['id'] ?? ('gal_' . wp_generate_uuid4())),
            'type'             => $type,
            'title'            => sanitize_text_field($item['title'] ?? mb_substr($prompt, 0, 40) ?: 'Untitled'),
            'prompt'           => $prompt,
            'provider'         => sanitize_text_field($item['provider'] ?? 'mock'),
            'model'            => sanitize_text_field($item['model'] ?? ''),
            'credits_used'     => (int) ($item['credits_used'] ?? 0),
            'studio'           => sanitize_text_field($item['studio'] ?? $this->studio_from_type($type)),
            'thumbnail'        => esc_url_raw($item['thumbnail'] ?? $item['cover_url'] ?? ''),
            'output_url'       => esc_url_raw($item['output_url'] ?? $item['url'] ?? $item['video_url'] ?? $item['audio_url'] ?? ''),
            'output'           => is_array($item['output'] ?? null) ? $item['output'] : [],
            'favorite'         => !empty($item['favorite']),
            'public'           => !empty($item['public']),
            'marketplace'      => !empty($item['marketplace']),
            'community_shared' => !empty($item['community_shared']),
            'created_at'       => $item['created_at'] ?? gmdate('c'),
            'updated_at'       => gmdate('c'),
            'meta'             => is_array($item['meta'] ?? null) ? $item['meta'] : [],
        ];
    }

    private function studio_from_type(string $type): string {
        switch ($type) {
            case 'video':
                return 'video-studio';
            case 'image':
                return 'image-studio';
            case 'music':
                return 'music-studio';
            case 'voice':
                return 'voice-studio';
            case 'avatar':
                return 'avatar-studio';
            case 'writing':
                return 'writing-studio';
            default:
                return 'unknown';
        }
    }
}
