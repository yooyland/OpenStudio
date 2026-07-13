<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/seed-data.php';

final class YooY_Official_Showcase {

    private const OPTION_KEY   = 'yoy_official_showcase';
    private const CACHE_KEY    = 'yoy_official_showcase_feed';
    private const CACHE_TTL    = 3600;
    private const SEED_FLAG    = 'yoy_official_showcase_seeded';

    /** @var self|null */
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function list_all(): array {
        $items = get_option(self::OPTION_KEY, []);
        if (!is_array($items) || empty($items)) {
            return [];
        }
        usort($items, function ($a, $b) {
            return (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0);
        });
        return array_map([$this, 'normalize'], $items);
    }

    public function list_visible(int $limit = 100, bool $featured_first = true): array {
        $items = array_values(array_filter($this->list_all(), function ($item) {
            return empty($item['hidden']);
        }));

        if ($featured_first) {
            usort($items, function ($a, $b) {
                $score_a = (!empty($a['featured']) ? 100 : 0) + (!empty($a['recommended']) ? 50 : 0);
                $score_b = (!empty($b['featured']) ? 100 : 0) + (!empty($b['recommended']) ? 50 : 0);
                if ($score_a === $score_b) {
                    return (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0);
                }
                return $score_b <=> $score_a;
            });
        }

        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
        return $items;
    }

    public function list_public(int $limit = 100): array {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return array_slice($cached, 0, max(1, $limit));
        }

        $items = $this->list_visible($limit > 0 ? max($limit, 100) : 100);
        set_transient(self::CACHE_KEY, $items, self::CACHE_TTL);
        return array_slice($items, 0, max(1, $limit));
    }

    public function get(string $id): ?array {
        foreach ($this->list_all() as $item) {
            if (($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    public function create(array $data): array {
        $items = $this->list_all();
        $entry = $this->normalize(array_merge($data, [
            'id'         => 'off_' . wp_generate_uuid4(),
            'sort_order' => count($items),
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ]));
        $items[] = $entry;
        $this->persist($items);
        return $entry;
    }

    public function update(string $id, array $data): ?array {
        $items = $this->list_all();
        foreach ($items as $idx => $item) {
            if (($item['id'] ?? '') !== $id) {
                continue;
            }
            $items[$idx] = $this->normalize(array_merge($item, $data, [
                'id'         => $id,
                'updated_at' => gmdate('c'),
            ]));
            $this->persist($items);
            return $items[$idx];
        }
        return null;
    }

    public function delete(string $id): bool {
        $items = $this->list_all();
        $before = count($items);
        $items = array_values(array_filter($items, function ($item) use ($id) {
            return ($item['id'] ?? '') !== $id;
        }));
        if (count($items) === $before) {
            return false;
        }
        $this->persist($items);
        return true;
    }

    public function reorder(array $ordered_ids): array {
        $items = $this->list_all();
        $map = [];
        foreach ($items as $item) {
            $map[$item['id'] ?? ''] = $item;
        }

        $ordered = [];
        $sort = 0;
        foreach ($ordered_ids as $id) {
            $id = sanitize_text_field((string) $id);
            if ($id === '' || !isset($map[$id])) {
                continue;
            }
            $entry = $map[$id];
            $entry['sort_order'] = $sort;
            $entry['updated_at'] = gmdate('c');
            $ordered[] = $entry;
            unset($map[$id]);
            $sort++;
        }

        foreach ($map as $item) {
            $item['sort_order'] = $sort;
            $ordered[] = $item;
            $sort++;
        }

        $this->persist($ordered);
        return $this->list_all();
    }

    public function seed_if_empty(): int {
        if (get_option(self::SEED_FLAG, '') === '1' && !empty(get_option(self::OPTION_KEY, []))) {
            return 0;
        }

        $seed = yoy_official_showcase_seed_data();
        update_option(self::OPTION_KEY, array_map([$this, 'normalize'], $seed), false);
        update_option(self::SEED_FLAG, '1', false);
        $this->clear_cache();
        return count($seed);
    }

    public function clear_cache(): void {
        delete_transient(self::CACHE_KEY);
    }

    public function to_feed_cards(array $items): array {
        $cards = [];
        foreach ($items as $item) {
            $cards[] = $this->to_feed_card($item);
        }
        return $cards;
    }

    public function to_feed_card(array $item): array {
        $type = sanitize_text_field($item['type'] ?? 'image');
        $thumb = (string) ($item['thumbnail_url'] ?? '');
        if ($thumb === '' && defined('YOY_AI_STUDIO_URL')) {
            $thumb = YOY_AI_STUDIO_URL . 'official-showcase/thumbs/placeholder.svg';
        }

        return [
            'id'            => (string) ($item['id'] ?? ''),
            'title'         => (string) ($item['title'] ?? 'Official Work'),
            'type'          => $type,
            'type_label'    => $this->type_label($type),
            'thumbnail_url' => esc_url_raw($thumb),
            'provider'      => 'YooY Official',
            'creator'       => 'YooY Studio',
            'prompt'        => (string) ($item['prompt'] ?? $item['description'] ?? ''),
            'genre'         => (string) ($item['genre'] ?? ''),
            'featured'      => !empty($item['featured']),
            'recommended'   => !empty($item['recommended']),
            'feed_source'   => !empty($item['is_demo']) ? 'demo' : 'official',
            'is_platform'   => true,
            'created_at'    => (string) ($item['created_at'] ?? ''),
            'updated_at'    => (string) ($item['updated_at'] ?? ''),
        ];
    }

    private function persist(array $items): void {
        update_option(self::OPTION_KEY, $items, false);
        $this->clear_cache();
    }

    private function normalize(array $item): array {
        $type = sanitize_text_field($item['type'] ?? 'image');
        $allowed = ['image', 'video', 'music', 'voice', 'writing', 'avatar'];
        if (!in_array($type, $allowed, true)) {
            $type = 'image';
        }

        $thumb = esc_url_raw($item['thumbnail_url'] ?? '');
        if ($thumb === '' && defined('YOY_AI_STUDIO_URL')) {
            $thumb = YOY_AI_STUDIO_URL . 'official-showcase/thumbs/placeholder.svg';
        }

        return [
            'id'            => sanitize_text_field($item['id'] ?? ('off_' . wp_generate_uuid4())),
            'title'         => sanitize_text_field($item['title'] ?? 'Official Work'),
            'description'   => sanitize_textarea_field($item['description'] ?? ''),
            'type'          => $type,
            'genre'         => sanitize_text_field($item['genre'] ?? ''),
            'prompt'        => sanitize_textarea_field($item['prompt'] ?? ''),
            'thumbnail_url' => $thumb,
            'media_url'     => esc_url_raw($item['media_url'] ?? ''),
            'featured'      => !empty($item['featured']),
            'recommended'   => !empty($item['recommended']),
            'hidden'        => !empty($item['hidden']),
            'is_demo'       => !isset($item['is_demo']) || !empty($item['is_demo']),
            'sort_order'    => (int) ($item['sort_order'] ?? 0),
            'created_at'    => $item['created_at'] ?? gmdate('c'),
            'updated_at'    => $item['updated_at'] ?? gmdate('c'),
        ];
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
}
