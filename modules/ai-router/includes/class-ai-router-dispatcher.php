<?php
if (!defined('ABSPATH')) exit;

final class YooY_AI_Router_Dispatcher {

    private YooY_Core_Engine $core;
    private YooY_Job_Store $jobs;
    private YooY_Credits_Service $credits;

    private array $studio_map = [
        'image'   => 'image-studio',
        'video'   => 'video-studio',
        'music'   => 'music-studio',
        'voice'   => 'voice-studio',
        'avatar'  => 'avatar-studio',
        'writing' => 'writing-studio',
    ];

    public function __construct(YooY_Core_Engine $core) {
        $this->core    = $core;
        $this->jobs    = new YooY_Job_Store();
        $this->credits = new YooY_Credits_Service();
    }

    public function dispatch(int $user_id, array $payload): array {
        $type = sanitize_text_field($payload['type'] ?? 'image');
        $studio_id = $this->studio_map[$type] ?? null;

        if ($type === 'image' && $this->core->module('image-studio') instanceof YooY_Module_Image_Studio) {
            return $this->dispatch_image($user_id, $payload);
        }

        if ($type === 'video' && $this->core->module('video-studio') instanceof YooY_Module_Video_Studio) {
            return $this->dispatch_video($user_id, $payload);
        }

        if ($type === 'music' && $this->core->module('music-studio') instanceof YooY_Module_Music_Studio) {
            return $this->dispatch_music($user_id, $payload);
        }

        if ($type === 'writing') {
            return $this->dispatch_writing($user_id, $payload);
        }

        $result = apply_filters('yoy_ai_studio_generate', null, array_merge($payload, [
            'user_id' => $user_id,
            'type'    => $type,
        ]));

        if ($result !== null) {
            return $this->finalize($user_id, $type, $studio_id ?? ($type . '-studio'), $result);
        }

        throw new Exception('No provider route available for type: ' . $type);
    }

    public function status(int $user_id, string $type, string $provider, string $job_id): array {
        if ($type === 'image') {
            $module = $this->core->module('image-studio');
            if ($module instanceof YooY_Module_Image_Studio) {
                return $module->poll_provider_job($user_id, $provider, $job_id);
            }
        }

        if ($type === 'video') {
            $module = $this->core->module('video-studio');
            if ($module instanceof YooY_Module_Video_Studio) {
                return $module->poll_provider_job($user_id, $provider, $job_id);
            }
        }

        if ($type === 'music') {
            $module = $this->core->module('music-studio');
            if ($module instanceof YooY_Module_Music_Studio) {
                return $module->poll_provider_job($user_id, $provider, $job_id);
            }
        }

        $result = apply_filters('yoy_ai_studio_job_status', null, [
            'user_id'  => $user_id,
            'type'     => $type,
            'provider' => $provider,
            'job_id'   => $job_id,
        ]);

        if ($result !== null) {
            return YooY_Job_Normalizer::normalize($result, $type);
        }

        $stored = $this->jobs->get($user_id, $job_id);
        if ($stored) return $stored;

        return YooY_Job_Normalizer::normalize([
            'job_id' => $job_id,
            'status' => YooY_Job_Status::FAILED,
            'error'  => 'Job not found.',
        ], $type);
    }

    private function dispatch_image(int $user_id, array $payload): array {
        $module = $this->core->module('image-studio');
        if (!$module instanceof YooY_Module_Image_Studio) {
            throw new Exception('Image Studio module unavailable.');
        }

        $result = $module->run_generate($user_id, array_merge($payload, [
            'prompt'     => $payload['prompt'] ?? '',
            'provider'   => $payload['provider'] ?? 'auto',
            'auto_save'  => true,
            'project_id' => sanitize_text_field((string) ($payload['project_id'] ?? '')),
        ]));

        return $this->finalize($user_id, 'image', 'image-studio', $result);
    }

