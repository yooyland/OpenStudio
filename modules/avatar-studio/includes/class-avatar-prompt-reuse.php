<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_Prompt_Reuse {

    private YooY_Avatar_History $history;
    private YooY_Avatar_Gallery $gallery;
    private YooY_Avatar_Catalog $catalog;

    public function __construct(YooY_Avatar_History $history, YooY_Avatar_Gallery $gallery, YooY_Avatar_Catalog $catalog) {
        $this->history = $history;
        $this->gallery = $gallery;
        $this->catalog = $catalog;
    }

    public function remix(int $user_id, array $params): array {
        $type = sanitize_text_field($params['source_type'] ?? 'history');
        $id   = sanitize_text_field($params['source_id'] ?? '');

        switch ($type) {
            case 'gallery':
                $base = $this->from_gallery($user_id, $id);
                break;
            case 'scene':
                $base = $this->from_scene($id);
                break;
            default:
                $base = $this->from_history($user_id, $id);
                break;
        }

        if (!empty($params['script_override'])) $base['script'] = sanitize_textarea_field($params['script_override']);
        $base['remix'] = true;
        return $base;
    }

    private function from_history(int $user_id, string $id): array {
        $item = $this->history->get($user_id, $id);
        if (!$item) throw new Exception('History item not found.');
        return $this->payload($item);
    }

    private function from_gallery(int $user_id, string $id): array {
        foreach ($this->gallery->list($user_id) as $item) {
            if (($item['id'] ?? '') === $id) return $this->payload($item);
        }
        throw new Exception('Gallery item not found.');
    }

    private function from_scene(string $id): array {
        foreach ($this->catalog->scenes() as $scene) {
            if ($scene['id'] === $id) {
                return [
                    'scene_id' => $scene['id'],
                    'script'   => $scene['template'],
                    'korean_context' => true,
                ];
            }
        }
        throw new Exception('Scene not found.');
    }

    private function payload(array $item): array {
        return [
            'script'           => $item['script'] ?? '',
            'avatar_id'        => $item['avatar_id'] ?? $item['avatar'] ?? 'ko_female_01',
            'voice_id'         => $item['voice_id'] ?? $item['voice'] ?? 'ko_female_warm',
            'expression'       => $item['expression'] ?? 'friendly',
            'gesture'          => $item['gesture'] ?? 'natural',
            'camera'           => $item['camera'] ?? 'medium',
            'emotion'          => $item['emotion'] ?? 'confident',
            'background'       => $item['background'] ?? 'studio',
            'scene_id'         => $item['scene_id'] ?? $item['scene'] ?? 'product_intro',
            'lip_sync'         => $item['lip_sync'] ?? true,
            'subtitle_enabled' => $item['subtitle_enabled'] ?? true,
            'provider'         => $item['provider'] ?? 'mock',
            'korean_context'   => true,
        ];
    }
}
