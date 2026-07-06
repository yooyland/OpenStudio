<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_Advanced {

    public function schema(): array {
        return [
            'stability'          => ['min' => 0, 'max' => 100, 'default' => 50, 'label' => 'Stability'],
            'similarity'         => ['min' => 0, 'max' => 100, 'default' => 75, 'label' => 'Similarity Boost'],
            'style_exaggeration' => ['min' => 0, 'max' => 100, 'default' => 0, 'label' => 'Style Exaggeration'],
            'speaker_boost'      => ['default' => true, 'label' => 'Speaker Boost'],
            'speed'              => ['min' => 0.5, 'max' => 2.0, 'default' => 1.0, 'step' => 0.05, 'label' => 'Speed'],
            'pitch'              => ['min' => -20, 'max' => 20, 'default' => 0, 'step' => 1, 'label' => 'Pitch (semitones)'],
        ];
    }

    public function apply(array $params): array {
        return [
            'stability'          => min(100, max(0, (int) ($params['stability'] ?? 50))),
            'similarity'         => min(100, max(0, (int) ($params['similarity'] ?? 75))),
            'style_exaggeration' => min(100, max(0, (int) ($params['style_exaggeration'] ?? 0))),
            'speaker_boost'      => !isset($params['speaker_boost']) || !empty($params['speaker_boost']),
            'speed'              => min(2.0, max(0.5, (float) ($params['speed'] ?? 1.0))),
            'pitch'              => min(20, max(-20, (float) ($params['pitch'] ?? 0))),
        ];
    }
}
