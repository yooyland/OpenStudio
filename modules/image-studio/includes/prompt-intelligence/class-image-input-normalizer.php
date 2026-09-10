<?php
if (!defined('ABSPATH')) exit;

/**
 * Input Normalizer — cleans short user requests without changing concept.
 */
final class YooY_Image_Input_Normalizer {

    /**
     * @return array{raw:string,normalized:string,language:string,is_short:bool,word_count:int}
     */
    public static function normalize(string $raw): array {
        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        $normalized = $raw;
        // Soft command verbs → keep concept, drop UI command fluff for analysis only.
        $normalized = preg_replace(
            '/\s*(해\s*줘|해주세요|만들어\s*줘|만들어주세요|그려\s*줘|그려주세요|생성해\s*줘|please\s*(create|draw|generate))\s*$/iu',
            '',
            $normalized
        );
        $normalized = trim((string) $normalized);
        if ($normalized === '') {
            $normalized = $raw;
        }

        $is_ko = (bool) preg_match('/[가-힣]/u', $normalized);
        $words = preg_split('/\s+/u', $normalized);
        $count = is_array($words) ? count(array_filter($words)) : 0;

        return [
            'raw'         => $raw,
            'normalized'  => $normalized,
            'language'    => $is_ko ? 'ko' : 'en',
            'is_short'    => $count <= 12 || mb_strlen($normalized) <= 40,
            'word_count'  => $count,
        ];
    }
}
