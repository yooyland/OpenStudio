<?php
if (!defined('ABSPATH')) exit;

/**
 * Generates human-friendly gallery/work titles from user intent.
 * Display titles must feel like creative work names — never "광고 이미지 (9)".
 */
final class YooY_Gallery_Title_Service {

    private const PLACEHOLDERS = ['untitled', 'work', 'generated', ''];

    /** UI / purpose words that must not become title content. */
    private const PURPOSE_NOISE = [
        '이미지', '광고 이미지', '생성 이미지', 'ai 이미지', 'ai image', 'generated image',
        '만들어줘', '만들어주세요', '해주세요', '해줘', '생성해줘', '그려줘', '그려주세요',
        '광고', '캠페인', '브랜드', '분양', '포스터', '썸네일', '제품컷', '제품 사진',
        'image', 'advert', 'advertising', 'campaign', 'please', 'create', 'generate', 'draw',
    ];

    public static function resolve(array $context): string {
        $explicit = trim((string) ($context['title'] ?? ''));
        if ($explicit !== '' && !self::is_placeholder($explicit) && !self::looks_mechanical($explicit)) {
            return self::clamp($explicit, 28);
        }

        $user_prompt = trim((string) ($context['user_prompt'] ?? $context['raw_user_request'] ?? ''));
        $prompt      = trim((string) ($context['prompt'] ?? ''));
        $source      = $user_prompt !== '' ? $user_prompt : $prompt;
        $domain      = sanitize_key((string) ($context['intent_domain'] ?? $context['content_domain'] ?? ''));
        $type        = (string) ($context['type'] ?? 'image');

        if ($source !== '') {
            $creative = self::creative_title($source, $domain, $type, $context);
            if ($creative !== '') {
                // Display titles may duplicate; gallery_id is the unique key.
                return $creative;
            }
        }

        $filename = trim((string) ($context['filename'] ?? ''));
        if ($filename !== '') {
            $title = self::from_filename($filename);
            if ($title !== '' && !self::looks_mechanical($title)) {
                return $title;
            }
        }

        return self::fallback($type);
    }

    /**
     * Kept for Store callers — display titles no longer get (2)/(9) counters.
     *
     * @param string   $title
     * @param string[] $existing_titles
     */
    public static function ensure_unique(string $title, array $existing_titles = []): string {
        unset($existing_titles);
        return self::cleanup_spaces($title);
    }

