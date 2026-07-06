<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_History {

    private const META_KEY = 'yoy_voice_history';

    public function list(int $user_id, int $limit = 50): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return array_slice(is_array($stored) ? $stored : [], 0, $limit);
    }

    public function add(int $user_id, array $result): array {
        $history = $this->list($user_id, 200);
        $entry   = array_merge($result, ['id' => $result['job_id'] ?? ('vhist_' . wp_generate_uuid4()), 'created_at' => gmdate('c')]);
        array_unshift($history, $entry);
        update_user_meta($user_id, self::META_KEY, array_slice($history, 0, 200));
        return $entry;
    }
}
