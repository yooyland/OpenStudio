<?php
if (!defined('ABSPATH')) exit;

final class YooY_Home_Sections_Service {

    private const OPTION_KEY = 'yoy_home_sections';

    private const TYPES = [
        'latest', 'featured', 'best', 'hot', 'marketplace', 'community',
        'manual', 'project', 'category', 'tag', 'official', 'mixed',
        'gallery', 'template', 'recent', 'guide', 'projects',
    ];

    private const SOURCES = [
        'user', 'community', 'marketplace', 'official', 'demo', 'mixed',
        'gallery', 'projects', 'templates', 'guide',
    ];

    private const DISPLAY_TYPES = ['gallery', 'template', 'recent', 'guide', 'projects'];

    private const DATA_SOURCES = [
        'gallery', 'projects', 'templates', 'community', 'marketplace', 'guide',
        'user', 'official', 'demo', 'mixed',
    ];

    /** @var array<string, array<int, array<string, mixed>>> */
    private static $pool_cache = [];

    private const COLUMN_OPTIONS = [2, 3, 4, 5, 6, 'carousel'];

    private const CARD_RATIOS = ['auto', 'square', 'portrait', 'landscape', 'wide', 'masonry'];

    private const TEXT_MODES = ['below', 'overlay', 'hidden'];

    public function list_all(): array {
        $sections = get_option(self::OPTION_KEY, []);
        if (!is_array($sections) || empty($sections)) {
            $sections = $this->default_sections();
            update_option(self::OPTION_KEY, $sections, false);
        }

        usort($sections, function ($a, $b) {
            return (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0);
        });

        return array_map([$this, 'normalize'], $sections);
    }

    public function list_visible(): array {
        return array_values(array_filter($this->list_all(), function ($section) {
            return !empty($section['visible']);
        }));
    }

    public function get(string $id): ?array {
        foreach ($this->list_all() as $section) {
            if (($section['id'] ?? '') === $id) {
                return $section;
            }
        }
        return null;
    }

    public function create(array $data): array {
        $sections = $this->list_all();
        $entry = $this->normalize(array_merge($data, [
            'id'         => 'sec_' . wp_generate_uuid4(),
            'sort_order' => count($sections),
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ]));
        $sections[] = $entry;
        update_option(self::OPTION_KEY, $sections, false);
        return $entry;
    }

    public function update(string $id, array $data): ?array {
        $sections = $this->list_all();
        foreach ($sections as $idx => $section) {
            if (($section['id'] ?? '') !== $id) {
                continue;
            }
            $sections[$idx] = $this->normalize(array_merge($section, $data, [
                'id'         => $id,
                'updated_at' => gmdate('c'),
            ]));
            update_option(self::OPTION_KEY, $sections, false);
            return $sections[$idx];
        }
        return null;
    }

    public function delete(string $id): bool {
        $sections = $this->list_all();
        $before = count($sections);
        $sections = array_values(array_filter($sections, function ($section) use ($id) {
            return ($section['id'] ?? '') !== $id;
        }));
        update_option(self::OPTION_KEY, $sections, false);
        return count($sections) < $before;
    }

    public function replace_with_dashboard_defaults(): array {
        update_option(self::OPTION_KEY, $this->default_sections(), false);
        return $this->list_all();
    }

