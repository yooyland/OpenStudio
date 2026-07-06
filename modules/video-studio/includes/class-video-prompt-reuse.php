<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Prompt_Reuse {

    private YooY_Video_History $history;
    private YooY_Video_Templates $templates;
    private YooY_Video_Gallery $gallery;

    public function __construct(YooY_Video_History $history, YooY_Video_Templates $templates, YooY_Video_Gallery $gallery) {
        $this->history   = $history;
        $this->templates = $templates;
        $this->gallery   = $gallery;
    }

    public function from_history(int $user_id, string $id): array {
        $item = $this->history->get($user_id, $id);
        if (!$item) {
            throw new Exception('History item not found.');
        }
        return $this->build_reuse_payload($item, 'history');
    }

    public function from_gallery(int $user_id, string $id): array {
        foreach ($this->gallery->list($user_id) as $item) {
            if (($item['id'] ?? '') === $id) {
                return $this->build_reuse_payload($item, 'gallery');
            }
        }
        throw new Exception('Gallery item not found.');
    }

    public function from_template(string $id): array {
        $applied = $this->templates->apply($id);
        return array_merge($applied, ['source' => 'template', 'source_id' => $id]);
    }

    public function remix(int $user_id, array $params): array {
        $source_type = sanitize_text_field($params['source_type'] ?? 'history');
        $source_id   = sanitize_text_field($params['source_id'] ?? '');
        $override    = sanitize_textarea_field($params['prompt_override'] ?? '');

        switch ($source_type) {
            case 'gallery':
                $base = $this->from_gallery($user_id, $source_id);
                break;
            case 'template':
                $base = $this->from_template($source_id);
                break;
            default:
                $base = $this->from_history($user_id, $source_id);
                break;
        }

        if ($override !== '') {
            $base['prompt'] = $override;
        }

        $base['remix'] = true;
        $base['remix_source'] = ['type' => $source_type, 'id' => $source_id];
        return $base;
    }

    private function build_reuse_payload(array $item, string $source): array {
        return [
            'source'         => $source,
            'source_id'      => $item['id'] ?? $item['job_id'] ?? '',
            'prompt'         => $item['prompt'] ?? '',
            'provider'       => $item['provider'] ?? 'mock',
            'model'          => $item['model'] ?? 'mock-v1',
            'aspect_ratio'   => $item['aspect_ratio'] ?? '16:9',
            'duration'       => $item['duration'] ?? 5,
            'style'          => $item['style'] ?? 'cinematic',
            'camera_motion'  => $item['camera_motion'] ?? 'static',
            'korean_context' => true,
        ];
    }
}
