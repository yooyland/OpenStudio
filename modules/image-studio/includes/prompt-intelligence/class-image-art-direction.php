<?php
if (!defined('ABSPATH')) exit;

/**
 * Internal art-direction presets (not providers).
 * Canonical ids use *_PREMIUM naming; legacy aliases kept for BC.
 */
final class YooY_Image_Art_Direction {

    public const MODERN_STORYBOOK_PREMIUM = 'MODERN_STORYBOOK_PREMIUM';
    public const PREMIUM_FANTASY_ILLUSTRATION = 'PREMIUM_FANTASY_ILLUSTRATION';
    public const BEAUTY_EDITORIAL_PREMIUM = 'BEAUTY_EDITORIAL_PREMIUM';
    public const LUXURY_PRODUCT_CAMPAIGN = 'LUXURY_PRODUCT_CAMPAIGN';
    public const ARCHITECTURAL_VISUALIZATION_PREMIUM = 'ARCHITECTURAL_VISUALIZATION_PREMIUM';
    public const CINEMATIC_LIFESTYLE_PREMIUM = 'CINEMATIC_LIFESTYLE_PREMIUM';
    public const EDITORIAL_PORTRAIT_PREMIUM = 'EDITORIAL_PORTRAIT_PREMIUM';
    public const GENERAL_PHOTOREAL_PREMIUM = 'GENERAL_PHOTOREAL_PREMIUM';
    public const PREMIUM_COMMERCIAL = 'PREMIUM_COMMERCIAL';
    public const CLEAN_EDITORIAL = 'CLEAN_EDITORIAL';
    public const FOOD_EDITORIAL = 'FOOD_EDITORIAL';

    // Legacy aliases
    public const MODERN_STORYBOOK = 'MODERN_STORYBOOK_PREMIUM';
    public const BEAUTY_CAMPAIGN = 'BEAUTY_EDITORIAL_PREMIUM';
    public const LUXURY_PRODUCT = 'LUXURY_PRODUCT_CAMPAIGN';
    public const ARCHITECTURAL_VISUALIZATION = 'ARCHITECTURAL_VISUALIZATION_PREMIUM';
    public const CINEMATIC_LIFESTYLE = 'CINEMATIC_LIFESTYLE_PREMIUM';
    public const EDITORIAL_PORTRAIT = 'EDITORIAL_PORTRAIT_PREMIUM';
    public const GENERAL_PHOTOREAL = 'GENERAL_PHOTOREAL_PREMIUM';
    public const FANTASY_ILLUSTRATION = 'PREMIUM_FANTASY_ILLUSTRATION';

