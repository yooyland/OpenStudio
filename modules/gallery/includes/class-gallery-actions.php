<?php
if (!defined('ABSPATH')) exit;

final class YooY_Gallery_Actions {

    private YooY_Gallery_Store $store;

    public function __construct(YooY_Gallery_Store $store) {
        $this->store = $store;
    }

    public function copy_prompt(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) throw new Exception('Item not found.');
        return ['prompt' => $item['prompt'], 'id' => $id];
    }

    public function regenerate_payload(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) throw new Exception('Item not found.');

        $studio = $item['studio'] ?? '';
        $user_prompt = (string) ($item['user_prompt'] ?? $item['prompt'] ?? '');
        $optimized = (string) ($item['optimized_prompt'] ?? '');
        $settings = is_array($item['settings'] ?? null) ? $item['settings'] : [];

        $payload = [
            'studio'           => $studio,
            'type'             => $item['type'],
            'prompt'           => $user_prompt !== '' ? $user_prompt : ($item['prompt'] ?? ''),
            'user_prompt'      => $user_prompt,
            'optimized_prompt' => $optimized,
            'provider'         => $settings['provider'] ?? $item['provider'] ?? 'auto',
            'model'            => $settings['model'] ?? $item['model'] ?? '',
            'reference_assets' => $item['reference_assets'] ?? [],
            'settings'         => $settings,
            'remix_source'     => ['gallery_id' => $id],
        ];

        switch ($item['type']) {
            case 'video':
                return array_merge($payload, [
                    'reference_url' => $item['output_url'] ?? $item['image_url'] ?? '',
                ]);
            case 'image':
                return array_merge($payload, [
                    'reference_url' => $item['output_url'] ?? $item['image_url'] ?? '',
                    'aspect_ratio'  => $settings['aspect_ratio'] ?? '1:1',
                    'quality'       => $settings['quality'] ?? 'standard',
                    'style'         => $settings['style'] ?? '',
                ]);
            case 'music':
                return array_merge($payload, [
                    'lyrics' => $user_prompt,
                    'reference_url' => $item['output_url'] ?? '',
                ]);
            case 'voice':
                return array_merge($payload, ['text' => $user_prompt]);
            case 'avatar':
                return array_merge($payload, ['script' => $user_prompt]);
            case 'writing':
                return $payload;
            case 'translation':
                $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
                return array_merge($payload, [
                    'text'               => $user_prompt,
                    'source_text'        => $user_prompt,
                    'translated_text'    => (string) ($meta['translated_text'] ?? $item['translated_text'] ?? ''),
                    'source_language'    => (string) ($meta['source_language'] ?? $settings['source_language'] ?? 'auto'),
                    'target_language'    => (string) ($meta['target_language'] ?? $settings['target_language'] ?? 'en'),
                    'mode'               => (string) ($meta['mode'] ?? $settings['mode'] ?? 'natural'),
                    'settings'           => array_merge($settings, [
                        'source_language' => (string) ($meta['source_language'] ?? $settings['source_language'] ?? 'auto'),
                        'target_language' => (string) ($meta['target_language'] ?? $settings['target_language'] ?? 'en'),
                        'mode'            => (string) ($meta['mode'] ?? $settings['mode'] ?? 'natural'),
                    ]),
                ]);
            default:
                return $payload;
        }
    }

    public function toggle_favorite(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) throw new Exception('Item not found.');
        return $this->store->update($user_id, $id, ['favorite' => !($item['favorite'] ?? false)]);
    }

    public function set_visibility(int $user_id, string $id, bool $public): array {
        $updated = $this->store->update($user_id, $id, ['public' => $public]);
        if (!$updated) throw new Exception('Item not found.');
        return $updated;
    }

    public function register_marketplace(int $user_id, string $id, array $options = []): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Item not found.');
        }

        $existing = $this->find_marketplace_listing($id);
        if ($existing && ($existing['status'] ?? '') !== 'delisted') {
            throw new Exception('이미 Marketplace에 등록된 작품입니다.');
        }

        $title = sanitize_text_field($options['title'] ?? $item['title'] ?? '');
        $description = sanitize_textarea_field($options['description'] ?? ($item['description'] ?? ''));
        $price = max(0, (int) ($options['price'] ?? 0));
        $category = sanitize_text_field($options['category'] ?? 'general');
        $tags = is_array($options['tags'] ?? null) ? array_map('sanitize_text_field', $options['tags']) : [];
        $license = sanitize_text_field($options['license'] ?? 'standard');
        $prompt_public = !empty($options['prompt_public']);
        $reference_public = !empty($options['reference_public']);
        $allow_download = !empty($options['allow_download']);
        $thumb = $this->public_preview_url($item);
        $user = wp_get_current_user();

        $listing = [
            'id'               => 'mkt_gal_' . $id,
            'gallery_id'       => $id,
            'owner_id'         => $user_id,
            'title'            => $title !== '' ? $title : (string) ($item['title'] ?? 'Work'),
            'description'      => $description,
            'prompt'           => $prompt_public ? (string) ($item['user_prompt'] ?? $item['prompt'] ?? '') : '',
            'type'             => $item['type'] ?? 'image',
            'thumbnail'        => $thumb,
            'thumbnail_url'    => $thumb,
            'display_url'      => $thumb,
            'image_url'        => $thumb,
            'creator'          => $user->display_name,
            'creator_name'     => $user->display_name,
            'price'            => $price,
            'tier'             => $price > 0 ? 'paid' : 'free',
            'category'         => $category !== '' ? $category : 'general',
            'tags'             => $tags,
            'license'          => $license !== '' ? $license : 'standard',
            'prompt_public'    => $prompt_public,
            'reference_public' => $reference_public,
            'allow_download'   => $allow_download,
            'status'           => 'listed',
            'created_at'       => gmdate('c'),
            'updated_at'       => gmdate('c'),
        ];

        $listings = get_user_meta($user_id, 'yoy_marketplace_listings', true);
        $listings = is_array($listings) ? $listings : [];
        $listings = array_values(array_filter($listings, function ($row) use ($id) {
            return ($row['gallery_id'] ?? '') !== $id;
        }));
        array_unshift($listings, $listing);
        update_user_meta($user_id, 'yoy_marketplace_listings', array_slice($listings, 0, 100));

        $global = get_option('yoy_marketplace_catalog', []);
        $global = is_array($global) ? $global : [];
        $global = array_values(array_filter($global, function ($row) use ($id) {
            return ($row['gallery_id'] ?? '') !== $id;
        }));
        array_unshift($global, $listing);
        update_option('yoy_marketplace_catalog', array_slice($global, 0, 200));

        $updated = $this->store->update($user_id, $id, [
            'marketplace'        => true,
            'marketplace_status' => 'listed',
            'public'             => true,
            'description'        => $description,
        ]);

        return [
            'item'    => $updated ? $updated : $this->store->get($user_id, $id),
            'listing' => $listing,
        ];
    }

    public function delist_marketplace(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Item not found.');
        }

        $listings = get_user_meta($user_id, 'yoy_marketplace_listings', true);
        if (is_array($listings)) {
            $listings = array_map(function ($row) use ($id) {
                if (($row['gallery_id'] ?? '') === $id) {
                    $row['status'] = 'delisted';
                    $row['updated_at'] = gmdate('c');
                }
                return $row;
            }, $listings);
            update_user_meta($user_id, 'yoy_marketplace_listings', $listings);
        }

        $global = get_option('yoy_marketplace_catalog', []);
        if (is_array($global)) {
            $global = array_values(array_filter($global, function ($row) use ($id, $user_id) {
                if (($row['gallery_id'] ?? '') !== $id) {
                    return true;
                }
                $owner = (int) ($row['owner_id'] ?? 0);
                return $owner > 0 && $owner !== $user_id;
            }));
            update_option('yoy_marketplace_catalog', $global);
        }

        $updated = $this->store->update($user_id, $id, [
            'marketplace'        => false,
            'marketplace_status' => 'none',
        ]);

        return [
            'item'    => $updated ? $updated : $this->store->get($user_id, $id),
            'delisted'=> true,
        ];
    }

    /**
     * @param array<string, mixed> $options caption optional
     */
    public function share_community(int $user_id, string $id, array $options = []): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Item not found.');
        }

        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        foreach ($feed as $row) {
            if (($row['gallery_id'] ?? '') === $id && ($row['status'] ?? 'active') !== 'inactive') {
                throw new Exception('이미 Community에 공유된 작품입니다.');
            }
        }

        $caption = sanitize_text_field($options['caption'] ?? $options['title'] ?? '');
        if ($caption === '') {
            $caption = (string) ($item['title'] ?? 'Work');
        }
        $thumb = $this->public_preview_url($item);
        $user = wp_get_current_user();

        $post = [
            'id'              => 'comm_' . wp_generate_uuid4(),
            'gallery_id'      => $id,
            'owner_id'        => $user_id,
            'type'            => $item['type'] ?? 'image',
            'title'           => $caption,
            'caption'         => $caption,
            'visibility'      => 'public',
            'status'          => 'active',
            'thumbnail'       => $thumb,
            'thumbnail_url'   => $thumb,
            'display_url'     => $thumb,
            'image_url'       => $thumb,
            'creator'         => $user->display_name,
            'creator_name'    => $user->display_name,
            'likes'           => 0,
            'created_at'      => gmdate('c'),
        ];

        array_unshift($feed, $post);
        update_option('yoy_community_feed', array_slice($feed, 0, 200));

        $updated = $this->store->update($user_id, $id, [
            'community_shared' => true,
            'public'           => true,
        ]);

        return [
            'item' => $updated ? $updated : $this->store->get($user_id, $id),
            'post' => $post,
        ];
    }

    public function unshare_community(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Item not found.');
        }

        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        $changed = false;
        foreach ($feed as $idx => $row) {
            if (($row['gallery_id'] ?? '') !== $id) {
                continue;
            }
            $owner = (int) ($row['owner_id'] ?? 0);
            if ($owner > 0 && $owner !== $user_id) {
                continue;
            }
            $feed[$idx]['status'] = 'inactive';
            $feed[$idx]['updated_at'] = gmdate('c');
            $changed = true;
        }
        if ($changed) {
            $feed = array_values(array_filter($feed, function ($row) {
                return ($row['status'] ?? 'active') !== 'inactive';
            }));
            update_option('yoy_community_feed', $feed);
        }

        $updated = $this->store->update($user_id, $id, ['community_shared' => false]);

        return [
            'item'      => $updated ? $updated : $this->store->get($user_id, $id),
            'unshared'  => true,
        ];
    }

    /**
     * Public-safe remix payload for another user's published gallery asset.
     *
     * @return array<string, mixed>
     */
    public function public_remix_payload(string $gallery_id): array {
        $gallery_id = sanitize_text_field($gallery_id);
        if ($gallery_id === '') {
            throw new Exception('gallery_id is required.');
        }

        $owner_id = 0;
        $preview = '';
        $title = '';
        $type = 'image';
        $prompt_public = '';

        $feed = get_option('yoy_community_feed', []);
        if (is_array($feed)) {
            foreach ($feed as $row) {
                if (($row['gallery_id'] ?? '') !== $gallery_id) {
                    continue;
                }
                if (($row['status'] ?? 'active') === 'inactive') {
                    continue;
                }
                $owner_id = (int) ($row['owner_id'] ?? 0);
                $preview = (string) ($row['thumbnail_url'] ?? $row['display_url'] ?? $row['thumbnail'] ?? '');
                $title = (string) ($row['caption'] ?? $row['title'] ?? '');
                $type = (string) ($row['type'] ?? 'image');
                break;
            }
        }

        if ($owner_id <= 0) {
            $catalog = get_option('yoy_marketplace_catalog', []);
            if (is_array($catalog)) {
                foreach ($catalog as $row) {
                    if (($row['gallery_id'] ?? '') !== $gallery_id) {
                        continue;
                    }
                    if (($row['status'] ?? '') === 'delisted') {
                        continue;
                    }
                    $owner_id = (int) ($row['owner_id'] ?? 0);
                    $preview = (string) ($row['thumbnail_url'] ?? $row['display_url'] ?? $row['thumbnail'] ?? '');
                    $title = (string) ($row['title'] ?? '');
                    $type = (string) ($row['type'] ?? 'image');
                    if (!empty($row['prompt_public']) && !empty($row['prompt'])) {
                        $prompt_public = (string) $row['prompt'];
                    }
                    break;
                }
            }
        }

        if ($owner_id <= 0) {
            throw new Exception('Public work not found.');
        }

        $item = $this->store->get($owner_id, $gallery_id);
        if (!$item || (empty($item['public']) && empty($item['community_shared']) && empty($item['marketplace']))) {
            throw new Exception('Public work not found.');
        }

        if ($preview === '') {
            $preview = $this->public_preview_url($item);
        }
        if ($title === '') {
            $title = (string) ($item['title'] ?? 'Work');
        }
        $type = (string) ($item['type'] ?? $type);
        $studio = (string) ($item['studio'] ?? ($type . '-studio'));

        $payload = [
            'studio'           => $studio,
            'type'             => $type,
            'prompt'           => $prompt_public,
            'user_prompt'      => $prompt_public,
            'optimized_prompt' => '',
            'provider'         => 'auto',
            'model'            => '',
            'reference_assets' => $preview !== '' ? [[
                'gallery_id' => $gallery_id,
                'url'        => $preview,
                'type'       => $type,
                'label'      => $title,
            ]] : [],
            'settings'         => [],
            'remix_source'     => [
                'gallery_id' => $gallery_id,
                'public'     => true,
                'title'      => $title,
            ],
            'reference_url'    => $preview,
            'thumbnail_url'    => $preview,
            'preview_url'      => $preview,
            'title'            => $title,
            'gallery_id'       => $gallery_id,
            'public_safe'      => true,
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function find_marketplace_listing(string $gallery_id): ?array {
        $global = get_option('yoy_marketplace_catalog', []);
        if (!is_array($global)) {
            return null;
        }
        foreach ($global as $row) {
            if (($row['gallery_id'] ?? '') === $gallery_id) {
                return $row;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function public_preview_url(array $item): string {
        $url = (string) (
            $item['thumbnail_url']
            ?? $item['display_url']
            ?? $item['large_url']
            ?? $item['image_url']
            ?? $item['output_url']
            ?? $item['asset_url']
            ?? $item['thumbnail']
            ?? ''
        );
        return esc_url_raw($url);
    }

    public function download_info(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) throw new Exception('Item not found.');
        $url = $item['output_url'] ?? '';
        if ($url === '') throw new Exception('No downloadable file.');
        return [
            'url'      => $url,
            'filename' => sanitize_file_name(($item['title'] ?: 'yoy-' . $item['type']) . $this->ext($item['type'])),
            'type'     => $item['type'],
        ];
    }

    public function publish_to_gallery(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            $job = (new YooY_Job_Store())->get($user_id, $id);
            if (!$job) throw new Exception('Item not found.');
            if (function_exists('yoy_gallery_capture')) {
                yoy_gallery_capture($user_id, $job, $job['type'] ?? 'music', $job['studio'] ?? 'music-studio');
            }
            $item = $this->store->get($user_id, $id);
            if (!$item) throw new Exception('Failed to publish item.');
        }
        $updated = $this->store->update($user_id, $id, ['public' => true]);
        if (!$updated) throw new Exception('Failed to publish item.');
        return ['item' => $updated, 'published' => true];
    }

    public function save_to_project(int $user_id, string $id, ?string $project_id = null): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Gallery item not found.');
        }

        if (!class_exists('YooY_Project_Store')) {
            if (defined('YOY_AI_STUDIO_MODULES_DIR')) {
                require_once YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php';
            }
        }
        if (!class_exists('YooY_Project_Store')) {
            throw new Exception('Project store unavailable.');
        }

        $project_store = new YooY_Project_Store();
        $current_project_id = (string) ($item['project_id'] ?? '');

        if ($project_id === '') {
            if ($current_project_id !== '') {
                $project_store->unlink_gallery_item($user_id, $current_project_id, $id);
            }
            $updated = $this->store->update($user_id, $id, ['project_id' => '']);
            return [
                'project' => null,
                'item'    => $updated,
                'removed' => true,
            ];
        }

        $target_project = null;
        if ($project_id !== null && $project_id !== '') {
            $target_project = $project_store->get($user_id, $project_id);
        }

        if (!$target_project) {
            $target_project = $project_store->create($user_id, [
                'title'       => 'My Project',
                'description' => '',
                'type'        => $item['type'] ?? 'mixed',
                'visibility'  => 'private',
                'status'      => 'active',
                'assets'      => [],
            ]);
        }

        if ($current_project_id !== '' && $current_project_id !== ($target_project['id'] ?? '')) {
            $project_store->unlink_gallery_item($user_id, $current_project_id, $id);
        }

        $project = $project_store->link_gallery_item($user_id, $target_project['id'], $item);
        $updated = $this->store->update($user_id, $id, ['project_id' => $target_project['id'] ?? '']);
        $project_store->sync_asset_counts($user_id);

        return [
            'project' => $project,
            'item'    => $updated,
            'asset'   => [
                'gallery_id' => $id,
                'type'       => $item['type'] ?? '',
                'title'      => $item['title'] ?? '',
            ],
        ];
    }

    public function delete_item(int $user_id, string $id, bool $delete_media = false): bool {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Item not found.');
        }

        $projects = get_user_meta($user_id, 'yoy_projects', true);
        if (is_array($projects)) {
            foreach ($projects as $pidx => $project) {
                $assets = is_array($project['assets'] ?? null) ? $project['assets'] : [];
                $assets = array_values(array_filter($assets, function ($asset) use ($id) {
                    return ($asset['gallery_id'] ?? '') !== $id;
                }));
                $projects[$pidx]['assets'] = $assets;
                $projects[$pidx]['items'] = count($assets);
            }
            update_user_meta($user_id, 'yoy_projects', $projects);
        }

        $listings = get_user_meta($user_id, 'yoy_marketplace_listings', true);
        if (is_array($listings)) {
            $listings = array_values(array_filter($listings, function ($listing) use ($id) {
                return ($listing['gallery_id'] ?? '') !== $id;
            }));
            update_user_meta($user_id, 'yoy_marketplace_listings', $listings);
        }

        $global = get_option('yoy_marketplace_catalog', []);
        if (is_array($global)) {
            $global = array_values(array_filter($global, function ($listing) use ($id) {
                return ($listing['gallery_id'] ?? '') !== $id;
            }));
            update_option('yoy_marketplace_catalog', $global);
        }

        $feed = get_option('yoy_community_feed', []);
        if (is_array($feed)) {
            $feed = array_values(array_filter($feed, function ($post) use ($id, $user_id) {
                if (($post['gallery_id'] ?? '') !== $id) {
                    return true;
                }
                $owner = (int) ($post['owner_id'] ?? 0);
                return $owner > 0 && $owner !== $user_id;
            }));
            update_option('yoy_community_feed', $feed);
        }

        if ($delete_media && !empty($item['attachment_id']) && current_user_can('manage_options')) {
            wp_delete_attachment((int) $item['attachment_id'], true);
        }

        return $this->store->remove($user_id, $id);
    }

    public function duplicate_item(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Item not found.');
        }
        unset($item['id'], $item['job_id']);
        $item['id'] = 'gal_' . wp_generate_uuid4();
        $item['title'] = ($item['title'] ?? 'Work') . ' (복제)';
        $item['favorite'] = false;
        $item['marketplace'] = false;
        $item['community_shared'] = false;
        $item['public'] = false;
        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
        $meta['marketplace_status'] = 'none';
        $item['meta'] = $meta;
        return $this->store->save($user_id, $item);
    }

    public function share_link(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) {
            throw new Exception('Item not found.');
        }
        $url = $item['asset_url'] ?? $item['output_url'] ?? $item['image_url'] ?? '';
        if ($url === '' && ($item['type'] ?? '') === 'translation') {
            $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
            $text = (string) ($meta['translated_text'] ?? $item['translated_text'] ?? '');
            if ($text === '') {
                throw new Exception('No shareable translation text.');
            }
            return [
                'url'   => '',
                'text'  => $text,
                'title' => $item['title'] ?? '',
                'type'  => 'translation',
            ];
        }
        if ($url === '') {
            throw new Exception('No shareable asset URL.');
        }
        return ['url' => $url, 'title' => $item['title'] ?? ''];
    }

    private function ext(string $type): string {
        switch ($type) {
            case 'video':
            case 'avatar':
                return '.mp4';
            case 'music':
            case 'voice':
                return '.mp3';
            case 'image':
                return '.png';
            case 'translation':
            case 'writing':
                return '.txt';
            default:
                return '.txt';
        }
    }
}
