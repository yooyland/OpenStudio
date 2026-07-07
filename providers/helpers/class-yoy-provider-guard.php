<?php
if (!defined('ABSPATH')) exit;

final class YooY_Provider_Guard {

    public static function require_key(string $label, string $api_key, array $params): void {
        if (!empty($params['strict_provider']) && $api_key === '') {
            throw new Exception($label . ' API key is not configured.');
        }
    }
}
