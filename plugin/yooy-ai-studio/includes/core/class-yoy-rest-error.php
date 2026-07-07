<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalizes REST error responses into structured JSON diagnostics.
 */
final class YooY_Rest_Error {

    public static function register(): void {
        add_filter('rest_post_dispatch', [self::class, 'enrich_response'], 20, 3);
    }

    /**
     * @param mixed $response
     * @return mixed
     */
    public static function enrich_response($response, $server, $request) {
        if (!($response instanceof WP_REST_Response)) {
            return $response;
        }

        $status = (int) $response->get_status();
        if ($status < 400) {
            return $response;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            $data = ['message' => is_scalar($data) ? (string) $data : 'Request failed.'];
        }

        if (!empty($data['stage']) && !empty($data['message'])) {
            return $response;
        }

        $response->set_data(self::normalize_wp_data($data, $status));
        return $response;
    }

    public static function format(array $detail): array {
        $body = array_merge([
            'success'            => false,
            'stage'              => 'unknown',
            'code'               => 'error',
            'message'            => 'An error occurred.',
            'provider_requested' => null,
            'provider_resolved'  => null,
            'reason'             => '',
            'missing_fields'     => [],
        ], $detail);

        if (isset($body['missing_fields']) && !is_array($body['missing_fields'])) {
            $body['missing_fields'] = [];
        }

        if (!empty($detail['debug']) && current_user_can('manage_options')) {
            $body['debug'] = $detail['debug'];
        }

        return $body;
    }

    private static function normalize_wp_data(array $data, int $status): array {
        $message = '';
        if (!empty($data['message']) && is_string($data['message'])) {
            $message = $data['message'];
        } elseif (!empty($data['error']) && is_string($data['error'])) {
            $message = $data['error'];
        } elseif (!empty($data['error']['message'])) {
            $message = (string) $data['error']['message'];
        }

        $code = !empty($data['code']) ? (string) $data['code'] : 'request_failed';
        $stage = !empty($data['stage']) ? (string) $data['stage'] : self::infer_stage($code, $message, $status);

        return self::format([
            'stage'              => $stage,
            'code'               => $code,
            'message'            => $message !== '' ? $message : 'Request failed.',
            'provider_requested' => $data['provider_requested'] ?? null,
            'provider_resolved'  => $data['provider_resolved'] ?? null,
            'reason'             => $data['reason'] ?? $code,
            'missing_fields'     => $data['missing_fields'] ?? [],
            'module'             => $data['module'] ?? null,
            'debug'              => current_user_can('manage_options') ? [
                'http_status' => $status,
                'raw'         => $data,
            ] : null,
        ]);
    }

    private static function infer_stage(string $code, string $message, int $status): string {
        if ($status === 401) {
            return 'authentication';
        }
        if (strpos($code, 'rest_') === 0) {
            return 'request_validation';
        }
        $lower = strtolower($message);
        if (strpos($lower, 'credit') !== false || strpos($lower, 'billing') !== false) {
            return 'credit_check';
        }
        if (strpos($lower, 'provider') !== false || strpos($lower, 'api key') !== false) {
            return 'provider_validation';
        }
        if (strpos($lower, 'prompt') !== false) {
            return 'request_validation';
        }
        return 'server_error';
    }
}
