<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'interface-translation-provider.php';

/**
 * Mock translation provider for UI / flow testing without API keys.
 * Returns structured, mode-aware results — not a trivial "[Translated]" prefix.
 */
final class YooY_Mock_Translation_Provider implements YooY_Translation_Provider_Interface {

    public function id(): string {
        return 'mock';
    }

    public function name(): string {
        return 'Mock Translator';
    }

    public function models(): array {
        return [
            ['id' => 'mock-translator-v1', 'name' => 'Mock Translator v1'],
        ];
    }

    public function translate(array $request): array {
        $text   = isset($request['text']) ? (string) $request['text'] : '';
        $source = isset($request['source_language']) ? (string) $request['source_language'] : 'auto';
        $target = isset($request['target_language']) ? (string) $request['target_language'] : 'en';
        $mode   = isset($request['mode']) ? (string) $request['mode'] : 'natural';

        if (class_exists('YooY_Translator_Validator')) {
            $source = YooY_Translator_Validator::normalize_language_code($source);
            $target = YooY_Translator_Validator::normalize_language_code($target);
        }

        $detected = $this->detect_language($text, $source);
        if (class_exists('YooY_Translator_Validator')) {
            $detected = YooY_Translator_Validator::normalize_language_code($detected);
            // Defense in depth: never produce same-language mock output.
            YooY_Translator_Validator::assert_different_languages($detected, $target);
        }

        $effective_source = ($source === 'auto')
            ? $detected
            : (isset($request['resolved_source_language']) && $request['resolved_source_language'] !== ''
                ? (string) $request['resolved_source_language']
                : $source);
        if (class_exists('YooY_Translator_Validator')) {
            $effective_source = YooY_Translator_Validator::normalize_language_code($effective_source);
            YooY_Translator_Validator::assert_different_languages($effective_source, $target);
        }

        $translated = $this->build_translation($text, $effective_source, $target, $mode);
        $count = $this->char_count($text);

        return [
            'success'           => true,
            'translated_text'   => $translated,
            'detected_language' => $detected,
            'provider'          => $this->id(),
            'model'             => 'mock-translator-v1',
            'character_count'   => $count,
            'credit_cost'       => 0,
            'usage'             => [
                'characters' => $count,
                'mode'       => $mode,
                'mock'       => true,
            ],
            'raw_response'      => [
                'mock'             => true,
                'effective_source' => $effective_source,
                'target'           => $target,
                'mode'             => $mode,
            ],
        ];
    }

