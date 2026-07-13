<?php
if (!defined('ABSPATH')) exit;

/**
 * Image Studio–specific prompt composer from Creative Brief.
 * Primary subject always outranks tone/style templates.
 */
final class YooY_Image_Domain_Prompt_Composer {

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string}
     */
    public function compose(array $brief, array $settings = []): array {
        $domain = (string) ($brief['content_domain'] ?? 'general');
        if (!empty($brief['wants_political']) || $domain === 'politics') {
            return $this->compose_politics($brief, $settings);
        }
        if (!empty($brief['wants_product']) || in_array($domain, ['product', 'ecommerce', 'fashion', 'food'], true)) {
            return $this->compose_product($brief, $settings);
        }
        if ($domain === 'travel') {
            return $this->compose_travel($brief, $settings);
        }
        return $this->compose_general($brief, $settings);
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string}
     */
    private function compose_politics(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'Korean political leadership figure');
        $message = (string) ($brief['core_message'] ?? '');
        $tone = (string) ($brief['tone'] ?? 'premium, trustworthy, impactful');
        $palette = (string) ($brief['color_palette'] ?? 'modern navy, clean blue and white Korean civic palette');
        $format = (string) ($brief['medium'] ?? 'premium Korean political editorial campaign poster');

        $parts = [
            'A ' . $format . ' centered on ' . $subject,
            'Confident and trustworthy visual tone (' . $tone . ')',
            'modern navy suit, clear leadership posture',
            $palette,
            'editorial magazine-cover composition with strong visual hierarchy',
            'space reserved for Korean headline and key policy message zones (do not render readable Korean text glyphs)',
            'subtle Korean civic atmosphere and citizen/city cues in the soft background',
            'high-end public campaign design, realistic photography',
            'professional Korean advertising finish, web and social media ready',
        ];
        if ($message !== '') {
            array_splice($parts, 1, 0, ['Narrative focus: ' . mb_substr($message, 0, 180)]);
        }

        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => $this->politics_negative($brief),
            'domain'          => 'politics',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string}
     */
    private function compose_product(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'hero product');
        $parts = [
            'Premium product advertising photograph of ' . $subject,
            'hero product as clear focal point',
            (string) ($brief['composition'] ?: 'balanced negative space for branding'),
            (string) ($brief['lighting'] ?: 'luxury studio lighting'),
            (string) ($brief['tone'] ?: 'premium commercial'),
            'advertising-ready commercial retouching',
        ];
        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'political poster, election campaign, unrelated celebrity, low quality, blurry',
            'domain'          => 'product',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string}
     */
    private function compose_travel(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'travel destination');
        $parts = [
            'Cinematic tourism campaign visual featuring ' . $subject,
            'aspirational travel atmosphere',
            (string) ($brief['color_palette'] ?: 'bright natural travel colors'),
            'wide inviting composition, web and social ready',
        ];
        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'cosmetic bottle, perfume, product pedestal, unrelated merchandise, political poster',
            'domain'          => 'travel',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string}
     */
    private function compose_general(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'the requested subject');
        $format = (string) ($brief['medium'] ?? 'photorealistic image');
        $parts = [
            'A ' . $format . ' of ' . $subject,
            (string) ($brief['tone'] ?: 'professional'),
            (string) ($brief['composition'] ?: 'clear focal hierarchy'),
            'faithful to the user request with clear subject priority',
        ];
        $neg = implode(', ', array_merge(
            (array) ($brief['forbidden_elements'] ?? []),
            ['unrelated merchandise', 'wrong subject']
        ));
        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => $neg,
            'domain'          => (string) ($brief['content_domain'] ?? 'general'),
        ];
    }

    /** @param array<string, mixed> $brief */
    private function politics_negative(array $brief): string {
        $extra = (array) ($brief['forbidden_elements'] ?? []);
        $base = [
            'cosmetic bottle', 'perfume', 'skincare product', 'unrelated merchandise',
            'generic product photography', 'empty studio product shot',
            'abstract product pedestal', 'irrelevant commercial object',
            'hero product packshot', 'distorted face', 'incorrect anatomy',
            'unreadable typography', 'cartoon', 'anime',
        ];
        return implode(', ', array_values(array_unique(array_merge($base, $extra))));
    }
}
