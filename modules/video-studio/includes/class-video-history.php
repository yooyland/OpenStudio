<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_History {

    private const META_KEY = 'yoy_video_history';

    public function list(int $user_id, int $limit = 50): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        $items  = is_array($stored) ? $stored : [];
        return array_slice($items, 0, $limit);
    }

    public function get(int $user_id, string $id): ?array {
        foreach ($this->list($user_id, 200) as $item) {
            if (($item['id'] ?? $item['job_id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    public function add(int $user_id, array $result): array {
        $history = $this->list($user_id, 200);
        $entry   = array_merge($result, [
            'id'         => $result['job_id'] ?? ('vhist_' . wp_generate_uuid4()),
            'created_at' => gmdate('c'),
        ]);
        array_unshift($history, $entry);
        $history = array_slice($history, 0, 200);
        update_user_meta($user_id, self::META_KEY, $history);
        return $entry;
    }

    public function clear(int $user_id): void {
        delete_user_meta($user_id, self::META_KEY);
    }
}
