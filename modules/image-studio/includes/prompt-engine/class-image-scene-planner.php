<?php
if (!defined('ABSPATH')) exit;

/**
 * Designs visual scenes from meaning — never literal text or word illustration.
 * Politics / public campaign must never collapse into product packshot scenes.
 */
final class YooY_Image_Scene_Planner {

    /**
     * @param array<string, mixed> $emotion
     * @return array{subject: string, environment: string, action: string, framing: string, elements: string[], narrative: string}
     */
    public function plan(string $user_prompt, array $emotion, array $intent): array {
        $abstract = !empty($emotion['abstract']) || !empty($intent['emotional']);
        $visuals = is_array($emotion['visuals'] ?? null) ? $emotion['visuals'] : [];

        if (!empty($intent['politics']) || !empty($intent['political_ad'])) {
            return $this->politics_scene($user_prompt, $emotion, $intent);
        }

        if (!empty($intent['architecture'])) {
            return $this->architecture_scene($user_prompt, $intent);
        }

        if ($abstract && ($emotion['primary'] ?? 'neutral') !== 'neutral' && empty($intent['product']) && empty($intent['architecture'])) {
            return $this->emotional_scene($emotion);
        }

        if (!empty($intent['lifestyle'])) {
            return $this->lifestyle_scene($user_prompt, $emotion, $intent);
        }
        if (!empty($intent['portrait'])) {
            return $this->portrait_scene($user_prompt, $emotion, $intent);
        }
        if (!empty($intent['product'])) {
            return $this->product_scene($user_prompt, $intent);
        }
        if (!empty($intent['landscape'])) {
            return $this->landscape_scene($user_prompt, $emotion);
        }
        // Commercial campaign that is NOT product (brand/public) — avoid packshot
        if (!empty($intent['commercial']) && empty($intent['product'])) {
            return $this->commercial_scene($user_prompt, $intent);
        }

        if (!empty($visuals)) {
            return [
                'subject'     => 'expressive human subject conveying inner feeling',
                'environment' => $visuals[0] ?? 'atmospheric interior',
                'action'      => 'subtle body language telling a story',
                'framing'     => 'cinematic composition with intentional negative space',
                'elements'    => array_slice($visuals, 0, 5),
                'narrative'   => 'visual storytelling through mood and environment, not literal text',
            ];
        }

        return $this->general_scene($user_prompt, $intent);
    }

    /**
     * @param array<string, mixed> $emotion
     * @param array<string, mixed> $intent
     */
    private function politics_scene(string $prompt, array $emotion, array $intent): array {
        $subject = $this->extract_subject_hint($prompt);
        if ($subject === 'a visually compelling scene expressing the concept through imagery alone') {
            $subject = 'Korean political leadership figure with clear civic message';
        }
        return [
            'subject'     => $subject,
            'environment' => 'premium Korean public-campaign / editorial poster setting',
            'action'      => 'confident leadership posture delivering a civic message',
            'framing'     => 'magazine-cover hierarchy with headline and message zones',
            'elements'    => ['navy suit', 'civic blue-white palette', 'soft city/citizen background', 'copy-safe space'],
            'narrative'   => 'Korean political editorial campaign poster, not product photography',
        ];
    }

    /**
     * @param array<string, mixed> $emotion
     * @return array{subject: string, environment: string, action: string, framing: string, elements: string[], narrative: string}
     */
    private function emotional_scene(array $emotion): array {
        $visuals = is_array($emotion['visuals'] ?? null) ? $emotion['visuals'] : [];
        $genre = (string) ($emotion['genre'] ?? 'cinematic fine-art portrait');

        return [
            'subject'     => 'a person whose expression and posture embody the feeling without any text',
            'environment' => $visuals[0] ?? 'atmospheric space that mirrors the emotion',
            'action'      => 'quiet moment of emotional realism',
            'framing'     => 'intimate cinematic framing, shallow depth of field',
            'elements'    => array_slice($visuals, 0, 6),
            'narrative'   => $genre . ', symbolic visual storytelling, no words or lettering in the image',
        ];
    }

