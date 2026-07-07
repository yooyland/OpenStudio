<?php
if (!defined('ABSPATH')) exit;

final class YooY_Admin_Providers {

    public static function catalog(): array {
        if (!class_exists('YooY_Provider_Catalog')) {
            return [];
        }
        return YooY_Provider_Catalog::definitions();
    }

    public static function list(): array {
        $modes = get_option('yoy_provider_modes', []);
        $modes = is_array($modes) ? $modes : [];
        $items = [];

        foreach (self::catalog() as $id => $meta) {
            $option  = (string) ($meta['option'] ?? '');
            $has_key = $option !== '' && YooY_Secrets::has_api_key($option);
            $mode    = sanitize_text_field($modes[$id] ?? 'auto');
            if ($mode === 'auto') {
                $mode = (($meta['impl'] ?? '') === 'mock' || !$has_key) ? 'mock' : 'real';
            }

            $state = class_exists('YooY_Provider_Resolver')
                ? YooY_Provider_Resolver::get_provider_state($id)
                : ['priority' => (int) ($meta['priority'] ?? 50)];

            if (empty($state['priority'])) {
                $state['priority'] = (int) ($meta['priority'] ?? 50);
            }

            $row = [
                'id'           => $id,
                'name'         => $meta['name'],
                'studios'      => $meta['studios'],
                'supports'     => $meta['studios'],
                'mode'         => $mode,
                'has_key'      => $has_key,
                'key_masked'   => $has_key ? YooY_Secrets::mask_key(YooY_Secrets::get_api_key($option)) : '',
                'status'       => $mode === 'mock' ? 'mock' : ($has_key ? 'active' : 'pending'),
                'priority'     => (int) ($state['priority'] ?? 50),
                'enabled'      => !isset($state['enabled']) || !empty($state['enabled']),
                'impl'         => (string) ($meta['impl'] ?? ''),
                'model'        => !empty($state['model']) ? (string) $state['model'] : self::default_model($id),
                'allowed_models' => self::allowed_models_for($id, $meta),
                'provider_label' => (string) ($meta['name'] ?? $id),
            ];

            $items[] = self::finalize_row(
                class_exists('YooY_Provider_Resolver')
                    ? YooY_Provider_Resolver::enrich_provider_row($row)
                    : $row,
                $meta
            );
        }

        return $items;
    }

    public static function summary(): array {
        $items = self::list();
        $summary = [
            'connected'   => 0,
            'need_test'   => 0,
            'failed'      => 0,
            'unsupported' => 0,
            'mock'        => 0,
            'disabled'    => 0,
            'api_missing' => 0,
            'total'       => count($items),
        ];
        foreach ($items as $row) {
            $group = $row['health_group'] ?? 'configured';
            if ($group === 'connected') {
                $summary['connected']++;
            } elseif ($group === 'configured' || $group === 'api_missing') {
                $summary['need_test']++;
                if ($group === 'api_missing') {
                    $summary['api_missing']++;
                }
            } elseif ($group === 'error') {
                $summary['failed']++;
            } elseif ($group === 'unsupported') {
                $summary['unsupported']++;
            } elseif ($group === 'mock') {
                $summary['mock']++;
            } elseif ($group === 'disabled') {
                $summary['disabled']++;
            }
        }
        return $summary;
    }

    public static function find(string $provider_id): ?array {
        foreach (self::list() as $row) {
            if (($row['id'] ?? '') === $provider_id) {
                return $row;
            }
        }
        return null;
    }

    private static function finalize_row(array $row, array $meta): array {
        $row['health_group'] = self::classify_health_group($row, $meta);
        $row['status_badge'] = self::status_badge($row);
        $row['last_test_relative'] = self::relative_time((string) ($row['last_test_at'] ?? ''));
        if ($row['success_rate'] === null) {
            if (($row['last_test_status'] ?? '') === 'passed') {
                $row['success_rate'] = 99;
            } elseif (($row['last_test_status'] ?? '') === 'failed') {
                $row['success_rate'] = 0;
            }
        }
        $row['error_reason'] = self::error_reason($row);
        return $row;
    }

