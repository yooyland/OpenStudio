<?php
if (!defined('ABSPATH')) exit;

/**
 * Default Official Showcase + Demo seed (80 items).
 *
 * @return array<int, array<string, mixed>>
 */
function yoy_official_showcase_seed_data(): array {
    $genres = [
        'kpop'     => ['label' => 'K-POP', 'prompts' => ['Neon K-pop stage portrait', 'Idol comeback poster art', 'Music video key visual']],
        'korea'    => ['label' => '대한민국', 'prompts' => ['Seoul cityscape at night', 'Hanok village in autumn', 'Korean street food market']],
        'ad'       => ['label' => '광고', 'prompts' => ['Premium product hero shot', 'Luxury brand campaign visual', 'Minimal ad banner concept']],
        'fashion'  => ['label' => '패션', 'prompts' => ['Editorial fashion lookbook', 'Runway outfit concept', 'Streetwear campaign']],
        'travel'   => ['label' => '여행', 'prompts' => ['Jeju island travel poster', 'Airport lifestyle photo', 'Resort sunset panorama']],
        'beauty'   => ['label' => '뷰티', 'prompts' => ['Skincare product flat lay', 'Makeup tutorial thumbnail', 'Salon beauty portrait']],
        'food'     => ['label' => '음식', 'prompts' => ['Korean BBQ food photography', 'Cafe dessert close-up', 'Recipe blog hero image']],
        'character'=> ['label' => '캐릭터', 'prompts' => ['Anime-style mascot design', 'Game character concept art', 'Cute chibi avatar']],
        'webtoon'  => ['label' => '웹툰', 'prompts' => ['Webtoon cover illustration', 'Romance comic panel', 'Action scene splash page']],
        'ai-video' => ['label' => 'AI 영상', 'prompts' => ['Cinematic AI video still', 'Product reel frame', 'Social short-form clip']],
    ];

    $types = ['image', 'image', 'image', 'video', 'music', 'writing', 'image', 'video'];
    $items = [];
    $sort = 0;
    $base_url = defined('YOY_AI_STUDIO_URL') ? YOY_AI_STUDIO_URL : '';

    foreach ($genres as $genre => $meta) {
        for ($i = 1; $i <= 8; $i++) {
            $type = $types[($sort + $i) % count($types)];
            $prompt = $meta['prompts'][($i - 1) % count($meta['prompts'])];
            $id = 'off_' . substr(md5($genre . '-' . $i), 0, 12);
            $hue = (crc32($genre . $i) % 360);
            $items[] = [
                'id'            => $id,
                'title'         => $meta['label'] . ' · Demo ' . $i,
                'description'   => $prompt . ' — YooY Official Showcase',
                'type'          => $type,
                'genre'         => $genre,
                'prompt'        => $prompt,
                'thumbnail_url' => $base_url . 'official-showcase/thumbs/placeholder.svg?g=' . rawurlencode($genre) . '&h=' . $hue,
                'media_url'     => '',
                'featured'      => ($i === 1),
                'recommended'   => ($i <= 3),
                'hidden'        => false,
                'is_demo'       => true,
                'sort_order'    => $sort,
                'created_at'    => gmdate('c', time() - ($sort * 3600)),
                'updated_at'    => gmdate('c', time() - ($sort * 1800)),
            ];
            $sort++;
        }
    }

    return $items;
}
