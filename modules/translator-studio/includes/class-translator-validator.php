<?php
if (!defined('ABSPATH')) exit;

/**
 * Request validation for Translator Studio.
 * Preserves newlines; strips HTML/script without destroying long text.
 */
final class YooY_Translator_Validator {

    const MAX_CHARS = 20000;
    const ERR_SAME_LANGUAGE = 'same_source_target_language';
    const MSG_SAME_LANGUAGE = '감지된 원문 언어와 대상 언어가 같습니다. 다른 대상 언어를 선택해 주세요.';

    /** @return array<string,string> code => label */
    public static function languages(): array {
        return [
            'auto'  => '자동 감지',
            'ko'    => '한국어',
            'en'    => '영어',
            'ja'    => '일본어',
            'zh-CN' => '중국어 간체',
            'zh-TW' => '중국어 번체',
            'es'    => '스페인어',
            'fr'    => '프랑스어',
            'de'    => '독일어',
            'it'    => '이탈리아어',
            'pt'    => '포르투갈어',
            'ru'    => '러시아어',
            'vi'    => '베트남어',
            'th'    => '태국어',
            'id'    => '인도네시아어',
            'ar'    => '아랍어',
            'hi'    => '힌디어',
        ];
    }

    /** @return array<string,string> id => label */
    public static function modes(): array {
        return [
            'natural'   => '자연스러운 번역',
            'literal'   => '정확한 직역',
            'business'  => '비즈니스',
            'formal'    => '격식체',
            'casual'    => '친근한 말투',
            'marketing' => '광고·마케팅',
            'email'     => '이메일',
            'document'  => '계약·문서',
            'social'    => 'SNS',
            'subtitle'  => '자막',
        ];
    }

    /**
     * Normalize language tags to studio codes.
     * en-US/en-GB → en, ko-KR → ko, ja-JP → ja, zh/zh-CN → zh-CN, zh-TW → zh-TW.
     */
    public static function normalize_language_code(string $code): string {
        $code = trim($code);
        if ($code === '' || strtolower($code) === 'auto') {
            return 'auto';
        }

        $raw = str_replace('_', '-', $code);
        $lower = strtolower($raw);

        if ($lower === 'zh-tw' || $lower === 'zh-hant' || strpos($lower, 'zh-tw') === 0) {
            return 'zh-TW';
        }
        if ($lower === 'zh' || $lower === 'zh-cn' || $lower === 'zh-hans' || strpos($lower, 'zh-cn') === 0 || strpos($lower, 'zh-hans') === 0) {
            return 'zh-CN';
        }

        $primary = $lower;
        $pos = strpos($lower, '-');
        if ($pos !== false) {
            $primary = substr($lower, 0, $pos);
        }

        $map = [
            'ko' => 'ko', 'en' => 'en', 'ja' => 'ja',
            'es' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it',
            'pt' => 'pt', 'ru' => 'ru', 'vi' => 'vi', 'th' => 'th',
            'id' => 'id', 'ar' => 'ar', 'hi' => 'hi',
        ];
        if (isset($map[$primary])) {
            return $map[$primary];
        }

        // Preserve known studio codes with correct casing.
        $langs = self::languages();
        foreach (array_keys($langs) as $known) {
            if (strtolower($known) === $lower) {
                return $known;
            }
        }

        return $raw;
    }

