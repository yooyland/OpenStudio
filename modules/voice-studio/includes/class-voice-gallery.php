<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_Gallery {

    private const META_KEY = 'yoy_voice_gallery';

    public function list(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public function save(int $user_id, array $item): array {
        $gallery = $this->list($user_id);
        $entry   = [
            'id'        => sanitize_text_field($item['id'] ?? ('vgal_' . wp_generate_uuid4())),
            'title'     => sanitize_text_field($item['title'] ?? mb_substr($item['text'] ?? 'Voice', 0, 40)),
            'text'      => sanitize_textarea_field($item['text'] ?? ''),
            'audio_url' => esc_url_raw($item['audio_url'] ?? ($item['output']['audio_url'] ?? '')),
            'voice_id'  => sanitize_text_field($item['voice_id'] ?? ''),
            'emotion'   => sanitize_text_field($item['emotion'] ?? ''),
            'language'  => sanitize_text_field($item['language'] ?? 'ko'),
            'provider'  => sanitize_text_field($item['provider'] ?? 'mock'),
            'duration'  => (float) ($item['duration_est'] ?? 0),
            'created_at'=> gmdate('c'),
        ];
        array_unshift($gallery, $entry);
        update_user_meta($user_id, self::META_KEY, array_slice($gallery, 0, 100));
        return $entry;
    }

    public function auto_save(int $user_id, array $result): void {
        $this->save($user_id, $result);
    }

    public function remove(int $user_id, string $id): bool {
        $gallery = $this->list($user_id);
        $before  = count($gallery);
        $gallery = array_values(array_filter($gallery, fn($g) => ($g['id'] ?? '') !== $id));
        update_user_meta($user_id, self::META_KEY, $gallery);
        return count($gallery) < $before;
    }
}
