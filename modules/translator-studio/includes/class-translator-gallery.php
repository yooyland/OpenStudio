<?php
if (!defined('ABSPATH')) exit;

/**
 * Saves Translator results into Gallery Store as type=translation.
 * History / My Works reuse the same Gallery items.
 */
final class YooY_Translator_Gallery {

    /** @var YooY_Gallery_Store|null */
    private $store;

    public function __construct() {
        $this->store = $this->boot_store();
    }

    public function is_ready(): bool {
        return $this->store !== null;
    }

    /**
     * @return array{saved:bool,gallery_item_id:?string,item:?array,error?:string}
     */
    public function save_translation(int $user_id, array $payload): array {
        if (!$this->store) {
            return [
                'saved'           => false,
                'gallery_item_id' => null,
                'item'            => null,
                'error'           => 'gallery_unavailable',
            ];
        }

        $source = (string) ($payload['source_text'] ?? '');
        $translated = (string) ($payload['translated_text'] ?? '');
        $project_id = sanitize_text_field((string) ($payload['project_id'] ?? ''));

        $settings = [
            'source_language' => (string) ($payload['source_language'] ?? 'auto'),
            'target_language' => (string) ($payload['target_language'] ?? 'en'),
            'mode'            => (string) ($payload['mode'] ?? 'natural'),
            'provider'        => (string) ($payload['provider'] ?? 'mock'),
            'model'           => (string) ($payload['model'] ?? ''),
        ];

        $item = [
            'id'            => 'tr_' . wp_generate_uuid4(),
            'type'          => 'translation',
            'studio'        => 'translator-studio',
            'prompt'        => $source,
            'text'          => $source,
            'user_prompt'   => $source,
            'provider'      => (string) ($payload['provider'] ?? 'mock'),
            'model'         => (string) ($payload['model'] ?? ''),
            'credits_used'  => (int) ($payload['credits_used'] ?? 0),
            'user_id'       => $user_id,
            'project_id'    => $project_id,
            'settings'      => $settings,
            'meta'          => [
                'translated_text'   => $translated,
                'source_language'   => $settings['source_language'],
                'target_language'   => $settings['target_language'],
                'mode'              => $settings['mode'],
                'detected_language' => (string) ($payload['detected_language'] ?? ''),
                'character_count'   => (int) ($payload['character_count'] ?? 0),
                'fallback_used'     => !empty($payload['fallback_used']),
                'fallback_from'     => (string) ($payload['fallback_from'] ?? ''),
                'project_id'        => $project_id,
            ],
        ];

        try {
            $saved = $this->store->save($user_id, $item);
            if ($project_id !== '') {
                $this->link_project($user_id, $project_id, $saved);
            }
            return [
                'saved'           => true,
                'gallery_item_id' => (string) ($saved['id'] ?? ''),
                'item'            => $saved,
            ];
        } catch (Exception $e) {
            return [
                'saved'           => false,
                'gallery_item_id' => null,
                'item'            => null,
                'error'           => $e->getMessage(),
            ];
        }
    }

    public function list_history(int $user_id, int $limit = 50): array {
        if (!$this->store) {
            return [];
        }
        $items = $this->store->list($user_id, ['type' => 'translation']);
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }
        return array_map([$this, 'history_row'], $items);
    }

    public function get_item(int $user_id, string $id): ?array {
        if (!$this->store) {
            return null;
        }
        $item = $this->store->get($user_id, $id);
        if (!$item || ($item['type'] ?? '') !== 'translation') {
            return null;
        }
        return $item;
    }

    /**
     * Payload for reopening a translation in Translator Studio.
     */
    public function reopen_payload(int $user_id, string $id): ?array {
        $item = $this->get_item($user_id, $id);
        if (!$item) {
            return null;
        }
        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
        $settings = is_array($item['settings'] ?? null) ? $item['settings'] : [];
        return [
            'id'                 => $item['id'],
            'source_text'        => (string) ($item['user_prompt'] ?? $item['prompt'] ?? ''),
            'translated_text'    => (string) ($meta['translated_text'] ?? $item['translated_text'] ?? ''),
            'source_language'    => (string) ($meta['source_language'] ?? $settings['source_language'] ?? 'auto'),
            'target_language'    => (string) ($meta['target_language'] ?? $settings['target_language'] ?? 'en'),
            'mode'               => (string) ($meta['mode'] ?? $settings['mode'] ?? 'natural'),
            'provider'           => (string) ($item['provider'] ?? 'mock'),
            'model'              => (string) ($item['model'] ?? ''),
            'detected_language'  => (string) ($meta['detected_language'] ?? ''),
            'project_id'         => (string) ($item['project_id'] ?? $meta['project_id'] ?? ''),
            'gallery_item_id'    => (string) ($item['id'] ?? ''),
        ];
    }

    private function history_row(array $item): array {
        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
        $source = (string) ($item['user_prompt'] ?? $item['prompt'] ?? '');
        $preview = function_exists('mb_substr') ? mb_substr($source, 0, 80, 'UTF-8') : substr($source, 0, 80);
        return [
            'id'                => (string) ($item['id'] ?? ''),
            'title'             => (string) ($item['title'] ?? ''),
            'preview'           => $preview,
            'source_language'   => (string) ($meta['source_language'] ?? $item['source_language'] ?? ''),
            'target_language'   => (string) ($meta['target_language'] ?? $item['target_language'] ?? ''),
            'mode'              => (string) ($meta['mode'] ?? $item['translation_mode'] ?? ''),
            'provider'          => (string) ($item['provider'] ?? ''),
            'model'             => (string) ($item['model'] ?? ''),
            'credits_used'      => (int) ($item['credits_used'] ?? 0),
            'created_at'        => (string) ($item['created_at'] ?? ''),
            'project_id'        => (string) ($item['project_id'] ?? ''),
        ];
    }

    private function link_project(int $user_id, string $project_id, array $item): void {
        if (!class_exists('YooY_Project_Store')) {
            $path = defined('YOY_AI_STUDIO_MODULES_DIR')
                ? YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php'
                : '';
            if ($path && file_exists($path)) {
                require_once $path;
            }
        }
        if (!class_exists('YooY_Project_Store')) {
            return;
        }
        try {
            (new YooY_Project_Store())->link_gallery_item($user_id, $project_id, $item);
        } catch (Exception $e) {
            // Project link failure must not roll back a successful translation save.
        }
    }

    private function boot_store(): ?YooY_Gallery_Store {
        if (!class_exists('YooY_Gallery_Store')) {
            $path = defined('YOY_AI_STUDIO_MODULES_DIR')
                ? YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php'
                : '';
            if ($path && file_exists($path)) {
                require_once $path;
            }
        }
        if (!class_exists('YooY_Gallery_Title_Service')) {
            $path = defined('YOY_AI_STUDIO_MODULES_DIR')
                ? YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-title-service.php'
                : '';
            if ($path && file_exists($path)) {
                require_once $path;
            }
        }
        return class_exists('YooY_Gallery_Store') ? new YooY_Gallery_Store() : null;
    }
}
