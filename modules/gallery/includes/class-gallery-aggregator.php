<?php
if (!defined('ABSPATH')) exit;

final class YooY_Gallery_Aggregator {

    private YooY_Gallery_Store $store;

    private array $sources = [
        'video'  => ['meta' => 'yoy_video_gallery',  'studio' => 'video-studio'],
        'image'  => ['meta' => 'yoy_image_gallery',  'studio' => 'image-studio'],
        'music'  => ['meta' => 'yoy_music_gallery',   'studio' => 'music-studio'],
        'voice'  => ['meta' => 'yoy_voice_gallery',   'studio' => 'voice-studio'],
        'avatar' => ['meta' => 'yoy_avatar_gallery',  'studio' => 'avatar-studio'],
        'writing'=> ['meta' => 'yoy_writing_gallery', 'studio' => 'writing-studio'],
    ];

    public function __construct(YooY_Gallery_Store $store) {
        $this->store = $store;
    }

    public function sync(int $user_id): array {
        $this->reconcile_jobs($user_id);
        $this->import_legacy_sources($user_id);
        return $this->store->list($user_id, []);
    }

    /**
     * Import legacy per-studio gallery/history metas into the unified store.
     * Existing canonical items in yoy_gallery_items are never replaced wholesale.
     */
    private function import_legacy_sources(int $user_id): void {
        foreach ($this->sources as $type => $config) {
            $raw = get_user_meta($user_id, $config['meta'], true);
            if (!is_array($raw)) {
                continue;
            }

            foreach ($raw as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $this->import_legacy_entry($user_id, $type, $entry, $config['studio']);
            }
        }

        $history_sources = [
            ['meta' => 'yoy_video_history', 'type' => 'video', 'studio' => 'video-studio'],
            ['meta' => 'yoy_image_history', 'type' => 'image', 'studio' => 'image-studio'],
            ['meta' => 'yoy_music_history', 'type' => 'music', 'studio' => 'music-studio'],
            ['meta' => 'yoy_voice_history', 'type' => 'voice', 'studio' => 'voice-studio'],
            ['meta' => 'yoy_avatar_history', 'type' => 'avatar', 'studio' => 'avatar-studio'],
        ];

        foreach ($history_sources as $src) {
            $raw = get_user_meta($user_id, $src['meta'], true);
            if (!is_array($raw)) {
                continue;
            }
            foreach ($raw as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $this->import_legacy_history($user_id, $src['type'], $src['studio'], $entry);
            }
        }
    }

    private function import_legacy_entry(int $user_id, string $type, array $entry, string $studio): void {
        $normalized = $this->map_entry($type, $entry, $studio);
        $id = (string) ($normalized['id'] ?? '');
        if ($id === '' || $this->store->get($user_id, $id)) {
            return;
        }
        try {
            $this->store->save($user_id, $normalized);
        } catch (Exception $e) {
            // Skip invalid legacy rows.
        }
    }

    private function import_legacy_history(int $user_id, string $type, string $studio, array $entry): void {
        if ($type === 'image' && !empty($entry['images']) && is_array($entry['images'])) {
            $job_id = (string) ($entry['job_id'] ?? $entry['id'] ?? '');
            foreach ($entry['images'] as $i => $img) {
                if (!is_array($img)) {
                    continue;
                }
                $id = $job_id !== '' ? ($job_id . '_' . $i) : (string) ($entry['id'] ?? ('gal_' . wp_generate_uuid4()));
                if ($this->store->get($user_id, $id)) {
                    continue;
                }
                $url = $img['url'] ?? $img['image_url'] ?? '';
                $thumb = $img['thumbnail'] ?? $img['thumbnail_url'] ?? $url;
                $attachment_id = (int) ($img['attachment_id'] ?? 0);
                if (!$this->is_http_url($url) && $attachment_id <= 0) {
                    continue;
                }
                try {
                    $this->store->save($user_id, [
                        'id'            => $id,
                        'type'          => 'image',
                        'studio'        => $studio,
                        'title'         => $entry['title'] ?? '',
                        'prompt'        => $entry['prompt'] ?? '',
                        'user_prompt'   => $entry['user_prompt'] ?? '',
                        'provider'      => $entry['provider'] ?? $entry['provider_used'] ?? 'mock',
                        'model'         => $entry['model'] ?? '',
                        'job_id'        => $job_id,
                        'attachment_id' => $attachment_id,
                        'image_url'     => $url,
                        'thumbnail_url' => $thumb,
                        'thumbnail'     => $thumb,
                        'output_url'    => $url,
                        'credits_used'  => (int) ($entry['credits_used'] ?? 0),
                        'created_at'    => $entry['created_at'] ?? gmdate('c'),
                    ]);
                } catch (Exception $e) {
                    // Skip invalid history rows.
                }
            }
            return;
        }

        $normalized = $this->map_history($type, $entry);
        $id = (string) ($normalized['id'] ?? '');
        if ($id === '' || $this->store->get($user_id, $id)) {
            return;
        }
        try {
            $this->store->save($user_id, $normalized);
        } catch (Exception $e) {
            // Skip invalid history rows.
        }
    }

