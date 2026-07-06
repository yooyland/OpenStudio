<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_Gallery {

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
        return $this->store->list($user_id, ['type' => 'avatar']);
    }

    public function save(int $user_id, array $item): array {
        return $this->store->save($user_id, array_merge($item, ['type' => 'avatar', 'studio' => 'avatar-studio']));
    }

    public function auto_save(int $user_id, array $result): void {
        $output = $result['output'] ?? [];
        $this->store->save($user_id, [
            'id'           => $result['job_id'] ?? $result['id'] ?? ('agal_' . wp_generate_uuid4()),
            'type'         => 'avatar',
            'studio'       => 'avatar-studio',
            'title'        => mb_substr($result['script'] ?? 'Avatar', 0, 40),
            'prompt'       => $result['script'] ?? '',
            'provider'     => $result['provider'] ?? 'mock',
            'model'        => $result['model'] ?? '',
            'credits_used' => (int) ($result['credits_used'] ?? 0),
            'thumbnail'    => $output['thumbnail'] ?? '',
            'output_url'   => $output['video_url'] ?? $output['primary'] ?? '',
            'created_at'   => $result['created_at'] ?? gmdate('c'),
            'meta'         => [
                'avatar_id' => $result['avatar'] ?? $result['avatar_id'] ?? '',
                'scene_id'  => $result['scene'] ?? $result['scene_id'] ?? '',
            ],
        ]);
    }

    public function remove(int $user_id, string $id): bool {
        return $this->store->remove($user_id, $id);
    }
}
