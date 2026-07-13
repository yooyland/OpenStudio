<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Projects extends YooY_Module_Base {

    private YooY_Project_Store $store;

    public function __construct() {
        $this->store = new YooY_Project_Store();
    }

    public function id(): string { return 'projects'; }
    public function name(): string { return 'Projects'; }
    public function description(): string { return 'User project workspace and generation history.'; }
    public function version(): string { return '1.1.0'; }

    public function register_rest_routes(): void {
        $this->register_route('', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create'],
                'permission_callback' => 'is_user_logged_in',
                'args'                => [
                    'title'       => ['type' => 'string', 'required' => true],
                    'description' => ['type' => 'string'],
                    'type'        => ['type' => 'string'],
                    'visibility'  => ['type' => 'string'],
                    'work_ids'    => ['type' => 'array'],
                ],
            ],
        ]);

        $this->register_route('/(?P<id>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'update'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);

        $this->register_route('/(?P<id>[a-zA-Z0-9_-]+)/assets', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'add_asset'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);

        $this->register_route('/(?P<id>[a-zA-Z0-9_-]+)/assets/(?P<asset_id>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'remove_asset'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);
    }

    public function list(): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        return $this->success(['projects' => $this->store->list($user_id)]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $title = sanitize_text_field((string) $this->body_param($request, 'title', ''));
        if ($title === '') {
            return $this->error('Project title is required.', 400);
        }

        $project = $this->store->create($user_id, [
            'title'       => $title,
            'description' => sanitize_textarea_field((string) $this->body_param($request, 'description', '')),
            'type'        => sanitize_text_field((string) $this->body_param($request, 'type', 'mixed')),
            'visibility'  => sanitize_text_field((string) $this->body_param($request, 'visibility', 'private')),
            'status'      => 'active',
            'items'       => 0,
            'assets'      => [],
        ]);

        $work_ids = $this->body_param($request, 'work_ids', []);
        if (!is_array($work_ids)) {
            $work_ids = [];
        }

        if (!empty($work_ids)) {
            $project = $this->attach_works_to_project($user_id, $project, $work_ids) ?? $project;
        }

        return $this->success(['project' => $project], 201);
    }

    public function get(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $id      = sanitize_text_field($request->get_param('id'));
        $project = $this->store->get($user_id, $id);

        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        return $this->success(['project' => $project]);
    }

    public function update(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $id = sanitize_text_field($request->get_param('id'));
        $project = $this->store->update($user_id, $id, $this->body_params($request, [
            'title',
            'description',
            'type',
            'visibility',
            'thumbnail_url',
            'cover_asset_id',
        ]));

        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        return $this->success(['project' => $project]);
    }

    public function delete(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $id = sanitize_text_field($request->get_param('id'));
        if (!$this->store->delete($user_id, $id)) {
            return $this->error('Project not found.', 404);
        }

        return $this->success(['deleted' => true]);
    }

    public function add_asset(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $id = sanitize_text_field($request->get_param('id'));
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];
        if (empty($body)) {
            $body = [
                'gallery_id' => $request->get_param('gallery_id'),
                'id'         => $request->get_param('asset_id'),
                'type'       => $request->get_param('type'),
                'title'      => $request->get_param('title'),
                'url'        => $request->get_param('url'),
                'thumbnail'  => $request->get_param('thumbnail'),
            ];
        }
        $project = $this->store->add_asset($user_id, $id, $body);

        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        $gallery_id = sanitize_text_field((string) ($body['gallery_id'] ?? ''));
        if ($gallery_id !== '') {
            if (!class_exists('YooY_Gallery_Store') && defined('YOY_AI_STUDIO_MODULES_DIR')) {
                $gallery_file = YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
                if (file_exists($gallery_file)) {
                    require_once $gallery_file;
                }
            }
            if (class_exists('YooY_Gallery_Store')) {
                $gallery = new YooY_Gallery_Store();
                $item = $gallery->get($user_id, $gallery_id);
                if ($item) {
                    $gallery->update($user_id, $gallery_id, ['project_id' => $id]);
                    $this->store->sync_asset_counts($user_id);
                    $project = $this->store->get($user_id, $id) ?? $project;
                }
            }
        }

        return $this->success(['project' => $project]);
    }

    public function remove_asset(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $id = sanitize_text_field($request->get_param('id'));
        $asset_id = sanitize_text_field($request->get_param('asset_id'));
        $project = $this->store->remove_asset($user_id, $id, $asset_id);

        if (!$project) {
            return $this->error('Project not found.', 404);
        }

        return $this->success(['project' => $project]);
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function body_param(WP_REST_Request $request, string $key, $default = '') {
        $value = $request->get_param($key);
        if ($value !== null && $value !== '') {
            return $value;
        }
        $body = $request->get_json_params();
        if (is_array($body) && array_key_exists($key, $body)) {
            return $body[$key];
        }
        return $default;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    private function body_params(WP_REST_Request $request, array $keys): array {
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];
        $out = [];
        foreach ($keys as $key) {
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') {
                $out[$key] = $value;
                continue;
            }
            if (array_key_exists($key, $body)) {
                $out[$key] = $body[$key];
            }
        }
        return $out;
    }

    /**
     * @param array<int, mixed> $work_ids
     */
    private function attach_works_to_project(int $user_id, array $project, array $work_ids): ?array {
        if (!class_exists('YooY_Gallery_Store')) {
            if (defined('YOY_AI_STUDIO_MODULES_DIR')) {
                $store_file = YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
                if (file_exists($store_file)) {
                    require_once $store_file;
                }
            }
        }
        if (!class_exists('YooY_Gallery_Store')) {
            return $project;
        }

        $gallery = new YooY_Gallery_Store();
        $project_id = (string) ($project['id'] ?? '');
        if ($project_id === '') {
            return $project;
        }

        foreach ($work_ids as $work_id) {
            $work_id = sanitize_text_field((string) $work_id);
            if ($work_id === '') {
                continue;
            }
            $item = $gallery->get($user_id, $work_id);
            if (!$item) {
                continue;
            }
            $this->store->link_gallery_item($user_id, $project_id, $item);
            $gallery->update($user_id, $work_id, ['project_id' => $project_id]);
        }

        $this->store->sync_asset_counts($user_id);
        return $this->store->get($user_id, $project_id);
    }
}
