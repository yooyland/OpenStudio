<?php
if (!defined('ABSPATH')) exit;

final class YooY_Import_Metadata_Extractor {

    public static function extract(string $type, string $path, string $binary, array $file_info): array {
        $meta = [
            'format'    => $file_info['extension'] ?? '',
            'mime'      => $file_info['mime'] ?? '',
            'size'      => (int) ($file_info['size'] ?? strlen($binary)),
            'filename'  => $file_info['filename'] ?? basename($path),
        ];

        switch ($type) {
            case 'image':
                return array_merge($meta, self::image_meta($path, $binary));
            case 'video':
                return array_merge($meta, self::video_meta($path, $binary, $meta['size']));
            case 'music':
            case 'voice':
                return array_merge($meta, self::audio_meta($path, $binary, $meta['size']));
            case 'writing':
                return array_merge($meta, self::document_meta($path, $binary, $file_info['extension'] ?? ''));
            default:
                return $meta;
        }
    }

    private static function image_meta(string $path, string $binary): array {
        $info = ['width' => 0, 'height' => 0];
        if ($path !== '' && is_readable($path)) {
            $size = @getimagesize($path);
        } elseif ($binary !== '') {
            $size = @getimagesizefromstring($binary);
        } else {
            $size = false;
        }

        if (is_array($size)) {
            $info['width']  = (int) ($size[0] ?? 0);
            $info['height'] = (int) ($size[1] ?? 0);
            if (!empty($size['mime'])) {
                $info['mime'] = $size['mime'];
            }
        }
        return $info;
    }

    private static function video_meta(string $path, string $binary, int $size): array {
        $meta = [
            'duration'   => 0,
            'fps'        => 0,
            'resolution' => '',
            'codec'      => 'unknown',
        ];

        if ($path !== '' && is_readable($path)) {
            $header = @file_get_contents($path, false, null, 0, 65536);
        } else {
            $header = substr($binary, 0, 65536);
        }

        if (is_string($header) && $header !== '') {
            if (strpos($header, 'ftyp') !== false || strpos($header, 'moov') !== false) {
                $meta['codec'] = 'mp4/mov';
            } elseif (strpos($header, 'webm') !== false || strpos($header, 'matroska') !== false) {
                $meta['codec'] = 'webm/mkv';
            }
        }

        $meta['duration'] = self::estimate_duration_from_size($size, 'video');
        $meta['resolution'] = '1920x1080';
        return $meta;
    }

    private static function audio_meta(string $path, string $binary, int $size): array {
        $bitrate = 192000;
        $duration = self::estimate_duration_from_size($size, 'audio');
        return [
            'duration' => $duration,
            'bitrate'  => $bitrate,
        ];
    }

    private static function document_meta(string $path, string $binary, string $ext): array {
        $meta = ['page_count' => 1, 'word_count' => 0];
        $content = $binary;
        if ($content === '' && $path !== '' && is_readable($path)) {
            $content = @file_get_contents($path);
        }
        if (!is_string($content)) {
            return $meta;
        }

        if ($ext === 'txt') {
            $meta['word_count'] = str_word_count(wp_strip_all_tags($content));
            $meta['page_count'] = max(1, (int) ceil($meta['word_count'] / 300));
            return $meta;
        }

        if ($ext === 'pdf') {
            $matches = [];
            preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches);
            $count = count($matches[0]);
            $meta['page_count'] = $count > 0 ? $count : 1;
            return $meta;
        }

        if ($ext === 'docx') {
            $meta['word_count'] = substr_count($content, ' ');
            $meta['page_count'] = max(1, (int) ceil($meta['word_count'] / 300));
        }

        return $meta;
    }

    private static function estimate_duration_from_size(int $size, string $kind): int {
        if ($size <= 0) {
            return 0;
        }
        $rate = $kind === 'video' ? 250000 : 24000;
        return max(1, (int) round($size / $rate));
    }
}
