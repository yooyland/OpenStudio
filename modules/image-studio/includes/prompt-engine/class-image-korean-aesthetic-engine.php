<?php
if (!defined('ABSPATH')) exit;

/**
 * Applies Korean cultural aesthetics when Korean prompts are detected.
 */
final class YooY_Image_Korean_Aesthetic_Engine {

    /** @var array<string, array<string, mixed>> */
    private static $motifs = [
        'arirang' => [
            'keywords' => ['아리랑', 'arirang'],
            'visuals'  => ['traditional Korean hanbok silhouettes', 'misty mountain ridges at dawn', 'ink-wash painting texture', 'subtle taeguk harmony', 'East Asian brushstroke atmosphere'],
            'palette'  => 'muted ink and earth tones with soft morning fog',
            'style'    => 'fine-art Korean landscape portrait',
        ],
        'hanbok' => [
            'keywords' => ['한복', 'hanbok', '전통', 'traditional korean'],
            'visuals'  => ['elegant hanbok fabric detail', 'Korean palace or hanok architecture', 'refined traditional composition', 'soft natural light'],
            'palette'  => 'harmonious traditional Korean color palette',
            'style'    => 'editorial Korean heritage photography',
        ],
        'kpop' => [
            'keywords' => ['k-pop', 'kpop', '케이팝', '아이돌', 'idol', '뮤직비디오', 'music video'],
            'visuals'  => ['high-fashion K-pop editorial lighting', 'dynamic pose', 'premium beauty retouching', 'vivid yet refined color grading'],
            'palette'  => 'contemporary Korean pop aesthetic',
            'style'    => 'K-pop album cover quality',
        ],
        'kbeauty' => [
            'keywords' => ['k-beauty', 'kbeauty', '케이뷰티', '스킨케어', 'skincare', '화장품'],
            'visuals'  => ['glass-skin beauty lighting', 'minimal clean Korean beauty composition', 'soft gradient background', 'premium cosmetic styling'],
            'palette'  => 'clean pastel Korean beauty tones',
            'style'    => 'K-beauty commercial photography',
        ],
        'korea_general' => [
            'keywords' => ['한국', '대한민국', 'korea', 'korean', '서울', 'seoul', '한강', 'jeju', '제주'],
            'visuals'  => ['distinctly Korean contemporary aesthetic', 'refined local sensibility', 'premium Korean commercial finish'],
            'palette'  => 'balanced modern Korean color harmony',
            'style'    => 'premium Korean visual culture',
        ],
    ];

    public function is_korean(string $prompt): bool {
        return (bool) preg_match('/[\x{AC00}-\x{D7A3}]/u', $prompt);
    }

    /**
     * @return array{active: bool, motif: string, visuals: string[], palette: string, style: string}
     */
    public function analyze(string $prompt): array {
        if (!$this->is_korean($prompt)) {
            return ['active' => false, 'motif' => '', 'visuals' => [], 'palette' => '', 'style' => ''];
        }

        $hay = mb_strtolower($prompt);
        foreach (self::$motifs as $id => $entry) {
            foreach ($entry['keywords'] as $kw) {
                if (mb_strpos($hay, mb_strtolower($kw)) !== false) {
                    return [
                        'active'  => true,
                        'motif'   => $id,
                        'visuals' => $entry['visuals'],
                        'palette' => $entry['palette'],
                        'style'   => $entry['style'],
                    ];
                }
            }
        }

        return [
            'active'  => true,
            'motif'   => 'korea_general',
            'visuals' => self::$motifs['korea_general']['visuals'],
            'palette' => self::$motifs['korea_general']['palette'],
            'style'   => self::$motifs['korea_general']['style'],
        ];
    }
}
