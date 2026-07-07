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

        $result = $module->run_generate($user_id, [
            'prompt'   => $payload['prompt'] ?? '',
            'provider' => $payload['provider'] ?? 'auto',
            'auto_save'=> true,
        ]);

        return $this->finalize($user_id, 'image', 'image-studio', $result);
    }

    private function dispatch_video(int $user_id, array $payload): array {
        $module = $this->core->module('video-studio');
        if (!$module instanceof YooY_Module_Video_Studio) {
            throw new Exception('Video Studio module unavailable.');
        }

        $result = $module->run_generate($user_id, array_merge($payload, [
            'prompt'   => $payload['prompt'] ?? '',
            'provider' => $payload['provider'] ?? 'auto',
            'auto_save'=> true,
        ]));

        return $this->finalize($user_id, 'video', 'video-studio', $result);
    }

    private function dispatch_music(int $user_id, array $payload): array {
        $module = $this->core->module('music-studio');
        if (!$module instanceof YooY_Module_Music_Studio) {
            throw new Exception('Music Studio module unavailable.');
        }

        $result = $module->run_generate($user_id, array_merge($payload, [
            'provider'  => $payload['provider'] ?? 'mock',
            'auto_save' => true,
        ]));

        return $this->finalize($user_id, 'music', 'music-studio', $result);
    }

    private function finalize(int $user_id, string $type, string $studio, array $result): array {
        $normalized = YooY_Job_Normalizer::normalize($result, $type);
        $saved = $this->jobs->save($user_id, array_merge($normalized, ['studio' => $studio]), $studio);
        return $saved;
    }
}
