<?php
if (!defined('ABSPATH')) exit;

final class YooY_Import_Queue {

    private const META_KEY = 'yoy_import_queue';
    private const LOG_OPTION = 'yoy_import_platform_log';

    public function list(int $user_id, ?string $status = null): array {
        $items = $this->all($user_id);
        if ($status === null || $status === '') {
            return $items;
        }
        return array_values(array_filter($items, function ($item) use ($status) {
            return ($item['status'] ?? '') === $status;
        }));
    }

    public function get(int $user_id, string $id): ?array {
        foreach ($this->all($user_id) as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    public function enqueue(int $user_id, array $payload): array {
        $items = $this->all($user_id);
        $entry = [
            'id'         => 'imp_' . wp_generate_uuid4(),
            'user_id'    => $user_id,
            'filename'   => sanitize_text_field($payload['filename'] ?? 'import.bin'),
            'type'       => sanitize_text_field($payload['type'] ?? 'image'),
            'source'     => sanitize_text_field($payload['source'] ?? 'upload'),
            'origin'     => sanitize_text_field($payload['origin'] ?? 'Imported'),
            'status'     => 'queued',
            'progress'   => 0,
            'error'      => '',
            'options'    => is_array($payload['options'] ?? null) ? $payload['options'] : [],
            'binary_ref' => sanitize_text_field($payload['binary_ref'] ?? ''),
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];

        array_unshift($items, $entry);
        $items = array_slice($items, 0, 100);
        update_user_meta($user_id, self::META_KEY, $items);

        return $entry;
    }

    public function update(int $user_id, string $id, array $patch): ?array {
        $items = $this->all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            foreach (['status', 'progress', 'error', 'gallery_id', 'binary_ref'] as $key) {
                if (array_key_exists($key, $patch)) {
                    $items[$idx][$key] = $patch[$key];
                }
            }
            if (array_key_exists('meta', $patch) && is_array($patch['meta'])) {
                $meta = is_array($items[$idx]['meta'] ?? null) ? $items[$idx]['meta'] : [];
                $items[$idx]['meta'] = array_merge($meta, $patch['meta']);
            }
            $items[$idx]['updated_at'] = gmdate('c');
            update_user_meta($user_id, self::META_KEY, $items);
            return $items[$idx];
        }
        return null;
    }

    public function store_binary(int $user_id, string $queue_id, string $binary): string {
        if (!function_exists('wp_upload_dir')) {
            return '';
        }

        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return '';
        }

        $dir = trailingslashit($upload['basedir']) . 'yooy-ai-studio/import-queue/' . $user_id . '/';
        if (!wp_mkdir_p($dir)) {
            return '';
        }

        $path = $dir . sanitize_file_name($queue_id) . '.bin';
        if (@file_put_contents($path, $binary) === false) {
            return '';
        }

        return $path;
    }

    public function load_binary(string $path): string {
        if ($path === '' || !is_readable($path)) {
            return '';
        }
        $data = @file_get_contents($path);
        return is_string($data) ? $data : '';
    }

    public function remove_binary(string $path): void {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    public function log_event(array $event): void {
        $log = get_option(self::LOG_OPTION, []);
        $log = is_array($log) ? $log : [];
        array_unshift($log, array_merge([
            'logged_at' => gmdate('c'),
        ], $event));
        update_option(self::LOG_OPTION, array_slice($log, 0, 500), false);
    }

    public function platform_log(int $limit = 50): array {
        $log = get_option(self::LOG_OPTION, []);
        return array_slice(is_array($log) ? $log : [], 0, $limit);
    }

    public function stats(): array {
        $log = $this->platform_log(500);
        $today = gmdate('Y-m-d');
        $imported_today = 0;
        $errors = 0;
        $queued = 0;

        foreach ($log as $row) {
            $day = substr((string) ($row['logged_at'] ?? ''), 0, 10);
            if (($row['status'] ?? '') === 'completed' && $day === $today) {
                $imported_today++;
            }
            if (($row['status'] ?? '') === 'failed') {
                $errors++;
            }
        }

        $users = get_users(['fields' => ['ID'], 'number' => 200]);
        foreach ($users as $user) {
            foreach ($this->list((int) $user->ID, 'queued') as $q) {
                $queued++;
            }
        }

        return [
            'queued'         => $queued,
            'imported_today' => $imported_today,
            'errors'         => $errors,
            'recent'         => array_slice($log, 0, 20),
        ];
    }

    private function all(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }
}
