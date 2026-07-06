<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Projects extends YooY_Module_Base {

    public function id(): string { return 'projects'; }
    public function name(): string { return 'Projects'; }
    public function description(): string { return 'User project workspace and generation history.'; }
    public function version(): string { return '1.0.0'; }

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
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    public function list(): WP_REST_Response {
        $user_id  = $this->current_user_id();
        $projects = $this->get_projects($user_id);

        if (empty($projects)) {
            $projects = $this->seed_projects($user_id);
        }

        return $this->success(['projects' => $projects]);
    }

    public function create(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        $title   = sanitize_text_field($request->get_param('title') ?: '새 프로젝트');
        $type    = sanitize_text_field($request->get_param('type') ?: 'mixed');

        $project = [
            'id'         => 'proj_' . wp_generate_uuid4(),
            'title'      => $title,
            'type'       => $type,
            'status'     => 'active',
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
            'items'      => 0,
        ];

        $projects   = $this->get_projects($user_id);
        $projects[] = $project;
        update_user_meta($user_id, 'yoy_projects', $projects);

        return $this->success(['project' => $project], 201);
    }

    public function get(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        $id      = sanitize_text_field($request->get_param('id'));
        $projects = $this->get_projects($user_id);

        foreach ($projects as $project) {
            if ($project['id'] === $id) {
                return $this->success(['project' => $project]);
            }
        }

        return $this->error('Project not found.', 404);
    }

    private function get_projects(int $user_id): array {
        $stored = get_user_meta($user_id, 'yoy_projects', true);
        return is_array($stored) ? $stored : [];
    }

    private function seed_projects(int $user_id): array {
        $projects = [
            ['id' => 'proj_demo_01', 'title' => '스마트스토어 봄 시즌 캠페인', 'type' => 'mixed', 'status' => 'active', 'created_at' => gmdate('c'), 'updated_at' => gmdate('c'), 'items' => 4],
            ['id' => 'proj_demo_02', 'title' => 'K-Beauty 유튜브 쇼츠', 'type' => 'video', 'status' => 'active', 'created_at' => gmdate('c'), 'updated_at' => gmdate('c'), 'items' => 2],
        ];
        update_user_meta($user_id, 'yoy_projects', $projects);
        return $projects;
    }
}
