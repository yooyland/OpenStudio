<?php
if (!defined('ABSPATH')) exit;

/**
 * Detects provider billing / auth / rate-limit failures that must not stay in running state.
 */
final class YooY_Provider_Billing_Error {

    private const PATTERNS = [
        'insufficient credit',
        'payment required',
        'billing',
        'unauthorized',
        'invalid api token',
        'rate limit',
    ];

    public static function matches(string $text): bool {
        $text = strtolower(trim($text));
        if ($text === '') {
            return false;
        }
        foreach (self::PATTERNS as $pattern) {
            if (strpos($text, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function detect_from_body(array $body): ?string {
        $parts = [];
        foreach (['title', 'error', 'detail', 'message', 'failure', 'failureReason'] as $key) {
            if (!empty($body[$key]) && is_string($body[$key])) {
                $parts[] = $body[$key];
            }
        }
        if (!empty($body['errors']) && is_array($body['errors'])) {
            foreach ($body['errors'] as $err) {
                if (is_string($err)) {
                    $parts[] = $err;
                } elseif (is_array($err)) {
                    foreach (['title', 'message', 'detail', 'error'] as $k) {
                        if (!empty($err[$k]) && is_string($err[$k])) {
                            $parts[] = $err[$k];
                        }
                    }
                }
            }
        }

        foreach ($parts as $part) {
            if (self::matches($part)) {
                return sanitize_text_field($part);
            }
        }
        return null;
    }

    public static function detect_from_job(array $job): ?string {
        $raw = is_array($job['raw'] ?? null) ? $job['raw'] : [];
        $from_raw = self::detect_from_body($raw);
        if ($from_raw !== null) {
            return $from_raw;
        }
        if (!empty($job['error']) && is_string($job['error']) && self::matches($job['error'])) {
            return sanitize_text_field($job['error']);
        }
        return null;
    }

    public static function is_replicate_billing_failure(array $job): bool {
        $provider = strtolower((string) ($job['provider_used'] ?? $job['provider'] ?? ''));
        $catalog  = strtolower((string) ($job['catalog_provider'] ?? ''));
        if ($provider !== 'replicate' && $catalog !== 'replicate' && $catalog !== 'flux') {
            return false;
        }
        return self::detect_from_job($job) !== null;
    }

    public static function replicate_insufficient_message(): string {
        return 'Replicate API account credit is insufficient. Please check provider billing.';
    }

    public static function provider_failure_lines(string $provider_label = 'Replicate'): array {
        return [
            'primary' => $provider_label . ' API 계정의 크레딧이 부족합니다.',
            'user_credits_ok' => 'YooY 사용자 크레딧과는 별도입니다.',
        ];
    }

    public static function user_message_fallback(): string {
        return 'AI 공급업체 API 계정의 크레딧이 부족합니다. 다른 공급업체로 다시 시도합니다. (YooY 사용자 크레딧과는 별도입니다.)';
    }

    public static function user_message_failed(string $provider_label = 'Replicate'): string {
        $lines = self::provider_failure_lines($provider_label);
        return $lines['primary'] . ' ' . $lines['user_credits_ok'];
    }
}
