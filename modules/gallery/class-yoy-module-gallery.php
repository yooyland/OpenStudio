<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Gallery extends YooY_Module_Base {

    private YooY_Gallery_Store $store;
    private YooY_Gallery_Aggregator $aggregator;
    private YooY_Gallery_Actions $actions;

    public function id(): string { return 'gallery'; }
    public function name(): string { return 'Gallery'; }
    public function description(): string { return 'Unified gallery for all generated content.'; }
    public function version(): string { return '2.0.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->store       = new YooY_Gallery_Store();
        $this->aggregator  = new YooY_Gallery_Aggregator($this->store);
        $this->actions     = new YooY_Gallery_Actions($this->store);
    }

    public function register_rest_routes(): void {
        $this->register_route('/showcase', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'showcase'],
            'permission_callback' => '__return_true',
        ]);

        $this->register_route('/items', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'items'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'item'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_item'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_item'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_item'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/favorite', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'toggle_favorite'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/visibility', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'set_visibility'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/copy', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'copy_prompt'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/regenerate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'regenerate'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/download', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'download'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/marketplace', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'marketplace'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/community', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'community'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/publish', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'publish'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/items/(?P<id>[a-zA-Z0-9_-]+)/project', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_project'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'sync'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/works', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'works'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        $this->register_route('/types', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'types'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function capture_item(int $user_id, array $entry, string $type, string $studio): array {
        return $this->aggregator->from_generation($user_id, $entry, $type, $studio);
    }

    public function showcase(): WP_REST_Response {
        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];

        $items = array_map(function ($post) {
            return [
                'id'        => $post['gallery_id'] ?? $post['id'] ?? '',
                'type'      => $post['type'] ?? 'image',
                'title'     => $post['title'] ?? '',
                'prompt'    => $post['prompt'] ?? '',
                'thumbnail' => $post['thumbnail'] ?? '',
                'creator'   => $post['creator'] ?? '',
                'likes'     => (int) ($post['likes'] ?? 0),
                'created_at'=> $post['created_at'] ?? '',
            ];
        }, array_slice($feed, 0, 20));

        return $this->success(['items' => $items]);
    }

    public function types(): WP_REST_Response {
        return $this->success([
            'types' => [
                ['id' => 'all', 'label' => '전체'],
                ['id' => 'video', 'label' => '영상'],
                ['id' => 'image', 'label' => '이미지'],
                ['id' => 'music', 'label' => '음악'],
                ['id' => 'writing', 'label' => '글'],
                ['id' => 'avatar', 'label' => '아바타'],
                ['id' => 'voice', 'label' => '음성'],
            ],
        ]);
    }

    public function items(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        $filters = [
            'type'     => sanitize_text_field($request->get_param('type') ?? ''),
            'favorite' => $request->get_param('favorite'),
        ];

        if ($request->get_param('sync') === '1') {
            $this->aggregator->sync($user);
        }

        $items = $this->store->list($user, $filters);
        if (empty($items)) {
            $items = $this->aggregator->sync($user);
            if ($filters['type'] !== '') {
                $items = array_values(array_filter($items, fn($i) => ($i['type'] ?? '') === $filters['type']));
            }
        }

        return $this->success(['items' => $items, 'total' => count($items)]);
    }

    public function item(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        $id   = sanitize_text_field($request->get_param('id'));
        $item = $this->store->get($user, $id);

        if (!$item) {
            $this->aggregator->sync($user);
            $item = $this->store->get($user, $id);
        }

        if (!$item) return $this->error('Item not found.', 404);
        return $this->success(['item' => $this->detail($item)]);
    }

    public function save_item(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        $body = $request->get_json_params();
        if (!is_array($body)) return $this->error('Invalid payload.');

        $saved = $this->store->save($user, $body);
        return $this->success(['item' => $this->detail($saved)]);
    }

    public function update_item(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        $id   = sanitize_text_field($request->get_param('id'));
        $body = $request->get_json_params();
        if (!is_array($body)) return $this->error('Invalid payload.');

        $updated = $this->store->update($user, $id, $body);
        if (!$updated) return $this->error('Item not found.', 404);
        return $this->success(['item' => $this->detail($updated)]);
    }

    public function delete_item(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        $id = sanitize_text_field($request->get_param('id'));
        if (!$this->store->remove($user, $id)) return $this->error('Item not found.', 404);
        return $this->success(['deleted' => true]);
    }

    public function toggle_favorite(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id   = sanitize_text_field($request->get_param('id'));
            $item = $this->actions->toggle_favorite($user, $id);
            return $this->success(['item' => $this->detail($item)]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function set_visibility(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id     = sanitize_text_field($request->get_param('id'));
            $body   = $request->get_json_params();
            $public = !empty($body['public']);
            $item   = $this->actions->set_visibility($user, $id, $public);
            return $this->success(['item' => $this->detail($item)]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function copy_prompt(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success($this->actions->copy_prompt($user, $id));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function regenerate(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success($this->actions->regenerate_payload($user, $id));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function download(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success($this->actions->download_info($user, $id));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function marketplace(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success($this->actions->register_marketplace($user, $id));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function community(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success($this->actions->share_community($user, $id));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function publish(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success($this->actions->publish_to_gallery($user, $id));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function save_project(WP_REST_Request $request): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        try {
            $id = sanitize_text_field($request->get_param('id'));
            $body = $request->get_json_params() ?: [];
            $project_id = sanitize_text_field($body['project_id'] ?? '');
            return $this->success($this->actions->save_to_project($user, $id, $project_id ?: null));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    public function sync(): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        $items = $this->aggregator->sync($user);
        return $this->success(['items' => $items, 'total' => count($items)]);
    }

    public function works(): WP_REST_Response {
        $user = $this->require_user();
        if ($user instanceof WP_REST_Response) return $user;

        $items = $this->aggregator->sync($user);
        return $this->success(['works' => $items, 'items' => $items]);
    }

    private function detail(array $item): array {
        return array_merge($item, [
            'type_label'     => $this->type_label($item['type'] ?? ''),
            'provider_label' => strtoupper($item['provider'] ?? 'MOCK'),
            'created_label'  => $this->format_date($item['created_at'] ?? ''),
        ]);
    }

    private function type_label(string $type): string {
        return match ($type) {
            'video'   => '영상',
            'image'   => '이미지',
            'music'   => '음악',
            'writing' => '글',
            'avatar'  => '아바타',
            'voice'   => '음성',
            default   => $type,
        };
    }

    private function format_date(string $iso): string {
        if ($iso === '') return '';
        $ts = strtotime($iso);
        return $ts ? date_i18n('Y-m-d H:i', $ts) : $iso;
    }
}
