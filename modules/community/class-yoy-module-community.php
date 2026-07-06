<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Community extends YooY_Module_Base {

    public function id(): string { return 'community'; }
    public function name(): string { return 'Community'; }
    public function description(): string { return 'Public gallery, creator posts, and community feed.'; }
    public function version(): string { return '1.0.0'; }

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
    }

    public function feed(): WP_REST_Response {
        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];

        $items = array_map(function ($post) {
            return array_merge($post, [
                'type_label' => $this->type_label($post['type'] ?? ''),
            ]);
        }, $feed);

        return $this->success(['feed' => $items]);
    }

    private function type_label(string $type): string {
        return match ($type) {
            'video'   => 'Video',
            'image'   => 'Image',
            'music'   => 'Music',
            'voice'   => 'Voice',
            'avatar'  => 'Avatar',
            'writing' => 'Writing',
            default   => ucfirst($type),
        };
    }

    public function posts(): WP_REST_Response {
        return $this->feed();
    }

    public function create_post(WP_REST_Request $request): WP_REST_Response {
        $title = sanitize_text_field($request->get_param('title') ?: '');
        $type  = sanitize_text_field($request->get_param('type') ?: 'work');

        if ($title === '') {
            return $this->error('Title is required.');
        }

        $user = wp_get_current_user();
        $post = [
            'id'         => 'post_' . wp_generate_uuid4(),
            'type'       => $type,
            'title'      => $title,
            'creator'    => $user->display_name,
            'likes'      => 0,
            'created_at' => gmdate('c'),
        ];

        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        array_unshift($feed, $post);
        update_option('yoy_community_feed', array_slice($feed, 0, 200));

        return $this->success(['post' => $post], 201);
    }
}