    private static function classify_health_group(array $row, array $meta): string {
        if (($meta['impl'] ?? '') === 'mock' || strpos((string) ($row['id'] ?? ''), 'mock-') === 0) {
            return 'mock';
        }
        if (empty($row['enabled'])) {
            return 'disabled';
        }
        if (($row['last_test_status'] ?? '') === 'failed') {
            return 'error';
        }
        if (($row['last_test_status'] ?? '') === 'unsupported') {
            return 'unsupported';
        }
        if (!empty($row['last_test_error_type']) && in_array($row['last_test_error_type'], ['auth_error', 'rate_limit', 'server_error'], true)) {
            return 'error';
        }
        if (!empty($row['usable']) && ($row['last_test_status'] ?? '') === 'passed' && !empty($row['has_key'])) {
            return 'connected';
        }
        if (empty($row['has_key'])) {
            return 'api_missing';
        }
        if (($row['last_test_status'] ?? '') === 'passed' && empty($row['usable'])) {
            return 'configured';
        }
        if (($row['last_test_status'] ?? '') !== 'passed') {
            return 'configured';
        }
        return 'configured';
    }

    private static function status_badge(array $row): array {
        $group = $row['health_group'] ?? 'configured';
        if ($group === 'connected') {
            return ['label' => 'CONNECTED', 'tone' => 'connected'];
        }
        if ($group === 'configured') {
            if (($row['last_test_status'] ?? '') === 'passed') {
                return ['label' => 'TEST PASSED', 'tone' => 'tested'];
            }
            return ['label' => 'NOT TESTED', 'tone' => 'pending'];
        }
        if ($group === 'error') {
            return ['label' => 'FAILED', 'tone' => 'error'];
        }
        if ($group === 'unsupported') {
            return ['label' => 'UNSUPPORTED', 'tone' => 'warning'];
        }
        if ($group === 'disabled') {
            return ['label' => 'DISABLED', 'tone' => 'disabled'];
        }
        if ($group === 'mock') {
            return ['label' => 'MOCK', 'tone' => 'mock'];
        }
        if ($group === 'api_missing') {
            return ['label' => 'API MISSING', 'tone' => 'warning'];
        }
        if (($row['last_test_status'] ?? '') === 'passed') {
            return ['label' => 'TEST PASSED', 'tone' => 'tested'];
        }
        return ['label' => 'UNKNOWN', 'tone' => 'pending'];
    }

    private static function error_reason(array $row): string {
        if (!empty($row['last_test_error'])) {
            return (string) $row['last_test_error'];
        }
        if (!empty($row['warning'])) {
            return (string) $row['warning'];
        }
        if (empty($row['has_key'])) {
            return 'API key not configured.';
        }
        if (($row['last_test_status'] ?? '') === 'not_tested') {
            return 'Connection test has not been run.';
        }
        return '';
    }

    private static function relative_time(string $iso): string {
        if ($iso === '') {
            return 'Never';
        }
        $ts = strtotime($iso);
        if (!$ts) {
            return 'Never';
        }
        $diff = time() - $ts;
        if ($diff < 60) {
            return max(1, $diff) . ' sec ago';
        }
        if ($diff < 3600) {
            return (int) floor($diff / 60) . ' min ago';
        }
        if ($diff < 86400) {
            return (int) floor($diff / 3600) . ' hr ago';
        }
        return (int) floor($diff / 86400) . ' d ago';
    }

    private static function default_model(string $id): string {
        $map = [
            'openai'       => 'gpt-image-1',
            'openai-tts'   => 'tts-1',
            'runway'       => 'gen-3-alpha',
            'replicate'    => 'flux-schnell',
            'flux'         => 'flux-schnell',
            'gemini-image' => 'gemini-image',
            'stability'    => 'stable-diffusion',
            'ideogram'     => 'ideogram-v2',
            'suno'         => 'chirp-v4',
            'elevenlabs'   => 'eleven_multilingual_v2',
            'heygen'       => 'avatar-v2',
            'mock-image'   => 'mock-image-v1',
            'mock-video'   => 'mock-video-v1',
            'mock-music'   => 'mock-music-v1',
            'mock-voice'   => 'mock-voice-v1',
            'mock-avatar'  => 'mock-avatar-v1',
        ];
        return $map[$id] ?? 'default';
    }

