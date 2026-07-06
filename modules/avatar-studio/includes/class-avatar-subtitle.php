<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_Subtitle {

    public function generate(array $params): array {
        $script   = $params['script'] ?? '';
        $language = $params['subtitle_language'] ?? 'ko';
        $style    = $params['subtitle_style'] ?? 'default';
        $enabled  = !empty($params['subtitle_enabled']);

        if (!$enabled || $script === '') {
            return ['enabled' => false, 'tracks' => []];
        }

        $sentences = preg_split('/(?<=[.!?。])\s+/', $script, -1, PREG_SPLIT_NO_EMPTY);
        if (count($sentences) <= 1) {
            $sentences = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $script)));
        }

        $duration  = (int) ($params['duration'] ?? 30);
        $per_line  = $duration / max(1, count($sentences));
        $tracks    = [];
        $offset    = 0.0;

        foreach ($sentences as $i => $line) {
            if (trim($line) === '') continue;
            $tracks[] = [
                'index'    => $i,
                'text'     => trim($line),
                'start'    => round($offset, 2),
                'end'      => round($offset + $per_line, 2),
                'language' => $language,
                'style'    => $style,
            ];
            $offset += $per_line;
        }

        return [
            'enabled'  => true,
            'language' => $language,
            'style'    => $style,
            'tracks'   => $tracks,
            'srt'      => $this->to_srt($tracks),
        ];
    }

    private function to_srt(array $tracks): string {
        $srt = '';
        foreach ($tracks as $i => $t) {
            $srt .= ($i + 1) . "\n";
            $srt .= $this->format_time($t['start']) . ' --> ' . $this->format_time($t['end']) . "\n";
            $srt .= $t['text'] . "\n\n";
        }
        return trim($srt);
    }

    private function format_time(float $seconds): string {
        $h = (int) floor($seconds / 3600);
        $m = (int) floor(($seconds % 3600) / 60);
        $s = (int) floor($seconds % 60);
        $ms = (int) round(($seconds - floor($seconds)) * 1000);
        return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
    }
}
