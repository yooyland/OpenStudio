<?php
if (!defined('ABSPATH')) exit;

/**
 * Prompt Composer — expands short user input. User must approve before Studio handoff.
 */
final class YooY_Assistant_Prompt_Composer {

    /**
     * @param string               $seed
     * @param string|null          $studio
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function compose(string $seed, ?string $studio = null, array $context = []): array {
        $seed = trim($seed);
        if ($seed === '') {
            return [
                'ok'            => false,
                'needs_input'   => true,
                'message'       => '보완할 한 줄 아이디어를 입력해 주세요.',
                'composed'      => '',
                'fields'        => [],
                'requires_approval' => true,
            ];
        }

        $studio = $studio ? sanitize_text_field($studio) : $this->infer_studio($seed);
        $fields = $this->expand_fields($seed, $studio, $context);
        $composed = $this->join_prompt($seed, $fields, $studio, $context);
        $brief = $this->build_creative_brief($seed, $studio, $fields, $context);

        return [
            'ok'                => true,
            'needs_input'       => false,
            'seed'              => $seed,
            'studio'            => $studio,
            'fields'            => $fields,
            'composed'          => $composed,
            'creative_brief'    => $brief,
            'intent_domain'     => $brief['content_domain'] ?? 'general',
            'raw_user_request'  => $seed,
            'prompt_version'    => 'spi-assistant-1',
            'requires_approval' => true,
            'note'              => '승인 후에만 Studio로 전달됩니다. 자동 생성하지 않습니다.',
        ];
    }

    /**
     * Structured brief for Studio handoff — primary subject over ad templates.
     *
     * @param array<string, string> $fields
     * @param array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function build_creative_brief(string $seed, string $studio, array $fields, array $context): array {
        $lower = mb_strtolower($seed);
        $is_politics = $this->contains_any($lower, ['정치', '이재명', '대통령', '선거', '정책', '국회', '정당', '대선']);
        $is_product = $this->contains_any($lower, ['향수', '화장품', '스킨케어', '제품', '상품', 'perfume', 'cosmetic']);
        $is_travel = $this->contains_any($lower, ['여행', '제주', '휴가', '관광']);
        $is_corp = $this->contains_any($lower, ['회사', '기업', '소개', '채용']);
        $is_ad = $this->contains_any($lower, ['광고', '캠페인', '프로모션']);

        $domain = 'general';
        $ad_subtype = '';
        if ($is_politics) {
            $domain = 'politics';
            $ad_subtype = $is_ad ? 'political_advertisement' : 'political_editorial';
        } elseif ($is_product) {
            $domain = 'product';
            $ad_subtype = 'product_advertisement';
        } elseif ($is_travel) {
            $domain = 'travel';
            $ad_subtype = $is_ad ? 'tourism_advertisement' : '';
        } elseif ($is_corp) {
            $domain = 'corporate';
            $ad_subtype = $is_ad ? 'corporate_advertisement' : '';
        } elseif ($is_ad) {
            $domain = 'brand';
            $ad_subtype = 'brand_advertisement';
        }

        $format = 'photorealistic image';
        if ($domain === 'politics') {
            $format = 'premium Korean political editorial campaign poster';
        } elseif ($domain === 'product') {
            $format = 'premium product advertising photograph';
        } elseif ($domain === 'travel') {
            $format = 'tourism campaign visual';
        } elseif ($domain === 'corporate') {
            $format = 'corporate introduction visual / thumbnail';
        }

        return [
            'primary_subject'    => mb_substr($seed, 0, 200),
            'core_message'       => 'Communicate the user request faithfully: ' . mb_substr($seed, 0, 160),
            'audience'           => $fields['대상'] ?? '',
            'medium'             => $format,
            'output_format'      => $format,
            'tone'               => $fields['톤'] ?? '',
            'content_domain'     => $domain,
            'intent_domain'      => $domain,
            'ad_subtype'         => $ad_subtype,
            'color_palette'      => $fields['색감'] ?? '',
            'composition'        => $fields['영상/카메라'] ?? '',
            'forbidden_elements' => $domain === 'politics' || (!$is_product && $is_ad)
                ? ['cosmetic bottle', 'perfume', 'generic product photography', 'unrelated merchandise']
                : [],
            'wants_political'    => $domain === 'politics',
            'wants_product'      => $domain === 'product',
            'raw_user_request'   => $seed,
            'project_context'    => is_array($context['project'] ?? null) ? $context['project'] : [],
            'studio'             => $studio,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private function expand_fields(string $seed, string $studio, array $context): array {
        $lower = mb_strtolower($seed);
        $is_ad = $this->contains_any($lower, ['광고', '캠페인', '프로모션', '마케팅']);
        $is_sns = $this->contains_any($lower, ['쇼츠', '릴스', '인스타', '틱톡', 'sns', '유튜브']);
        $is_travel = $this->contains_any($lower, ['여행', '제주', '휴가', '관광']);
        $is_corp = $this->contains_any($lower, ['회사', '기업', '소개', '채용', '브랜딩']);
        $is_politics = $this->contains_any($lower, ['정치', '이재명', '대통령', '선거', '정책', '국회', '정당', '대선']);
        $is_product = $this->contains_any($lower, ['향수', '화장품', '스킨케어', '제품', '상품', 'perfume', 'cosmetic']);

        $audience = '20–40대 한국 크리에이터·소비자';
        if ($is_politics) {
            $audience = '한국 정치 메시지에 관심 있는 대중';
        } elseif ($is_corp) {
            $audience = '잠재 고객·채용 후보자·파트너사';
        } elseif ($is_travel) {
            $audience = '국내 여행·휴가 계획 중인 2030';
        } elseif ($is_ad && $is_product) {
            $audience = '구매 전환을 목표로 하는 타깃 고객';
        } elseif ($is_ad) {
            $audience = '캠페인 메시지를 받아들이는 일반 대중';
        }

        $tone = '친근하고 명확한 한국어 톤';
        if ($is_politics) {
            $tone = '임팩트 있고 신뢰감 있는 프리미엄';
        } elseif ($is_corp) {
            $tone = '전문적이면서 따뜻한 브랜드 톤';
        } elseif ($is_ad) {
            $tone = '임팩트 있는 광고 카피 톤';
        }

        $palette = '밝고 깨끗한 상업용 색감';
        if ($is_politics) {
            $palette = '모던 네이비·클린 블루·화이트 시빅 팔레트';
        } elseif ($is_travel) {
            $palette = '시원한 블루·시안, 자연광, 청량한 여름 감성';
        } elseif ($this->contains_any($lower, ['가을', '감성', '영화'])) {
            $palette = '따뜻한 앰버·시네마틱 대비';
        }

        $camera = '아이레벨, 안정된 구도';
        if ($studio === 'video' || $is_sns) {
            $camera = '세로 9:16, 다이내믹 컷, 첫 3초 훅 프레임';
        } elseif ($is_politics) {
            $camera = '에디토리얼 미디엄샷, 매거진 커버 구도, 한글 헤드라인 여백';
        } elseif ($studio === 'image' && $is_product) {
            $camera = '제품 중심 클로즈업 또는 라이프스타일 와이드';
        } elseif ($studio === 'image') {
            $camera = '장면·인물 중심 구도, 카피 여백 확보';
        }

        $sns = $is_sns
            ? '유튜브 쇼츠·인스타 릴스·틱톡 공통 세로 포맷'
            : '웹·소셜·캠페인 겸용';

        $ad = '스토리·분위기 중심, 과도한 세일즈 톤 지양';
        if ($is_politics && $is_ad) {
            $ad = '정치·공공 캠페인 포스터, 제품 광고 템플릿 사용 금지';
        } elseif ($is_ad && $is_product) {
            $ad = '혜택·CTA를 짧게 강조, 브랜드 메시지 1줄';
        } elseif ($is_ad) {
            $ad = '캠페인 메시지 1줄, 무관한 제품 촬영 금지';
        }

        $project_note = '';
        if (($context['mode'] ?? '') === 'project' && !empty($context['project']['title'])) {
            $project_note = '프로젝트 "' . $context['project']['title'] . '" 톤·비주얼 일관성 유지';
        }

        $fields = [
            '대상'       => $audience,
            '톤'         => $tone,
            '색감'       => $palette,
            '영상/카메라' => $camera,
            'SNS'        => $sns,
            '광고'       => $ad,
        ];

        if ($studio === 'writing' || $studio === 'translator') {
            $fields['문체'] = '한국어 우선, 읽기 쉬운 단락, SEO 키워드 자연 삽입';
        }
        if ($studio === 'music' || $studio === 'voice') {
            $fields['사운드'] = '한국 대중 감성, 과도한 저음 없이 명확한 믹스';
        }
        if ($studio === 'avatar') {
            $fields['아바타'] = '자연스러운 표정, 한국어 립싱크 여유, 프레젠테이션 구도';
        }
        if ($project_note !== '') {
            $fields['프로젝트'] = $project_note;
        }

        return $fields;
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, mixed>  $context
     */
    private function join_prompt(string $seed, array $fields, string $studio, array $context): string {
        $parts = [$seed];
        foreach ($fields as $label => $value) {
            $parts[] = $label . ': ' . $value;
        }
        $parts[] = '스튜디오: ' . $studio;
        $parts[] = '한국 크리에이터 OS 품질, 상업용으로 바로 쓸 수 있는 완성도';
        return implode(' · ', $parts);
    }

    private function infer_studio(string $seed): string {
        $lower = mb_strtolower($seed);
        if ($this->contains_any($lower, ['번역', 'translate', '영어', '일본어', '중국어'])) {
            return 'translator';
        }
        if ($this->contains_any($lower, ['영상', '비디오', '쇼츠', '릴스', '유튜브', '영화', '필름'])) {
            return 'video';
        }
        if ($this->contains_any($lower, ['글', '카피', '스크립트', '블로그', '대본', '소개서'])) {
            return 'writing';
        }
        if ($this->contains_any($lower, ['음악', 'bgm', '사운드트랙', '비트'])) {
            return 'music';
        }
        if ($this->contains_any($lower, ['보이스', '성우', '나레이션', '더빙'])) {
            return 'voice';
        }
        if ($this->contains_any($lower, ['아바타', '디지털 휴먼', '캐릭터 발표'])) {
            return 'avatar';
        }
        return 'image';
    }

    /**
     * @param string[] $needles
     */
    private function contains_any(string $haystack, array $needles): bool {
        foreach ($needles as $n) {
            if ($n !== '' && mb_strpos($haystack, $n) !== false) {
                return true;
            }
        }
        return false;
    }
}
