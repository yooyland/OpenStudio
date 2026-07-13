<?php
if (!defined('ABSPATH')) exit;

/**
 * Saves Translator results into Gallery Store as Language Assets (type=translation).
 * History = Gallery filter(type=translation). Projects via Gallery_Actions.
 *
 * Meta is open for future Language Asset keys (parent_asset_id, revision, …)
 * without writing them yet — see docs/LANGUAGE_ASSET.md.
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
     * @return array{saved:bool,gallery_item_id:?string,item:?array,project_id?:string,error?:string}
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

        // Same Gallery schema as Image/Video/Music/Voice — text-only asset for translation.
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
            'public'        => false,
            'favorite'      => false,
            'project_id'    => $project_id,
            'settings'      => $settings,
            'meta'          => [
                // Canonical Language Asset fields (active).
                'source_type'       => (string) ($payload['source_type'] ?? 'text'),
                'translated_text'   => $translated,
                'source_language'   => $settings['source_language'],
                'target_language'   => $settings['target_language'],
                'mode'              => $settings['mode'],
                'detected_language' => (string) ($payload['detected_language'] ?? ''),
                'character_count'   => (int) ($payload['character_count'] ?? 0),
                'fallback_used'     => !empty($payload['fallback_used']),
                'fallback_from'     => (string) ($payload['fallback_from'] ?? ''),
                'project_id'        => $project_id,
                // Reserved Language Asset meta (NOT written yet — see docs/LANGUAGE_ASSET.md,
                // docs/TRANSLATOR_SOURCE_TYPES.md):
                // asset_uuid, parent_asset_uuid, revision, origin, pipeline, pipeline_step,
                // workflow_id, source_url, source_title, source_mime_type, source_filename,
                // source_filesize, source_attachment_id, source_external_id, source_provider,
                // source_content_hash, source_excerpt, source_metadata, output_type,
                // output_attachment_id, output_filename, processing_status.
            ],
        ];

        try {
            $saved = $this->store->save($user_id, $item);
            $gid = (string) ($saved['id'] ?? '');

            // Reuse Gallery → Projects link path (no Translator-specific project store).
            if ($gid !== '' && $project_id !== '') {
                $linked = $this->link_to_project($user_id, $gid, $project_id);
                if ($linked) {
                    $saved = $linked;
                }
            }

            return [
                'saved'           => true,
                'gallery_item_id' => $gid,
                'item'            => $saved,
                'project_id'      => (string) ($saved['project_id'] ?? $project_id),
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
     * @throws YooY_Translator_Exception
     */
    public function toggle_favorite(int $user_id, string $id): array {
        $item = $this->get_item($user_id, $id);
        if (!$item || !$this->store) {
            throw new YooY_Translator_Exception('번역 기록을 찾을 수 없습니다.', 'history_not_found', 404);
        }
        $next = empty($item['favorite']);
        $updated = $this->store->update($user_id, $id, ['favorite' => $next]);
        if (!$updated || ($updated['type'] ?? '') !== 'translation') {
            throw new YooY_Translator_Exception('즐겨찾기를 변경할 수 없습니다.', 'favorite_failed', 400);
        }
        return $this->history_row($updated);
    }

    /**
     * Deletes via Gallery_Actions (cleans Project assets) — same path as Gallery UI.
     *
     * @throws YooY_Translator_Exception
     */
    public function delete_item(int $user_id, string $id): bool {
        $item = $this->get_item($user_id, $id);
        if (!$item || !$this->store) {
            throw new YooY_Translator_Exception('번역 기록을 찾을 수 없습니다.', 'history_not_found', 404);
        }

        $actions = $this->boot_actions();
        if ($actions) {
            try {
                $actions->delete_item($user_id, $id, false);
                return true;
            } catch (Exception $e) {
                throw new YooY_Translator_Exception('번역 기록을 삭제할 수 없습니다.', 'delete_failed', 400);
            }
        }

        $ok = $this->store->remove($user_id, $id);
        if (!$ok) {
            throw new YooY_Translator_Exception('번역 기록을 삭제할 수 없습니다.', 'delete_failed', 400);
        }
        return true;
    }

    /**
     * Attach an existing Gallery translation item to a Project (Gallery_Actions).
     *
     * @return array{project:?array,item:?array}
     * @throws YooY_Translator_Exception
     */
    public function attach_to_project(int $user_id, string $id, ?string $project_id = null): array {
        $item = $this->get_item($user_id, $id);
        if (!$item || !$this->store) {
            throw new YooY_Translator_Exception('번역 기록을 찾을 수 없습니다.', 'history_not_found', 404);
        }
        $actions = $this->boot_actions();
        if (!$actions) {
            throw new YooY_Translator_Exception('Project 연동을 사용할 수 없습니다.', 'projects_unavailable', 503);
        }
        try {
            // null → create/link default project (same as Music/Image Gallery.project()).
            $result = $actions->save_to_project($user_id, $id, $project_id);
            return [
                'project' => $result['project'] ?? null,
                'item'    => $result['item'] ?? null,
            ];
        } catch (Exception $e) {
            throw new YooY_Translator_Exception(
                $e->getMessage() !== '' ? $e->getMessage() : 'Project에 저장할 수 없습니다.',
                'project_link_failed',
                400
            );
        }
    }

    /**
     * Best-effort stamp of credits_used after successful ledger deduct.
     */
    public function stamp_credits_used(int $user_id, string $id, int $credits_used): void {
        if (!$this->store || $credits_used < 0) {
            return;
        }
        $item = $this->get_item($user_id, $id);
        if (!$item) {
            return;
        }
        try {
            $this->store->update($user_id, $id, ['credits_used' => $credits_used]);
        } catch (Exception $e) {
            // non-fatal
        }
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
            'favorite'           => !empty($item['favorite']),
            'gallery_item_id'    => (string) ($item['id'] ?? ''),
            'project_id'         => (string) ($item['project_id'] ?? $meta['project_id'] ?? ''),
        ];
    }

    private function history_row(array $item): array {
        $meta = is_array($item['meta'] ?? null) ? $item['meta'] : [];
        $source = (string) ($item['user_prompt'] ?? $item['prompt'] ?? '');
        $translated = (string) ($meta['translated_text'] ?? $item['translated_text'] ?? '');
        $preview = function_exists('mb_substr') ? mb_substr($source, 0, 80, 'UTF-8') : substr($source, 0, 80);
        return [
            'id'                => (string) ($item['id'] ?? ''),
            'title'             => (string) ($item['title'] ?? ''),
            'preview'           => $preview,
            'translated_text'   => $translated,
            'source_type'       => (string) ($meta['source_type'] ?? 'text'),
            'source_language'   => (string) ($meta['source_language'] ?? $item['source_language'] ?? ''),
            'target_language'   => (string) ($meta['target_language'] ?? $item['target_language'] ?? ''),
            'detected_language' => (string) ($meta['detected_language'] ?? ''),
            'mode'              => (string) ($meta['mode'] ?? $item['translation_mode'] ?? ''),
            'provider'          => (string) ($item['provider'] ?? ''),
            'model'             => (string) ($item['model'] ?? ''),
            'credits_used'      => (int) ($item['credits_used'] ?? 0),
            'favorite'          => !empty($item['favorite']),
            'public'            => !empty($item['public']),
            'project_id'        => (string) ($item['project_id'] ?? $meta['project_id'] ?? ''),
            'created_at'        => (string) ($item['created_at'] ?? ''),
        ];
    }

    /**
     * @return array|null Updated gallery item after link, or null on soft failure.
     */
    private function link_to_project(int $user_id, string $gallery_id, string $project_id): ?array {
        $actions = $this->boot_actions();
        if (!$actions) {
            return null;
        }
        try {
            $result = $actions->save_to_project($user_id, $gallery_id, $project_id);
            return is_array($result['item'] ?? null) ? $result['item'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function boot_actions(): ?YooY_Gallery_Actions {
        if (!$this->store) {
            return null;
        }
        if (!class_exists('YooY_Gallery_Actions')) {
            $path = defined('YOY_AI_STUDIO_MODULES_DIR')
                ? YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-actions.php'
                : '';
            if ($path && file_exists($path)) {
                require_once $path;
            }
        }
        if (!class_exists('YooY_Project_Store')) {
            $path = defined('YOY_AI_STUDIO_MODULES_DIR')
                ? YOY_AI_STUDIO_MODULES_DIR . 'projects/includes/class-project-store.php'
                : '';
            if ($path && file_exists($path)) {
                require_once $path;
            }
        }
        return class_exists('YooY_Gallery_Actions') ? new YooY_Gallery_Actions($this->store) : null;
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
