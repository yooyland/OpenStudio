<?php
if (!defined('ABSPATH')) exit;

/**
 * Domain-keyed commercial art direction — not generic "premium/award-winning" fluff.
 * Phrases must change the visual brief (camera, light, materials, constraints).
 */
final class YooY_Image_Commercial_Optimizer {

    public function should_apply(array $intent, array $params): bool {
        if (isset($params['commercial']) && $params['commercial'] === false) {
            return false;
        }
        if (!empty($intent['politics']) || !empty($intent['political_ad'])) {
            return true;
        }
        if (!empty($intent['product']) || !empty($intent['commercial']) || !empty($intent['architecture']) || !empty($intent['lifestyle'])) {
            return true;
        }
        $style = sanitize_text_field((string) ($params['style'] ?? ''));
        return in_array($style, ['commercial', 'k-beauty', 'editorial', 'cinematic'], true);
    }

    /**
     * @param array<string, mixed> $intent
     * @return string[]
     */
    public function pick_for_prompt(array $intent, bool $premium = false): array {
        $domain = $this->resolve_domain($intent);
        $phrases = $this->phrases_for_domain($domain, $premium);
        $limit = $premium ? 4 : 2;
        return array_slice($phrases, 0, $limit);
    }

    /**
     * @param array<string, mixed> $intent
     */
    private function resolve_domain(array $intent): string {
        if (!empty($intent['politics']) || !empty($intent['political_ad'])) {
            return 'politics';
        }
        if (!empty($intent['architecture'])) {
            return 'architecture';
        }
        if (!empty($intent['product'])) {
            return 'product';
        }
        if (!empty($intent['lifestyle']) || (!empty($intent['portrait']) && !empty($intent['commercial']))) {
            return 'lifestyle';
        }
        if (!empty($intent['portrait'])) {
            return 'portrait';
        }
        if (!empty($intent['commercial'])) {
            return 'brand';
        }
        return 'general';
    }

    /**
     * @return string[]
     */
    private function phrases_for_domain(string $domain, bool $premium): array {
        switch ($domain) {
            case 'product':
                $base = [
                    'hero product packshot with controlled studio lighting',
                    'accurate product geometry and realistic material reflections',
                    'soft graduated shadows, elegant negative space for brand layout',
                    'advertising-grade finish without fake glow or glitter clutter',
                ];
                break;
            case 'architecture':
                $base = [
                    'professional architectural visualization for real-estate marketing',
                    'straight building geometry, coherent façade rhythm, believable scale',
                    'natural daylight with realistic landscaping and ground plane',
                    'high-end property brochure composition, no warped towers',
                ];
                break;
            case 'lifestyle':
                $base = [
                    'editorial lifestyle campaign photography',
                    'natural candid posture, realistic skin texture, coherent wardrobe',
                    'cinematic depth of field with believable environment interaction',
                    'avoid generic stock-photo couple poses and plastic skin',
                ];
                break;
            case 'portrait':
                $base = [
                    'editorial portrait lighting with natural skin detail',
                    'plausible anatomy, realistic hands and gaze',
                    'wardrobe and age/context consistency',
                ];
                break;
            case 'politics':
                $base = [
                    'premium Korean civic editorial campaign photography',
                    'confident leadership posture, clean civic lighting',
                    'magazine-cover hierarchy with copy-safe space',
                ];
                break;
            case 'brand':
                $base = [
                    'brand campaign key visual with clear focal hierarchy',
                    'art-directed lighting and intentional negative space',
                    'commercial usability for web and OOH',
                ];
                break;
            default:
                $base = [
                    'professionally art-directed photograph',
                    'intentional composition, believable materials, no generic AI-stock look',
                ];
                break;
        }
        if ($premium && $domain === 'product') {
            $base[] = 'luxury brand campaign hero frame';
        }
        if ($premium && $domain === 'architecture') {
            $base[] = 'developer sales-gallery visualization quality';
        }
        return $base;
    }
}
