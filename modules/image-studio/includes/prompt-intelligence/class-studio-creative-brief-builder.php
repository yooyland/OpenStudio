<?php
if (!defined('ABSPATH')) exit;

/**
 * Builds Structured Creative Brief from intent analysis.
 */
final class YooY_Studio_Creative_Brief_Builder {

    /**
     * @param array<string, mixed> $intent
     * @return array<string, mixed>
     */
    public function build(array $intent): array {
        return [
            'what'               => (string) ($intent['intent'] ?? ''),
            'primary_subject'    => (string) ($intent['primary_subject'] ?? ''),
            'core_message'       => (string) ($intent['core_message'] ?? ''),
            'audience'           => (string) ($intent['audience'] ?? ''),
            'medium'             => (string) ($intent['output_format'] ?? ''),
            'tone'               => (string) ($intent['tone'] ?? ''),
            'content_domain'     => (string) ($intent['content_domain'] ?? 'general'),
            'ad_subtype'         => (string) ($intent['ad_subtype'] ?? ''),
            'visual_style'       => (string) ($intent['visual_style'] ?? ''),
            'composition'        => (string) ($intent['composition'] ?? ''),
            'color_palette'      => (string) ($intent['color_palette'] ?? ''),
            'lighting'           => (string) ($intent['lighting'] ?? ''),
            'camera'             => (string) ($intent['camera'] ?? ''),
            'required_elements'  => array_values((array) ($intent['required_elements'] ?? [])),
            'forbidden_elements' => array_values((array) ($intent['forbidden_elements'] ?? [])),
            'entities'           => array_values((array) ($intent['entities'] ?? [])),
            'text_overlay'       => array_values((array) ($intent['text_overlay'] ?? [])),
            'project_context'    => is_array($intent['project_context'] ?? null) ? $intent['project_context'] : [],
            'raw_user_request'   => (string) ($intent['raw_user_request'] ?? ''),
            'confidence'         => (float) ($intent['confidence'] ?? 0),
            'wants_product'      => !empty($intent['wants_product']),
            'wants_political'    => !empty($intent['wants_political']),
        ];
    }
}