    public static function base_title(string $title): string {
        $title = self::cleanup_spaces($title);
        if (preg_match('/^(.+?)\s+\((\d+)\)$/u', $title, $matches)) {
            return self::cleanup_spaces((string) ($matches[1] ?? $title));
        }
        return $title;
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
        return (bool) preg_match('/^(untitled|generated|work|ai\s*이미지|ai\s*image)(\s|$)/iu', $normalized);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function creative_title(string $source, string $domain, string $type, array $context): string {
        $raw_lower = mb_strtolower($source);
        $clean = self::strip_purpose_language($source);
        $lower = mb_strtolower($clean);

        // Domain / scene-specific creative titles (deterministic, concept-faithful).
        $special = self::scene_title($clean, $lower, $domain, $raw_lower);
        if ($special !== '') {
            return self::clamp($special, 22);
        }

        $subject = self::extract_visual_subject($clean, $domain);
        if ($subject === '') {
            $subject = self::first_meaningful_chunk($clean);
        }
        if ($subject === '') {
            return '';
        }

        // Prefer short natural subject phrase; avoid appending type/purpose words.
        if (mb_strlen($subject) <= 22 && !self::looks_mechanical($subject)) {
            return self::clamp($subject, 22);
        }

        return self::clamp($subject, 22);
    }

    private static function scene_title(string $clean, string $lower, string $domain, string $raw_lower = ''): string {
        if ($raw_lower === '') {
            $raw_lower = $lower;
        }
        // Penguin family whale adventure
        if ((preg_match('/펭귄|팽귄/u', $clean) || preg_match('/펭귄|팽귄/u', $raw_lower))
            && preg_match('/고래/u', $raw_lower)) {
            if (preg_match('/오로라|aurora/u', $raw_lower)) {
                return '오로라 너머의 여행';
            }
            if (preg_match('/밤|별|night|star/u', $raw_lower)) {
                return '별을 건너는 펭귄 가족';
            }
            return '고래 등에 올라탄 세계여행';
        }
        if (preg_match('/펭귄|팽귄/u', $raw_lower) && preg_match('/가족|family/u', $raw_lower)) {
            return '하늘을 나는 펭귄 가족';
        }
        if (preg_match('/고래/u', $clean) && preg_match('/여행|하늘|날/u', $clean)) {
            return '달빛 아래 고래여행';
        }

        // Summer beach cosmetics
        if (preg_match('/화장품|스킨케어|크림|세럼|cosmetic|skincare/u', $raw_lower)
            && preg_match('/여름|바다|해변|beach|summer|sea/u', $raw_lower)) {
            return '바다빛을 담은 여름';
        }
        if (preg_match('/스킨케어|크림|skincare|cream/u', $raw_lower)
            && preg_match('/럭셔리|프리미엄|luxury|premium/u', $raw_lower)) {
            return '빛을 담은 스킨케어';
        }
        if (preg_match('/화장품|스킨케어|크림|세럼|향수/u', $raw_lower)) {
            return '순수함의 한 순간';
        }

        // Lifestyle couple (before architecture keywords like 아파트)
        if (preg_match('/부부|커플|couple/u', $raw_lower) && preg_match('/아파트|서울|단지/u', $raw_lower)) {
            return '도시의 오후, 둘';
        }
        if (preg_match('/부부|커플|couple/u', $raw_lower)) {
            return '자연스러운 하루의 대화';
        }

        // Architecture / apartment — purpose words may already be stripped from $clean
        if ($domain === 'architecture' || preg_match('/아파트|조감|단지|건축/u', $raw_lower)) {
            if (preg_match('/조감/u', $raw_lower)) {
                return '한강빛 주거단지 조감도';
            }
            if (preg_match('/분양|광고|캠페인|advert|campaign/u', $raw_lower)) {
                return '빛이 머무는 프리미엄 라이프';
            }
            return '도시와 만나는 하루';
        }

        // Children dream without specific adventure nouns already handled
        if (preg_match('/어린이|꿈|동화|storybook/u', $raw_lower) && preg_match('/여행|하늘|바다/u', $raw_lower)) {
            return '꿈속의 세계여행';
        }

        return '';
    }

    private static function extract_visual_subject(string $text, string $domain): string {
        $text = self::strip_purpose_language($text);
        // Keep Korean phrase up to first filler verb leftovers
        $text = preg_replace('/\s*(을|를|이|가|은|는)?\s*$/u', '', $text);

        if (preg_match('/(.{2,18}?)(을|를)\s/u', $text, $m)) {
            $cand = self::cleanup_spaces((string) $m[1]);
            if ($cand !== '' && !self::looks_mechanical($cand)) {
                return $cand;
            }
        }

        $words = preg_split('/\s+/u', $text);
        $words = is_array($words) ? array_values(array_filter($words)) : [];
        $keep = [];
        foreach ($words as $w) {
            $wl = mb_strtolower($w);
            if (self::is_noise_token($wl)) {
                continue;
            }
            $keep[] = $w;
            if (count($keep) >= 5) {
                break;
            }
        }
        return self::cleanup_spaces(implode(' ', $keep));
    }

    private static function first_meaningful_chunk(string $text): string {
        $text = self::strip_purpose_language($text);
        $parts = preg_split('/[.!?\n,]/u', $text);
        $chunk = self::cleanup_spaces((string) ($parts[0] ?? $text));
        return self::clamp($chunk, 22);
    }

    private static function strip_purpose_language(string $text): string {
        $text = trim($text);
        $text = preg_replace('/\s*(해\s*줘|해주세요|만들어\s*줘|만들어주세요|생성해\s*줘|그려\s*줘|그려주세요|please|create|generate|draw)\s*$/iu', '', $text);
        foreach (self::PURPOSE_NOISE as $noise) {
            if ($noise === '') {
                continue;
            }
            $text = preg_replace('/\b' . preg_quote($noise, '/') . '\b/iu', ' ', $text);
            // Korean phrases without word boundaries
            $text = str_replace($noise, ' ', $text);
        }
        $text = preg_replace('/\s*(을|를|이|가)?\s*$/u', '', $text);
        return self::cleanup_spaces((string) $text);
    }

    private static function is_noise_token(string $token): bool {
        foreach (self::PURPOSE_NOISE as $noise) {
            if ($noise !== '' && mb_strtolower($noise) === $token) {
                return true;
            }
        }
        return in_array($token, ['위한', '있는', '하는', '그릴', '것이다', '배경으로'], true);
    }

    private static function looks_mechanical(string $title): bool {
        $t = mb_strtolower(trim($title));
        if (preg_match('/\((\d+)\)$/u', $t)) {
            return true;
        }
        if (preg_match('/이미지|광고 이미지|generated image|ai image/u', $t)) {
            return true;
        }
        if (preg_match('/^(한국|광고|이미지)(\s+(한국|광고|이미지))*$/u', $t)) {
            return true;
        }
        return false;
    }

    private static function from_filename(string $filename): string {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/[_\-]+/', ' ', (string) $name);
        $name = self::cleanup_spaces((string) $name);
        if ($name === '' || self::looks_mechanical($name)) {
            return '';
        }
        return self::clamp($name, 22);
    }

    private static function fallback(string $type): string {
        switch ($type) {
            case 'video':
                return '새로운 영상 작품';
            case 'music':
                return '새로운 음악 작품';
            case 'voice':
                return '새로운 음성 작품';
            case 'writing':
                return '새로운 글 작품';
            case 'translation':
                return '새로운 번역 작품';
            case 'avatar':
                return '새로운 아바타 작품';
            default:
                return '새로운 시각 작품';
        }
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
