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
        $payload = [
            'studio'   => $studio,
            'type'     => $item['type'],
            'prompt'   => $item['prompt'],
            'provider' => $item['provider'],
            'model'    => $item['model'],
            'remix_source' => ['gallery_id' => $id],
        ];

        return match ($item['type']) {
            'video'  => array_merge($payload, ['prompt' => $item['prompt']]),
            'image'  => array_merge($payload, ['prompt' => $item['prompt']]),
            'music'  => array_merge($payload, ['lyrics' => $item['prompt']]),
            'voice'  => array_merge($payload, ['text' => $item['prompt']]),
            'avatar' => array_merge($payload, ['script' => $item['prompt']]),
            'writing'=> array_merge($payload, ['prompt' => $item['prompt']]),
            default  => $payload,
        };
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

    public function register_marketplace(int $user_id, string $id): array {
        $item = $this->store->get($user_id, $id);
        if (!$item) throw new Exception('Item not found.');

        $listing = [
            'id'         => 'mkt_gal_' . $id,
            'gallery_id' => $id,
            'title'      => $item['title'],
            'prompt'     => $item['prompt'],
            'type'       => $item['type'],
            'provider'   => $item['provider'],
            'creator'    => wp_get_current_user()->display_name,
            'price'      => 0,
            'tier'       => 'free',
            'created_at' => gmdate('c'),
        ];

        $listings = get_user_meta($user_id, 'yoy_marketplace_listings', true);
        $listings = is_array($listings) ? $listings : [];
        array_unshift($listings, $listing);
        update_user_meta($user_id, 'yoy_marketplace_listings', array_slice($listings, 0, 100));

        $global = get_option('yoy_marketplace_catalog', []);
        $global = is_array($global) ? $global : [];
        array_unshift($global, $listing);
        update_option('yoy_marketplace_catalog', array_slice($global, 0, 200));

        $updated = $this->store->update($user_id, $id, ['marketplace' => true]);
        return ['item' => $updated, 'listing' => $listing];
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

    private function ext(string $type): string {
        return match ($type) {
            'video', 'avatar' => '.mp4',
            'music', 'voice'  => '.mp3',
            'image'           => '.png',
            default           => '.txt',
        };
    }
}
