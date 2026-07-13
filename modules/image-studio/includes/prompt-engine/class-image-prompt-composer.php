<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-image-emotion-engine.php';
require_once __DIR__ . '/class-image-scene-planner.php';
require_once __DIR__ . '/class-image-korean-aesthetic-engine.php';
require_once __DIR__ . '/class-image-commercial-optimizer.php';
require_once __DIR__ . '/class-image-prompt-formatter.php';

/**
 * Central Prompt Composer — meaning, scene, emotion, culture, commercial quality.
 */
final class YooY_Image_Prompt_Composer {

    private const GLOBAL_NEGATIVE = 'text, words, letters, typography, captions, subtitles, watermarks, logos with text, cartoon, anime, illustration, childish drawing, clip art, sticker style, low resolution, blurry, distorted face, bad hands, extra fingers, deformed anatomy, amateur, oversaturated, plastic skin, meaningless empty background, ugly, uncanny AI look';

    private YooY_Image_Emotion_Engine $emotion;
    private YooY_Image_Scene_Planner $scene;
    private YooY_Image_Korean_Aesthetic_Engine $korean;
    private YooY_Image_Commercial_Optimizer $commercial;
    private YooY_Image_Prompt_Formatter $formatter;

    public function __construct() {
        $this->emotion    = new YooY_Image_Emotion_Engine();
        $this->scene      = new YooY_Image_Scene_Planner();
        $this->korean     = new YooY_Image_Korean_Aesthetic_Engine();
        $this->commercial = new YooY_Image_Commercial_Optimizer();
        $this->formatter  = new YooY_Image_Prompt_Formatter();
    }

    /**
     * @param array<string, mixed> $params
     * @return array{prompt: string, canonical_prompt: string, negative_prompt: string, settings: array<string, mixed>, meta: array<string, mixed>}
     */
    public function compose(array $params): array {
        $user_prompt = trim(sanitize_textarea_field((string) ($params['user_prompt'] ?? $params['prompt'] ?? '')));
        $premium     = sanitize_text_field((string) ($params['generation_mode'] ?? 'fast')) === 'premium'
            || sanitize_text_field((string) ($params['quality'] ?? '')) === 'hd';

        $intent  = $this->scene->detect_intent($user_prompt);
        $emotion = $this->emotion->analyze($user_prompt);
        if ($this->emotion->is_abstract_emotional($user_prompt)) {
            $intent['emotional'] = true;
        }

        $korean = $this->korean->analyze($user_prompt);
        $scene  = $this->scene->plan($user_prompt, $emotion, $intent);
        $settings = $this->resolve_auto_settings($params, $emotion, $intent, $korean, $premium);

        $canonical = $this->build_canonical_prompt($user_prompt, $scene, $emotion, $korean, $intent, $settings, $premium);
        $negative  = $this->build_negative_prompt($params, $intent);

        $meta = [
            'user_prompt'   => $user_prompt,
            'emotion'       => $emotion,
            'scene'         => $scene,
            'intent'        => $intent,
            'korean'        => $korean,
            'premium'       => $premium,
            'quality_tail'  => $this->quality_tail($premium, $intent),
        ];

        $formatted = $this->formatter->format($canonical, array_merge($params, $settings), $meta);

        $meta['canonical_prompt'] = $canonical;
        $meta['analysis'] = [
            'emotion'  => $emotion['primary'],
            'mood'     => $settings['mood'],
            'style'    => $settings['style'],
            'scene'    => array_slice($scene['elements'], 0, 4),
            'korean'   => $korean['active'] ? $korean['motif'] : '',
            'abstract' => !empty($intent['emotional']),
        ];

        return apply_filters('yoy_image_prompt_compose', [
            'prompt'           => $formatted['prompt'],
            'canonical_prompt' => $canonical,
            'negative_prompt'  => $formatted['negative_prompt'] ?: $negative,
            'settings'         => $settings,
            'meta'             => $meta,
        ], $params);
    }

