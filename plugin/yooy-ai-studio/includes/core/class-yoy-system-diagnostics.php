<?php
if (!defined('ABSPATH')) exit;

/**
 * Self-diagnosis engine. Runs real, end-to-end health checks across every
 * subsystem the Creator OS depends on (REST wiring, provider availability,
 * credits, storage, permissions, PHP/upload capability) and returns a
 * structured report used by the Image Studio System Check panel, the
 * pre-generate gate, the always-visible status widget and the admin
 * Operations Center "System Health" dashboard.
 *
 * Status vocabulary: 'ok' (green), 'warn' (yellow), 'error' (red).
 */
final class YooY_System_Diagnostics {

    /** Check ids required before a generation may start. */
    const ESSENTIAL = ['rest', 'provider', 'credits', 'gallery', 'image_save'];

    /**
     * Run the full diagnostic sweep.
     *
     * @param int  $user_id Current user id (0 = guest).
     * @param bool $admin   Include admin-only system info.
     * @return array
     */
    public static function run($user_id = 0, $admin = false) {
        $user_id = (int) $user_id;
        $checks = [
            self::check_rest(),
            self::check_openai(),
            self::check_provider($user_id),
            self::check_credits($user_id),
            self::check_gallery($user_id),
            self::check_projects($user_id),
            self::check_upload(),
            self::check_permission($user_id),
            self::check_php_upload(),
            self::check_image_save(),
        ];

        $counts = ['ok' => 0, 'warn' => 0, 'error' => 0];
        foreach ($checks as $c) {
            $status = isset($c['status']) ? $c['status'] : 'error';
            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
        }

        $overall = 'ok';
        if ($counts['error'] > 0) {
            $overall = 'error';
        } elseif ($counts['warn'] > 0) {
            $overall = 'warn';
        }

        $essential_ok = true;
        foreach ($checks as $c) {
            if (in_array($c['id'], self::ESSENTIAL, true) && $c['status'] === 'error') {
                $essential_ok = false;
                break;
            }
        }

        $report = [
            'overall'       => $overall,
            'ok'            => ($overall !== 'error'),
            'essential_ok'  => $essential_ok,
            'summary'       => $counts,
            'checks'        => $checks,
            'generated_at'  => gmdate('c'),
            'site'          => home_url('/'),
            'plugin_version'=> defined('YOY_AI_STUDIO_VERSION') ? YOY_AI_STUDIO_VERSION : '',
            'permalink'     => get_option('permalink_structure') ? 'pretty' : 'plain',
        ];

        if ($admin) {
            $report['system_info'] = self::system_info();
        }

        return $report;
    }

    private static function make($id, $label, $status, $message, $detail = [], $fix_action = '') {
        return [
            'id'         => $id,
            'label'      => $label,
            'status'     => $status,
            'message'    => $message,
            'detail'     => $detail,
            'essential'  => in_array($id, self::ESSENTIAL, true),
            'fixable'    => $fix_action !== '',
            'fix_action' => $fix_action,
        ];
    }

