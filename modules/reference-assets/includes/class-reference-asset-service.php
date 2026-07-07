<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-reference-asset-store.php';

final class YooY_Reference_Asset_Service {

    private YooY_Reference_Asset_Store $store;

    public function __construct(?YooY_Reference_Asset_Store $store = null) {
        $this->store = $store ?? new YooY_Reference_Asset_Store();
    }

    public function list(int $user_id, array $filters = []): array {
        return $this->store->list($user_id, $filters);
    }

    public function get(int $user_id, string $id): ?array {
        return $this->store->get($user_id, $id);
    }

    public function upload(int $user_id, array $data): array {
        $title = sanitize_text_field($data['title'] ?? '');
        $role = YooY_Reference_Asset_Store::sanitize_role((string) ($data['role'] ?? 'image'));
        $studio = sanitize_text_field($data['studio'] ?? '');
        $project_id = sanitize_text_field($data['project_id'] ?? '');
        $asset = $this->resolve_upload_asset($user_id, $data);
        if (empty($asset['url'])) {
            throw new Exception('Failed to store reference asset.');
        }

        $entry = $this->store->save($user_id, array_merge($asset, [
            'title'      => $title !== '' ? $title : ($data['filename'] ?? 'Reference Asset'),
            'role'       => $role,
            'studio'     => $studio,
            'project_id' => $project_id,
            'source'     => 'upload',
        ]));

        if ($project_id !== '') {
            $this->attach_to_project($user_id, $project_id, $entry);
        }

        return $entry;
    }

    public function from_gallery(int $user_id, string $gallery_id, array $opts = []): array {
        if (!class_exists('YooY_Gallery_Store')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
        }
        $gallery = new YooY_Gallery_Store();
        $item = $gallery->get($user_id, $gallery_id);
        if (!$item) {
            throw new Exception('Gallery item not found.');
        }

        $url = esc_url_raw($item['output_url'] ?? $item['image_url'] ?? '');
        if ($url === '' && !empty($item['output']['primary'])) {
            $url = esc_url_raw($item['output']['primary']);
        }
        if ($url === '') {
            throw new Exception('Gallery item has no usable asset URL.');
        }

        $entry = $this->store->save($user_id, [
            'title'         => sanitize_text_field($opts['title'] ?? $item['title'] ?? 'Gallery Reference'),
            'asset_type'    => sanitize_text_field($item['type'] ?? 'image'),
            'role'          => YooY_Reference_Asset_Store::sanitize_role((string) ($opts['role'] ?? $item['type'] ?? 'image')),
            'url'           => $url,
            'thumbnail_url' => esc_url_raw($item['thumbnail_url'] ?? $item['thumbnail'] ?? $url),
            'attachment_id' => (int) ($item['attachment_id'] ?? 0),
            'source'        => 'gallery',
            'source_id'     => $gallery_id,
            'studio'        => sanitize_text_field($opts['studio'] ?? $item['studio'] ?? ''),
            'project_id'    => sanitize_text_field($opts['project_id'] ?? ''),
            'meta'          => ['gallery_id' => $gallery_id, 'prompt' => $item['prompt'] ?? ''],
        ]);

        $project_id = sanitize_text_field($opts['project_id'] ?? '');
        if ($project_id !== '') {
            $this->attach_to_project($user_id, $project_id, $entry);
        }

        return $entry;
    }

    public function from_import(int $user_id, string $import_id, array $opts = []): array {
        if (!class_exists('YooY_Import_Queue')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'import-engine/includes/class-import-queue.php';
        }
        $queue = new YooY_Import_Queue();
        $item = $queue->get($user_id, $import_id);
        if (!$item) {
            throw new Exception('Import item not found.');
        }

        $url = esc_url_raw($item['asset_url'] ?? $item['url'] ?? '');
        if ($url === '' && !empty($item['gallery_id']) && class_exists('YooY_Gallery_Store')) {
            $gallery = new YooY_Gallery_Store();
            $g = $gallery->get($user_id, (string) $item['gallery_id']);
            $url = esc_url_raw($g['output_url'] ?? $g['image_url'] ?? '');
        }
        if ($url === '') {
            throw new Exception('Import item has no stored asset URL.');
        }

        return $this->store->save($user_id, [
            'title'         => sanitize_text_field($opts['title'] ?? $item['filename'] ?? 'Import Reference'),
            'asset_type'    => sanitize_text_field($item['type'] ?? 'image'),
            'role'          => YooY_Reference_Asset_Store::sanitize_role((string) ($opts['role'] ?? $item['type'] ?? 'image')),
            'url'           => $url,
            'thumbnail_url' => esc_url_raw($item['thumbnail_url'] ?? $url),
            'attachment_id' => (int) ($item['attachment_id'] ?? 0),
            'source'        => 'import',
            'source_id'     => $import_id,
            'studio'        => sanitize_text_field($opts['studio'] ?? ''),
            'project_id'    => sanitize_text_field($opts['project_id'] ?? ''),
        ]);
    }

