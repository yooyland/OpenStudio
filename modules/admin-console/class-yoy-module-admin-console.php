<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-admin-providers.php';
require_once __DIR__ . '/includes/class-home-sections-service.php';

final class YooY_Module_Admin_Console extends YooY_Module_Base {

    private YooY_Credits_Service $credits;
    private YooY_Job_Store $jobs;
    private YooY_Home_Sections_Service $home_sections;

    public function id(): string { return 'admin-console'; }
    public function name(): string { return 'Admin Console'; }
    public function description(): string { return 'Administrator operational console for providers, credits, users, and system.'; }
    public function version(): string { return '1.1.0'; }

    public function init(YooY_Core_Engine $core): void {
        parent::init($core);
        $this->credits = new YooY_Credits_Service();
        $this->jobs    = new YooY_Job_Store();
        $this->home_sections = new YooY_Home_Sections_Service();
    }

    public function register_rest_routes(): void {
        $admin = [$this, 'require_admin'];

        $this->register_route('/dashboard', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'dashboard'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'providers'], 'permission_callback' => $admin],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'save_provider'], 'permission_callback' => $admin],
        ]);
        $this->register_route('/providers/test', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'test_provider'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers/summary', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'providers_summary'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers/(?P<id>[a-zA-Z0-9_-]+)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'get_provider'], 'permission_callback' => $admin],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'save_provider'], 'permission_callback' => $admin],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'save_provider'], 'permission_callback' => $admin],
        ]);
        $this->register_route('/providers/(?P<id>[a-zA-Z0-9_-]+)/test', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'test_provider'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers/(?P<id>[a-zA-Z0-9_-]+)/logs', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'provider_logs'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers/(?P<id>[a-zA-Z0-9_-]+)/monitoring', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'provider_monitoring'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers/(?P<id>[a-zA-Z0-9_-]+)/disable', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'disable_provider'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers/(?P<id>[a-zA-Z0-9_-]+)/enable', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'enable_provider'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/providers/(?P<id>[a-zA-Z0-9_-]+)/studio-default', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'set_provider_studio_default'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/users', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'users'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/users/(?P<id>\d+)/credits', [
            'methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'adjust_user_credits'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/users/(?P<id>\d+)/plan', [
            'methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'set_user_plan'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/credits/packages', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'credit_packages'], 'permission_callback' => $admin],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'save_credit_packages'], 'permission_callback' => $admin],
        ]);
        $this->register_route('/credits/wc-products', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'wc_product_search'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/credits/transactions', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'credit_transactions'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/settings', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'system_settings'], 'permission_callback' => $admin],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'save_system_settings'], 'permission_callback' => $admin],
        ]);
        $this->register_route('/logs', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'logs'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/system', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'system_info'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/backup', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'backup_info'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/projects', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'admin_projects'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/gallery', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'admin_gallery'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/community', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'admin_community'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/marketplace', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'admin_marketplace'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/prompts', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'admin_prompts'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/home-sections', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'home_sections_list'],
                'permission_callback' => $admin,
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'home_sections_create'],
                'permission_callback' => $admin,
            ],
        ]);
        $this->register_route('/home-sections/reorder', [
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'home_sections_reorder'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/home-sections/works-search', [
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'home_sections_works_search'], 'permission_callback' => $admin,
        ]);
        $this->register_route('/home-sections/(?P<id>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'home_sections_update'],
                'permission_callback' => $admin,
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'home_sections_delete'],
                'permission_callback' => $admin,
            ],
        ]);
    }

    public function require_admin() {
        return current_user_can('manage_options');
    }

    public function dashboard(): WP_REST_Response {
        return $this->success([
            'users'     => count_users()['total_users'],
            'providers' => count(array_filter(YooY_Admin_Providers::list(), function ($p) {
                return ($p['status'] ?? '') === 'active';
            })),
            'jobs'      => $this->count_all_jobs(),
            'failed'    => $this->count_all_jobs(YooY_Job_Status::FAILED),
            'modules'   => $this->core->registry()->count(),
        ]);
    }

    public function providers(): WP_REST_Response {
        return $this->success([
            'providers' => YooY_Admin_Providers::list(),
            'summary'   => YooY_Admin_Providers::summary(),
        ]);
    }

    public function providers_summary(): WP_REST_Response {
        return $this->success(YooY_Admin_Providers::summary());
    }

    public function provider_logs(WP_REST_Request $request): WP_REST_Response {
        $id = sanitize_text_field($request->get_param('id'));
        return $this->success(YooY_Admin_Providers::provider_logs($id));
    }

    public function provider_monitoring(WP_REST_Request $request): WP_REST_Response {
        $id = sanitize_text_field($request->get_param('id'));
        return $this->success(YooY_Admin_Providers::provider_monitoring($id));
    }

    public function disable_provider(WP_REST_Request $request): WP_REST_Response {
        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success(['provider' => YooY_Admin_Providers::disable($id)]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function enable_provider(WP_REST_Request $request): WP_REST_Response {
        try {
            $id = sanitize_text_field($request->get_param('id'));
            return $this->success(['provider' => YooY_Admin_Providers::enable($id)]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function get_provider(WP_REST_Request $request): WP_REST_Response {
        try {
            $id = sanitize_text_field($request->get_param('id'));
            $provider = YooY_Admin_Providers::find($id);
            if (!$provider) {
                return $this->error('Provider not found.', 404);
            }
            return $this->success(['provider' => $provider]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function save_provider(WP_REST_Request $request): WP_REST_Response {
        try {
            $payload = $request->get_json_params() ?: [];
            $id = sanitize_text_field($request->get_param('id') ?: ($payload['id'] ?? ''));
            if ($id === '') {
                return $this->error('Provider id is required.');
            }
            unset($payload['id']);
            return $this->success(YooY_Admin_Providers::save($id, $payload));
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function test_provider(WP_REST_Request $request): WP_REST_Response {
        try {
            $payload = $request->get_json_params() ?: [];
            $id = sanitize_text_field($request->get_param('id') ?: ($payload['id'] ?? ''));
            if ($id === '') {
                return $this->error('Provider id is required.');
            }
            return $this->success(YooY_Admin_Providers::test($id));
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function set_provider_studio_default(WP_REST_Request $request): WP_REST_Response {
        try {
            $id     = sanitize_text_field($request->get_param('id'));
            $body   = $request->get_json_params() ?: [];
            $studio = sanitize_text_field($body['studio'] ?? '');
            $defaults = YooY_Admin_Providers::set_studio_default($id, $studio);
            return $this->success([
                'studio'            => $studio,
                'provider'          => $id,
                'default_providers' => $defaults,
                'providers'         => YooY_Admin_Providers::list(),
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function users(WP_REST_Request $request): WP_REST_Response {
        $search = sanitize_text_field($request->get_param('search') ?: '');
        $args = ['number' => 50, 'orderby' => 'registered', 'order' => 'DESC'];
        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $rows = [];
        foreach (get_users($args) as $user) {
            $uid = (int) $user->ID;
            $jobs = $this->jobs->all($uid);
            $last = !empty($jobs[0]['updated_at']) ? $jobs[0]['updated_at'] : $user->user_registered;

            $rows[] = [
                'id'            => $uid,
                'login'         => $user->user_login,
                'name'          => $user->display_name,
                'email'         => $user->user_email,
                'role'          => implode(', ', $user->roles),
                'credits'       => $this->credits->balance($uid),
                'unlimited'     => $this->credits->is_unlimited($uid),
                'plan'          => $this->credits->get_user_plan_id($uid),
                'plan_name'     => $this->credits->get_user_plan($uid)['name'] ?? 'Free',
                'status'        => user_can($uid, 'read') ? 'active' : 'inactive',
                'last_activity' => $last,
            ];
        }

        return $this->success(['users' => $rows]);
    }

    public function adjust_user_credits(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid    = (int) $request->get_param('id');
            $body   = $request->get_json_params() ?: [];
            $delta  = (int) ($body['delta'] ?? 0);
            $label  = sanitize_text_field($body['label'] ?? 'Admin adjustment');

            if ($uid <= 0 || $delta === 0) {
                return $this->error('Invalid user or delta.');
            }

            $snapshot = $this->credits->adjust_balance($uid, $delta, $label, 'admin-console', [
                'studio' => sanitize_text_field($body['studio'] ?? 'admin'),
                'status' => 'completed',
            ]);
            YooY_System_Log::write('info', 'Credits adjusted', ['user_id' => $uid, 'delta' => $delta]);

            return $this->success(['credits' => $snapshot]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function set_user_plan(WP_REST_Request $request): WP_REST_Response {
        try {
            $uid = (int) $request->get_param('id');
            $body = $request->get_json_params() ?: [];
            $plan = sanitize_text_field($body['plan'] ?? $body['plan_id'] ?? '');
            $grant = !empty($body['grant_credits']);

            if ($uid <= 0 || $plan === '') {
                return $this->error('Invalid user or plan.');
            }

            $overview = $this->credits->set_user_plan($uid, $plan, $grant);
            YooY_System_Log::write('info', 'User plan updated', ['user_id' => $uid, 'plan' => $plan]);

            return $this->success(['account' => $overview]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function credit_packages(): WP_REST_Response {
        $plans = class_exists('YooY_Credits_Plans') ? YooY_Credits_Plans::merged() : [];
        if (empty($plans)) {
            $stored = get_option('yoy_credit_packages', []);
            if (is_array($stored) && !empty($stored)) {
                $plans = $stored;
            }
        }
        return $this->success($this->membership_mapping_payload($plans));
    }

    public function save_credit_packages(WP_REST_Request $request): WP_REST_Response {
        $body  = $request->get_json_params();
        $plans = is_array($body) ? ($body['plans'] ?? null) : null;
        if (!is_array($plans)) {
            return $this->error('Invalid plans payload.');
        }

        $clean = [];
        foreach ($plans as $plan) {
            if (!is_array($plan) || empty($plan['id'])) {
                continue;
            }
            $clean[] = [
                'id'         => sanitize_text_field($plan['id']),
                'name'       => sanitize_text_field($plan['name'] ?? $plan['id']),
                'credits'    => (int) ($plan['credits'] ?? 0),
                'price_krw'  => (int) ($plan['price_krw'] ?? 0),
                'yearly_price_krw' => (int) ($plan['yearly_price_krw'] ?? 0),
                'product_id' => (int) ($plan['product_id'] ?? 0),
                'yearly_product_id' => (int) ($plan['yearly_product_id'] ?? 0),
            ];
        }

        update_option('yoy_credit_packages', $clean, false);
        $merged = class_exists('YooY_Credits_Plans') ? YooY_Credits_Plans::merged() : $clean;
        return $this->success($this->membership_mapping_payload($merged));
    }

    public function wc_product_search(WP_REST_Request $request): WP_REST_Response {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_products')) {
            return $this->success([
                'products'           => [],
                'woocommerce_active' => false,
            ]);
        }

        $query = sanitize_text_field($request->get_param('q') ?? '');
        $args  = [
            'status' => 'publish',
            'limit'  => 30,
            'return' => 'objects',
        ];
        if ($query !== '') {
            $args['s'] = $query;
        }

        $products = [];
        foreach (wc_get_products($args) as $product) {
            if (!is_object($product) || !method_exists($product, 'get_id')) {
                continue;
            }
            $products[] = [
                'id'    => (int) $product->get_id(),
                'name'  => (string) $product->get_name(),
                'sku'   => (string) $product->get_sku(),
                'price' => (string) $product->get_price(),
                'type'  => (string) $product->get_type(),
            ];
        }

        return $this->success([
            'products'           => $products,
            'woocommerce_active' => true,
            'query'              => $query,
        ]);
    }

    private function membership_mapping_payload(array $plans): array {
        $billing = class_exists('YooY_Credits_Plans') ? YooY_Credits_Plans::billing_config() : [];
        $mapped  = 0;
        $paid    = 0;
        foreach ($plans as $plan) {
            if (($plan['id'] ?? '') === 'free') {
                continue;
            }
            $paid++;
            if ((int) ($plan['product_id'] ?? 0) > 0 || (int) ($plan['yearly_product_id'] ?? 0) > 0) {
                $mapped++;
            }
        }

        return [
            'plans'   => $plans,
            'billing' => $billing,
            'mapping' => [
                'woocommerce_active' => !empty($billing['woocommerce_active']),
                'payment_ready'      => !empty($billing['payment_ready']),
                'paid_plans'         => $paid,
                'mapped_plans'       => $mapped,
                'needs_mapping'      => !empty($billing['woocommerce_active']) && empty($billing['payment_ready']),
            ],
            'wc_admin' => [
                'new_product_url' => admin_url('post-new.php?post_type=product'),
                'products_url'    => admin_url('edit.php?post_type=product'),
            ],
        ];
    }

    public function credit_transactions(WP_REST_Request $request): WP_REST_Response {
        $user_id = (int) ($request->get_param('user_id') ?: 0);
        $rows = [];

        if ($user_id > 0) {
            $rows = $this->credits->ledger($user_id);
        } else {
            foreach (get_users(['number' => 30, 'orderby' => 'ID', 'order' => 'DESC']) as $user) {
                foreach (array_slice($this->credits->ledger((int) $user->ID), 0, 5) as $tx) {
                    $tx['user_id'] = (int) $user->ID;
                    $tx['user']    = $user->display_name;
                    $rows[] = $tx;
                }
            }
            usort($rows, function ($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
            $rows = array_slice($rows, 0, 100);
        }

        return $this->success(['transactions' => $rows]);
    }

    public function system_settings(): WP_REST_Response {
        return $this->success([
            'default_providers' => get_option('yoy_studio_default_providers', [
                'video' => 'auto', 'image' => 'auto', 'music' => 'auto',
                'voice' => 'auto', 'avatar' => 'auto', 'writing' => 'auto',
            ]),
            'credit_costs' => get_option('yoy_studio_credit_costs', [
                'video' => 50, 'image' => 10, 'music' => 20, 'voice' => 15, 'avatar' => 30, 'writing' => 5,
            ]),
            'korean_context_engine' => [
                'enabled' => false,
                'status'  => 'Coming in v12',
                'note'    => 'Production-ready setting row reserved for Korean Context Engine rollout.',
            ],
            'flags' => [
                'credits_enabled'     => (bool) get_option('yoy_credits_enabled', true),
                'marketplace_enabled' => (bool) get_option('yoy_marketplace_enabled', true),
                'community_enabled'   => (bool) get_option('yoy_community_enabled', true),
            ],
        ]);
    }

    public function save_system_settings(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params() ?: [];

        if (isset($body['default_providers']) && is_array($body['default_providers'])) {
            update_option('yoy_studio_default_providers', array_map('sanitize_text_field', $body['default_providers']), false);
        }
        if (isset($body['credit_costs']) && is_array($body['credit_costs'])) {
            $costs = [];
            foreach ($body['credit_costs'] as $k => $v) {
                $costs[sanitize_text_field($k)] = (int) $v;
            }
            update_option('yoy_studio_credit_costs', $costs, false);
        }
        if (isset($body['flags']) && is_array($body['flags'])) {
            if (array_key_exists('credits_enabled', $body['flags'])) {
                update_option('yoy_credits_enabled', (bool) $body['flags']['credits_enabled'], false);
            }
            if (array_key_exists('marketplace_enabled', $body['flags'])) {
                update_option('yoy_marketplace_enabled', (bool) $body['flags']['marketplace_enabled'], false);
            }
            if (array_key_exists('community_enabled', $body['flags'])) {
                update_option('yoy_community_enabled', (bool) $body['flags']['community_enabled'], false);
            }
        }

        return $this->system_settings();
    }

    public function logs(WP_REST_Request $request): WP_REST_Response {
        $provider_id = sanitize_text_field($request->get_param('provider_id') ?: '');
        if ($provider_id !== '') {
            return $this->success(YooY_Admin_Providers::provider_logs($provider_id));
        }

        $failed_jobs = [];
        foreach (get_users(['number' => 50, 'orderby' => 'ID', 'order' => 'DESC']) as $user) {
            foreach ($this->jobs->list((int) $user->ID, ['status' => YooY_Job_Status::FAILED]) as $job) {
                $job['user_id'] = (int) $user->ID;
                $failed_jobs[] = $job;
            }
        }

        return $this->success([
            'recent_jobs'  => $this->recent_jobs(30),
            'failed_jobs'  => array_slice($failed_jobs, 0, 30),
            'system_logs'  => YooY_System_Log::recent(50),
            'rest_errors'  => YooY_System_Log::recent(30, 'error'),
            'generation_perf' => $this->generation_perf_samples(12),
        ]);
    }

    public function system_info(): WP_REST_Response {
        global $wp_version;
        return $this->success([
            'wordpress'   => $wp_version,
            'php'         => PHP_VERSION,
            'plugin'      => YOY_AI_STUDIO_VERSION,
            'modules'     => $this->core->registry()->ids(),
            'rest_base'   => rest_url('yoy-ai-studio/v1'),
            'providers'   => is_dir(YOY_AI_STUDIO_PROVIDERS_DIR),
            'memory_limit'=> ini_get('memory_limit'),
            'max_execution_time' => (int) ini_get('max_execution_time'),
        ]);
    }

    public function backup_info(): WP_REST_Response {
        return $this->success([
            'status'  => 'manual',
            'message' => 'Use WordPress export or hosting backup tools. Automated backup UI ships in a future release.',
            'paths'   => ['plugin' => YOY_AI_STUDIO_DIR, 'modules' => YOY_AI_STUDIO_MODULES_DIR],
        ]);
    }

    public function admin_projects(): WP_REST_Response {
        $rows = [];
        foreach (get_users(['number' => 100, 'orderby' => 'ID', 'order' => 'DESC']) as $user) {
            $projects = get_user_meta((int) $user->ID, 'yoy_projects', true);
            if (!is_array($projects)) {
                continue;
            }
            foreach ($projects as $project) {
                $project['user_id'] = (int) $user->ID;
                $project['user']    = $user->display_name;
                $rows[] = $project;
            }
        }
        usort($rows, function ($a, $b) {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });
        return $this->success(['projects' => array_slice($rows, 0, 100)]);
    }

    public function admin_gallery(): WP_REST_Response {
        $rows = [];
        if (class_exists('YooY_Gallery_Store')) {
            $store = new YooY_Gallery_Store();
            foreach (get_users(['number' => 100, 'orderby' => 'ID', 'order' => 'DESC']) as $user) {
                foreach ($store->list((int) $user->ID, []) as $item) {
                    $item['user_id'] = (int) $user->ID;
                    $item['user']    = $user->display_name;
                    $rows[] = $item;
                }
            }
        }
        usort($rows, function ($a, $b) {
            return strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? '');
        });
        return $this->success(['items' => array_slice($rows, 0, 100)]);
    }

    public function admin_community(): WP_REST_Response {
        $feed = get_option('yoy_community_feed', []);
        return $this->success(['feed' => is_array($feed) ? $feed : []]);
    }

    public function admin_marketplace(): WP_REST_Response {
        $items = get_option('yoy_marketplace_items', []);
        return $this->success(['items' => is_array($items) ? $items : []]);
    }

    public function admin_prompts(): WP_REST_Response {
        $official = get_option('yoy_prompt_library_official', []);
        $user_prompts = [];
        foreach (get_users(['number' => 50, 'fields' => ['ID', 'display_name']]) as $user) {
            $saved = get_user_meta((int) $user->ID, 'yoy_saved_prompts', true);
            if (!is_array($saved)) {
                continue;
            }
            foreach ($saved as $prompt) {
                $prompt['user_id'] = (int) $user->ID;
                $prompt['user']    = $user->display_name;
                $user_prompts[] = $prompt;
            }
        }
        return $this->success([
            'official' => is_array($official) ? $official : [],
            'user'     => array_slice($user_prompts, 0, 100),
        ]);
    }

    private function count_all_jobs(string $status = ''): int {
        $count = 0;
        foreach (get_users(['number' => 100, 'fields' => ['ID']]) as $user) {
            $filters = $status !== '' ? ['status' => $status] : [];
            $count += count($this->jobs->list((int) $user->ID, $filters));
        }
        return $count;
    }

    private function recent_jobs(int $limit): array {
        $rows = [];
        foreach (get_users(['number' => 50, 'orderby' => 'ID', 'order' => 'DESC']) as $user) {
            foreach (array_slice($this->jobs->all((int) $user->ID), 0, 10) as $job) {
                $job['user_id'] = (int) $user->ID;
                $rows[] = $job;
            }
        }
        usort($rows, function ($a, $b) {
            return strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? '');
        });
        return array_slice($rows, 0, $limit);
    }

    private function generation_perf_samples(int $limit): array {
        $samples = [];
        foreach ($this->recent_jobs(40) as $job) {
            if (($job['studio'] ?? '') !== 'image-studio' && ($job['type'] ?? '') !== 'image') {
                continue;
            }
            $meta = is_array($job['meta'] ?? null) ? $job['meta'] : [];
            $perf = is_array($meta['generation_perf'] ?? null) ? $meta['generation_perf'] : [];
            if (empty($perf['total_generation_ms'])) {
                continue;
            }
            $samples[] = [
                'job_id'              => (string) ($job['job_id'] ?? ''),
                'provider'            => (string) ($job['provider_used'] ?? $job['provider'] ?? ''),
                'model'               => (string) ($job['model'] ?? ''),
                'status'              => (string) ($job['status'] ?? ''),
                'created_at'          => (string) ($job['created_at'] ?? ''),
                'provider_resolve_ms' => (int) ($perf['provider_resolve_ms'] ?? 0),
                'prompt_optimize_ms'  => (int) ($perf['prompt_optimize_ms'] ?? 0),
                'api_request_ms'      => (int) ($perf['api_request_ms'] ?? 0),
                'image_save_ms'       => (int) ($perf['image_save_ms'] ?? 0),
                'gallery_save_ms'     => (int) ($perf['gallery_save_ms'] ?? 0),
                'total_generation_ms' => (int) ($perf['total_generation_ms'] ?? 0),
            ];
            if (count($samples) >= $limit) {
                break;
            }
        }
        return $samples;
    }

    public function home_sections_list(): WP_REST_Response {
        return $this->success(['sections' => $this->home_sections->list_all()]);
    }

    public function home_sections_create(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];
        $title = sanitize_text_field($body['title'] ?? '');
        if ($title === '') {
            return $this->error('Section title is required.', 400);
        }
        $section = $this->home_sections->create($body);
        return $this->success(['section' => $section], 201);
    }

    public function home_sections_update(WP_REST_Request $request): WP_REST_Response {
        $id = sanitize_text_field($request->get_param('id'));
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];
        $section = $this->home_sections->update($id, $body);
        if (!$section) {
            return $this->error('Section not found.', 404);
        }
        return $this->success(['section' => $section]);
    }

    public function home_sections_delete(WP_REST_Request $request): WP_REST_Response {
        $id = sanitize_text_field($request->get_param('id'));
        if (!$this->home_sections->delete($id)) {
            return $this->error('Section not found.', 404);
        }
        return $this->success(['deleted' => true]);
    }

    public function home_sections_reorder(WP_REST_Request $request): WP_REST_Response {
        $body = $request->get_json_params();
        $body = is_array($body) ? $body : [];
        $ordered = is_array($body['ordered_ids'] ?? null) ? $body['ordered_ids'] : [];
        return $this->success(['sections' => $this->home_sections->reorder($ordered)]);
    }

    public function home_sections_works_search(WP_REST_Request $request): WP_REST_Response {
        $q = sanitize_text_field($request->get_param('q') ?? '');
        $limit = max(1, min(50, (int) ($request->get_param('limit') ?? 20)));
        return $this->success([
            'works' => $this->home_sections->search_works($q, $limit),
        ]);
    }
}
