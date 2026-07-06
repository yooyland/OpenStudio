<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_History {

    private YooY_Job_Store $store;

    public function __construct(?YooY_Job_Store $store = null) {
        $this->store = $store ?? new YooY_Job_Store();
    }

    public function list(int $user_id, int $limit = 50): array {
        return array_slice($this->store->list($user_id, [
            'type'   => 'image',
            'studio' => 'image-studio',
        ]), 0, $limit);
    }

    public function get(int $user_id, string $id): ?array {
        $item = $this->store->get($user_id, $id);
        if ($item && ($item['studio'] ?? '') === 'image-studio') return $item;
        return null;
    }

    public function add(int $user_id, array $result): array {
        return $this->store->save($user_id, $result, 'image-studio');
    }

    public function clear(int $user_id): void {
        $this->store->clear($user_id, 'image-studio');
    }
}
