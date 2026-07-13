<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-translation-provider.php';

/**
 * OpenAI Chat Completions translator.
 * Reuses yoy_openai_api_key (same as Image / Import Engine). Default model: gpt-4o-mini.
 */
final class YooY_OpenAI_Translation_Provider implements YooY_Translation_Provider_Interface {

    const DEFAULT_MODEL = 'gpt-4o-mini';
    const OPTION_MODEL  = 'yoy_translator_openai_model';
    const TIMEOUT       = 60;

    /** @var string */
    private $api_key;

    public function __construct() {
        $this->api_key = class_exists('YooY_Secrets')
            ? YooY_Secrets::get_api_key('yoy_openai_api_key')
            : '';
    }

    public function id(): string {
        return 'openai';
    }

    public function name(): string {
        return 'OpenAI Translator';
    }

    public function models(): array {
        $current = $this->model_id();
        return [
            ['id' => $current, 'name' => $current],
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o mini'],
            ['id' => 'gpt-4o', 'name' => 'GPT-4o'],
        ];
    }

    public function is_configured(): bool {
        return $this->api_key !== '';
    }

    public function translate(array $request): array {
        if ($this->api_key === '') {
            throw new YooY_Translator_Provider_Exception(
                'OpenAI API key is not configured.',
                'openai_key_missing',
                0,
                true
            );
        }

        $text   = isset($request['text']) ? (string) $request['text'] : '';
        $source = isset($request['source_language']) ? (string) $request['source_language'] : 'auto';
        $target = isset($request['target_language']) ? (string) $request['target_language'] : 'en';
        $mode   = isset($request['mode']) ? (string) $request['mode'] : 'natural';
        $context = isset($request['context']) ? (string) $request['context'] : '';

        if (class_exists('YooY_Translator_Validator')) {
            $source = YooY_Translator_Validator::normalize_language_code($source);
            $target = YooY_Translator_Validator::normalize_language_code($target);
        }

        $resolved = isset($request['resolved_source_language'])
            ? (string) $request['resolved_source_language']
            : '';
        if ($resolved === '' && $source !== 'auto' && class_exists('YooY_Translator_Validator')) {
            $resolved = $source;
        }

        $model = $this->model_id();
        $body = [
            'model'       => $model,
            'temperature' => 0.2,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => $this->system_prompt($source, $target, $mode, $resolved),
                ],
                [
                    'role'    => 'user',
                    'content' => $this->user_prompt($text, $source, $target, $mode, $context, $resolved),
                ],
            ],
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $code = $response->get_error_code();
            $internal = ($code === 'http_request_failed' || strpos((string) $code, 'timeout') !== false)
                ? 'openai_timeout'
                : 'openai_http_error';
            throw new YooY_Translator_Provider_Exception(
                'OpenAI request failed.',
                $internal,
                0,
                true,
                ''
            );
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);
        $request_id = '';
        if (is_array($data) && !empty($data['id'])) {
            $request_id = (string) $data['id'];
        }
        $headers = wp_remote_retrieve_headers($response);
        if ($request_id === '' && is_object($headers) && method_exists($headers, 'offsetGet')) {
            $rid = $headers->offsetGet('x-request-id');
            if (is_string($rid)) {
                $request_id = $rid;
            }
        }

        if ($status === 401 || $status === 403 || $status === 429 || $status >= 500 || $status === 0) {
            throw new YooY_Translator_Provider_Exception(
                'OpenAI provider unavailable.',
                'openai_http_' . $status,
                $status,
                true,
                $request_id
            );
        }
        if ($status < 200 || $status >= 300) {
            throw new YooY_Translator_Provider_Exception(
                'OpenAI provider error.',
                'openai_http_' . $status,
                $status,
                true,
                $request_id
            );
        }
        if (!is_array($data)) {
            throw new YooY_Translator_Provider_Exception(
                'Invalid OpenAI response.',
                'openai_json_error',
                $status,
                true,
                $request_id
            );
        }

        $content = '';
        if (isset($data['choices'][0]['message']['content'])) {
            $content = (string) $data['choices'][0]['message']['content'];
        }
        $translated = $this->clean_translation($content);
        if (trim($translated) === '') {
            throw new YooY_Translator_Provider_Exception(
                'Empty OpenAI translation.',
                'openai_empty_result',
                $status,
                true,
                $request_id
            );
        }

        $detected = $resolved !== '' ? $resolved : $source;
        if ($detected === 'auto' && class_exists('YooY_Translator_Validator')) {
            $detected = YooY_Translator_Validator::detect_language($text);
        }
        if (class_exists('YooY_Translator_Validator')) {
            $detected = YooY_Translator_Validator::normalize_language_code($detected);
        }

        $count = class_exists('YooY_Translator_Validator')
            ? YooY_Translator_Validator::char_count($text)
            : strlen($text);

        $usage = isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : [];

        return [
            'success'           => true,
            'translated_text'   => $translated,
            'detected_language' => $detected,
            'provider'          => $this->id(),
            'model'             => $model,
            'character_count'   => $count,
            'credit_cost'       => 0,
            'usage'             => $usage,
            'raw_response'      => [
                'id'     => $request_id,
                'model'  => $model,
                'usage'  => $usage,
                // Never persist full source/API payloads here.
            ],
        ];
    }

    private function model_id(): string {
        $from_option = get_option(self::OPTION_MODEL, '');
        if (is_string($from_option) && $from_option !== '') {
            return sanitize_text_field($from_option);
        }
        return self::DEFAULT_MODEL;
    }

    private function language_name(string $code): string {
        $names = [
            'auto'  => 'Auto-detect',
            'ko'    => 'Korean',
            'en'    => 'English',
            'ja'    => 'Japanese',
            'zh-CN' => 'Simplified Chinese',
            'zh-TW' => 'Traditional Chinese',
            'es'    => 'Spanish',
            'fr'    => 'French',
            'de'    => 'German',
            'it'    => 'Italian',
            'pt'    => 'Portuguese',
            'ru'    => 'Russian',
            'vi'    => 'Vietnamese',
            'th'    => 'Thai',
            'id'    => 'Indonesian',
            'ar'    => 'Arabic',
            'hi'    => 'Hindi',
        ];
        return isset($names[$code]) ? $names[$code] : $code;
    }

    private function mode_instruction(string $mode): string {
        switch ($mode) {
            case 'literal':
                return 'Preserve the original structure and wording as closely as possible (literal).';
            case 'business':
                return 'Use a professional, clear business tone.';
            case 'formal':
                return 'Use a formal, respectful register.';
            case 'casual':
                return 'Use a friendly, natural casual tone.';
            case 'marketing':
                return 'Use a persuasive, engaging marketing tone without inventing claims.';
            case 'email':
                return 'Use a polite tone suitable for email correspondence.';
            case 'document':
                return 'Use precise official-document wording.';
            case 'social':
                return 'Use concise wording suitable for social media.';
            case 'subtitle':
                return 'Use short, easy-to-read subtitle style lines.';
            case 'natural':
            default:
                return 'Produce a natural, fluent translation that reads well.';
        }
    }

    private function system_prompt(string $source, string $target, string $mode, string $resolved): string {
        $target_name = $this->language_name($target);
        $source_name = ($source === 'auto')
            ? (($resolved !== '') ? $this->language_name($resolved) : 'the source language (detect automatically)')
            : $this->language_name($source);

        return "You are a professional translator for YooY AI Studio.\n"
            . "Translate the user's text into {$target_name}.\n"
            . "Source language: {$source_name}.\n"
            . $this->mode_instruction($mode) . "\n"
            . "Rules:\n"
            . "- Return ONLY the translation text.\n"
            . "- Do not add explanations, notes, titles, or prefaces.\n"
            . "- Do not wrap the answer in Markdown code fences.\n"
            . "- Do not say phrases like \"Here is the translation\".\n"
            . "- Preserve meaning, context, proper nouns, numbers, dates, URLs, emails, and product names.\n"
            . "- Preserve paragraph breaks and line breaks.\n"
            . "- Do not summarize or omit content even if the text is long.\n"
            . "- If source language is auto-detect, detect it silently and translate to {$target_name}.";
    }

    private function user_prompt(string $text, string $source, string $target, string $mode, string $context, string $resolved): string {
        $parts = [];
        $parts[] = 'Target language code: ' . $target;
        $parts[] = 'Source language code: ' . (($source === 'auto' && $resolved !== '') ? $resolved : $source);
        $parts[] = 'Mode: ' . $mode;
        if (trim($context) !== '') {
            $parts[] = 'Context: ' . $context;
        }
        $parts[] = "Text to translate:\n" . $text;
        return implode("\n", $parts);
    }

    private function clean_translation(string $content): string {
        $text = trim($content);
        if ($text === '') {
            return '';
        }
        // Strip Markdown fences if the model ignores instructions.
        if (preg_match('/^```(?:\w+)?\s*([\s\S]*?)\s*```$/u', $text, $m)) {
            $text = trim($m[1]);
        }
        $text = preg_replace('/^(here is the translation|translation|translated text)\s*[:\-–]?\s*/iu', '', $text);
        return is_string($text) ? trim($text) : '';
    }
}

/**
 * Fallbackable provider failure (OpenAI HTTP / empty / parse). Not for validation errors.
 */
final class YooY_Translator_Provider_Exception extends Exception {

    /** @var string */
    private $internal_code;

    /** @var int */
    private $http_status;

    /** @var bool */
    private $fallbackable;

    /** @var string */
    private $request_id;

    public function __construct(
        string $message,
        string $internal_code = 'provider_error',
        int $http_status = 0,
        bool $fallbackable = true,
        string $request_id = ''
    ) {
        parent::__construct($message);
        $this->internal_code = $internal_code;
        $this->http_status = $http_status;
        $this->fallbackable = $fallbackable;
        $this->request_id = $request_id;
    }

    public function internal_code(): string {
        return $this->internal_code;
    }

    public function http_status(): int {
        return $this->http_status;
    }

    public function is_fallbackable(): bool {
        return $this->fallbackable;
    }

    public function request_id(): string {
        return $this->request_id;
    }
}
