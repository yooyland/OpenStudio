<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Gallery {

    private const META_KEY = 'yoy_video_gallery';

    public function list(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public function save(int $user_id, array $item): array {
        $gallery = $this->list($user_id);
        $entry   = [
            'id'           => sanitize_text_field($item['id'] ?? ('vgal_' . wp_generate_uuid4())),
            'title'        => sanitize_text_field($item['title'] ?? 'Untitled Video'),
            'prompt'       => sanitize_textarea_field($item['prompt'] ?? ''),
            'thumbnail'    => esc_url_raw($item['thumbnail'] ?? ($item['output']['thumbnail'] ?? '')),
            'url'          => esc_url_raw($item['url'] ?? ($item['output']['url'] ?? '')),
            'provider'     => sanitize_text_field($item['provider'] ?? 'mock'),
            'aspect_ratio' => sanitize_text_field($item['aspect_ratio'] ?? '16:9'),
            'duration'     => (int) ($item['duration'] ?? 5),
            'created_at'   => gmdate('c'),
            'public'       => !empty($item['public']),
        ];
        array_unshift($gallery, $entry);
        $gallery = array_slice($gallery, 0, 100);
        update_user_meta($user_id, self::META_KEY, $gallery);
        return $entry;
    }

    public function auto_save(int $user_id, array $entry, bool $enabled): void {
        if (!$enabled) return;
        $this->save($user_id, [
            'id'        => $entry['id'] ?? $entry['job_id'] ?? '',
            'title'     => mb_substr($entry['prompt'] ?? 'Generated Video', 0, 40),
            'prompt'    => $entry['prompt'] ?? '',
            'output'    => $entry['output'] ?? [],
            'provider'  => $entry['provider'] ?? 'mock',
            'aspect_ratio' => $entry['aspect_ratio'] ?? '16:9',
            'duration'  => $entry['duration'] ?? 5,
        ]);
    }

    public function remove(int $user_id, string $id): bool {
        $gallery = $this->list($user_id);
        $before  = count($gallery);
        $gallery = array_values(array_filter($gallery, fn($item) => ($item['id'] ?? '') !== $id));
        update_user_meta($user_id, self::META_KEY, $gallery);
        return count($gallery) < $before;
    }
}
