<?php
if (!defined('ABSPATH')) exit;

final class YooY_Import_Validator {

    private const MAX_BYTES = 104857600; // 100 MB

    public static function supported_types(): array {
        return [
            'image'    => ['png', 'jpg', 'jpeg', 'webp', 'svg'],
            'video'    => ['mp4', 'mov', 'avi', 'mkv', 'webm'],
            'music'    => ['mp3', 'wav', 'flac', 'aac'],
            'voice'    => ['mp3', 'wav'],
            'document' => ['pdf', 'docx', 'txt'],
        ];
    }

    public static function origins(): array {
        return ['AI', 'Imported', 'Generated', 'External', 'Folder', 'Cloud'];
    }

    public static function sources(): array {
        return [
            ['id' => 'upload', 'label' => 'Upload Button'],
            ['id' => 'drag', 'label' => 'Drag & Drop'],
            ['id' => 'folder', 'label' => 'Folder Import'],
            ['id' => 'cloud', 'label' => 'Cloud Import', 'future' => true],
        ];
    }

    public static function validate_file(string $filename, int $size, string $mime = '', ?string $type_hint = null): array {
        if ($size <= 0) {
            throw new Exception('Empty file.');
        }
        if ($size > self::MAX_BYTES) {
            throw new Exception('File exceeds maximum import size (100 MB).');
        }

        $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $type = self::detect_type($ext, $mime, $type_hint);
        if ($type === '') {
            throw new Exception('Unsupported file type: .' . $ext);
        }

        return [
            'type'       => $type,
            'extension'  => $ext,
            'mime'       => $mime,
            'size'       => $size,
            'filename'   => sanitize_file_name($filename),
        ];
    }

    public static function detect_type(string $ext, string $mime = '', ?string $hint = null): string {
        if ($hint !== null && $hint !== '') {
            $hint = sanitize_text_field($hint);
            if ($hint === 'writing') {
                $hint = 'document';
            }
            foreach (self::supported_types() as $type => $exts) {
                if ($type === $hint && in_array($ext, $exts, true)) {
                    return $type === 'document' ? 'writing' : $type;
                }
            }
        }

        foreach (self::supported_types() as $type => $exts) {
            if (in_array($ext, $exts, true)) {
                if ($type === 'voice' && $hint !== 'voice') {
                    continue;
                }
                return $type === 'document' ? 'writing' : $type;
            }
        }

        if (in_array($ext, ['mp3', 'wav'], true) && $hint === 'voice') {
            return 'voice';
        }
        if (in_array($ext, ['mp3', 'wav', 'flac', 'aac'], true)) {
            return 'music';
        }

        return '';
    }

    public static function provider_for_source(string $source): string {
        switch ($source) {
            case 'folder':
                return 'Folder Import';
            case 'cloud':
                return 'Cloud Import';
            case 'drag':
            case 'upload':
            default:
                return 'Imported';
        }
    }
}