    public function from_project(int $user_id, string $project_id, string $asset_id, array $opts = []): array {
        if (!class_exists('YooY_Project_Store')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php';
        }
        $projects = new YooY_Project_Store();
        $project = $projects->get($user_id, $project_id);
        if (!$project) {
            throw new Exception('Project not found.');
        }

        $match = null;
        foreach (($project['assets'] ?? []) as $asset) {
            if (($asset['id'] ?? '') === $asset_id || ($asset['gallery_id'] ?? '') === $asset_id) {
                $match = $asset;
                break;
            }
        }
        foreach (($project['reference_assets'] ?? []) as $asset) {
            if (($asset['id'] ?? '') === $asset_id) {
                $match = $asset;
                break;
            }
        }
        if (!$match) {
            throw new Exception('Project asset not found.');
        }

        $url = esc_url_raw($match['url'] ?? '');
        if ($url === '') {
            throw new Exception('Project asset has no URL.');
        }

        return $this->store->save($user_id, [
            'title'         => sanitize_text_field($opts['title'] ?? $match['title'] ?? 'Project Reference'),
            'asset_type'    => sanitize_text_field($match['type'] ?? 'image'),
            'role'          => YooY_Reference_Asset_Store::sanitize_role((string) ($opts['role'] ?? $match['type'] ?? 'image')),
            'url'           => $url,
            'thumbnail_url' => esc_url_raw($match['thumbnail'] ?? $url),
            'source'        => 'project',
            'source_id'     => $asset_id,
            'studio'        => sanitize_text_field($opts['studio'] ?? ''),
            'project_id'    => $project_id,
        ]);
    }

    public function rename(int $user_id, string $id, string $title): ?array {
        return $this->store->update($user_id, $id, ['title' => $title]);
    }

    public function replace(int $user_id, string $id, array $data): array {
        $existing = $this->store->get($user_id, $id);
        if (!$existing) {
            throw new Exception('Reference asset not found.');
        }
        $asset = $this->resolve_upload_asset($user_id, $data);
        if (empty($asset['url'])) {
            throw new Exception('Failed to store replacement asset.');
        }
        return $this->store->save($user_id, array_merge($existing, $asset, [
            'id'    => $id,
            'title' => sanitize_text_field($data['title'] ?? $existing['title']),
        ]));
    }

    public function remove(int $user_id, string $id): bool {
        return $this->store->remove($user_id, $id);
    }

    public static function normalize_payload_list($raw): array {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = esc_url_raw($item['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $out[] = [
                'id'            => sanitize_text_field($item['id'] ?? ''),
                'url'           => $url,
                'asset_type'    => sanitize_text_field($item['asset_type'] ?? $item['type'] ?? 'image'),
                'role'          => YooY_Reference_Asset_Store::sanitize_role((string) ($item['role'] ?? '')),
                'title'         => sanitize_text_field($item['title'] ?? ''),
                'thumbnail_url' => esc_url_raw($item['thumbnail_url'] ?? $item['thumbnail'] ?? ''),
                'attachment_id' => (int) ($item['attachment_id'] ?? 0),
            ];
        }
        return array_slice($out, 0, 12);
    }

    public static function primary_url(array $assets): string {
        if (empty($assets[0]['url'])) {
            return '';
        }
        return esc_url_raw((string) $assets[0]['url']);
    }

    private function resolve_upload_asset(int $user_id, array $data): array {
        if (!class_exists('YooY_Asset_Generator')) {
            require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
        }

        if (!empty($data['file_base64'])) {
            $base64 = (string) $data['file_base64'];
            $filename = sanitize_file_name($data['filename'] ?? ('ref-' . wp_generate_uuid4()));
            if (strpos($base64, 'data:') !== 0) {
                $mime = sanitize_mime_type($data['mime_type'] ?? 'application/octet-stream');
                $base64 = 'data:' . $mime . ';base64,' . $base64;
            }
            $stored = YooY_Asset_Generator::import_from_data_uri($base64, $filename, $user_id);
            return $this->asset_from_stored($stored, $data);
        }

        if (!empty($data['url'])) {
            return [
                'url'           => esc_url_raw($data['url']),
                'thumbnail_url' => esc_url_raw($data['thumbnail_url'] ?? $data['url']),
                'asset_type'    => sanitize_text_field($data['asset_type'] ?? 'image'),
                'mime_type'     => sanitize_text_field($data['mime_type'] ?? ''),
                'attachment_id' => (int) ($data['attachment_id'] ?? 0),
            ];
        }

        throw new Exception('Reference file or URL is required.');
    }

    private function asset_from_stored(array $stored, array $data): array {
        if (empty($stored['url'])) {
            return [];
        }
        $ext = strtolower(pathinfo((string) ($data['filename'] ?? ''), PATHINFO_EXTENSION));
        $asset_type = sanitize_text_field($data['asset_type'] ?? $this->detect_type_from_ext($ext));
        return [
            'url'           => esc_url_raw($stored['url']),
            'thumbnail_url' => esc_url_raw($stored['thumbnail'] ?? $stored['url']),
            'attachment_id' => (int) ($stored['attachment_id'] ?? 0),
            'asset_type'    => $asset_type,
            'mime_type'     => sanitize_text_field($stored['mime'] ?? $data['mime_type'] ?? ''),
        ];
    }

    private function detect_type_from_ext(string $ext): string {
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg', 'gif'], true)) {
            return 'image';
        }
        if (in_array($ext, ['mp4', 'mov', 'webm', 'avi', 'mkv'], true)) {
            return 'video';
        }
        if (in_array($ext, ['mp3', 'wav', 'flac', 'aac', 'ogg'], true)) {
            return 'audio';
        }
        if (in_array($ext, ['pdf', 'docx', 'txt', 'md', 'markdown'], true)) {
            return 'document';
        }
        return 'image';
    }

    private function attach_to_project(int $user_id, string $project_id, array $entry): void {
        if (!class_exists('YooY_Project_Store')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php';
        }
        $projects = new YooY_Project_Store();
        $projects->add_reference_asset($user_id, $project_id, $entry);
    }
}
