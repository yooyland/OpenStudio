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
            'brand_token'        => $this->extract_brand_token($raw),
            'beauty_mode'        => $this->beauty_mode_for($domain, $lower),
            'wants_product'      => $this->wants_product_for($domain),
            'wants_model'        => $this->wants_model_for($domain, $lower),
            'wants_political'    => $domain === 'politics',
            'art_direction_preset' => class_exists('YooY_Image_Art_Direction')
                ? YooY_Image_Art_Direction::resolve_preset([
                    'content_domain'   => $domain,
                    'raw_user_request' => $raw,
                    'primary_subject'  => $primary,
                    'brand_token'      => $this->extract_brand_token($raw),
                ])
                : '',
        ];

        if ($this->is_beauty_family($domain)) {
            $intent['tone'] = $this->beauty_tone($lower, $hint);
            $intent['k_culture'] = 'kbeauty';
            $intent['meaning'] = 'product efficacy + aspirational lifestyle';
        }

        return $intent;
    }

    public static function is_beauty_family(string $domain): bool {
        return in_array($domain, [
            'beauty',
            'beauty_product_packshot',
            'beauty_model_campaign',
            'beauty_poster_editorial',
        ], true);
    }

    private function wants_product_for(string $domain): bool {
        return in_array($domain, [
            'product', 'ecommerce', 'fashion', 'food', 'beauty',
            'beauty_product_packshot', 'beauty_model_campaign', 'beauty_poster_editorial',
        ], true);
    }

    private function wants_model_for(string $domain, string $lower): bool {
        if (in_array($domain, ['beauty_model_campaign', 'beauty_poster_editorial'], true)) {
            return true;
        }
        if ($domain === 'beauty_product_packshot') {
            return false;
        }
        return (bool) preg_match('/모델|인물|여성|여자|남자|model|woman|man/u', $lower);
    }

    private function beauty_mode_for(string $domain, string $lower): string {
        if ($domain === 'beauty_product_packshot') {
            return 'product_only';
        }
        if ($domain === 'beauty_poster_editorial') {
            return 'poster_editorial';
        }
        if ($domain === 'beauty_model_campaign' || $domain === 'beauty') {
            return 'model_campaign';
        }
        unset($lower);
        return '';
    }

    private function beauty_tone(string $lower, array $hint): string {
        if (!empty($hint['tone'])) {
            return sanitize_text_field((string) $hint['tone']);
        }
        if (preg_match('/프레시|fresh|상쾌/u', $lower)) {
            return 'fresh, radiant, premium, clean';
        }
        return 'refined, elegant, radiant, premium, calm, clean';
    }

    /**
     * Short explicit brand token (e.g. VVR) — never invent brands.
     */
    private function extract_brand_token(string $raw): string {
        if (preg_match('/[\'"“‘]([A-Za-z0-9][A-Za-z0-9.&-]{1,11})[\'"”’]/u', $raw, $m)) {
            return sanitize_text_field($m[1]);
        }
        if (preg_match('/브랜드\s*[\'"“‘]?([A-Za-z0-9][A-Za-z0-9.&-]{1,11})[\'"”’]?/u', $raw, $m)) {
            return sanitize_text_field($m[1]);
        }
        if (preg_match('/\b([A-Z]{2,8})\b/u', $raw, $m)
            && !in_array(strtoupper($m[1]), ['AI', 'UI', 'UX', 'HD', 'SNS', 'TV', 'AD'], true)) {
            return sanitize_text_field($m[1]);
        }
        return '';
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

        // Storybook / fantasy adventure before lifestyle "가족" or travel "여행".
        if ($this->looks_like_fantasy($lower) && (
            preg_match('/세련|고급|현대|프리미엄|일러스트|그림책\s*표지|premium|refined|sophisticated/u', $lower)
            || !$this->looks_like_storybook($lower)
        )) {
            return 'fantasy';
        }
        if ($this->looks_like_storybook($lower)) {
            return 'storybook';
        }
        if ($this->looks_like_fantasy($lower)) {
            return 'fantasy';
        }
        // Human portrait / 화보 before brand keyword hijack.
        if (preg_match('/화보|초상|portrait|인물/u', $lower)
            || (preg_match('/여성|남성|여자|남자|woman|man/u', $lower)
                && preg_match('/세련|신뢰|프리미엄|브랜드|화보|editorial/u', $lower)
                && !preg_match('/제품|크림|아파트|단지|화장품/u', $lower))) {
            return 'portrait';
        }

        $rules = [
            'politics'      => ['정치', '이재명', '대통령', '선거', '정책', '국회', '정당', '대선', '여야', 'political', 'president', 'election', 'policy'],
            'beauty'        => ['화장품', '스킨케어', '세럼', '향수', '뷰티', '안티에이징', 'cosmetic', 'skincare', 'beauty', 'serum', 'perfume', 'anti-aging', 'anti aging'],
            'lifestyle'     => ['부부', '커플', '라이프스타일', '일상', '행복한', '사람들', 'lifestyle', 'couple'],
            'architecture'  => ['조감도', '아파트', '건축', '단지', '외관', '건물', '빌딩', '타워', '주거단지', '분양', 'architectural', 'architecture', 'aerial view', "bird's eye", 'facade', 'residential complex', 'real estate visualization'],
            'product'       => ['제품', '상품', '병', '패키지', 'bottle', 'product', 'packshot', '제품컷'],
            'ecommerce'     => ['스마트스토어', '쿠팡', '이커머스', '상세페이지', 'ecommerce', 'coupang'],
            'cinematic'     => ['시네마틱', '영화적', 'cinematic', 'film still'],
            'illustration'  => ['일러스트', '삽화', 'illustration', 'illustrated'],
            'travel'        => ['여행', '관광', '제주', '휴가', 'tour', 'travel'],
            'corporate'     => ['회사 소개', '기업', '채용', 'corporate', 'recruit'],
            'education'     => ['교육', '학교', '강의', 'education'],
            'entertainment' => ['영화', '엔터', '드라마', 'entertainment', 'movie'],
            'food'          => ['음식', '맛집', '요리', 'food', 'restaurant'],
            'fashion'       => ['패션', '의류', 'fashion', 'apparel'],
            'portrait'      => ['인물 사진', '초상', 'portrait', '인물', '화보'],
            'editorial'     => ['매거진', 'editorial'],
            'social'        => ['사회 캠페인', '공익', 'social campaign', 'psa'],
            'brand'         => ['브랜드', 'brand identity', '로고'],
        ];

        foreach ($rules as $domain => $needles) {
            foreach ($needles as $n) {
                if ($n !== '' && mb_strpos($lower, mb_strtolower($n)) !== false) {
                    // Human family in apartment → lifestyle, not architecture-only.
                    if ($domain === 'architecture' && preg_match('/부부|커플|사람들|이야기하는|couple|family/u', $lower)) {
                        return 'lifestyle';
                    }
                    // Flat "beauty" expands into campaign / packshot / poster subtypes.
                    if ($domain === 'beauty') {
                        return $this->classify_beauty_subtype($lower);
                    }
                    // "크림" alone without beauty words stays product; with beauty+ad → beauty.
                    if ($domain === 'product' && preg_match('/크림|cream/u', $lower)
                        && preg_match('/화장품|스킨케어|안티에이징|뷰티|cosmetic|skincare|beauty/u', $lower)) {
                        return $this->classify_beauty_subtype($lower);
                    }
                    return $domain;
                }
            }
        }

        if (preg_match('/광고|advert|campaign|캠페인|포스터|poster/u', $lower)) {
            if ($this->looks_like_architecture($lower)) {
                return 'architecture';
            }
            if (preg_match('/화장품|스킨케어|안티에이징|세럼|크림|뷰티|cosmetic|skincare|beauty|cream|serum/u', $lower)) {
                return $this->classify_beauty_subtype($lower);
            }
            if ($this->looks_like_product($lower)) {
                return 'product';
            }
            return 'brand';
        }
        return 'general';
    }

    /**
     * Beauty taxonomy:
     * - beauty_product_packshot — explicit product-only
     * - beauty_model_campaign — default for brand/ad/skincare campaign
     * - beauty_poster_editorial — advertising poster / editorial poster
     */
    private function classify_beauty_subtype(string $lower): string {
        if (preg_match('/제품만|누끼|상세페이지|제품\s*사진만|제품컷만|packshot|product\s*only|product-only|제품\s*단독/u', $lower)) {
            return 'beauty_product_packshot';
        }
        if (preg_match('/포스터|poster|에디토리얼|editorial|광고\s*포스터/u', $lower)) {
            return 'beauty_poster_editorial';
        }
        if (preg_match('/광고|캠페인|브랜드|advert|campaign|brand|모델|인물|피부|model|woman|여성/u', $lower)) {
            return 'beauty_model_campaign';
        }
        // Skincare / cosmetics without packshot-only language → campaign default.
        if (preg_match('/안티에이징|스킨케어|화장품|세럼|cosmetic|skincare|beauty|serum/u', $lower)) {
            return 'beauty_model_campaign';
        }
        return 'beauty_product_packshot';
    }

    private function looks_like_architecture(string $lower): bool {
        return (bool) preg_match('/조감|아파트|건축|단지|건물|분양|architectural|apartment|real.?estate/u', $lower);
    }

    private function looks_like_product(string $lower): bool {
        return (bool) preg_match('/제품|화장품|스킨케어|크림|향수|상품|cosmetic|skincare|product|cream|perfume/u', $lower);
    }

    private function looks_like_storybook(string $lower): bool {
        if (class_exists('YooY_Image_Art_Direction')) {
            return YooY_Image_Art_Direction::looks_storybook($lower);
        }
        if (preg_match('/어린이|동화|그림책|아동|키즈|storybook|fairy.?tale|picture.?book/u', $lower)) {
            return true;
        }
        if (preg_match('/꿈|상상/u', $lower) && preg_match('/펭귄|고래|용|요정|마법|날아|하늘을|세계\s*여행/u', $lower)) {
            return true;
        }
        if (preg_match('/펭귄/u', $lower) && preg_match('/고래|하늘|날/u', $lower)) {
            return true;
        }
        return false;
    }

    private function looks_like_fantasy(string $lower): bool {
        if (class_exists('YooY_Image_Art_Direction')) {
            return YooY_Image_Art_Direction::looks_fantasy($lower);
        }
        return (bool) preg_match('/판타지|드래곤|유니콘|마법사|fantasy|dragon|unicorn|wizard|오로라|aurora/u', $lower);
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
            'beauty_product_packshot' => 'product_advertisement',
            'beauty_model_campaign'   => 'beauty_campaign_advertisement',
            'beauty_poster_editorial' => 'beauty_poster_advertisement',
            'beauty'    => 'beauty_campaign_advertisement',
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
        $cut = mb_substr(trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw), 0, 160);
        if (in_array($domain, ['lifestyle', 'architecture', 'product', 'brand', 'storybook', 'fantasy', 'beauty', 'beauty_product_packshot', 'beauty_model_campaign', 'beauty_poster_editorial', 'cinematic'], true) && $cut !== '') {
            // Prefer the full user request as subject for commercial / narrative domains —
            // place-only entity extraction (e.g. "Seoul") is too thin for art direction.
            return $cut;
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
        if ($domain === 'beauty_poster_editorial' || $ad_subtype === 'beauty_poster_advertisement') {
            return 'premium vertical beauty advertising poster';
        }
        if ($domain === 'beauty_model_campaign' || $ad_subtype === 'beauty_campaign_advertisement') {
            return 'luxury beauty model campaign key visual';
        }
        if ($ad_subtype === 'product_advertisement' || $domain === 'product' || $domain === 'ecommerce' || $domain === 'beauty_product_packshot' || $domain === 'beauty') {
            return 'premium product advertising photograph';
        }
        if ($domain === 'storybook') {
            return 'modern premium picture-book illustration';
        }
        if ($domain === 'fantasy') {
            return 'polished fantasy editorial illustration';
        }
        if ($domain === 'architecture') {
            if (preg_match('/조감|aerial|bird.?s.?eye|birdseye/u', $lower)) {
                return 'photorealistic architectural aerial visualization';
            }
            return 'photorealistic architectural visualization';
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
        if ($domain === 'beauty_poster_editorial') {
            return 'luxury beauty campaign poster — editorial advertising finish';
        }
        if ($domain === 'beauty_model_campaign') {
            return 'luxury beauty campaign photography with model and product';
        }
        if ($domain === 'beauty_product_packshot' || $domain === 'product' || $domain === 'ecommerce' || $domain === 'beauty') {
            return 'premium product photography';
        }
        if ($domain === 'storybook') {
            return 'modern cinematic storybook illustration';
        }
        if ($domain === 'fantasy') {
            return 'polished fantasy editorial illustration';
        }
        if ($domain === 'architecture') {
            return 'photorealistic architectural visualization, real-estate marketing grade';
        }
        if ($domain === 'lifestyle' || $domain === 'cinematic') {
            return 'editorial lifestyle campaign photography';
        }
        if ($domain === 'portrait') {
            return 'editorial commercial portrait';
        }
        if ($domain === 'brand') {
            return 'premium brand campaign key visual';
        }
        return 'professionally art-directed photograph';
    }

    private function composition(string $domain): string {
        if ($domain === 'politics') {
            return 'magazine-cover hierarchy with headline space and message zones';
        }
        if ($domain === 'beauty_poster_editorial') {
            return 'vertical advertising poster hierarchy — hero model/product balance with headline negative space';
        }
        if ($domain === 'beauty_model_campaign') {
            return 'balanced model-led campaign framing; product legible in hand or beside model; usable copy space';
        }
        if ($domain === 'product' || $domain === 'beauty_product_packshot' || $domain === 'beauty') {
            return 'hero product centered composition with elegant negative space';
        }
        if ($domain === 'storybook' || $domain === 'fantasy') {
            return 'cinematic storytelling wide frame with imaginative scale and atmospheric depth';
        }
        if ($domain === 'architecture') {
            return 'wide establishing aerial or elevated viewpoint with accurate building proportions';
        }
        if ($domain === 'lifestyle' || $domain === 'cinematic') {
            return 'editorial lifestyle framing with strong human focal point';
        }
        if ($domain === 'portrait') {
            return 'close to medium portrait with eyes as focal point';
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
        if ($domain === 'architecture') {
            return 'natural daylight materials, realistic façade colors';
        }
        if ($this->is_beauty_family($domain) || $domain === 'product') {
            return 'clean luminous beauty neutrals with soft champagne / pearl accents';
        }
        if ($domain === 'storybook' || $domain === 'fantasy') {
            return 'sophisticated child-friendly cinematic palette with luminous night accents';
        }
        if ($domain === 'lifestyle' || $domain === 'cinematic') {
            return 'warm natural lifestyle grading';
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
        if ($this->is_beauty_family($domain)) {
            if ($domain !== 'beauty_product_packshot') {
                $req[] = 'healthy attractive model with luminous skin';
                $req[] = 'skincare product clearly visible';
                $req[] = 'premium K-beauty campaign composition';
                $req[] = 'usable negative space for poster copy';
            } else {
                $req[] = 'hero skincare product with accurate packaging geometry';
            }
        }
        if ($domain === 'architecture') {
            $req[] = 'accurate building scale and proportions';
            $req[] = 'detailed façade materials';
            $req[] = 'realistic landscaping and site context';
        }
        return array_values(array_unique($req));
    }

    /** @return string[] */
    private function forbidden_for_domain(string $domain): array {
        if ($this->is_beauty_family($domain) || in_array($domain, ['product', 'ecommerce', 'fashion', 'food'], true)) {
            return [
                'political poster unrelated to product',
                'random celebrity unless requested',
                'invented long fake marketing paragraphs on packaging',
                'pharmacy bottle aesthetic',
                'cheap home-shopping mood',
                'anger / hostile facial expression',
            ];
        }
        if ($domain === 'storybook' || $domain === 'fantasy') {
            return [
                'dated cheap storybook look',
                'clip-art aesthetics',
                'flat mural decoration',
                'unrelated child observer in bedroom unless requested',
                'generic stock photo humans unless requested',
            ];
        }
        if ($domain === 'architecture') {
            return [
                'warped geometry',
                'distorted windows',
                'bent buildings',
                'floating structures',
                'melted façade',
                'unrelated people close-up unless requested',
                'cosmetic bottle',
                'perfume bottle',
                'cartoon architecture',
            ];
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