    private static function allowed_models_for(string $id, array $meta): array {
        $studio = (string) (($meta['studios'][0] ?? '') ?: ($meta['type'] ?? 'image'));
        if (!class_exists('YooY_Studio_Model_Resolver')) {
            return [self::default_model($id)];
        }
        $route_id = class_exists('YooY_Provider_Catalog')
            ? YooY_Provider_Catalog::route_id($id)
            : $id;
        return YooY_Studio_Model_Resolver::allowed_models($studio, $route_id, $id);
    }

    public static function provider_logs(string $provider_id, int $limit = 40): array {
        $logs = class_exists('YooY_System_Log')
            ? YooY_System_Log::recent_for_provider($provider_id, $limit)
            : [];
        $jobs = self::recent_jobs_for_provider($provider_id, $limit);
        return [
            'provider_id' => $provider_id,
            'system_logs' => $logs,
            'recent_jobs' => $jobs,
            'entries'     => self::merge_log_entries($logs, $jobs, $limit),
        ];
    }

    public static function provider_monitoring(string $provider_id): array {
        $jobs = self::recent_jobs_for_provider($provider_id, 100);
        $total = count($jobs);
        $failed = 0;
        $latency_sum = 0;
        $latency_count = 0;
        foreach ($jobs as $job) {
            $st = strtolower((string) ($job['status'] ?? ''));
            if (in_array($st, ['failed', 'error'], true)) {
                $failed++;
            }
            $ms = (int) ($job['duration_ms'] ?? $job['latency_ms'] ?? 0);
            if ($ms > 0) {
                $latency_sum += $ms;
                $latency_count++;
            }
        }
        $row = self::find($provider_id);
        $registry_ms = (int) ($row['last_test_ms'] ?? 0);
        if ($latency_count === 0 && $registry_ms > 0) {
            $latency_sum = $registry_ms;
            $latency_count = 1;
        }
        $success_rate = $total > 0 ? max(0, min(100, (int) round((($total - $failed) / $total) * 100))) : ($row['success_rate'] ?? null);
        return [
            'provider_id'   => $provider_id,
            'request_count' => $total,
            'failed_count'  => $failed,
            'success_rate'  => $success_rate,
            'avg_latency_ms'=> $latency_count > 0 ? (int) round($latency_sum / $latency_count) : $registry_ms,
            'recent_errors' => array_values(array_filter(array_map(function ($job) {
                if (!in_array(strtolower((string) ($job['status'] ?? '')), ['failed', 'error'], true)) {
                    return null;
                }
                return [
                    'id'         => $job['id'] ?? $job['job_id'] ?? '',
                    'message'    => $job['error'] ?? $job['message'] ?? 'Job failed',
                    'created_at' => $job['updated_at'] ?? $job['created_at'] ?? '',
                    'studio'     => $job['studio'] ?? $job['type'] ?? '',
                ];
            }, $jobs))),
            'usage' => self::usage_buckets($jobs),
        ];
    }

    private static function usage_buckets(array $jobs): array {
        $buckets = [];
        for ($i = 5; $i >= 0; $i--) {
            $label = gmdate('H:i', strtotime('-' . ($i * 4) . ' hours'));
            $buckets[$label] = 0;
        }
        foreach ($jobs as $job) {
            $at = $job['updated_at'] ?? $job['created_at'] ?? '';
            if ($at === '') {
                continue;
            }
            $label = gmdate('H:i', strtotime($at));
            if (!isset($buckets[$label])) {
                $buckets[$label] = 0;
            }
            $buckets[$label]++;
        }
        return [
            'labels' => array_keys($buckets),
            'values' => array_values($buckets),
        ];
    }