    /**
     * @param array<string, mixed> $scene
     * @param array<string, mixed> $emotion
     * @param array<string, mixed> $korean
     * @param array<string, mixed> $intent
     * @param array<string, mixed> $settings
     */
    private function build_canonical_prompt(
        string $user_prompt,
        array $scene,
        array $emotion,
        array $korean,
        array $intent,
        array $settings,
        bool $premium
    ): string {
        $segments = [];

        if (!empty($intent['emotional']) && $emotion['primary'] !== 'neutral') {
            $genre = (string) ($emotion['genre'] ?: 'cinematic fine-art photograph');
            $segments[] = 'A ' . $genre . ' of ' . $scene['subject'];
            $segments[] = $scene['environment'];
            if (!empty($scene['elements'])) {
                $segments[] = implode(', ', array_slice($scene['elements'], 0, 4));
            }
            if (!empty($emotion['lighting'])) {
                $segments[] = (string) $emotion['lighting'];
            }
            $segments[] = 'shallow depth of field, expressive emotion, visual storytelling';
            $segments[] = 'symbolic expression without any text or lettering';
        } elseif (!empty($intent['product'])) {
            $segments[] = 'Premium advertising photograph of ' . $scene['subject'];
            $segments[] = $scene['environment'] . ', ' . $scene['framing'];
            $segments[] = $this->lighting_phrase($settings['lighting']);
        } elseif (!empty($intent['landscape'])) {
            $segments[] = 'Breathtaking ' . $scene['narrative'];
            $segments[] = $scene['environment'] . ', ' . $scene['framing'];
        } else {
            $hint = $this->translate_concept($user_prompt, $intent);
            $segments[] = 'A premium photorealistic image of ' . $hint;
            $segments[] = $scene['environment'];
            $segments[] = $scene['action'];
        }

        if ($korean['active'] && !empty($korean['visuals'])) {
            $segments[] = implode(', ', array_slice($korean['visuals'], 0, 3));
            if (!empty($korean['palette'])) {
                $segments[] = (string) $korean['palette'];
            }
        }

        if ($this->commercial->should_apply($intent, $settings)) {
            $segments[] = implode(', ', $this->commercial->pick_for_prompt($intent, $premium));
        }

        $segments[] = $this->camera_phrase($settings);
        $segments[] = $premium
            ? 'award-winning photography, emotional realism, ultra detailed, premium advertising quality'
            : 'professional quality, natural realism, highly detailed';

        $text = $this->dedupe_segments($segments);
        return $this->trim_prompt($text);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $emotion
     * @param array<string, mixed> $intent
     * @param array<string, mixed> $korean
     * @return array<string, mixed>
     */
    private function resolve_auto_settings(array $params, array $emotion, array $intent, array $korean, bool $premium): array {
        $out = [
            'style'          => $this->auto_value($params, 'style', 'photorealistic'),
            'lighting'       => $this->auto_value($params, 'lighting', 'studio'),
            'composition'    => $this->auto_value($params, 'composition', 'rule_of_thirds'),
            'background'     => $this->auto_value($params, 'background', 'studio_white'),
            'color_palette'  => $this->auto_value($params, 'color_palette', 'neutral'),
            'mood'           => $this->auto_value($params, 'mood', 'neutral'),
            'camera'         => $this->auto_value($params, 'camera', 'cinema_50mm'),
            'lens'           => $this->auto_value($params, 'lens', 'standard'),
            'camera_angle'   => $this->auto_value($params, 'camera_angle', 'eye_level'),
            'depth_of_field' => $this->auto_value($params, 'depth_of_field', 'medium'),
            'brand_tone'     => $this->auto_value($params, 'brand_tone', 'premium'),
            'product_type'   => $this->auto_value($params, 'product_type', 'general'),
            'quality'        => $premium ? 'hd' : sanitize_text_field((string) ($params['quality'] ?? 'standard')),
            'commercial'     => !isset($params['commercial']) || !empty($params['commercial']),
            'korean_context' => !empty($params['korean_context']) || $korean['active'],
        ];

        if ($this->is_auto($params, 'mood') && !empty($emotion['mood'])) {
            $out['mood'] = (string) $emotion['mood'];
        }
        if ($this->is_auto($params, 'lighting') && !empty($emotion['lighting'])) {
            $out['lighting'] = $this->map_lighting_id($emotion['lighting']);
        }
        if ($this->is_auto($params, 'style') && !empty($emotion['genre'])) {
            $out['style'] = 'cinematic';
        }
        if ($korean['active'] && $this->is_auto($params, 'style')) {
            $out['style'] = strpos((string) $korean['motif'], 'beauty') !== false ? 'k-beauty' : 'editorial';
        }
        if (!empty($intent['product'])) {
            $out['style'] = $this->is_auto($params, 'style') ? 'commercial' : $out['style'];
            $out['composition'] = $this->is_auto($params, 'composition')
                ? (!empty($intent['flat_lay']) ? 'flat_lay' : 'hero')
                : $out['composition'];
            $out['background'] = $this->is_auto($params, 'background') ? 'studio_white' : $out['background'];
        }
        if (!empty($intent['emotional'])) {
            $out['composition'] = $this->is_auto($params, 'composition') ? 'close_up' : $out['composition'];
            $out['depth_of_field'] = $this->is_auto($params, 'depth_of_field') ? 'shallow' : $out['depth_of_field'];
            $out['camera'] = $this->is_auto($params, 'camera') ? 'tele_85mm' : $out['camera'];
        }
        if (!empty($intent['landscape'])) {
            $out['composition'] = $this->is_auto($params, 'composition') ? 'wide' : $out['composition'];
            $out['camera'] = $this->is_auto($params, 'camera') ? 'wide_24mm' : $out['camera'];
            $out['depth_of_field'] = $this->is_auto($params, 'depth_of_field') ? 'deep' : $out['depth_of_field'];
        }

        return $out;
    }

    /** @param array<string, mixed> $params */
    private function auto_value(array $params, string $key, string $default): string {
        $val = sanitize_text_field((string) ($params[$key] ?? ''));
        if ($val === '' || $val === 'auto') {
            return $default;
        }
        if (!empty($params['smart_auto']) && $this->is_default_field($key, $val)) {
            return $default;
        }
        return $val;
    }

    /** @param array<string, mixed> $params */
    private function is_auto(array $params, string $key): bool {
        $val = sanitize_text_field((string) ($params[$key] ?? ''));
        return $val === '' || $val === 'auto' || (!empty($params['smart_auto']) && $this->is_default_field($key, $val));
    }

    private function is_default_field(string $key, string $val): bool {
        $defaults = [
            'style' => ['commercial', 'photorealistic'],
            'lighting' => ['studio'],
            'composition' => ['center', 'rule_of_thirds'],
            'background' => ['studio_white'],
            'color_palette' => ['neutral'],
            'mood' => ['neutral'],
            'brand_tone' => ['premium'],
        ];
        return isset($defaults[$key]) && in_array($val, $defaults[$key], true);
    }

    /** @param array<string, mixed> $params @param array<string, mixed> $intent */
    private function build_negative_prompt(array $params, array $intent): string {
        $user_neg = sanitize_textarea_field((string) ($params['negative_prompt'] ?? ''));
        $parts = [self::GLOBAL_NEGATIVE];
        if (!empty($intent['emotional'])) {
            $parts[] = 'written words, kanji text, hangul text, subtitles, meme text';
        }
        if ($user_neg !== '' && strpos($user_neg, 'cartoon') === false) {
            $parts[] = $user_neg;
        }
        return implode(', ', array_unique(array_filter(array_map('trim', explode(',', implode(', ', $parts))))));
    }

    /** @param array<string, mixed> $intent */
    private function quality_tail(bool $premium, array $intent): string {
        if ($premium) {
            return 'museum-quality fine art print, Hasselblad medium format look, impeccable detail';
        }
        if (!empty($intent['commercial'])) {
            return 'high-end commercial finish';
        }
        return 'professional photography quality';
    }

    /** @param array<string, mixed> $settings */
    private function camera_phrase(array $settings): string {
        $map = [
            'cinema_50mm' => '50mm cinematic lens',
            'wide_24mm'   => '24mm wide-angle lens',
            'tele_85mm'   => '85mm portrait lens',
            'macro'       => 'macro lens',
        ];
        $camera = $map[$settings['camera'] ?? ''] ?? 'professional camera';
        $dof = $settings['depth_of_field'] ?? 'medium';
        $dof_label = $dof === 'shallow' ? 'shallow depth of field' : ($dof === 'deep' ? 'deep focus' : 'balanced depth of field');
        return $camera . ', ' . $dof_label;
    }

    private function lighting_phrase(string $lighting): string {
        $map = [
            'studio'       => 'professional studio lighting',
            'natural'      => 'natural daylight',
            'golden_hour'  => 'golden hour lighting',
            'dramatic'     => 'dramatic cinematic lighting',
            'soft'         => 'soft diffused lighting',
            'neon'         => 'stylized neon lighting',
        ];
        return $map[$lighting] ?? 'refined professional lighting';
    }

    private function map_lighting_id(string $phrase): string {
        $t = strtolower($phrase);
        if (strpos($t, 'window') !== false || strpos($t, 'soft') !== false) return 'soft';
        if (strpos($t, 'golden') !== false || strpos($t, 'backlit') !== false) return 'golden_hour';
        if (strpos($t, 'dramatic') !== false || strpos($t, 'shadow') !== false) return 'dramatic';
        if (strpos($t, 'overcast') !== false || strpos($t, 'diffused') !== false) return 'natural';
        return 'studio';
    }

    /** @param array<string, mixed> $intent */
    private function translate_concept(string $user_prompt, array $intent): string {
        if (!empty($intent['emotional']) || preg_match('/[\x{AC00}-\x{D7A3}]/u', $user_prompt)) {
            return 'the feeling and concept expressed purely through visuals, never as written words';
        }
        return mb_substr($user_prompt, 0, 200);
    }

    /**
     * @param string[] $segments
     */
    private function dedupe_segments(array $segments): string {
        $seen = [];
        $out = [];
        foreach ($segments as $seg) {
            $seg = trim(preg_replace('/\s+/', ' ', (string) $seg) ?? '');
            if ($seg === '') continue;
            $key = mb_strtolower($seg);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = $seg;
        }
        return implode('. ', $out);
    }

    private function trim_prompt(string $text): string {
        $text = preg_replace('/\.+/', '.', $text) ?? '';
        $text = trim($text, " \t\n\r\0\x0B.");
        if (mb_strlen($text) > 850) {
            $text = mb_substr($text, 0, 847) . '…';
        }
        return $text;
    }
}