    // ---- ① REST API -------------------------------------------------------
    private static function check_rest() {
        $registered = [];
        $methods_by_route = [];
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
                                    if ($v) { $allowed[strtoupper($k)] = true; }
                                } else {
                                    $allowed[strtoupper((string) $v)] = true;
                                }
                            }
                        }
                    }
                    $methods_by_route[$route] = array_keys($allowed);
                }
            }
        }

        $required = class_exists('YooY_REST_Controller') && method_exists('YooY_REST_Controller', 'required_endpoints')
            ? YooY_REST_Controller::required_endpoints()
            : [];

        $missing = [];
        foreach ($required as $chk) {
            $method = strtoupper($chk[0]);
            $route  = $chk[1];
            $exists = in_array($route, $registered, true);
            $ok     = $exists && in_array($method, isset($methods_by_route[$route]) ? $methods_by_route[$route] : [], true);
            if (!$ok) {
                $missing[] = $method . ' ' . $route;
            }
        }

        $detail = [
            'registered_count' => count($registered),
            'missing'          => $missing,
            'permalink'        => get_option('permalink_structure') ? 'pretty' : 'plain',
            'rest_url'         => esc_url_raw(rest_url('yoy-ai-studio/v1')),
            'rest_route_url'   => esc_url_raw(site_url('index.php')) . '?rest_route=/yoy-ai-studio/v1',
        ];

        if (empty($registered)) {
            return self::make('rest', 'REST API', 'error', 'REST 라우트가 하나도 등록되지 않았습니다.', $detail, 'flush_rewrite_rules');
        }
        if (!empty($missing)) {
            return self::make('rest', 'REST API', 'error', count($missing) . '개 필수 엔드포인트가 없습니다.', $detail, 'flush_rewrite_rules');
        }
        return self::make('rest', 'REST API', 'ok', '모든 필수 엔드포인트 등록됨 (' . count($registered) . ')', $detail);
    }

    // ---- ② OpenAI ---------------------------------------------------------
    private static function check_openai() {
        if (!class_exists('YooY_Provider_Resolver')) {
            return self::make('openai', 'OpenAI', 'warn', 'Provider 시스템을 사용할 수 없습니다.');
        }
        $state = YooY_Provider_Resolver::get_provider_state('openai');
        $configured = class_exists('YooY_Secrets') ? YooY_Secrets::has_api_key('yoy_openai_api_key') : false;
        $test = isset($state['last_test_status']) ? $state['last_test_status'] : 'not_tested';
        $detail = [
            'configured'       => $configured,
            'last_test_status' => $test,
            'billing_status'   => isset($state['billing_status']) ? $state['billing_status'] : 'ok',
        ];

        if (!$configured) {
            return self::make('openai', 'OpenAI', 'warn', 'OpenAI API 키가 설정되지 않았습니다.', $detail, 'open_providers');
        }
        if (isset($state['billing_status']) && $state['billing_status'] === 'blocked') {
            return self::make('openai', 'OpenAI', 'error', 'OpenAI 결제/크레딧 오류로 차단됨 (YooY 사용자 크레딧과 무관).', $detail, 'open_providers');
        }
        if ($test === 'passed') {
            return self::make('openai', 'OpenAI', 'ok', 'gpt-image-1 Ready (Test Passed)', $detail);
        }
        if ($test === 'failed') {
            return self::make('openai', 'OpenAI', 'error', 'OpenAI Test 실패.', $detail, 'open_providers');
        }
        return self::make('openai', 'OpenAI', 'warn', 'OpenAI가 아직 Test Connection을 통과하지 않았습니다.', $detail, 'open_providers');
    }

    // ---- ③ Provider (resolved for image auto) -----------------------------
    private static function check_provider($user_id) {
        if (!class_exists('YooY_Provider_Resolver')) {
            return self::make('provider', 'Provider', 'error', 'Provider Resolver 클래스를 찾을 수 없습니다.');
        }
        $resolution = YooY_Provider_Resolver::resolve('image', ['provider' => 'auto'], (int) $user_id);
        $provider = isset($resolution['provider']) ? $resolution['provider'] : '';
        $is_mock  = !empty($resolution['is_mock']);
        $detail = [
            'resolved_provider' => $provider,
            'catalog_provider'  => isset($resolution['catalog_provider']) ? $resolution['catalog_provider'] : '',
            'model'             => isset($resolution['model']) ? $resolution['model'] : '',
            'is_mock'           => $is_mock,
            'fallback_reason'   => isset($resolution['fallback_reason']) ? $resolution['fallback_reason'] : '',
            'warning'           => isset($resolution['warning']) ? $resolution['warning'] : '',
        ];

        if ($provider === '') {
            return self::make('provider', 'Provider', 'error', '이미지 생성 Provider를 확정할 수 없습니다.', $detail, 'open_providers');
        }
        if ($is_mock) {
            return self::make('provider', 'Provider', 'warn', '실 Provider가 없어 Mock으로 생성됩니다.', $detail, 'open_providers');
        }
        return self::make('provider', 'Provider', 'ok', 'Auto → ' . strtoupper($provider) . (isset($resolution['model']) && $resolution['model'] ? ' / ' . $resolution['model'] : ''), $detail);
    }

    // ---- ④ Credits --------------------------------------------------------
    private static function check_credits($user_id) {
        if ((int) $user_id <= 0) {
            return self::make('credits', 'Credits', 'error', '로그인이 필요합니다.');
        }
        if (!class_exists('YooY_Credits_Service')) {
            return self::make('credits', 'Credits', 'warn', 'Credits 서비스를 사용할 수 없습니다.');
        }
        $svc = new YooY_Credits_Service();
        $snap = $svc->snapshot((int) $user_id);
        $detail = $snap;
        if (!empty($snap['unlimited'])) {
            return self::make('credits', 'Credits', 'ok', 'Unlimited (' . (isset($snap['plan_name']) ? $snap['plan_name'] : 'Plan') . ')', $detail);
        }
        $balance = (int) (isset($snap['balance']) ? $snap['balance'] : 0);
        if ($balance <= 0) {
            return self::make('credits', 'Credits', 'error', '크레딧이 부족합니다.', $detail, 'open_credits');
        }
        if ($balance < 10) {
            return self::make('credits', 'Credits', 'warn', '크레딧이 곧 소진됩니다 (' . $balance . ').', $detail, 'open_credits');
        }
        return self::make('credits', 'Credits', 'ok', $balance . ' credits', $detail);
    }

    // ---- ⑤ Gallery --------------------------------------------------------
    private static function check_gallery($user_id) {
        if (!class_exists('YooY_Gallery_Store')) {
            return self::make('gallery', 'Gallery', 'error', 'Gallery Store 클래스를 찾을 수 없습니다.');
        }
        try {
            $store = new YooY_Gallery_Store();
            $items = $store->get_all((int) $user_id);
            $detail = ['items' => is_array($items) ? count($items) : 0];
            return self::make('gallery', 'Gallery', 'ok', '저장소 정상 (' . $detail['items'] . ' items)', $detail);
        } catch (Exception $e) {
            return self::make('gallery', 'Gallery', 'error', 'Gallery 저장소 접근 실패: ' . $e->getMessage());
        }
    }

    // ---- ⑥ Projects -------------------------------------------------------
    private static function check_projects($user_id) {
        if (!class_exists('YooY_Project_Store')) {
            if (defined('YOY_AI_STUDIO_MODULES_DIR') && file_exists(YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php')) {
                require_once YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php';
            }
        }
        if (!class_exists('YooY_Project_Store')) {
            return self::make('projects', 'Projects', 'warn', 'Projects 모듈을 사용할 수 없습니다.');
        }
        try {
            $store = new YooY_Project_Store();
            $count = $store->count((int) $user_id);
            return self::make('projects', 'Projects', 'ok', '저장소 정상 (' . (int) $count . ' projects)', ['count' => (int) $count]);
        } catch (Exception $e) {
            return self::make('projects', 'Projects', 'error', 'Projects 저장소 접근 실패: ' . $e->getMessage());
        }
    }

    // ---- ⑦ Upload (uploads dir writable) ----------------------------------
    private static function check_upload() {
        $dir = wp_upload_dir();
        $detail = [
            'basedir'  => isset($dir['basedir']) ? $dir['basedir'] : '',
            'error'    => isset($dir['error']) ? $dir['error'] : false,
        ];
        if (!empty($dir['error'])) {
            return self::make('upload', 'Upload', 'error', '업로드 디렉터리 오류: ' . $dir['error'], $detail);
        }
        $writable = isset($dir['basedir']) && wp_is_writable($dir['basedir']);
        $detail['writable'] = $writable;
        if (!$writable) {
            return self::make('upload', 'Upload', 'error', '업로드 디렉터리에 쓸 수 없습니다.', $detail);
        }
        return self::make('upload', 'Upload', 'ok', '업로드 디렉터리 쓰기 가능', $detail);
    }

    // ---- ⑧ WordPress Permission -------------------------------------------
    private static function check_permission($user_id) {
        $logged_in = ((int) $user_id > 0) && is_user_logged_in();
        $detail = [
            'logged_in'    => $logged_in,
            'can_read'     => current_user_can('read'),
            'can_upload'   => current_user_can('upload_files'),
            'is_admin'     => current_user_can('manage_options'),
        ];
        if (!$logged_in) {
            return self::make('permission', 'WordPress Permission', 'error', '로그인 세션이 없습니다.');
        }
        if (!current_user_can('read')) {
            return self::make('permission', 'WordPress Permission', 'error', '기본 읽기 권한이 없습니다.', $detail);
        }
        return self::make('permission', 'WordPress Permission', 'ok', '권한 정상', $detail);
    }

    // ---- ⑨ PHP Upload capability ------------------------------------------
    private static function check_php_upload() {
        $file_uploads = (bool) ini_get('file_uploads');
        $umax = self::bytes(ini_get('upload_max_filesize'));
        $pmax = self::bytes(ini_get('post_max_size'));
        $mem  = self::bytes(ini_get('memory_limit'));
        $detail = [
            'file_uploads'        => $file_uploads,
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size'       => ini_get('post_max_size'),
            'memory_limit'        => ini_get('memory_limit'),
            'max_execution_time'  => ini_get('max_execution_time'),
            'php_version'         => PHP_VERSION,
        ];
        if (!$file_uploads) {
            return self::make('php_upload', 'PHP Upload', 'error', 'PHP file_uploads 가 비활성화되어 있습니다.', $detail);
        }
        if ($umax > 0 && $umax < 4 * 1024 * 1024) {
            return self::make('php_upload', 'PHP Upload', 'warn', 'upload_max_filesize 가 낮습니다 (' . $detail['upload_max_filesize'] . ').', $detail);
        }
        if ($mem > 0 && $mem < 128 * 1024 * 1024) {
            return self::make('php_upload', 'PHP Upload', 'warn', 'memory_limit 가 낮습니다 (' . $detail['memory_limit'] . ').', $detail);
        }
        return self::make('php_upload', 'PHP Upload', 'ok', 'PHP 업로드 설정 정상', $detail);
    }

    // ---- ⑩ Image Save -----------------------------------------------------
    private static function check_image_save() {
        $dir = wp_upload_dir();
        $writable = empty($dir['error']) && isset($dir['basedir']) && wp_is_writable($dir['basedir']);
        $gd = function_exists('imagecreatetruecolor');
        $imagick = class_exists('Imagick');
        $detail = [
            'uploads_writable' => $writable,
            'gd'               => $gd,
            'imagick'          => $imagick,
        ];
        if (!$writable) {
            return self::make('image_save', 'Image Save', 'error', '생성 이미지를 저장할 수 없습니다 (업로드 디렉터리 쓰기 불가).', $detail);
        }
        if (!$gd && !$imagick) {
            return self::make('image_save', 'Image Save', 'warn', 'GD/Imagick 미탑재 — 썸네일 생성이 제한될 수 있습니다.', $detail);
        }
        return self::make('image_save', 'Image Save', 'ok', '이미지 저장 가능', $detail);
    }

    // ---- Admin system info ------------------------------------------------
    private static function system_info() {
        global $wp_version;
        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $next_cron = wp_next_scheduled('wp_version_check');
        return [
            'wordpress'     => isset($wp_version) ? $wp_version : get_bloginfo('version'),
            'php'           => PHP_VERSION,
            'memory_limit'  => ini_get('memory_limit'),
            'max_execution' => ini_get('max_execution_time'),
            'cron'          => [
                'disabled'    => $cron_disabled,
                'next_check'  => $next_cron ? gmdate('c', $next_cron) : null,
                'status'      => $cron_disabled ? 'warn' : 'ok',
            ],
            'https'         => is_ssl(),
            'multisite'     => is_multisite(),
            'debug'         => (defined('WP_DEBUG') && WP_DEBUG),
        ];
    }

    /**
     * Attempt an automatic fix for a fixable check.
     *
     * @param string $action Fix action id.
     * @return array ['success'=>bool,'status'=>'ok|warn|error','message'=>string,'requires'=>string]
     */
    public static function fix($action) {
        $action = sanitize_key((string) $action);
        switch ($action) {
            case 'flush_rewrite_rules':
                flush_rewrite_rules(false);
                if (class_exists('YooY_System_Log')) {
                    YooY_System_Log::write('info', 'Self-diagnosis auto-fix: flushed rewrite rules', ['action' => $action]);
                }
                return [
                    'success' => true,
                    'status'  => 'ok',
                    'message' => 'REST rewrite 규칙을 재생성했습니다. 페이지를 새로고침하세요.',
                    'requires'=> 'reload',
                ];
            default:
                return [
                    'success' => false,
                    'status'  => 'error',
                    'message' => '지원하지 않는 자동 수정 동작입니다: ' . $action,
                    'requires'=> '',
                ];
        }
    }

    private static function bytes($val) {
        $val = trim((string) $val);
        if ($val === '') {
            return 0;
        }
        $last = strtolower($val[strlen($val) - 1]);
        $num = (float) $val;
        switch ($last) {
            case 'g': $num *= 1024;
            // fall through
            case 'm': $num *= 1024;
            // fall through
            case 'k': $num *= 1024;
        }
        return (int) $num;
    }
}