    /**
     * @param array<string, mixed> $emotion
     * @param array<string, mixed> $intent
     */
    private function portrait_scene(string $prompt, array $emotion, array $intent): array {
        return [
            'subject'     => $this->extract_subject_hint($prompt) ?: 'compelling human portrait with authentic expression',
            'environment' => !empty($intent['studio']) ? 'professional studio setting with soft separation' : 'contextual environment with editorial depth',
            'action'      => 'natural pose, believable gaze, no stiff stock-photo smile',
            'framing'     => 'close-up to medium shot, eyes as focal point',
            'elements'    => array_slice((array) ($emotion['visuals'] ?? []), 0, 4),
            'narrative'   => 'editorial commercial portrait, natural skin texture, plausible anatomy',
        ];
    }

    /** @param array<string, mixed> $intent */
    private function lifestyle_scene(string $prompt, array $emotion, array $intent): array {
        $with_apt = (bool) preg_match('/아파트|단지|apartment|residential/u', mb_strtolower($prompt));
        return [
            'subject'     => $this->extract_subject_hint($prompt),
            'environment' => $with_apt
                ? 'Seoul residential apartment complex with believable architecture and landscaping'
                : 'lived-in aspirational environment matching the request',
            'action'      => 'candid lifestyle moment with natural body language',
            'framing'     => 'editorial campaign framing, mobile-safe focal hierarchy',
            'elements'    => array_merge(
                ['realistic skin', 'coherent wardrobe', 'cinematic depth'],
                array_slice((array) ($emotion['visuals'] ?? []), 0, 2)
            ),
            'narrative'   => 'premium lifestyle advertising photograph, not generic stock',
        ];
    }

    /** @param array<string, mixed> $intent */
    private function architecture_scene(string $prompt, array $intent): array {
        $aerial = (bool) preg_match('/조감|aerial|bird.?s.?eye/u', mb_strtolower($prompt));
        return [
            'subject'     => $this->extract_subject_hint($prompt),
            'environment' => 'urban residential context with realistic roads and landscaping',
            'action'      => 'static architectural presentation with readable massing',
            'framing'     => $aerial
                ? 'elevated aerial bird\'s-eye site composition'
                : 'elevated establishing architectural composition with straight verticals',
            'elements'    => ['aligned façades', 'detailed windows', 'believable scale', 'natural daylight'],
            'narrative'   => 'professional real-estate architectural visualization',
        ];
    }

    /** @param array<string, mixed> $intent */
    private function product_scene(string $prompt, array $intent): array {
        $subject = $this->extract_subject_hint($prompt);
        // Prefer keeping user product wording rather than anonymous "hero product"
        if (strpos($subject, 'scene featuring') === 0) {
            $subject = mb_substr($subject, strlen('scene featuring '));
        }
        return [
            'subject'     => $subject !== '' ? $subject : 'requested hero product',
            'environment' => !empty($intent['flat_lay']) ? 'clean flat-lay surface' : 'controlled premium studio set',
            'action'      => 'product presented as advertising hero with accurate geometry',
            'framing'     => !empty($intent['flat_lay']) ? 'top-down flat lay composition' : 'three-quarter hero packshot with elegant negative space',
            'elements'    => ['realistic reflections', 'soft graduated shadows', 'material fidelity'],
            'narrative'   => 'commercial product advertising photography',
        ];
    }

    /** @param array<string, mixed> $emotion */
    private function landscape_scene(string $prompt, array $emotion): array {
        return [
            'subject'     => $this->extract_subject_hint($prompt) ?: 'expansive environment as the hero',
            'environment' => 'breathtaking natural or urban landscape',
            'action'      => 'atmospheric depth and scale',
            'framing'     => 'wide cinematic composition',
            'elements'    => array_slice((array) ($emotion['visuals'] ?? []), 0, 3),
            'narrative'   => 'epic landscape photography, National Geographic quality',
        ];
    }