    public function from_generation(int $user_id, array $result, string $type, string $studio): array {
        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        $user_prompt = (string) ($result['user_prompt'] ?? $meta['user_prompt'] ?? '');
        $optimized = (string) ($result['optimized_prompt'] ?? $meta['optimized_prompt'] ?? '');

        $item = [
            'id'           => $result['job_id'] ?? $result['id'] ?? ('gal_' . wp_generate_uuid4()),
            'type'         => $type,
            'title'        => $result['title'] ?? '',
            'prompt'       => $result['prompt'] ?? $result['script'] ?? $result['text'] ?? $result['lyrics'] ?? '',
            'user_prompt'  => $user_prompt,
            'optimized_prompt' => $optimized,
            'negative_prompt'  => $result['negative_prompt'] ?? '',
            'provider'     => $result['provider'] ?? $result['provider_used'] ?? 'mock',
            'model'        => $result['model'] ?? '',
            'credits_used' => (int) ($result['credits_used'] ?? 0),
            'studio'       => $studio,
            'output'       => $result['output'] ?? [],
            'created_at'   => $result['created_at'] ?? gmdate('c'),
            'settings'     => [
                'aspect_ratio' => $result['aspect_ratio'] ?? '',
                'quality'      => $result['quality'] ?? '',
                'style'        => $result['style'] ?? '',
            ],
            'meta'         => array_merge($meta, [
                'user_prompt'      => $user_prompt,
                'optimized_prompt' => $optimized,
                'reference_url'    => $result['reference_url'] ?? '',
                'reference_assets' => $result['reference_assets'] ?? [],
            ]),
        ];

        if ($type === 'video' || $type === 'avatar') {
            $item['output_url'] = $result['output']['video_url'] ?? $result['output']['url'] ?? '';
            $item['thumbnail'] = $result['output']['thumbnail'] ?? '';
        } elseif ($type === 'image') {
            $img = ($result['images'] ?? [])[0] ?? [];
            $item['output_url'] = $img['url'] ?? $result['output']['url'] ?? '';
            $item['thumbnail'] = $img['thumbnail'] ?? $img['url'] ?? '';
            $item['image_url'] = $item['output_url'];
            $item['thumbnail_url'] = $item['thumbnail'];
            $item['attachment_id'] = (int) ($img['attachment_id'] ?? 0);
            $item['job_id'] = $result['job_id'] ?? '';
        } elseif ($type === 'music' || $type === 'voice') {
            $item['output_url'] = $result['output']['audio_url'] ?? '';
            $item['thumbnail'] = $result['output']['cover_url'] ?? '';
        }

        return $this->store->save($user_id, $item);
    }

    /**
     * Auto-sync completed jobs from the unified job store into gallery.
     * Runs on gallery load — users never need to press a manual sync button.
     */
    public function reconcile_jobs(int $user_id): int {
        if (!class_exists('YooY_Job_Store')) {
            return 0;
        }

        $job_store = new YooY_Job_Store();
        $added     = 0;

        foreach ($job_store->all($user_id) as $job) {
            if (($job['status'] ?? '') !== YooY_Job_Status::COMPLETED) {
                continue;
            }

            $type   = sanitize_text_field($job['type'] ?? 'image');
            $studio = sanitize_text_field($job['studio'] ?? ($type . '-studio'));
            $job_id = (string) ($job['job_id'] ?? '');

            if ($type === 'image' && !empty($job['images']) && is_array($job['images'])) {
                foreach ($job['images'] as $i => $img) {
                    if (!is_array($img)) {
                        continue;
                    }
                    $id = $job_id . '_' . $i;
                    if ($id === '_' || $this->store->get($user_id, $id)) {
                        continue;
                    }
                    $url = $img['url'] ?? $img['image_url'] ?? '';
                    if (!$this->is_http_url($url)) {
                        continue;
                    }
                    try {
                        $thumb = $img['thumbnail'] ?? $img['thumbnail_url'] ?? $url;
                        $user_prompt = (string) ($job['user_prompt'] ?? $job['prompt'] ?? '');
                        $this->store->save($user_id, [
                            'id'            => $id,
                            'type'          => 'image',
                            'studio'        => $studio,
                            'title'         => '',
                            'prompt'        => $job['prompt'] ?? '',
                            'user_prompt'   => $user_prompt,
                            'optimized_prompt' => $job['optimized_prompt'] ?? '',
                            'provider'      => $job['provider'] ?? $job['provider_used'] ?? 'mock',
                            'model'         => $job['model'] ?? '',
                            'job_id'        => $job_id,
                            'user_id'       => $user_id,
                            'attachment_id' => (int) ($img['attachment_id'] ?? 0),
                            'credits_used'  => (int) ($job['credits_used'] ?? 0),
                            'image_url'     => $url,
                            'thumbnail_url' => $thumb,
                            'thumbnail'     => $thumb,
                            'output_url'    => $url,
                            'created_at'    => $job['created_at'] ?? gmdate('c'),
                            'settings'      => [
                                'aspect_ratio' => $job['aspect_ratio'] ?? '1:1',
                                'quality'      => $job['quality'] ?? '',
                                'style'        => $job['style'] ?? '',
                            ],
                            'meta'          => [
                                'aspect_ratio'     => $job['aspect_ratio'] ?? '1:1',
                                'optimized_prompt' => $job['optimized_prompt'] ?? '',
                                'user_prompt'      => $user_prompt,
                                'auto_synced'      => true,
                                'index'            => $i,
                            ],
                        ]);
                        $added++;
                    } catch (Exception $e) {
                        // Skip items without valid assets.
                    }
                }
                continue;
            }

            if ($job_id === '' || $this->store->get($user_id, $job_id)) {
                continue;
            }

            try {
                $this->from_generation($user_id, $job, $type, $studio);
                $added++;
            } catch (Exception $e) {
                // Skip invalid entries.
            }
        }

        return $added;
    }

