<?php
if (!defined('ABSPATH')) exit;

/**
 * Projects REST handlers — shared by module + core fallback registration.
 * Canonical Asset references only (gallery_id); no second Asset Store.
 */
final class YooY_Projects_REST {

    /** @var bool */
    private static $registered = false;

    /** @var YooY_Project_Store */
    private $store;

    public function __construct(?YooY_Project_Store $store = null) {
        if ($store instanceof YooY_Project_Store) {
            $this->store = $store;
            return;
        }
        if (!class_exists('YooY_Project_Store')) {
            $file = defined('YOY_AI_STUDIO_MODULES_DIR')
                ? YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php'
                : '';
            if ($file !== '' && is_readable($file)) {
                require_once $file;
            }
        }
        $this->store = new YooY_Project_Store();
    }

    /**
     * Idempotent registration of /yoy-ai-studio/v1/projects* routes.
     */
    public static function register_routes(): void {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        $self = new self();
        $auth = 'is_user_logged_in';

        register_rest_route('yoy-ai-studio/v1', '/projects', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$self, 'list_projects'],
                'permission_callback' => $auth,
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$self, 'create_project'],
                'permission_callback' => $auth,
            ],
        ]);

        register_rest_route('yoy-ai-studio/v1', '/projects/(?P<id>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$self, 'get_project'],
                'permission_callback' => $auth,
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$self, 'update_project'],
                'permission_callback' => $auth,
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$self, 'delete_project'],
                'permission_callback' => $auth,
            ],
        ]);

        register_rest_route('yoy-ai-studio/v1', '/projects/(?P<id>[a-zA-Z0-9_-]+)/assets', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$self, 'add_asset'],
                'permission_callback' => $auth,
            ],
        ]);

        register_rest_route('yoy-ai-studio/v1', '/projects/(?P<id>[a-zA-Z0-9_-]+)/assets/(?P<asset_id>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$self, 'remove_asset'],
                'permission_callback' => $auth,
            ],
        ]);
    }

    public function list_projects(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        $this->store->sync_asset_counts($user_id);
        return $this->ok(['projects' => $this->store->list($user_id)]);
    }

    public function create_project(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $title = sanitize_text_field((string) $this->param($request, 'title', ''));
        if ($title === '') {
            $title = sanitize_text_field((string) $this->param($request, 'name', ''));
        }
        if ($title === '') {
            return $this->fail('프로젝트 이름을 입력해 주세요.', 400);
        }
        if (function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') > 120 : strlen($title) > 120) {
            return $this->fail('프로젝트 이름은 120자 이하여야 합니다.', 400);
        }

        $project = $this->store->create($user_id, [
            'title'       => $title,
            'description' => sanitize_textarea_field((string) $this->param($request, 'description', '')),
            'type'        => sanitize_text_field((string) $this->param($request, 'type', 'mixed')),
            'visibility'  => sanitize_text_field((string) $this->param($request, 'visibility', 'private')),
            'status'      => 'active',
            'items'       => 0,
            'assets'      => [],
        ]);

        $work_ids = $this->param($request, 'work_ids', []);
        if (is_array($work_ids) && !empty($work_ids)) {
            $project = $this->attach_works($user_id, $project, $work_ids) ?? $project;
        }

        return $this->ok(['project' => $project], 201);
    }

    public function get_project(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        $id = sanitize_text_field((string) $request->get_param('id'));
        $project = $this->store->get($user_id, $id);
        if (!$project) {
            return $this->fail('프로젝트를 찾을 수 없습니다.', 404);
        }
        return $this->ok(['project' => $project]);
    }

    public function update_project(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        $id = sanitize_text_field((string) $request->get_param('id'));
        $data = $this->params($request, [
            'title', 'description', 'type', 'visibility', 'thumbnail_url', 'cover_asset_id', 'status',
        ]);
        $project = $this->store->update($user_id, $id, $data);
        if (!$project) {
            return $this->fail('프로젝트를 찾을 수 없습니다.', 404);
        }
        return $this->ok(['project' => $project]);
    }

    public function delete_project(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        $id = sanitize_text_field((string) $request->get_param('id'));
        if (!$this->store->delete($user_id, $id)) {
            return $this->fail('프로젝트를 찾을 수 없습니다.', 404);
        }
        return $this->ok(['deleted' => true, 'id' => $id]);
    }

    public function add_asset(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }

        $id = sanitize_text_field((string) $request->get_param('id'));
        if (!$this->store->get($user_id, $id)) {
            return $this->fail('프로젝트를 찾을 수 없습니다.', 404);
        }

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

        $gallery_id = sanitize_text_field((string) ($body['gallery_id'] ?? $body['id'] ?? ''));
        if ($gallery_id === '') {
            return $this->fail('연결할 Gallery Asset이 필요합니다.', 400);
        }

        // IDOR: only own gallery items.
        $gallery_item = $this->get_own_gallery_item($user_id, $gallery_id);
        if (!$gallery_item) {
            return $this->fail('작품을 찾을 수 없거나 권한이 없습니다.', 403);
        }

        $payload = array_merge($body, [
            'gallery_id' => $gallery_id,
            'type'       => $gallery_item['type'] ?? ($body['type'] ?? 'image'),
            'title'      => $gallery_item['title'] ?? ($body['title'] ?? 'Work'),
            'url'        => $gallery_item['image_url'] ?? $gallery_item['output_url'] ?? $gallery_item['asset_url'] ?? '',
            'thumbnail'  => $gallery_item['thumbnail_url'] ?? '',
        ]);

        $project = $this->store->add_asset($user_id, $id, $payload);
        if (!$project) {
            return $this->fail('프로젝트를 찾을 수 없습니다.', 404);
        }

        if (class_exists('YooY_Gallery_Store')) {
            $gallery = new YooY_Gallery_Store();
            $gallery->update($user_id, $gallery_id, ['project_id' => $id]);
            $this->store->sync_asset_counts($user_id);
            $project = $this->store->get($user_id, $id) ?? $project;
        }

        return $this->ok(['project' => $project]);
    }

    public function remove_asset(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        $id = sanitize_text_field((string) $request->get_param('id'));
        $asset_id = sanitize_text_field((string) $request->get_param('asset_id'));
        $project = $this->store->remove_asset($user_id, $id, $asset_id);
        if (!$project) {
            return $this->fail('프로젝트를 찾을 수 없습니다.', 404);
        }

        if (class_exists('YooY_Gallery_Store')) {
            $gallery = new YooY_Gallery_Store();
            $item = $gallery->get($user_id, $asset_id);
            if ($item && (string) ($item['project_id'] ?? '') === $id) {
                $gallery->update($user_id, $asset_id, ['project_id' => '']);
            }
            $this->store->sync_asset_counts($user_id);
            $project = $this->store->get($user_id, $id) ?? $project;
        }

        return $this->ok(['project' => $project]);
    }

    /**
     * @param array<int,mixed> $work_ids
     */
    private function attach_works(int $user_id, array $project, array $work_ids): ?array {
        $project_id = (string) ($project['id'] ?? '');
        if ($project_id === '' || !class_exists('YooY_Gallery_Store')) {
            return $project;
        }
        $gallery = new YooY_Gallery_Store();
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

    private function get_own_gallery_item(int $user_id, string $gallery_id): ?array {
        if (!class_exists('YooY_Gallery_Store')) {
            if (defined('YOY_AI_STUDIO_MODULES_DIR')) {
                $file = YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
                if (is_readable($file)) {
                    require_once $file;
                }
            }
        }
        if (!class_exists('YooY_Gallery_Store')) {
            return null;
        }
        $gallery = new YooY_Gallery_Store();
        $item = $gallery->get($user_id, $gallery_id);
        return is_array($item) ? $item : null;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private function param(WP_REST_Request $request, string $key, $default = '') {
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
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    private function params(WP_REST_Request $request, array $keys): array {
        $out = [];
        foreach ($keys as $key) {
            $body = $request->get_json_params();
            $body = is_array($body) ? $body : [];
            $value = $request->get_param($key);
            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            } elseif (array_key_exists($key, $body)) {
                $out[$key] = $body[$key];
            }
        }
        return $out;
    }

    /** @return int|WP_REST_Response */
    private function require_user() {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return $this->fail('로그인이 필요합니다.', 401);
        }
        return $user_id;
    }

    private function ok($data, int $status = 200): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'module'  => 'projects',
            'data'    => $data,
        ], $status);
    }

    private function fail(string $message, int $status = 400): WP_REST_Response {
        return new WP_REST_Response([
            'success' => false,
            'module'  => 'projects',
            'error'   => $message,
            'message' => $message,
            'code'    => $status === 401 ? 'login_required' : 'projects_error',
        ], $status);
    }
}
