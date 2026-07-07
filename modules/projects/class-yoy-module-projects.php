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

        $title = sanitize_text_field($request->get_param('title') ?? '');
        if ($title === '') {
            return $this->error('Project title is required.', 400);
        }

        $project = $this->store->create($user_id, [
            'title'       => $title,
            'description' => sanitize_textarea_field($request->get_param('description') ?? ''),
            'type'        => sanitize_text_field($request->get_param('type') ?? 'mixed'),
            'visibility'  => sanitize_text_field($request->get_param('visibility') ?? 'private'),
            'status'      => 'active',
            'items'       => 0,
            'assets'      => [],
        ]);

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
        $project = $this->store->update($user_id, $id, [
            'title'       => $request->get_param('title'),
            'description' => $request->get_param('description'),
            'type'        => $request->get_param('type'),
            'visibility'  => $request->get_param('visibility'),
            'thumbnail_url' => $request->get_param('thumbnail_url'),
            'cover_asset_id' => $request->get_param('cover_asset_id'),
        ]);

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
        $project = $this->store->add_asset($user_id, $id, $body);

        if (!$project) {
            return $this->error('Project not found.', 404);
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
}
