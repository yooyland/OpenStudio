<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_Pause {

    public function process(string $text): array {
        $pauses = [];
        $processed = preg_replace_callback('/\[pause:(\d+(?:\.\d+)?)s?\]/i', function ($m) use (&$pauses) {
            $pauses[] = ['duration' => (float) $m[1], 'tag' => $m[0]];
            return '<break time="' . $m[1] . 's" />';
        }, $text);

        $processed = preg_replace('/\.{3,}/', '<break time="0.8s" />', $processed);

        return [
            'original'  => $text,
            'processed' => $processed,
            'pauses'    => $pauses,
            'has_pauses'=> !empty($pauses),
        ];
    }

    public function insert_pause(string $text, float $seconds, int $position = -1): string {
        $tag = '[pause:' . $seconds . 's]';
        if ($position < 0) return rtrim($text) . ' ' . $tag;
        return substr($text, 0, $position) . $tag . substr($text, $position);
    }
}
