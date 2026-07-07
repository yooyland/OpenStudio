<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Templates {

    public function list(array $filters = []): array {
        $templates = $this->catalog();
        $category  = sanitize_text_field($filters['category'] ?? '');

        if ($category !== '') {
            $templates = array_values(array_filter($templates, fn($t) => ($t['category'] ?? '') === $category));
        }

        return $templates;
    }

    public function get(string $id): ?array {
        foreach ($this->catalog() as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }
        return null;
    }

    public function apply(string $id): array {
        $template = $this->get($id);
        if (!$template) {
            throw new Exception('Template not found.');
        }

        return [
            'template_id'   => $template['id'],
            'prompt'        => $template['prompt'],
            'aspect_ratio'  => $template['aspect_ratio'],
            'duration'      => $template['duration'],
            'style'         => $template['style'],
            'camera_motion' => $template['camera_motion'],
            'provider'      => $template['recommended_provider'],
            'korean_context'=> true,
            'guide'         => $template['guide'],
            'storyboard'    => $template['storyboard'] ?? [],
        ];
    }

    public function categories(): array {
        return [
            ['id' => 'advertising', 'label' => '한국 광고'],
            ['id' => 'ecommerce', 'label' => '쇼핑몰'],
            ['id' => 'youtube', 'label' => '유튜브'],
            ['id' => 'sns', 'label' => 'SNS'],
            ['id' => 'drama', 'label' => '드라마/영화'],
            ['id' => 'corporate', 'label' => '기업'],
        ];
    }

    private function catalog(): array {
        return [
            [
                'id' => 'tpl_kbeauty_15s', 'title' => 'K-Beauty 15초 TVC', 'category' => 'advertising',
                'prompt' => '프리미엄 한국 화장품 브랜드 15초 광고. 골든 아워 조명, 모델 클로즈업, 제품 텍스처 강조, 한국어 자막 공간 확보.',
                'aspect_ratio' => '16:9', 'duration' => 15, 'style' => 'commercial', 'camera_motion' => 'dolly_in',
                'recommended_provider' => 'runway', 'tags' => ['K-Beauty', '광고', '15초'],
                'guide' => '제품명과 핵심 카피를 CTA 씬에 배치하세요.',
                'storyboard' => [
                    ['label' => 'Hook', 'duration' => 3, 'prompt' => '모델 눈 클로즈업, 빛 반사'],
                    ['label' => 'Product', 'duration' => 7, 'prompt' => '제품 텍스처, 손에 바르는 장면'],
                    ['label' => 'CTA', 'duration' => 5, 'prompt' => '브랜드 로고, 슬로건 자막'],
                ],
            ],
            [
                'id' => 'tpl_smartstore', 'title' => '스마트스토어 제품 영상', 'category' => 'ecommerce',
                'prompt' => '스마트스토어 제품 소개 영상. 흰 배경, 360도 회전, 가격/혜택 뱃지 공간, 한국 이커머스 스타일.',
                'aspect_ratio' => '1:1', 'duration' => 10, 'style' => 'commercial', 'camera_motion' => 'orbit',
                'recommended_provider' => 'runway', 'tags' => ['스마트스토어', '제품', '이커머스'],
                'guide' => 'Runway 상업용 템플릿에 최적화되어 있습니다.',
            ],
            [
                'id' => 'tpl_yt_shorts', 'title' => '유튜브 쇼츠 3초 훅', 'category' => 'youtube',
                'prompt' => '한국 유튜브 쇼츠 3초 임팩트 오프닝. 빠른 전환, 세로 9:16, 자막 상단/하단 공간.',
                'aspect_ratio' => '9:16', 'duration' => 15, 'style' => 'cinematic', 'camera_motion' => 'zoom_in',
                'recommended_provider' => 'runway', 'tags' => ['유튜브', '쇼츠', '훅'],
                'guide' => '첫 3초에 시선을 끄는 요소를 넣으세요.',
            ],
            [
                'id' => 'tpl_instagram_reels', 'title' => '인스타 릴스 카페', 'category' => 'sns',
                'prompt' => '한국 감성 카페 릴스. 따뜻한 톤, 라떼아트 클로즈업, BGM 리듬감, 세로 9:16.',
                'aspect_ratio' => '9:16', 'duration' => 10, 'style' => 'documentary', 'camera_motion' => 'pan_right',
                'recommended_provider' => 'google-veo', 'tags' => ['인스타', '릴스', '카페'],
                'guide' => '트렌디한 한국 SNS 감성에 맞춘 템플릿.',
            ],
            [
                'id' => 'tpl_kdrama_trailer', 'title' => 'K-드라마 예고편', 'category' => 'drama',
                'prompt' => '한국 드라마 예고편 스타일. 감성 조명, 슬로모션, 피아노+현악 BGM 분위기, 감정 클로즈업.',
                'aspect_ratio' => '16:9', 'duration' => 30, 'style' => 'k-drama', 'camera_motion' => 'dolly_in',
                'recommended_provider' => 'runway', 'tags' => ['드라마', '예고편', '감성'],
                'guide' => '감정 전환 포인트에 슬로모션을 활용하세요.',
            ],
            [
                'id' => 'tpl_corporate', 'title' => '기업 홍보 영상', 'category' => 'corporate',
                'prompt' => '한국 기업 IR/홍보 영상. 깔끔한 오피스, 데이터 시각화, 신뢰감 있는 내레이션 톤.',
                'aspect_ratio' => '16:9', 'duration' => 30, 'style' => 'documentary', 'camera_motion' => 'static',
                'recommended_provider' => 'runway', 'tags' => ['기업', 'IR', '홍보'],
                'guide' => '핵심 수치와 메시지를 자막으로 강조하세요.',
            ],
        ];
    }
}
