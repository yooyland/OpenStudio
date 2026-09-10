<?php
if (!defined('ABSPATH')) exit;

/**
 * Prompt fidelity + bloat helpers for Final Prompt Builder.
 * P1/P2 user intent must never be overridden by art-direction fluff.
 */
final class YooY_Image_Prompt_Fidelity {

    /**
     * @param array<string, mixed> $scene
     * @param array<string, mixed> $brief
     */
    public static function lock_block(array $scene, array $brief, string $raw_user): string {
        $subject = trim((string) ($scene['subject'] ?? $brief['primary_subject'] ?? ''));
        $action  = trim((string) ($scene['action'] ?? ''));
        $setting = trim((string) ($scene['setting'] ?? ''));
        $must    = is_array($scene['must_include'] ?? null) ? $scene['must_include'] : [];

        if ($subject === '' && $raw_user !== '') {
            $subject = mb_substr($raw_user, 0, 160);
        }

        $lines = [
            'P1 CORE SUBJECT: ' . ($subject !== '' ? $subject : 'as stated by the user'),
            'P1 CORE ACTION: ' . ($action !== '' ? $action : 'as stated by the user — do not replace with a different action'),
            'P1 CORE SETTING: ' . ($setting !== '' ? $setting : 'as stated by the user — never indoor mural / child imagining unless requested'),
            'P2 STYLE LOCK: honor the user\'s explicit style constraints before default art direction',
            'FIDELITY RULE: never remove CORE SUBJECT + CORE ACTION + CORE SETTING when expanding art direction',
        ];
        if ($must) {
            $lines[] = 'MUST INCLUDE: ' . implode(', ', array_slice($must, 0, 12));
        }
        return implode('. ', $lines);
    }

    /**
     * Compress repeated premium adjectives into one coherent bias line.
     */
    public static function compress_bloat(string $prompt): string {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return $prompt;
        }

        // Collapse stacked QUALITY ESCALATOR / PREMIUM BIAS duplicates into single short lines.
        $prompt = preg_replace(
            '/(?:QUALITY ESCALATOR\s*\([^)]*\):\s*[^.]+\.\s*)+/iu',
            'QUALITY ESCALATOR: refined, richly detailed, professionally directed. ',
            $prompt
        ) ?? $prompt;
        $prompt = preg_replace(
            '/(?:PREMIUM BIAS:\s*[^.]+\.\s*)+/iu',
            'PREMIUM BIAS: contemporary refined finish with clear depth and lighting. ',
            $prompt
        ) ?? $prompt;

        $keywords = ['premium', 'luxury', 'high-end', 'cinematic', 'professional', 'refined', 'sophisticated', 'elegant', 'editorial'];
        foreach ($keywords as $kw) {
            $count = preg_match_all('/\b' . preg_quote($kw, '/') . '\b/iu', $prompt);
            if ($count !== false && $count > 2) {
                // Keep first two occurrences; soften later ones by removing the word.
                $seen = 0;
                $prompt = preg_replace_callback(
                    '/\b' . preg_quote($kw, '/') . '\b/iu',
                    function ($m) use (&$seen) {
                        $seen++;
                        return $seen <= 2 ? $m[0] : '';
                    },
                    $prompt
                ) ?? $prompt;
            }
        }

        $prompt = preg_replace('/\s{2,}/u', ' ', $prompt) ?? $prompt;
        $prompt = preg_replace('/\s+,/u', ',', $prompt) ?? $prompt;
        $prompt = preg_replace('/,\s*,+/u', ',', $prompt) ?? $prompt;
        return trim($prompt);
    }
}
