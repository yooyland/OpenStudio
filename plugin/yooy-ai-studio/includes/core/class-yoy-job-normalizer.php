<?php
if (!defined('ABSPATH')) exit;

final class YooY_Job_Normalizer {

    public static function normalize(array $raw, string $type = 'image'): array {
        $status = YooY_Job_Status::normalize((string) ($raw['status'] ?? YooY_Job_Status::COMPLETED));
        $output = self::build_output($raw, $type);

        return [
            'job_id'          => (string) ($raw['job_id'] ?? $raw['id'] ?? ('job_' . wp_generate_uuid4())),
            'status'          => $status,
            'type'            => $type,
            'provider'        => (string) ($raw['provider'] ?? 'mock'),
            'provider_used'   => (string) ($raw['provider_used'] ?? $raw['provider'] ?? 'mock'),
            'provider_job_id' => (string) ($raw['provider_job_id'] ?? $raw['meta']['provider_job_id'] ?? ''),
            'requested_provider' => $raw['requested_provider'] ?? null,
            'fallback_reason'=> $raw['fallback_reason'] ?? null,
            'warning'       => $raw['warning'] ?? null,
            'model'           => (string) ($raw['model'] ?? ''),
            'prompt'          => (string) ($raw['prompt'] ?? $raw['text'] ?? $raw['script'] ?? $raw['lyrics'] ?? ''),
            'progress'        => (int) ($raw['progress'] ?? (YooY_Job_Status::is_terminal($status) ? 100 : 0)),
            'stage'           => self::derive_stage($raw, $status),
            'output'          => $output,
            'images'       => $raw['images'] ?? self::extract_images($raw, $output),
            'error'        => $raw['error'] ?? null,
            'credits_used' => (int) ($raw['credits_used'] ?? 0),
            'created_at'   => $raw['created_at'] ?? gmdate('c'),
            'updated_at'   => $raw['updated_at'] ?? gmdate('c'),
            'progress_updated_at' => $raw['progress_updated_at'] ?? $raw['created_at'] ?? gmdate('c'),
            'meta'         => is_array($raw['meta'] ?? null) ? $raw['meta'] : [],
            'raw'          => $raw['raw'] ?? null,
        ];
    }

    /** Completed jobs must include a displayable asset or become failed. */
    public static function ensure_output_or_fail(array $normalized): array {
        if (($normalized['status'] ?? '') !== YooY_Job_Status::COMPLETED) {
            return $normalized;
        }
        if (class_exists('YooY_Asset_Generator') && YooY_Asset_Generator::has_displayable_asset($normalized)) {
            return $normalized;
        }
        $normalized['status'] = YooY_Job_Status::FAILED;
        $normalized['error']  = 'Generation completed but no output asset was returned.';
        $normalized['progress'] = 0;
        return $normalized;
    }

    public static function from_vendor(string $provider, array $body): array {
        $vendor_status = strtolower((string) ($body['status'] ?? $body['state'] ?? 'running'));
        switch ($provider) {
            case 'runway':
                $status = YooY_Job_Status::normalize($vendor_status);
                if (!empty($body['failure']) || !empty($body['error'])) {
                    $status = YooY_Job_Status::FAILED;
                }
                break;
            case 'replicate':
                $status = YooY_Job_Status::normalize($vendor_status);
                break;
            default:
                $status = YooY_Job_Status::normalize($vendor_status);
                break;
        }

        $output = null;
        if ($status === YooY_Job_Status::COMPLETED) {
            $output = self::vendor_output($provider, $body);
        }

        $progress = (int) ($body['progress'] ?? 0);
        if ($progress <= 0 && $status === YooY_Job_Status::COMPLETED) {
            $progress = 100;
        } elseif ($progress <= 0 && $status === YooY_Job_Status::RUNNING) {
            if (in_array($vendor_status, ['processing', 'rendering'], true)) {
                $progress = 50;
            }
        }

        return [
            'job_id'        => (string) ($body['id'] ?? $body['job_id'] ?? ''),
            'provider_job_id' => (string) ($body['id'] ?? $body['job_id'] ?? ''),
            'status'        => $status,
            'vendor_status' => $vendor_status,
            'progress'      => $progress,
            'output'        => $output,
            'error'         => $body['error'] ?? $body['failure'] ?? $body['failureReason'] ?? null,
            'raw'           => $body,
        ];
    }

