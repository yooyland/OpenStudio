<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Gallery {

    private const META_KEY = 'yoy_music_gallery';

    public function list(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public function save(int $user_id, array $item): array {
        $gallery = $this->list($user_id);
        $entry   = [
            'id'         => sanitize_text_field($item['id'] ?? ('mgal_' . wp_generate_uuid4())),
            'title'      => sanitize_text_field($item['title'] ?? 'Untitled Track'),
            'genre'      => sanitize_text_field($item['genre'] ?? ''),
            'mood'       => sanitize_text_field($item['mood'] ?? ''),
            'audio_url'  => esc_url_raw($item['audio_url'] ?? ($item['output']['audio_url'] ?? '')),
            'cover_url'  => esc_url_raw($item['cover_url'] ?? ($item['output']['cover_url'] ?? '')),
            'duration'   => (int) ($item['duration'] ?? 0),
            'provider'   => sanitize_text_field($item['provider'] ?? 'mock'),
            'created_at' => gmdate('c'),
        ];
        array_unshift($gallery, $entry);
        update_user_meta($user_id, self::META_KEY, array_slice($gallery, 0, 100));
        return $entry;
    }

    public function auto_save(int $user_id, array $result): void {
        $this->save($user_id, [
            'id'        => $result['job_id'] ?? '',
            'title'     => $result['title'] ?? mb_substr($result['lyrics'] ?? 'Track', 0, 30),
            'genre'     => $result['genre'] ?? '',
            'mood'      => $result['mood'] ?? '',
            'audio_url' => $result['output']['audio_url'] ?? '',
            'cover_url' => $result['output']['cover_url'] ?? '',
            'duration'  => $result['duration'] ?? 0,
            'provider'  => $result['provider'] ?? 'mock',
        ]);
    }

    public function remove(int $user_id, string $id): bool {
        $gallery = $this->list($user_id);
        $before  = count($gallery);
        $gallery = array_values(array_filter($gallery, fn($g) => ($g['id'] ?? '') !== $id));
        update_user_meta($user_id, self::META_KEY, $gallery);
        return count($gallery) < $before;
    }
}