    public function reorder(array $ordered_ids): array {
        $sections = $this->list_all();
        $map = [];
        foreach ($sections as $section) {
            $map[$section['id'] ?? ''] = $section;
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

        foreach ($map as $section) {
            $section['sort_order'] = $sort;
            $ordered[] = $section;
            $sort++;
        }

        update_option(self::OPTION_KEY, $ordered, false);
        return $this->list_all();
    }

    public function resolve_for_home(int $user_id): array {
        self::$pool_cache = [];
        $output = [];
        foreach ($this->list_visible() as $section) {
            $display_type = $this->normalize_display_type($section);
            $data_source = $this->normalize_data_source($section);
            $layout = $this->normalize_layout($section);
            $type = sanitize_text_field($section['type'] ?? 'latest');
            $entry = [
                'id'            => $section['id'],
                'title'         => $section['title'],
                'description'   => $section['description'],
                'type'          => $type,
                'display_type'  => $display_type,
                'data_source'   => $data_source,
                'source'        => $section['source'],
                'layout'        => $layout,
                'column_count'  => $section['column_count'],
                'card_ratio'    => $section['card_ratio'],
                'text_mode'     => $section['text_mode'],
                'filter'        => is_array($section['filter'] ?? null) ? $section['filter'] : [],
                'limit'         => (int) $section['limit'],
                'order'         => (int) ($section['sort_order'] ?? 0),
                'works'         => [],
                'projects'      => [],
            ];

            if ($display_type === 'guide' || $data_source === 'guide') {
                $output[] = $entry;
                continue;
            }

            if ($display_type === 'recent') {
                $output[] = $entry;
                continue;
            }

            if ($display_type === 'projects' || $data_source === 'projects' || $type === 'project') {
                $entry['projects'] = $this->resolve_project_section($section, $user_id);
                $output[] = $entry;
                continue;
            }

            if ($display_type === 'template' || $data_source === 'templates') {
                $entry['works'] = $this->resolve_templates($section, $user_id);
                $output[] = $entry;
                continue;
            }

            $works = $this->resolve_works($section, $user_id);
            $entry['works'] = $this->apply_filters($works, $section['filter'] ?? []);
            $output[] = $entry;
        }
        self::$pool_cache = [];
        return $output;
    }

    /**
     * Platform feed filler for dashboard blocks (showcase, market, community, works).
     *
     * @return array<int, array<string, mixed>>
     */
    public function fill_platform_feed(int $user_id, int $limit, array $chain): array {
        self::$pool_cache = [];
        $section = [
            'type'   => 'latest',
            'source' => 'mixed',
            'limit'  => $limit,
        ];
        $works = $this->fill_to_limit([], $section, $user_id, $limit, $chain);
        self::$pool_cache = [];
        return $works;
    }

    public function search_works(string $query, int $limit = 20): array {
        $query = trim($query);
        $results = [];
        $users = get_users(['number' => 50, 'orderby' => 'ID', 'order' => 'DESC', 'fields' => ['ID', 'display_name']]);

        if (!class_exists('YooY_Gallery_Store')) {
            return [];
        }

        $store = new YooY_Gallery_Store();
        foreach ($users as $user) {
            foreach ($store->list((int) $user->ID, []) as $item) {
                if (empty($item['public']) && empty($item['community_shared'])) {
                    continue;
                }
                if ($query !== '') {
                    $hay = strtolower(
                        ($item['title'] ?? '') . ' ' .
                        ($item['prompt'] ?? '') . ' ' .
                        ($item['user_prompt'] ?? '')
                    );
                    if (strpos($hay, strtolower($query)) === false) {
                        continue;
                    }
                }
                $item['creator'] = $user->display_name;
                $item['creator_id'] = (int) $user->ID;
                $results[] = $this->public_work_card($item);
                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }

        return $results;
    }

    public function resolve_works(array $section, int $user_id): array {
        $limit = max(1, min(24, (int) ($section['limit'] ?? 8)));
        $type = sanitize_text_field($section['type'] ?? 'latest');
        $source = $this->normalize_source($section);

        if ($type === 'manual') {
            $works = $this->resolve_manual($section, $limit);
            return $this->fill_to_limit($works, $section, $user_id, $limit, $this->chain_for_source($source));
        }

        if ($type === 'official') {
            return $this->fill_to_limit([], $section, $user_id, $limit, ['official', 'demo']);
        }

        if ($source === 'community') {
            return $this->fill_to_limit([], $section, $user_id, $limit, ['community', 'marketplace', 'official', 'demo']);
        }

        if ($source === 'marketplace') {
            return $this->fill_to_limit([], $section, $user_id, $limit, ['marketplace', 'community', 'official', 'demo']);
        }

        if ($source === 'official') {
            return $this->fill_to_limit([], $section, $user_id, $limit, ['official', 'demo', 'community', 'marketplace']);
        }

        if ($source === 'demo') {
            return $this->fill_to_limit([], $section, $user_id, $limit, ['demo', 'official', 'community', 'marketplace']);
        }

        if ($source === 'mixed' || $type === 'mixed') {
            return $this->fill_to_limit([], $section, $user_id, $limit, ['user', 'community', 'marketplace', 'official', 'demo']);
        }

        $primary = $this->resolve_primary_by_type($type, $section, $user_id, $limit);
        return $this->fill_to_limit(
            $primary,
            $section,
            $user_id,
            $limit,
            ['user', 'project', 'community', 'marketplace', 'official', 'demo']
        );
    }

    /**
     * @param array<int, array<string, mixed>> $works
     * @param array<int, string> $chain
     * @return array<int, array<string, mixed>>
     */
    private function fill_to_limit(array $works, array $section, int $user_id, int $limit, array $chain): array {
        $seen = [];
        $filled = [];

        foreach ($works as $work) {
            $key = $this->work_dedupe_key($work);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            if (!isset($work['feed_source'])) {
                $work['feed_source'] = 'user';
            }
            $filled[] = $work;
            $seen[$key] = true;
            if (count($filled) >= $limit) {
                return array_slice($filled, 0, $limit);
            }
        }

        foreach ($chain as $source) {
            if (count($filled) >= $limit) {
                break;
            }
            $pool = $this->fetch_source_pool($source, $section, $user_id, $limit * 3);
            foreach ($pool as $item) {
                $key = $this->work_dedupe_key($item);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $item['feed_source'] = $source;
                $filled[] = $item;
                $seen[$key] = true;
                if (count($filled) >= $limit) {
                    break 2;
                }
            }
        }

        return array_slice($filled, 0, $limit);
    }

    /**
     * @return array<int, string>
     */
    private function chain_for_source(string $source): array {
        switch ($source) {
            case 'community':
                return ['community', 'marketplace', 'official', 'demo'];
            case 'marketplace':
                return ['marketplace', 'community', 'official', 'demo'];
            case 'official':
                return ['official', 'demo', 'community', 'marketplace'];
            case 'demo':
                return ['demo', 'official', 'community', 'marketplace'];
            case 'mixed':
                return ['user', 'community', 'marketplace', 'official', 'demo'];
            case 'user':
            default:
                return ['user', 'project', 'community', 'marketplace', 'official', 'demo'];
        }
    }

    private function normalize_source(array $section): string {
        $source = sanitize_text_field($section['source'] ?? '');
        if ($source !== '' && in_array($source, self::SOURCES, true)) {
            return $source;
        }

        $type = sanitize_text_field($section['type'] ?? 'latest');
        if ($type === 'community') {
            return 'community';
        }
        if ($type === 'marketplace') {
            return 'marketplace';
        }
        if ($type === 'official' || $type === 'mixed') {
            return $type;
        }
        return 'user';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolve_primary_by_type(string $type, array $section, int $user_id, int $limit): array {
        switch ($type) {
            case 'marketplace':
                return $this->tag_feed_source($this->resolve_marketplace($limit), 'marketplace');
            case 'community':
                return $this->tag_feed_source($this->resolve_community($limit), 'community');
            case 'project':
                return $this->tag_feed_source($this->resolve_project($section, $user_id, $limit), 'user');
            case 'category':
                return $this->tag_feed_source($this->resolve_category($section, $user_id, $limit), 'user');
            case 'tag':
                return $this->tag_feed_source($this->resolve_tag($section, $user_id, $limit), 'user');
            case 'featured':
                return $this->tag_feed_source($this->resolve_featured($user_id, $limit), 'user');
            case 'best':
                return $this->tag_feed_source($this->resolve_best($user_id, $limit), 'user');
            case 'hot':
                return $this->tag_feed_source($this->resolve_hot($user_id, $limit), 'user');
            case 'latest':
            default:
                return $this->tag_feed_source($this->resolve_latest($user_id, $limit), 'user');
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function tag_feed_source(array $items, string $source): array {
        foreach ($items as $idx => $item) {
            if (!isset($item['feed_source'])) {
                $items[$idx]['feed_source'] = $source;
            }
        }
        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch_source_pool(string $source, array $section, int $user_id, int $limit): array {
        $cache_key = $source . ':' . $user_id . ':' . ($section['type'] ?? 'latest');
        if (isset(self::$pool_cache[$cache_key])) {
            return self::$pool_cache[$cache_key];
        }

        $pool = [];
        switch ($source) {
            case 'user':
                $pool = $this->resolve_primary_by_type(
                    sanitize_text_field($section['type'] ?? 'latest'),
                    $section,
                    $user_id,
                    $limit
                );
                break;
            case 'project':
                $pool = $this->tag_feed_source($this->resolve_project_works($user_id, $limit), 'project');
                break;
            case 'community':
                $pool = $this->tag_feed_source($this->resolve_community($limit), 'community');
                break;
            case 'marketplace':
                $pool = $this->tag_feed_source($this->resolve_marketplace($limit), 'marketplace');
                break;
            case 'official':
                $pool = $this->resolve_official($limit, false);
                break;
            case 'demo':
                $pool = $this->resolve_official($limit, true);
                break;
        }

        self::$pool_cache[$cache_key] = $pool;
        return $pool;
    }

    private function work_dedupe_key(array $work): string {
        $id = (string) ($work['id'] ?? '');
        $source = (string) ($work['feed_source'] ?? '');
        if ($id === '') {
            return '';
        }
        return $source . ':' . $id;
    }

    private function resolve_project_works(int $user_id, int $limit): array {
        if ($user_id <= 0 || !class_exists('YooY_Gallery_Store')) {
            return [];
        }

        $store = new YooY_Gallery_Store();
        $items = $store->list($user_id, []);
        $with_project = array_values(array_filter($items, function ($item) {
            return (string) ($item['project_id'] ?? '') !== '';
        }));

        usort($with_project, function ($a, $b) {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });

        return array_map([$this, 'public_work_card'], array_slice($with_project, 0, $limit));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolve_official(int $limit, bool $demo_only): array {
        $showcase = $this->official_showcase();
        if (!$showcase) {
            return [];
        }

        $items = $showcase->list_public(max($limit, 24));
        if ($demo_only) {
            $items = array_values(array_filter($items, function ($item) {
                return !empty($item['is_demo']);
            }));
        }

        return $showcase->to_feed_cards(array_slice($items, 0, $limit));
    }

    private function official_showcase(): ?YooY_Official_Showcase {
        if (class_exists('YooY_Official_Showcase')) {
            return YooY_Official_Showcase::instance();
        }

        $paths = [];
        if (defined('YOY_AI_STUDIO_DIR')) {
            $paths[] = YOY_AI_STUDIO_DIR . 'official-showcase/class-yoy-official-showcase.php';
        }

        foreach ($paths as $path) {
            if ($path && file_exists($path)) {
                require_once $path;
                break;
            }
        }

        if (!class_exists('YooY_Official_Showcase')) {
            return null;
        }

        $instance = YooY_Official_Showcase::instance();
        $instance->seed_if_empty();
        return $instance;
    }

    private function resolve_latest(int $user_id, int $limit): array {
        if ($user_id <= 0 || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $items = (new YooY_Gallery_Store())->list($user_id, []);
        usort($items, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        return array_map([$this, 'public_work_card'], array_slice($items, 0, $limit));
    }

    private function resolve_featured(int $user_id, int $limit): array {
        if ($user_id <= 0 || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $items = array_values(array_filter(
            (new YooY_Gallery_Store())->list($user_id, ['favorite' => true]),
            function ($item) {
                return !empty($item['favorite']);
            }
        ));
        if (empty($items)) {
            return $this->resolve_latest($user_id, $limit);
        }
        return array_map([$this, 'public_work_card'], array_slice($items, 0, $limit));
    }

    private function resolve_best(int $user_id, int $limit): array {
        if ($user_id <= 0 || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $items = (new YooY_Gallery_Store())->list($user_id, []);
        usort($items, function ($a, $b) {
            $score_a = (!empty($a['favorite']) ? 10 : 0) + (!empty($a['marketplace']) ? 5 : 0) + (!empty($a['public']) ? 2 : 0);
            $score_b = (!empty($b['favorite']) ? 10 : 0) + (!empty($b['marketplace']) ? 5 : 0) + (!empty($b['public']) ? 2 : 0);
            if ($score_a === $score_b) {
                return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
            }
            return $score_b <=> $score_a;
        });
        return array_map([$this, 'public_work_card'], array_slice($items, 0, $limit));
    }

    private function resolve_hot(int $user_id, int $limit): array {
        if ($user_id <= 0 || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $items = (new YooY_Gallery_Store())->list($user_id, []);
        $year = gmdate('Y');
        $items = array_values(array_filter($items, function ($item) use ($year) {
            $created = (string) ($item['created_at'] ?? '');
            return $created !== '' && strpos($created, $year) === 0;
        }));
        usort($items, function ($a, $b) {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });
        if (empty($items)) {
            return $this->resolve_latest($user_id, $limit);
        }
        return array_map([$this, 'public_work_card'], array_slice($items, 0, $limit));
    }

    private function resolve_manual(array $section, int $limit): array {
        $ids = is_array($section['manual_ids'] ?? null) ? $section['manual_ids'] : [];
        if (empty($ids) || !class_exists('YooY_Gallery_Store')) {
            return [];
        }

        $store = new YooY_Gallery_Store();
        $map = [];
        foreach (get_users(['number' => 50, 'fields' => ['ID']]) as $user) {
            foreach ($store->list((int) $user->ID, []) as $item) {
                $map[$item['id'] ?? ''] = $item;
            }
        }

        $works = [];
        foreach ($ids as $id) {
            $id = sanitize_text_field((string) $id);
            if ($id !== '' && isset($map[$id])) {
                $works[] = $this->public_work_card($map[$id]);
            }
            if (count($works) >= $limit) {
                break;
            }
        }
        return $works;
    }

    private function resolve_marketplace(int $limit): array {
        $catalog = get_option('yoy_marketplace_catalog', []);
        $catalog = is_array($catalog) ? $catalog : [];
        $works = [];
        foreach (array_slice($catalog, 0, $limit) as $item) {
            $works[] = [
                'id'            => (string) ($item['id'] ?? $item['gallery_id'] ?? ''),
                'title'         => (string) ($item['title'] ?? 'Work'),
                'type'          => (string) ($item['type'] ?? 'image'),
                'type_label'    => $this->type_label((string) ($item['type'] ?? 'image')),
                'thumbnail_url' => (string) ($item['thumbnail_url'] ?? $item['thumbnail'] ?? ''),
                'provider'      => (string) ($item['provider'] ?? 'marketplace'),
                'creator'       => (string) ($item['creator'] ?? ''),
                'created_at'    => (string) ($item['created_at'] ?? ''),
                'feed_source'   => 'marketplace',
                'is_platform'   => true,
            ];
        }
        return $works;
    }

    private function resolve_community(int $limit): array {
        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        usort($feed, function ($a, $b) {
            return (int) ($b['likes'] ?? 0) <=> (int) ($a['likes'] ?? 0);
        });
        $works = [];
        foreach (array_slice($feed, 0, $limit) as $item) {
            $works[] = [
                'id'            => (string) ($item['id'] ?? $item['gallery_id'] ?? ''),
                'title'         => (string) ($item['title'] ?? 'Work'),
                'type'          => (string) ($item['type'] ?? 'image'),
                'type_label'    => $this->type_label((string) ($item['type'] ?? 'image')),
                'thumbnail_url' => (string) ($item['thumbnail_url'] ?? $item['thumbnail'] ?? ''),
                'provider'      => (string) ($item['provider'] ?? 'community'),
                'creator'       => (string) ($item['creator'] ?? ''),
                'likes'         => (int) ($item['likes'] ?? 0),
                'created_at'    => (string) ($item['created_at'] ?? ''),
                'feed_source'   => 'community',
                'is_platform'   => true,
            ];
        }
        return $works;
    }

    private function resolve_project(array $section, int $user_id, int $limit): array {
        $project_id = sanitize_text_field($section['project_id'] ?? '');
        if ($user_id <= 0 || $project_id === '' || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $items = (new YooY_Gallery_Store())->list($user_id, ['project_id' => $project_id]);
        return array_map([$this, 'public_work_card'], array_slice($items, 0, $limit));
    }

    private function resolve_project_section(array $section, int $user_id): array {
        $limit = max(1, min(12, (int) ($section['limit'] ?? 6)));
        if ($user_id <= 0 || !class_exists('YooY_Project_Store')) {
            return [];
        }
        $store = new YooY_Project_Store();
        $project_id = sanitize_text_field($section['project_id'] ?? '');
        if ($project_id !== '') {
            $project = $store->get($user_id, $project_id);
            return $project ? [$this->public_project_card($project)] : [];
        }
        $store->sync_asset_counts($user_id);
        return array_map([$this, 'public_project_card'], array_slice($store->list($user_id, $limit), 0, $limit));
    }

    private function public_project_card(array $project): array {
        return [
            'id'             => (string) ($project['id'] ?? ''),
            'title'          => (string) ($project['title'] ?? 'Project'),
            'description'    => (string) ($project['description'] ?? ''),
            'thumbnail_url'  => (string) ($project['thumbnail_url'] ?? ''),
            'asset_count'    => (int) ($project['asset_count'] ?? $project['items'] ?? 0),
            'updated_at'     => (string) ($project['updated_at'] ?? $project['created_at'] ?? ''),
            'created_at'     => (string) ($project['created_at'] ?? ''),
            'visibility'     => (string) ($project['visibility'] ?? 'private'),
            'type'           => (string) ($project['type'] ?? $project['project_type'] ?? 'mixed'),
        ];
    }

    private function resolve_category(array $section, int $user_id, int $limit): array {
        $category = sanitize_text_field($section['category'] ?? '');
        if ($user_id <= 0 || $category === '' || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $items = (new YooY_Gallery_Store())->list($user_id, ['type' => $category]);
        return array_map([$this, 'public_work_card'], array_slice($items, 0, $limit));
    }

    private function resolve_tag(array $section, int $user_id, int $limit): array {
        $tag = strtolower(trim(sanitize_text_field($section['tag'] ?? '')));
        if ($user_id <= 0 || $tag === '' || !class_exists('YooY_Gallery_Store')) {
            return [];
        }
        $items = array_values(array_filter((new YooY_Gallery_Store())->list($user_id, []), function ($item) use ($tag) {
            $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
            $tags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
            $hay = strtolower(($item['title'] ?? '') . ' ' . ($item['prompt'] ?? '') . ' ' . implode(' ', $tags));
            return strpos($hay, $tag) !== false;
        }));
        return array_map([$this, 'public_work_card'], array_slice($items, 0, $limit));
    }

    private function public_work_card(array $item): array {
        return [
            'id'            => (string) ($item['id'] ?? ''),
            'title'         => (string) ($item['title'] ?? 'Work'),
            'type'          => (string) ($item['type'] ?? 'image'),
            'type_label'    => $this->type_label((string) ($item['type'] ?? 'image')),
            'thumbnail_url' => (string) ($item['thumbnail_url'] ?? $item['thumbnail'] ?? $item['image_url'] ?? ''),
            'large_url'     => (string) ($item['large_url'] ?? $item['full_url'] ?? $item['image_url'] ?? $item['thumbnail_url'] ?? ''),
            'full_url'      => (string) ($item['full_url'] ?? $item['original_url'] ?? $item['image_url'] ?? ''),
            'display_url'   => (string) ($item['display_url'] ?? $item['large_url'] ?? $item['full_url'] ?? $item['thumbnail_url'] ?? ''),
            'provider'      => (string) ($item['provider'] ?? ''),
            'project_id'    => (string) ($item['project_id'] ?? ''),
            'project_title' => (string) ($item['project_title'] ?? ''),
            'created_at'    => (string) ($item['created_at'] ?? ''),
            'updated_at'    => (string) ($item['updated_at'] ?? ''),
            'creator'       => (string) ($item['creator'] ?? ''),
            'feed_source'   => 'user',
            'is_platform'   => false,
            'srcset'        => (string) ($item['srcset'] ?? ''),
            'sizes'         => (string) ($item['sizes'] ?? ''),
            'images'        => is_array($item['images'] ?? null) ? $item['images'] : [],
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

    private function default_sections(): array {
        return [
            $this->normalize([
                'id'           => 'sec_focus',
                'title'        => '집중형 작품',
                'description'  => '인물, 제품, 포스터 등 집중감 있는 작품',
                'display_type' => 'gallery',
                'data_source'  => 'gallery',
                'type'         => 'latest',
                'source'       => 'mixed',
                'filter'       => ['orientation' => 'portrait'],
                'layout'       => 'carousel',
                'card_ratio'   => 'portrait',
                'visible'      => true,
                'limit'        => 12,
                'column_count' => 'carousel',
                'sort_order'   => 0,
            ]),
            $this->normalize([
                'id'           => 'sec_wide',
                'title'        => '확장형 작품',
                'description'  => '풍경, 배경, 배너 등 넓고 시원한 작품',
                'display_type' => 'gallery',
                'data_source'  => 'gallery',
                'type'         => 'latest',
                'source'       => 'mixed',
                'filter'       => ['orientation' => 'landscape'],
                'layout'       => 'carousel',
                'card_ratio'   => 'wide',
                'visible'      => true,
                'limit'        => 12,
                'column_count' => 'carousel',
                'sort_order'   => 1,
            ]),
            $this->normalize([
                'id'           => 'sec_templates',
                'title'        => '추천 템플릿',
                'description'  => '바로 시작할 수 있는 인기 템플릿',
                'display_type' => 'template',
                'data_source'  => 'templates',
                'type'         => 'official',
                'source'       => 'official',
                'layout'       => 'carousel',
                'visible'      => true,
                'limit'        => 10,
                'column_count' => 'carousel',
                'sort_order'   => 2,
            ]),
            $this->normalize([
                'id'           => 'sec_recent',
                'title'        => '최근 작업',
                'description'  => '이어서 작업하거나 다시 열어보세요',
                'display_type' => 'recent',
                'data_source'  => 'gallery',
                'type'         => 'latest',
                'source'       => 'user',
                'layout'       => 'grid',
                'visible'      => true,
                'limit'        => 8,
                'column_count' => 4,
                'sort_order'   => 3,
            ]),
            $this->normalize([
                'id'           => 'sec_saved_templates',
                'title'        => '저장된 템플릿',
                'description'  => '내가 저장한 프롬프트와 템플릿',
                'display_type' => 'template',
                'data_source'  => 'gallery',
                'type'         => 'featured',
                'source'       => 'user',
                'layout'       => 'grid',
                'visible'      => true,
                'limit'        => 8,
                'column_count' => 4,
                'sort_order'   => 4,
            ]),
            $this->normalize([
                'id'           => 'sec_guide',
                'title'        => '초보자 가이드',
                'description'  => '처음이어도 쉽게 따라 할 수 있어요',
                'display_type' => 'guide',
                'data_source'  => 'guide',
                'type'         => 'guide',
                'source'       => 'mixed',
                'layout'       => 'grid',
                'visible'      => true,
                'limit'        => 3,
                'column_count' => 3,
                'sort_order'   => 5,
            ]),
        ];
    }

    private function normalize(array $section): array {
        $type = sanitize_text_field($section['type'] ?? 'latest');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'latest';
        }

        $source = sanitize_text_field($section['source'] ?? 'user');
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'user';
        }

        $manual_ids = [];
        if (is_array($section['manual_ids'] ?? null)) {
            foreach ($section['manual_ids'] as $id) {
                $id = sanitize_text_field((string) $id);
                if ($id !== '') {
                    $manual_ids[] = $id;
                }
            }
        }

        $column_count = $this->normalize_column_count($section);

        return [
            'id'          => sanitize_text_field($section['id'] ?? ('sec_' . wp_generate_uuid4())),
            'title'       => sanitize_text_field($section['title'] ?? 'Home Section'),
            'description' => sanitize_textarea_field($section['description'] ?? ''),
            'type'        => $type,
            'display_type'=> $this->normalize_display_type($section),
            'data_source' => $this->normalize_data_source($section),
            'source'      => $source,
            'layout'      => $this->normalize_layout($section),
            'column_count' => $column_count,
            'card_ratio'  => $this->normalize_card_ratio($section),
            'text_mode'   => $this->normalize_text_mode($section),
            'filter'      => $this->normalize_filter($section),
            'visible'     => !empty($section['visible']),
            'limit'       => max(1, min(24, (int) ($section['limit'] ?? 8))),
            'sort_order'  => (int) ($section['sort_order'] ?? $section['order'] ?? 0),
            'manual_ids'  => $manual_ids,
            'project_id'  => sanitize_text_field($section['project_id'] ?? ''),
            'category'    => sanitize_text_field($section['category'] ?? ''),
            'tag'         => sanitize_text_field($section['tag'] ?? ''),
            'cover_work_id' => sanitize_text_field($section['cover_work_id'] ?? ''),
            'created_at'  => $section['created_at'] ?? gmdate('c'),
            'updated_at'  => $section['updated_at'] ?? gmdate('c'),
        ];
    }

    private function normalize_display_type(array $section): string {
        $raw = sanitize_text_field($section['display_type'] ?? '');
        if ($raw !== '' && in_array($raw, self::DISPLAY_TYPES, true)) {
            return $raw;
        }

        $type = sanitize_text_field($section['type'] ?? 'latest');
        if (in_array($type, self::DISPLAY_TYPES, true)) {
            return $type;
        }
        if ($type === 'project') {
            return 'projects';
        }
        if ($type === 'community' || $type === 'marketplace') {
            return 'gallery';
        }
        return 'gallery';
    }

    private function normalize_data_source(array $section): string {
        $raw = sanitize_text_field($section['data_source'] ?? '');
        if ($raw !== '' && in_array($raw, self::DATA_SOURCES, true)) {
            return $raw;
        }

        $display = $this->normalize_display_type($section);
        if ($display === 'guide') {
            return 'guide';
        }
        if ($display === 'projects') {
            return 'projects';
        }
        if ($display === 'template') {
            return 'templates';
        }

        return $this->normalize_source($section);
    }

    private function normalize_layout(array $section): string {
        $layout = sanitize_text_field($section['layout'] ?? '');
        if ($layout === 'carousel' || $layout === 'grid') {
            return $layout;
        }
        $columns = $section['column_count'] ?? null;
        if ($columns === 'carousel' || (is_string($columns) && strtolower($columns) === 'carousel')) {
            return 'carousel';
        }
        return 'grid';
    }

    /**
     * @return array<string, string>
     */
    private function normalize_filter(array $section): array {
        $filter = $section['filter'] ?? [];
        if (!is_array($filter)) {
            return [];
        }
        $out = [];
        $orientation = sanitize_text_field($filter['orientation'] ?? '');
        if (in_array($orientation, ['portrait', 'landscape', 'square', 'wide'], true)) {
            $out['orientation'] = $orientation;
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $works
     * @param array<string, string> $filter
     * @return array<int, array<string, mixed>>
     */
    private function apply_filters(array $works, array $filter): array {
        $orientation = sanitize_text_field($filter['orientation'] ?? '');
        if ($orientation === '') {
            return $works;
        }

        $filtered = array_values(array_filter($works, function ($work) use ($orientation) {
            return $this->work_matches_orientation($work, $orientation);
        }));

        if (!empty($filtered)) {
            return $filtered;
        }

        return $works;
    }

    private function work_matches_orientation(array $work, string $orientation): bool {
        $ratio = $this->work_aspect_ratio($work);
        if ($ratio === null) {
            return true;
        }

        switch ($orientation) {
            case 'portrait':
                return $ratio < 0.95;
            case 'landscape':
            case 'wide':
                return $ratio > 1.05;
            case 'square':
                return $ratio >= 0.95 && $ratio <= 1.05;
            default:
                return true;
        }
    }

    private function work_aspect_ratio(array $work): ?float {
        $meta = is_array($work['meta'] ?? null) ? $work['meta'] : [];
        $w = (int) ($work['width'] ?? $meta['width'] ?? 0);
        $h = (int) ($work['height'] ?? $meta['height'] ?? 0);
        if ($w > 0 && $h > 0) {
            return $w / $h;
        }

        $type = sanitize_text_field($work['type'] ?? '');
        if (in_array($type, ['video', 'writing', 'music', 'voice'], true)) {
            return 16 / 9;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolve_templates(array $section, int $user_id): array {
        $limit = max(1, min(24, (int) ($section['limit'] ?? 8)));
        $official = $this->resolve_official($limit, false);
        if (!empty($official)) {
            return array_map(function ($item) {
                $item['seed'] = (string) ($item['seed_prompt'] ?? $item['prompt'] ?? $item['title'] ?? '');
                $item['seed_prompt'] = $item['seed'];
                return $item;
            }, $official);
        }

        return $this->apply_filters(
            $this->resolve_works(array_merge($section, ['type' => 'featured', 'source' => 'user']), $user_id),
            $section['filter'] ?? []
        );
    }

    /**
     * @return int|string carousel or 2-6
     */
    private function normalize_column_count(array $section) {
        $raw = $section['column_count'] ?? null;

        if ($raw === null || $raw === '') {
            $legacy = sanitize_text_field($section['layout'] ?? '');
            if ($legacy === 'carousel') {
                return 'carousel';
            }
            if ($legacy === 'grid-large') {
                return 4;
            }
            $raw = 4;
        }

        if ($raw === 'carousel' || (is_string($raw) && strtolower($raw) === 'carousel')) {
            return 'carousel';
        }

        $count = (int) $raw;
        if ($count < 2) {
            $count = 2;
        }
        if ($count > 6) {
            $count = 6;
        }

        return $count;
    }

    private function normalize_card_ratio(array $section): string {
        $raw = sanitize_text_field($section['card_ratio'] ?? 'auto');
        return in_array($raw, self::CARD_RATIOS, true) ? $raw : 'auto';
    }

    private function normalize_text_mode(array $section): string {
        $raw = sanitize_text_field($section['text_mode'] ?? 'below');
        return in_array($raw, self::TEXT_MODES, true) ? $raw : 'below';
    }
}
