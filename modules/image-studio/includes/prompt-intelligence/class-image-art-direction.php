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
    // v2 expanded presets (aliases map into canonical behavior)
    public const LUXURY_EDITORIAL = 'LUXURY_EDITORIAL';
    public const BEAUTY_CAMPAIGN_PREMIUM = 'BEAUTY_CAMPAIGN_PREMIUM';
    public const HIGH_END_ARCHVIZ = 'HIGH_END_ARCHVIZ';
    public const ELEVATED_PRODUCT_HERO = 'ELEVATED_PRODUCT_HERO';
    public const FASHION_EDITORIAL_PREMIUM = 'FASHION_EDITORIAL_PREMIUM';
    public const CLEAN_MINIMAL_LUXURY = 'CLEAN_MINIMAL_LUXURY';
    public const SOCIAL_AD_PREMIUM = 'SOCIAL_AD_PREMIUM';
    public const KOREAN_PREMIUM_BRAND_VISUAL = 'KOREAN_PREMIUM_BRAND_VISUAL';
    public const PREMIUM_FAMILY_LIFESTYLE = 'PREMIUM_FAMILY_LIFESTYLE';
    public const HIGH_END_REAL_ESTATE_CAMPAIGN = 'HIGH_END_REAL_ESTATE_CAMPAIGN';

    // Legacy aliases
    public const MODERN_STORYBOOK = 'MODERN_STORYBOOK_PREMIUM';
    public const BEAUTY_CAMPAIGN = 'BEAUTY_CAMPAIGN_PREMIUM';
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
        if ($domain === 'architecture' || preg_match('/조감|분양|archviz|real.?estate/u', $raw)) {
            if (preg_match('/분양|캠페인|advert|campaign|조감/u', $raw)) {
                return self::HIGH_END_REAL_ESTATE_CAMPAIGN;
            }
            return self::HIGH_END_ARCHVIZ;
        }
        if ($domain === 'product' || $domain === 'ecommerce' || $domain === 'beauty_product_packshot') {
            if (self::looks_beauty($raw) && $domain !== 'beauty_product_packshot'
                && !preg_match('/제품만|누끼|상세페이지|packshot|product\s*only/u', $raw)) {
                return self::BEAUTY_CAMPAIGN_PREMIUM;
            }
            if (self::looks_beauty($raw)) {
                return self::BEAUTY_CAMPAIGN_PREMIUM;
            }
            return self::ELEVATED_PRODUCT_HERO;
        }
        if (in_array($domain, ['beauty', 'beauty_model_campaign', 'beauty_poster_editorial'], true)
            || self::looks_beauty($raw)) {
            return self::BEAUTY_CAMPAIGN_PREMIUM;
        }
        if ($domain === 'fashion') {
            return self::FASHION_EDITORIAL_PREMIUM;
        }
        if ($domain === 'food') {
            return self::FOOD_EDITORIAL;
        }
        if ($domain === 'lifestyle' || $domain === 'cinematic') {
            if (preg_match('/가족|family|아이|어린이/u', $raw)) {
                return self::PREMIUM_FAMILY_LIFESTYLE;
            }
            return self::CINEMATIC_LIFESTYLE_PREMIUM;
        }
        if ($domain === 'portrait' || $domain === 'editorial') {
            return preg_match('/화보|fashion|패션/u', $raw)
                ? self::FASHION_EDITORIAL_PREMIUM
                : self::EDITORIAL_PORTRAIT_PREMIUM;
        }
        if ($domain === 'illustration') {
            return $premium ? self::PREMIUM_FANTASY_ILLUSTRATION : self::MODERN_STORYBOOK_PREMIUM;
        }
        if ($domain === 'brand' || $domain === 'corporate' || preg_match('/한국\s*프리미엄|k-?brand|korean\s*premium/u', $raw)) {
            return self::KOREAN_PREMIUM_BRAND_VISUAL;
        }
        if ($domain === 'social' || preg_match('/sns|인스타|social\s*ad/u', $raw)) {
            return self::SOCIAL_AD_PREMIUM;
        }
        if (self::looks_commercial($raw)) {
            return self::PREMIUM_COMMERCIAL;
        }
        if (preg_match('/미니멀|minimal|클린\s*럭셔리/u', $raw)) {
            return self::CLEAN_MINIMAL_LUXURY;
        }
        if ($premium) {
            return self::LUXURY_EDITORIAL;
        }
        return self::GENERAL_PHOTOREAL_PREMIUM;
    }

    /** Map expanded preset ids to quality/negative buckets. */
    public static function canonical_bucket(string $preset): string {
        switch ($preset) {
            case self::BEAUTY_CAMPAIGN_PREMIUM:
            case self::BEAUTY_EDITORIAL_PREMIUM:
                return self::BEAUTY_EDITORIAL_PREMIUM;
            case self::ELEVATED_PRODUCT_HERO:
            case self::LUXURY_PRODUCT_CAMPAIGN:
                return self::LUXURY_PRODUCT_CAMPAIGN;
            case self::HIGH_END_ARCHVIZ:
            case self::HIGH_END_REAL_ESTATE_CAMPAIGN:
            case self::ARCHITECTURAL_VISUALIZATION_PREMIUM:
                return self::ARCHITECTURAL_VISUALIZATION_PREMIUM;
            case self::FASHION_EDITORIAL_PREMIUM:
            case self::EDITORIAL_PORTRAIT_PREMIUM:
            case self::LUXURY_EDITORIAL:
                return self::EDITORIAL_PORTRAIT_PREMIUM;
            case self::PREMIUM_FAMILY_LIFESTYLE:
            case self::CINEMATIC_LIFESTYLE_PREMIUM:
                return self::CINEMATIC_LIFESTYLE_PREMIUM;
            case self::SOCIAL_AD_PREMIUM:
            case self::KOREAN_PREMIUM_BRAND_VISUAL:
            case self::CLEAN_MINIMAL_LUXURY:
            case self::PREMIUM_COMMERCIAL:
                return self::PREMIUM_COMMERCIAL;
            case self::MODERN_STORYBOOK_PREMIUM:
            case self::PREMIUM_FANTASY_ILLUSTRATION:
                return $preset;
            default:
                return self::GENERAL_PHOTOREAL_PREMIUM;
        }
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
        return (bool) preg_match('/화장품|스킨케어|크림|세럼|향수|뷰티|안티에이징|cosmetic|skincare|beauty|serum|perfume|anti.?aging/u', $raw);
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
            'looks expensive — commercially usable contemporary taste',
        ];
        $bucket = self::canonical_bucket($preset);
        switch ($bucket) {
            case self::MODERN_STORYBOOK_PREMIUM:
            case self::PREMIUM_FANTASY_ILLUSTRATION:
                return array_merge($common, [
                    'modern premium picture-book cover illustration aesthetic',
                    'grand outdoor adventure scale — not an indoor mural or flat wall decoration',
                    'Disney-theme-park kitsch avoided; refined premium picture-book cover mood',
                    'strong focal hierarchy, luminous atmosphere, layered landmarks',
                ]);
            case self::EDITORIAL_PORTRAIT_PREMIUM:
            case self::CINEMATIC_LIFESTYLE_PREMIUM:
                return array_merge($common, [
                    'contemporary editorial portrait / lifestyle photography',
                    'natural styling, believable people, non-stock composition',
                ]);
            case self::BEAUTY_EDITORIAL_PREMIUM:
            case self::BEAUTY_CAMPAIGN_PREMIUM:
            case self::LUXURY_PRODUCT_CAMPAIGN:
                return array_merge($common, [
                    'modern premium beauty campaign / K-beauty advertising finish',
                    'luminous skin, soft flattering beauty light, elegant commercial polish',
                    'model + product campaign hierarchy when campaign/poster intent is present',
                    'refined packaging presentation — short brand marks allowed when user-provided',
                ]);
            case self::ARCHITECTURAL_VISUALIZATION_PREMIUM:
                return array_merge($common, [
                    'premium real-estate campaign architectural visualization',
                    'brochure-worthy lighting, straight perspective, refined landscaping',
                ]);
            case self::PREMIUM_COMMERCIAL:
                return array_merge($common, [
                    'Korean premium brand visual language — restrained, modern, campaign-ready',
                    'clean social-ad crop with clear hero subject',
                ]);
            default:
                return $common;
        }
    }

    /** @return string[] */
    public static function quality_constraints(string $preset): array {
        $preset = self::canonical_bucket($preset);
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
            case self::BEAUTY_CAMPAIGN_PREMIUM:
            case self::LUXURY_PRODUCT_CAMPAIGN:
                return [
                    'premium beauty campaign photography with accurate product geometry',
                    'luminous natural skin texture — no plastic or mannequin faces',
                    'allow short user-given brand tokens; do not invent long fake packaging copy',
                    'campaign-ready poster polish with usable negative space',
                    'avoid pharmacy bottle aesthetic and cheap e-commerce snapshots',
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
        $preset = self::canonical_bucket($preset);
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
                    'anger face',
                    'hostile expression',
                    'pharmacy bottle aesthetic',
                    'cheap home-shopping mood',
                    'plastic skin',
                    'mannequin face',
                    'generic stock cosmetic',
                    'product-only empty tabletop when campaign/poster was requested',
                    'invented long packaging paragraphs',
                    'kitschy beauty styling',
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

    /**
     * Internal anti-cheap / anti-kitsch negatives (not shown to end users).
     *
     * @return string[]
     */
    public static function anti_cheap_negatives(): array {
        return [
            'tacky cartoonish simplification',
            'clumsy symmetry',
            'awkward empty background',
            'muddy desaturated colors',
            'plastic skin',
            'generic stock pose',
            'bad fashion styling',
            'unintentional product text',
            'simplistic mural illustration',
            'low-detail landmarks',
            'weak focal composition',
            'amateur poster look',
            'outdated clipart aesthetic',
            'cheap stock-photo lighting',
        ];
    }
}
