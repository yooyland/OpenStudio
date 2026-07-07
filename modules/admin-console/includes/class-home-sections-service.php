<?php
if (!defined('ABSPATH')) exit;

final class YooY_Home_Sections_Service {

    private const OPTION_KEY = 'yoy_home_sections';

    private const TYPES = [
        'latest', 'featured', 'best', 'hot', 'marketplace', 'community',
        'manual', 'project', 'category', 'tag',
    ];

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
        $output = [];
        foreach ($this->list_visible() as $section) {
            $works = $this->resolve_works($section, $user_id);
            $output[] = [
                'id'          => $section['id'],
                'title'       => $section['title'],
                'description' => $section['description'],
                'type'        => $section['type'],
                'limit'       => (int) $section['limit'],
                'works'       => $works,
            ];
        }
        return $output;
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

        switch ($type) {
            case 'manual':
                return $this->resolve_manual($section, $limit);
            case 'marketplace':
                return $this->resolve_marketplace($limit);
            case 'community':
                return $this->resolve_community($limit);
            case 'project':
                return $this->resolve_project($section, $user_id, $limit);
            case 'category':
                return $this->resolve_category($section, $user_id, $limit);
            case 'tag':
                return $this->resolve_tag($section, $user_id, $limit);
            case 'featured':
                return $this->resolve_featured($user_id, $limit);
            case 'best':
                return $this->resolve_best($user_id, $limit);
            case 'hot':
                return $this->resolve_hot($user_id, $limit);
            case 'latest':
            default:
                return $this->resolve_latest($user_id, $limit);
        }
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
            'provider'      => (string) ($item['provider'] ?? ''),
            'project_id'    => (string) ($item['project_id'] ?? ''),
            'project_title' => (string) ($item['project_title'] ?? ''),
            'created_at'    => (string) ($item['created_at'] ?? ''),
            'updated_at'    => (string) ($item['updated_at'] ?? ''),
            'creator'       => (string) ($item['creator'] ?? ''),
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
                'id'          => 'sec_latest_default',
                'title'       => '새로 만든 작품',
                'description' => '최근 생성한 작품을 확인하세요.',
                'type'        => 'latest',
                'visible'     => true,
                'limit'       => 8,
                'sort_order'  => 0,
            ]),
            $this->normalize([
                'id'          => 'sec_hot_default',
                'title'       => '올해 가장 핫한 아이템',
                'description' => '올해 가장 활발한 작품들입니다.',
                'type'        => 'hot',
                'visible'     => true,
                'limit'       => 6,
                'sort_order'  => 1,
            ]),
            $this->normalize([
                'id'          => 'sec_best_default',
                'title'       => '베스트 작품',
                'description' => '즐겨찾기와 공개 작품 중 베스트.',
                'type'        => 'best',
                'visible'     => true,
                'limit'       => 6,
                'sort_order'  => 2,
            ]),
        ];
    }

    private function normalize(array $section): array {
        $type = sanitize_text_field($section['type'] ?? 'latest');
        if (!in_array($type, self::TYPES, true)) {
            $type = 'latest';
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

        return [
            'id'          => sanitize_text_field($section['id'] ?? ('sec_' . wp_generate_uuid4())),
            'title'       => sanitize_text_field($section['title'] ?? 'Home Section'),
            'description' => sanitize_textarea_field($section['description'] ?? ''),
            'type'        => $type,
            'visible'     => !empty($section['visible']),
            'limit'       => max(1, min(24, (int) ($section['limit'] ?? 8))),
            'sort_order'  => (int) ($section['sort_order'] ?? 0),
            'manual_ids'  => $manual_ids,
            'project_id'  => sanitize_text_field($section['project_id'] ?? ''),
            'category'    => sanitize_text_field($section['category'] ?? ''),
            'tag'         => sanitize_text_field($section['tag'] ?? ''),
            'cover_work_id' => sanitize_text_field($section['cover_work_id'] ?? ''),
            'created_at'  => $section['created_at'] ?? gmdate('c'),
            'updated_at'  => $section['updated_at'] ?? gmdate('c'),
        ];
    }
}
