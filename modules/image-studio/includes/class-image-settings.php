<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_Settings {

    private const META_KEY = 'yoy_image_settings';

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
            'aspect_ratios' => [
                ['id' => '1:1', 'label' => '1:1 Square', 'use' => '쇼핑몰, SNS'],
                ['id' => '16:9', 'label' => '16:9 Landscape', 'use' => '배너, 유튜브'],
                ['id' => '9:16', 'label' => '9:16 Vertical', 'use' => '스토리, 릴스'],
                ['id' => '4:5', 'label' => '4:5 Portrait', 'use' => '인스타 피드'],
                ['id' => '3:2', 'label' => '3:2 Photo', 'use' => '제품 사진'],
                ['id' => '2:3', 'label' => '2:3 Portrait', 'use' => '포스터'],
            ],
            'providers' => [
                ['id' => 'mock', 'label' => 'Mock (Local)'],
                ['id' => 'openai', 'label' => 'OpenAI GPT Image'],
                ['id' => 'replicate', 'label' => 'Replicate FLUX'],
                ['id' => 'topview', 'label' => 'Topview Image'],
            ],
            'resolutions' => ['512', '1024', '1536', '2048'],
            'qualities'     => [
                ['id' => 'draft', 'label' => 'Draft', 'credits' => 5],
                ['id' => 'standard', 'label' => 'Standard', 'credits' => 10],
                ['id' => 'hd', 'label' => 'HD', 'credits' => 20],
            ],
            'lighting' => [
                ['id' => 'natural', 'label' => '자연광'],
                ['id' => 'studio', 'label' => '스튜디오'],
                ['id' => 'golden_hour', 'label' => '골든아워'],
                ['id' => 'neon', 'label' => '네온'],
                ['id' => 'soft', 'label' => '소프트'],
                ['id' => 'dramatic', 'label' => '드라마틱'],
            ],
            'compositions' => [
                ['id' => 'center', 'label' => '중앙 구도'],
                ['id' => 'rule_of_thirds', 'label' => '삼분할'],
                ['id' => 'close_up', 'label' => '클로즈업'],
                ['id' => 'wide', 'label' => '와이드'],
                ['id' => 'flat_lay', 'label' => '플랫레이'],
                ['id' => 'hero', 'label' => '히어로 샷'],
            ],
            'styles' => [
                ['id' => 'photorealistic', 'label' => '실사'],
                ['id' => 'commercial', 'label' => '광고'],
                ['id' => 'minimal', 'label' => '미니멀'],
                ['id' => 'k-beauty', 'label' => 'K-Beauty'],
                ['id' => 'illustration', 'label' => '일러스트'],
                ['id' => '3d', 'label' => '3D 렌더'],
                ['id' => 'cinematic', 'label' => '시네마틱'],
                ['id' => 'editorial', 'label' => '에디토리얼'],
            ],
            'backgrounds' => [
                ['id' => 'studio_white', 'label' => '스튜디오 화이트'],
                ['id' => 'studio_gray', 'label' => '스튜디오 그레이'],
                ['id' => 'lifestyle', 'label' => '라이프스타일'],
                ['id' => 'outdoor', 'label' => '야외'],
                ['id' => 'gradient', 'label' => '그라데이션'],
                ['id' => 'transparent', 'label' => '투명 배경'],
            ],
            'color_palettes' => [
                ['id' => 'neutral', 'label' => '뉴트럴'],
                ['id' => 'warm', 'label' => '웜톤'],
                ['id' => 'cool', 'label' => '쿨톤'],
                ['id' => 'pastel', 'label' => '파스텔'],
                ['id' => 'vivid', 'label' => '비비드'],
                ['id' => 'monochrome', 'label' => '모노크롬'],
            ],
            'product_types' => [
                ['id' => 'general', 'label' => '일반'],
                ['id' => 'cosmetics', 'label' => '화장품'],
                ['id' => 'fashion', 'label' => '패션'],
                ['id' => 'food', 'label' => '식품'],
                ['id' => 'electronics', 'label' => '전자기기'],
                ['id' => 'interior', 'label' => '인테리어'],
            ],
            'brand_tones' => [
                ['id' => 'premium', 'label' => '프리미엄'],
                ['id' => 'friendly', 'label' => '친근함'],
                ['id' => 'youthful', 'label' => '젊은'],
                ['id' => 'luxury', 'label' => '럭셔리'],
                ['id' => 'eco', 'label' => '친환경'],
            ],
            'image_counts' => [1, 2, 3, 4],
        ];
    }

    private function defaults(): array {
        return [
            'default_provider' => 'mock',
            'default_model'    => 'mock-image-v1',
            'aspect_ratio'     => '1:1',
            'resolution'       => '1024',
            'quality'          => 'standard',
            'lighting'         => 'studio',
            'composition'      => 'center',
            'style'            => 'commercial',
            'background'       => 'studio_white',
            'color_palette'    => 'neutral',
            'product_type'     => 'general',
            'brand_tone'       => 'premium',
            'negative_prompt'  => 'blurry, low quality, distorted, watermark, text errors',
            'seed'             => -1,
            'image_count'      => 1,
            'korean_context'   => true,
            'auto_save'        => true,
        ];
    }

    private function sanitize(string $key, $value) {
        return match ($key) {
            'seed', 'image_count' => (int) $value,
            'korean_context', 'auto_save' => (bool) $value,
            'negative_prompt' => sanitize_textarea_field((string) $value),
            default => sanitize_text_field((string) $value),
        };
    }
}
