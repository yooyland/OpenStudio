<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Gallery {

    private YooY_Gallery_Store $store;

    public function __construct(?YooY_Gallery_Store $store = null) {
        if ($store === null) {
            require_once YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
            $store = new YooY_Gallery_Store();
        }
        $this->store = $store;
    }

    public function list(int $user_id): array {
        return $this->store->list($user_id, ['type' => 'music']);
    }

    public function save(int $user_id, array $item): array {
        return $this->store->save($user_id, array_merge($item, ['type' => 'music', 'studio' => 'music-studio']));
    }

    public function auto_save(int $user_id, array $result): void {
        $output = $result['output'] ?? [];
        $this->store->save($user_id, [
            'id'           => $result['job_id'] ?? $result['id'] ?? ('mgal_' . wp_generate_uuid4()),
            'type'         => 'music',
            'studio'       => 'music-studio',
            'title'        => $result['title'] ?? mb_substr($result['lyrics'] ?? 'Track', 0, 30),
            'prompt'       => $result['prompt'] ?? $result['style_prompt'] ?? $result['lyrics'] ?? '',
            'provider'     => $result['provider'] ?? 'mock',
            'model'        => $result['model'] ?? '',
            'credits_used' => (int) ($result['credits_used'] ?? 0),
            'thumbnail'    => $output['cover_url'] ?? $output['thumbnail'] ?? '',
            'output_url'   => $output['audio_url'] ?? $output['primary'] ?? '',
            'created_at'   => $result['created_at'] ?? gmdate('c'),
            'meta'         => [
                'genre'    => $result['genre'] ?? '',
                'mood'     => $result['mood'] ?? '',
                'duration' => $result['duration'] ?? 0,
            ],
        ]);
    }

    public function remove(int $user_id, string $id): bool {
        return $this->store->remove($user_id, $id);
    }
}
