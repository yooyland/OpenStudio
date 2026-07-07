<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('YooY_Asset_Generator') && defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
    require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
}

final class YooY_Image_Gallery {

    private YooY_Gallery_Store $store;

    public function __construct(?YooY_Gallery_Store $store = null) {
        if ($store === null) {
            if (!class_exists('YooY_Gallery_Store')) {
                require_once YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
            }
            $store = new YooY_Gallery_Store();
        }
        $this->store = $store;
    }

    public function list(int $user_id): array {
        if (class_exists('YooY_Gallery_Aggregator')) {
            $aggregator = new YooY_Gallery_Aggregator($this->store);
            $aggregator->reconcile_jobs($user_id);
        }
        return $this->store->list($user_id, ['type' => 'image']);
    }

    public function save(int $user_id, array $item): array {
        return $this->store->save($user_id, array_merge($item, ['type' => 'image', 'studio' => 'image-studio']));
    }

    public function save_from_result(int $user_id, array $result): array {
        $saved_ids = [];
        foreach (($result['images'] ?? []) as $i => $img) {
            $url = $img['url'] ?? '';
            $attachment_id = (int) ($img['attachment_id'] ?? 0);
            if ($attachment_id > 0) {
                $resolved = YooY_Asset_Generator::resolve_attachment($attachment_id);
                if (!empty($resolved['url'])) {
                    $url = $resolved['url'];
                }
                if (empty($img['thumbnail']) && !empty($resolved['thumbnail'])) {
                    $img['thumbnail'] = $resolved['thumbnail'];
                }
            }
            if (!YooY_Asset_Generator::is_http_asset_url($url) && $attachment_id <= 0) {
                $this->log_gallery_event('skip', $user_id, $result, $i, $url, $attachment_id, '');
                continue;
            }

            $thumb = $img['thumbnail'] ?? $url;
            if ($attachment_id > 0) {
                $resolved = YooY_Asset_Generator::resolve_attachment($attachment_id);
                if (!empty($resolved['thumbnail'])) {
                    $thumb = $resolved['thumbnail'];
                }
            }

            $job_id = (string) ($result['job_id'] ?? '');
            $this->log_gallery_event('before', $user_id, $result, $i, $url, $attachment_id, $thumb);
            $saved = $this->store->save($user_id, [
                'id'            => $job_id . '_' . $i,
                'type'          => 'image',
                'studio'        => 'image-studio',
                'title'         => '',
                'prompt'        => $result['prompt'] ?? '',
                'user_prompt'   => (string) ($result['user_prompt'] ?? ''),
                'optimized_prompt' => (string) ($result['optimized_prompt'] ?? ''),
                'negative_prompt'  => (string) ($result['negative_prompt'] ?? ''),
                'provider'      => $result['provider'] ?? $result['provider_used'] ?? 'mock',
                'model'         => $result['model'] ?? '',
                'job_id'        => $job_id,
                'user_id'       => $user_id,
                'attachment_id' => $attachment_id,
                'credits_used'  => (int) ($result['credits_used'] ?? 0),
                'image_url'     => $url,
                'thumbnail_url' => $thumb ?: $url,
                'thumbnail'     => $thumb ?: $url,
                'output_url'    => $url,
                'created_at'    => $result['created_at'] ?? gmdate('c'),
                'settings'      => [
                    'aspect_ratio' => $result['aspect_ratio'] ?? '1:1',
                    'resolution'   => $result['resolution'] ?? '1024',
                    'style'        => $result['style'] ?? '',
                    'quality'      => $result['quality'] ?? '',
                    'provider'     => $result['provider'] ?? '',
                    'model'        => $result['model'] ?? '',
                ],
                'meta'          => [
                    'aspect_ratio'     => $result['aspect_ratio'] ?? '1:1',
                    'resolution'       => $result['resolution'] ?? '1024',
                    'style'            => $result['style'] ?? '',
                    'index'            => $i,
                    'parent_job'       => $job_id,
                    'attachment_id'    => $attachment_id,
                    'optimized_prompt' => $result['optimized_prompt'] ?? '',
                    'user_prompt'      => $result['user_prompt'] ?? '',
                    'reference_url'    => $result['reference_url'] ?? '',
                    'reference_assets' => $result['reference_assets'] ?? [],
                ],
            ]);
            if (!empty($saved['id'])) {
                $saved_ids[] = (string) $saved['id'];
            }
            $this->log_gallery_event('after', $user_id, $result, $i, $url, $attachment_id, $thumb);
        }
        return $saved_ids;
    }

    private function log_gallery_event(string $phase, int $user_id, array $result, int $index, string $asset_url, int $attachment_id, string $thumbnail_url): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        error_log(wp_json_encode([
            'event'          => 'yoy_gallery_save_' . $phase,
            'user_id'        => $user_id,
            'job_id'         => $result['job_id'] ?? '',
            'provider'       => $result['provider'] ?? '',
            'model'          => $result['model'] ?? '',
            'index'          => $index,
            'asset_url'      => $asset_url,
            'attachment_id'  => $attachment_id,
            'thumbnail_url'  => $thumbnail_url,
        ]));
    }

    public function remove(int $user_id, string $id): bool {
        return $this->store->remove($user_id, $id);
    }
}