    private static function recent_jobs_for_provider(string $provider_id, int $limit): array {
        if (!class_exists('YooY_Job_Store')) {
            return [];
        }
        $store = new YooY_Job_Store();
        $rows = [];
        foreach (get_users(['number' => 40, 'fields' => ['ID']]) as $user) {
            foreach ($store->all((int) $user->ID) as $job) {
                $prov = strtolower((string) ($job['provider'] ?? $job['provider_used'] ?? ''));
                if ($prov !== strtolower($provider_id) && $prov !== 'mock' && strpos($provider_id, 'mock') !== 0) {
                    continue;
                }
                if ($prov === 'mock' && strpos($provider_id, 'mock') !== 0) {
                    continue;
                }
                if ($prov !== strtolower($provider_id)) {
                    continue;
                }
                $job['user_id'] = (int) $user->ID;
                $rows[] = $job;
            }
        }
        usort($rows, function ($a, $b) {
            return strcmp($b['updated_at'] ?? $b['created_at'] ?? '', $a['updated_at'] ?? $a['created_at'] ?? '');
        });
        return array_slice($rows, 0, $limit);
    }

    private static function merge_log_entries(array $logs, array $jobs, int $limit): array {
        $entries = [];
        foreach ($logs as $log) {
            $entries[] = [
                'type'       => 'system',
                'level'      => $log['level'] ?? 'info',
                'message'    => $log['message'] ?? '',
                'context'    => $log['context'] ?? [],
                'created_at' => $log['created_at'] ?? '',
            ];
        }
        foreach ($jobs as $job) {
            $entries[] = [
                'type'       => 'job',
                'level'      => in_array(strtolower((string) ($job['status'] ?? '')), ['failed', 'error'], true) ? 'error' : 'info',
                'message'    => ($job['label'] ?? $job['type'] ?? 'Job') . ' · ' . ($job['status'] ?? 'unknown'),
                'context'    => [
                    'provider' => $job['provider'] ?? $job['provider_used'] ?? '',
                    'job_id'   => $job['id'] ?? $job['job_id'] ?? '',
                    'response' => $job['error'] ?? $job['output'] ?? null,
                ],
                'created_at' => $job['updated_at'] ?? $job['created_at'] ?? '',
            ];
        }
        usort($entries, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        return array_slice($entries, 0, $limit);
    }

    public static function disable(string $provider_id): array {
        $catalog = self::catalog();
        if (!isset($catalog[$provider_id])) {
            throw new Exception('Unknown provider.');
        }
        $modes = get_option('yoy_provider_modes', []);
        $modes = is_array($modes) ? $modes : [];
        $modes[$provider_id] = 'disabled';
        update_option('yoy_provider_modes', $modes, false);
        if (class_exists('YooY_Provider_Resolver')) {
            YooY_Provider_Resolver::save_provider_state($provider_id, [
                'enabled' => false,
                'active'  => false,
            ]);
        }
        YooY_System_Log::write('warn', 'Provider disabled', ['provider' => $provider_id]);
        return self::find($provider_id) ?: [];
    }

    public static function enable(string $provider_id): array {
        $catalog = self::catalog();
        if (!isset($catalog[$provider_id])) {
            throw new Exception('Unknown provider.');
        }
        $modes = get_option('yoy_provider_modes', []);
        $modes = is_array($modes) ? $modes : [];
        if (($modes[$provider_id] ?? '') === 'disabled') {
            $modes[$provider_id] = 'auto';
            update_option('yoy_provider_modes', $modes, false);
        }
        if (class_exists('YooY_Provider_Resolver')) {
            YooY_Provider_Resolver::save_provider_state($provider_id, [
                'enabled' => true,
                'active'  => false,
            ]);
        }
        YooY_System_Log::write('info', 'Provider enabled', ['provider' => $provider_id]);
        return self::find($provider_id) ?: [];
    }

    public static function save(string $provider_id, array $payload): array {
        $catalog = self::catalog();
        if (!isset($catalog[$provider_id])) {
            throw new Exception('Unknown provider.');
        }

        $meta  = $catalog[$provider_id];
        $modes = get_option('yoy_provider_modes', []);
        $modes = is_array($modes) ? $modes : [];

        if (isset($payload['mode'])) {
            $mode = sanitize_text_field($payload['mode']);
            if (!in_array($mode, ['mock', 'real', 'auto'], true)) {
                throw new Exception('Invalid mode.');
            }
            $modes[$provider_id] = $mode;
            update_option('yoy_provider_modes', $modes, false);
        }

        if (!empty($payload['api_key'])) {
            $option = (string) ($meta['option'] ?? '');
            if ($option === '') {
                throw new Exception('This provider does not accept API keys.');
            }
            YooY_Secrets::set_api_key($option, self::sanitize_api_key((string) $payload['api_key']));
        }

        if (isset($payload['studio_defaults']) && is_array($payload['studio_defaults']) && class_exists('YooY_Provider_Resolver')) {
            foreach ($meta['studios'] as $studio) {
                $enabled = !empty($payload['studio_defaults'][$studio]);
                if ($enabled) {
                    YooY_Provider_Resolver::set_studio_default($studio, $provider_id);
                } elseif (YooY_Provider_Resolver::admin_default_for_studio($studio) === $provider_id) {
                    YooY_Provider_Resolver::clear_studio_default($studio);
                }
            }
        }

        if (class_exists('YooY_Provider_Resolver')) {
            $state_payload = [];
            if (array_key_exists('enabled', $payload)) {
                $state_payload['enabled'] = !empty($payload['enabled']);
            }
            if (array_key_exists('priority', $payload)) {
                $state_payload['priority'] = (int) $payload['priority'];
            }
            if (array_key_exists('billing_status', $payload)) {
                $state_payload['billing_status'] = sanitize_text_field($payload['billing_status']);
            }
            if (array_key_exists('model', $payload)) {
                $state_payload['model'] = sanitize_text_field((string) $payload['model']);
            }
            if ($state_payload !== []) {
                YooY_Provider_Resolver::save_provider_state($provider_id, $state_payload);
            }
        }

        YooY_System_Log::write('info', 'Provider settings updated', ['provider' => $provider_id]);

        return [
            'provider'  => self::find($provider_id),
            'providers' => self::list(),
            'summary'   => self::summary(),
        ];
    }

    private static function sanitize_api_key(string $key): string {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        return preg_replace('/[\x00-\x1F\x7F]/', '', $key);
    }

    public static function test(string $provider_id): array {
        $catalog = self::catalog();
        if (!isset($catalog[$provider_id])) {
            throw new Exception('Unknown provider.');
        }

        $meta = $catalog[$provider_id];
        $option = (string) ($meta['option'] ?? '');
        $key  = $option !== '' ? YooY_Secrets::get_api_key($option) : '';
        $modes = get_option('yoy_provider_modes', []);
        $mode  = sanitize_text_field(is_array($modes) ? ($modes[$provider_id] ?? 'auto') : 'auto');

        if ($mode === 'mock' || ($meta['impl'] ?? '') === 'mock') {
            if (class_exists('YooY_Provider_Resolver')) {
                YooY_Provider_Resolver::set_test_result($provider_id, true, [
                    'latency_ms' => 12,
                    'status'     => 'passed',
                ]);
            }
            $provider = self::find($provider_id);
            return self::test_response($provider_id, true, 'passed', 12, 'Mock mode active — no remote call required.', '', $provider);
        }

        if ($key === '') {
            if (class_exists('YooY_Provider_Resolver')) {
                YooY_Provider_Resolver::set_test_result($provider_id, false, [
                    'status'     => 'failed',
                    'error'      => 'API key not configured.',
                    'error_type' => 'auth_error',
                ]);
            }
            throw new Exception('API key not configured.');
        }

        $ping = self::ping_provider($provider_id, $key);
        $status = !empty($ping['unsupported']) ? 'unsupported' : (!empty($ping['ok']) ? 'passed' : 'failed');
        $ok = $status === 'passed';

        if (class_exists('YooY_Provider_Resolver')) {
            YooY_Provider_Resolver::set_test_result($provider_id, $ok, [
                'status'       => $status,
                'latency_ms'   => (int) ($ping['latency_ms'] ?? 0),
                'error'        => $ok ? '' : ($ping['error'] ?? 'Connection test failed.'),
                'error_type'   => $ok ? '' : ($ping['error_type'] ?? 'server_error'),
                'raw_summary'  => (string) ($ping['raw_summary'] ?? ''),
            ]);
        }

        if ($status === 'unsupported') {
            YooY_System_Log::write('warn', 'Provider test unsupported', [
                'provider' => $provider_id,
                'error'    => $ping['error'] ?? '',
            ]);
            return self::test_response(
                $provider_id,
                false,
                'unsupported',
                (int) ($ping['latency_ms'] ?? 0),
                (string) ($ping['error'] ?? 'Automated connection test is not supported for this provider.'),
                (string) ($ping['raw_summary'] ?? ''),
                self::find($provider_id)
            );
        }

        if (!$ok) {
            YooY_System_Log::write('error', 'Provider test failed', [
                'provider'   => $provider_id,
                'error'      => $ping['error'] ?? '',
                'error_type' => $ping['error_type'] ?? '',
            ]);
            throw new Exception($ping['error'] ?? 'Connection test failed.');
        }

        YooY_System_Log::write('info', 'Provider test succeeded', [
            'provider'   => $provider_id,
            'latency_ms' => (int) ($ping['latency_ms'] ?? 0),
        ]);

        return self::test_response(
            $provider_id,
            true,
            'passed',
            (int) ($ping['latency_ms'] ?? 0),
            'Connection test passed.',
            (string) ($ping['raw_summary'] ?? ''),
            self::find($provider_id)
        );
    }

    private static function test_response(string $provider_id, bool $success, string $status, int $latency_ms, string $message, string $raw_summary, ?array $provider): array {
        return [
            'success'      => $success,
            'ok'           => $success,
            'provider'     => $provider,
            'provider_id'  => $provider_id,
            'stage'        => 'test_connection',
            'status'       => $status,
            'latency_ms'   => $latency_ms,
            'message'      => $message,
            'raw_summary'  => $raw_summary,
        ];
    }

    public static function set_studio_default(string $provider_id, string $studio): array {
        $catalog = self::catalog();
        if (!isset($catalog[$provider_id])) {
            throw new Exception('Unknown provider.');
        }
        if (!in_array($studio, $catalog[$provider_id]['studios'], true)) {
            throw new Exception('Provider does not support this studio.');
        }
        if (!class_exists('YooY_Provider_Resolver')) {
            throw new Exception('Provider resolver unavailable.');
        }
        return YooY_Provider_Resolver::set_studio_default($studio, $provider_id);
    }

    private static function ping_provider(string $id, string $key): array {
        $start = microtime(true);
        $latency = static function () use ($start): int {
            return max(1, (int) round((microtime(true) - $start) * 1000));
        };

        switch ($id) {
            case 'openai':
            case 'openai-tts':
                return self::ping_http_get('https://api.openai.com/v1/models', [
                    'Authorization' => 'Bearer ' . $key,
                ], $latency);
            case 'replicate':
            case 'flux':
                return self::ping_http_get('https://api.replicate.com/v1/account', [
                    'Authorization' => 'Token ' . $key,
                    'Content-Type'  => 'application/json',
                ], $latency);
            case 'runway':
                return self::ping_runway($key, $latency);
            case 'elevenlabs':
                return self::ping_http_get('https://api.elevenlabs.io/v1/user', [
                    'xi-api-key' => $key,
                ], $latency);
            case 'heygen':
                return self::ping_http_get('https://api.heygen.com/v2/user/remaining_quota', [
                    'X-Api-Key' => $key,
                ], $latency);
            case 'suno':
                return self::ping_unsupported(
                    $latency,
                    'Suno API key saved, but automated connection test is not supported by this endpoint.'
                );
            default:
                if (self::is_bridge_provider($id)) {
                    return self::ping_unsupported(
                        $latency,
                        ucfirst($id) . ' API key saved, but automated connection test is not supported by this endpoint.'
                    );
                }
                return self::ping_unsupported(
                    $latency,
                    'API key saved, but automated connection test is not supported for this provider.'
                );
        }
    }

    private static function ping_runway(string $key, callable $latency): array {
        $res = wp_remote_get('https://api.dev.runwayml.com/v1/organization', [
            'timeout' => 15,
            'headers' => [
                'Authorization'    => 'Bearer ' . $key,
                'X-Runway-Version' => '2024-11-06',
            ],
        ]);
        if (is_wp_error($res)) {
            return [
                'ok'          => false,
                'latency_ms'  => $latency(),
                'error'       => $res->get_error_message(),
                'error_type'  => 'server_error',
                'raw_summary' => '',
            ];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        $summary = self::summarize_json_body($body);
        if ($code === 401 || $code === 403) {
            return [
                'ok'          => false,
                'latency_ms'  => $latency(),
                'error'       => 'Authentication failed. Check Runway API key.',
                'error_type'  => 'auth_error',
                'raw_summary' => $summary,
            ];
        }
        if ($code === 429) {
            return [
                'ok'          => false,
                'latency_ms'  => $latency(),
                'error'       => 'Rate limit exceeded.',
                'error_type'  => 'rate_limit',
                'raw_summary' => $summary,
            ];
        }
        return [
            'ok'          => $code >= 200 && $code < 300,
            'latency_ms'  => $latency(),
            'error'       => $code >= 300 ? 'Runway API error (' . $code . ').' : '',
            'error_type'  => $code >= 300 ? 'server_error' : '',
            'raw_summary' => $summary,
        ];
    }

    private static function ping_http_get(string $url, array $headers, callable $latency): array {
        $res = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => $headers,
        ]);
        if (is_wp_error($res)) {
            return [
                'ok'          => false,
                'latency_ms'  => $latency(),
                'error'       => $res->get_error_message(),
                'error_type'  => 'server_error',
                'raw_summary' => '',
            ];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);
        $summary = self::summarize_json_body($body);
        if ($code === 401 || $code === 403) {
            return [
                'ok'          => false,
                'latency_ms'  => $latency(),
                'error'       => 'Authentication failed. Check API key.',
                'error_type'  => 'auth_error',
                'raw_summary' => $summary,
            ];
        }
        if ($code === 429) {
            return [
                'ok'          => false,
                'latency_ms'  => $latency(),
                'error'       => 'Rate limit exceeded.',
                'error_type'  => 'rate_limit',
                'raw_summary' => $summary,
            ];
        }
        return [
            'ok'          => $code >= 200 && $code < 300,
            'latency_ms'  => $latency(),
            'error'       => $code >= 300 ? 'API error (' . $code . ').' : '',
            'error_type'  => $code >= 300 ? 'server_error' : '',
            'raw_summary' => $summary,
        ];
    }

