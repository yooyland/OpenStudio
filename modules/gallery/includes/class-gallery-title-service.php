<?php
if (!defined('ABSPATH')) exit;

/**
 * Generates human-friendly gallery/work titles from prompts and filenames.
 */
final class YooY_Gallery_Title_Service {

    private const PLACEHOLDERS = ['untitled', 'work', 'generated', ''];

    public static function resolve(array $context): string {
        $explicit = trim((string) ($context['title'] ?? ''));
        if ($explicit !== '' && !self::is_placeholder($explicit)) {
            $title = self::clamp($explicit, 35);
            return self::ensure_unique($title, self::existing_titles_from_context($context));
        }

        $user_prompt = trim((string) ($context['user_prompt'] ?? ''));
        $prompt      = trim((string) ($context['prompt'] ?? ''));
        $source      = $user_prompt !== '' ? $user_prompt : $prompt;

        if ($source !== '') {
            $from_prompt = self::from_prompt($source, (string) ($context['type'] ?? 'image'));
            if ($from_prompt !== '') {
                return self::ensure_unique($from_prompt, self::existing_titles_from_context($context));
            }
        }

        $filename = trim((string) ($context['filename'] ?? ''));
        if ($filename !== '') {
            $title = self::from_filename($filename);
            return self::ensure_unique($title, self::existing_titles_from_context($context));
        }

        $title = self::fallback((string) ($context['type'] ?? 'image'));
        return self::ensure_unique($title, self::existing_titles_from_context($context));
    }

    /**
     * Append (2), (3)... when the same title already exists.
     *
     * @param string   $title
     * @param string[] $existing_titles
     */
    public static function ensure_unique(string $title, array $existing_titles = []): string {
        $title = self::cleanup_spaces($title);
        if ($title === '' || empty($existing_titles)) {
            return $title;
        }

        $existing = [];
        foreach ($existing_titles as $existing_title) {
            $existing_title = self::cleanup_spaces((string) $existing_title);
            if ($existing_title !== '') {
                $existing[] = $existing_title;
            }
        }
        if (empty($existing)) {
            return $title;
        }

        if (!in_array($title, $existing, true)) {
            return $title;
        }

        $base = self::base_title($title);
        $n = 2;
        while ($n < 1000) {
            $candidate = self::with_numeric_suffix($base, $n);
            if (!in_array($candidate, $existing, true)) {
                return $candidate;
            }
            $n++;
        }

        return self::with_numeric_suffix($base, $n);
    }

    public static function base_title(string $title): string {
        $title = self::cleanup_spaces($title);
        if (preg_match('/^(.+?)\s+\((\d+)\)$/u', $title, $matches)) {
            return self::cleanup_spaces((string) ($matches[1] ?? $title));
        }
        return $title;
    }

    private static function with_numeric_suffix(string $base, int $n, int $max = 35): string {
        $suffix = ' (' . $n . ')';
        $max_base = max(5, $max - mb_strlen($suffix));
        $base = self::clamp($base, $max_base);
        return $base . $suffix;
    }

    private static function existing_titles_from_context(array $context): array {
        $existing = $context['existing_titles'] ?? [];
        return is_array($existing) ? $existing : [];
    }

    public static function is_placeholder(string $title): bool {
        $normalized = mb_strtolower(trim($title));
        if ($normalized === '') {
            return true;
        }
        foreach (self::PLACEHOLDERS as $placeholder) {
            if ($placeholder !== '' && $normalized === $placeholder) {
                return true;
            }
        }
        return (bool) preg_match('/^(untitled|generated|work)(\s|$)/iu', $normalized);
    }

    private static function from_prompt(string $prompt, string $type): string {
        $parts = preg_split('/[—–\-]\s*/u', $prompt, 2);
        $lead  = trim((string) ($parts[0] ?? $prompt));
        $tail  = trim((string) ($parts[1] ?? ''));

        $lead = self::strip_request_suffix($lead);
        $combined = $lead;
        if ($tail !== '') {
            $combined = self::merge_prompt_parts($lead, $tail, $type);
        }

        if ($combined === '') {
            $combined = self::strip_request_suffix($prompt);
        }

        if (mb_strlen($combined) > 35) {
            $combined = self::keyword_title($combined, $type);
        }

        $combined = self::cleanup_spaces($combined);
        if ($combined === '') {
            return '';
        }

        return self::clamp($combined, 35);
    }

    private static function merge_prompt_parts(string $lead, string $tail, string $type): string {
        $modifiers = [];
        if (preg_match('/한국|korea|k-?culture/iu', $lead . ' ' . $tail)) {
            $modifiers[] = '한국';
        }
        if (preg_match('/\bTV\b|티비|television/iu', $tail)) {
            $modifiers[] = 'TV';
        }
        if (preg_match('/디지털|digital/iu', $tail)) {
            $modifiers[] = '디지털';
        }

        $subject = self::extract_subject($lead);
        $theme   = '';
        if (preg_match('/광고|advertis|commercial|campaign/iu', $lead . ' ' . $tail)) {
            $theme = '광고';
        } elseif (preg_match('/포스터|poster/iu', $lead . ' ' . $tail)) {
            $theme = '포스터';
        } elseif (preg_match('/썸네일|thumbnail/iu', $lead . ' ' . $tail)) {
            $theme = '썸네일';
        }

        $suffix = self::type_suffix($type);
        $chunks = array_filter(array_merge($modifiers, [$subject, $theme, $suffix]));
        if (count($chunks) >= 2) {
            return implode(' ', $chunks);
        }

        if (mb_strlen($lead) <= 35) {
            return $lead;
        }

        return self::keyword_title($lead, $type);
    }

