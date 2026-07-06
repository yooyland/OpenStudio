<?php
if (!defined('ABSPATH')) exit;

final class YooY_Voice_Catalog {

    public function voices(): array {
        $cloned = $this->get_cloned_voices();
        return array_merge([
            ['id' => 'ko_female_warm', 'name' => '지은 (따뜻한)', 'language' => 'ko', 'gender' => 'female', 'category' => 'premade', 'preview' => '따뜻하고 친근한 한국어 여성'],
            ['id' => 'ko_female_bright', 'name' => '서연 (밝은)', 'language' => 'ko', 'gender' => 'female', 'category' => 'premade', 'preview' => '밝고 에너지 넘치는 톤'],
            ['id' => 'ko_female_narrator', 'name' => '하은 (내레이션)', 'language' => 'ko', 'gender' => 'female', 'category' => 'premade', 'preview' => '다큐/광고 내레이션'],
            ['id' => 'ko_male_deep', 'name' => '준혁 (저음)', 'language' => 'ko', 'gender' => 'male', 'category' => 'premade', 'preview' => '신뢰감 있는 저음 남성'],
            ['id' => 'ko_male_friendly', 'name' => '민재 (친근한)', 'language' => 'ko', 'gender' => 'male', 'category' => 'premade', 'preview' => '유튜브/팟캐스트 톤'],
            ['id' => 'ko_male_announcer', 'name' => '성우 (아나운서)', 'language' => 'ko', 'gender' => 'male', 'category' => 'premade', 'preview' => '뉴스/공식 안내'],
            ['id' => 'en_female', 'name' => 'Emma (English)', 'language' => 'en', 'gender' => 'female', 'category' => 'premade', 'preview' => 'Natural English female'],
            ['id' => 'en_male', 'name' => 'James (English)', 'language' => 'en', 'gender' => 'male', 'category' => 'premade', 'preview' => 'Natural English male'],
            ['id' => 'ja_female', 'name' => 'さくら (Japanese)', 'language' => 'ja', 'gender' => 'female', 'category' => 'premade', 'preview' => 'Japanese female'],
        ], $cloned);
    }

    public function emotions(): array {
        return [
            ['id' => 'neutral', 'label' => 'Neutral', 'modifier' => 0],
            ['id' => 'happy', 'label' => 'Happy', 'modifier' => 0.15],
            ['id' => 'sad', 'label' => 'Sad', 'modifier' => -0.1],
            ['id' => 'angry', 'label' => 'Angry', 'modifier' => 0.2],
            ['id' => 'excited', 'label' => 'Excited', 'modifier' => 0.25],
            ['id' => 'calm', 'label' => 'Calm', 'modifier' => -0.15],
            ['id' => 'whisper', 'label' => 'Whisper', 'modifier' => -0.3],
            ['id' => 'confident', 'label' => 'Confident', 'modifier' => 0.1],
        ];
    }

    public function languages(): array {
        return [
            ['id' => 'ko', 'label' => '한국어', 'code' => 'ko'],
            ['id' => 'en', 'label' => 'English', 'code' => 'en'],
            ['id' => 'ja', 'label' => '日本語', 'code' => 'ja'],
            ['id' => 'zh', 'label' => '中文', 'code' => 'zh'],
            ['id' => 'es', 'label' => 'Español', 'code' => 'es'],
            ['id' => 'fr', 'label' => 'Français', 'code' => 'fr'],
        ];
    }

    public function add_cloned_voice(int $user_id, array $voice): void {
        $cloned = get_user_meta($user_id, 'yoy_cloned_voices', true);
        $cloned = is_array($cloned) ? $cloned : [];
        $cloned[] = array_merge($voice, ['category' => 'cloned', 'created_at' => gmdate('c')]);
        update_user_meta($user_id, 'yoy_cloned_voices', $cloned);
    }

    private function get_cloned_voices(): array {
        return [];
    }

    public function cloned_voices(int $user_id): array {
        $stored = get_user_meta($user_id, 'yoy_cloned_voices', true);
        return is_array($stored) ? $stored : [];
    }
}
