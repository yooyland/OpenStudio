<?php
if (!defined('ABSPATH')) exit;

final class YooY_Avatar_Catalog {

    private function ensure_asset_generator(): void {
        if (class_exists('YooY_Asset_Generator')) {
            return;
        }
        $asset_helper = YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';
        if (is_readable($asset_helper)) {
            require_once $asset_helper;
        }
    }

    public function avatars(): array {
        $this->ensure_asset_generator();

        $items = [
            ['id' => 'ko_female_01', 'name' => '지연 (K-Beauty MC)', 'gender' => 'female', 'style' => 'professional', 'label' => 'MC'],
            ['id' => 'ko_male_01', 'name' => '민수 (뉴스 앵커)', 'gender' => 'male', 'style' => 'professional', 'label' => 'Anchor'],
            ['id' => 'ko_female_02', 'name' => '수아 (쇼핑 호스트)', 'gender' => 'female', 'style' => 'casual', 'label' => 'Host'],
            ['id' => 'ko_male_02', 'name' => '준호 (유튜버)', 'gender' => 'male', 'style' => 'casual', 'label' => 'Creator'],
            ['id' => 'ko_child_01', 'name' => '하늘 (키즈)', 'gender' => 'child', 'style' => 'cute', 'label' => 'Kids'],
        ];

        return array_map(function ($item) {
            if (class_exists('YooY_Asset_Generator')) {
                $item['preview'] = YooY_Asset_Generator::svg_data_uri(200, 200, $item['label'], '#1a1a1a', '#d8a63a');
            } else {
                $item['preview'] = '';
            }
            unset($item['label']);
            return $item;
        }, $items);
    }

    public function voices(): array {
        return [
            ['id' => 'ko_female_warm', 'name' => '한국어 여성 (따뜻한)', 'language' => 'ko', 'gender' => 'female'],
            ['id' => 'ko_female_bright', 'name' => '한국어 여성 (밝은)', 'language' => 'ko', 'gender' => 'female'],
            ['id' => 'ko_male_deep', 'name' => '한국어 남성 (저음)', 'language' => 'ko', 'gender' => 'male'],
            ['id' => 'ko_male_friendly', 'name' => '한국어 남성 (친근한)', 'language' => 'ko', 'gender' => 'male'],
            ['id' => 'en_female', 'name' => 'English Female', 'language' => 'en', 'gender' => 'female'],
        ];
    }

    public function expressions(): array {
        return [
            ['id' => 'neutral', 'label' => 'Neutral'],
            ['id' => 'happy', 'label' => 'Happy'],
            ['id' => 'serious', 'label' => 'Serious'],
            ['id' => 'friendly', 'label' => 'Friendly'],
            ['id' => 'surprised', 'label' => 'Surprised'],
            ['id' => 'confident', 'label' => 'Confident'],
        ];
    }

    public function gestures(): array {
        return [
            ['id' => 'natural', 'label' => 'Natural'],
            ['id' => 'presenting', 'label' => 'Presenting'],
            ['id' => 'pointing', 'label' => 'Pointing'],
            ['id' => 'waving', 'label' => 'Waving'],
            ['id' => 'thinking', 'label' => 'Thinking'],
            ['id' => 'emphasis', 'label' => 'Emphasis'],
        ];
    }

    public function cameras(): array {
        return [
            ['id' => 'close_up', 'label' => 'Close-up', 'description' => '얼굴 클로즈업'],
            ['id' => 'medium', 'label' => 'Medium Shot', 'description' => '상반신'],
            ['id' => 'wide', 'label' => 'Wide Shot', 'description' => '전신'],
            ['id' => 'over_shoulder', 'label' => 'Over Shoulder', 'description' => '어깨 너머'],
            ['id' => 'dynamic', 'label' => 'Dynamic', 'description' => '카메라 움직임'],
        ];
    }

    public function emotions(): array {
        return [
            ['id' => 'confident', 'label' => '자신감'],
            ['id' => 'warm', 'label' => '따뜻함'],
            ['id' => 'energetic', 'label' => '에너지'],
            ['id' => 'calm', 'label' => '차분함'],
            ['id' => 'passionate', 'label' => '열정'],
            ['id' => 'trustworthy', 'label' => '신뢰감'],
        ];
    }

    public function backgrounds(): array {
        return [
            ['id' => 'studio', 'label' => '스튜디오', 'type' => 'solid', 'preview' => '#1a1a1a'],
            ['id' => 'office', 'label' => '오피스', 'type' => 'scene'],
            ['id' => 'home', 'label' => '홈', 'type' => 'scene'],
            ['id' => 'outdoor', 'label' => '야외', 'type' => 'scene'],
            ['id' => 'green_screen', 'label' => '그린스크린', 'type' => 'chroma'],
            ['id' => 'brand', 'label' => '브랜드 배경', 'type' => 'custom'],
        ];
    }

    public function scenes(): array {
        return [
            ['id' => 'product_intro', 'label' => '제품 소개', 'template' => '라이브커머스 제품 소개 영상'],
            ['id' => 'news', 'label' => '뉴스/앵커', 'template' => '뉴스 데스크 앵커 리포트'],
            ['id' => 'education', 'label' => '교육/강의', 'template' => '온라인 강의 아바타'],
            ['id' => 'ad_commercial', 'label' => '광고/TVC', 'template' => '15초 브랜드 광고'],
            ['id' => 'youtube_intro', 'label' => '유튜브 인트로', 'template' => '유튜브 채널 오프닝'],
            ['id' => 'customer_service', 'label' => '고객 안내', 'template' => 'CS 안내 아바타'],
        ];
    }
}