    private static function extract_subject(string $text): string {
        $text = self::strip_request_suffix($text);
        $text = preg_replace('/\b(여성|남성|male|female)\b/iu', '', $text);
        $text = self::cleanup_spaces($text);

        if (preg_match('/(수영복|스마트스토어|제품|product|brand|브랜드|고래|펭귄|여행|travel|movie|영화|mv|뮤직비디오)/iu', $text, $m)) {
            $keyword = trim($m[1]);
            if (preg_match('/수영복/iu', $keyword)) {
                return '수영복';
            }
            if (preg_match('/고래/iu', $text) && preg_match('/여행|travel/iu', $text)) {
                return '고래 타고 세계여행';
            }
            if (preg_match('/펭귄/iu', $text) && preg_match('/가족|family/iu', $text)) {
                return '펭귄 가족';
            }
        }

        $words = preg_split('/\s+/u', $text);
        $words = is_array($words) ? array_values(array_filter($words)) : [];
        if (count($words) > 6) {
            return implode(' ', array_slice($words, 0, 5));
        }

        return $text;
    }

    private static function keyword_title(string $text, string $type): string {
        $text = self::strip_request_suffix($text);
        $keywords = [];

        $map = [
            '한국' => '한국', 'korea' => '한국', 'tv' => 'TV', '티비' => 'TV',
            '수영복' => '수영복', '광고' => '광고', 'advert' => '광고',
            '고래' => '고래', '여행' => '여행', 'travel' => '여행',
            '펭귄' => '펭귄', '가족' => '가족', 'family' => '가족',
            '포스터' => '포스터', 'poster' => '포스터', '제품' => '제품',
            'product' => '제품', '영상' => '영상', 'video' => '영상',
            '음악' => '음악', 'music' => '음악', '음성' => '음성', 'voice' => '음성',
        ];

        $lower = mb_strtolower($text);
        foreach ($map as $needle => $label) {
            if (mb_strpos($lower, mb_strtolower($needle)) !== false) {
                $keywords[] = $label;
            }
        }
        $keywords = array_values(array_unique($keywords));

        if (!empty($keywords)) {
            $title = implode(' ', array_slice($keywords, 0, 4));
            $suffix = self::type_suffix($type);
            if ($suffix !== '' && mb_strpos($title, $suffix) === false && mb_strlen($title) < 28) {
                $title .= ' ' . $suffix;
            }
            return self::clamp(self::cleanup_spaces($title), 35);
        }

        $sentence = preg_split('/[.!?\n]/u', $text, 2)[0];
        $sentence = self::cleanup_spaces((string) $sentence);
        if (mb_strlen($sentence) > 35) {
            $sentence = mb_substr($sentence, 0, 35);
            $sentence = preg_replace('/\s+\S*$/u', '', $sentence);
        }
        return self::clamp($sentence, 35);
    }

    private static function strip_request_suffix(string $text): string {
        $text = trim($text);
        $text = preg_replace('/\s*(해\s*줘|해주세요|만들어\s*줘|만들어주세요|생성해\s*줘|제작해\s*줘|please|create|generate)\s*$/iu', '', $text);
        $text = preg_replace('/\s{2,}/u', ' ', $text);
        return trim($text);
    }

    private static function from_filename(string $filename): string {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/[_\-]+/', ' ', (string) $name);
        $name = self::cleanup_spaces((string) $name);
        if ($name === '') {
            return '';
        }
        return self::clamp($name, 35);
    }

    private static function type_suffix(string $type): string {
        switch ($type) {
            case 'image':
                return '이미지';
            case 'video':
                return '영상';
            case 'music':
                return '음악';
            case 'voice':
                return '음성';
            case 'writing':
                return '글';
            case 'avatar':
                return '아바타';
            default:
                return '';
        }
    }

    private static function fallback(string $type): string {
        $label = self::type_suffix($type);
        if ($label === '') {
            $label = 'AI 작품';
        } else {
            $label = 'AI ' . $label;
        }
        return $label . ' ' . date_i18n('Y-m-d H:i');
    }

    private static function cleanup_spaces(string $text): string {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function clamp(string $text, int $max): string {
        $text = self::cleanup_spaces($text);
        if ($text === '') {
            return '';
        }
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $cut = preg_replace('/\s+\S*$/u', '', $cut);
        return trim($cut) !== '' ? trim($cut) : mb_substr($text, 0, $max);
    }
}
