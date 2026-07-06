<?php
if (!defined('ABSPATH')) exit;

final class YooY_Asset_Generator {

    public static function svg_data_uri(int $width, int $height, string $label, string $bg = '#0d0d0d', string $fg = '#d8a63a'): string {
        $label = esc_html(mb_substr($label, 0, 48));
        $font  = 14;
        $lines = self::wrap_lines($label, 22);
        $y     = (int) ($height / 2) - (count($lines) * $font / 2);

        $text_nodes = '';
        foreach ($lines as $i => $line) {
            $ty = $y + ($i * ($font + 4));
            $text_nodes .= '<text x="50%" y="' . $ty . '" fill="' . esc_attr($fg) . '" font-family="system-ui,sans-serif" font-size="' . $font . '" text-anchor="middle" dominant-baseline="middle">' . $line . '</text>';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">'
            . '<rect width="100%" height="100%" fill="' . esc_attr($bg) . '"/>'
            . '<rect x="8" y="8" width="' . ($width - 16) . '" height="' . ($height - 16) . '" fill="none" stroke="' . esc_attr($fg) . '" stroke-opacity="0.35" rx="12"/>'
            . $text_nodes
            . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function audio_data_uri(string $label = 'YooY Audio'): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">'
            . '<rect width="512" height="512" fill="#1a1a0a"/>'
            . '<circle cx="256" cy="256" r="120" fill="none" stroke="#d8a63a" stroke-width="8"/>'
            . '<text x="256" y="270" fill="#ffd76a" font-family="system-ui,sans-serif" font-size="20" text-anchor="middle">' . esc_html(mb_substr($label, 0, 16)) . '</text>'
            . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Minimal silent WAV for mock TTS/Music providers. */
    public static function silent_audio_data_uri(): string {
        return 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
    }

    public static function waveform_data_uri(string $label = 'Waveform'): string {
        return self::svg_data_uri(800, 120, $label, '#0d0d0d', '#ffd76a');
    }

    private static function wrap_lines(string $text, int $max): array {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line  = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (mb_strlen($candidate) > $max && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') $lines[] = $line;
        return $lines ?: ['YooY'];
    }
}
