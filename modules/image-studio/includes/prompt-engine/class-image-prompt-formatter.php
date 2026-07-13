<?php
if (!defined('ABSPATH')) exit;

/**
 * Provider-specific prompt formatting — same meaning, different API dialects.
 */
final class YooY_Image_Prompt_Formatter {

    public function format(string $canonical, array $params, array $meta): array {
        $provider = sanitize_text_field((string) ($params['provider'] ?? 'openai'));
        $model    = sanitize_text_field((string) ($params['model'] ?? ''));
        $negative = sanitize_textarea_field((string) ($params['negative_prompt'] ?? ''));

        if ($provider === 'replicate' || $provider === 'flux' || strpos($model, 'flux') !== false) {
            return [
                'prompt'          => $this->format_flux($canonical, $meta),
                'negative_prompt' => $negative,
            ];
        }
        if ($provider === 'ideogram' || strpos($model, 'ideogram') !== false) {
            return [
                'prompt'          => $this->format_ideogram($canonical, $meta),
                'negative_prompt' => $negative,
            ];
        }
        if ($provider === 'gemini-image' || strpos($model, 'imagen') !== false) {
            return [
                'prompt'          => $this->format_imagen($canonical, $meta),
                'negative_prompt' => $negative,
            ];
        }
        if ($provider === 'stability' || strpos($model, 'sdxl') !== false || strpos($model, 'stable') !== false) {
            return [
                'prompt'          => $this->format_sdxl($canonical, $meta),
                'negative_prompt' => $negative,
            ];
        }
        if ($provider === 'recraft' || strpos($model, 'recraft') !== false) {
            return [
                'prompt'          => $this->format_recraft($canonical, $meta),
                'negative_prompt' => $negative,
            ];
        }

        return [
            'prompt'          => $this->format_openai($canonical, $meta),
            'negative_prompt' => $negative,
        ];
    }

    /** @param array<string, mixed> $meta */
    private function format_openai(string $canonical, array $meta): string {
        $parts = [$canonical];
        if (!empty($meta['quality_tail'])) {
            $parts[] = (string) $meta['quality_tail'];
        }
        return $this->trim_sentence(implode(' ', $parts));
    }

    /** @param array<string, mixed> $meta */
    private function format_flux(string $canonical, array $meta): string {
        $tags = $this->extract_visual_tags($canonical, $meta);
        $tags = array_merge(
            ['photorealistic', 'ultra detailed', 'professional photography'],
            $tags,
            ['8k', 'sharp focus', 'cinematic']
        );
        return implode(', ', array_unique(array_filter($tags)));
    }

    /** @param array<string, mixed> $meta */
    private function format_ideogram(string $canonical, array $meta): string {
        return $this->trim_sentence($canonical . '. Photorealistic, design-forward, no text or lettering.');
    }

    /** @param array<string, mixed> $meta */
    private function format_imagen(string $canonical, array $meta): string {
        return $this->trim_sentence('A highly detailed photograph: ' . $canonical);
    }

    /** @param array<string, mixed> $meta */
    private function format_sdxl(string $canonical, array $meta): string {
        $tags = $this->extract_visual_tags($canonical, $meta);
        array_unshift($tags, 'masterpiece', 'best quality', 'photorealistic');
        return implode(', ', array_unique(array_filter($tags)));
    }

    /** @param array<string, mixed> $meta */
    private function format_recraft(string $canonical, array $meta): string {
        $style = !empty($meta['korean']['active']) ? 'vector-illustration-realistic hybrid' : 'realistic vector';
        return $this->trim_sentence($canonical . '. Style: ' . $style . ', refined commercial design.');
    }

    /**
     * @param array<string, mixed> $meta
     * @return string[]
     */
    private function extract_visual_tags(string $canonical, array $meta): array {
        $tags = [];
        $scene = is_array($meta['scene'] ?? null) ? $meta['scene'] : [];
        foreach (['subject', 'environment', 'framing', 'narrative'] as $key) {
            if (!empty($scene[$key])) {
                $tags[] = (string) $scene[$key];
            }
        }
        if (!empty($scene['elements']) && is_array($scene['elements'])) {
            foreach ($scene['elements'] as $el) {
                $tags[] = (string) $el;
            }
        }
        $emotion = is_array($meta['emotion'] ?? null) ? $meta['emotion'] : [];
        if (!empty($emotion['lighting'])) {
            $tags[] = (string) $emotion['lighting'];
        }
        if (!empty($emotion['mood'])) {
            $tags[] = (string) $emotion['mood'] . ' mood';
        }
        if (count($tags) < 4) {
            $tags = array_merge($tags, preg_split('/[,.]/', $canonical) ?: []);
        }
        $out = [];
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '' && mb_strlen($tag) < 80) {
                $out[] = $tag;
            }
        }
        return array_slice($out, 0, 12);
    }

    private function trim_sentence(string $text): string {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        if (mb_strlen($text) > 900) {
            $text = mb_substr($text, 0, 897) . '…';
        }
        return $text;
    }
}
