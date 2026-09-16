<?php
if (!defined('ABSPATH')) exit;

/**
 * Creative Canvas Store — visual AI workflow board per Project.
 * Nodes reference gallery_id (Gallery SoT); no media duplication.
 */
final class YooY_Project_Canvas_Store {

    public const RELATION_GENERATED_FROM = 'generated_from';
    public const RELATION_USED_AS_REFERENCE = 'used_as_reference';
    public const RELATION_VARIANT_OF = 'variant_of';
    public const RELATION_FOR_PROJECT = 'for_project';
    public const RELATION_PUBLISH_CANDIDATE = 'publish_candidate';

    /** @var YooY_Project_Store */
    private $projects;

    public function __construct(?YooY_Project_Store $projects = null) {
        if ($projects instanceof YooY_Project_Store) {
            $this->projects = $projects;
            return;
        }
        if (!class_exists('YooY_Project_Store')) {
            $file = defined('YOY_AI_STUDIO_MODULES_DIR')
                ? YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php'
                : dirname(__DIR__) . '/class-project-store.php';
            if (is_readable($file)) {
                require_once $file;
            }
        }
        $this->projects = new YooY_Project_Store();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(int $user_id, string $project_id): ?array {
        $project = $this->projects->get($user_id, $project_id);
        if (!$project) {
            return null;
        }
        return $this->normalize_canvas($project['canvas'] ?? null, $project_id);
    }

    /**
     * @param array<string, mixed> $canvas_data
     * @return array<string, mixed>|null
     */
    public function save(int $user_id, string $project_id, array $canvas_data): ?array {
        $project = $this->projects->get($user_id, $project_id);
        if (!$project) {
            return null;
        }
        $canvas = $this->normalize_canvas($canvas_data, $project_id);
        $canvas['updated_at'] = gmdate('c');
        $updated = $this->projects->update($user_id, $project_id, ['canvas' => $canvas]);
        if (!$updated) {
            return null;
        }
        return $this->normalize_canvas($updated['canvas'] ?? $canvas, $project_id);
    }

    /**
     * @param array<string, mixed> $node_data
     * @return array<string, mixed>|null Full canvas after insert
     */
    public function add_node(int $user_id, string $project_id, array $node_data): ?array {
        $canvas = $this->get($user_id, $project_id);
        if (!$canvas) {
            return null;
        }
        $node = $this->normalize_node($node_data, $project_id);
        $nodes = is_array($canvas['nodes'] ?? null) ? $canvas['nodes'] : [];
        // Dedupe image nodes by gallery_id when provided.
        if ($node['linked_gallery_id'] !== '') {
            $nodes = array_values(array_filter($nodes, function ($n) use ($node) {
                return ($n['linked_gallery_id'] ?? '') !== $node['linked_gallery_id']
                    || ($n['node_type'] ?? '') !== ($node['node_type'] ?? '');
            }));
        }
        $nodes[] = $node;
        $canvas['nodes'] = array_slice($nodes, 0, 120);
        $canvas['updated_at'] = gmdate('c');
        return $this->save($user_id, $project_id, $canvas);
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>|null
     */
    public function update_node(int $user_id, string $project_id, string $node_id, array $patch): ?array {
        $canvas = $this->get($user_id, $project_id);
        if (!$canvas) {
            return null;
        }
        $found = false;
        foreach ($canvas['nodes'] as $i => $node) {
            if (($node['node_id'] ?? '') !== $node_id) {
                continue;
            }
            $merged = array_merge($node, $patch);
            $merged['node_id'] = $node_id;
            $canvas['nodes'][$i] = $this->normalize_node($merged, $project_id);
            $found = true;
            break;
        }
        if (!$found) {
            return null;
        }
        $canvas['updated_at'] = gmdate('c');
        return $this->save($user_id, $project_id, $canvas);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function remove_node(int $user_id, string $project_id, string $node_id): ?array {
        $canvas = $this->get($user_id, $project_id);
        if (!$canvas) {
            return null;
        }
        $canvas['nodes'] = array_values(array_filter($canvas['nodes'], function ($n) use ($node_id) {
            return ($n['node_id'] ?? '') !== $node_id;
        }));
        $canvas['edges'] = array_values(array_filter($canvas['edges'], function ($e) use ($node_id) {
            return ($e['from_node_id'] ?? '') !== $node_id && ($e['to_node_id'] ?? '') !== $node_id;
        }));
        $canvas['updated_at'] = gmdate('c');
        return $this->save($user_id, $project_id, $canvas);
    }

    /**
     * @param array<string, mixed> $edge_data
     * @return array<string, mixed>|null
     */
    public function add_edge(int $user_id, string $project_id, array $edge_data): ?array {
        $canvas = $this->get($user_id, $project_id);
        if (!$canvas) {
            return null;
        }
        $edge = $this->normalize_edge($edge_data);
        $edges = is_array($canvas['edges'] ?? null) ? $canvas['edges'] : [];
        $edges[] = $edge;
        $canvas['edges'] = array_slice($edges, 0, 200);
        $canvas['updated_at'] = gmdate('c');
        return $this->save($user_id, $project_id, $canvas);
    }

    /**
     * Convenience: add generated image node linked from prompt/reference nodes.
     *
     * @param string[] $from_node_ids
     * @return array<string, mixed>|null
     */
    public function add_generated_result(
        int $user_id,
        string $project_id,
        string $gallery_id,
        array $from_node_ids = [],
        array $layout = []
    ): ?array {
        $gallery_id = sanitize_text_field($gallery_id);
        if ($gallery_id === '') {
            return null;
        }
        $title = 'Generated';
        $thumb = '';
        if (class_exists('YooY_Gallery_Store')) {
            $g = new YooY_Gallery_Store();
            $item = $g->get($user_id, $gallery_id);
            if (is_array($item)) {
                $title = (string) ($item['title'] ?? $item['display_title'] ?? $title);
                $thumb = (string) ($item['thumbnail_url'] ?? $item['full_url'] ?? $item['image_url'] ?? '');
            }
        }
        $node = [
            'node_type'          => 'generated_image',
            'linked_gallery_id'  => $gallery_id,
            'linked_project_id'  => $project_id,
            'prompt_text'        => '',
            'note_text'          => '',
            'x'                  => isset($layout['x']) ? (float) $layout['x'] : 420,
            'y'                  => isset($layout['y']) ? (float) $layout['y'] : 180,
            'width'              => isset($layout['width']) ? (float) $layout['width'] : 240,
            'height'             => isset($layout['height']) ? (float) $layout['height'] : 280,
            'z_index'            => 10,
            'metadata_json'      => [
                'title'         => $title,
                'thumbnail_url' => $thumb,
            ],
        ];
        $canvas = $this->add_node($user_id, $project_id, $node);
        if (!$canvas) {
            return null;
        }
        $new_id = '';
        foreach (array_reverse($canvas['nodes']) as $n) {
            if (($n['linked_gallery_id'] ?? '') === $gallery_id && ($n['node_type'] ?? '') === 'generated_image') {
                $new_id = (string) ($n['node_id'] ?? '');
                break;
            }
        }
        foreach ($from_node_ids as $from) {
            $from = sanitize_text_field((string) $from);
            if ($from === '' || $new_id === '') {
                continue;
            }
            $canvas = $this->add_edge($user_id, $project_id, [
                'from_node_id'  => $from,
                'to_node_id'    => $new_id,
                'relation_type' => self::RELATION_GENERATED_FROM,
            ]) ?: $canvas;
        }
        // Also ensure project assets link (Gallery SoT).
        $this->projects->add_asset($user_id, $project_id, [
            'gallery_id' => $gallery_id,
            'type'       => 'image',
            'title'      => $title,
            'thumbnail'  => $thumb,
        ]);
        return $this->get($user_id, $project_id);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function normalize_canvas($raw, string $project_id): array {
        if (!is_array($raw)) {
            $raw = [];
        }
        $nodes = [];
        foreach ((array) ($raw['nodes'] ?? []) as $n) {
            if (!is_array($n)) {
                continue;
            }
            $nodes[] = $this->normalize_node($n, $project_id);
        }
        $edges = [];
        foreach ((array) ($raw['edges'] ?? []) as $e) {
            if (!is_array($e)) {
                continue;
            }
            $edges[] = $this->normalize_edge($e);
        }
        $title = sanitize_text_field((string) ($raw['title'] ?? 'Creative Canvas'));
        if ($title === '') {
            $title = 'Creative Canvas';
        }
        return [
            'canvas_id'  => sanitize_text_field((string) ($raw['canvas_id'] ?? ('canvas_' . $project_id))),
            'project_id' => $project_id,
            'title'      => $title,
            'viewport'   => [
                'x'     => (float) ($raw['viewport']['x'] ?? 0),
                'y'     => (float) ($raw['viewport']['y'] ?? 0),
                'zoom'  => max(0.25, min(2.5, (float) ($raw['viewport']['zoom'] ?? 1))),
            ],
            'nodes'      => $nodes,
            'edges'      => $edges,
            'created_at' => sanitize_text_field((string) ($raw['created_at'] ?? gmdate('c'))),
            'updated_at' => sanitize_text_field((string) ($raw['updated_at'] ?? gmdate('c'))),
        ];
    }

    /**
     * @param array<string, mixed> $n
     * @return array<string, mixed>
     */
    private function normalize_node(array $n, string $project_id): array {
        $type = sanitize_key((string) ($n['node_type'] ?? 'note'));
        $allowed = ['prompt', 'reference_image', 'generated_image', 'note', 'group', 'action', 'studio_handoff'];
        if (!in_array($type, $allowed, true)) {
            $type = 'note';
        }
        $meta = $n['metadata_json'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        return [
            'node_id'           => sanitize_text_field((string) ($n['node_id'] ?? ('node_' . wp_generate_uuid4()))),
            'canvas_id'         => sanitize_text_field((string) ($n['canvas_id'] ?? '')),
            'node_type'         => $type,
            'linked_gallery_id' => sanitize_text_field((string) ($n['linked_gallery_id'] ?? '')),
            'linked_project_id' => sanitize_text_field((string) ($n['linked_project_id'] ?? $project_id)),
            'prompt_text'       => sanitize_textarea_field((string) ($n['prompt_text'] ?? '')),
            'note_text'         => sanitize_textarea_field((string) ($n['note_text'] ?? '')),
            'metadata_json'     => $meta,
            'x'                 => (float) ($n['x'] ?? 80),
            'y'                 => (float) ($n['y'] ?? 80),
            'width'             => max(120, (float) ($n['width'] ?? 220)),
            'height'            => max(80, (float) ($n['height'] ?? 160)),
            'z_index'           => (int) ($n['z_index'] ?? 1),
            'created_at'        => sanitize_text_field((string) ($n['created_at'] ?? gmdate('c'))),
            'updated_at'        => sanitize_text_field((string) ($n['updated_at'] ?? gmdate('c'))),
        ];
    }

    /**
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    private function normalize_edge(array $e): array {
        $rel = sanitize_key((string) ($e['relation_type'] ?? self::RELATION_GENERATED_FROM));
        $allowed = [
            self::RELATION_GENERATED_FROM,
            self::RELATION_USED_AS_REFERENCE,
            self::RELATION_VARIANT_OF,
            self::RELATION_FOR_PROJECT,
            self::RELATION_PUBLISH_CANDIDATE,
        ];
        if (!in_array($rel, $allowed, true)) {
            $rel = self::RELATION_GENERATED_FROM;
        }
        $meta = $e['metadata_json'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }
        return [
            'edge_id'       => sanitize_text_field((string) ($e['edge_id'] ?? ('edge_' . wp_generate_uuid4()))),
            'canvas_id'     => sanitize_text_field((string) ($e['canvas_id'] ?? '')),
            'from_node_id'  => sanitize_text_field((string) ($e['from_node_id'] ?? '')),
            'to_node_id'    => sanitize_text_field((string) ($e['to_node_id'] ?? '')),
            'relation_type' => $rel,
            'metadata_json' => $meta,
        ];
    }
}
