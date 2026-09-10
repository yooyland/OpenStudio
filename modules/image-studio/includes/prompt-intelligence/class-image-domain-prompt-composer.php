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
        $preset = class_exists('YooY_Image_Art_Direction')
            ? YooY_Image_Art_Direction::resolve_preset($brief)
            : 'GENERAL_PHOTOREAL';

        if (!empty($brief['wants_political']) || $domain === 'politics') {
            return $this->finalize($this->compose_politics($brief, $settings), $preset);
        }
        // Storybook / fantasy before lifestyle "가족" so adventure scenes stay literal.
        if ($domain === 'fantasy'
            || (class_exists('YooY_Image_Art_Direction') && YooY_Image_Art_Direction::looks_fantasy($raw)
                && (YooY_Image_Art_Direction::looks_premium_visual($raw) || YooY_Image_Art_Direction::looks_storybook($raw)))) {
            // Premium fantasy illustration when explicitly fantasy + polish cues.
            if ($preset !== YooY_Image_Art_Direction::MODERN_STORYBOOK) {
                $preset = YooY_Image_Art_Direction::PREMIUM_FANTASY_ILLUSTRATION;
            }
            return $this->finalize($this->compose_storybook($brief, $settings, $preset), $preset);
        }
        if ($domain === 'storybook'
            || (class_exists('YooY_Image_Art_Direction') && YooY_Image_Art_Direction::looks_storybook($raw))) {
            $preset = YooY_Image_Art_Direction::MODERN_STORYBOOK;
            return $this->finalize($this->compose_storybook($brief, $settings, $preset), $preset);
        }
        // People + apartment → lifestyle campaign (not empty architecture plate)
        if ($domain === 'lifestyle' || $domain === 'cinematic' || $this->looks_lifestyle($raw)) {
            return $this->finalize($this->compose_lifestyle($brief, $settings), $preset);
        }
        if ($domain === 'architecture' || $this->looks_architecture($raw)) {
            return $this->finalize($this->compose_architecture($brief, $settings), $preset);
        }
        if ($domain === 'beauty' || (class_exists('YooY_Image_Art_Direction') && YooY_Image_Art_Direction::looks_beauty($raw))) {
            return $this->finalize($this->compose_product($brief, $settings), $preset);
        }
        if (!empty($brief['wants_product']) || in_array($domain, ['product', 'ecommerce', 'fashion', 'food'], true)) {
            return $this->finalize($this->compose_product($brief, $settings), $preset);
        }
        if ($domain === 'portrait' || $this->looks_portrait($raw)) {
            return $this->finalize($this->compose_portrait($brief, $settings), $preset);
        }
        if ($domain === 'travel') {
            return $this->finalize($this->compose_travel($brief, $settings), $preset);
        }
        if ($domain === 'brand' || $this->looks_commercial($raw)) {
            return $this->finalize($this->compose_brand($brief, $settings), $preset);
        }
        return $this->finalize($this->compose_general($brief, $settings), $preset);
    }

    /**
     * @param array{prompt:string,negative_prompt:string,domain:string,preset:string} $composed
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string,art_direction:string}
     */
    private function finalize(array $composed, string $art_preset): array {
        $composed['art_direction'] = $art_preset;
        if (class_exists('YooY_Image_Art_Direction')) {
            $bias = YooY_Image_Art_Direction::premium_visual_bias($art_preset);
            $extra = YooY_Image_Art_Direction::quality_constraints($art_preset);
            $bits = array_merge($bias, $extra);
            if ($bits) {
                $composed['prompt'] = rtrim((string) $composed['prompt'], '. ')
                    . '. PREMIUM BIAS: ' . implode('; ', $bits);
            }
            $neg = (string) ($composed['negative_prompt'] ?? '');
            $common = implode(', ', YooY_Image_Art_Direction::common_negatives());
            $composed['negative_prompt'] = $neg !== '' ? ($neg . ', ' . $common) : $common;
            $composed['preset'] = $art_preset;
        }
        return $composed;
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
        $purpose = 'premium brand / ecommerce advertising key visual';
        if ($is_beach && $is_beauty) {
            $env = 'aspirational summer coastal environment with product as clear hero in foreground; sea light and soft horizon — no readable brand text';
            $light = 'bright natural daylight with soft fill, realistic reflections on glass/plastic';
            $purpose = 'summer beauty campaign still — product hero, lifestyle atmosphere without invented labels';
        } elseif ($is_beauty) {
            $env = 'luxury beauty campaign set, minimal props, elegant negative space';
            $light = 'beauty-advertising soft key light, silky highlights on cream and glass';
            $purpose = 'luxury skincare / cosmetics campaign still';
        }

        $parts = [
            'CORE SCENE: ' . $subject . ' as unmistakable hero object with accurate silhouette and geometry',
            'PURPOSE: ' . $purpose . ' (treat words like advertising/campaign as intent, not literal text in the image)',
            'VISUAL DIRECTION: modern premium product photography, quiet luxury, commercially usable',
            'COMPOSITION: ' . ((string) ($brief['composition'] ?: 'rule-of-thirds hero placement, asymmetric negative space, not dead-center stock framing')),
            'LIGHTING: ' . ((string) ($brief['lighting'] ?: $light)),
            'STYLING & MATERIALS: accurate package geometry; premium glass/metal/plastic micro-reflections; soft contact shadow; no melted edges',
            'COLOR & ATMOSPHERE: ' . ((string) ($brief['color_palette'] ?: 'refined brand palette, controlled accents')),
            'ENVIRONMENT: ' . $env,
            'QUALITY CONSTRAINTS: campaign-ready detail; no invented Hangul/English logos or random label text unless the user explicitly asked for text',
            'AVOID: plain pharmacy bottle look, glitter dust clichés, fake logos, dead-center phone snapshot',
        ];

        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'warped bottle geometry, melted packaging, unreadable fake logos, invented Hangul text, glitter overload, plastic skin, political poster, low detail mush, generic stock clutter',
            'domain'          => $is_beauty ? 'beauty' : 'product',
            'preset'          => 'product',
        ];
    }

    /**
     * Modern storybook / premium fantasy — literal adventure, never flat kids mural.
     *
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $settings
     * @return array{prompt:string,negative_prompt:string,domain:string,preset:string}
     */
    private function compose_storybook(array $brief, array $settings, string $forced_preset = ''): array {
        $subject = (string) ($brief['primary_subject'] ?? 'imaginative adventure scene');
        $raw = (string) ($brief['raw_user_request'] ?? $subject);
        $raw_l = mb_strtolower($raw);
        $is_fantasy = ($forced_preset === 'PREMIUM_FANTASY_ILLUSTRATION')
            || (class_exists('YooY_Image_Art_Direction') && YooY_Image_Art_Direction::looks_fantasy($raw_l));
        $wants_premium = class_exists('YooY_Image_Art_Direction')
            ? YooY_Image_Art_Direction::looks_premium_visual($raw_l)
            : (bool) preg_match('/세련|고급|현대|premium|refined/u', $raw_l);

        $purpose = $is_fantasy
            ? 'premium fantasy editorial illustration — refined picture-book cover energy, not toddler clipart'
            : 'modern premium picture-book cover illustration for children\'s imagination — sophisticated, not babyish';

        $parts = [
            'CORE SCENE: Depict exactly this outdoor adventure as the main visual — ' . $subject,
            'PURPOSE: ' . $purpose,
            'VISUAL DIRECTION: contemporary cinematic ' . ($is_fantasy ? 'fantasy' : 'storybook')
                . ' illustration; sophisticated, refined, elegant, polished, editorial-quality; non-kitschy',
            'COMPOSITION: grand widescreen storytelling frame with imaginative scale, layered atmospheric depth, clear narrative hero, beautiful depth of field',
            'LIGHTING: cinematic magical light — soft moonlight, star glitter, optional aurora glow, volumetric haze; rich but tasteful',
            'STYLING & MATERIALS: expressive character design with coherent anatomy for the requested creatures; rich environmental detail; high-detail surfaces',
            'COLOR & ATMOSPHERE: ' . ((string) ($brief['color_palette']
                ?: 'warm emotional luminous night palette with tasteful color harmony — never neon toy oversaturation')),
            'QUALITY CONSTRAINTS: emotional visual narrative; respect literal subjects, actions, landmarks, and relationships from the user request',
            'AVOID: childish clipart; kitschy Disney-park look; cheap poster; flat mural; indoor bedroom framing; inserting an unrelated child observer unless asked',
            'IMPORTANT: do not rewrite into a child looking at a picture or a wall mural — show the sky adventure itself, majestic and beautifully composed',
        ];
        if (preg_match('/밤|별|night|star/u', $raw_l)) {
            $parts[] = 'SETTING DETAIL: star-filled night sky as the primary backdrop';
        }
        if (preg_match('/오로라|aurora/u', $raw_l)) {
            $parts[] = 'ATMOSPHERE DETAIL: soft aurora-like dream light across the sky';
        }
        if (preg_match('/에펠|피라미드|빅벤|자유의\s*여신|eiffel|pyramid|big\s*ben|liberty/u', $raw_l)) {
            $parts[] = 'LANDMARKS: distant fantastical silhouettes of world landmarks matching the request, ethereal and secondary to the flying adventure';
        }
        if ($wants_premium) {
            $parts[] = 'TONE CUE: modern, sophisticated, premium picture-book cover — warm and moving but never rustic or childish';
        }

        $domain = $is_fantasy ? 'fantasy' : 'storybook';
        $preset = $forced_preset !== ''
            ? $forced_preset
            : ($is_fantasy ? 'PREMIUM_FANTASY_ILLUSTRATION' : 'MODERN_STORYBOOK');

        return [
            'prompt'          => implode('. ', $parts),
            'negative_prompt' => 'childish clipart, kitschy, cheap poster look, flat mural look, dated storybook cliché, Disney theme-park kitsch, indoor bedroom child observer, generic stock photo, plastic CGI toys, overly saturated toy-like colors',
            'domain'          => $domain,
            'preset'          => $preset,
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
            'CORE SCENE: Photorealistic architectural visualization of ' . $subject,
            'PURPOSE: ' . ($is_ad ? 'high-end real-estate sales campaign key visual (purpose only — no literal advertising text)' : 'architectural visualization'),
            'VISUAL DIRECTION: premium but believable architecture, professional real-estate imagery',
            'COMPOSITION: ' . $viewpoint . '; ' . ((string) ($brief['composition'] ?: 'strong massing hierarchy, leading landscape lines, brochure-ready framing — not a flat orthographic plate')),
            'LIGHTING: ' . ((string) ($brief['lighting'] ?: 'believable late-afternoon daylight with soft long shadows; crisp glass speculars')),
            'STYLING & MATERIALS: detailed façade cladding variation, balcony railings, window mullions, glass reflections, coherent floor rhythm',
            'ENVIRONMENT: layered landscaping, realistic pavement, cars for scale, contextual urban surroundings',
            'COLOR & ATMOSPHERE: ' . ((string) ($brief['color_palette'] ?: 'authentic façade materials with natural greens')),
            'QUALITY CONSTRAINTS: coherent geometry, straight verticals, plausible perspective, realistic windows',
            'AVOID: identical clone towers, warped geometry, bland AI cityscape, fake luxury glow everywhere',
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
            'CORE SCENE: ' . $subject,
            'PURPOSE: premium residential / lifestyle brand campaign key visual (advertising intent — do not paint the word advertising)',
            'VISUAL DIRECTION: contemporary sophisticated editorial photography — refined, believable, not fashion-model forced',
            'COMPOSITION: ' . ((string) ($brief['composition'] ?: 'off-center editorial framing, environmental storytelling, mobile-safe hierarchy — not centered stock stare')),
            'LIGHTING: ' . ((string) ($brief['lighting'] ?: 'directional golden-hour key with soft fill; editorial light without plastic skin')),
            'STYLING & MATERIALS: natural skin texture with subtle imperfections; contemporary wardrobe and grooming; fabric weave; realistic hair',
            'ENVIRONMENT: ' . ($with_apt
                ? 'Seoul premium apartment complex with readable architecture and landscaping behind subjects'
                : ((string) ($brief['visual_style'] ?: 'contextual lived-in environment matching the request'))),
            'COLOR & ATMOSPHERE: ' . ((string) ($brief['color_palette'] ?: 'warm refined lifestyle grading, restrained palette')),
            'QUALITY CONSTRAINTS: authentic Korean adults with natural anatomy, plausible hands, natural micro-expressions; candid interaction',
            'AVOID: generic AI stock people, stiff catalog pose, exaggerated smile, plastic skin, outdated hair/fashion, mannequin faces, tacky luxury glow',
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
            'CORE SCENE: ' . $subject . ' with natural anatomy and realistic skin',
            'PURPOSE: editorial / commercial portrait',
            'VISUAL DIRECTION: contemporary sophisticated portrait — refined and believable, not forced glamour',
            'COMPOSITION: ' . ((string) ($brief['composition'] ?: 'close to medium shot, eyes as primary focal point')),
            'LIGHTING: ' . ((string) ($brief['lighting'] ?: 'soft Rembrandt or beauty key with gentle fill')),
            'STYLING & MATERIALS: natural skin pores, realistic hair, contemporary wardrobe appropriate to context',
            'COLOR & ATMOSPHERE: ' . ((string) ($brief['tone'] ?: 'confident, authentic, editorial')),
            'QUALITY CONSTRAINTS: plausible hands if visible; consistent age/context; subtle natural imperfections',
            'AVOID: plastic skin, uncanny valley, distorted face, bad hands, cheap corporate-stock smile, outdated fashion',
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
