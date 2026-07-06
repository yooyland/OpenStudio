<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_Prompt_Reuse {

    private YooY_Image_History $history;
    private YooY_Image_Gallery $gallery;

    public function __construct(YooY_Image_History $history, YooY_Image_Gallery $gallery) {
        $this->history = $history;
        $this->gallery = $gallery;
    }

    public function remix(int $user_id, array $params): array {
        $source_type = sanitize_text_field($params['source_type'] ?? 'history');
        $source_id   = sanitize_text_field($params['source_id'] ?? '');
        $override    = sanitize_textarea_field($params['prompt_override'] ?? '');

        $base = match ($source_type) {
            'gallery' => $this->from_gallery($user_id, $source_id),
            default   => $this->from_history($user_id, $source_id),
        };

        if ($override !== '') $base['prompt'] = $override;
        $base['remix'] = true;
        $base['remix_source'] = ['type' => $source_type, 'id' => $source_id];
        return $base;
    }

    private function from_history(int $user_id, string $id): array {
        $item = $this->history->get($user_id, $id);
        if (!$item) throw new Exception('History item not found.');
        return $this->build_payload($item);
    }

    private function from_gallery(int $user_id, string $id): array {
        foreach ($this->gallery->list($user_id) as $item) {
            if (($item['id'] ?? '') === $id) return $this->build_payload($item);
        }
        throw new Exception('Gallery item not found.');
    }

    private function build_payload(array $item): array {
        return [
            'prompt'         => $item['prompt'] ?? '',
            'provider'       => $item['provider'] ?? 'mock',
            'aspect_ratio'   => $item['aspect_ratio'] ?? '1:1',
            'resolution'     => $item['resolution'] ?? '1024',
            'style'          => $item['style'] ?? 'commercial',
            'lighting'       => $item['lighting'] ?? 'studio',
            'composition'    => $item['composition'] ?? 'center',
            'negative_prompt'=> $item['negative_prompt'] ?? '',
            'seed'           => $item['seed'] ?? -1,
            'quality'        => $item['quality'] ?? 'standard',
            'image_count'    => $item['image_count'] ?? 1,
            'korean_context' => true,
        ];
    }
}
