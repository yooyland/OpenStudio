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
        $existing = $this->store->get_all($user_id);
        $by_id    = [];
        foreach ($existing as $item) {
            $by_id[$item['id']] = $item;
        }

        foreach ($this->sources as $type => $config) {
            $raw = get_user_meta($user_id, $config['meta'], true);
            if (!is_array($raw)) continue;

            foreach ($raw as $entry) {
                $normalized = $this->map_entry($type, $entry, $config['studio']);
                $id = $normalized['id'];
                if (!isset($by_id[$id])) {
                    $by_id[$id] = $normalized;
                } else {
                    $by_id[$id] = array_merge($by_id[$id], array_filter($normalized));
                }
            }
        }

        $history_sources = [
            ['meta' => 'yoy_video_history', 'type' => 'video'],
            ['meta' => 'yoy_image_history', 'type' => 'image'],
            ['meta' => 'yoy_music_history', 'type' => 'music'],
            ['meta' => 'yoy_voice_history', 'type' => 'voice'],
            ['meta' => 'yoy_avatar_history', 'type' => 'avatar'],
        ];

        foreach ($history_sources as $src) {
            $raw = get_user_meta($user_id, $src['meta'], true);
            if (!is_array($raw)) continue;
            foreach ($raw as $entry) {
                $normalized = $this->map_history($src['type'], $entry);
                $id = $normalized['id'];
                if (!isset($by_id[$id])) {
                    $by_id[$id] = $normalized;
                }
            }
        }

        $items = array_values($by_id);
        usort($items, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        $this->store->set_all($user_id, $items);
        return $items;
    }

    public function from_generation(int $user_id, array $result, string $type, string $studio): array {
        $item = [
            'id'           => $result['job_id'] ?? $result['id'] ?? ('gal_' . wp_generate_uuid4()),
            'type'         => $type,
            'title'        => $result['title'] ?? '',
            'prompt'       => $result['prompt'] ?? $result['script'] ?? $result['text'] ?? $result['lyrics'] ?? '',
            'provider'     => $result['provider'] ?? 'mock',
            'model'        => $result['model'] ?? '',
            'credits_used' => (int) ($result['credits_used'] ?? 0),
            'studio'       => $studio,
            'output'       => $result['output'] ?? [],
            'created_at'   => $result['created_at'] ?? gmdate('c'),
        ];

        if ($type === 'video' || $type === 'avatar') {
            $item['output_url'] = $result['output']['video_url'] ?? $result['output']['url'] ?? '';
            $item['thumbnail'] = $result['output']['thumbnail'] ?? '';
        } elseif ($type === 'image') {
            $img = ($result['images'] ?? [])[0] ?? [];
            $item['output_url'] = $img['url'] ?? $result['output']['url'] ?? '';
            $item['thumbnail'] = $img['thumbnail'] ?? $img['url'] ?? '';
        } elseif ($type === 'music' || $type === 'voice') {
            $item['output_url'] = $result['output']['audio_url'] ?? '';
            $item['thumbnail'] = $result['output']['cover_url'] ?? '';
        }

        return $this->store->save($user_id, $item);
    }

    private function map_entry(string $type, array $entry, string $studio): array {
        return [
            'id'           => $entry['id'] ?? ('gal_' . wp_generate_uuid4()),
            'type'         => $type,
            'title'        => $entry['title'] ?? '',
            'prompt'       => $entry['prompt'] ?? $entry['script'] ?? $entry['text'] ?? $entry['lyrics'] ?? '',
            'provider'     => $entry['provider'] ?? 'mock',
            'model'        => $entry['model'] ?? '',
            'credits_used' => (int) ($entry['credits_used'] ?? 0),
            'studio'       => $studio,
            'thumbnail'    => $entry['thumbnail'] ?? $entry['cover_url'] ?? '',
            'output_url'   => $entry['url'] ?? $entry['video_url'] ?? $entry['audio_url'] ?? ($entry['output']['audio_url'] ?? $entry['output']['url'] ?? ''),
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
            $item['thumbnail']  = $entry['images'][0]['thumbnail'] ?? '';
        } else {
            $item['output_url'] = $output['video_url'] ?? $output['audio_url'] ?? $output['url'] ?? '';
            $item['thumbnail']  = $output['thumbnail'] ?? $output['cover_url'] ?? '';
        }

        return $item;
    }
}
