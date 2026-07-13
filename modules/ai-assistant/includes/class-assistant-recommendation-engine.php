<?php
if (!defined('ABSPATH')) exit;

/**
 * Purpose-first recommendation cards for Conversational Assistant UI.
 */
final class YooY_Assistant_Recommendation_Engine {

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    public function cards(array $context = []): array {
        $cards = [
            [
                'id'          => 'purpose_ad',
                'purpose'     => 'ad',
                'tone'        => 'coral',
                'icon'        => 'megaphone',
                'title'       => '광고 만들기',
                'description' => '제품, 서비스, 브랜드 광고',
                'cta'         => '시작하기',
                'seed'        => '광고 만들고 싶어',
                'opening'     => '어떤 광고를 만들고 싶으신가요?',
                'studios'     => ['image', 'video', 'writing'],
            ],
            [
                'id'          => 'purpose_youtube',
                'purpose'     => 'youtube',
                'tone'        => 'purple',
                'icon'        => 'clapper',
                'title'       => '유튜브 영상',
                'description' => '기획부터 영상 제작',
                'cta'         => '시작하기',
                'seed'        => '유튜브 영상 만들고 싶어',
                'opening'     => '어떤 유튜브 영상을 만들고 싶으신가요?',
                'studios'     => ['video', 'writing', 'music', 'voice'],
            ],
            [
                'id'          => 'purpose_shorts',
                'purpose'     => 'shorts',
                'tone'        => 'sky',
                'icon'        => 'phone',
                'title'       => '쇼츠 / 릴스',
                'description' => '짧고 임팩트 있는 숏폼',
                'cta'         => '시작하기',
                'seed'        => '쇼츠 만들고 싶어',
                'opening'     => '어떤 쇼츠/릴스를 만들고 싶으신가요?',
                'studios'     => ['video', 'music', 'writing'],
            ],
            [
                'id'          => 'purpose_blog',
                'purpose'     => 'blog',
                'tone'        => 'green',
                'icon'        => 'doc',
                'title'       => '블로그 / 글쓰기',
                'description' => '블로그 글, 칼럼, 스토리',
                'cta'         => '시작하기',
                'seed'        => '블로그 글 쓰고 싶어',
                'opening'     => '어떤 글을 쓰고 싶으신가요?',
                'studios'     => ['writing', 'image'],
            ],
            [
                'id'          => 'purpose_music',
                'purpose'     => 'music',
                'tone'        => 'orange',
                'icon'        => 'headphones',
                'title'       => '음악 / BGM',
                'description' => '분위기에 맞는 음악',
                'cta'         => '시작하기',
                'seed'        => '음악 만들고 싶어',
                'opening'     => '어떤 분위기의 음악이 필요하신가요?',
                'studios'     => ['music', 'voice'],
            ],
            [
                'id'          => 'purpose_translate',
                'purpose'     => 'translate',
                'tone'        => 'indigo',
                'icon'        => 'translate',
                'title'       => '번역하기',
                'description' => '문서, 텍스트, 웹 콘텐츠',
                'cta'         => '시작하기',
                'seed'        => '번역하고 싶어',
                'opening'     => '무엇을 번역하고 싶으신가요?',
                'studios'     => ['translator', 'writing'],
            ],
        ];

        if (($context['mode'] ?? '') === 'project' && !empty($context['project']['title'])) {
            $title = (string) $context['project']['title'];
            array_unshift($cards, [
                'id'          => 'purpose_project',
                'purpose'     => 'project',
                'tone'        => 'gold',
                'icon'        => 'folder',
                'title'       => $title . ' 이어가기',
                'description' => 'Active Project로 다음 작품 기획',
                'cta'         => '시작하기',
                'seed'        => $title . ' 프로젝트 다음 작품 기획해줘',
                'opening'     => '프로젝트에서 다음에 무엇을 만들까요?',
                'studios'     => ['image', 'video', 'writing'],
            ]);
        }

        return $cards;
    }
}
