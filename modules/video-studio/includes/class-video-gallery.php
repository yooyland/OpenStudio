<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Gallery {

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
        return $this->store->list($user_id, ['type' => 'video']);
    }

    public function save(int $user_id, array $item): array {
        return $this->store->save($user_id, array_merge($item, ['type' => 'video', 'studio' => 'video-studio']));
    }

    public function auto_save(int $user_id, array $entry, bool $enabled): void {
        if (!$enabled) return;
        $output = $entry['output'] ?? [];
        $this->store->save($user_id, [
            'id'           => $entry['job_id'] ?? $entry['id'] ?? ('vgal_' . wp_generate_uuid4()),
            'type'         => 'video',
            'studio'       => 'video-studio',
            'title'        => mb_substr($entry['prompt'] ?? 'Generated Video', 0, 40),
            'prompt'       => $entry['prompt'] ?? '',
            'provider'     => $entry['provider'] ?? 'mock',
            'model'        => $entry['model'] ?? '',
            'credits_used' => (int) ($entry['credits_used'] ?? 0),
            'thumbnail'    => $output['thumbnail'] ?? $output['primary'] ?? '',
            'output_url'   => $output['primary'] ?? $output['url'] ?? '',
            'created_at'   => $entry['created_at'] ?? gmdate('c'),
            'meta'         => [
                'aspect_ratio' => $entry['aspect_ratio'] ?? '16:9',
                'duration'     => $entry['duration'] ?? 5,
            ],
        ]);
    }

    public function remove(int $user_id, string $id): bool {
        return $this->store->remove($user_id, $id);
    }
}
