<?php
if (!defined('ABSPATH')) exit;

final class YooY_Job_Normalizer {

    public static function normalize(array $raw, string $type = 'image'): array {
        $status = YooY_Job_Status::normalize((string) ($raw['status'] ?? YooY_Job_Status::COMPLETED));
        $output = self::build_output($raw, $type);

        return [
            'job_id'       => (string) ($raw['job_id'] ?? $raw['id'] ?? ('job_' . wp_generate_uuid4())),
            'status'       => $status,
            'type'         => $type,
            'provider'     => (string) ($raw['provider'] ?? 'mock'),
            'model'        => (string) ($raw['model'] ?? ''),
            'prompt'       => (string) ($raw['prompt'] ?? $raw['text'] ?? $raw['script'] ?? $raw['lyrics'] ?? ''),
            'progress'     => (int) ($raw['progress'] ?? (YooY_Job_Status::is_terminal($status) ? 100 : 0)),
            'output'       => $output,
            'images'       => $raw['images'] ?? self::extract_images($raw, $output),
            'error'        => $raw['error'] ?? null,
            'credits_used' => (int) ($raw['credits_used'] ?? 0),
            'created_at'   => $raw['created_at'] ?? gmdate('c'),
            'updated_at'   => $raw['updated_at'] ?? gmdate('c'),
            'meta'         => is_array($raw['meta'] ?? null) ? $raw['meta'] : [],
            'raw'          => $raw['raw'] ?? null,
        ];
    }

    public static function from_vendor(string $provider, array $body): array {
        $status = match ($provider) {
            'runway' => YooY_Job_Status::normalize((string) ($body['status'] ?? 'running')),
            'replicate' => YooY_Job_Status::normalize((string) ($body['status'] ?? 'running')),
            default => YooY_Job_Status::normalize((string) ($body['status'] ?? 'completed')),
        };

        $output = null;
        if ($status === YooY_Job_Status::COMPLETED) {
            $output = self::vendor_output($provider, $body);
        }

        return [
            'job_id'   => (string) ($body['id'] ?? $body['job_id'] ?? ''),
            'status'   => $status,
            'progress' => (int) ($body['progress'] ?? ($status === YooY_Job_Status::COMPLETED ? 100 : 0)),
            'output'   => $output,
            'error'    => $body['error'] ?? $body['failure'] ?? null,
            'raw'      => $body,
        ];
    }

    private static function build_output(array $raw, string $type): array {
        if (!empty($raw['output']) && is_array($raw['output'])) {
            return self::normalize_output_shape($raw['output'], $type);
        }

        $images = $raw['images'] ?? [];
        if (!empty($images[0]['url'])) {
            return self::normalize_output_shape(['url' => $images[0]['url'], 'thumbnail' => $images[0]['thumbnail'] ?? $images[0]['url']], $type);
        }

        return ['urls' => [], 'primary' => '', 'mime' => self::mime_for_type($type), 'artifacts' => []];
    }

    private static function normalize_output_shape(array $output, string $type): array {
        $primary = $output['url']
            ?? $output['video_url']
            ?? $output['audio_url']
            ?? ($output['urls'][0] ?? '');

        $urls = $output['urls'] ?? array_values(array_filter([
            $output['url'] ?? null,
            $output['video_url'] ?? null,
            $output['audio_url'] ?? null,
            $output['thumbnail'] ?? null,
        ]));

        return [
            'urls'      => $urls,
            'primary'   => $primary,
            'mime'      => $output['mime'] ?? self::mime_for_type($type),
            'thumbnail' => $output['thumbnail'] ?? $output['cover_url'] ?? '',
            'artifacts' => array_diff_key($output, array_flip(['url', 'urls', 'primary', 'mime', 'thumbnail'])),
        ];
    }

    private static function extract_images(array $raw, array $output): array {
        if (!empty($raw['images'])) return $raw['images'];
        if ($output['primary'] ?? '') {
            return [['url' => $output['primary'], 'thumbnail' => $output['thumbnail'] ?? $output['primary']]];
        }
        return [];
    }

    private static function vendor_output(string $provider, array $body): ?array {
        return match ($provider) {
            'runway' => isset($body['output'][0]) ? self::normalize_output_shape(['url' => $body['output'][0], 'thumbnail' => $body['thumbnail'] ?? ''], 'video') : null,
            'replicate' => isset($body['output']) ? self::normalize_output_shape(['urls' => (array) $body['output'], 'url' => is_array($body['output']) ? ($body['output'][0] ?? '') : $body['output']], 'image') : null,
            'openai' => isset($body['data'][0]['url']) ? self::normalize_output_shape(['url' => $body['data'][0]['url']], 'image') : null,
            default => null,
        };
    }

    private static function mime_for_type(string $type): string {
        return match ($type) {
            'video', 'avatar' => 'video/mp4',
            'music', 'voice'  => 'audio/mpeg',
            'image'           => 'image/png',
            default           => 'text/plain',
        };
    }
}
