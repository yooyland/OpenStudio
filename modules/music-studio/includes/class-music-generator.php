<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Generator {

    private YooY_Music_API_Router $router;
    private YooY_Music_History $history;
    private YooY_Music_Gallery $gallery;
    private YooY_Music_Structure $structure;
    private YooY_Music_Credits $credits;

    public function __construct(
        YooY_Music_API_Router $router,
        YooY_Music_History $history,
        YooY_Music_Gallery $gallery,
        YooY_Music_Structure $structure,
        YooY_Music_Credits $credits
    ) {
        $this->router    = $router;
        $this->history   = $history;
        $this->gallery   = $gallery;
        $this->structure = $structure;
        $this->credits   = $credits;
    }

    public function generate(int $user_id, array $params): array {
        $payload = $this->normalize($params);

        if ($payload['mode'] === 'custom' && empty($payload['lyrics']) && !empty($payload['structure_template'])) {
            $payload['lyrics'] = $this->structure->build_lyrics_skeleton(
                $payload['structure_template'],
                $payload['language']
            );
        }

        if ($payload['mode'] === 'description' && empty($payload['style_prompt'])) {
            if (empty($payload['prompt'] ?? '')) {
                throw new Exception('Style prompt or lyrics required.');
            }
            $payload['style_prompt'] = $payload['prompt'];
        }

        if ($payload['mode'] === 'custom' && empty($payload['lyrics'])) {
            throw new Exception('Lyrics are required in custom mode.');
        }

        $payload['structure']    = $this->structure->parse_lyrics($payload['lyrics'] ?? '');
        $payload['style_prompt'] = $this->build_style_prompt($payload);
        $payload['prompt']       = $payload['mode'] === 'custom'
            ? ($payload['lyrics'] ?? '')
            : ($payload['style_prompt'] ?? $payload['prompt'] ?? '');

        $estimate = $this->credits->estimate($payload);
        if (!$this->credits->can_afford($user_id, $payload)) {
            throw new Exception('Insufficient credits. Required: ' . $estimate);
        }

        $result = $this->router->generate($payload);

        if (YooY_Job_Status::is_terminal($result['status'] ?? '')) {
            $credit_info = $this->credits->deduct(
                $user_id,
                (int) ($result['credits_used'] ?? $estimate),
                'Music: ' . ($result['title'] ?? 'Track')
            );
            $result['credits_used'] = $credit_info['deducted'] ?: (int) ($result['credits_used'] ?? $estimate);
            $result['credits'] = $credit_info;
        }

        $entry = $this->history->add($user_id, array_merge($result, [
            'type'            => 'music',
            'studio'          => 'music-studio',
            'mode'            => $payload['mode'],
            'lyrics'          => $payload['lyrics'],
            'style_prompt'    => $payload['style_prompt'],
            'genre'           => $payload['genre'],
            'mood'            => $payload['mood'],
            'tempo'           => $payload['tempo'],
            'instrument'      => $payload['instrument'],
            'vocal'           => $payload['vocal'],
            'language'        => $payload['language'],
            'negative_prompt' => $payload['negative_prompt'],
            'estimate'        => $estimate,
        ]));

        if (!empty($params['auto_save']) && ($entry['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $this->gallery->auto_save($user_id, $entry);
            $this->capture_gallery($user_id, $entry);
        }

        return $entry;
    }

    public function estimate(int $user_id, array $params): array {
        $payload = $this->normalize($params);
        $cost    = $this->credits->estimate($payload);
        return array_merge($this->credits->service()->snapshot($user_id), [
            'estimate'   => $cost,
            'can_afford' => $this->credits->can_afford($user_id, $payload),
        ]);
    }

    public function poll_and_finalize(int $user_id, string $provider, string $job_id): ?array {
        $status = $this->router->status($provider, $job_id);
        if (!YooY_Job_Status::is_terminal($status['status'] ?? '')) {
            $this->history->add($user_id, array_merge($status, ['studio' => 'music-studio', 'type' => 'music']));
            return $status;
        }

        $existing = $this->history->get($user_id, $job_id);
        if ($existing && !empty($existing['credits']['deducted'])) {
            return $this->history->add($user_id, $status);
        }

        $estimate = $this->credits->estimate($existing ?? $status);
        if (($status['status'] ?? '') === YooY_Job_Status::COMPLETED) {
            $credit_info = $this->credits->deduct(
                $user_id,
                (int) ($status['credits_used'] ?? $estimate),
                'Music: ' . ($status['title'] ?? 'Track')
            );
            $status['credits'] = $credit_info;
            $status['credits_used'] = $credit_info['deducted'] ?: (int) ($status['credits_used'] ?? $estimate);
            $this->gallery->auto_save($user_id, $status);
            $this->capture_gallery($user_id, $status);
        }

        return $this->history->add($user_id, array_merge($status, ['studio' => 'music-studio', 'type' => 'music']));
    }

    public function options(): array {
        $settings = new YooY_Music_Settings();
        return array_merge($settings->schema(), [
            'structures' => (new YooY_Music_Structure())->templates(),
            'modes'      => [
                ['id' => 'custom', 'label' => 'Custom (Lyrics + Style)'],
                ['id' => 'description', 'label' => 'Simple (Style Description)'],
            ],
        ]);
    }

    private function capture_gallery(int $user_id, array $entry): void {
        if (function_exists('yoy_gallery_capture')) {
            yoy_gallery_capture($user_id, $entry, 'music', 'music-studio');
        }
    }

    private function normalize(array $params): array {
        return [
            'provider'          => sanitize_text_field($params['provider'] ?? $params['default_provider'] ?? 'mock'),
            'model'             => sanitize_text_field($params['model'] ?? $params['default_model'] ?? 'mock-music-v1'),
            'mode'              => sanitize_text_field($params['mode'] ?? 'custom'),
            'title'             => sanitize_text_field($params['title'] ?? ''),
            'prompt'            => sanitize_textarea_field($params['prompt'] ?? ''),
            'lyrics'            => sanitize_textarea_field($params['lyrics'] ?? ''),
            'genre'             => sanitize_text_field($params['genre'] ?? 'k-pop'),
            'mood'              => sanitize_text_field($params['mood'] ?? 'upbeat'),
            'tempo'             => (int) ($params['tempo'] ?? 120),
            'instrument'        => sanitize_text_field($params['instrument'] ?? 'synth'),
            'vocal'             => sanitize_text_field($params['vocal'] ?? 'female'),
            'language'          => sanitize_text_field($params['language'] ?? 'ko'),
            'structure_template'=> sanitize_text_field($params['structure_template'] ?? 'kpop_hook'),
            'duration'          => min(240, max(30, (int) ($params['duration'] ?? 120))),
            'negative_prompt'   => sanitize_textarea_field($params['negative_prompt'] ?? ''),
            'reference_url'     => esc_url_raw($params['reference_url'] ?? ''),
            'reference_clip_id' => sanitize_text_field($params['reference_clip_id'] ?? ''),
            'weirdness'         => (int) ($params['weirdness'] ?? 50),
            'style_influence'   => (int) ($params['style_influence'] ?? 65),
            'audio_quality'     => sanitize_text_field($params['audio_quality'] ?? 'standard'),
            'korean_context'    => !empty($params['korean_context']),
            'auto_save'         => !isset($params['auto_save']) || !empty($params['auto_save']),
            'style_prompt'      => sanitize_textarea_field($params['style_prompt'] ?? ''),
            'async'             => array_key_exists('async', $params) ? !empty($params['async']) : true,
        ];
    }

    private function build_style_prompt(array $params): string {
        if (!empty($params['style_prompt'])) return $params['style_prompt'];

        $parts = [];
        if (!empty($params['korean_context']) && $params['language'] === 'ko') {
            $parts[] = 'Korean pop style';
        }
        $parts[] = $params['genre'];
        $parts[] = $params['mood'];
        $parts[] = $params['tempo'] . ' BPM';
        $parts[] = $params['instrument'];
        if ($params['vocal'] !== 'instrumental') {
            $parts[] = $params['vocal'] . ' vocal';
        } else {
            $parts[] = 'instrumental';
        }
        return implode(', ', array_filter($parts));
    }
}
