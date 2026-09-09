<?php
if (!defined('ABSPATH')) exit;

/**
 * Image Studio–specific prompt composer from Creative Brief.
 * Commercial / brand / architecture / lifestyle art direction over generic fluff.
 */
final class YooY_Image_Domain_Prompt_Composer {

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    public function compose(array $brief, array $settings = []): array {
        $domain = (string) ($brief['content_domain'] ?? 'general');
        $raw = mb_strtolower((string) ($brief['raw_user_request'] ?? $brief['primary_subject'] ?? ''));

        if (!empty($brief['wants_political']) || $domain === 'politics') {
            return $this->compose_politics($brief, $settings);
        }
        // People + apartment → lifestyle campaign (not empty architecture plate)
        if ($domain === 'lifestyle' || $this->looks_lifestyle($raw)) {
            return $this->compose_lifestyle($brief, $settings);
        }
        if ($domain === 'architecture' || $this->looks_architecture($raw)) {
            return $this->compose_architecture($brief, $settings);
        }
        if (!empty($brief['wants_product']) || in_array($domain, ['product', 'ecommerce', 'fashion', 'food'], true)) {
            return $this->compose_product($brief, $settings);
        }
        if ($domain === 'portrait' || $this->looks_portrait($raw)) {
            return $this->compose_portrait($brief, $settings);
        }
        if ($domain === 'travel') {
            return $this->compose_travel($brief, $settings);
        }
        if ($domain === 'brand' || $this->looks_commercial($raw)) {
            return $this->compose_brand($brief, $settings);
        }
        return $this->compose_general($brief, $settings);
    }

    private function looks_architecture(string $raw): bool {
        return (bool) preg_match('/조감|아파트|건축|단지|외관|건물|빌딩|분양|architectural|aerial|facade|real.?estate|residential/u', $raw);
    }

    private function looks_lifestyle(string $raw): bool {
        return (bool) preg_match('/부부|가족|커플|라이프|lifestyle|일상|행복한|사람들|모델|couple|family/u', $raw);
    }

    private function looks_portrait(string $raw): bool {
        return (bool) preg_match('/인물|초상|portrait|얼굴|여자|남자|30대|20대|woman|man|person/u', $raw);
    }

