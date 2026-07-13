<?php
if (!defined('ABSPATH')) exit;

/**
 * Resolves which AI provider to use per studio using priority rules.
 */
final class YooY_Provider_Resolver {

    private const REGISTRY_OPTION = 'yoy_provider_registry';
    private const MODES_OPTION    = 'yoy_provider_modes';
    private const DEFAULTS_OPTION = 'yoy_studio_default_providers';

    public static function resolve(string $studio, array $params, int $user_id = 0): array {
        $requested = self::normalize_requested($params);

        if ($requested === 'mock' || strpos($requested, 'mock-') === 0) {
            $catalog = ($requested === 'mock' && $studio === 'image') ? 'mock-image' : $requested;
            return self::build('mock', $requested, null, null, false, $studio, $catalog);
        }

        if ($requested !== '' && $requested !== 'auto') {
            $eval = self::evaluate($requested, $studio);
            if ($eval['usable']) {
                return self::build(self::route_provider_id($requested, $studio), $requested, null, null, true, $studio, $requested);
            }
            if (!empty($eval['billing_blocked'])) {
                self::throw_provider_error($requested, $studio, $eval, 'credit_check', 'billing_blocked');
            }
            self::throw_provider_error($requested, $studio, $eval, 'provider_resolver', 'provider_unavailable');
        }

        $is_auto = ($requested === '' || $requested === 'auto');

        $best = self::best_live_for_studio($studio);
        if ($best) {
            return self::build(
                self::route_provider_id($best['id'], $studio),
                $requested ?: 'auto',
                null,
                null,
                !$is_auto,
                $studio,
                $best['id']
            );
        }

        $configured = self::best_configured_unsupported_for_studio($studio);
        if ($configured) {
            return self::build(
                self::route_provider_id($configured['id'], $studio),
                $requested ?: 'auto',
                null,
                null,
                !$is_auto,
                $studio,
                $configured['id']
            );
        }

        $reason = self::fallback_reason($studio, self::admin_default_for_studio($studio), $best);
        return self::build('mock', $requested ?: 'auto', $reason, self::fallback_warning($reason), false, $studio, $studio === 'image' ? 'mock-image' : 'mock');
    }

    /** Mark provider billing failure and disable auto routing until resolved. */
    public static function mark_billing_error(string $provider_id, string $message): void {
        $message = sanitize_text_field($message);
        if ($message === '') {
            $message = 'Insufficient credit';
        }

        $targets = [$provider_id];
        if ($provider_id === 'replicate') {
            $targets[] = 'flux';
        } elseif ($provider_id === 'flux') {
            $targets[] = 'replicate';
        }

        $registry = self::get_registry();
        foreach (array_unique($targets) as $id) {
            $state = self::get_provider_state($id);
            $state['billing_status'] = 'blocked';
            $state['auto_routing_disabled'] = true;
            $state['last_test_error'] = $message;
            $state['last_test_error_type'] = 'billing_error';
            $state['last_test_status'] = 'failed';
            $state['active'] = false;
            $state['success_rate'] = 0;
            $registry[$id] = $state;
        }
        update_option(self::REGISTRY_OPTION, $registry, false);
    }

    public static function is_auto_excluded(string $provider_id): bool {
        $state = self::get_provider_state($provider_id);
        if (!empty($state['auto_routing_disabled'])) {
            return true;
        }
        if (($state['billing_status'] ?? '') === 'blocked') {
            return true;
        }
        $error = (string) ($state['last_test_error'] ?? '');
        if ($error !== '' && class_exists('YooY_Provider_Billing_Error') && YooY_Provider_Billing_Error::matches($error)) {
            return true;
        }
        if (in_array($provider_id, ['replicate', 'flux'], true) && ($state['last_test_status'] ?? '') === 'failed') {
            return true;
        }
        return false;
    }

    public static function is_openai_image_ready(): bool {
        $eval = self::evaluate('openai', 'image');
        return !empty($eval['usable']) && !empty($eval['is_live']);
    }

