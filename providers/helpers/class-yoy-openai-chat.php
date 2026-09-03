<?php
if (!defined('ABSPATH')) exit;

/**
 * Shared OpenAI Chat Completions helper (Translator / Import / Writing).
 * Not a new Provider architecture — thin HTTP wrapper around existing yoy_openai_api_key.
 */
final class YooY_OpenAI_Chat {

    const DEFAULT_MODEL = 'gpt-4o-mini';
    const TIMEOUT       = 90;

    /**
     * @param array $messages OpenAI messages [{role, content}, ...]
     * @param array $opts Optional: model, temperature, timeout
     * @return array{content:string,model:string,provider:string,request_id:string}
     * @throws Exception On missing key, HTTP failure, or empty content
     */
    public static function complete(array $messages, array $opts = []): array {
        if (!class_exists('YooY_Secrets')) {
            throw new Exception('Secrets service unavailable.');
        }
        $api_key = YooY_Secrets::get_api_key('yoy_openai_api_key');
        if ($api_key === '') {
            throw new Exception('openai_key_missing');
        }

        $model = sanitize_text_field((string) ($opts['model'] ?? self::DEFAULT_MODEL));
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }
        $temperature = isset($opts['temperature']) ? (float) $opts['temperature'] : 0.7;
        $timeout = isset($opts['timeout']) ? (int) $opts['timeout'] : self::TIMEOUT;
        if ($timeout < 15) {
            $timeout = 15;
        }

        $clean_messages = [];
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $role = sanitize_text_field((string) ($msg['role'] ?? 'user'));
            $content = (string) ($msg['content'] ?? '');
            if ($content === '') {
                continue;
            }
            $clean_messages[] = [
                'role'    => $role,
                'content' => $content,
            ];
        }
        if (empty($clean_messages)) {
            throw new Exception('Empty chat messages.');
        }

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => $model,
                'temperature' => $temperature,
                'messages'    => $clean_messages,
            ]),
        ]);

        if (is_wp_error($response)) {
            if (class_exists('YooY_System_Log')) {
                YooY_System_Log::write('error', 'OpenAI chat request failed', [
                    'provider' => 'openai',
                    'error'    => $response->get_error_message(),
                    'code'     => $response->get_error_code(),
                ]);
            }
            throw new Exception('openai_http_error');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);
        $request_id = '';
        if (is_array($data) && !empty($data['id'])) {
            $request_id = (string) $data['id'];
        }

        if ($status < 200 || $status >= 300 || !is_array($data)) {
            if (class_exists('YooY_System_Log')) {
                YooY_System_Log::write('error', 'OpenAI chat HTTP error', [
                    'provider'   => 'openai',
                    'http'       => $status,
                    'request_id' => $request_id,
                ]);
            }
            throw new Exception('openai_http_' . $status);
        }

        $content = '';
        if (isset($data['choices'][0]['message']['content'])) {
            $content = trim((string) $data['choices'][0]['message']['content']);
        }
        if ($content === '') {
            if (class_exists('YooY_System_Log')) {
                YooY_System_Log::write('error', 'OpenAI chat empty content', [
                    'provider'   => 'openai',
                    'request_id' => $request_id,
                ]);
            }
            throw new Exception('openai_empty_result');
        }

        $used_model = sanitize_text_field((string) ($data['model'] ?? $model));

        return [
            'content'    => $content,
            'model'      => $used_model !== '' ? $used_model : $model,
            'provider'   => 'openai',
            'request_id' => $request_id,
        ];
    }

    public static function is_configured(): bool {
        if (!class_exists('YooY_Secrets')) {
            return false;
        }
        return YooY_Secrets::get_api_key('yoy_openai_api_key') !== '';
    }

    /**
     * Dev/test only: WP_DEBUG or YOOY_DEBUG with explicit mock provider.
     */
    public static function allow_mock_fallback(): bool {
        $debug = (defined('YOOY_DEBUG') && YOOY_DEBUG) || (defined('WP_DEBUG') && WP_DEBUG);
        return (bool) $debug;
    }
}
