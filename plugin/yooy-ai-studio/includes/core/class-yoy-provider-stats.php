<?php
if (!defined('ABSPATH')) exit;

/**
 * Per-provider generation statistics (daily request/success/fail counters,
 * latency, last error) and a rolling provider error log (last 100 entries).
 *
 * This is provider API telemetry — completely separate from YooY user credits.
 */
final class YooY_Provider_Stats {

    private const STATS_OPTION = 'yoy_provider_stats';
    private const LOG_OPTION    = 'yoy_provider_error_log';
    private const LOG_LIMIT     = 100;

    /**
     * Record a generation attempt/outcome for a provider.
     *
     * @param array<string, mixed> $data {
     *   provider, catalog_provider, model, status, error, error_type,
     *   latency_ms, retry, studio
     * }
     * @param bool $count_request Whether this call increments the request counter.
     */
    public static function record(array $data, bool $count_request = true): void {
        $provider = sanitize_text_field((string) ($data['provider'] ?? ''));
        if ($provider === '') {
            return;
        }

        $status  = strtolower(sanitize_text_field((string) ($data['status'] ?? '')));
        $is_success = ($status === 'completed');
        $is_fail    = in_array($status, ['failed', 'error', 'timeout'], true);

        $stats = get_option(self::STATS_OPTION, []);
        $stats = is_array($stats) ? $stats : [];
        $today = current_time('Y-m-d');

        $row = isset($stats[$provider]) && is_array($stats[$provider]) ? $stats[$provider] : [];
        if (($row['day'] ?? '') !== $today) {
            $row = [
                'day'      => $today,
                'requests' => 0,
                'success'  => 0,
                'fail'     => 0,
            ];
        }

        if ($count_request) {
            $row['requests'] = (int) ($row['requests'] ?? 0) + 1;
        }
        if ($is_success) {
            $row['success'] = (int) ($row['success'] ?? 0) + 1;
        } elseif ($is_fail) {
            $row['fail'] = (int) ($row['fail'] ?? 0) + 1;
        }

        $latency = (int) ($data['latency_ms'] ?? 0);
        if ($latency > 0) {
            $row['latency_sum']   = (int) ($row['latency_sum'] ?? 0) + $latency;
            $row['latency_count'] = (int) ($row['latency_count'] ?? 0) + 1;
            $row['last_latency_ms'] = $latency;
        }

        $row['last_status'] = $status;
        $row['last_at']     = gmdate('c');
        if ($is_fail) {
            $row['last_error']    = sanitize_text_field((string) ($data['error'] ?? 'Generation failed.'));
            $row['last_error_at'] = gmdate('c');
        }

        $stats[$provider] = $row;
        update_option(self::STATS_OPTION, $stats, false);

        if ($is_fail) {
            self::append_error_log($provider, $data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function append_error_log(string $provider, array $data): void {
        $log = get_option(self::LOG_OPTION, []);
        $log = is_array($log) ? $log : [];

        array_unshift($log, [
            'id'               => 'perr_' . wp_generate_uuid4(),
            'provider'         => $provider,
            'catalog_provider' => sanitize_text_field((string) ($data['catalog_provider'] ?? $provider)),
            'model'            => sanitize_text_field((string) ($data['model'] ?? '')),
            'studio'           => sanitize_text_field((string) ($data['studio'] ?? 'image')),
            'status'           => strtolower(sanitize_text_field((string) ($data['status'] ?? 'failed'))),
            'error'            => sanitize_text_field((string) ($data['error'] ?? 'Generation failed.')),
            'error_type'       => sanitize_text_field((string) ($data['error_type'] ?? '')),
            'retry'            => !empty($data['retry']),
            'created_at'       => gmdate('c'),
        ]);

        update_option(self::LOG_OPTION, array_slice($log, 0, self::LOG_LIMIT), false);
    }

    /**
     * Today's counters for a provider.
     *
     * @return array<string, int>
     */
    public static function today(string $provider): array {
        $provider = sanitize_text_field($provider);
        $stats = get_option(self::STATS_OPTION, []);
        $stats = is_array($stats) ? $stats : [];
        $today = current_time('Y-m-d');
        $row = isset($stats[$provider]) && is_array($stats[$provider]) ? $stats[$provider] : [];
        if (($row['day'] ?? '') !== $today) {
            return ['requests' => 0, 'success' => 0, 'fail' => 0];
        }
        return [
            'requests' => (int) ($row['requests'] ?? 0),
            'success'  => (int) ($row['success'] ?? 0),
            'fail'     => (int) ($row['fail'] ?? 0),
        ];
    }

    /**
     * Full stats row (today counters + latency + last error) for a provider.
     *
     * @return array<string, mixed>
     */
    public static function summary_for(string $provider): array {
        $provider = sanitize_text_field($provider);
        $stats = get_option(self::STATS_OPTION, []);
        $stats = is_array($stats) ? $stats : [];
        $today = current_time('Y-m-d');
        $row = isset($stats[$provider]) && is_array($stats[$provider]) ? $stats[$provider] : [];
        $fresh = (($row['day'] ?? '') === $today);

        $latency_count = (int) ($row['latency_count'] ?? 0);
        $avg_latency = $latency_count > 0 ? (int) round((int) ($row['latency_sum'] ?? 0) / $latency_count) : 0;

        return [
            'today_requests'  => $fresh ? (int) ($row['requests'] ?? 0) : 0,
            'today_success'   => $fresh ? (int) ($row['success'] ?? 0) : 0,
            'today_fail'      => $fresh ? (int) ($row['fail'] ?? 0) : 0,
            'avg_latency_ms'  => $avg_latency,
            'last_latency_ms' => (int) ($row['last_latency_ms'] ?? 0),
            'last_status'     => (string) ($row['last_status'] ?? ''),
            'last_error'      => (string) ($row['last_error'] ?? ''),
            'last_error_at'   => (string) ($row['last_error_at'] ?? ''),
        ];
    }

    /**
     * Rolling provider error log (most recent first).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function error_log(int $limit = self::LOG_LIMIT): array {
        $log = get_option(self::LOG_OPTION, []);
        $log = is_array($log) ? $log : [];
        $limit = max(1, min(self::LOG_LIMIT, $limit));
        return array_slice($log, 0, $limit);
    }
}