    public static function derive_stage(array $raw, string $status = ''): string {
        if ($status === '') {
            $status = YooY_Job_Status::normalize((string) ($raw['status'] ?? ''));
        }
        if ($status === YooY_Job_Status::FAILED) {
            return 'failed';
        }
        if ($status === YooY_Job_Status::COMPLETED) {
            return 'completed';
        }
        $vendor = strtolower((string) ($raw['vendor_status'] ?? $raw['raw']['status'] ?? $raw['raw']['state'] ?? ''));
        if ($vendor === 'rendering') {
            return 'rendering';
        }
        if ($vendor === 'processing' || ((int) ($raw['progress'] ?? 0) > 0 && (int) ($raw['progress'] ?? 0) < 100)) {
            return 'processing';
        }
        if ($status === YooY_Job_Status::QUEUED) {
            return 'queued';
        }
        return 'running';
    }

    private static function build_output(array $raw, string $type): array {
        if (!empty($raw['output']) && is_array($raw['output'])) {
            return self::normalize_output_shape($raw['output'], $type);
        }

        $images = $raw['images'] ?? [];
        if (!empty($images[0])) {
            $first = $images[0];
            $url = $first['url'] ?? '';
            $thumb = $first['thumbnail'] ?? $url;
            if ($url === '' && !empty($first['attachment_id']) && class_exists('YooY_Asset_Generator')) {
                $resolved = YooY_Asset_Generator::resolve_attachment((int) $first['attachment_id']);
                $url = $resolved['url'] ?? '';
                $thumb = $resolved['thumbnail'] ?? $url;
            }
            if ($url !== '') {
                return self::normalize_output_shape([
                    'url' => $url,
                    'thumbnail' => $thumb ?: $url,
                    'attachment_id' => (int) ($first['attachment_id'] ?? 0),
                ], $type);
            }
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
        if (!empty($raw['images'])) {
            return $raw['images'];
        }
        if (!empty($output['primary'])) {
            return [[
                'url' => $output['primary'],
                'thumbnail' => $output['thumbnail'] ?? $output['primary'],
                'attachment_id' => (int) ($output['attachment_id'] ?? 0),
            ]];
        }
        return [];
    }

    private static function vendor_output(string $provider, array $body): ?array {
        switch ($provider) {
            case 'runway':
                if (!empty($body['output'][0])) {
                    return self::normalize_output_shape(['url' => $body['output'][0], 'thumbnail' => $body['thumbnail'] ?? ''], 'video');
                }
                if (!empty($body['assets']) && is_array($body['assets'])) {
                    $url = $body['assets'][0]['url'] ?? $body['assets'][0]['uri'] ?? '';
                    if ($url !== '') {
                        return self::normalize_output_shape(['url' => $url, 'thumbnail' => $body['thumbnail'] ?? ''], 'video');
                    }
                }
                return null;
            case 'replicate':
                return isset($body['output']) ? self::normalize_output_shape(['urls' => (array) $body['output'], 'url' => is_array($body['output']) ? ($body['output'][0] ?? '') : $body['output']], 'image') : null;
            case 'openai':
                return isset($body['data'][0]['url']) ? self::normalize_output_shape(['url' => $body['data'][0]['url']], 'image') : null;
            default:
                return null;
        }
    }

    private static function mime_for_type(string $type): string {
        switch ($type) {
            case 'video':
            case 'avatar':
                return 'video/mp4';
            case 'music':
            case 'voice':
                return 'audio/mpeg';
            case 'image':
                return 'image/png';
            default:
                return 'text/plain';
        }
    }
}