    private function is_http_url(string $url): bool {
        if ($url === '') {
            return false;
        }
        if (class_exists('YooY_Asset_Generator') && method_exists('YooY_Asset_Generator', 'is_http_asset_url')) {
            return YooY_Asset_Generator::is_http_asset_url($url);
        }
        return (bool) preg_match('#^https?://#i', $url);
    }

    private function map_entry(string $type, array $entry, string $studio): array {
        $output_url = $entry['url'] ?? $entry['video_url'] ?? $entry['audio_url'] ?? ($entry['output']['audio_url'] ?? $entry['output']['url'] ?? $entry['image_url'] ?? '');
        $thumbnail = $entry['thumbnail'] ?? $entry['thumbnail_url'] ?? $entry['cover_url'] ?? $output_url;

        return [
            'id'           => $entry['id'] ?? ('gal_' . wp_generate_uuid4()),
            'type'         => $type,
            'title'        => $entry['title'] ?? '',
            'prompt'       => $entry['prompt'] ?? $entry['script'] ?? $entry['text'] ?? $entry['lyrics'] ?? '',
            'provider'     => $entry['provider'] ?? 'mock',
            'model'        => $entry['model'] ?? '',
            'credits_used' => (int) ($entry['credits_used'] ?? 0),
            'studio'       => $studio,
            'thumbnail'    => $thumbnail,
            'thumbnail_url'=> $thumbnail,
            'image_url'    => $type === 'image' ? $output_url : '',
            'output_url'   => $output_url,
            'attachment_id'=> (int) ($entry['attachment_id'] ?? 0),
            'favorite'     => !empty($entry['favorite']),
            'public'       => !empty($entry['public']),
            'created_at'   => $entry['created_at'] ?? gmdate('c'),
        ];
    }

    private function map_history(string $type, array $entry): array {
        $prompt = $entry['prompt'] ?? $entry['script'] ?? $entry['text'] ?? $entry['lyrics'] ?? '';
        $output = $entry['output'] ?? [];

        $item = [
            'id'           => $entry['id'] ?? $entry['job_id'] ?? ('gal_' . wp_generate_uuid4()),
            'type'         => $type,
            'title'        => $entry['title'] ?? mb_substr($prompt, 0, 40),
            'prompt'       => $prompt,
            'provider'     => $entry['provider'] ?? 'mock',
            'model'        => $entry['model'] ?? '',
            'credits_used' => (int) ($entry['credits_used'] ?? 0),
            'studio'       => $type . '-studio',
            'created_at'   => $entry['created_at'] ?? gmdate('c'),
        ];

        if ($type === 'image' && !empty($entry['images'][0])) {
            $item['output_url'] = $entry['images'][0]['url'] ?? '';
            $item['thumbnail']  = $entry['images'][0]['thumbnail'] ?? $entry['images'][0]['thumbnail_url'] ?? $item['output_url'];
            $item['image_url']  = $item['output_url'];
            $item['thumbnail_url'] = $item['thumbnail'];
            $item['attachment_id'] = (int) ($entry['images'][0]['attachment_id'] ?? 0);
        } else {
            $item['output_url'] = $output['video_url'] ?? $output['audio_url'] ?? $output['url'] ?? '';
            $item['thumbnail']  = $output['thumbnail'] ?? $output['cover_url'] ?? '';
        }

        return $item;
    }
}
