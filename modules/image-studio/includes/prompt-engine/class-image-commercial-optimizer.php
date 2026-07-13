<?php
if (!defined('ABSPATH')) exit;

/**
 * Boosts commercial / advertising imagery quality descriptors.
 */
final class YooY_Image_Commercial_Optimizer {

    public function should_apply(array $intent, array $params): bool {
        if (!empty($params['commercial']) && $params['commercial'] === false) {
            return false;
        }
        if (!empty($intent['product']) || !empty($intent['commercial'])) {
            return true;
        }
        $style = sanitize_text_field((string) ($params['style'] ?? ''));
        return in_array($style, ['commercial', 'k-beauty', 'editorial'], true);
    }

    /**
     * @return string[]
     */
    public function quality_phrases(bool $premium = false): array {
        $base = [
            'premium product photography',
            'luxury lighting',
            'award-winning composition',
            'commercial retouching',
            'magazine quality',
            'advertising ready',
            'brand storytelling',
            'luxury color grading',
        ];
        if ($premium) {
            $base[] = 'hero campaign visual';
            $base[] = 'ultra-high-end production value';
        }
        return $base;
    }

    /**
     * @return string[]
     */
    public function pick_for_prompt(array $intent, bool $premium = false): array {
        $all = $this->quality_phrases($premium);
        if (!empty($intent['product'])) {
            return array_slice($all, 0, $premium ? 6 : 4);
        }
        return array_slice($all, 0, $premium ? 5 : 3);
    }
}