    private function looks_commercial(string $raw): bool {
        return (bool) preg_match('/광고|캠페인|브랜드|분양 광고|advert|campaign|brand/u', $raw);
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_politics(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'Korean political leadership figure');
        $message = (string) ($brief['core_message'] ?? '');
        $tone = (string) ($brief['tone'] ?? 'premium, trustworthy, impactful');
        $palette = (string) ($brief['color_palette'] ?? 'modern navy, clean blue and white Korean civic palette');
        $format = (string) ($brief['medium'] ?? 'premium Korean political editorial campaign poster');

        $parts = [
            'A ' . $format . ' centered on ' . $subject,
            'Subject: ' . $subject,
            'Purpose: Korean civic / political public campaign key visual',
            'Composition: editorial magazine-cover hierarchy with headline and message zones',
            'Camera: medium editorial shot, eye-level, confident leadership posture',
            'Lighting: clean civic soft key light, controlled contrast',
            'Materials: tailored navy suit fabric detail, realistic skin',
            'Environment: subtle Korean civic / city atmosphere in soft background',
            'Color: ' . $palette,
            'Mood: ' . $tone,
            'Art direction: high-end public campaign design, web and social ready',
            'Constraints: do not render readable Korean text glyphs; no product packshot objects',
        ];
        if ($message !== '') {
            $parts[] = 'Narrative focus: ' . mb_substr($message, 0, 180);
        }

        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => $this->politics_negative($brief),
            'domain'          => 'politics',
            'preset'          => 'editorial',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_product(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'hero product');
        $raw = mb_strtolower((string) ($brief['raw_user_request'] ?? $subject));
        $is_beauty = (bool) preg_match('/화장품|스킨케어|크림|세럼|향수|cosmetic|skincare|cream|serum|perfume|beauty/u', $raw);
        $is_beach = (bool) preg_match('/바다|해변|여름|beach|summer|sea|ocean/u', $raw);

        $env = 'controlled premium studio set with clean gradient backdrop';
        $light = 'soft dual softbox key + gentle rim, controlled specular highlights on packaging';
        if ($is_beach) {
            $env = 'aspirational summer coastal environment with product as clear hero in foreground';
            $light = 'bright natural daylight with soft fill, realistic reflections on glass/plastic';
        } elseif ($is_beauty) {
            $env = 'luxury beauty campaign set, minimal props, elegant negative space';
            $light = 'beauty-advertising soft key light, silky highlights on cream and glass';
        }

        $parts = [
            'Commercial product advertising photograph of ' . $subject,
            'Subject: ' . $subject . ' as unmistakable hero object with readable silhouette',
            'Purpose: premium brand / ecommerce advertising key visual for a beauty campaign',
            'Composition: ' . ((string) ($brief['composition'] ?: 'rule-of-thirds hero placement, asymmetric negative space, not dead-center stock framing')),
            'Camera: 85–100mm product lens feel, slight three-quarter angle to show form and depth',
            'Lighting: ' . ((string) ($brief['lighting'] ?: $light)),
            'Materials: accurate package geometry, premium glass/metal/plastic micro-reflections, soft contact shadow, no melted edges',
            'Environment: ' . $env,
            'Color: ' . ((string) ($brief['color_palette'] ?: 'refined brand palette, controlled accents, clean highlights')),
            'Mood: ' . ((string) ($brief['tone'] ?: 'desirable, elevated, quiet luxury')),
            'Art direction: Korean beauty brand campaign still — art director approved, magazine double-page quality',
            'Avoid: plain unlabeled pharmacy bottle look, dead-center phone snapshot framing, harsh noon shadow only, glitter dust clichés, fake logos',
            'Constraints: no random Hangul/English logos unless requested; keep packaging elegant and campaign-ready',
        ];

        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'warped bottle geometry, melted packaging, unreadable fake logos, glitter overload, plastic skin, political poster, low detail mush, generic stock clutter',
            'domain'          => 'product',
            'preset'          => 'product',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_architecture(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'residential apartment complex');
        $raw = mb_strtolower((string) ($brief['raw_user_request'] ?? $subject));
        $is_aerial = (bool) preg_match('/조감|aerial|bird.?s.?eye|birdseye|위에서|공중/u', $raw);
        $is_ad = (bool) preg_match('/광고|분양|캠페인|brochure|advert|campaign/u', $raw);
        $viewpoint = $is_aerial
            ? 'wide-angle elevated aerial / bird\'s-eye architectural viewpoint'
            : 'elevated establishing architectural viewpoint with readable massing';

        $parts = [
            'Photorealistic architectural visualization of ' . $subject,
            'Subject: premium Korean residential apartment complex as sales-gallery hero',
            'Purpose: ' . ($is_ad ? 'high-end Korean real-estate brochure / digital sales campaign key visual' : 'architectural visualization'),
            'Composition: ' . ((string) ($brief['composition'] ?: 'strong massing hierarchy, leading road/landscape lines, brochure-ready framing — not a flat orthographic plate')),
            'Camera: ' . $viewpoint . ', corrected verticals, believable human scale cues',
            'Lighting: ' . ((string) ($brief['lighting'] ?: 'believable late-afternoon daylight with soft long shadows; crisp glass speculars')),
            'Materials: detailed façade cladding variation, balcony railings, window mullions, glass reflections, coherent floor rhythm',
            'Environment: layered landscaping, realistic pavement, cars for scale, contextual Seoul-like urban surroundings',
            'Color: ' . ((string) ($brief['color_palette'] ?: 'authentic façade materials with natural greens')),
            'Mood: ' . ((string) ($brief['tone'] ?: 'aspirational, trustworthy, premium residential')),
            'Art direction: top Korean developer CGI/photography hybrid used in sales galleries',
            'Avoid: identical clone towers with plastic smooth façades, toy-like round trees only, empty lifeless plaza, warped perspective',
            'Constraints: no bent windows, no floating slabs, no melted edges',
        ];
        if (!empty($brief['core_message'])) {
            $parts[] = 'Narrative focus: ' . mb_substr((string) $brief['core_message'], 0, 180);
        }

        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => implode(', ', array_merge(
                (array) ($brief['forbidden_elements'] ?? []),
                [
                    'warped geometry', 'distorted windows', 'bent buildings', 'floating structures',
                    'melted façade', 'low detail mushy surfaces', 'cartoon architecture',
                    'generic stock couple overlay unless requested',
                ]
            )),
            'domain'          => 'architecture',
            'preset'          => 'architecture',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_lifestyle(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'lifestyle subjects in a believable setting');
        $raw = mb_strtolower((string) ($brief['raw_user_request'] ?? $subject));
        $with_apt = (bool) preg_match('/아파트|단지|residential|apartment/u', $raw);

        $parts = [
            'Editorial lifestyle advertising photograph of ' . $subject,
            'Subject: authentic Korean couple/people in their 30s with natural anatomy, realistic hands, natural micro-expressions',
            'Purpose: premium residential / lifestyle brand campaign key visual',
            'Composition: ' . ((string) ($brief['composition'] ?: 'off-center editorial framing, environmental storytelling, mobile-safe hierarchy — not centered stock stare')),
            'Camera: 50–85mm editorial lens, slight environmental context, cinematic depth',
            'Lighting: ' . ((string) ($brief['lighting'] ?: 'directional golden-hour key with soft fill; editorial beauty light without plastic skin')),
            'Materials: visible natural skin texture, fabric weave, hair detail, believable props',
            'Environment: ' . ($with_apt
                ? 'Seoul premium apartment complex with readable architecture and landscaping behind subjects'
                : ((string) ($brief['visual_style'] ?: 'contextual lived-in environment matching the request'))),
            'Color: ' . ((string) ($brief['color_palette'] ?: 'warm refined lifestyle grading')),
            'Mood: ' . ((string) ($brief['tone'] ?: 'quiet genuine warmth — candid, not forced')),
            'Art direction: Korean premium lifestyle campaign like high-end residential brochure photography',
            'Pose direction: candid interaction (shared glance / walking / soft conversation) — forbid generic arms-around-waist stock smile at camera',
            'Avoid: plastic skin, over-smoothed faces, identical beige outfit cliché, stiff catalog pose, uncanny symmetry',
            'Constraints: no extra fingers, no arbitrary text overlays',
        ];

        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'generic stock-photo pose, plastic skin, uncanny smile, distorted hands, warped buildings, fake glow particles, arbitrary text, low advertising value',
            'domain'          => 'lifestyle',
            'preset'          => 'editorial',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_portrait(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'human subject');
        $parts = [
            'Editorial commercial portrait of ' . $subject,
            'Subject: ' . $subject . ' with natural anatomy and realistic skin',
            'Purpose: portrait / talent campaign visual',
            'Composition: ' . ((string) ($brief['composition'] ?: 'close to medium shot, eyes as primary focal point')),
            'Camera: 85mm portrait lens feel, shallow but controlled depth of field',
            'Lighting: ' . ((string) ($brief['lighting'] ?: 'soft Rembrandt or beauty key with gentle fill')),
            'Materials: natural skin pores, realistic hair strands, wardrobe fabric detail',
            'Environment: ' . ((string) ($brief['visual_style'] ?: 'clean studio or contextual background with separation')),
            'Mood: ' . ((string) ($brief['tone'] ?: 'confident, authentic, editorial')),
            'Art direction: magazine-cover portrait quality',
            'Constraints: plausible hands if visible, consistent age/ethnicity/context, no plastic skin',
        ];
        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'plastic skin, uncanny valley, distorted face, bad hands, extra fingers, generic AI stock look',
            'domain'          => 'portrait',
            'preset'          => 'editorial',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_brand(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'brand campaign subject');
        $parts = [
            'Premium brand campaign key visual featuring ' . $subject,
            'Subject: ' . $subject,
            'Purpose: advertising / brand campaign still for web and OOH',
            'Composition: ' . ((string) ($brief['composition'] ?: 'strong hero focal point, clear visual hierarchy, copy-safe margins')),
            'Camera: intentional commercial camera angle matching the subject',
            'Lighting: ' . ((string) ($brief['lighting'] ?: 'art-directed commercial lighting with controlled contrast')),
            'Materials: believable surfaces and product/environment detail',
            'Environment: polished campaign setting derived from the request',
            'Color: ' . ((string) ($brief['color_palette'] ?: 'brand-appropriate refined grading')),
            'Mood: ' . ((string) ($brief['tone'] ?: 'premium, impactful')),
            'Art direction: high advertising value, not a generic AI stock frame',
            'Constraints: no arbitrary logos/text unless requested; no excessive glow particles',
        ];
        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'generic stock photo, low advertising value, plastic materials, arbitrary text, glitter clichés',
            'domain'          => 'brand',
            'preset'          => 'commercial',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_travel(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'travel destination');
        $parts = [
            'Cinematic tourism campaign visual featuring ' . $subject,
            'Subject: ' . $subject,
            'Purpose: tourism / travel advertising key visual',
            'Composition: wide inviting establishing frame with clear destination hero',
            'Camera: wide 24–35mm cinematic landscape feel',
            'Lighting: ' . ((string) ($brief['lighting'] ?: 'aspirational natural travel light')),
            'Environment: authentic place atmosphere with depth and scale',
            'Color: ' . ((string) ($brief['color_palette'] ?: 'bright natural travel colors')),
            'Mood: ' . ((string) ($brief['tone'] ?: 'aspirational, inviting')),
            'Art direction: tourism board campaign quality',
            'Constraints: no unrelated product bottles; no fake text overlays',
        ];
        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'cosmetic bottle, perfume, product pedestal, unrelated merchandise, political poster',
            'domain'          => 'travel',
            'preset'          => 'cinematic',
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_general(array $brief, array $settings): array {
        $subject = (string) ($brief['primary_subject'] ?? 'the requested subject');
        $format = (string) ($brief['medium'] ?? 'professionally art-directed photograph');
        $parts = [
            $format . ' of ' . $subject,
            'Subject: ' . $subject,
            'Purpose: ' . ((string) ($brief['core_message'] ?: 'faithful commercial-usable image of the user request')),
            'Composition: ' . ((string) ($brief['composition'] ?: 'clear focal hierarchy and deliberate camera viewpoint')),
            'Camera: intentional viewpoint matching the subject (not a random phone snapshot)',
            'Lighting: ' . ((string) ($brief['lighting'] ?: 'scene-appropriate controlled lighting')),
            'Materials: specific surface and material detail derived from the request',
            'Environment: concrete place/context from the request — avoid empty generic backdrop',
            'Color: ' . ((string) ($brief['color_palette'] ?: 'coherent palette matching mood')),
            'Mood: ' . ((string) ($brief['tone'] ?: 'professional, intentional')),
            'Art direction: avoid generic AI-stock look; prioritize clarity and commercial usability',
            'Constraints: no arbitrary text; no plastic skin; no warped geometry',
        ];
        $neg = implode(', ', array_merge(
            (array) ($brief['forbidden_elements'] ?? []),
            ['generic stock look', 'unrelated merchandise', 'wrong subject', 'blurry', 'low detail', 'plastic skin']
        ));
        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => $neg,
            'domain'          => (string) ($brief['content_domain'] ?? 'general'),
            'preset'          => 'photoreal',
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