    private function dispatch_writing(int $user_id, array $payload): array {
        $prompt = sanitize_textarea_field((string) ($payload['prompt'] ?? ''));
        $job_id = sanitize_text_field((string) ($payload['job_id'] ?? ('job_' . wp_generate_uuid4())));
        $project_id = sanitize_text_field((string) ($payload['project_id'] ?? ''));
        $purpose = sanitize_text_field((string) ($payload['purpose'] ?? 'free'));
        $tone = sanitize_text_field((string) ($payload['tone'] ?? 'friendly'));
        $length = sanitize_text_field((string) ($payload['length'] ?? 'medium'));

        $cost = 5;
        if (!$this->credits->can_afford($user_id, $cost)) {
            throw new Exception('크레딧이 부족합니다. Credits에서 충전 후 다시 시도해 주세요.');
        }

        $body = $this->compose_writing_draft($prompt, $purpose, $tone, $length);
        $result = [
            'job_id'       => $job_id,
            'status'       => YooY_Job_Status::COMPLETED,
            'type'         => 'writing',
            'studio'       => 'writing-studio',
            'provider'     => 'mock',
            'model'        => 'writing-draft-1',
            'prompt'       => $prompt,
            'output'       => $body,
            'text'         => $body,
            'content'      => $body,
            'credits_used' => $cost,
            'created_at'   => gmdate('c'),
            'project_id'   => $project_id,
        ];

        if (class_exists('YooY_Credits_Service')) {
            $label = function_exists('mb_substr')
                ? mb_substr($prompt, 0, 40)
                : substr($prompt, 0, 40);
            $credit_info = $this->credits->deduct($user_id, $cost, 'Writing: ' . $label, 'writing-studio');
            $result['credits_used'] = (int) ($credit_info['deducted'] ?? $cost);
            $result['credits'] = $credit_info;
        }

        if (!class_exists('YooY_Gallery_Store') && defined('YOY_AI_STUDIO_MODULES_DIR')) {
            $gpath = YOY_AI_STUDIO_MODULES_DIR . 'gallery/includes/class-gallery-store.php';
            if (file_exists($gpath)) {
                require_once $gpath;
            }
        }
        if (class_exists('YooY_Gallery_Store')) {
            $gallery = new YooY_Gallery_Store();
            $title = function_exists('mb_substr')
                ? mb_substr(wp_strip_all_tags($prompt), 0, 48)
                : substr(wp_strip_all_tags($prompt), 0, 48);
            $saved = $gallery->save($user_id, [
                'id'           => $job_id,
                'type'         => 'writing',
                'studio'       => 'writing-studio',
                'title'        => $title,
                'prompt'       => $prompt,
                'user_prompt'  => $prompt,
                'provider'     => 'mock',
                'model'        => 'writing-draft-1',
                'credits_used' => (int) ($result['credits_used'] ?? $cost),
                'project_id'   => $project_id,
                'created_at'   => gmdate('c'),
                'meta'         => [
                    'content'    => $body,
                    'body'       => $body,
                    'purpose'    => $purpose,
                    'tone'       => $tone,
                    'length'     => $length,
                    'project_id' => $project_id,
                ],
            ]);
            $result['gallery_id'] = $saved['id'] ?? $job_id;
            $result['gallery_item_id'] = $saved['id'] ?? $job_id;
        }

        return $this->finalize($user_id, 'writing', 'writing-studio', $result);
    }

    private function compose_writing_draft(string $prompt, string $purpose, string $tone, string $length): string {
        $lines = [];
        $lines[] = '[초안] ' . ($purpose !== '' ? $purpose : 'writing') . ' · ' . $tone . ' · ' . $length;
        $lines[] = '';
        $lines[] = trim($prompt);
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '요청하신 주제를 바탕으로 초안을 정리했습니다. Studio에서 톤과 길이를 조정해 이어서 다듬을 수 있습니다.';
        return implode("\n", $lines);
    }

    private function dispatch_video(int $user_id, array $payload): array {
        $module = $this->core->module('video-studio');
        if (!$module instanceof YooY_Module_Video_Studio) {
            throw new Exception('Video Studio module unavailable.');
        }

        $result = $module->run_generate($user_id, array_merge($payload, [
            'prompt'     => $payload['prompt'] ?? '',
            'provider'   => $payload['provider'] ?? 'auto',
            'auto_save'  => true,
            'project_id' => sanitize_text_field((string) ($payload['project_id'] ?? '')),
        ]));

        return $this->finalize($user_id, 'video', 'video-studio', $result);
    }

    private function dispatch_music(int $user_id, array $payload): array {
        $module = $this->core->module('music-studio');
        if (!$module instanceof YooY_Module_Music_Studio) {
            throw new Exception('Music Studio module unavailable.');
        }

        $result = $module->run_generate($user_id, array_merge($payload, [
            'provider'   => $payload['provider'] ?? 'mock',
            'auto_save'  => true,
            'project_id' => sanitize_text_field((string) ($payload['project_id'] ?? '')),
        ]));

        return $this->finalize($user_id, 'music', 'music-studio', $result);
    }

    private function finalize(int $user_id, string $type, string $studio, array $result): array {
        $normalized = YooY_Job_Normalizer::normalize($result, $type);
        $saved = $this->jobs->save($user_id, array_merge($normalized, ['studio' => $studio]), $studio);
        return $saved;
    }
}