    private static function ping_unsupported(callable $latency, string $message): array {
        return [
            'ok'          => false,
            'unsupported' => true,
            'latency_ms'  => $latency(),
            'error'       => $message,
            'error_type'  => 'unsupported',
            'raw_summary' => '',
        ];
    }

    private static function summarize_json_body(string $body): string {
        if ($body === '') {
            return '';
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return substr($body, 0, 240);
        }
        $keys = array_slice(array_keys($decoded), 0, 8);
        return 'keys: ' . implode(', ', $keys);
    }

    private static function is_bridge_provider(string $id): bool {
        $catalog = self::catalog();
        return isset($catalog[$id]) && ($catalog[$id]['impl'] ?? '') === 'bridge';
    }

    public static function effective_mode(string $provider_id): string {
        $modes = get_option('yoy_provider_modes', []);
        $mode  = sanitize_text_field(is_array($modes) ? ($modes[$provider_id] ?? 'auto') : 'auto');
        if ($mode === 'disabled') {
            return 'disabled';
        }
        if ($mode === 'mock') {
            return 'mock';
        }
        if ($mode === 'real') {
            return 'real';
        }
        $catalog = self::catalog();
        if (!isset($catalog[$provider_id])) {
            return 'mock';
        }
        if (($catalog[$provider_id]['impl'] ?? '') === 'mock') {
            return 'mock';
        }
        $option = (string) ($catalog[$provider_id]['option'] ?? '');
        if ($option !== '' && YooY_Secrets::has_api_key($option)) {
            return 'real';
        }
        return 'mock';
    }
}
