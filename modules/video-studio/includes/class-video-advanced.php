<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Advanced {

    public function options(): array {
        return [
            'seed'            => ['type' => 'number', 'label' => 'Seed', 'default' => -1],
            'guidance_scale'  => ['type' => 'range', 'label' => 'Guidance Scale', 'min' => 1, 'max' => 20, 'default' => 7.5],
            'motion_strength' => ['type' => 'range', 'label' => 'Motion Strength', 'min' => 0, 'max' => 100, 'default' => 50],
            'consistency'     => ['type' => 'range', 'label' => 'Frame Consistency', 'min' => 0, 'max' => 100, 'default' => 75],
            'interpolation'   => ['type' => 'select', 'label' => 'Interpolation', 'options' => ['linear', 'smooth', 'cinematic'], 'default' => 'smooth'],
            'upscale'         => ['type' => 'toggle', 'label' => 'AI Upscale', 'default' => false],
            'watermark'       => ['type' => 'toggle', 'label' => 'YooY Watermark', 'default' => true],
            'subtitle_track'  => ['type' => 'toggle', 'label' => 'Subtitle Track', 'default' => true],
            'audio_sync'      => ['type' => 'toggle', 'label' => 'Audio Sync', 'default' => false],
        ];
    }

    public function apply(array $params): array {
        return [
            'seed'            => (int) ($params['seed'] ?? -1),
            'guidance_scale'  => (float) ($params['guidance_scale'] ?? 7.5),
            'motion_strength' => (int) ($params['motion_strength'] ?? 50),
            'consistency'     => (int) ($params['consistency'] ?? 75),
            'interpolation'   => sanitize_text_field($params['interpolation'] ?? 'smooth'),
            'upscale'         => !empty($params['upscale']),
            'watermark'       => !isset($params['watermark']) || !empty($params['watermark']),
            'subtitle_track'  => !isset($params['subtitle_track']) || !empty($params['subtitle_track']),
            'audio_sync'      => !empty($params['audio_sync']),
            'negative_prompt' => sanitize_textarea_field($params['negative_prompt'] ?? 'blurry, low quality, distorted'),
        ];
    }

    public function presets(): array {
        return [
            ['id' => 'fast', 'label' => '빠른 생성', 'values' => ['quality' => 'draft', 'motion_strength' => 30, 'upscale' => false]],
            ['id' => 'quality', 'label' => '고품질', 'values' => ['quality' => 'pro', 'motion_strength' => 50, 'upscale' => true, 'consistency' => 90]],
            ['id' => 'social', 'label' => 'SNS 최적화', 'values' => ['aspect_ratio' => '9:16', 'duration' => 10, 'subtitle_track' => true]],
            ['id' => 'cinematic', 'label' => '시네마틱', 'values' => ['style' => 'cinematic', 'interpolation' => 'cinematic', 'fps' => 24, 'camera_motion' => 'dolly_in']],
        ];
    }
}
