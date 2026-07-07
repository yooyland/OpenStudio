<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-reference-asset-service.php';

final class YooY_Module_Reference_Assets extends YooY_Module_Base {

    private YooY_Reference_Asset_Service $service;

    public function id(): string { return 'reference-assets'; }
    public function name(): string { return 'Reference Assets'; }
    public function description(): string { return 'Universal reference assets for all AI studios.'; }
    public function version(): string { return '1.0.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->service = new YooY_Reference_Asset_Service();
    }

    public function register_rest_routes(): void {
        $auth = [$this, 'require_user'];

        $this->register_route('', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list_assets'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'upload_asset'], 'permission_callback' => $auth],
        ]);

        $this->register_route('/(?P<id>[a-zA-Z0-9_-]+)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_asset'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update_asset'], 'permission_callback' => $auth],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete_asset'], 'permission_callback' => $auth],
        ]);

        $this->register_route('/from-gallery', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'from_gallery'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/from-import/(?P<import_id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'from_import'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/from-project', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'from_project'], 'permission_callback' => $auth,
        ]);
        $this->register_route('/schema', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'schema'], 'permission_callback' => '__return_true',
        ]);
    }

    public function require_user() {
        return is_user_logged_in();
    }

    public function schema(): WP_REST_Response {
        return $this->success([
            'roles'       => YooY_Reference_Asset_Store::allowed_roles(),
            'asset_types' => ['image', 'video', 'audio', 'voice', 'document'],
            'sources'     => ['upload', 'gallery', 'import', 'project'],
        ]);
    }

    public function list_assets(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user_id();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        return $this->success([
            'assets' => $this->service->list($user_id, [
                'studio'     => $request->get_param('studio'),
                'project_id' => $request->get_param('project_id'),
                'type'       => $request->get_param('type'),
            ]),
        ]);
    }

    public function get_asset(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user_id();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        $asset = $this->service->get($user_id, sanitize_text_field($request->get_param('id')));
        if (!$asset) {
            return $this->error('Reference asset not found.', 404);
        }
        return $this->success(['asset' => $asset]);
    }

    public function upload_asset(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user_id();
            if ($user_id instanceof WP_REST_Response) {
                return $user_id;
            }
            $asset = $this->service->upload($user_id, $request->get_json_params() ?: []);
            return $this->success(['asset' => $asset], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update_asset(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user_id();
            if ($user_id instanceof WP_REST_Response) {
                return $user_id;
            }
            $id = sanitize_text_field($request->get_param('id'));
            $body = $request->get_json_params() ?: [];
            if (!empty($body['replace']) || !empty($body['file_base64'])) {
                $asset = $this->service->replace($user_id, $id, $body);
                return $this->success(['asset' => $asset]);
            }
            $asset = $this->service->rename($user_id, $id, sanitize_text_field($body['title'] ?? ''));
            if (!$asset) {
                return $this->error('Reference asset not found.', 404);
            }
            return $this->success(['asset' => $asset]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function delete_asset(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->require_user_id();
        if ($user_id instanceof WP_REST_Response) {
            return $user_id;
        }
        $ok = $this->service->remove($user_id, sanitize_text_field($request->get_param('id')));
        return $ok ? $this->success(['deleted' => true]) : $this->error('Reference asset not found.', 404);
    }

    public function from_gallery(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user_id();
            if ($user_id instanceof WP_REST_Response) {
                return $user_id;
            }
            $body = $request->get_json_params() ?: [];
            $gallery_id = sanitize_text_field($body['gallery_id'] ?? '');
            if ($gallery_id === '') {
                return $this->error('gallery_id is required.');
            }
            $asset = $this->service->from_gallery($user_id, $gallery_id, $body);
            return $this->success(['asset' => $asset], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function from_import(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user_id();
            if ($user_id instanceof WP_REST_Response) {
                return $user_id;
            }
            $asset = $this->service->from_import(
                $user_id,
                sanitize_text_field($request->get_param('import_id')),
                $request->get_json_params() ?: []
            );
            return $this->success(['asset' => $asset], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function from_project(WP_REST_Request $request): WP_REST_Response {
        try {
            $user_id = $this->require_user_id();
            if ($user_id instanceof WP_REST_Response) {
                return $user_id;
            }
            $body = $request->get_json_params() ?: [];
            $project_id = sanitize_text_field($body['project_id'] ?? '');
            $asset_id = sanitize_text_field($body['asset_id'] ?? '');
            if ($project_id === '' || $asset_id === '') {
                return $this->error('project_id and asset_id are required.');
            }
            $asset = $this->service->from_project($user_id, $project_id, $asset_id, $body);
            return $this->success(['asset' => $asset], 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function require_user_id() {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return $this->error('Authentication required.', 401);
        }
        return $user_id;
    }
}
