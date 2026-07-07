<?php
if (!defined('ABSPATH')) exit;

final class YooY_Project_Store {

    private const META_KEY = 'yoy_projects';

    public function list(int $user_id, int $limit = 0): array {
        $items = $this->get_all($user_id);
        usort($items, function ($a, $b) {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
        return $items;
    }

    public function count(int $user_id): int {
        return count($this->get_all($user_id));
    }

    public function get(int $user_id, string $id): ?array {
        foreach ($this->get_all($user_id) as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    public function create(int $user_id, array $data): array {
        $entry = $this->normalize(array_merge($data, [
            'id'         => 'proj_' . wp_generate_uuid4(),
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ]), $user_id);

        $items = $this->get_all($user_id);
        array_unshift($items, $entry);
        $items = array_slice($items, 0, 200);
        update_user_meta($user_id, self::META_KEY, $items);

        return $entry;
    }

    public function update(int $user_id, string $id, array $data): ?array {
        $items = $this->get_all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            if (isset($data['title'])) {
                $title = sanitize_text_field($data['title']);
                if ($title !== '') {
                    $items[$idx]['title'] = $title;
                }
            }
            if (isset($data['description'])) {
                $items[$idx]['description'] = sanitize_textarea_field($data['description']);
            }
            if (isset($data['visibility'])) {
                $vis = sanitize_text_field($data['visibility']);
                if (in_array($vis, ['private', 'public'], true)) {
                    $items[$idx]['visibility'] = $vis;
                }
            }
            if (isset($data['type'])) {
                $type = sanitize_text_field($data['type']);
                $allowed = ['mixed', 'video', 'image', 'music', 'writing', 'avatar', 'voice'];
                if (in_array($type, $allowed, true)) {
                    $items[$idx]['type'] = $type;
                }
            }
            if (isset($data['thumbnail_url'])) {
                $items[$idx]['thumbnail_url'] = esc_url_raw($data['thumbnail_url']);
            }
            if (isset($data['cover_asset_id'])) {
                $items[$idx]['cover_asset_id'] = sanitize_text_field($data['cover_asset_id']);
            }
            $items[$idx]['updated_at'] = gmdate('c');
            update_user_meta($user_id, self::META_KEY, $items);
            return $this->normalize($items[$idx], $user_id);
        }
        return null;
    }

    public function delete(int $user_id, string $id): bool {
        $items = $this->get_all($user_id);
        $before = count($items);
        $items = array_values(array_filter($items, function ($item) use ($id) {
            return ($item['id'] ?? '') !== $id;
        }));
        update_user_meta($user_id, self::META_KEY, $items);

        if (class_exists('YooY_Gallery_Store')) {
            $gallery = new YooY_Gallery_Store();
            foreach ($gallery->get_all($user_id) as $work) {
                $meta = is_array($work['meta'] ?? null) ? $work['meta'] : [];
                if (($meta['project_id'] ?? '') === $id) {
                    $gallery->update($user_id, $work['id'], ['project_id' => '']);
                }
            }
        }

        return count($items) < $before;
    }

    public function add_asset(int $user_id, string $project_id, array $asset): ?array {
        $items = $this->get_all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $project_id) {
                continue;
            }
            $assets = is_array($item['assets'] ?? null) ? $item['assets'] : [];
            $entry = [
                'id'         => sanitize_text_field($asset['id'] ?? ('asset_' . wp_generate_uuid4())),
                'gallery_id' => sanitize_text_field($asset['gallery_id'] ?? ''),
                'type'       => sanitize_text_field($asset['type'] ?? 'image'),
                'title'      => sanitize_text_field($asset['title'] ?? 'Asset'),
                'url'        => esc_url_raw($asset['url'] ?? $asset['asset_url'] ?? $asset['output_url'] ?? ''),
                'thumbnail'  => esc_url_raw($asset['thumbnail'] ?? $asset['thumbnail_url'] ?? ''),
                'added_at'   => gmdate('c'),
            ];
            $assets = array_values(array_filter($assets, function ($a) use ($entry) {
                return ($a['gallery_id'] ?? '') !== ($entry['gallery_id'] ?? '') || ($entry['gallery_id'] ?? '') === '';
            }));
            array_unshift($assets, $entry);
            $items[$idx]['assets'] = array_slice($assets, 0, 200);
            $items[$idx]['items'] = count($items[$idx]['assets']);
            $items[$idx]['asset_count'] = count($items[$idx]['assets']);
            $items[$idx]['updated_at'] = gmdate('c');
            $this->apply_thumbnail_from_assets($items[$idx]);
            update_user_meta($user_id, self::META_KEY, $items);
            return $this->normalize($items[$idx], $user_id);
        }
        return null;
    }

    public function link_gallery_item(int $user_id, string $project_id, array $item): ?array {
        $gallery_id = sanitize_text_field($item['id'] ?? '');
        if ($gallery_id === '') {
            return null;
        }

        $asset = [
            'gallery_id'    => $gallery_id,
            'type'          => $item['type'] ?? 'image',
            'title'         => $item['title'] ?? 'Work',
            'url'           => $item['image_url'] ?? $item['output_url'] ?? $item['asset_url'] ?? '',
            'thumbnail'     => $item['thumbnail_url'] ?? $item['thumbnail'] ?? '',
            'thumbnail_url' => $item['thumbnail_url'] ?? $item['thumbnail'] ?? '',
        ];

        return $this->add_asset($user_id, $project_id, $asset);
    }

    public function unlink_gallery_item(int $user_id, string $project_id, string $gallery_id): ?array {
        return $this->remove_asset($user_id, $project_id, $gallery_id);
    }

    public function add_reference_asset(int $user_id, string $project_id, array $asset): ?array {
        $items = $this->get_all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $project_id) {
                continue;
            }
            $refs = is_array($item['reference_assets'] ?? null) ? $item['reference_assets'] : [];
            $entry = [
                'id'            => sanitize_text_field($asset['id'] ?? ('rasset_' . wp_generate_uuid4())),
                'title'         => sanitize_text_field($asset['title'] ?? 'Reference'),
                'type'          => sanitize_text_field($asset['asset_type'] ?? $asset['type'] ?? 'image'),
                'role'          => sanitize_text_field($asset['role'] ?? ''),
                'url'           => esc_url_raw($asset['url'] ?? ''),
                'thumbnail'     => esc_url_raw($asset['thumbnail_url'] ?? $asset['thumbnail'] ?? ''),
                'attachment_id' => (int) ($asset['attachment_id'] ?? 0),
                'added_at'      => gmdate('c'),
            ];
            $refs = array_values(array_filter($refs, function ($r) use ($entry) {
                return ($r['id'] ?? '') !== $entry['id'];
            }));
            array_unshift($refs, $entry);
            $items[$idx]['reference_assets'] = array_slice($refs, 0, 50);
            $items[$idx]['updated_at'] = gmdate('c');
            update_user_meta($user_id, self::META_KEY, $items);
            return $this->normalize($items[$idx], $user_id);
        }
        return null;
    }

    public function remove_asset(int $user_id, string $project_id, string $asset_id): ?array {
        $items = $this->get_all($user_id);
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $project_id) {
                continue;
            }
            $assets = is_array($item['assets'] ?? null) ? $item['assets'] : [];
            $assets = array_values(array_filter($assets, function ($a) use ($asset_id) {
                return ($a['id'] ?? '') !== $asset_id && ($a['gallery_id'] ?? '') !== $asset_id;
            }));
            $items[$idx]['assets'] = $assets;
            $items[$idx]['items'] = count($assets);
            $items[$idx]['asset_count'] = count($assets);
            $items[$idx]['updated_at'] = gmdate('c');
            $this->apply_thumbnail_from_assets($items[$idx]);
            update_user_meta($user_id, self::META_KEY, $items);
            return $this->normalize($items[$idx], $user_id);
        }
        return null;
    }

    public function sync_asset_counts(int $user_id): void {
        if (!class_exists('YooY_Gallery_Store')) {
            return;
        }

        $gallery = new YooY_Gallery_Store();
        $counts = [];
        foreach ($gallery->get_all($user_id) as $work) {
            $meta = is_array($work['meta'] ?? null) ? $work['meta'] : [];
            $pid = (string) ($meta['project_id'] ?? '');
            if ($pid !== '') {
                $counts[$pid] = ($counts[$pid] ?? 0) + 1;
            }
        }

        $items = $this->get_all($user_id);
        $changed = false;
        foreach ($items as $idx => $item) {
            $pid = $item['id'] ?? '';
            $count = (int) ($counts[$pid] ?? count($item['assets'] ?? []));
            if ((int) ($item['asset_count'] ?? 0) !== $count) {
                $items[$idx]['asset_count'] = $count;
                $items[$idx]['items'] = $count;
                $changed = true;
            }
            if (empty($item['thumbnail_url'])) {
                $before = $items[$idx]['thumbnail_url'] ?? '';
                $this->apply_thumbnail_from_assets($items[$idx]);
                if (($items[$idx]['thumbnail_url'] ?? '') !== $before) {
                    $changed = true;
                }
            }
        }

        if ($changed) {
            update_user_meta($user_id, self::META_KEY, $items);
        }
    }

    public function title_map(int $user_id): array {
        $map = [];
        foreach ($this->get_all($user_id) as $project) {
            $map[$project['id'] ?? ''] = $project['title'] ?? '';
        }
        return $map;
    }

    public function get_all(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        if (!is_array($stored)) {
            return [];
        }
        $items = [];
        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }
            $items[] = $this->normalize($item, $user_id);
        }
        return $items;
    }

    private function apply_thumbnail_from_assets(array &$item): void {
        $cover_id = (string) ($item['cover_asset_id'] ?? '');
        $assets = is_array($item['assets'] ?? null) ? $item['assets'] : [];

        if ($cover_id !== '') {
            foreach ($assets as $asset) {
                if (($asset['id'] ?? '') === $cover_id || ($asset['gallery_id'] ?? '') === $cover_id) {
                    $thumb = $asset['thumbnail'] ?? $asset['thumbnail_url'] ?? $asset['url'] ?? '';
                    if ($thumb !== '') {
                        $item['thumbnail_url'] = esc_url_raw($thumb);
                        return;
                    }
                }
            }
        }

        foreach ($assets as $asset) {
            $thumb = $asset['thumbnail'] ?? $asset['thumbnail_url'] ?? $asset['url'] ?? '';
            $type = $asset['type'] ?? 'image';
            if ($thumb !== '' && in_array($type, ['image', 'video', 'avatar'], true)) {
                $item['thumbnail_url'] = esc_url_raw($thumb);
                if ($cover_id === '' && !empty($asset['gallery_id'])) {
                    $item['cover_asset_id'] = sanitize_text_field($asset['gallery_id']);
                }
                return;
            }
        }

        if (empty($item['thumbnail_url'])) {
            $item['thumbnail_url'] = '';
        }
    }

    private function normalize(array $item, int $user_id): array {
        $type = sanitize_text_field($item['type'] ?? 'mixed');
        $allowed_types = ['mixed', 'video', 'image', 'music', 'writing', 'avatar', 'voice'];
        if (!in_array($type, $allowed_types, true)) {
            $type = 'mixed';
        }

        $visibility = sanitize_text_field($item['visibility'] ?? 'private');
        if (!in_array($visibility, ['private', 'public'], true)) {
            $visibility = 'private';
        }

        $title = sanitize_text_field($item['title'] ?? '');
        if ($title === '') {
            $title = '새 프로젝트';
        }

        $assets = is_array($item['assets'] ?? null) ? $item['assets'] : [];
        $asset_count = (int) ($item['asset_count'] ?? $item['items'] ?? count($assets));

        $normalized = [
            'id'               => sanitize_text_field($item['id'] ?? ('proj_' . wp_generate_uuid4())),
            'user_id'          => $user_id,
            'title'            => $title,
            'description'      => sanitize_textarea_field($item['description'] ?? ''),
            'type'             => $type,
            'visibility'       => $visibility,
            'status'           => sanitize_text_field($item['status'] ?? 'active'),
            'items'            => $asset_count,
            'asset_count'      => $asset_count,
            'assets'           => $assets,
            'reference_assets' => is_array($item['reference_assets'] ?? null) ? $item['reference_assets'] : [],
            'thumbnail_url'    => esc_url_raw($item['thumbnail_url'] ?? ''),
            'cover_asset_id'   => sanitize_text_field($item['cover_asset_id'] ?? ''),
            'created_at'       => $item['created_at'] ?? gmdate('c'),
            'updated_at'       => $item['updated_at'] ?? gmdate('c'),
        ];

        $this->apply_thumbnail_from_assets($normalized);
        return $normalized;
    }
}
