<?php
if (!defined('ABSPATH')) exit;

/**
 * Studio Intent Analyzer — preserves primary subject over style templates.
 * Reusable across Studios; Image is the first consumer.
 */
final class YooY_Studio_Intent_Analyzer {

    /** @var array<string, string> */
    private const ENTITY_EN = [
        '이재명'   => 'Lee Jae-myung',
        '윤석열'   => 'Yoon Suk-yeol',
        '대한민국' => 'South Korea',
        '한국'     => 'Korea',
        '대통령실' => 'Office of the President of Korea',
        '대통령'   => 'President',
        '서울'     => 'Seoul',
        '부산'     => 'Busan',
        '제주'     => 'Jeju',
    ];

    /**
     * @param string               $raw
     * @param array<string, mixed> $hint Optional creative_brief / domain hint from Assistant.
     * @return array<string, mixed>
     */
    public function analyze(string $raw, array $hint = []): array {
        $raw = trim($raw);
        $lower = mb_strtolower($raw);
        $domain = $this->classify_domain($lower, $hint);
        $entities = $this->extract_entities($raw);
        $ad_subtype = $this->classify_ad_subtype($domain, $lower);
        $primary = $this->primary_subject($raw, $entities, $domain, $hint);
        $format = $this->output_format($domain, $ad_subtype, $lower, $hint);
        $forbidden = $this->forbidden_for_domain($domain);

        $intent = [
            'primary_subject'    => $primary,
            'entities'           => $entities,
            'intent'             => $this->intent_summary($domain, $ad_subtype, $primary),
            'content_domain'     => $domain,
            'ad_subtype'         => $ad_subtype,
            'output_format'      => $format,
            'audience'           => $this->audience($domain, $hint),
            'core_message'       => $this->core_message($raw, $primary, $hint),
            'tone'               => $this->tone($lower, $hint),
            'visual_style'       => $this->visual_style($domain, $ad_subtype),
            'composition'        => $this->composition($domain),
            'camera'             => $domain === 'politics' ? 'editorial medium shot' : '',
            'lighting'           => $domain === 'politics' ? 'clean civic lighting' : '',
            'color_palette'      => $this->palette($domain),
            'required_elements'  => $this->required($domain, $entities),
            'forbidden_elements' => $forbidden,
            'text_overlay'       => [],
            'project_context'    => is_array($hint['project_context'] ?? null) ? $hint['project_context'] : [],
            'confidence'         => $raw === '' ? 0.0 : ($domain !== 'general' ? 0.86 : 0.62),
            'raw_user_request'   => $raw,
            'wants_product'      => $domain === 'product' || $domain === 'ecommerce' || $domain === 'fashion' || $domain === 'food',
            'wants_political'    => $domain === 'politics',
        ];

        return $intent;
    }

    /**
     * @param array<string, mixed> $hint
     */
    private function classify_domain(string $lower, array $hint): string {
        if (!empty($hint['intent_domain'])) {
            return sanitize_key((string) $hint['intent_domain']);
        }
        if (!empty($hint['content_domain'])) {
            return sanitize_key((string) $hint['content_domain']);
        }

        $rules = [
            'politics'      => ['정치', '이재명', '대통령', '선거', '정책', '국회', '정당', '대선', '여야', 'political', 'president', 'election', 'policy'],
            'product'       => ['제품', '상품', '향수', '화장품', '스킨케어', '병', '패키지', 'perfume', 'cosmetic', 'skincare', 'bottle', 'product'],
            'ecommerce'     => ['스마트스토어', '쿠팡', '이커머스', '상세페이지', 'ecommerce', 'coupang'],
            'travel'        => ['여행', '관광', '제주', '휴가', 'tour', 'travel'],
            'corporate'     => ['회사 소개', '기업', '채용', 'corporate', 'recruit'],
            'education'     => ['교육', '학교', '강의', 'education'],
            'entertainment' => ['영화', '엔터', '드라마', 'entertainment', 'movie'],
            'food'          => ['음식', '맛집', '요리', 'food', 'restaurant'],
            'fashion'       => ['패션', '의류', 'fashion', 'apparel'],
            'portrait'      => ['인물 사진', '초상', 'portrait'],
            'editorial'     => ['매거진', 'editorial', '화보'],
            'social'        => ['사회 캠페인', '공익', 'social campaign', 'psa'],
            'brand'         => ['브랜드', 'brand identity', '로고'],
        ];

        foreach ($rules as $domain => $needles) {
            foreach ($needles as $n) {
                if ($n !== '' && mb_strpos($lower, mb_strtolower($n)) !== false) {
                    return $domain;
                }
            }
        }

        if (preg_match('/광고|advert|campaign|캠페인/u', $lower)) {
            return 'brand';
        }
        return 'general';
    }

    private function classify_ad_subtype(string $domain, string $lower): string {
        $is_ad = (bool) preg_match('/광고|advert|campaign|캠페인|포스터|poster/u', $lower);
        if (!$is_ad && $domain !== 'politics') {
            return '';
        }
        $map = [
            'politics'  => 'political_advertisement',
            'product'   => 'product_advertisement',
            'ecommerce' => 'product_advertisement',
            'travel'    => 'tourism_advertisement',
            'corporate' => 'corporate_advertisement',
            'social'    => 'social_campaign',
            'brand'     => 'brand_advertisement',
            'food'      => 'product_advertisement',
            'fashion'   => 'product_advertisement',
        ];
        return $map[$domain] ?? ($is_ad ? 'public_campaign' : '');
    }