    /**
     * Heuristic language detection for auto source (shared by service + mock).
     */
    public static function detect_language(string $text): string {
        $sample = trim($text);
        if ($sample === '') {
            return 'en';
        }
        if (preg_match('/[\x{3040}-\x{30FF}]/u', $sample)) {
            return 'ja';
        }
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $sample)) {
            return 'zh-CN';
        }
        if (preg_match('/[\x{AC00}-\x{D7A3}]/u', $sample)) {
            return 'ko';
        }
        if (preg_match('/[\x{0400}-\x{04FF}]/u', $sample)) {
            return 'ru';
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $sample)) {
            return 'ar';
        }
        if (preg_match('/[\x{0E00}-\x{0E7F}]/u', $sample)) {
            return 'th';
        }
        return 'en';
    }

    /**
     * @throws YooY_Translator_Exception
     */
    public static function assert_different_languages(string $source_or_detected, string $target): void {
        $src = self::normalize_language_code($source_or_detected);
        $tgt = self::normalize_language_code($target);
        if ($src === 'auto') {
            return;
        }
        if ($src === $tgt) {
            throw new YooY_Translator_Exception(self::MSG_SAME_LANGUAGE, self::ERR_SAME_LANGUAGE, 400);
        }
    }

    public static function sanitize_text(string $text): string {
        $text = wp_check_invalid_utf8($text);
        $text = wp_strip_all_tags($text, false);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        return $text;
    }

    public static function char_count(string $text): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($text, 'UTF-8');
        }
        return strlen($text);
    }

    /**
     * @param array $params Raw request params.
     * @return array Normalized params.
     * @throws YooY_Translator_Exception|Exception
     */
    public static function validate_translate(array $params): array {
        $text = self::sanitize_text(isset($params['text']) ? (string) $params['text'] : '');
        if (trim($text) === '') {
            throw new YooY_Translator_Exception('원문을 입력해 주세요.', 'empty_text', 400);
        }

        $count = self::char_count($text);
        if ($count > self::MAX_CHARS) {
            throw new YooY_Translator_Exception(
                '원문은 최대 ' . self::MAX_CHARS . '자까지 입력할 수 있습니다. (현재 ' . $count . '자)',
                'text_too_long',
                400
            );
        }

        $langs = self::languages();
        $source_raw = isset($params['source_language']) ? (string) $params['source_language'] : 'auto';
        $target_raw = isset($params['target_language']) ? (string) $params['target_language'] : 'en';
        $source = self::normalize_language_code($source_raw);
        $target = self::normalize_language_code($target_raw);

        if (!isset($langs[$source])) {
            throw new YooY_Translator_Exception('지원하지 않는 원문 언어입니다.', 'unsupported_source_language', 400);
        }
        if ($target === 'auto' || !isset($langs[$target])) {
            throw new YooY_Translator_Exception('지원하지 않는 대상 언어입니다.', 'unsupported_target_language', 400);
        }

        // Explicit source === target → block before any provider call.
        if ($source !== 'auto') {
            self::assert_different_languages($source, $target);
        }

        $modes = self::modes();
        $mode = isset($params['mode']) ? sanitize_key((string) $params['mode']) : 'natural';
        if (!isset($modes[$mode])) {
            throw new YooY_Translator_Exception('지원하지 않는 번역 모드입니다.', 'unsupported_mode', 400);
        }

        $context = self::sanitize_text(isset($params['context']) ? (string) $params['context'] : '');
        if (self::char_count($context) > 2000) {
            $context = function_exists('mb_substr')
                ? mb_substr($context, 0, 2000, 'UTF-8')
                : substr($context, 0, 2000);
        }

        $glossary = [];
        if (!empty($params['glossary']) && is_array($params['glossary'])) {
            foreach (array_slice($params['glossary'], 0, 50) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $src = sanitize_text_field((string) ($item['source'] ?? ''));
                $tgt = sanitize_text_field((string) ($item['target'] ?? ''));
                if ($src !== '' && $tgt !== '') {
                    $glossary[] = ['source' => $src, 'target' => $tgt];
                }
            }
        }

        return [
            'text'             => $text,
            'source_language'  => $source,
            'target_language'  => $target,
            'mode'             => $mode,
            'context'          => $context,
            'glossary'         => $glossary,
            'character_count'  => $count,
            'provider'         => sanitize_key((string) ($params['provider'] ?? 'auto')),
            'project_id'       => sanitize_text_field((string) ($params['project_id'] ?? '')),
        ];
    }
}

/**
 * Translator domain exception with stable error codes for REST clients.
 */
final class YooY_Translator_Exception extends Exception {

    /** @var string */
    private $error_code;

    /** @var int */
    private $http_status;

    public function __construct(string $message, string $error_code = 'error', int $http_status = 400) {
        parent::__construct($message);
        $this->error_code = $error_code;
        $this->http_status = $http_status;
    }

    public function error_code(): string {
        return $this->error_code;
    }

    public function http_status(): int {
        return $this->http_status;
    }

    public function to_rest_detail(): array {
        return [
            'stage'   => 'validation',
            'code'    => $this->error_code,
            'message' => $this->getMessage(),
            'reason'  => $this->error_code,
        ];
    }
}