    public static function apply(array &$payload, string $studio, int $user_id = 0): array {
        $requested = self::normalize_requested($payload);
        if (($requested === '' || $requested === 'auto') && class_exists('YooY_Studio_Model_Resolver')) {
            $model_req = sanitize_text_field((string) ($payload['model'] ?? $payload['default_model'] ?? ''));
            if ($model_req === '' || YooY_Studio_Model_Resolver::is_mock_model($model_req)) {
                unset($payload['model'], $payload['default_model']);
            }
        }

        $resolution = self::resolve($studio, $payload, $user_id);
        $payload['provider']            = $resolution['provider'];
        $payload['requested_provider']  = $resolution['requested_provider'];
        $payload['strict_provider']     = !empty($resolution['strict']);
        $payload['provider_resolution'] = $resolution;

        if (class_exists('YooY_Studio_Model_Resolver') && in_array($studio, ['image', 'video', 'music', 'voice', 'avatar'], true)) {
            $catalog = (string) ($resolution['catalog_provider'] ?? $resolution['requested_provider'] ?? '');
            $model_resolution = YooY_Studio_Model_Resolver::resolve(
                $studio,
                (string) $resolution['provider'],
                $catalog,
                (string) ($payload['model'] ?? $payload['default_model'] ?? '')
            );
            $payload['model'] = $model_resolution['model'];
            $resolution['model'] = $model_resolution['model'];
            $resolution['model_requested'] = $model_resolution['requested'];
            $resolution['model_corrected'] = !empty($model_resolution['corrected']);
            $resolution['mode'] = !empty($resolution['is_mock']) ? 'mock' : 'real';
            $payload['provider_resolution'] = $resolution;
            YooY_Studio_Model_Resolver::validate(
                $studio,
                (string) $resolution['provider'],
                $catalog,
                (string) $payload['model']
            );
        }

        if ($studio === 'image' && class_exists('YooY_Image_Size_Resolver')) {
            $catalog = (string) ($resolution['catalog_provider'] ?? $resolution['requested_provider'] ?? '');
            $size_resolution = YooY_Image_Size_Resolver::resolve(
                (string) $resolution['provider'],
                $catalog,
                (string) ($payload['model'] ?? ''),
                (string) ($payload['aspect_ratio'] ?? '1:1'),
                (string) ($payload['resolution'] ?? '1024'),
                (string) ($payload['size'] ?? '')
            );
            $payload['size'] = $size_resolution['size'];
            $payload['size_original'] = $size_resolution['original'];
            $resolution['size'] = $size_resolution['size'];
            $resolution['size_requested'] = $size_resolution['requested'];
            $resolution['size_corrected'] = !empty($size_resolution['corrected']);
            $resolution['size_original'] = $size_resolution['original'];
            $payload['provider_resolution'] = $resolution;
            YooY_Image_Size_Resolver::validate(
                (string) $resolution['provider'],
                $catalog,
                (string) ($payload['model'] ?? ''),
                (string) $payload['size']
            );
        }

        return $resolution;
    }

    public static function annotate(array $result, array $resolution): array {
        $result['provider']           = $resolution['provider'];
        $result['provider_used']      = $resolution['provider'];
        $result['requested_provider'] = $resolution['requested_provider'];
        if (!empty($resolution['model'])) {
            $result['model'] = $resolution['model'];
        }
        if (!empty($resolution['size'])) {
            $result['size'] = $resolution['size'];
        }
        if (!empty($resolution['catalog_provider'])) {
            $result['catalog_provider'] = $resolution['catalog_provider'];
        }
        if (!empty($resolution['fallback_reason'])) {
            $result['fallback_reason'] = $resolution['fallback_reason'];
        }
        if (!empty($resolution['warning'])) {
            $result['warning'] = $resolution['warning'];
        }
        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        $meta['provider_resolution'] = $resolution;
        $result['meta'] = $meta;
        return $result;
    }

