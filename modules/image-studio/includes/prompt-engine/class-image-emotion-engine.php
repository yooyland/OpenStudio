<?php
if (!defined('ABSPATH')) exit;

/**
 * Translates emotional language into visual vocabulary — never literal word rendering.
 */
final class YooY_Image_Emotion_Engine {

    /** @var array<string, array<string, mixed>> */
    private static $lexicon = [
        'suffocation' => [
            'keywords' => ['답답', '숨막', '답답한', '답답함', 'suffocat', 'stifl', 'claustroph', 'cramped', 'oppress'],
            'emotions' => ['frustration', 'anxiety', 'invisible emotional pressure'],
            'visuals'  => ['narrow confined space', 'heavy still air', 'muted desaturated palette', 'spatial pressure', 'head lowered', 'claustrophobic framing'],
            'lighting' => 'soft window light with deep shadows',
            'mood'     => 'moody',
            'genre'    => 'cinematic fine-art portrait',
        ],
        'loneliness' => [
            'keywords' => ['외로', '고독', '쓸쓸', 'lonely', 'loneliness', 'solitude', 'alone'],
            'emotions' => ['loneliness', 'quiet isolation'],
            'visuals'  => ['vast open sea or empty horizon', 'single small figure', 'mist and fog', 'long shadows', 'negative space'],
            'lighting' => 'overcast diffused light',
            'mood'     => 'melancholic',
            'genre'    => 'cinematic landscape portrait',
        ],
        'hope' => [
            'keywords' => ['희망', 'hope', 'hopeful', '기대'],
            'emotions' => ['hope', 'quiet optimism'],
            'visuals'  => ['backlit silhouette', 'warm golden rim light', 'blue sky breaking through clouds', 'birds in distance', 'gentle sunrise'],
            'lighting' => 'backlit golden hour',
            'mood'     => 'hopeful',
            'genre'    => 'cinematic storytelling',
        ],
        'freedom' => [
            'keywords' => ['자유', 'free', 'freedom', 'liberat'],
            'emotions' => ['freedom', 'release'],
            'visuals'  => ['high cliff edge', 'wind in hair and fabric', 'expansive sky', 'dynamic movement', 'open horizon'],
            'lighting' => 'bright natural daylight',
            'mood'     => 'epic',
            'genre'    => 'cinematic wide shot',
        ],
        'happiness' => [
            'keywords' => ['행복', '기쁨', '즐거', 'happy', 'joy', 'delight'],
            'emotions' => ['genuine happiness', 'warmth'],
            'visuals'  => ['natural authentic smile', 'warm color palette', 'sunlit environment', 'lively energy', 'candid moment'],
            'lighting' => 'bright soft sunlight',
            'mood'     => 'joyful',
            'genre'    => 'premium lifestyle photography',
        ],
        'sadness' => [
            'keywords' => ['슬픔', '슬픈', '우울', 'sad', 'sorrow', 'grief', 'melanchol'],
            'emotions' => ['sadness', 'quiet grief'],
            'visuals'  => ['rain on window', 'cool blue tones', 'downcast gaze', 'empty chair', 'soft blur at edges'],
            'lighting' => 'dim overcast light',
            'mood'     => 'melancholic',
            'genre'    => 'emotional fine-art photography',
        ],
        'anger' => [
            'keywords' => ['분노', '화', 'anger', 'rage', 'fury'],
            'emotions' => ['controlled intensity', 'inner turmoil'],
            'visuals'  => ['tight facial tension', 'dramatic contrast', 'sharp shadows', 'compressed framing'],
            'lighting' => 'hard dramatic side light',
            'mood'     => 'intense',
            'genre'    => 'dramatic portrait',
        ],
        'peace' => [
            'keywords' => ['평화', '고요', '평온', 'peace', 'calm', 'serene', 'tranquil'],
            'emotions' => ['serenity', 'inner peace'],
            'visuals'  => ['still water reflection', 'soft morning mist', 'minimal composition', 'harmonious balance'],
            'lighting' => 'soft diffused morning light',
            'mood'     => 'serene',
            'genre'    => 'minimal fine-art photography',
        ],
        'love' => [
            'keywords' => ['사랑', '연인', '로맨', 'love', 'romantic', 'affection'],
            'emotions' => ['tenderness', 'intimacy'],
            'visuals'  => ['intimate close proximity', 'warm bokeh', 'gentle touch', 'soft focus background'],
            'lighting' => 'warm golden soft light',
            'mood'     => 'romantic',
            'genre'    => 'editorial portrait',
        ],
        'wonder' => [
            'keywords' => ['경이', '신비', '몽환', 'wonder', 'awe', 'magical', 'dream'],
            'emotions' => ['awe', 'wonder'],
            'visuals'  => ['ethereal atmosphere', 'subtle lens flare', 'surreal scale', 'floating particles of light'],
            'lighting' => 'ethereal backlight',
            'mood'     => 'dreamy',
            'genre'    => 'cinematic fantasy realism',
        ],
    ];

    /**
     * @return array{primary: string, emotions: string[], visuals: string[], lighting: string, mood: string, genre: string, abstract: bool}
     */
    public function analyze(string $prompt): array {
        $hay = mb_strtolower($prompt);
        $best = null;
        $best_score = 0;

        foreach (self::$lexicon as $id => $entry) {
            $score = 0;
            foreach ($entry['keywords'] as $kw) {
                if (mb_strpos($hay, mb_strtolower($kw)) !== false) {
                    $score += 2;
                }
            }
            if ($score > $best_score) {
                $best_score = $score;
                $best = $id;
            }
        }

        if ($best === null || $best_score === 0) {
            return [
                'primary'   => 'neutral',
                'emotions'  => [],
                'visuals'   => [],
                'lighting'  => '',
                'mood'      => 'neutral',
                'genre'     => '',
                'abstract'  => $this->is_abstract_emotional($prompt),
            ];
        }

        $entry = self::$lexicon[$best];
        return [
            'primary'   => $best,
            'emotions'  => $entry['emotions'],
            'visuals'   => $entry['visuals'],
            'lighting'  => $entry['lighting'],
            'mood'      => $entry['mood'],
            'genre'     => $entry['genre'],
            'abstract'  => true,
        ];
    }

    public function is_abstract_emotional(string $prompt): bool {
        $trim = trim($prompt);
        if ($trim === '') {
            return false;
        }
        $len = mb_strlen($trim);
        if ($len > 80) {
            return false;
        }

        $analysis = $this->analyze($prompt);
        if ($analysis['primary'] !== 'neutral') {
            return true;
        }

        $concrete = ['제품', 'product', 'logo', '썸네일', 'thumbnail', 'banner', '배너', '광고', 'advert', '스마트스토어', 'ecommerce'];
        $hay = mb_strtolower($trim);
        foreach ($concrete as $word) {
            if (mb_strpos($hay, mb_strtolower($word)) !== false) {
                return false;
            }
        }

        return $len <= 24 && preg_match('/[\x{AC00}-\x{D7A3}]/u', $trim);
    }
}
