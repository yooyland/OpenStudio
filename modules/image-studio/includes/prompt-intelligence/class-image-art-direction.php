<?php
if (!defined('ABSPATH')) exit;

/**
 * Internal image art-direction presets — not a new provider.
 * Maps visual intent → preset id + premium visual bias fragments.
 */
final class YooY_Image_Art_Direction {

    public const PREMIUM_COMMERCIAL = 'PREMIUM_COMMERCIAL';
    public const EDITORIAL_PORTRAIT = 'EDITORIAL_PORTRAIT';
    public const LUXURY_PRODUCT = 'LUXURY_PRODUCT';
    public const BEAUTY_CAMPAIGN = 'BEAUTY_CAMPAIGN';
    public const ARCHITECTURAL_VISUALIZATION = 'ARCHITECTURAL_VISUALIZATION';
    public const MODERN_STORYBOOK = 'MODERN_STORYBOOK';
    public const CINEMATIC_LIFESTYLE = 'CINEMATIC_LIFESTYLE';
    public const CLEAN_EDITORIAL = 'CLEAN_EDITORIAL';
    public const FANTASY_ILLUSTRATION = 'FANTASY_ILLUSTRATION';
    public const PREMIUM_FANTASY_ILLUSTRATION = 'PREMIUM_FANTASY_ILLUSTRATION';
    public const FOOD_EDITORIAL = 'FOOD_EDITORIAL';
    public const GENERAL_PHOTOREAL = 'GENERAL_PHOTOREAL';

    /**
     * @param array<string, mixed> $brief
     */
    public static function resolve_preset(array $brief): string {
        $domain = sanitize_key((string) ($brief['content_domain'] ?? 'general'));
        $raw = mb_strtolower((string) ($brief['raw_user_request'] ?? $brief['primary_subject'] ?? ''));

        if ($domain === 'politics') {
            return self::CLEAN_EDITORIAL;
        }

        // Explicit fantasy (+ premium polish) before broad storybook keyword match.
        if ($domain === 'fantasy'
            || (self::looks_fantasy($raw) && (self::looks_premium_visual($raw) || $domain === 'fantasy'))) {
            return self::PREMIUM_FANTASY_ILLUSTRATION;
        }
        if ($domain === 'storybook' || self::looks_storybook($raw)) {
            return self::MODERN_STORYBOOK;
        }
        if (self::looks_fantasy($raw)) {
            return self::PREMIUM_FANTASY_ILLUSTRATION;
        }
        if ($domain === 'architecture') {
            return self::ARCHITECTURAL_VISUALIZATION;
        }
        if ($domain === 'product' || $domain === 'ecommerce') {
            if (self::looks_beauty($raw)) {
                return self::BEAUTY_CAMPAIGN;
            }
            return self::LUXURY_PRODUCT;
        }
        if ($domain === 'fashion' || $domain === 'beauty') {
            return self::BEAUTY_CAMPAIGN;
        }
        if ($domain === 'food') {
            return self::FOOD_EDITORIAL;
        }
        if ($domain === 'lifestyle') {
            return self::CINEMATIC_LIFESTYLE;
        }
        if ($domain === 'portrait') {
            return self::EDITORIAL_PORTRAIT;
        }
        if ($domain === 'brand' || $domain === 'corporate' || $domain === 'social') {
            return self::PREMIUM_COMMERCIAL;
        }
        if ($domain === 'travel' || $domain === 'cinematic') {
            return self::CINEMATIC_LIFESTYLE;
        }
        if (self::looks_commercial($raw)) {
            return self::PREMIUM_COMMERCIAL;
        }
        return self::GENERAL_PHOTOREAL;
    }

