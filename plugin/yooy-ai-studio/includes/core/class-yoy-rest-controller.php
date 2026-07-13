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

        register_rest_route('yoy-ai-studio/v1', '/core/rest-health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'rest_health'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/system-check', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'system_check'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/system-fix', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'system_fix'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/home-public', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'home_public'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/public-works', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'public_works'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/dashboard', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'dashboard'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('yoy-ai-studio/v1', '/core/jobs/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_job'],
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

    /**
     * REST route health snapshot. Lists every registered route under the
     * yoy-ai-studio/v1 namespace and reports whether the routes required for
     * image generation are present. Used by the frontend pre-generate check
     * and by the release build verifier.
     */
    public function rest_health(): WP_REST_Response {
        $registered    = [];
        $methodsByRoute = [];

        if (function_exists('rest_get_server')) {
            $server = rest_get_server();
            if ($server) {
                foreach ($server->get_routes() as $route => $handlers) {
                    if (strpos($route, '/yoy-ai-studio/v1') !== 0) {
                        continue;
                    }
                    $registered[] = $route;
                    $allowed = [];
                    foreach ((array) $handlers as $handler) {
                        $m = isset($handler['methods']) ? $handler['methods'] : [];
                        if (is_string($m)) {
                            foreach (array_map('trim', explode(',', $m)) as $mm) {
                                if ($mm !== '') {
                                    $allowed[strtoupper($mm)] = true;
                                }
                            }
                        } elseif (is_array($m)) {
                            foreach ($m as $k => $v) {
                                if (is_string($k)) {
                                    if ($v) {
                                        $allowed[strtoupper($k)] = true;
                                    }
                                } else {
                                    $allowed[strtoupper((string) $v)] = true;
                                }
                            }
                        }
                    }
                    $methodsByRoute[$route] = array_keys($allowed);
                }
            }
        }
        $registered = array_values(array_unique($registered));

        // Verify the ACTUAL endpoints the frontend calls, including that the
        // registered HTTP method matches (a POST endpoint registered as GET-only
        // would still return rest_no_route). See required_endpoints().
        $checks  = [];
        $missing = [];
        $all_ok  = true;
        foreach (self::required_endpoints() as $chk) {
            $method    = strtoupper($chk[0]);
            $route_key = $chk[1];
            $exists    = in_array($route_key, $registered, true);
            $allowed   = isset($methodsByRoute[$route_key]) ? $methodsByRoute[$route_key] : [];
            $method_ok = $exists && in_array($method, $allowed, true);
            if (!$exists || !$method_ok) {
                $all_ok    = false;
                $missing[] = $method . ' ' . $route_key;
            }
            $checks[] = [
                'method'          => $method,
                'route'           => $route_key,
                'registered'      => $exists,
                'method_ok'       => $method_ok,
                'allowed_methods' => $allowed,
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'ok'             => $all_ok,
                'namespace'      => 'yoy-ai-studio/v1',
                'rest_url'       => esc_url_raw(rest_url('yoy-ai-studio/v1')),
                'rest_route_url' => esc_url_raw(site_url('index.php')) . '?rest_route=/yoy-ai-studio/v1',
                'registered'     => $registered,
                'checks'         => $checks,
                'missing'        => array_values($missing),
                'count'          => count($registered),
            ],
        ], 200);
    }

    public function system_check(): WP_REST_Response {
        if (!class_exists('YooY_System_Diagnostics')) {
            return new WP_REST_Response(['success' => false, 'error' => 'Diagnostics unavailable.'], 500);
        }
        $user_id = get_current_user_id();
        $admin = current_user_can('manage_options');
        return new WP_REST_Response([
            'success' => true,
            'data'    => YooY_System_Diagnostics::run($user_id, $admin),
        ], 200);
    }

    public function system_fix(WP_REST_Request $request): WP_REST_Response {
        if (!class_exists('YooY_System_Diagnostics')) {
            return new WP_REST_Response(['success' => false, 'error' => 'Diagnostics unavailable.'], 500);
        }
        $action = sanitize_text_field((string) $request->get_param('action'));
        $result = YooY_System_Diagnostics::fix($action);
        return new WP_REST_Response([
            'success' => !empty($result['success']),
            'data'    => $result,
        ], !empty($result['success']) ? 200 : 400);
    }

    /**
     * The exact endpoints (method + registered route key) the frontend calls.
     * The route strings are match keys as returned by WP_REST_Server::get_routes().
     *
     * @return array<int, array{0:string,1:string}>
     */
    public static function required_endpoints(): array {
        return [
            ['GET',  '/yoy-ai-studio/v1/core/rest-health'],
            ['GET',  '/yoy-ai-studio/v1/credits/overview'],
            ['GET',  '/yoy-ai-studio/v1/image-studio/provider-health'],
            ['GET',  '/yoy-ai-studio/v1/image-studio/config'],
            ['GET',  '/yoy-ai-studio/v1/image-studio/settings'],
            ['GET',  '/yoy-ai-studio/v1/image-studio/credits'],
            ['POST', '/yoy-ai-studio/v1/image-studio/credits/estimate'],
            ['POST', '/yoy-ai-studio/v1/image-studio/generate'],
            ['POST', '/yoy-ai-studio/v1/image-studio/jobs/(?P<id>[a-zA-Z0-9_-]+)/poll'],
            ['GET',  '/yoy-ai-studio/v1/image-studio/gallery'],
            ['GET',  '/yoy-ai-studio/v1/image-studio/history'],
            ['GET',  '/yoy-ai-studio/v1/projects'],
            ['POST', '/yoy-ai-studio/v1/projects'],
            ['GET',  '/yoy-ai-studio/v1/core/dashboard'],
        ];
    }

    public function dashboard(): WP_REST_Response {
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            return new WP_REST_Response(['success' => false, 'error' => 'Login required.'], 401);
        }

        $feed_service = new YooY_Public_Works_Feed();
        $feed_service->ensure_seeds();

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

        $recent_jobs = [];
        foreach (array_slice($jobs->all($user_id), 0, 7) as $job) {
            $recent_jobs[] = $this->enrich_activity_item($job, $user_id);
        }

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

        $home_sections = [];
        $home_feed = null;
        if (class_exists('YooY_Home_Sections_Service')) {
            $home_feed = new YooY_Home_Sections_Service();
            $home_sections = $home_feed->resolve_for_home($user_id);
        } elseif (defined('YOY_AI_STUDIO_MODULES_DIR') && file_exists(YOY_AI_STUDIO_MODULES_DIR . 'admin-console/includes/class-home-sections-service.php')) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'admin-console/includes/class-home-sections-service.php';
            $home_feed = new YooY_Home_Sections_Service();
            $home_sections = $home_feed->resolve_for_home($user_id);
        }

        $gallery_items = array_slice(
            $feed_service->fill_mixed($user_id, 12, ['user', 'community', 'marketplace', 'official', 'demo']),
            0,
            12
        );
        $showcase = array_slice(
            $feed_service->fill_mixed($user_id, 6, ['official', 'demo', 'community', 'marketplace']),
            0,
            6
        );
        $marketplace = array_slice(
            $feed_service->fill_mixed($user_id, 6, ['marketplace', 'community', 'official', 'demo']),
            0,
            6
        );
        $community_trending = array_slice(
            $feed_service->fill_mixed($user_id, 6, ['community', 'marketplace', 'official', 'demo']),
            0,
            6
        );

        if (empty($showcase) && !empty($gallery_items)) {
            $showcase = array_values(array_filter($gallery_items, function ($item) {
                return !empty($item['public']) || !empty($item['is_platform']);
            }));
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

    public function home_public(): WP_REST_Response {
        $user_id = get_current_user_id();
        $feed = new YooY_Public_Works_Feed();
        $payload = $feed->home_payload($user_id);

        $data = [
            'guest'              => $user_id <= 0,
            'works'              => $payload['works'] ?? [],
            'work_count'         => (int) ($payload['work_count'] ?? 0),
            'showcase'           => $payload['showcase'] ?? [],
            'marketplace'        => $payload['marketplace'] ?? [],
            'community_trending' => $payload['community_trending'] ?? [],
            'home_sections'      => $payload['home_sections'] ?? [],
            'showcase_seed_low'  => !empty($payload['showcase_seed_low']),
        ];

        if ($user_id > 0) {
            $credits = new YooY_Credits_Service();
            $data['credits'] = $credits->snapshot($user_id);
            $data['monthly_usage'] = $credits->monthly_usage($user_id);
        }

        return new WP_REST_Response(['success' => true, 'data' => $data], 200);
    }

    public function public_works(WP_REST_Request $request): WP_REST_Response {
        $limit = max(1, min(100, (int) $request->get_param('limit')));
        $source = sanitize_text_field((string) $request->get_param('source'));
        $feed = new YooY_Public_Works_Feed();
        $items = $feed->list_public($limit, $source !== '' ? $source : null);

        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'items'  => $items,
                'count'  => count($items),
                'source' => $source !== '' ? $source : 'mixed',
            ],
        ], 200);
    }

    public function delete_job(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            return new WP_REST_Response(['success' => false, 'error' => 'Login required.'], 401);
        }

        $job_id = sanitize_text_field((string) $request['id']);
        if ($job_id === '') {
            return new WP_REST_Response(['success' => false, 'error' => 'Job id required.'], 400);
        }

        $store = new YooY_Job_Store();
        if (!$store->remove($user_id, $job_id)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Job not found.'], 404);
        }

        return new WP_REST_Response(['success' => true, 'data' => ['job_id' => $job_id]], 200);
    }

    private function enrich_activity_item(array $job, int $user_id): array {
        $type = sanitize_text_field((string) ($job['type'] ?? 'image'));
        $studio = sanitize_text_field((string) ($job['studio'] ?? ''));
        $status = YooY_Job_Status::normalize((string) ($job['status'] ?? ''));
        $route = $this->studio_route_for($type, $studio);
        $meta = is_array($job['meta'] ?? null) ? $job['meta'] : [];
        $asset_debug = is_array($meta['openai_asset_debug'] ?? null) ? $meta['openai_asset_debug'] : [];

        $work_id = (string) ($asset_debug['gallery_item_id'] ?? '');
        if ($work_id === '' && !empty($job['job_id'])) {
            $work_id = (string) $job['job_id'] . '_0';
        }

        $error_message = (string) ($job['error'] ?? '');
        $error_code = $this->detect_activity_error_code($job, $error_message);
        $prompt = (string) ($job['user_prompt'] ?? $job['prompt'] ?? '');

        return array_merge($job, [
            'id'            => (string) ($job['job_id'] ?? $job['id'] ?? ''),
            'job_id'        => (string) ($job['job_id'] ?? ''),
            'work_id'       => $work_id,
            'type'          => $type,
            'status'        => $status,
            'title'         => $this->activity_title($job, $type),
            'prompt'        => $prompt,
            'provider'      => (string) ($job['provider_used'] ?? $job['provider'] ?? ''),
            'model'         => (string) ($job['model'] ?? ''),
            'error_code'    => $error_code,
            'error_message' => $error_message,
            'raw_error'     => is_array($job['raw'] ?? null) ? wp_json_encode($job['raw']) : (string) ($job['raw'] ?? ''),
            'created_at'    => (string) ($job['created_at'] ?? ''),
            'updated_at'    => (string) ($job['updated_at'] ?? ''),
            'target_route'  => $route,
            'target_id'     => $status === YooY_Job_Status::FAILED
                ? (string) ($job['job_id'] ?? '')
                : $work_id,
            'studio'        => $studio,
        ]);
    }

    private function activity_title(array $job, string $type): string {
        $prompt = (string) ($job['user_prompt'] ?? $job['prompt'] ?? '');
        if ($prompt !== '') {
            return mb_strlen($prompt) > 48 ? mb_substr($prompt, 0, 48) . '…' : $prompt;
        }
        return $this->work_type_label($type) . ' Generation';
    }

    private function studio_route_for(string $type, string $studio): string {
        if ($studio !== '') {
            $map = [
                'image-studio'  => 'image',
                'video-studio'  => 'video',
                'music-studio'  => 'music',
                'voice-studio'  => 'voice',
                'avatar-studio' => 'avatar',
                'writing-studio'=> 'writing',
            ];
            if (isset($map[$studio])) {
                return $map[$studio];
            }
        }
        switch ($type) {
            case 'video': return 'video';
            case 'music': return 'music';
            case 'voice': return 'voice';
            case 'avatar': return 'avatar';
            case 'writing': return 'writing';
            default: return 'image';
        }
    }

    private function detect_activity_error_code(array $job, string $error_message): string {
        $meta = is_array($job['meta'] ?? null) ? $job['meta'] : [];
        $resolution = is_array($meta['provider_resolution'] ?? null) ? $meta['provider_resolution'] : [];
        if (!empty($resolution['error_code'])) {
            return sanitize_text_field((string) $resolution['error_code']);
        }

        $hay = strtolower($error_message);
        if (strpos($hay, 'insufficient credit') !== false || strpos($hay, 'billing') !== false || strpos($hay, 'payment required') !== false) {
            return 'insufficient_provider_credit';
        }
        if (strpos($hay, 'provider_not_tested') !== false || strpos($hay, 'test connection') !== false || strpos($hay, 'must pass test') !== false) {
            return 'provider_not_tested';
        }
        if (strpos($hay, 'api key') !== false || strpos($hay, 'not configured') !== false) {
            return 'provider_not_configured';
        }
        if (strpos($hay, 'size') !== false && (strpos($hay, 'mismatch') !== false || strpos($hay, 'unsupported') !== false)) {
            return 'invalid_size';
        }
        if (strpos($hay, 'no output') !== false || strpos($hay, 'no output asset') !== false) {
            return 'no_output_asset';
        }
        if (strpos($hay, 'timed out') !== false || strpos($hay, 'timeout') !== false) {
            return 'poll_timeout';
        }
        if (($job['status'] ?? '') === YooY_Job_Status::FAILED) {
            return 'generation_failed';
        }
        return '';
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
