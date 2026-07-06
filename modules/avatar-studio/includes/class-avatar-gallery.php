<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_Gallery {

    private const META_KEY = 'yoy_avatar_gallery';

    public function list(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public function save(int $user_id, array $item): array {
        $gallery = $this->list($user_id);
        $entry   = [
            'id'          => sanitize_text_field($item['id'] ?? ('agal_' . wp_generate_uuid4())),
            'title'       => sanitize_text_field($item['title'] ?? 'Avatar Video'),
            'script'      => sanitize_textarea_field($item['script'] ?? ''),
            'video_url'   => esc_url_raw($item['video_url'] ?? ($item['output']['video_url'] ?? '')),
            'thumbnail'   => esc_url_raw($item['thumbnail'] ?? ($item['output']['thumbnail'] ?? '')),
            'avatar_id'   => sanitize_text_field($item['avatar_id'] ?? ''),
            'scene_id'    => sanitize_text_field($item['scene_id'] ?? ''),
            'provider'    => sanitize_text_field($item['provider'] ?? 'mock'),
            'created_at'  => gmdate('c'),
        ];
        array_unshift($gallery, $entry);
        update_user_meta($user_id, self::META_KEY, array_slice($gallery, 0, 100));
        return $entry;
    }

    public function auto_save(int $user_id, array $result): void {
        $this->save($user_id, [
            'id'        => $result['job_id'] ?? '',
            'title'     => mb_substr($result['script'] ?? 'Avatar', 0, 40),
            'script'    => $result['script'] ?? '',
            'video_url' => $result['output']['video_url'] ?? '',
            'thumbnail' => $result['output']['thumbnail'] ?? '',
            'avatar_id' => $result['avatar'] ?? $result['avatar_id'] ?? '',
            'scene_id'  => $result['scene'] ?? $result['scene_id'] ?? '',
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