    public static function looks_storybook(string $raw): bool {
        if (preg_match('/그림책|동화|picture.?book|storybook/u', $raw)) {
            return true;
        }
        if (preg_match('/어린이|아동|키즈|kids|for\s*children/u', $raw)) {
            return true;
        }
        // Dream + imaginative adventure animals (literal scene, not "child imagining").
        if (preg_match('/꿈|상상/u', $raw) && preg_match('/펭귄|고래|용|요정|마법|날아|하늘을|세계\s*여행|판타지/u', $raw)) {
            return true;
        }
        if (preg_match('/펭귄/u', $raw) && preg_match('/고래|하늘|날/u', $raw)) {
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

    /**
     * Shared premium visual bias — applied once, not keyword soup spam.
     *
     * @return string[]
     */
    public static function premium_visual_bias(string $preset): array {
        $common = [
            'sophisticated refined premium editorial-quality finish',
            'elegant polished composition with rich depth and tasteful color harmony',
            'cinematic lighting, beautifully composed, high-detail, non-kitschy',
        ];
        switch ($preset) {
            case self::MODERN_STORYBOOK:
            case self::PREMIUM_FANTASY_ILLUSTRATION:
            case self::FANTASY_ILLUSTRATION:
                return array_merge($common, [
                    'modern premium picture-book cover illustration aesthetic',
                    'grand outdoor adventure scale — not an indoor mural or flat wall decoration',
                    'Disney-theme-park kitsch avoided; prefer refined European/Japanese premium picture-book cover mood',
                ]);
            case self::EDITORIAL_PORTRAIT:
            case self::CINEMATIC_LIFESTYLE:
                return array_merge($common, [
                    'contemporary editorial portrait / lifestyle photography',
                ]);
            case self::BEAUTY_CAMPAIGN:
            case self::LUXURY_PRODUCT:
                return array_merge($common, [
                    'quiet luxury product still, magazine double-page quality',
                ]);
            case self::ARCHITECTURAL_VISUALIZATION:
                return array_merge($common, [
                    'premium real-estate campaign architectural visualization',
                ]);
            default:
                return $common;
        }
    }

    /**
     * Shared quality constraints appended once.
     *
     * @return string[]
     */
    public static function quality_constraints(string $preset): array {
        switch ($preset) {
            case self::EDITORIAL_PORTRAIT:
            case self::CINEMATIC_LIFESTYLE:
                return [
                    'contemporary sophisticated but believable human styling',
                    'natural micro-expression, plausible anatomy and hands',
                    'realistic skin texture with subtle natural imperfections',
                    'refined wardrobe and grooming appropriate to context — not generic stock smiles',
                    'no plastic skin, no mannequin faces, no awkward proportions',
                ];
            case self::MODERN_STORYBOOK:
            case self::PREMIUM_FANTASY_ILLUSTRATION:
            case self::FANTASY_ILLUSTRATION:
                return [
                    'modern premium picture-book / editorial illustration',
                    'cinematic storytelling composition with atmospheric depth and layered parallax',
                    'sophisticated child-friendly palette — warm and emotional but never childish clipart',
                    'depict the requested adventure as the main scene — do not invent an unrelated child observer unless asked',
                    'avoid dated clip-art, flat mural, toy-like oversaturation, cheap poster look',
                ];
            case self::BEAUTY_CAMPAIGN:
            case self::LUXURY_PRODUCT:
                return [
                    'premium product photography with accurate geometry',
                    'realistic glass/metal/plastic materials and controlled speculars',
                    'hero composition with refined negative space',
                    'do not invent readable logos, Hangul/English product text, or fake brand marks',
                ];
            case self::ARCHITECTURAL_VISUALIZATION:
                return [
                    'coherent building geometry, straight verticals, plausible perspective',
                    'detailed façade materials and realistic landscaping',
                    'professional real-estate campaign visualization',
                    'no warped towers or identical plastic clone façades',
                ];
            case self::PREMIUM_COMMERCIAL:
                return [
                    'strong focal hierarchy and brand-ready framing',
                    'polished materials, deliberate lighting, modern restrained palette',
                    'avoid generic stock imagery and tacky luxury clichés',
                ];
            default:
                return [
                    'professionally art-directed photograph',
                    'intentional composition and believable materials',
                    'avoid generic AI-stock look',
                ];
        }
    }

    /**
     * Common negative guidance for all image domains.
     *
     * @return string[]
     */
    public static function common_negatives(): array {
        return [
            'childish clipart',
            'kitschy',
            'cheap poster look',
            'flat mural look',
            'awkward anatomy',
            'plasticky skin',
            'generic stock composition',
            'overly saturated toy-like colors',
            'random text overlays',
            'low detail mush',
            'dated cheap storybook look',
        ];
    }
}
