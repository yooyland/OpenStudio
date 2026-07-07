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
        if (!$item) throw new Exception('Item not found.');

        $title = sanitize_text_field($options['title'] ?? $item['title'] ?? '');
        $description = sanitize_textarea_field($options['description'] ?? ($item['description'] ?? ''));
        $price = max(0, (int) ($options['price'] ?? 0));
        $category = sanitize_text_field($options['category'] ?? 'general');
        $tags = is_array($options['tags'] ?? null) ? array_map('sanitize_text_field', $options['tags']) : [];
        $license = sanitize_text_field($options['license'] ?? 'standard');
        $prompt_public = !empty($options['prompt_public']);
        $reference_public = !empty($options['reference_public']);
        $allow_download = !empty($options['allow_download']);

        $listing = [
            'id'               => 'mkt_gal_' . $id,
            'gallery_id'       => $id,
            'title'            => $title !== '' ? $title : $item['title'],
            'description'      => $description,
            'prompt'           => $prompt_public ? ($item['user_prompt'] ?? $item['prompt']) : '',
            'type'             => $item['type'],
            'provider'         => $item['provider'],
            'creator'          => wp_get_current_user()->display_name,
            'price'            => $price,
            'tier'             => $price > 0 ? 'paid' : 'free',
            'category'         => $category,
            'tags'             => $tags,
            'license'          => $license,
            'prompt_public'    => $prompt_public,
            'reference_public' => $reference_public,
            'allow_download'   => $allow_download,
            'status'           => 'draft',
            'created_at'       => gmdate('c'),
        ];

        $listings = get_user_meta($user_id, 'yoy_marketplace_listings', true);
        $listings = is_array($listings) ? $listings : [];
        array_unshift($listings, $listing);
        update_user_meta($user_id, 'yoy_marketplace_listings', array_slice($listings, 0, 100));

        $global = get_option('yoy_marketplace_catalog', []);
        $global = is_array($global) ? $global : [];
        array_unshift($global, $listing);
        update_option('yoy_marketplace_catalog', array_slice($global, 0, 200));

        $updated = $this->store->update($user_id, $id, [
            'marketplace' => true,
            'marketplace_status' => 'draft',
            'description' => $description,
        ]);
        return ['item' => $this->store->get($user_id, $id), 'listing' => $listing, 'draft' => true];
    }

    public function share_community(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) throw new Exception('Item not found.');

        $post = [
            'id'         => 'comm_' . wp_generate_uuid4(),
            'gallery_id' => $id,
            'type'       => $item['type'],
            'title'      => $item['title'],
            'prompt'     => $item['prompt'],
            'thumbnail'  => $item['thumbnail'],
            'creator'    => wp_get_current_user()->display_name,
            'likes'      => 0,
            'created_at' => gmdate('c'),
        ];

        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        array_unshift($feed, $post);
        update_option('yoy_community_feed', array_slice($feed, 0, 200));

        $updated = $this->store->update($user_id, $id, ['community_shared' => true, 'public' => true]);
        return ['item' => $updated, 'post' => $post];
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
            default:
                return '.txt';
        }
    }
}
