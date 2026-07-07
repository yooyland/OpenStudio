<?php
if (!defined('ABSPATH')) exit;

final class YooY_Job_Store {

    private const META_KEY = 'yoy_job_store';

    public function list(int $user_id, array $filters = []): array {
        $items = $this->all($user_id);
        $type   = sanitize_text_field($filters['type'] ?? '');
        $studio = sanitize_text_field($filters['studio'] ?? '');
        $status = sanitize_text_field($filters['status'] ?? '');

        if ($type !== '') {
            $items = array_values(array_filter($items, fn($i) => ($i['type'] ?? '') === $type));
        }
        if ($studio !== '') {
            $items = array_values(array_filter($items, fn($i) => ($i['studio'] ?? '') === $studio));
        }
        if ($status !== '') {
            $items = array_values(array_filter($items, fn($i) => ($i['status'] ?? '') === $status));
        }

        return $items;
    }

    public function get(int $user_id, string $job_id): ?array {
        foreach ($this->all($user_id) as $item) {
            if (($item['job_id'] ?? '') === $job_id) return $item;
        }
        return null;
    }

    public function save(int $user_id, array $job, string $studio = ''): array {
        $normalized = YooY_Job_Normalizer::normalize($job, $job['type'] ?? 'image');
        $normalized = YooY_Job_Normalizer::ensure_output_or_fail($normalized);
        $entry = array_merge($normalized, [
            'studio'     => $studio ?: ($job['studio'] ?? ''),
            'id'         => $normalized['job_id'],
            'updated_at' => gmdate('c'),
        ]);

        if (empty($entry['created_at'])) {
            $entry['created_at'] = gmdate('c');
        }

        $items = $this->all($user_id);
        $found = false;
        foreach ($items as $idx => $existing) {
            if (($existing['job_id'] ?? '') === $entry['job_id']) {
                $items[$idx] = array_merge($existing, $entry);
                $found = true;
                break;
            }
        }

        if (!$found) {
            array_unshift($items, $entry);
        }

        $items = array_slice($items, 0, 500);
        update_user_meta($user_id, self::META_KEY, $items);

        foreach ($items as $item) {
            if (($item['job_id'] ?? '') === $entry['job_id']) return $item;
        }
        return $entry;
    }

    public function update_status(int $user_id, string $job_id, array $patch): ?array {
        $items = $this->all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['job_id'] ?? '') !== $job_id) continue;
            $items[$idx] = array_merge($item, $patch, ['updated_at' => gmdate('c')]);
            update_user_meta($user_id, self::META_KEY, $items);
            return $items[$idx];
        }
        return null;
    }

    public function remove(int $user_id, string $job_id): bool {
        $items  = $this->all($user_id);
        $before = count($items);
        $items  = array_values(array_filter($items, fn($i) => ($i['job_id'] ?? '') !== $job_id));
        update_user_meta($user_id, self::META_KEY, $items);
        return count($items) < $before;
    }

    public function clear(int $user_id, string $studio = ''): void {
        if ($studio === '') {
            delete_user_meta($user_id, self::META_KEY);
            return;
        }
        $items = array_values(array_filter($this->all($user_id), fn($i) => ($i['studio'] ?? '') !== $studio));
        update_user_meta($user_id, self::META_KEY, $items);
    }

    public function all(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }
}