    /**
     * @param array<string, mixed> $brief
     */
    public static function resolve_preset(array $brief): string {
        $domain = sanitize_key((string) ($brief['content_domain'] ?? 'general'));
        $raw = mb_strtolower((string) ($brief['raw_user_request'] ?? $brief['primary_subject'] ?? ''));
        $premium = self::looks_premium_visual($raw) || (($brief['quality_tier'] ?? '') === 'premium');

        if ($domain === 'politics') {
            return self::CLEAN_EDITORIAL;
        }

        // Kids + fantasy + refined → never cheap clipart; prefer premium fantasy or modern storybook.
        if ((self::looks_storybook($raw) || $domain === 'storybook')
            && (self::looks_fantasy($raw) || $domain === 'fantasy')
            && $premium) {
            return self::looks_fantasy($raw) ? self::PREMIUM_FANTASY_ILLUSTRATION : self::MODERN_STORYBOOK_PREMIUM;
        }
        if ($domain === 'fantasy' || (self::looks_fantasy($raw) && $premium)) {
            return self::PREMIUM_FANTASY_ILLUSTRATION;
        }
        if ($domain === 'storybook' || self::looks_storybook($raw)) {
            return self::MODERN_STORYBOOK_PREMIUM;
        }
        if (self::looks_fantasy($raw)) {
            return self::PREMIUM_FANTASY_ILLUSTRATION;
        }
        if ($domain === 'architecture') {
            return self::ARCHITECTURAL_VISUALIZATION_PREMIUM;
        }
        if ($domain === 'product' || $domain === 'ecommerce') {
            return self::looks_beauty($raw) ? self::BEAUTY_EDITORIAL_PREMIUM : self::LUXURY_PRODUCT_CAMPAIGN;
        }
        if ($domain === 'fashion' || $domain === 'beauty') {
            return self::BEAUTY_EDITORIAL_PREMIUM;
        }
        if ($domain === 'food') {
            return self::FOOD_EDITORIAL;
        }
        if ($domain === 'lifestyle' || $domain === 'cinematic') {
            return self::CINEMATIC_LIFESTYLE_PREMIUM;
        }
        if ($domain === 'portrait') {
            return self::EDITORIAL_PORTRAIT_PREMIUM;
        }
        if ($domain === 'illustration') {
            return $premium ? self::PREMIUM_FANTASY_ILLUSTRATION : self::MODERN_STORYBOOK_PREMIUM;
        }
        if ($domain === 'brand' || $domain === 'corporate' || $domain === 'social' || self::looks_commercial($raw)) {
            return self::PREMIUM_COMMERCIAL;
        }
        return self::GENERAL_PHOTOREAL_PREMIUM;
    }

    public static function looks_storybook(string $raw): bool {
        if (preg_match('/그림책|동화|picture.?book|storybook/u', $raw)) {
            return true;
        }
        if (preg_match('/어린이|아동|키즈|kids|for\s*children/u', $raw)) {
            return true;
        }
        if (preg_match('/꿈|상상/u', $raw) && preg_match('/펭귄|팽귄|고래|용|요정|마법|날아|하늘을|세계\s*여행|판타지/u', $raw)) {
            return true;
        }
        if (preg_match('/펭귄|팽귄/u', $raw) && preg_match('/고래|하늘|날/u', $raw)) {
            return true;
        }
        return false;
    }

    public static function looks_fantasy(string $raw): bool {
        return (bool) preg_match('/판타지|드래곤|유니콘|마법|요정|fantasy|dragon|unicorn|wizard|aurora|오로라/u', $raw);
    }

    public static function looks_premium_visual(string $raw): bool {
        return (bool) preg_match(
            '/현대적|세련|고급|프리미엄|우아|정교|그림책\s*표지|촌스럽지|디즈니풍\s*아니라|editorial|premium|sophisticated|refined|elegant|polished|cinematic|high.?end|tasteful|non.?kitschy|not\s*kitschy/u',
            $raw
        );
    }

    public static function looks_beauty(string $raw): bool {
        return (bool) preg_match('/화장품|스킨케어|크림|세럼|향수|뷰티|cosmetic|skincare|beauty|serum|perfume/u', $raw);
    }

    public static function looks_commercial(string $raw): bool {
        return (bool) preg_match('/광고|캠페인|브랜드|분양|advert|campaign|brand/u', $raw);
    }

    /** @return string[] */
    public static function premium_visual_bias(string $preset): array {
        $common = [
            'sophisticated refined premium editorial-quality finish',
            'elegant polished composition with rich depth and tasteful color harmony',
            'cinematic lighting, beautifully composed, high-detail, non-kitschy',
        ];
        switch ($preset) {
            case self::MODERN_STORYBOOK_PREMIUM:
            case self::PREMIUM_FANTASY_ILLUSTRATION:
                return array_merge($common, [
                    'modern premium picture-book cover illustration aesthetic',
                    'grand outdoor adventure scale — not an indoor mural or flat wall decoration',
                    'Disney-theme-park kitsch avoided; refined premium picture-book cover mood',
                ]);
            case self::EDITORIAL_PORTRAIT_PREMIUM:
            case self::CINEMATIC_LIFESTYLE_PREMIUM:
                return array_merge($common, ['contemporary editorial portrait / lifestyle photography']);
            case self::BEAUTY_EDITORIAL_PREMIUM:
            case self::LUXURY_PRODUCT_CAMPAIGN:
                return array_merge($common, ['quiet luxury product / beauty still, magazine double-page quality']);
            case self::ARCHITECTURAL_VISUALIZATION_PREMIUM:
                return array_merge($common, ['premium real-estate campaign architectural visualization']);
            default:
                return $common;
        }
    }

