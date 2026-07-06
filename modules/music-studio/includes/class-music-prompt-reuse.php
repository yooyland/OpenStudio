<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Prompt_Reuse {

    private YooY_Music_History $history;
    private YooY_Music_Gallery $gallery;

    public function __construct(YooY_Music_History $history, YooY_Music_Gallery $gallery) {
        $this->history = $history;
        $this->gallery = $gallery;
    }

    public function remix(int $user_id, array $params): array {
        $type = sanitize_text_field($params['source_type'] ?? 'history');
        $id   = sanitize_text_field($params['source_id'] ?? '');
        $base = $type === 'gallery' ? $this->from_gallery($user_id, $id) : $this->from_history($user_id, $id);

        if (!empty($params['lyrics_override'])) $base['lyrics'] = sanitize_textarea_field($params['lyrics_override']);
        if (!empty($params['style_override'])) $base['style_prompt'] = sanitize_textarea_field($params['style_override']);

        $base['remix'] = true;
        $base['remix_source'] = ['type' => $type, 'id' => $id];
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

    private function payload(array $item): array {
        return [
            'title'       => $item['title'] ?? '',
            'lyrics'      => $item['lyrics'] ?? '',
            'genre'       => $item['genre'] ?? 'k-pop',
            'mood'        => $item['mood'] ?? 'upbeat',
            'tempo'       => $item['tempo'] ?? 120,
            'instrument'  => $item['instrument'] ?? 'synth',
            'vocal'       => $item['vocal'] ?? 'female',
            'language'    => $item['language'] ?? 'ko',
            'structure'   => $item['structure'] ?? [],
            'provider'    => $item['provider'] ?? 'mock',
            'negative_prompt' => $item['negative_prompt'] ?? '',
            'korean_context'  => true,
        ];
    }
}
