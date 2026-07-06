<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Settings {

    private const META_KEY = 'yoy_music_settings';

    public function get(int $user_id): array {
        $stored = get_user_meta($user_id, self::META_KEY, true);
        return is_array($stored) && !empty($stored) ? array_merge($this->defaults(), $stored) : $this->defaults();
    }

    public function update(int $user_id, array $data): array {
        $current = $this->get($user_id);
        foreach (array_keys($this->defaults()) as $key) {
            if (array_key_exists($key, $data)) {
                $current[$key] = $this->sanitize($key, $data[$key]);
            }
        }
        update_user_meta($user_id, self::META_KEY, $current);
        return $current;
    }

    public function schema(): array {
        return [
            'genres' => [
                ['id' => 'k-pop', 'label' => 'K-Pop'],
                ['id' => 'pop', 'label' => 'Pop'],
                ['id' => 'ballad', 'label' => '발라드'],
                ['id' => 'hip-hop', 'label' => 'Hip-Hop'],
                ['id' => 'rnb', 'label' => 'R&B'],
                ['id' => 'edm', 'label' => 'EDM'],
                ['id' => 'rock', 'label' => 'Rock'],
                ['id' => 'jazz', 'label' => 'Jazz'],
                ['id' => 'ost', 'label' => 'OST / 드라마'],
                ['id' => 'trot', 'label' => '트로트'],
                ['id' => 'indie', 'label' => '인디'],
                ['id' => 'lofi', 'label' => 'Lo-Fi'],
            ],
            'moods' => [
                ['id' => 'upbeat', 'label' => 'Upbeat'],
                ['id' => 'emotional', 'label' => 'Emotional'],
                ['id' => 'dreamy', 'label' => 'Dreamy'],
                ['id' => 'dark', 'label' => 'Dark'],
                ['id' => 'energetic', 'label' => 'Energetic'],
                ['id' => 'chill', 'label' => 'Chill'],
                ['id' => 'romantic', 'label' => 'Romantic'],
                ['id' => 'epic', 'label' => 'Epic'],
            ],
            'tempos' => [
                ['id' => 60, 'label' => '60 BPM (Slow)'],
                ['id' => 90, 'label' => '90 BPM (Ballad)'],
                ['id' => 120, 'label' => '120 BPM (Pop)'],
                ['id' => 128, 'label' => '128 BPM (Dance)'],
                ['id' => 140, 'label' => '140 BPM (Fast)'],
            ],
            'instruments' => [
                ['id' => 'piano', 'label' => 'Piano'],
                ['id' => 'guitar', 'label' => 'Guitar'],
                ['id' => 'synth', 'label' => 'Synth'],
                ['id' => 'strings', 'label' => 'Strings'],
                ['id' => 'drums', 'label' => 'Drums'],
                ['id' => 'bass', 'label' => 'Bass'],
                ['id' => 'orchestral', 'label' => 'Orchestral'],
                ['id' => 'acoustic', 'label' => 'Acoustic'],
            ],
            'vocals' => [
                ['id' => 'female', 'label' => 'Female Vocal'],
                ['id' => 'male', 'label' => 'Male Vocal'],
                ['id' => 'duet', 'label' => 'Duet'],
                ['id' => 'choir', 'label' => 'Choir'],
                ['id' => 'instrumental', 'label' => 'Instrumental'],
            ],
            'languages' => [
                ['id' => 'ko', 'label' => '한국어'],
                ['id' => 'en', 'label' => 'English'],
                ['id' => 'ja', 'label' => '日本語'],
                ['id' => 'zh', 'label' => '中文'],
                ['id' => 'instrumental', 'label' => 'Instrumental'],
            ],
            'durations' => [30, 60, 120, 180, 240],
            'advanced' => [
                'weirdness'       => ['min' => 0, 'max' => 100, 'default' => 50, 'label' => 'Weirdness'],
                'style_influence' => ['min' => 0, 'max' => 100, 'default' => 65, 'label' => 'Style Influence'],
                'audio_quality'   => ['options' => ['standard', 'high'], 'default' => 'standard'],
                'exclude_styles'  => ['label' => 'Exclude Styles'],
            ],
        ];
    }

    private function defaults(): array {
        return [
            'default_provider'  => 'mock',
            'default_model'     => 'mock-music-v1',
            'mode'              => 'custom',
            'title'             => '',
            'genre'             => 'k-pop',
            'mood'              => 'upbeat',
            'tempo'             => 120,
            'instrument'        => 'synth',
            'vocal'             => 'female',
            'language'          => 'ko',
            'structure_template'=> 'kpop_hook',
            'duration'          => 120,
            'negative_prompt'   => 'low quality, distorted, noise, off-key',
            'weirdness'         => 50,
            'style_influence'   => 65,
            'audio_quality'     => 'standard',
            'korean_context'    => true,
            'auto_save'         => true,
        ];
    }

    private function sanitize(string $key, $value) {
        return match ($key) {
            'tempo', 'duration', 'weirdness', 'style_influence' => (int) $value,
            'korean_context', 'auto_save' => (bool) $value,
            'lyrics', 'negative_prompt', 'style_prompt' => sanitize_textarea_field((string) $value),
            default => sanitize_text_field((string) $value),
        };
    }
}
