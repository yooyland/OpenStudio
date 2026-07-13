<?php
if (!defined('ABSPATH')) exit;

/**
 * Designs visual scenes from meaning — never literal text or word illustration.
 */
final class YooY_Image_Scene_Planner {

    /**
     * @param array<string, mixed> $emotion
     * @return array{subject: string, environment: string, action: string, framing: string, elements: string[], narrative: string}
     */
    public function plan(string $user_prompt, array $emotion, array $intent): array {
        $abstract = !empty($emotion['abstract']) || !empty($intent['emotional']);
        $visuals = is_array($emotion['visuals'] ?? null) ? $emotion['visuals'] : [];

        if ($abstract && $emotion['primary'] !== 'neutral') {
            return $this->emotional_scene($emotion);
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
        if (!empty($intent['commercial'])) {
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
            'subject'     => 'compelling human portrait with authentic expression',
            'environment' => !empty($intent['studio']) ? 'professional studio setting' : 'contextual environment',
            'action'      => 'natural pose with emotional depth',
            'framing'     => 'close-up to medium shot, eyes as focal point',
            'elements'    => array_slice((array) ($emotion['visuals'] ?? []), 0, 4),
            'narrative'   => 'award-winning portrait photography, emotional realism',
        ];
    }

    /** @param array<string, mixed> $intent */
    private function product_scene(string $prompt, array $intent): array {
        return [
            'subject'     => 'hero product as the clear focal point',
            'environment' => !empty($intent['flat_lay']) ? 'clean flat-lay surface' : 'premium studio set',
            'action'      => 'product presented with luxury styling',
            'framing'     => !empty($intent['flat_lay']) ? 'top-down flat lay composition' : 'hero product shot with balanced negative space',
            'elements'    => ['subtle props', 'refined shadows', 'premium material highlights'],
            'narrative'   => 'premium product photography, advertising ready',
        ];
    }

    /** @param array<string, mixed> $emotion */
    private function landscape_scene(string $prompt, array $emotion): array {
        return [
            'subject'     => 'expansive environment as the hero',
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
            'subject'     => 'brand-focused visual with clear commercial intent',
            'environment' => 'polished advertising environment',
            'action'      => 'aspirational lifestyle moment',
            'framing'     => 'magazine-quality composition with copy-safe negative space',
            'elements'    => ['luxury lighting', 'refined color grading', 'premium finish'],
            'narrative'   => 'award-winning commercial campaign imagery',
        ];
    }

    /** @param array<string, mixed> $intent */
    private function general_scene(string $prompt, array $intent): array {
        $subject = $this->extract_subject_hint($prompt);
        return [
            'subject'     => $subject,
            'environment' => 'richly detailed believable setting',
            'action'      => 'natural moment captured mid-story',
            'framing'     => 'professional composition with clear focal hierarchy',
            'elements'    => ['cinematic depth', 'premium lighting', 'ultra-fine detail'],
            'narrative'   => 'photorealistic visual storytelling',
        ];
    }

    private function extract_subject_hint(string $prompt): string {
        $clean = trim(preg_replace('/[\x{AC00}-\x{D7A3}]+/u', '', $prompt) ?? $prompt);
        if ($clean !== '' && mb_strlen($clean) > 8) {
            return 'scene featuring ' . mb_substr($clean, 0, 120);
        }
        return 'a visually compelling scene expressing the concept through imagery alone';
    }

    /**
     * @return array{emotional: bool, portrait: bool, product: bool, landscape: bool, commercial: bool, studio: bool, flat_lay: bool}
     */
    public function detect_intent(string $prompt): array {
        $t = mb_strtolower($prompt);
        return [
            'emotional'  => (bool) preg_match('/답답|외로|희망|자유|행복|슬픔|사랑|마음|감정|느낌/u', $t)
                || preg_match('/\b(feel|feeling|emotion|mood|heart|soul)\b/u', $t),
            'portrait'   => (bool) preg_match('/인물|초상|portrait|face|얼굴|여자|남자|person|woman|man/u', $t),
            'product'    => (bool) preg_match('/제품|product|스마트스토어|쇼핑|ecommerce|상품|패키지|package/u', $t),
            'landscape'  => (bool) preg_match('/풍경|landscape|산|바다|sea|mountain|여행|travel/u', $t),
            'commercial' => (bool) preg_match('/광고|advert|commercial|브랜드|brand|럭셔리|luxury|캠페인|campaign/u', $t),
            'studio'     => (bool) preg_match('/스튜디오|studio|제품|product/u', $t),
            'flat_lay'   => (bool) preg_match('/플랫|flat.?lay|탑뷰|top.?view/u', $t),
        ];
    }
}