    /**
     * @param array<int, array{name:string,name_en:string}> $entities
     * @param array<string, mixed> $hint
     */
    private function primary_subject(string $raw, array $entities, string $domain, array $hint): string {
        if (!empty($hint['primary_subject'])) {
            return sanitize_text_field((string) $hint['primary_subject']);
        }
        if ($entities) {
            $names = [];
            foreach ($entities as $e) {
                $names[] = $e['name_en'] !== '' ? $e['name_en'] . ' (' . $e['name'] . ')' : $e['name'];
            }
            if ($domain === 'politics') {
                return implode(', ', $names) . ' delivering the most important Korean political message';
            }
            return implode(', ', $names);
        }
        $cut = mb_substr(trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw), 0, 160);
        return $cut !== '' ? $cut : 'user-requested creative subject';
    }

    /** @return array<int, array{name:string,name_en:string}> */
    private function extract_entities(string $raw): array {
        $found = [];
        foreach (self::ENTITY_EN as $ko => $en) {
            if (mb_strpos($raw, $ko) !== false) {
                $found[] = ['name' => $ko, 'name_en' => $en];
            }
        }
        return $found;
    }

    /**
     * @param array<string, mixed> $hint
     */
    private function output_format(string $domain, string $ad_subtype, string $lower, array $hint): string {
        if (!empty($hint['output_format'])) {
            return sanitize_text_field((string) $hint['output_format']);
        }
        if ($ad_subtype === 'political_advertisement' || $domain === 'politics') {
            return 'premium Korean political editorial campaign poster';
        }
        if ($ad_subtype === 'tourism_advertisement' || $domain === 'travel') {
            return 'tourism campaign visual';
        }
        if ($ad_subtype === 'product_advertisement' || $domain === 'product' || $domain === 'ecommerce') {
            return 'premium product advertising photograph';
        }
        if (preg_match('/포스터|poster|썸네일|thumbnail/u', $lower)) {
            return 'editorial poster composition';
        }
        if (preg_match('/광고|advert|campaign/u', $lower)) {
            return 'premium advertising campaign visual';
        }
        return 'photorealistic image';
    }

    /** @param array<string, mixed> $hint */
    private function audience(string $domain, array $hint): string {
        if (!empty($hint['audience'])) {
            return sanitize_text_field((string) $hint['audience']);
        }
        if ($domain === 'politics') {
            return 'Korean public audience interested in political messaging';
        }
        return 'general Korean creators and consumers';
    }

    /** @param array<string, mixed> $hint */
    private function core_message(string $raw, string $primary, array $hint): string {
        if (!empty($hint['core_message'])) {
            return sanitize_textarea_field((string) $hint['core_message']);
        }
        return 'Communicate the user request faithfully: ' . mb_substr($primary, 0, 200);
    }

    /** @param array<string, mixed> $hint */
    private function tone(string $lower, array $hint): string {
        if (!empty($hint['tone'])) {
            return sanitize_text_field((string) $hint['tone']);
        }
        $parts = [];
        if (preg_match('/프리미엄|premium|럭셔리|luxury/u', $lower)) {
            $parts[] = 'premium';
        }
        if (preg_match('/신뢰|trust|진지/u', $lower)) {
            $parts[] = 'trustworthy';
        }
        if (preg_match('/임팩트|impact|강렬/u', $lower)) {
            $parts[] = 'impactful';
        }
        if (!$parts) {
            $parts[] = 'clear';
            $parts[] = 'professional';
        }
        return implode(', ', $parts);
    }

    private function visual_style(string $domain, string $ad_subtype): string {
        if ($domain === 'politics' || $ad_subtype === 'political_advertisement') {
            return 'Korean editorial political poster';
        }
        if ($domain === 'travel') {
            return 'cinematic tourism campaign';
        }
        if ($domain === 'product' || $domain === 'ecommerce') {
            return 'premium product photography';
        }
        return 'photorealistic commercial visual';
    }

    private function composition(string $domain): string {
        if ($domain === 'politics') {
            return 'magazine-cover hierarchy with headline space and message zones';
        }
        if ($domain === 'product') {
            return 'hero product centered composition';
        }
        return 'clear focal hierarchy';
    }

    private function palette(string $domain): string {
        if ($domain === 'politics') {
            return 'modern navy, clean blue and white Korean civic palette';
        }
        if ($domain === 'travel') {
            return 'bright natural travel colors';
        }
        return 'refined professional color grading';
    }

    /**
     * @param array<int, array{name:string,name_en:string}> $entities
     * @return string[]
     */
    private function required(string $domain, array $entities): array {
        $req = [];
        foreach ($entities as $e) {
            $req[] = $e['name_en'] !== '' ? $e['name_en'] : $e['name'];
        }
        if ($domain === 'politics') {
            $req[] = 'leadership posture';
            $req[] = 'civic campaign atmosphere';
            $req[] = 'space for Korean headline';
        }
        return array_values(array_unique($req));
    }

    /** @return string[] */
    private function forbidden_for_domain(string $domain): array {
        if (in_array($domain, ['product', 'ecommerce', 'fashion', 'food'], true)) {
            return ['political poster unrelated to product', 'random celebrity unless requested'];
        }
        return [
            'cosmetic bottle',
            'perfume bottle',
            'skincare product',
            'unrelated merchandise',
            'generic product photography',
            'empty studio product shot',
            'abstract product pedestal',
            'irrelevant commercial object',
            'hero product packshot',
        ];
    }

    private function intent_summary(string $domain, string $ad_subtype, string $primary): string {
        if ($ad_subtype === 'political_advertisement') {
            return 'Create a political public-campaign advertisement centered on: ' . $primary;
        }
        if ($ad_subtype !== '') {
            return 'Create a ' . str_replace('_', ' ', $ad_subtype) . ' centered on: ' . $primary;
        }
        return 'Create an image centered on: ' . $primary;
    }
}
