<?php
if (!defined('ABSPATH')) exit;

final class YooY_REST_Controller {

    private YooY_Core_Engine $core;

    public function __construct(YooY_Core_Engine $core) {
        $this->core = $core;
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('yoy-ai-studio/v1', '/core/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'status'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/modules', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'modules'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/dashboard', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'dashboard'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        foreach ($this->core->registry()->all() as $module) {
            $module->register_rest_routes();
        }
    }

    public function status(): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $this->core->status(),
        ], 200);
    }

    public function modules(): WP_REST_Response {
        return new WP_REST_Response([
            'success' => true,
            'data'    => $this->core->registry()->configs(),
        ], 200);
    }

    public function dashboard(): WP_REST_Response {
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            return new WP_REST_Response(['success' => false, 'error' => 'Login required.'], 401);
        }

        $credits  = new YooY_Credits_Service();
        $jobs     = new YooY_Job_Store();

        $project_store = null;
        if (class_exists('YooY_Project_Store')) {
            $project_store = new YooY_Project_Store();
        } elseif (defined('YOY_AI_STUDIO_MODULES_DIR') && file_exists(YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php';
            $project_store = new YooY_Project_Store();
        }

        $projects = $project_store ? $project_store->list($user_id, 5) : [];
        $project_count = $project_store ? $project_store->count($user_id) : 0;
        if ($project_store) {
            $project_store->sync_asset_counts($user_id);
            $projects = $project_store->list($user_id, 5);
        }
        $project_titles = $project_store ? $project_store->title_map($user_id) : [];

        $gallery_items = [];
        if (class_exists('YooY_Gallery_Store')) {
            $store = new YooY_Gallery_Store();
            if (class_exists('YooY_Gallery_Aggregator')) {
                $aggregator = new YooY_Gallery_Aggregator($store);
                $aggregator->reconcile_jobs($user_id);
            }
            $gallery_items = array_slice($store->list($user_id, []), 0, 12);
            foreach ($gallery_items as $idx => $work) {
                $pid = (string) ($work['project_id'] ?? '');
                $gallery_items[$idx]['project_title'] = $pid !== '' ? ($project_titles[$pid] ?? '') : '';
                $gallery_items[$idx]['type_label'] = $this->work_type_label((string) ($work['type'] ?? 'image'));
            }
        }

        $recent_jobs = array_slice($jobs->all($user_id), 0, 7);

        $feed = get_option('yoy_community_feed', []);
        $feed = is_array($feed) ? $feed : [];
        $community_likes = 0;
        $user    = wp_get_current_user();
        $display = $user->display_name ?? '';
        foreach ($feed as $item) {
            $creator = $item['creator'] ?? '';
            $creator_id = (int) ($item['creator_id'] ?? 0);
            if ($creator_id === $user_id || ($display !== '' && $creator === $display)) {
                $community_likes += (int) ($item['likes'] ?? 0);
            }
        }

        $monthly = $credits->monthly_usage($user_id);
        $announcements = get_option('yoy_studio_announcements', []);
        $announcements = is_array($announcements) ? array_slice($announcements, 0, 5) : [];

        $showcase = array_slice($feed, 0, 6);

        $marketplace = get_option('yoy_marketplace_catalog', []);
        $marketplace = is_array($marketplace) ? array_slice($marketplace, 0, 6) : [];

        $community_trending = $feed;
        usort($community_trending, function ($a, $b) {
            return (int) ($b['likes'] ?? 0) <=> (int) ($a['likes'] ?? 0);
        });
        $community_trending = array_slice($community_trending, 0, 6);

        if (empty($showcase) && !empty($gallery_items)) {
            $showcase = array_values(array_filter($gallery_items, function ($item) {
                return !empty($item['public']);
            }));
        }

        $home_sections = [];
        if (class_exists('YooY_Home_Sections_Service')) {
            $home_sections = (new YooY_Home_Sections_Service())->resolve_for_home($user_id);
        } elseif (defined('YOY_AI_STUDIO_MODULES_DIR') && file_exists(YOY_AI_STUDIO_MODULES_DIR . 'admin-console/includes/class-home-sections-service.php')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'admin-console/includes/class-home-sections-service.php';
            $home_sections = (new YooY_Home_Sections_Service())->resolve_for_home($user_id);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'credits'          => $credits->snapshot($user_id),
                'monthly_usage'    => $monthly,
                'projects'         => $projects,
                'project_count'    => $project_count,
                'works'            => $gallery_items,
                'work_count'       => class_exists('YooY_Gallery_Store') ? count((new YooY_Gallery_Store())->list($user_id, [])) : 0,
                'jobs'             => $recent_jobs,
                'job_count'        => count($jobs->all($user_id)),
                'community_likes'  => $community_likes,
                'announcements'    => $announcements,
                'showcase'         => $showcase,
                'marketplace'      => $marketplace,
                'community_trending' => $community_trending,
                'home_sections'    => $home_sections,
            ],
        ], 200);
    }

    private function work_type_label(string $type): string {
        switch ($type) {
            case 'video': return 'Video';
            case 'music': return 'Music';
            case 'voice': return 'Voice';
            case 'avatar': return 'Avatar';
            case 'writing': return 'Writing';
            default: return 'Image';
        }
    }
}