    private function char_count(string $text): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($text, 'UTF-8');
        }
        return strlen($text);
    }

    private function detect_language(string $text, string $source): string {
        if ($source !== 'auto' && $source !== '') {
            return class_exists('YooY_Translator_Validator')
                ? YooY_Translator_Validator::normalize_language_code($source)
                : $source;
        }
        if (class_exists('YooY_Translator_Validator')) {
            return YooY_Translator_Validator::detect_language($text);
        }
        $sample = trim($text);
        if ($sample === '') {
            return 'en';
        }
        if (preg_match('/[\x{AC00}-\x{D7A3}]/u', $sample)) {
            return 'ko';
        }
        return 'en';
    }

    private function phrase_map(): array {
        return [
            'ko|en' => [
                '안녕하세요.' => 'Hello.',
                '안녕하세요' => 'Hello',
                '만나서 반갑습니다.' => 'Nice to meet you.',
                '만나서 반갑습니다' => 'Nice to meet you',
                '확인 후 다시 연락드리겠습니다.' => 'I will review it and get back to you.',
                '확인 후 다시 연락드리겠습니다' => 'I will review it and get back to you',
                '계약서를 검토해 주세요.' => 'Please review the contract.',
                '계약서를 검토해 주세요' => 'Please review the contract',
                '오늘 회의는 오후 세 시에 시작합니다.' => 'The meeting starts at 3 PM today.',
                '오늘 회의는 오후 세 시에 시작합니다' => 'The meeting starts at 3 PM today',
            ],
            'en|ko' => [
                'Hello.' => '안녕하세요.',
                'Hello' => '안녕하세요',
                'Nice to meet you.' => '만나서 반갑습니다.',
                'Nice to meet you' => '만나서 반갑습니다',
                'I will review it and get back to you.' => '확인 후 다시 연락드리겠습니다.',
                'I will review it and get back to you' => '확인 후 다시 연락드리겠습니다',
                'Please review the contract.' => '계약서를 검토해 주세요.',
                'Please review the contract' => '계약서를 검토해 주세요',
                'The meeting starts at 3 PM today.' => '오늘 회의는 오후 세 시에 시작합니다.',
                'The meeting starts at 3 PM today' => '오늘 회의는 오후 세 시에 시작합니다',
            ],
            'ko|ja' => [
                '안녕하세요.' => 'こんにちは。',
                '안녕하세요' => 'こんにちは',
                '만나서 반갑습니다.' => 'お会いできて嬉しいです。',
            ],
            'ja|ko' => [
                'こんにちは。' => '안녕하세요.',
                'こんにちは' => '안녕하세요',
            ],
            'ko|zh-CN' => [
                '안녕하세요.' => '你好。',
                '안녕하세요' => '你好',
            ],
            'zh-CN|ko' => [
                '你好。' => '안녕하세요.',
                '你好' => '안녕하세요',
            ],
        ];
    }

    private function build_translation(string $text, string $source, string $target, string $mode): string {
        $trimmed = trim($text);
        $key = $source . '|' . $target;
        $map = $this->phrase_map();

        $base = null;
        if (isset($map[$key][$trimmed])) {
            $base = $map[$key][$trimmed];
        } else {
            // Line-by-line for multi-line inputs with known phrases.
            $lines = preg_split("/\r\n|\n|\r/", $text);
            if (is_array($lines) && count($lines) > 1 && isset($map[$key])) {
                $out = [];
                $any = false;
                foreach ($lines as $line) {
                    $t = trim($line);
                    if ($t !== '' && isset($map[$key][$t])) {
                        $out[] = $map[$key][$t];
                        $any = true;
                    } elseif ($t === '') {
                        $out[] = '';
                    } else {
                        $out[] = $this->structured_fallback_line($line, $source, $target, $mode);
                        $any = true;
                    }
                }
                if ($any) {
                    $base = implode("\n", $out);
                }
            }
        }

        if ($base === null) {
            $base = $this->structured_fallback($text, $source, $target, $mode);
        }

        return $this->apply_mode($base, $mode, $target);
    }

    private function structured_fallback(string $text, string $source, string $target, string $mode): string {
        $summary = $this->simulate_summary($text, $source, $target);
        $header = ($target === 'ko')
            ? '[Mock 번역 시뮬레이션]'
            : '[Mock translation simulation]';

        $body = $this->apply_mode_to_simulation($summary, $mode, $target);
        return $header . "\n" . $body;
    }

    private function structured_fallback_line(string $line, string $source, string $target, string $mode): string {
        return $this->simulate_summary(trim($line), $source, $target);
    }

    /**
     * Build a target-language development simulation that does NOT echo the source text.
     */
    private function simulate_summary(string $text, string $source, string $target): string {
        $len = $this->char_count($text);
        $topic = $this->topic_hint($text, $source);

        // en → ko
        if ($source === 'en' && $target === 'ko') {
            if ($topic !== '') {
                return '이 문장은 ' . $topic . '에 대해 설명하는 내용입니다. (개발용 시뮬레이션 · 원문 ' . $len . '자)';
            }
            return '이 문장은 다양한 산업 분야에서 인공지능이 복잡한 문제 해결과 새로운 혁신 기회 창출에 어떻게 기여할 수 있는지를 설명하는 내용입니다. (개발용 시뮬레이션 · 원문 ' . $len . '자)';
        }

        // ko → en
        if ($source === 'ko' && $target === 'en') {
            if ($topic !== '') {
                return 'This text describes ' . $topic . '. (Development simulation · ' . $len . ' source chars)';
            }
            return 'This text describes a reader-driven media platform built around shared participation and community values. (Development simulation · ' . $len . ' source chars)';
        }

        // ja / zh and other pairs — target-shaped simulation, never echo source.
        if ($target === 'ko') {
            return '이 내용은 ' . $this->lang_label($source) . '에서 ' . $this->lang_label($target)
                . '로 옮긴 개발용 요약 시뮬레이션입니다. (원문 ' . $len . '자)';
        }
        if ($target === 'ja') {
            return 'これは' . $this->lang_label($source) . 'から' . $this->lang_label($target)
                . 'への開発用翻訳シミュレーションです。（原文' . $len . '文字）';
        }
        if ($target === 'zh-CN' || $target === 'zh-TW') {
            return '这是一段从' . $this->lang_label($source) . '到' . $this->lang_label($target)
                . '的开发用翻译模拟结果。（原文' . $len . '字）';
        }
        if ($target === 'en') {
            return 'This is a development translation simulation from '
                . $this->lang_label($source) . ' to ' . $this->lang_label($target)
                . '. (' . $len . ' source chars)';
        }

        return '[' . $this->lang_label($source) . ' → ' . $this->lang_label($target)
            . '] Development translation simulation for ' . $len . ' source characters. Source text is not echoed.';
    }

    private function topic_hint(string $text, string $source): string {
        $hay = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);

        if ($source === 'en') {
            if (strpos($hay, 'ai') !== false || strpos($hay, 'artificial intelligence') !== false) {
                return '인공지능(AI)이 산업 전반의 복잡한 과제 해결과 혁신 기회 창출에 기여하는 방식';
            }
            if (strpos($hay, 'project') !== false) {
                return '여러 프로젝트와 응용 사례가 보여주는 폭넓은 활용 범위';
            }
            if (strpos($hay, 'innovation') !== false) {
                return '혁신을 위한 새로운 기회와 산업별 적용 가능성';
            }
            return '';
        }

        if ($source === 'ko') {
            if (strpos($text, '스카이데일리') !== false || strpos($text, '언론') !== false || strpos($text, '독자') !== false) {
                return 'a reader-driven media platform built around shared participation and community values';
            }
            if (strpos($text, '인공지능') !== false || strpos($text, 'AI') !== false) {
                return 'how artificial intelligence can address complex challenges and create innovation opportunities';
            }
            if (strpos($text, '계약') !== false) {
                return 'a request to review contractual documents carefully';
            }
            return '';
        }

        return '';
    }

    private function lang_label(string $code): string {
        $names = [
            'ko' => 'Korean', 'en' => 'English', 'ja' => 'Japanese',
            'zh-CN' => 'Chinese (Simplified)', 'zh-TW' => 'Chinese (Traditional)',
            'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'it' => 'Italian',
            'pt' => 'Portuguese', 'ru' => 'Russian', 'vi' => 'Vietnamese',
            'th' => 'Thai', 'id' => 'Indonesian', 'ar' => 'Arabic', 'hi' => 'Hindi',
        ];
        return isset($names[$code]) ? $names[$code] : $code;
    }

    private function apply_mode_to_simulation(string $body, string $mode, string $target): string {
        if ($mode === 'natural' || $mode === 'subtitle') {
            return $body;
        }
        return $this->mode_wrapper($body, $mode, $target);
    }

    private function mode_wrapper(string $body, string $mode, string $target): string {
        $is_ko = ($target === 'ko');
        switch ($mode) {
            case 'literal':
                return $is_ko
                    ? '(직역 톤) ' . $body
                    : '(Literal tone) ' . $body;
            case 'business':
                return $is_ko
                    ? '업무 회신: ' . $body
                    : 'Business note: ' . $body;
            case 'formal':
                return $is_ko
                    ? '정중히 말씀드리면, ' . $body
                    : 'Respectfully, ' . $body;
            case 'casual':
                return $is_ko
                    ? '편하게 말하면 — ' . $body
                    : 'Just saying — ' . $body;
            case 'marketing':
                return $is_ko
                    ? '지금 바로! ' . $body
                    : 'Act now! ' . $body;
            case 'email':
                return $is_ko
                    ? "안녕하세요,\n\n" . $body . "\n\n감사합니다."
                    : "Hello,\n\n" . $body . "\n\nBest regards,";
            case 'document':
                return $is_ko
                    ? '[문서] ' . $body
                    : '[Document] ' . $body;
            case 'social':
                return $body . ' ✨';
            case 'subtitle':
                return $body;
            case 'natural':
            default:
                return $body;
        }
    }

    private function apply_mode(string $text, string $mode, string $target): string {
        // Known phrase maps already return natural text; wrap only when mode needs tone.
        if ($mode === 'natural' || $mode === 'subtitle') {
            return $text;
        }
        // Avoid double-wrapping simulation headers.
        if (strpos($text, '[Mock') === 0) {
            return $text;
        }
        return $this->mode_wrapper($text, $mode, $target);
    }
}
