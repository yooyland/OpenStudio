<?php
if (!defined('ABSPATH')) exit;

final class YooY_Gallery_Store {

    private const META_KEY = 'yoy_gallery_items';

    public function list(int $user_id, array $filters = []): array {
        $this->migrate_items($user_id);
        $items = $this->get_all($user_id);
        $type       = sanitize_text_field($filters['type'] ?? '');
        $project_id = sanitize_text_field($filters['project_id'] ?? '');
        $fav        = isset($filters['favorite']) ? (bool) $filters['favorite'] : null;

        if ($type !== '') {
            $items = array_values(array_filter($items, fn($i) => ($i['type'] ?? '') === $type));
        }
        if ($project_id !== '') {
            $items = array_values(array_filter($items, function ($i) use ($project_id) {
                $meta = is_array($i['meta'] ?? null) ? $i['meta'] : [];
                return ($meta['project_id'] ?? '') === $project_id;
            }));
        }
        if ($fav !== null) {
            $items = array_values(array_filter($items, fn($i) => !empty($i['favorite']) === $fav));
        }

        return array_map([$this, 'enrich_item'], $items);
    }

    public function get(int $user_id, string $id): ?array {
        $this->migrate_items($user_id);
        foreach ($this->get_all($user_id) as $item) {
            if (($item['id'] ?? '') === $id) {
                return $this->enrich_item($item);
            }
        }
        return null;
    }

    public function save(int $user_id, array $item): array {
        $items = $this->get_all($user_id);
        $item_id = sanitize_text_field($item['id'] ?? '');
        $entry = $this->normalize($item, $this->collect_existing_titles($items, $item_id));
        if (!$this->has_valid_asset($entry)) {
            throw new Exception('Cannot save gallery item without a valid asset URL.');
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(wp_json_encode([
                'event'         => 'yoy_gallery_store_save',
                'user_id'       => $user_id,
                'job_id'        => $entry['job_id'] ?? '',
                'provider'      => $entry['provider'] ?? '',
                'model'         => $entry['model'] ?? '',
                'title'         => $entry['title'] ?? '',
                'asset_url'     => $entry['image_url'] ?? $entry['output_url'] ?? '',
                'attachment_id' => (int) ($entry['attachment_id'] ?? 0),
                'thumbnail_url' => $entry['thumbnail_url'] ?? '',
            ]));
        }
        foreach ($items as $idx => $existing) {
            if (($existing['id'] ?? '') === $entry['id']) {
                $items[$idx] = array_merge($existing, $entry);
                update_user_meta($user_id, self::META_KEY, $items);
                return $this->enrich_item($items[$idx]);
            }
        }

        array_unshift($items, $entry);
        $items = array_slice($items, 0, 500);
        update_user_meta($user_id, self::META_KEY, $items);
        return $this->enrich_item($entry);
    }

    public function update(int $user_id, string $id, array $data): ?array {
        $items = $this->get_all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }

            if (array_key_exists('title', $data)) {
                $new_title = sanitize_text_field((string) $data['title']);
                if (class_exists('YooY_Gallery_Title_Service')) {
                    $new_title = YooY_Gallery_Title_Service::ensure_unique(
                        $new_title,
                        $this->collect_existing_titles($items, $id)
                    );
                }
                $items[$idx]['title'] = $new_title;
            }
            if (array_key_exists('favorite', $data)) {
                $items[$idx]['favorite'] = !empty($data['favorite']);
            }
            if (array_key_exists('public', $data)) {
                $items[$idx]['public'] = !empty($data['public']);
            }
            if (array_key_exists('marketplace', $data)) {
                $items[$idx]['marketplace'] = !empty($data['marketplace']);
            }
            if (array_key_exists('community_shared', $data)) {
                $items[$idx]['community_shared'] = !empty($data['community_shared']);
            }

