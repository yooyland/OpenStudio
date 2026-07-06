<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Prompt_Library extends YooY_Module_Base {

    public function id(): string { return 'prompt-library'; }
    public function name(): string { return 'Prompt Library'; }
    public function description(): string { return 'Saved prompts, templates, and Korean context presets.'; }
    public function version(): string { return '1.0.0'; }

    public function register_rest_routes(): void {
        $this->register_route('/prompts', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'list'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);

        $this->register_route('/presets', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'presets'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function list(): WP_REST_Response {
        $user_id = $this->current_user_id();
        $saved   = $user_id ? get_user_meta($user_id, 'yoy_saved_prompts', true) : [];
        $saved   = is_array($saved) ? $saved : [];

        return $this->success([
            'saved'    => $saved,
            'official' => $this->official_prompts(),
        ]);
    }

    public function save(WP_REST_Request $request): WP_REST_Response {
        $user_id = $this->current_user_id();
        $prompt  = sanitize_textarea_field($request->get_param('prompt') ?: '');
        $title   = sanitize_text_field($request->get_param('title') ?: '저장된 프롬프트');
        $type    = sanitize_text_field($request->get_param('type') ?: 'general');

        if ($prompt === '') {
            return $this->error('Prompt is required.');
        }

        $entry = [
            'id'         => 'prm_' . wp_generate_uuid4(),
            'title'      => $title,
            'prompt'     => $prompt,
            'type'       => $type,
            'created_at' => gmdate('c'),
        ];

        $saved   = get_user_meta($user_id, 'yoy_saved_prompts', true);
        $saved   = is_array($saved) ? $saved : [];
        $saved[] = $entry;
        update_user_meta($user_id, 'yoy_saved_prompts', $saved);

        return $this->success(['prompt' => $entry], 201);
    }

    public function presets(): WP_REST_Response {
        return $this->success(['presets' => $this->korean_presets()]);
    }

    private function official_prompts(): array {
        $stored = get_option('yoy_official_prompts', []);
        return is_array($stored) ? $stored : [];
    }

    private function korean_presets(): array {
        $stored = get_option('yoy_korean_presets', null);
        if (is_array($stored)) return $stored;

        $defaults = [
            ['id' => 'kr_ad', 'label' => '한국 광고', 'context' => '한국 TV/디지털 광고 톤, 짧은 카피, 임팩트 있는 오프닝'],
            ['id' => 'kr_youtube', 'label' => '한국 유튜브', 'context' => '유튜브 쇼츠/롱폼, 한국어 자막, 3초 훅'],
            ['id' => 'kr_shop', 'label' => '한국 쇼핑몰', 'context' => '스마트스토어/쿠팡 스타일, 제품 중심, 가격·혜택 강조'],
            ['id' => 'kr_sns', 'label' => '한국 SNS', 'context' => '인스타/틱톡 한국 트렌드, 세로 9:16, 빠른 전환'],
            ['id' => 'kr_drama', 'label' => '한국 드라마', 'context' => '감성적 조명, 일상·로맨스 톤, 한국 드라마 색감'],
        ];
        update_option('yoy_korean_presets', $defaults, false);
        return $defaults;
    }
}
