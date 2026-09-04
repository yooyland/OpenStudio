<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Community extends YooY_Module_Base {

    public function id(): string { return 'community'; }
    public function name(): string { return 'Community'; }
    public function description(): string { return 'Public gallery, creator posts, and community feed.'; }
    public function version(): string { return '1.1.0'; }

    public function register_rest_routes(): void {
        $this->register_route('/feed', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'feed'],
            'permission_callback' => '__return_true',
        ]);

        $this->register_route('/posts', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'posts'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_post'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);

        $this->register_route('/posts/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'post_detail'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function feed(): WP_REST_Response {
        return $this->success(['feed' => $this->public_posts()]);
    }

    public function posts(): WP_REST_Response {
        return $this->feed();
    }

    public function post_detail(WP_REST_Request $request): WP_REST_Response {
        $id = sanitize_text_field($request->get_param('id'));
        foreach ($this->public_posts() as $post) {
            if (($post['id'] ?? '') === $id || ($post['gallery_id'] ?? '') === $id) {
                return $this->success(['post' => $post]);
            }
        }
        return $this->error('Post not found.', 404);
    }

    public function create_post(WP_REST_Request $request): WP_REST_Response {
        $gallery_id = sanitize_text_field($request->get_param('gallery_id') ?: '');
        if ($gallery_id === '') {
            return $this->error('gallery_id is required. Community posts must reference a Gallery asset.');
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return $this->error('Login required.', 401);
        }

        $this->ensure_gallery_actions();
        if (!class_exists('YooY_Gallery_Store') || !class_exists('YooY_Gallery_Actions')) {
            return $this->error('Gallery unavailable.', 500);
        }

        $caption = sanitize_text_field($request->get_param('caption') ?: $request->get_param('title') ?: '');

        try {
            $actions = new YooY_Gallery_Actions(new YooY_Gallery_Store());
            $result = $actions->share_community($user_id, $gallery_id, [
                'caption' => $caption,
            ]);
            return $this->success($result, 201);
        } catch (Exception $e) {
            $code = (strpos($e->getMessage(), '이미') !== false) ? 409 : 400;
            return $this->error($e->getMessage(), $code);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function public_posts(): array {
        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        $out = [];

        foreach ($feed as $post) {
            if (!is_array($post)) {
                continue;
            }
            if (($post['status'] ?? 'active') === 'inactive') {
                continue;
            }
            $gallery_id = (string) ($post['gallery_id'] ?? '');
            if ($gallery_id === '') {
                continue;
            }
            if ($this->is_demo_or_placeholder($post)) {
                continue;
            }
            $out[] = $this->sanitize_public_post($post);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    private function sanitize_public_post(array $post): array {
        $thumb = (string) ($post['thumbnail_url'] ?? $post['display_url'] ?? $post['thumbnail'] ?? $post['image_url'] ?? '');
        $creator = (string) ($post['creator_name'] ?? $post['creator'] ?? 'Creator');
        $caption = (string) ($post['caption'] ?? $post['title'] ?? '');

        return [
            'id'            => (string) ($post['id'] ?? ''),
            'gallery_id'    => (string) ($post['gallery_id'] ?? ''),
            'type'          => (string) ($post['type'] ?? 'image'),
            'type_label'    => $this->type_label((string) ($post['type'] ?? '')),
            'title'         => $caption,
            'caption'       => $caption,
            'thumbnail'     => esc_url_raw($thumb),
            'thumbnail_url' => esc_url_raw($thumb),
            'display_url'   => esc_url_raw($thumb),
            'image_url'     => esc_url_raw($thumb),
            'creator'       => $creator,
            'creator_name'  => $creator,
            'visibility'    => 'public',
            'status'        => 'active',
            'created_at'    => (string) ($post['created_at'] ?? ''),
            'likes'         => (int) ($post['likes'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    private function is_demo_or_placeholder(array $post): bool {
        if (!empty($post['is_demo'])) {
            return true;
        }
        $url = (string) ($post['thumbnail_url'] ?? $post['display_url'] ?? $post['thumbnail'] ?? '');
        if ($url === '') {
            return true;
        }
        return strpos($url, 'placeholder') !== false
            || strpos($url, 'placehold.co') !== false
            || strpos($url, 'official-showcase/thumbs') !== false;
    }

    private function type_label(string $type): string {
        switch ($type) {
            case 'video':
                return 'Video';
            case 'image':
                return 'Image';
            case 'music':
                return 'Music';
            case 'voice':
                return 'Voice';
            case 'avatar':
                return 'Avatar';
            case 'writing':
                return 'Writing';
            default:
                return $type !== '' ? ucfirst($type) : 'Work';
        }
    }

    private function ensure_gallery_actions(): void {
        if (class_exists('YooY_Gallery_Actions') && class_exists('YooY_Gallery_Store')) {
            return;
        }
        if (!defined('YOY_AI_STUDIO_MODULES_DIR')) {
            return;
        }
        $store = YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
        $actions = YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-actions.php';
        if (file_exists($store)) {
            require_once $store;
        }
        if (file_exists($actions)) {
            require_once $actions;
        }
    }
}
