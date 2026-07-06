<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_Upload {

    public function save_reference(int $user_id, array $data): array {
        $url = '';

        if (!empty($data['reference_url'])) {
            $url = esc_url_raw($data['reference_url']);
        } elseif (!empty($data['image_base64'])) {
            $url = $this->save_base64($user_id, $data['image_base64']);
        }

        if ($url === '') {
            throw new Exception('Reference image is required.');
        }

        $refs = get_user_meta($user_id, 'yoy_image_references', true);
        $refs = is_array($refs) ? $refs : [];
        $entry = ['id' => 'ref_' . wp_generate_uuid4(), 'url' => $url, 'created_at' => gmdate('c')];
        array_unshift($refs, $entry);
        update_user_meta($user_id, 'yoy_image_references', array_slice($refs, 0, 20));

        return $entry;
    }

    public function list(int $user_id): array {
        $stored = get_user_meta($user_id, 'yoy_image_references', true);
        return is_array($stored) ? $stored : [];
    }

    private function save_base64(int $user_id, string $base64): string {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) {
            $ext     = $m[1] === 'jpeg' ? 'jpg' : $m[1];
            $base64  = substr($base64, strpos($base64, ',') + 1);
        } else {
            $ext = 'png';
        }

        $decoded = base64_decode($base64);
        if ($decoded === false) {
            throw new Exception('Invalid image data.');
        }

        $upload_dir = wp_upload_dir();
        $filename   = 'yoy-ref-' . $user_id . '-' . time() . '.' . $ext;
        $filepath   = $upload_dir['path'] . '/' . $filename;

        if (!file_put_contents($filepath, $decoded)) {
            throw new Exception('Failed to save reference image.');
        }

        return $upload_dir['url'] . '/' . $filename;
    }
}