            $meta = is_array($items[$idx]['meta'] ?? null) ? $items[$idx]['meta'] : [];
            if (array_key_exists('description', $data)) {
                $meta['description'] = sanitize_textarea_field((string) $data['description']);
            }
            if (array_key_exists('project_id', $data)) {
                $meta['project_id'] = sanitize_text_field((string) $data['project_id']);
            }
            if (array_key_exists('marketplace_status', $data)) {
                $meta['marketplace_status'] = sanitize_text_field((string) $data['marketplace_status']);
            }
            $items[$idx]['meta'] = $meta;
            $items[$idx]['updated_at'] = gmdate('c');
            update_user_meta($user_id, self::META_KEY, $items);
            return $this->enrich_item($items[$idx]);
        }
        return null;
    }

    public function remove(int $user_id, string $id): bool {
        $items  = $this->get_all($user_id);
        $before = count($items);
        $items  = array_values(array_filter($items, fn($i) => ($i['id'] ?? '') !== $id));
        update_user_meta($user_id, self::META_KEY, $items);
        return count($items) < $before;
    }

    public function get_all(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) ? $stored : [];
    }

    public function set_all(int $user_id, array $items): void {
        update_user_meta($user_id, self::META_KEY, array_slice($items, 0, 500));
    }

    public function migrate_items(int $user_id): void {
        if (!class_exists('YooY_Gallery_Title_Service')) {
            return;
        }

        $items = $this->get_all($user_id);
        $changed = false;
        $used_titles = [];

        foreach ($items as $idx => $item) {
            $updated = $item;
            $meta = is_array($updated['meta'] ?? null) ? $updated['meta'] : [];

            if (YooY_Gallery_Title_Service::is_placeholder((string) ($updated['title'] ?? ''))) {
                $updated['title'] = YooY_Gallery_Title_Service::resolve([
                    'title'           => $updated['title'] ?? '',
                    'user_prompt'     => $meta['user_prompt'] ?? '',
                    'prompt'          => $updated['prompt'] ?? '',
                    'filename'        => $meta['filename'] ?? '',
                    'type'            => $updated['type'] ?? 'image',
                    'existing_titles' => $used_titles,
                ]);
                $changed = true;
            } else {
                $current_title = trim((string) ($updated['title'] ?? ''));
                $unique_title = YooY_Gallery_Title_Service::ensure_unique($current_title, $used_titles);
                if ($unique_title !== $current_title) {
                    $updated['title'] = $unique_title;
                    $changed = true;
                }
            }

            if (($updated['title'] ?? '') !== '') {
                $used_titles[] = (string) $updated['title'];
            }

            $asset_url = $updated['image_url'] ?? $updated['output_url'] ?? ($meta['asset_url'] ?? '');
            $thumb = $updated['thumbnail_url'] ?? $updated['thumbnail'] ?? '';
            if ($thumb === '' && $asset_url !== '') {
                $updated['thumbnail_url'] = $asset_url;
                $updated['thumbnail'] = $asset_url;
                $changed = true;
            }
            if (empty($meta['asset_url']) && $asset_url !== '') {
                $meta['asset_url'] = $asset_url;
                $updated['meta'] = $meta;
                $changed = true;
            }

            if ($updated !== $item) {
                $items[$idx] = $updated;
            }
        }

        if ($changed) {
            update_user_meta($user_id, self::META_KEY, $items);
        }
    }

    private function normalize(array $item, array $existing_titles = []): array {
        if (!class_exists('YooY_Gallery_Title_Service')) {
            require_once dirname(__FILE__) . '/class-gallery-title-service.php';
        }

        $type = sanitize_text_field($item['type'] ?? 'image');
        $prompt = sanitize_textarea_field($item['prompt'] ?? $item['script'] ?? $item['text'] ?? $item['lyrics'] ?? '');
        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];

        if (!empty($item['user_prompt'])) {
            $meta['user_prompt'] = sanitize_textarea_field($item['user_prompt']);
        }
        if (!empty($item['optimized_prompt'])) {
            $meta['optimized_prompt'] = sanitize_textarea_field($item['optimized_prompt']);
        }
        if (!empty($item['negative_prompt'])) {
            $meta['negative_prompt'] = sanitize_textarea_field($item['negative_prompt']);
        }
        if (!empty($item['reference_assets']) && is_array($item['reference_assets'])) {
            $meta['reference_assets'] = $item['reference_assets'];
        }
        if (!empty($item['settings']) && is_array($item['settings'])) {
            $meta['settings'] = $item['settings'];
        }
        if (!empty($item['origin'])) {
            $meta['origin'] = sanitize_text_field($item['origin']);
        }
        if (!empty($item['description'])) {
            $meta['description'] = sanitize_textarea_field($item['description']);
        }
        if (!empty($item['status'])) {
            $meta['status'] = sanitize_text_field($item['status']);
        }
        if (!empty($item['project_id'])) {
            $meta['project_id'] = sanitize_text_field($item['project_id']);
        }
        if (!empty($item['filename'])) {
            $meta['filename'] = sanitize_text_field($item['filename']);
        }
        if (!empty($item['asset_url'])) {
            $meta['asset_url'] = $this->sanitize_asset_url($item['asset_url']);
        }
        if (!empty($item['marketplace_status'])) {
            $meta['marketplace_status'] = sanitize_text_field($item['marketplace_status']);
        }

        $image_url = $this->sanitize_asset_url($item['image_url'] ?? $item['asset_url'] ?? $item['output_url'] ?? $item['url'] ?? $item['video_url'] ?? $item['audio_url'] ?? '');
        $thumbnail_url = $this->sanitize_asset_url($item['thumbnail_url'] ?? $item['thumbnail'] ?? $item['cover_url'] ?? '');

        $title = YooY_Gallery_Title_Service::resolve([
            'title'           => $item['title'] ?? '',
            'user_prompt'     => $meta['user_prompt'] ?? '',
            'prompt'          => $prompt,
            'filename'        => $meta['filename'] ?? '',
            'type'            => $type,
            'existing_titles' => $existing_titles,
        ]);

        $marketplace_status = (string) ($meta['marketplace_status'] ?? '');
        if ($marketplace_status === '' && !empty($item['marketplace'])) {
            $marketplace_status = 'listed';
            $meta['marketplace_status'] = $marketplace_status;
        }

        return [
            'id'               => sanitize_text_field($item['id'] ?? ('gal_' . wp_generate_uuid4())),
            'type'             => $type,
            'title'            => $title,
            'prompt'           => $prompt,
            'provider'         => sanitize_text_field($item['provider'] ?? 'mock'),
            'model'            => sanitize_text_field($item['model'] ?? ''),
            'job_id'           => sanitize_text_field($item['job_id'] ?? $meta['parent_job'] ?? ''),
            'user_id'          => (int) ($item['user_id'] ?? 0),
            'attachment_id'    => (int) ($item['attachment_id'] ?? $meta['attachment_id'] ?? 0),
            'credits_used'     => (int) ($item['credits_used'] ?? 0),
            'studio'           => sanitize_text_field($item['studio'] ?? $this->studio_from_type($type)),
            'image_url'        => $image_url,
            'thumbnail_url'    => $thumbnail_url ?: $image_url,
            'thumbnail'        => $thumbnail_url ?: $image_url,
            'output_url'       => $image_url,
            'output'           => is_array($item['output'] ?? null) ? $item['output'] : [],
            'favorite'         => !empty($item['favorite']),
            'public'           => !empty($item['public']),
            'marketplace'      => !empty($item['marketplace']),
            'community_shared' => !empty($item['community_shared']),
            'created_at'       => $item['created_at'] ?? gmdate('c'),
            'updated_at'       => gmdate('c'),
            'meta'             => $meta,
        ];
    }

    public function enrich_item(array $item): array {
        if (!class_exists('YooY_Gallery_Asset_Resolver')) {
            if (defined('YOY_AI_STUDIO_MODULES_DIR')) {
                $resolver = YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-asset-resolver.php';
                if (file_exists($resolver)) {
                    require_once $resolver;
                }
            }
        }
        $item = class_exists('YooY_Gallery_Asset_Resolver')
            ? YooY_Gallery_Asset_Resolver::enrich($item)
            : $item;

        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
        $asset_url = $item['image_url'] ?? $item['output_url'] ?? ($meta['asset_url'] ?? '');

        return array_merge($item, [
            'description'        => (string) ($meta['description'] ?? ''),
            'user_prompt'        => (string) ($meta['user_prompt'] ?? $item['prompt'] ?? ''),
            'optimized_prompt'   => (string) ($meta['optimized_prompt'] ?? ''),
            'negative_prompt'    => (string) ($meta['negative_prompt'] ?? ''),
            'reference_assets'   => is_array($meta['reference_assets'] ?? null) ? $meta['reference_assets'] : [],
            'settings'           => is_array($meta['settings'] ?? null) ? $meta['settings'] : [],
            'asset_url'          => $asset_url,
            'project_id'         => (string) ($meta['project_id'] ?? ''),
            'type_label'         => $this->type_label((string) ($item['type'] ?? 'image')),
            'visibility'         => !empty($item['public']) ? 'public' : 'private',
            'is_favorite'        => !empty($item['favorite']),
            'marketplace_status' => (string) ($meta['marketplace_status'] ?? (!empty($item['marketplace']) ? 'listed' : 'none')),
            'filename'           => (string) ($meta['filename'] ?? ''),
        ]);
    }

    private function has_valid_asset(array $entry): bool {
        if (!empty($entry['attachment_id'])) {
            return true;
        }
        $url = $entry['image_url'] ?? $entry['output_url'] ?? '';
        if (class_exists('YooY_Asset_Generator')) {
            return YooY_Asset_Generator::is_http_asset_url($url);
        }
        return is_string($url) && $url !== '';
    }

    private function sanitize_asset_url($url): string {
        if (class_exists('YooY_Asset_Generator')) {
            return YooY_Asset_Generator::sanitize_asset_url($url);
        }
        $url = is_string($url) ? trim($url) : '';
        if ($url === '') {
            return '';
        }
        $clean = esc_url_raw($url);
        return is_string($clean) ? $clean : '';
    }

    private function studio_from_type(string $type): string {
        switch ($type) {
            case 'video':
                return 'video-studio';
            case 'image':
                return 'image-studio';
            case 'music':
                return 'music-studio';
            case 'voice':
                return 'voice-studio';
            case 'avatar':
                return 'avatar-studio';
            case 'writing':
                return 'writing-studio';
            default:
                return 'unknown';
        }
    }

    private function type_label(string $type): string {
        switch ($type) {
            case 'video': return 'Video';
            case 'music': return 'Music';
            case 'voice': return 'Voice';
            case 'avatar': return 'Avatar';
            case 'writing': return 'Writing';
            default: return 'Image';
        }
    }

    private function collect_existing_titles(array $items, string $exclude_id = ''): array {
        $titles = [];
        foreach ($items as $existing) {
            if ($exclude_id !== '' && ($existing['id'] ?? '') === $exclude_id) {
                continue;
            }
            $title = trim((string) ($existing['title'] ?? ''));
            if ($title !== '') {
                $titles[] = $title;
            }
        }
        return $titles;
    }
}