    public static function get_registry(): array {
        $stored = get_option(self::REGISTRY_OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    public static function get_provider_state(string $provider_id): array {
        $registry = self::get_registry();
        $state    = is_array($registry[$provider_id] ?? null) ? $registry[$provider_id] : [];
        return array_merge([
            'enabled'          => true,
            'last_test_status' => 'not_tested',
            'last_test_at'     => '',
            'last_test_ms'     => 0,
            'last_test_error'  => '',
            'last_test_error_type' => '',
            'billing_status'   => 'unknown',
            'auto_routing_disabled' => false,
            'priority'         => 50,
            'success_rate'     => null,
        ], $state);
    }

    public static function save_provider_state(string $provider_id, array $data): array {
        $registry = self::get_registry();
        $current  = self::get_provider_state($provider_id);

        if (array_key_exists('enabled', $data)) {
            $current['enabled'] = !empty($data['enabled']);
        }
        if (array_key_exists('priority', $data)) {
            $current['priority'] = max(0, min(1000, (int) $data['priority']));
        }
        if (array_key_exists('billing_status', $data)) {
            $status = sanitize_text_field($data['billing_status']);
            if (in_array($status, ['unknown', 'available', 'blocked'], true)) {
                $current['billing_status'] = $status;
            }
        }
        if (array_key_exists('model', $data)) {
            $current['model'] = sanitize_text_field((string) $data['model']);
        }
        if (array_key_exists('active', $data)) {
            $current['active'] = !empty($data['active']);
        }

        $registry[$provider_id] = $current;
        update_option(self::REGISTRY_OPTION, $registry, false);
        return $current;
    }

    public static function set_test_result(string $provider_id, bool $passed, array $extra = []): void {
        $registry = self::get_registry();
        $state    = self::get_provider_state($provider_id);
        $status   = isset($extra['status'])
            ? sanitize_text_field((string) $extra['status'])
            : ($passed ? 'passed' : 'failed');
        if (!in_array($status, ['passed', 'failed', 'unsupported', 'not_tested'], true)) {
            $status = $passed ? 'passed' : 'failed';
        }

        $state['last_test_status'] = $status;
        $state['last_test_at']     = gmdate('c');
        if (isset($extra['latency_ms'])) {
            $state['last_test_ms'] = max(0, (int) $extra['latency_ms']);
        }
        if (isset($extra['error'])) {
            $state['last_test_error'] = sanitize_text_field((string) $extra['error']);
        }
        if (isset($extra['error_type'])) {
            $state['last_test_error_type'] = sanitize_text_field((string) $extra['error_type']);
        }
        if (isset($extra['raw_summary'])) {
            $state['last_test_raw_summary'] = sanitize_text_field((string) $extra['raw_summary']);
        }
        $state['success_rate'] = $status === 'passed' ? 99 : 0;
        if ($status === 'passed') {
            $state['active'] = true;
            $state['enabled'] = true;
            $state['last_test_error'] = '';
            $state['last_test_error_type'] = '';
        } elseif ($status === 'unsupported') {
            $state['active'] = false;
        } else {
            $state['active'] = false;
        }
        $registry[$provider_id] = $state;
        update_option(self::REGISTRY_OPTION, $registry, false);

        if ($status === 'passed') {
            self::activate_provider($provider_id);
        }
    }

    /** Mark provider live and set as studio default after successful connection test. */
    public static function activate_provider(string $provider_id): void {
        if (!class_exists('YooY_Admin_Providers')) {
            return;
        }
        $catalog = YooY_Admin_Providers::catalog();
        if (!isset($catalog[$provider_id])) {
            return;
        }
        if (($catalog[$provider_id]['impl'] ?? '') === 'mock') {
            return;
        }

        $modes = get_option(self::MODES_OPTION, []);
        $modes = is_array($modes) ? $modes : [];
        $modes[$provider_id] = 'real';
        update_option(self::MODES_OPTION, $modes, false);

        self::save_provider_state($provider_id, [
            'enabled' => true,
            'active'  => true,
        ]);
    }

    public static function clear_studio_default(string $studio): void {
        $studio = sanitize_text_field($studio);
        $defaults = get_option(self::DEFAULTS_OPTION, []);
        $defaults = is_array($defaults) ? $defaults : [];
        if (!isset($defaults[$studio])) {
            return;
        }
        unset($defaults[$studio]);
        update_option(self::DEFAULTS_OPTION, $defaults, false);
    }

    public static function set_studio_default(string $studio, string $provider_id): array {
        $studio = sanitize_text_field($studio);
        if (!in_array($studio, ['image', 'video', 'music', 'voice', 'avatar', 'writing', 'translation'], true)) {
            throw new Exception('Invalid studio type.');
        }

        $defaults = get_option(self::DEFAULTS_OPTION, []);
        $defaults = is_array($defaults) ? $defaults : [];
        $defaults[$studio] = sanitize_text_field($provider_id);
        update_option(self::DEFAULTS_OPTION, $defaults, false);
        return $defaults;
    }

    public static function admin_default_for_studio(string $studio): string {
        $defaults = get_option(self::DEFAULTS_OPTION, []);
        if (!is_array($defaults)) {
            return '';
        }
        return sanitize_text_field($defaults[$studio] ?? '');
    }

    public static function studio_defaults(): array {
        $defaults = get_option(self::DEFAULTS_OPTION, []);
        return is_array($defaults) ? $defaults : [];
    }

    public static function evaluate(string $provider_id, string $studio): array {
        if ($provider_id === 'mock' || strpos($provider_id, 'mock-') === 0) {
            return ['usable' => true, 'is_live' => false, 'message' => '', 'error_code' => ''];
        }

        if (!class_exists('YooY_Admin_Providers')) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Provider catalog unavailable.',
                'error_code' => 'catalog_unavailable',
            ];
        }

        $catalog = YooY_Admin_Providers::catalog();
        if (!isset($catalog[$provider_id])) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Unknown provider "' . $provider_id . '".',
                'error_code' => 'provider_unknown',
            ];
        }

        $meta = $catalog[$provider_id];
        if (($meta['impl'] ?? '') === 'mock') {
            return ['usable' => true, 'is_live' => false, 'message' => '', 'error_code' => ''];
        }
        if (array_key_exists('real_impl', $meta) && $meta['real_impl'] === false) {
            $name = (string) ($meta['name'] ?? $provider_id);
            $message = $provider_id === 'runway'
                ? 'Runway real generation is not implemented/configured yet.'
                : $name . ' real generation is not implemented/configured yet.';
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => $message,
                'error_code' => 'bridge_unimplemented',
            ];
        }
        if (!in_array($studio, $meta['studios'], true)) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Provider does not support this studio.',
                'error_code' => 'provider_studio_mismatch',
            ];
        }

        $state = self::get_provider_state($provider_id);
        if (empty($state['enabled'])) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Provider is disabled.',
                'error_code' => 'provider_disabled',
            ];
        }

        $mode = YooY_Admin_Providers::effective_mode($provider_id);
        if ($mode === 'disabled') {
            return [
                'usable'     => false,
                'is_live'    => false,
                'message'    => 'Provider is disabled.',
                'error_code' => 'provider_disabled',
            ];
        }
        if ($mode === 'mock') {
            return [
                'usable'     => false,
                'is_live'    => false,
                'message'    => 'Provider is set to Mock mode.',
                'error_code' => 'provider_in_mock_mode',
            ];
        }

        $has_key = ($meta['option'] ?? '') !== '' && YooY_Secrets::has_api_key($meta['option']);
        if (!$has_key) {
            $name = (string) ($meta['name'] ?? $provider_id);
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => $name . ' API key is missing.',
                'error_code' => 'provider_not_configured',
            ];
        }

        $test_status = $state['last_test_status'] ?? 'not_tested';
        if ($test_status === 'failed') {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Last connection test failed for ' . ($meta['name'] ?? $provider_id) . '.',
                'error_code' => 'provider_test_failed',
            ];
        }
        if ($test_status === 'unsupported') {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => (string) ($state['last_test_error'] ?? 'Automated connection test is not supported for this provider.'),
                'error_code' => 'provider_test_unsupported',
            ];
        }
        if ($test_status !== 'passed') {
            $name = (string) ($meta['name'] ?? $provider_id);
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => $name . ' must pass Test Connection before use.',
                'error_code' => 'provider_not_tested',
            ];
        }

        $billing = $state['billing_status'] ?? 'unknown';
        if ($billing === 'blocked') {
            return [
                'usable'          => false,
                'is_live'         => true,
                'billing_blocked' => true,
                'message'         => 'Provider billing is blocked.',
                'error_code'      => 'billing_blocked',
            ];
        }

        return [
            'usable'     => true,
            'is_live'    => true,
            'priority'   => (int) ($state['priority'] ?? 50),
            'message'    => '',
            'error_code' => '',
        ];
    }

    public static function is_usable(string $provider_id, string $studio): bool {
        $eval = self::evaluate($provider_id, $studio);
        return !empty($eval['usable']);
    }

    /** Auto routing eligibility: tier 1 = test passed live, tier 2 = configured but test unsupported. */
    public static function evaluate_for_auto(string $provider_id, string $studio): array {
        if (self::is_auto_excluded($provider_id)) {
            return ['tier' => 0, 'usable' => false, 'priority' => 0];
        }

        $tier1 = self::evaluate($provider_id, $studio);
        if (!empty($tier1['usable']) && !empty($tier1['is_live'])) {
            return [
                'tier'     => 1,
                'usable'   => true,
                'priority' => (int) ($tier1['priority'] ?? 50),
            ];
        }

        $tier2 = self::evaluate_configured_unsupported($provider_id, $studio);
        if (!empty($tier2['usable'])) {
            return [
                'tier'     => 2,
                'usable'   => true,
                'priority' => (int) ($tier2['priority'] ?? 50),
            ];
        }

        return ['tier' => 0, 'usable' => false, 'priority' => 0];
    }

    public static function is_auto_eligible(string $provider_id, string $studio): bool {
        $eval = self::evaluate_for_auto($provider_id, $studio);
        return !empty($eval['usable']);
    }

    public static function enrich_provider_row(array $row): array {
        $state = self::get_provider_state($row['id']);
        $eval_image = self::evaluate($row['id'], ($row['studios'][0] ?? 'image'));
        $defaults = self::studio_defaults();
        $studio_map = [];
        foreach (($row['studios'] ?? []) as $studio) {
            $studio_map[$studio] = (($defaults[$studio] ?? '') === $row['id']);
        }

        $mode = $row['mode'] ?? 'auto';
        $mode_label = $mode === 'mock' ? 'Mock' : ($mode === 'real' ? 'Live' : 'Auto');

        $test_map = [
            'passed'      => 'Passed',
            'failed'      => 'Failed',
            'unsupported' => 'Unsupported',
            'not_tested'  => 'Not tested',
        ];
        $billing_map = [
            'unknown'   => 'Unknown',
            'available' => 'Available',
            'blocked'   => 'Blocked',
        ];

        $warning = '';
        if (!empty($row['has_key']) && !$eval_image['usable'] && empty($eval_image['billing_blocked'])) {
            $warning = 'Provider is registered but not usable: ' . ($eval_image['message'] ?? 'check configuration.');
        } elseif (!empty($eval_image['billing_blocked']) || !empty($state['auto_routing_disabled'])) {
            $warning = 'Provider API account billing issue. This is separate from YooY user credits.';
        }

        $routing_status = self::routing_status($row['id'], $row, $eval_image, $studio_map, $defaults);
        $billing_display = self::provider_billing_display($row, $state, $eval_image);

        if (!empty($state['model'])) {
            $row['model'] = (string) $state['model'];
        }

        return array_merge($row, [
            'enabled'          => !empty($state['enabled']),
            'active'           => !empty($state['active']),
            'priority'         => (int) ($state['priority'] ?? 50),
            'last_test_status' => $state['last_test_status'] ?? 'not_tested',
            'last_test_at'     => $state['last_test_at'] ?? '',
            'last_test_ms'     => (int) ($state['last_test_ms'] ?? 0),
            'last_test_error'  => $state['last_test_error'] ?? '',
            'last_test_error_type' => $state['last_test_error_type'] ?? '',
            'last_test_raw_summary' => $state['last_test_raw_summary'] ?? '',
            'success_rate'     => $state['success_rate'] ?? null,
            'last_test_label'  => $test_map[$state['last_test_status'] ?? 'not_tested'] ?? 'Not tested',
            'billing_status'   => $state['billing_status'] ?? 'unknown',
            'billing_label'    => $billing_map[$state['billing_status'] ?? 'unknown'] ?? 'Unknown',
            'auto_routing_disabled' => !empty($state['auto_routing_disabled']),
            'mode_label'       => $mode_label,
            'supports'         => $row['studios'] ?? [],
            'studio_defaults'  => $studio_map,
            'usable'           => !empty($eval_image['usable']),
            'routing_status'   => $routing_status['code'],
            'routing_label'    => $routing_status['label'],
            'routing_label_ko' => $routing_status['label_ko'],
            'configured'       => !empty($row['has_key']),
            'warning'          => $warning,
            'provider_billing_status' => $billing_display['billing'],
            'provider_api_status'     => $billing_display['api'],
            'provider_test_status'    => $billing_display['test'],
            'provider_billing_tone'   => $billing_display['billing_tone'],
            'provider_api_tone'       => $billing_display['api_tone'],
            'provider_test_tone'      => $billing_display['test_tone'],
        ]);
    }

    /** Provider API billing / key / test — separate from YooY user credits. */
    private static function provider_billing_display(array $row, array $state, array $eval): array {
        $is_mock = (($row['impl'] ?? '') === 'mock') || (strpos((string) ($row['id'] ?? ''), 'mock-') === 0);
        if ($is_mock) {
            return [
                'billing' => 'N/A (Mock)',
                'api'     => 'N/A',
                'test'    => 'N/A',
                'billing_tone' => 'muted',
                'api_tone'     => 'muted',
                'test_tone'    => 'muted',
            ];
        }

        $billing = 'Unknown';
        $billing_tone = 'pending';
        $billing_error = (string) ($state['last_test_error'] ?? '');
        $billing_blocked = !empty($state['auto_routing_disabled']) || ($state['billing_status'] ?? '') === 'blocked';

        if ($billing_blocked) {
            $billing_tone = 'error';
            if ($billing_error !== '' && class_exists('YooY_Provider_Billing_Error') && stripos($billing_error, 'insufficient') !== false) {
                $billing = 'Insufficient Credit';
            } elseif ($billing_error !== '' && class_exists('YooY_Provider_Billing_Error') && YooY_Provider_Billing_Error::matches($billing_error)) {
                $billing = 'Billing Error';
            } elseif (($state['billing_status'] ?? '') === 'blocked') {
                $billing = 'Blocked';
            } else {
                $billing = 'Billing Error';
            }
        } elseif (($state['billing_status'] ?? '') === 'available' || !empty($eval['usable'])) {
            $billing = 'Connected';
            $billing_tone = 'connected';
        }

        $api = !empty($row['has_key']) ? 'Valid' : 'Missing';
        $api_tone = !empty($row['has_key']) ? 'connected' : 'error';
        if (!empty($row['has_key']) && ($state['last_test_error_type'] ?? '') === 'auth_error') {
            $api = 'Invalid';
            $api_tone = 'error';
        }

        $test_status = $state['last_test_status'] ?? 'not_tested';
        $test_map = [
            'passed'      => 'Passed',
            'failed'      => 'Failed',
            'unsupported' => 'Unsupported',
            'not_tested'  => 'Not Tested',
        ];
        $test = $test_map[$test_status] ?? 'Not Tested';
        $test_tone = $test_status === 'passed' ? 'connected' : ($test_status === 'failed' ? 'error' : 'pending');

        return [
            'billing' => $billing,
            'api'     => $api,
            'test'    => $test,
            'billing_tone' => $billing_tone,
            'api_tone'     => $api_tone,
            'test_tone'    => $test_tone,
        ];
    }

    private static function best_live_for_studio(string $studio): ?array {
        return self::best_auto_candidate_for_studio($studio, 1);
    }

    private static function best_configured_unsupported_for_studio(string $studio): ?array {
        return self::best_auto_candidate_for_studio($studio, 2);
    }

    private static function best_auto_candidate_for_studio(string $studio, int $tier): ?array {
        if (!class_exists('YooY_Admin_Providers')) {
            return null;
        }

        $candidates = [];
        foreach (YooY_Admin_Providers::catalog() as $id => $meta) {
            if (!in_array($studio, $meta['studios'], true)) {
                continue;
            }
            $eval = self::evaluate_for_auto($id, $studio);
            if (empty($eval['usable']) || (int) ($eval['tier'] ?? 0) !== $tier) {
                continue;
            }
            $candidates[] = [
                'id'       => $id,
                'priority' => (int) ($eval['priority'] ?? 50),
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) use ($studio) {
            $score_a = self::auto_sort_score($a['id'], $studio, (int) $a['priority']);
            $score_b = self::auto_sort_score($b['id'], $studio, (int) $b['priority']);
            return $score_b <=> $score_a;
        });

        return $candidates[0];
    }

    private static function auto_sort_score(string $provider_id, string $studio, int $base_priority): int {
        $score = $base_priority;
        if ($studio === 'image') {
            if ($provider_id === 'openai' && self::is_openai_image_ready()) {
                $score += 500;
            }
            if (in_array($provider_id, ['replicate', 'flux'], true)) {
                $score -= 100;
            }
        }
        $admin_default = self::admin_default_for_studio($studio);
        if ($admin_default !== '' && $provider_id === $admin_default) {
            $score += 50;
        }
        return $score;
    }

    private static function evaluate_configured_unsupported(string $provider_id, string $studio): array {
        if ($provider_id === 'mock' || strpos($provider_id, 'mock-') === 0) {
            return ['usable' => false, 'is_live' => false, 'message' => '', 'error_code' => ''];
        }

        if (!class_exists('YooY_Admin_Providers')) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Provider catalog unavailable.',
                'error_code' => 'catalog_unavailable',
            ];
        }

        $catalog = YooY_Admin_Providers::catalog();
        if (!isset($catalog[$provider_id])) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Unknown provider "' . $provider_id . '".',
                'error_code' => 'provider_unknown',
            ];
        }

        $meta = $catalog[$provider_id];
        if (($meta['impl'] ?? '') === 'mock') {
            return ['usable' => false, 'is_live' => false, 'message' => '', 'error_code' => ''];
        }
        if (array_key_exists('real_impl', $meta) && $meta['real_impl'] === false) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Real generation is not implemented for this provider.',
                'error_code' => 'bridge_unimplemented',
            ];
        }
        if (!in_array($studio, $meta['studios'], true)) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Provider does not support this studio.',
                'error_code' => 'provider_studio_mismatch',
            ];
        }

        $state = self::get_provider_state($provider_id);
        if (empty($state['enabled'])) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Provider is disabled.',
                'error_code' => 'provider_disabled',
            ];
        }

        $mode = YooY_Admin_Providers::effective_mode($provider_id);
        if ($mode === 'disabled' || $mode === 'mock') {
            return [
                'usable'     => false,
                'is_live'    => false,
                'message'    => 'Provider is not in live mode.',
                'error_code' => 'provider_in_mock_mode',
            ];
        }

        $has_key = ($meta['option'] ?? '') !== '' && YooY_Secrets::has_api_key($meta['option']);
        if (!$has_key) {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'API key is missing.',
                'error_code' => 'provider_not_configured',
            ];
        }

        $test_status = $state['last_test_status'] ?? 'not_tested';
        if ($test_status !== 'unsupported') {
            return [
                'usable'     => false,
                'is_live'    => true,
                'message'    => 'Provider is not in configured-but-untested auto tier.',
                'error_code' => $test_status === 'failed' ? 'provider_test_failed' : 'provider_not_tested',
            ];
        }

        $billing = $state['billing_status'] ?? 'unknown';
        if ($billing === 'blocked') {
            return [
                'usable'          => false,
                'is_live'         => true,
                'billing_blocked' => true,
                'message'         => 'Provider billing is blocked.',
                'error_code'      => 'billing_blocked',
            ];
        }

        return [
            'usable'     => true,
            'is_live'    => true,
            'priority'   => (int) ($state['priority'] ?? 50),
            'message'    => '',
            'error_code' => '',
        ];
    }

    private static function normalize_requested(array $params): string {
        $raw = $params['provider'] ?? $params['default_provider'] ?? $params['requested_provider'] ?? 'auto';
        return self::normalize_provider_id((string) $raw);
    }

    private static function normalize_provider_id(string $id): string {
        $id = sanitize_text_field($id);
        $aliases = [
            'openai-image' => 'openai',
            'gpt-image'    => 'openai',
            'dalle'        => 'openai',
            'dall-e'       => 'openai',
        ];
        return isset($aliases[$id]) ? $aliases[$id] : $id;
    }

    private static function throw_provider_error(string $requested, string $studio, array $eval, string $stage, string $fallback_code): void {
        $code = !empty($eval['error_code']) ? (string) $eval['error_code'] : $fallback_code;
        $message = !empty($eval['message']) ? (string) $eval['message'] : 'Selected provider is not available.';

        if (in_array($code, ['provider_not_configured', 'provider_not_tested', 'provider_test_failed', 'provider_in_mock_mode', 'provider_disabled', 'bridge_unimplemented'], true)) {
            $stage = 'provider_validation';
        } elseif ($code === 'billing_blocked' || $stage === 'credit_check') {
            $stage = 'credit_check';
        } else {
            $stage = 'provider_resolver';
        }

        $debug = [
            'studio'   => $studio,
            'evaluate' => $eval,
        ];
        $context = [
            'provider_requested' => $requested,
            'provider_resolved'  => null,
            'reason'             => $code,
            'missing_fields'     => $code === 'provider_not_configured' ? ['api_key'] : [],
            'debug'              => $debug,
        ];
        if ($code === 'provider_not_tested') {
            $context['suggested_actions'] = ['open_operations_center', 'use_auto_mock'];
            if (class_exists('YooY_Admin_Providers')) {
                $catalog = YooY_Admin_Providers::catalog();
                $context['provider_name'] = (string) (($catalog[$requested]['name'] ?? $requested));
            }
        }
        if (class_exists('YooY_Generation_Exception')) {
            throw new YooY_Generation_Exception($stage, $code, $message, $context);
        }
        throw new Exception($message);
    }

    private static function route_provider_id(string $provider_id, string $studio): string {
        if (class_exists('YooY_Provider_Catalog')) {
            return YooY_Provider_Catalog::route_id($provider_id);
        }
        if (strpos($provider_id, 'mock-') === 0) {
            return 'mock';
        }
        if ($provider_id === 'flux' && $studio === 'image') {
            return 'replicate';
        }
        return $provider_id;
    }

    private static function build(string $provider, string $requested, ?string $fallback_reason, ?string $warning, bool $strict, string $studio = '', string $catalog_provider = ''): array {
        if ($catalog_provider === '') {
            $catalog_provider = ($provider === 'mock' && $studio === 'image') ? 'mock-image' : $requested;
        }
        if ($catalog_provider === 'auto' || $catalog_provider === 'mock') {
            $catalog_provider = ($provider === 'mock' && $studio === 'image') ? 'mock-image' : $catalog_provider;
        }

        $model = '';
        if (class_exists('YooY_Studio_Model_Resolver') && in_array($studio, ['image', 'video', 'music', 'voice', 'avatar'], true)) {
            $model = YooY_Studio_Model_Resolver::default_for($studio, $provider, $catalog_provider);
        }

        return [
            'provider'           => $provider,
            'provider_used'      => $provider,
            'catalog_provider'   => $catalog_provider,
            'model'              => $model,
            'requested_provider' => $requested,
            'fallback_reason'    => $fallback_reason,
            'warning'            => $warning,
            'strict'             => $strict,
            'is_mock'            => ($provider === 'mock'),
            'mode'               => ($provider === 'mock') ? 'mock' : 'real',
        ];
    }

    private static function routing_status(string $provider_id, array $row, array $eval, array $studio_map, array $defaults): array {
        $state = self::get_provider_state($provider_id);
        if (!empty($state['auto_routing_disabled']) || ($state['billing_status'] ?? '') === 'blocked') {
            return ['code' => 'billing_error', 'label' => 'Billing Error', 'label_ko' => '결제 오류'];
        }
        if (empty($row['enabled'])) {
            return ['code' => 'disabled', 'label' => 'Disabled', 'label_ko' => '비활성화'];
        }
        if (($eval['error_code'] ?? '') === 'provider_in_mock_mode') {
            return ['code' => 'mock_mode', 'label' => 'Mock mode', 'label_ko' => 'Mock 모드'];
        }
        if (($eval['error_code'] ?? '') === 'bridge_unimplemented') {
            return ['code' => 'bridge_unimplemented', 'label' => 'Needs implementation', 'label_ko' => '구현 필요'];
        }
        if (($eval['error_code'] ?? '') === 'provider_not_tested') {
            return ['code' => 'needs_test', 'label' => 'Needs Test', 'label_ko' => '테스트 필요'];
        }
        if (($eval['error_code'] ?? '') === 'provider_test_failed') {
            return ['code' => 'test_failed', 'label' => 'Test Failed', 'label_ko' => '테스트 실패'];
        }
        if (($eval['error_code'] ?? '') === 'provider_test_unsupported') {
            return ['code' => 'test_unsupported', 'label' => 'Test Unsupported', 'label_ko' => '테스트 미지원'];
        }
        if (!empty($eval['usable'])) {
            $is_default = false;
            foreach ($studio_map as $enabled) {
                if ($enabled) {
                    $is_default = true;
                    break;
                }
            }
            if ($is_default) {
                return ['code' => 'used_by_auto', 'label' => 'Used by Auto', 'label_ko' => 'Auto 사용'];
            }
            return ['code' => 'ready', 'label' => 'Ready', 'label_ko' => '사용 가능'];
        }
        if (empty($row['has_key'])) {
            return ['code' => 'not_configured', 'label' => 'Not configured', 'label_ko' => '미설정'];
        }
        return ['code' => 'not_used', 'label' => 'Not used', 'label_ko' => '미사용'];
    }

    private static function fallback_reason(string $studio, string $admin_default, ?array $best): string {
        if ($best === null && $admin_default === '') {
            return 'no_live_provider_configured';
        }
        if ($best === null) {
            return 'no_tested_live_provider';
        }
        return 'live_provider_unavailable';
    }

    private static function fallback_warning(string $reason): string {
        switch ($reason) {
            case 'no_live_provider_configured':
                return 'No live provider is configured. Using Mock provider.';
            case 'no_tested_live_provider':
                return 'No tested live provider is available. Using Mock provider.';
            default:
                return 'Live provider unavailable. Using Mock provider.';
        }
    }
}
