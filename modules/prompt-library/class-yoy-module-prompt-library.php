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
            ['id' => 'kr_shop', 'label' => '쇼핑몰', 'studio' => 'image', 'context' => '스마트스토어·쿠팡 스타일, 제품 중심, 깔끔한 화이트 배경, 상업용 고품질'],
            ['id' => 'kr_smartstore', 'label' => '스마트스토어', 'studio' => 'image', 'context' => '네이버 스마트스토어 썸네일, 제품 클로즈업, 혜택 강조, 한국 이커머스'],
            ['id' => 'kr_coupang', 'label' => '쿠팡', 'studio' => 'image', 'context' => '쿠팡 상품 상세, 밝은 조명, 제품 정보 강조, 신뢰감 있는 상업 사진'],
            ['id' => 'kr_youtube', 'label' => '유튜브 썸네일', 'studio' => 'image', 'context' => '유튜브 썸네일, 강한 대비, 3초 훅, 한국어 타이포 느낌, 클릭 유도'],
            ['id' => 'kr_blog', 'label' => '블로그', 'studio' => 'writing', 'context' => '한국 블로그 톤, 친근하고 정보성 있는 글, SEO 친화'],
            ['id' => 'kr_sns', 'label' => 'SNS', 'studio' => 'image', 'context' => '인스타·틱톡 한국 트렌드, 세로 9:16, 감성적 색감, 빠른 전환'],
            ['id' => 'kr_poster', 'label' => '포스터', 'studio' => 'image', 'context' => '영화·공연 포스터, 시네마틱, 강렬한 타이포 비주얼'],
            ['id' => 'kr_cardnews', 'label' => '카드뉴스', 'studio' => 'image', 'context' => '카드뉴스 슬라이드, 정돈된 레이아웃, 읽기 쉬운 구성'],
            ['id' => 'kr_ad', 'label' => '광고', 'studio' => 'image', 'context' => '한국 TV·디지털 광고 톤, 프리미엄, 짧은 카피, 임팩트'],
            ['id' => 'kr_product_page', 'label' => '제품 상세페이지', 'studio' => 'image', 'context' => '상세페이지 비주얼, 제품 특징 강조, 라이프스타일 컷'],
            ['id' => 'kr_banner', 'label' => '배너', 'studio' => 'image', 'context' => '웹·앱 배너, 16:9 또는 와이드, 브랜드 메시지'],
            ['id' => 'kr_logo', 'label' => '로고', 'studio' => 'image', 'context' => '미니멀 로고, 브랜드 아이덴티티, 벡터 느낌'],
            ['id' => 'kr_character', 'label' => '캐릭터', 'studio' => 'image', 'context' => '한국 감성 캐릭터, 친근하고 귀여운 스타일'],
            ['id' => 'kr_webtoon', 'label' => '웹툰', 'studio' => 'image', 'context' => '한국 웹툰 스타일, 선명한 라인, 감정 표현'],
            ['id' => 'kr_game', 'label' => '게임', 'studio' => 'image', 'context' => '게임 아트, 고품질 일러스트, 판타지 또는 SF'],
            ['id' => 'kr_movie', 'label' => '영화', 'studio' => 'video', 'context' => '영화 시네마틱, 드라마틱 조명, 한국 영화 색감'],
            ['id' => 'kr_mv', 'label' => '뮤직비디오', 'studio' => 'video', 'context' => 'K-POP 뮤직비디오 느낌, 화려한 조명, 역동적 구도'],
            ['id' => 'kr_drama', 'label' => 'K-Drama', 'studio' => 'image', 'context' => '감성적 조명, 로맨스·일상 톤, 한국 드라마 색감'],
            ['id' => 'kr_kpop', 'label' => 'K-POP', 'studio' => 'image', 'context' => 'K-POP 아이돌 비주얼, 화려하고 트렌디, 프리미엄'],
            ['id' => 'kr_kfood', 'label' => 'K-Food', 'studio' => 'image', 'context' => '한국 음식 사진, 따뜻한 색감, 식욕 자극'],
        ];
        update_option('yoy_korean_presets', $defaults, false);
        return $defaults;
    }
}
