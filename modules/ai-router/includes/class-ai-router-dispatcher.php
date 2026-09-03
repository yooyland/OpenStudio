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
        $requested_provider = sanitize_text_field((string) ($payload['provider'] ?? 'auto'));
        if ($requested_provider === '') {
            $requested_provider = 'auto';
        }

        // Existing product convention (estimate endpoint): writing => 5
        $cost = 5;
        if (!$this->credits->can_afford($user_id, $cost)) {
            throw new Exception('크레딧이 부족합니다. Credits에서 충전 후 다시 시도해 주세요.');
        }

        if ($prompt === '') {
            throw new Exception('무엇을 작성할지 입력해 주세요.');
        }

        $this->ensure_openai_chat_helper();

        $ref_context = $this->extract_writing_reference_context($payload);
        $generation = $this->generate_writing_content(
            $prompt,
            $purpose,
            $tone,
            $length,
            $ref_context,
            $requested_provider
        );

        $body = (string) ($generation['content'] ?? '');
        if (trim($body) === '') {
            throw new Exception('글을 생성하지 못했습니다. 다시 시도해 주세요.');
        }

        $provider_used = sanitize_text_field((string) ($generation['provider'] ?? 'openai'));
        $model_used = sanitize_text_field((string) ($generation['model'] ?? 'gpt-4o-mini'));

        // Charge once after successful generation (matches Image Studio pattern).
        $credit_info = ['deducted' => 0];
        if (class_exists('YooY_Credits_Service')) {
            $label = function_exists('mb_substr')
                ? mb_substr($prompt, 0, 40)
                : substr($prompt, 0, 40);
            $credit_info = $this->credits->deduct(
                $user_id,
                $cost,
                'Writing: ' . $label,
                'writing-studio',
                [
                    'studio'   => 'writing-studio',
                    'provider' => $provider_used,
                    'status'   => 'completed',
                ]
            );
        }

        $result = [
            'job_id'             => $job_id,
            'status'             => YooY_Job_Status::COMPLETED,
            'type'               => 'writing',
            'studio'             => 'writing-studio',
            'provider'           => $provider_used,
            'provider_used'      => $provider_used,
            'requested_provider' => $requested_provider,
            'model'              => $model_used,
            'prompt'             => $prompt,
            'output'             => [
                'text'    => $body,
                'content' => $body,
                'mime'    => 'text/plain',
            ],
            'text'               => $body,
            'content'            => $body,
            'credits_used'       => (int) ($credit_info['deducted'] ?? $cost),
            'credits'            => $credit_info,
            'created_at'         => gmdate('c'),
            'project_id'         => $project_id,
            'meta'               => [
                'content'    => $body,
                'body'       => $body,
                'purpose'    => $purpose,
                'tone'       => $tone,
                'length'     => $length,
                'project_id' => $project_id,
                'request_id' => (string) ($generation['request_id'] ?? ''),
            ],
        ];

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
                'title'        => $title !== '' ? $title : 'Writing',
                'prompt'       => $prompt,
                'user_prompt'  => $prompt,
                'provider'     => $provider_used,
                'model'        => $model_used,
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

    /**
     * Resolve Writing text via existing OpenAI chat path (same key as Translator/Import).
     * Mock draft only when explicit provider=mock AND debug flags allow it.
     *
     * @return array{content:string,provider:string,model:string,request_id?:string}
     */
    private function generate_writing_content(
        string $prompt,
        string $purpose,
        string $tone,
        string $length,
        string $ref_context,
        string $requested_provider
    ): array {
        $want_mock = ($requested_provider === 'mock');
        $allow_mock = class_exists('YooY_OpenAI_Chat')
            ? YooY_OpenAI_Chat::allow_mock_fallback()
            : false;

        if ($want_mock && $allow_mock) {
            return [
                'content'  => $this->compose_writing_draft($prompt, $purpose, $tone, $length),
                'provider' => 'mock',
                'model'    => 'writing-draft-1',
            ];
        }

        if (!class_exists('YooY_OpenAI_Chat') || !YooY_OpenAI_Chat::is_configured()) {
            if (class_exists('YooY_System_Log')) {
                YooY_System_Log::write('error', 'Writing provider unavailable: OpenAI key missing', [
                    'provider' => 'openai',
                    'studio'   => 'writing-studio',
                ]);
            }
            throw new Exception('글을 생성하지 못했습니다. 다시 시도해 주세요.');
        }

        try {
            $messages = [
                [
                    'role'    => 'system',
                    'content' => $this->build_writing_system_prompt($purpose, $tone, $length),
                ],
                [
                    'role'    => 'user',
                    'content' => $this->build_writing_user_prompt($prompt, $ref_context),
                ],
            ];
            $chat = YooY_OpenAI_Chat::complete($messages, [
                'model'       => 'gpt-4o-mini',
                'temperature' => 0.7,
                'timeout'     => 90,
            ]);
            return [
                'content'    => (string) ($chat['content'] ?? ''),
                'provider'   => 'openai',
                'model'      => (string) ($chat['model'] ?? 'gpt-4o-mini'),
                'request_id' => (string) ($chat['request_id'] ?? ''),
            ];
        } catch (Exception $e) {
            if (class_exists('YooY_System_Log')) {
                YooY_System_Log::write('error', 'Writing generation failed', [
                    'provider' => 'openai',
                    'studio'   => 'writing-studio',
                    'code'     => $e->getMessage(),
                ]);
            }
            // Production: never silently return mock draft to users.
            throw new Exception('글을 생성하지 못했습니다. 다시 시도해 주세요.');
        }
    }

    private function ensure_openai_chat_helper(): void {
        if (class_exists('YooY_OpenAI_Chat')) {
            return;
        }
        $path = '';
        if (defined('YOY_AI_STUDIO_PROVIDERS_DIR')) {
            $path = YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-openai-chat.php';
        }
        if ($path !== '' && file_exists($path)) {
            require_once $path;
        }
    }

    private function build_writing_system_prompt(string $purpose, string $tone, string $length): string {
        $purpose_map = [
            'blog'    => '블로그 글',
            'product' => '제품 소개문',
            'ad'      => '광고 카피',
            'company' => '회사 소개문',
            'sns'     => 'SNS 게시글',
            'press'   => '보도자료 초안',
            'free'    => '자유 형식 글',
        ];
        $tone_map = [
            'friendly'     => '친근하고 따뜻한',
            'professional' => '전문적이고 신뢰감 있는',
            'persuasive'   => '설득력 있는',
            'concise'      => '간결하고 명확한',
            'emotional'    => '감성적인',
        ];
        $length_map = [
            'short'  => '짧게 (핵심만, 대략 150~300자)',
            'medium' => '보통 길이 (대략 400~800자)',
            'long'   => '길게 (상세하게, 대략 900~1500자)',
        ];

        $purpose_label = $purpose_map[$purpose] ?? $purpose_map['free'];
        $tone_label = $tone_map[$tone] ?? $tone_map['friendly'];
        $length_label = $length_map[$length] ?? $length_map['medium'];

        return "당신은 YooY AI Studio의 한국어 카피라이터입니다.\n"
            . "글 종류: {$purpose_label}\n"
            . "톤: {$tone_label}\n"
            . "길이: {$length_label}\n"
            . "규칙:\n"
            . "- 요청에 맞는 완성된 본문만 작성합니다.\n"
            . "- 메타 설명, 프롬프트 재진술, '여기 초안입니다' 같은 서두는 쓰지 않습니다.\n"
            . "- 마크다운 제목이 자연스러우면 사용하되 과도한 장식은 피합니다.\n"
            . "- 참고 자료가 있으면 사실에 맞게 활용하고 없는 내용은 지어내지 않습니다.";
    }

    private function build_writing_user_prompt(string $prompt, string $ref_context): string {
        $parts = [];
        $parts[] = "요청:\n" . trim($prompt);
        if ($ref_context !== '') {
            $parts[] = "참고 자료:\n" . $ref_context;
        }
        return implode("\n\n", $parts);
    }

    private function extract_writing_reference_context(array $payload): string {
        $chunks = [];
        $assets = [];
        if (!empty($payload['reference_assets']) && is_array($payload['reference_assets'])) {
            $assets = $payload['reference_assets'];
        }
        foreach ($assets as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $title = sanitize_text_field((string) ($asset['title'] ?? $asset['name'] ?? ''));
            $excerpt = sanitize_textarea_field((string) (
                $asset['excerpt']
                ?? $asset['content']
                ?? $asset['text']
                ?? $asset['normalized_content']
                ?? ''
            ));
            if ($excerpt === '' && !empty($asset['url'])) {
                $excerpt = 'URL: ' . esc_url_raw((string) $asset['url']);
            }
            if ($excerpt === '') {
                continue;
            }
            if (function_exists('mb_substr')) {
                $excerpt = mb_substr($excerpt, 0, 4000);
            } else {
                $excerpt = substr($excerpt, 0, 4000);
            }
            $chunks[] = ($title !== '' ? '[' . $title . "]\n" : '') . $excerpt;
        }

        if (!empty($payload['reference_url']) && empty($chunks)) {
            $chunks[] = 'URL: ' . esc_url_raw((string) $payload['reference_url']);
        }

        $joined = implode("\n\n---\n\n", $chunks);
        if (function_exists('mb_substr')) {
            return mb_substr($joined, 0, 8000);
        }
        return substr($joined, 0, 8000);
    }

    /**
     * Dev/test-only draft (never used for normal production users).
     */
    private function compose_writing_draft(string $prompt, string $purpose, string $tone, string $length): string {
        $lines = [];
        $lines[] = '[DEV MOCK DRAFT] ' . ($purpose !== '' ? $purpose : 'writing') . ' · ' . $tone . ' · ' . $length;
        $lines[] = '';
        $lines[] = trim($prompt);
        $lines[] = '';
        $lines[] = '---';
        $lines[] = 'This mock draft is only available when WP_DEBUG/YOOY_DEBUG is on and provider=mock.';
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

        if ($type === 'writing') {
            $text = (string) ($result['text'] ?? $result['content'] ?? '');
            if ($text === '' && !empty($result['output']) && is_array($result['output'])) {
                $text = (string) ($result['output']['text'] ?? $result['output']['content'] ?? '');
            }
            if ($text !== '') {
                $normalized['text'] = $text;
                $normalized['content'] = $text;
                $out = is_array($normalized['output'] ?? null) ? $normalized['output'] : [];
                $out['text'] = $text;
                $out['content'] = $text;
                if (empty($out['mime'])) {
                    $out['mime'] = 'text/plain';
                }
                $normalized['output'] = $out;
            }
            if (!empty($result['gallery_id'])) {
                $normalized['gallery_id'] = (string) $result['gallery_id'];
                $normalized['gallery_item_id'] = (string) ($result['gallery_item_id'] ?? $result['gallery_id']);
            }
            if (!empty($result['project_id'])) {
                $normalized['project_id'] = (string) $result['project_id'];
            }
            if (!empty($result['credits']) && is_array($result['credits'])) {
                $normalized['credits'] = $result['credits'];
            }
        }

        $saved = $this->jobs->save($user_id, array_merge($normalized, ['studio' => $studio]), $studio);
        return $saved;
    }
}
