<?php
if (!defined('ABSPATH')) exit;

/**
 * Internal image art-direction presets — not a new provider.
 * Maps visual intent → preset id + prompt guidance fragments.
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
        if ($domain === 'storybook' || self::looks_storybook($raw)) {
            return self::MODERN_STORYBOOK;
        }
        if ($domain === 'fantasy' || self::looks_fantasy($raw)) {
            return self::FANTASY_ILLUSTRATION;
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
        return (bool) preg_match('/어린이|동화|그림책|꿈|상상|storybook|fairy|아동|키즈|kids/u', $raw);
    }

    public static function looks_fantasy(string $raw): bool {
        return (bool) preg_match('/판타지|마법|드래곤|요정|fantasy|magic|unicorn|요괴/u', $raw);
    }

    public static function looks_beauty(string $raw): bool {
        return (bool) preg_match('/화장품|스킨케어|크림|세럼|향수|뷰티|cosmetic|skincare|beauty|serum|perfume/u', $raw);
    }

    public static function looks_commercial(string $raw): bool {
        return (bool) preg_match('/광고|캠페인|브랜드|분양|advert|campaign|brand/u', $raw);
    }

    /**
     * Shared quality constraints appended once (not keyword soup).
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
            case self::FANTASY_ILLUSTRATION:
                return [
                    'modern premium picture-book / editorial illustration',
                    'cinematic storytelling composition with atmospheric depth',
                    'sophisticated child-friendly palette, polished lighting',
                    'depict the requested adventure as the main scene — do not invent an unrelated child observer unless asked',
                    'avoid dated clip-art or flat mural aesthetics',
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
}
