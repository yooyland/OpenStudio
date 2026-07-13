<?php
if (!defined('ABSPATH')) exit;

/**
 * Public works feed — user, community, marketplace, official, demo with mixed fallback.
 */
final class YooY_Public_Works_Feed {

    /** @var array<int, string> */
    private const MIXED_CHAIN = ['user', 'community', 'marketplace', 'official', 'demo'];

    /** @var array<int, string> */
    private const GUEST_CHAIN = ['community', 'marketplace', 'official', 'demo'];

    public function ensure_seeds(): void {
        if (class_exists('YooY_Official_Showcase')) {
            YooY_Official_Showcase::instance()->seed_if_empty();
        }
        $this->seed_platform_feed_if_empty('yoy_community_feed', 'community');
        $this->seed_platform_feed_if_empty('yoy_marketplace_catalog', 'marketplace');
    }

    /**
     * @return array<string, mixed>
     */
    public function home_payload(int $user_id = 0): array {
        $this->ensure_seeds();
        $home_feed = $this->home_sections_service();

        $chain = $user_id > 0 ? self::MIXED_CHAIN : self::GUEST_CHAIN;
        $works = $this->fill_mixed($user_id, 12, $chain);

        $showcase = $this->fill_mixed($user_id, 6, ['official', 'demo', 'community', 'marketplace']);
        $marketplace = $this->fill_mixed($user_id, 6, ['marketplace', 'community', 'official', 'demo']);
        $community = $this->fill_mixed($user_id, 6, ['community', 'marketplace', 'official', 'demo']);

        $home_sections = [];
        if ($home_feed) {
            $home_sections = $home_feed->resolve_for_home($user_id);
        }

        $showcase_low = count($showcase) < 3 && current_user_can('manage_options');

        return [
            'works'              => $works,
            'work_count'         => $user_id > 0 && class_exists('YooY_Gallery_Store')
                ? count((new YooY_Gallery_Store())->list($user_id, []))
                : 0,
            'showcase'           => $showcase,
            'marketplace'        => $marketplace,
            'community_trending' => $community,
            'home_sections'      => $home_sections,
            'guest'              => $user_id <= 0,
            'showcase_seed_low'  => $showcase_low,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_public(int $limit = 24, ?string $source = null): array {
        $this->ensure_seeds();
        $limit = max(1, min(100, $limit));

        if ($source !== null && $source !== '' && $source !== 'mixed') {
            return array_slice($this->pool_for_source(sanitize_text_field($source), 0, $limit * 2), 0, $limit);
        }

        return $this->fill_mixed(0, $limit, self::GUEST_CHAIN);
    }

    /**
     * @param array<int, string> $chain
     * @return array<int, array<string, mixed>>
     */
    public function fill_mixed(int $user_id, int $limit, array $chain): array {
        $seen = [];
        $filled = [];

        foreach ($chain as $source) {
            if (count($filled) >= $limit) {
                break;
            }
            $pool = $this->pool_for_source($source, $user_id, $limit * 3);
            foreach ($pool as $item) {
                $key = $this->dedupe_key($item);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                if (!$this->has_display_asset($item)) {
                    continue;
                }
                $seen[$key] = true;
                $filled[] = $this->normalize_feed_item($item, (string) ($item['feed_source'] ?? $source));
                if (count($filled) >= $limit) {
                    break 2;
                }
            }
        }

        return array_slice($filled, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pool_for_source(string $source, int $user_id, int $limit): array {
        switch ($source) {
            case 'user':
                return $this->pool_user($user_id, $limit);
            case 'community':
                return $this->pool_community($limit);
            case 'marketplace':
                return $this->pool_marketplace($limit);
            case 'official':
                return $this->pool_official($limit, false);
            case 'demo':
                return $this->pool_official($limit, true);
            default:
                return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pool_user(int $user_id, int $limit): array {
        if ($user_id <= 0 || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $store = new YooY_Gallery_Store();
        if (class_exists('YooY_Gallery_Aggregator')) {
            (new YooY_Gallery_Aggregator($store))->reconcile_jobs($user_id);
        }
        $items = array_slice($store->list($user_id, []), 0, $limit);
        $out = [];
        foreach ($items as $item) {
            $item['feed_source'] = 'user';
            $item['is_platform'] = false;
            $out[] = $item;
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pool_community(int $limit): array {
        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        usort($feed, function ($a, $b) {
            return (int) ($b['likes'] ?? 0) <=> (int) ($a['likes'] ?? 0);
        });

        $out = [];
        foreach (array_slice($feed, 0, $limit) as $item) {
            $out[] = array_merge($item, [
                'feed_source' => 'community',
                'is_platform' => true,
            ]);
        }

        $public_gallery = $this->scan_public_gallery($limit, 'community');
        return $this->merge_pools($out, $public_gallery, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pool_marketplace(int $limit): array {
        $catalog = get_option('yoy_marketplace_catalog', []);
        $catalog = is_array($catalog) ? $catalog : [];
        $out = [];
        foreach (array_slice($catalog, 0, $limit) as $item) {
            $out[] = array_merge($item, [
                'feed_source'   => 'marketplace',
                'is_platform'   => true,
                'marketplace_status' => (string) ($item['status'] ?? 'listed'),
            ]);
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pool_official(int $limit, bool $demo_only): array {
        if (!class_exists('YooY_Official_Showcase')) {
            return [];
        }
        $showcase = YooY_Official_Showcase::instance();
        $items = $showcase->list_public(max($limit, 24));
        if ($demo_only) {
            $items = array_values(array_filter($items, function ($item) {
                return !empty($item['is_demo']);
            }));
        }
        return $showcase->to_feed_cards(array_slice($items, 0, $limit));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scan_public_gallery(int $limit, string $source): array {
        if (!class_exists('YooY_Gallery_Store')) {
            return [];
        }

        $store = new YooY_Gallery_Store();
        $results = [];
        $users = get_users(['number' => 80, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => ['ID', 'display_name']]);

        foreach ($users as $user) {
            foreach ($store->list((int) $user->ID, []) as $item) {
                if (empty($item['public']) && empty($item['community_shared'])) {
                    continue;
                }
                $item['creator'] = $user->display_name;
                $item['creator_name'] = $user->display_name;
                $item['creator_id'] = (int) $user->ID;
                $item['feed_source'] = $source;
                $item['is_platform'] = true;
                $item['visibility'] = 'public';
                $results[] = $item;
                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $primary
     * @param array<int, array<string, mixed>> $secondary
     * @return array<int, array<string, mixed>>
     */
    private function merge_pools(array $primary, array $secondary, int $limit): array {
        $seen = [];
        $merged = [];
        foreach (array_merge($primary, $secondary) as $item) {
            $key = $this->dedupe_key($item);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $item;
            if (count($merged) >= $limit) {
                break;
            }
        }
        return $merged;
    }

    /**
     * @param array<string, mixed> $item
     */
    public function normalize_feed_item(array $item, string $source): array {
        $thumb = (string) ($item['thumbnail_url'] ?? $item['thumbnail'] ?? '');
        $display = (string) ($item['display_url'] ?? $item['large_url'] ?? $item['image_url'] ?? $thumb);
        $full = (string) ($item['full_url'] ?? $item['original_url'] ?? $item['image_url'] ?? $display);
        $creator = (string) ($item['creator_name'] ?? $item['creator'] ?? 'Creator');

        return [
            'id'                 => (string) ($item['id'] ?? ''),
            'title'              => (string) ($item['title'] ?? 'Work'),
            'description'        => (string) ($item['description'] ?? $item['prompt'] ?? ''),
            'type'               => (string) ($item['type'] ?? 'image'),
            'type_label'         => (string) ($item['type_label'] ?? $this->type_label((string) ($item['type'] ?? 'image'))),
            'thumbnail_url'      => esc_url_raw($thumb),
            'display_url'        => esc_url_raw($display),
            'full_url'           => esc_url_raw($full),
            'large_url'          => esc_url_raw($display),
            'provider'           => (string) ($item['provider'] ?? ''),
            'model'              => (string) ($item['model'] ?? ''),
            'creator_name'       => $creator,
            'creator'            => $creator,
            'creator_avatar'     => (string) ($item['creator_avatar'] ?? ''),
            'visibility'         => (string) ($item['visibility'] ?? 'public'),
            'source'             => $source,
            'feed_source'        => $source,
            'marketplace_status' => (string) ($item['marketplace_status'] ?? ($source === 'marketplace' ? 'listed' : '')),
            'likes'              => (int) ($item['likes'] ?? 0),
            'created_at'         => (string) ($item['created_at'] ?? ''),
            'updated_at'         => (string) ($item['updated_at'] ?? ''),
            'is_platform'        => !empty($item['is_platform']) || in_array($source, ['community', 'marketplace', 'official', 'demo'], true),
            'genre'              => (string) ($item['genre'] ?? ''),
            'prompt'             => (string) ($item['prompt'] ?? ''),
        ];
    }

    private function seed_platform_feed_if_empty(string $option_key, string $source): void {
        $existing = get_option($option_key, []);
        if (is_array($existing) && !empty($existing)) {
            return;
        }
        if (!class_exists('YooY_Official_Showcase')) {
            return;
        }

        $cards = YooY_Official_Showcase::instance()->to_feed_cards(
            array_slice(YooY_Official_Showcase::instance()->list_public(24), 0, 12)
        );

        $seed = [];
        foreach ($cards as $idx => $card) {
            $seed[] = [
                'id'            => ($source === 'marketplace' ? 'mkt_' : 'com_') . ($card['id'] ?? ('seed_' . $idx)),
                'gallery_id'    => (string) ($card['id'] ?? ''),
                'title'         => (string) ($card['title'] ?? 'Sample Work'),
                'type'          => (string) ($card['type'] ?? 'image'),
                'thumbnail_url' => (string) ($card['thumbnail_url'] ?? ''),
                'provider'      => (string) ($card['provider'] ?? 'YooY Official'),
                'creator'       => $source === 'marketplace' ? 'YooY Marketplace' : 'YooY Community',
                'likes'         => max(1, 24 - $idx),
                'price'         => $source === 'marketplace' ? ($idx % 3 === 0 ? 0 : 9900) : 0,
                'status'        => 'listed',
                'created_at'    => gmdate('c', time() - ($idx * 3600)),
                'feed_source'   => $source,
                'is_demo'       => true,
            ];
        }

        if (!empty($seed)) {
            update_option($option_key, $seed, false);
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function has_display_asset(array $item): bool {
        $url = (string) ($item['thumbnail_url'] ?? $item['display_url'] ?? $item['large_url'] ?? $item['image_url'] ?? '');
        if ($url === '') {
            return false;
        }
        if (strpos($url, 'placeholder.svg') !== false && empty($item['is_platform']) && empty($item['is_demo'])) {
            return false;
        }
        return true;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function dedupe_key(array $item): string {
        $id = (string) ($item['id'] ?? '');
        $source = (string) ($item['feed_source'] ?? $item['source'] ?? '');
        if ($id === '') {
            return '';
        }
        return $source . ':' . $id;
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

    private function home_sections_service(): ?YooY_Home_Sections_Service {
        if (class_exists('YooY_Home_Sections_Service')) {
            return new YooY_Home_Sections_Service();
        }
        if (defined('YOY_AI_STUDIO_MODULES_DIR') && file_exists(YOY_AI_STUDIO_MODULES_DIR . 'admin-console/includes/class-home-sections-service.php')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'admin-console/includes/class-home-sections-service.php';
            return new YooY_Home_Sections_Service();
        }
        return null;
    }
}
