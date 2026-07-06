<?php
if (!defined('ABSPATH')) exit;

require_once YOY_AI_STUDIO_PROVIDERS_DIR . 'helpers/class-yoy-asset-generator.php';

final class YooY_Avatar_Catalog {

    public function avatars(): array {
        $items = [
            ['id' => 'ko_female_01', 'name' => '지연 (K-Beauty MC)', 'gender' => 'female', 'style' => 'professional', 'label' => 'MC'],
            ['id' => 'ko_male_01', 'name' => '민수 (뉴스 앵커)', 'gender' => 'male', 'style' => 'professional', 'label' => 'Anchor'],
            ['id' => 'ko_female_02', 'name' => '수아 (쇼핑 호스트)', 'gender' => 'female', 'style' => 'casual', 'label' => 'Host'],
            ['id' => 'ko_male_02', 'name' => '준호 (유튜버)', 'gender' => 'male', 'style' => 'casual', 'label' => 'Creator'],
            ['id' => 'ko_child_01', 'name' => '하늘 (키즈)', 'gender' => 'child', 'style' => 'cute', 'label' => 'Kids'],
        ];

        return array_map(function ($item) {
            $item['preview'] = YooY_Asset_Generator::svg_data_uri(200, 200, $item['label'], '#1a1a1a', '#d8a63a');
            unset($item['label']);
            return $item;
        }, $items);
    }

    public function voices(): array {