    /** @param array<string, mixed> $intent */
    private function commercial_scene(string $prompt, array $intent): array {
        return [
            'subject'     => $this->extract_subject_hint($prompt),
            'environment' => 'art-directed advertising environment matching the brief',
            'action'      => 'aspirational campaign moment with clear hero focus',
            'framing'     => 'campaign composition with copy-safe negative space',
            'elements'    => ['controlled lighting', 'believable materials', 'strong focal hierarchy'],
            'narrative'   => 'brand campaign key visual with commercial usability',
        ];
    }

    /** @param array<string, mixed> $intent */
    private function general_scene(string $prompt, array $intent): array {
        $subject = $this->extract_subject_hint($prompt);
        return [
            'subject'     => $subject,
            'environment' => 'specific believable setting derived from the request',
            'action'      => 'natural moment with intentional storytelling',
            'framing'     => 'professional composition with clear focal hierarchy',
            'elements'    => ['controlled lighting', 'material detail', 'intentional depth'],
            'narrative'   => 'art-directed photograph, not generic AI stock',
        ];
    }

    private function extract_subject_hint(string $prompt): string {
        $prompt = trim(preg_replace('/\s+/u', ' ', $prompt) ?? $prompt);
        if ($prompt === '') {
            return 'a visually compelling scene expressing the concept through imagery alone';
        }
        // Keep Hangul subjects — do not strip Korean characters
        return 'scene featuring ' . mb_substr($prompt, 0, 160);
    }

    /**
     * @return array{emotional: bool, portrait: bool, product: bool, landscape: bool, commercial: bool, studio: bool, flat_lay: bool, politics: bool, political_ad: bool, architecture: bool, lifestyle: bool}
     */
    public function detect_intent(string $prompt): array {
        $t = mb_strtolower($prompt);
        $politics = (bool) preg_match('/정치|이재명|대통령|선거|정책|국회|정당|대선|여야|political|president|election|policy|캠페인 포스터/u', $t);
        $architecture = (bool) preg_match('/조감|아파트|건축|단지|외관|건물|빌딩|분양|architectural|aerial|facade|real.?estate|residential/u', $t);
        $product  = (bool) preg_match('/제품|product|스마트스토어|쇼핑|ecommerce|상품|패키지|package|향수|화장품|스킨케어|크림|세럼|perfume|cosmetic|skincare|bottle|cream|serum/u', $t);
        $lifestyle = (bool) preg_match('/부부|가족|커플|라이프|lifestyle|일상|행복한|사람들|모델|couple|family/u', $t);
        // 「광고」 alone is commercial campaign, NOT product photography
        $commercial = (bool) preg_match('/광고|advert|commercial|브랜드|brand|럭셔리|luxury|캠페인|campaign|분양/u', $t);

        // Apartment ads with people → lifestyle; pure apartment/aerial → architecture
        if ($architecture && $lifestyle) {
            $product = false;
        } elseif ($architecture) {
            $product = false;
        }

        return [
            'emotional'    => (bool) preg_match('/답답|외로|희망|자유|행복|슬픔|사랑|마음|감정|느낌/u', $t)
                || preg_match('/\b(feel|feeling|emotion|mood|heart|soul)\b/u', $t),
            'portrait'     => !$architecture && ($politics || (bool) preg_match('/인물|초상|portrait|face|얼굴|여자|남자|person|woman|man|30대|20대/u', $t) || $lifestyle),
            'product'      => $product && !$politics && !$architecture,
            'landscape'    => !$politics && !$architecture && (bool) preg_match('/풍경|landscape|산|바다|sea|mountain|여행|travel|관광|tour/u', $t),
            'commercial'   => $commercial,
            'studio'       => (bool) preg_match('/스튜디오|studio/u', $t) && $product,
            'flat_lay'     => (bool) preg_match('/플랫|flat.?lay|탑뷰|top.?view/u', $t),
            'politics'     => $politics,
            'political_ad' => $politics && $commercial,
            'architecture' => $architecture && !$politics,
            'lifestyle'    => ($lifestyle || ($architecture && $lifestyle)) && !$politics,
        ];
    }
}
