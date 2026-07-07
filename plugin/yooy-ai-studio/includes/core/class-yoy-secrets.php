<?php
if (!defined('ABSPATH')) exit;

final class YooY_Secrets {

    private const PREFIX = 'yoyenc:';

    public static function encrypt(string $value): string {
        if ($value === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($value);
        }
        $key = hash('sha256', wp_salt('auth'), true);
        $iv  = openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($enc === false) {
            return base64_encode($value);
        }
        return base64_encode($iv . $enc);
    }

    public static function decrypt(string $stored): string {
        if ($stored === '') {
            return '';
        }
        if (strpos($stored, self::PREFIX) === 0) {
            $stored = substr($stored, strlen(self::PREFIX));
        }
        $raw = base64_decode($stored, true);
        if ($raw === false) {
            return '';
        }
        if (!function_exists('openssl_decrypt') || strlen($raw) < 17) {
            return (string) base64_decode($stored, true);
        }
        $iv  = substr($raw, 0, 16);
        $enc = substr($raw, 16);
        $key = hash('sha256', wp_salt('auth'), true);
        $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $dec !== false ? $dec : '';
    }

    public static function get_api_key(string $option_key): string {
        $stored = get_option($option_key, '');
        if ($stored === '' || !is_string($stored)) {
            return '';
        }
        if (strpos($stored, self::PREFIX) === 0) {
            return self::decrypt($stored);
        }
        return $stored;
    }

    public static function set_api_key(string $option_key, string $value): void {
        $value = trim($value);
        if ($value === '') {
            delete_option($option_key);
            return;
        }
        update_option($option_key, self::PREFIX . self::encrypt($value), false);
    }

    public static function has_api_key(string $option_key): bool {
        return self::get_api_key($option_key) !== '';
    }

    public static function mask_key(string $value): string {
        if ($value === '') {
            return '';
        }
        $len = strlen($value);
        if ($len <= 4) {
            return '••••';
        }
        return str_repeat('*', 8) . substr($value, -4);
    }
}
