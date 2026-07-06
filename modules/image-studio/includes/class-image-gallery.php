<?php
if (!defined('ABSPATH')) exit;

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
        return $this->store->list($user_id, ['type' => 'image']);
    }

    public function save(int $user_id, array $item): array {
        return $this->store->save($user_id, array_merge($item, ['type' => 'image', 'studio' => 'image-studio']));
    }

    public function save_from_result(int $user_id, array $result): void {
        foreach (($result['images'] ?? []) as $i => $img) {
            $this->store->save($user_id, [
                'id'           => ($result['job_id'] ?? '') . '_' . $i,
                'type'         => 'image',
                'studio'       => 'image-studio',
                'title'        => mb_substr($result['prompt'] ?? 'Generated', 0, 40),
                'prompt'       => $result['prompt'] ?? '',
                'provider'     => $result['provider'] ?? 'mock',
                'model'        => $result['model'] ?? '',
                'credits_used' => (int) ($result['credits_used'] ?? 0),
                'thumbnail'    => $img['thumbnail'] ?? $img['url'] ?? '',
                'output_url'   => $img['url'] ?? '',
                'created_at'   => $result['created_at'] ?? gmdate('c'),
                'meta'         => [
                    'aspect_ratio' => $result['aspect_ratio'] ?? '1:1',
                    'resolution'   => $result['resolution'] ?? '1024',
                    'style'        => $result['style'] ?? '',
                    'index'        => $i,
                    'parent_job'   => $result['job_id'] ?? '',
                ],
            ]);
        }
    }

    public function remove(int $user_id, string $id): bool {
        return $this->store->remove($user_id, $id);
    }
}
