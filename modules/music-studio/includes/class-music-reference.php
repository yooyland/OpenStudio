<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Reference {

    private const META_KEY = 'yoy_music_references';

    public function list(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public function save(int $user_id, array $data): array {
        $entry = [
            'id'         => 'mref_' . wp_generate_uuid4(),
            'title'      => sanitize_text_field($data['title'] ?? 'Reference Song'),
            'url'        => esc_url_raw($data['url'] ?? ''),
            'clip_id'    => sanitize_text_field($data['clip_id'] ?? ''),
            'style_tags' => sanitize_text_field($data['style_tags'] ?? ''),
            'created_at' => gmdate('c'),
        ];

        if ($entry['url'] === '' && empty($data['audio_base64'])) {
            throw new Exception('Reference song URL or audio is required.');
        }

        if (!empty($data['audio_base64'])) {
            $entry['url'] = $this->save_audio($user_id, $data['audio_base64']);
        }

        $refs = $this->list($user_id);
        array_unshift($refs, $entry);
        update_user_meta($user_id, self::META_KEY, array_slice($refs, 0, 20));
        return $entry;
    }

    private function save_audio(int $user_id, string $base64): string {
        $ext = 'mp3';
        if (preg_match('/^data:audio\/(\w+);base64,/', $base64, $m)) {
            $ext    = $m[1] === 'mpeg' ? 'mp3' : $m[1];
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }
        $decoded = base64_decode($base64);
        if ($decoded === false) throw new Exception('Invalid audio data.');

        $upload_dir = wp_upload_dir();
        $filename   = 'yoy-ref-music-' . $user_id . '-' . time() . '.' . $ext;
        $filepath   = $upload_dir['path'] . '/' . $filename;
        if (!file_put_contents($filepath, $decoded)) throw new Exception('Failed to save reference audio.');
        return $upload_dir['url'] . '/' . $filename;
    }
}
