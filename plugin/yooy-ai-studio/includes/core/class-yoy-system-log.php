<?php
if (!defined('ABSPATH')) exit;

final class YooY_System_Log {

    private const OPTION = 'yoy_system_logs';

    public static function write(string $level, string $message, array $context = []): void {
        $logs = get_option(self::OPTION, []);
        $logs = is_array($logs) ? $logs : [];

        array_unshift($logs, [
            'id'         => 'log_' . wp_generate_uuid4(),
            'level'      => sanitize_key($level),
            'message'    => sanitize_text_field($message),
            'context'    => $context,
            'created_at' => gmdate('c'),
        ]);

        update_option(self::OPTION, array_slice($logs, 0, 500), false);
    }

    public static function recent(int $limit = 50, string $level = ''): array {
        $logs = get_option(self::OPTION, []);
        $logs = is_array($logs) ? $logs : [];

        if ($level !== '') {
            $logs = array_values(array_filter($logs, function ($row) use ($level) {
                return ($row['level'] ?? '') === $level;
            }));
        }

        return array_slice($logs, 0, $limit);
    }

    public static function recent_for_provider(string $provider_id, int $limit = 40): array {
        $provider_id = sanitize_text_field($provider_id);
        if ($provider_id === '') {
            return [];
        }
        $logs = get_option(self::OPTION, []);
        $logs = is_array($logs) ? $logs : [];
        $filtered = array_values(array_filter($logs, function ($row) use ($provider_id) {
            $ctx = is_array($row['context'] ?? null) ? $row['context'] : [];
            return ($ctx['provider'] ?? '') === $provider_id;
        }));
        return array_slice($filtered, 0, $limit);
    }
}
