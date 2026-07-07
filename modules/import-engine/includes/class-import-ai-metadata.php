<?php
if (!defined('ABSPATH')) exit;

/**
 * Generates AI-ready metadata for imported assets.
 * Uses OpenAI when configured; otherwise deterministic heuristics.
 */
final class YooY_Import_AI_Metadata {

    public static function generate(string $filename, string $type, array $extracted, array $options = []): array {
        $base = self::heuristic($filename, $type, $extracted);

        if (!empty($options['skip_ai'])) {
            return $base;
        }

        $enhanced = self::try_openai($filename, $type, $extracted, $base);
        return $enhanced ?: $base;
    }

    private static function heuristic(string $filename, string $type, array $extracted): array {
        $stem    = self::humanize_filename($filename);
        $tokens  = self::tokenize($stem);
        $category = self::category_for_type($type);

        $dims = '';
        if (!empty($extracted['width']) && !empty($extracted['height'])) {
            $dims = $extracted['width'] . '×' . $extracted['height'];
        } elseif (!empty($extracted['resolution'])) {
            $dims = (string) $extracted['resolution'];
        }

        $duration = (int) ($extracted['duration'] ?? 0);
        $desc_parts = [ucfirst($type) . ' asset'];
        if ($dims !== '') {
            $desc_parts[] = $dims;
        }
        if ($duration > 0) {
            $desc_parts[] = $duration . 's';
        }
        if (!empty($extracted['format'])) {
            $desc_parts[] = strtoupper((string) $extracted['format']);
        }

        $keywords = array_values(array_unique(array_merge($tokens, [$type, $category])));
        $tags = array_slice($keywords, 0, 8);

        return [
            'title'       => $stem,
            'description' => implode(' · ', $desc_parts) . '. Imported into YooY AI Studio.',
            'keywords'    => $keywords,
            'tags'        => $tags,
            'category'    => $category,
            'source'      => 'heuristic',
        ];
    }

    private static function try_openai(string $filename, string $type, array $extracted, array $base): ?array {
        if (!class_exists('YooY_Secrets')) {
            return null;
        }
        $key = YooY_Secrets::get_api_key('yoy_openai_api_key');
        if ($key === '') {
            return null;
        }

        $prompt = 'Generate JSON for an imported creative asset. Fields: title, description, keywords (array), tags (array), category. '
            . 'Asset type: ' . $type . '. Filename: ' . $filename . '. Metadata: ' . wp_json_encode($extracted)
            . '. Respond with JSON only. Korean-friendly titles when appropriate.';

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => 'gpt-4o-mini',
                'temperature' => 0.4,
                'messages'    => [
                    ['role' => 'system', 'content' => 'You are a creative asset librarian for YooY AI Studio.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $text = $body['choices'][0]['message']['content'] ?? '';
        if (!is_string($text) || $text === '') {
            return null;
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (!is_array($parsed)) {
                return null;
            }
            return [
                'title'       => sanitize_text_field($parsed['title'] ?? $base['title']),
                'description' => sanitize_textarea_field($parsed['description'] ?? $base['description']),
                'keywords'    => self::string_array($parsed['keywords'] ?? $base['keywords']),
                'tags'        => self::string_array($parsed['tags'] ?? $base['tags']),
                'category'    => sanitize_text_field($parsed['category'] ?? $base['category']),
                'source'      => 'openai',
            ];
        }

        return null;
    }

    private static function humanize_filename(string $filename): string {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $stem = preg_replace('/[_\-\.]+/', ' ', $stem);
        $stem = preg_replace('/\s+/', ' ', trim((string) $stem));
        if ($stem === '') {
            return 'Imported Asset';
        }
        return mb_convert_case($stem, MB_CASE_TITLE, 'UTF-8');
    }

    private static function tokenize(string $text): array {
        $parts = preg_split('/\s+/', strtolower($text)) ?: [];
        $out   = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part) >= 2) {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }

    private static function category_for_type(string $type): string {
        switch ($type) {
            case 'image':
                return 'Visual';
            case 'video':
                return 'Video';
            case 'music':
                return 'Music';
            case 'voice':
                return 'Voice';
            case 'writing':
                return 'Document';
            default:
                return 'General';
        }
    }

    private static function string_array($value): array {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            $item = sanitize_text_field((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        return array_values(array_unique($out));
    }
}
