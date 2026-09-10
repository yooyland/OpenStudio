<?php
if (!defined('ABSPATH')) exit;

/**
 * Quality Escalator — short prompts still receive at least refined/premium bias.
 */
final class YooY_Image_Quality_Escalator {

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $normalized
     * @param array<string, mixed> $scene
     * @return array{
     *   tier:string,
     *   escalate:bool,
     *   reasons:string[],
     *   bias_lines:string[],
     *   brief:array<string,mixed>
     * }
     */
    public static function escalate(array $brief, array $normalized, array $scene = []): array {
        $raw = mb_strtolower((string) ($normalized['normalized'] ?? $brief['raw_user_request'] ?? ''));
        $reasons = [];
        $tier = 'refined';
        $escalate = !empty($normalized['is_short']);

        if (class_exists('YooY_Image_Art_Direction') && YooY_Image_Art_Direction::looks_premium_visual($raw)) {
            $tier = 'premium';
            $escalate = true;
            $reasons[] = 'premium_language';
        }
        if (preg_match('/상업|광고|캠페인|브랜드|분양|commercial|campaign|brand/u', $raw)) {
            $tier = 'premium';
            $escalate = true;
            $reasons[] = 'commercial_intent';
        }
        if (preg_match('/그림책\s*표지|picture.?book\s*cover|editorial|시네마틱|cinematic/u', $raw)) {
            $tier = 'premium';
            $escalate = true;
            $reasons[] = 'cover_or_cinematic';
        }
        if (in_array((string) ($brief['content_domain'] ?? ''), ['storybook', 'fantasy', 'beauty', 'architecture', 'product', 'portrait', 'lifestyle'], true)) {
            if ($tier !== 'premium') {
                $tier = 'refined';
            }
            $escalate = true;
            $reasons[] = 'domain_default_escalate';
        }
        if ($escalate && $tier === 'refined' && empty($reasons)) {
            $reasons[] = 'short_prompt_floor';
        }

        $bias = [
            'refined, polished, tasteful color harmony',
            'beautifully composed with rich depth and intentional lighting',
            'non-kitschy commercial-usable finish',
        ];
        if ($tier === 'premium') {
            $bias = array_merge($bias, [
                'sophisticated elegant premium editorial-quality',
                'high-detail materials and atmospheric depth',
                'modern premium visual language suitable for campaign or picture-book cover',
            ]);
        }

        $brief['tone'] = self::merge_tone((string) ($brief['tone'] ?? ''), $tier);
        $brief['visual_style'] = self::merge_style((string) ($brief['visual_style'] ?? ''), $tier, (string) ($brief['content_domain'] ?? ''));
        $brief['quality_tier'] = $tier;
        $brief['quality_escalate'] = $escalate;
        if (!empty($scene['must_include'])) {
            $brief['required_elements'] = array_values(array_unique(array_merge(
                (array) ($brief['required_elements'] ?? []),
                $scene['must_include']
            )));
        }

        return [
            'tier'       => $tier,
            'escalate'   => $escalate,
            'reasons'    => $reasons,
            'bias_lines' => $bias,
            'brief'      => $brief,
        ];
    }

    private static function merge_tone(string $tone, string $tier): string {
        $extra = $tier === 'premium' ? 'premium, sophisticated, refined' : 'refined, polished';
        if ($tone === '') {
            return $extra;
        }
        if (stripos($tone, 'premium') !== false || stripos($tone, 'refined') !== false) {
            return $tone;
        }
        return $tone . ', ' . $extra;
    }

    private static function merge_style(string $style, string $tier, string $domain): string {
        if ($style !== '' && stripos($style, 'premium') !== false) {
            return $style;
        }
        $map = [
            'storybook'    => 'modern premium picture-book cover illustration',
            'fantasy'      => 'premium fantasy editorial illustration',
            'beauty'       => 'premium beauty editorial campaign still',
            'architecture' => 'premium architectural visualization',
            'product'      => 'luxury product campaign photography',
            'portrait'     => 'editorial premium portrait photography',
            'lifestyle'    => 'cinematic premium lifestyle photography',
            'cinematic'    => 'cinematic premium key visual',
            'illustration' => 'polished editorial illustration',
        ];
        $base = $map[$domain] ?? 'professionally art-directed premium photograph';
        if ($tier === 'premium') {
            return $base;
        }
        return $style !== '' ? $style : $base;
    }
}