    /** @return string[] */
    public static function quality_constraints(string $preset): array {
        switch ($preset) {
            case self::EDITORIAL_PORTRAIT_PREMIUM:
            case self::CINEMATIC_LIFESTYLE_PREMIUM:
                return [
                    'contemporary sophisticated but believable human styling',
                    'natural micro-expression, plausible anatomy and hands',
                    'realistic skin texture with subtle natural imperfections',
                    'no plastic skin, no mannequin faces, no awkward proportions',
                ];
            case self::MODERN_STORYBOOK_PREMIUM:
            case self::PREMIUM_FANTASY_ILLUSTRATION:
                return [
                    'modern premium picture-book / editorial illustration',
                    'cinematic storytelling composition with atmospheric depth',
                    'sophisticated child-friendly palette — warm but never childish clipart',
                    'depict the requested adventure as the main scene',
                    'avoid dated clip-art, flat mural, toy-like oversaturation, cheap poster look',
                ];
            case self::BEAUTY_EDITORIAL_PREMIUM:
            case self::LUXURY_PRODUCT_CAMPAIGN:
                return [
                    'premium product photography with accurate geometry',
                    'realistic glass/metal/plastic materials and controlled speculars',
                    'do not invent readable logos or random label text unless requested',
                ];
            case self::ARCHITECTURAL_VISUALIZATION_PREMIUM:
                return [
                    'coherent building geometry, straight verticals, plausible perspective',
                    'detailed façade materials and realistic landscaping',
                    'no warped towers or identical plastic clone façades',
                ];
            default:
                return [
                    'professionally art-directed image',
                    'intentional composition and believable materials',
                    'avoid generic AI-stock look',
                ];
        }
    }

    /** @return string[] */
    public static function common_negatives(): array {
        return [
            'childish clipart',
            'cheap poster look',
            'flat mural look',
            'awkward anatomy',
            'plasticky surfaces',
            'generic stock composition',
            'low-detail rendering',
            'toy-like oversaturation',
            'random text overlays',
            'tacky visual treatment',
            'kitschy',
            'low detail mush',
        ];
    }

    /**
     * Genre-specific negatives.
     *
     * @return string[]
     */
    public static function genre_negatives(string $preset): array {
        switch ($preset) {
            case self::MODERN_STORYBOOK_PREMIUM:
            case self::PREMIUM_FANTASY_ILLUSTRATION:
                return [
                    'dated cheap storybook look',
                    'Disney theme-park kitsch',
                    'indoor bedroom child observer',
                    'plastic CGI toys',
                ];
            case self::EDITORIAL_PORTRAIT_PREMIUM:
            case self::CINEMATIC_LIFESTYLE_PREMIUM:
                return [
                    'plasticky skin',
                    'uncanny smile',
                    'bad hands',
                    'extra fingers',
                    'generic stock-photo pose',
                ];
            case self::BEAUTY_EDITORIAL_PREMIUM:
            case self::LUXURY_PRODUCT_CAMPAIGN:
                return [
                    'warped bottle geometry',
                    'melted packaging',
                    'invented Hangul text',
                    'fake logos',
                    'glitter overload',
                ];
            case self::ARCHITECTURAL_VISUALIZATION_PREMIUM:
                return [
                    'warped geometry',
                    'distorted windows',
                    'bent buildings',
                    'floating structures',
                    'cartoon architecture',
                ];
            default:
                return [];
        }
    }
}
