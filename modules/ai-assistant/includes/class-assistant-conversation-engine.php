<?php
if (!defined('ABSPATH')) exit;

/**
 * Creative Partner Conversation Engine.
 *
 * Flow: listen → ask → plan → recommend Studio → (optional) final Prompt.
 * Does NOT emit Prompt on the first turn. No Credits. No Gallery chat save.
 */
final class YooY_Assistant_Conversation_Engine {

    /** @var YooY_Assistant_Prompt_Composer */
    private $composer;

    /** @var YooY_Assistant_Recommendation_Engine */
    private $recommendations;

    public function __construct(
        YooY_Assistant_Prompt_Composer $composer,
        YooY_Assistant_Recommendation_Engine $recommendations
    ) {
        $this->composer        = $composer;
        $this->recommendations = $recommendations;
    }

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     * @param array<int, mixed>    $history
     * @param array<string, mixed> $brief_in Client brief snapshot (optional).
     * @return array<string, mixed>
     */
    public function reply(string $message, array $context = [], array $history = [], array $brief_in = []): array {
        $message = trim($message);

        if ($message === '') {
            return $this->payload(
                '무엇을 만들고 싶으신가요? 원하는 내용을 편하게 말해 주세요. 제가 먼저 질문하며 작품을 함께 기획할게요.',
                'welcome',
                [],
                $this->default_actions(),
                null,
                $this->recommendations->cards($context),
                [
                    '목적 카드를 고르거나, 아래 큰 입력창에 아이디어를 적어 주세요.',
                    '광고', '유튜브', '쇼츠', '웹툰', '블로그', '음악', '번역',
                ],
                $this->empty_brief(),
                $context
            );
        }

        $brief = $this->merge_brief($brief_in, $history, $message, $context);
        $phase = $this->phase_for($brief, $message);

        if ($phase === 'clarify') {
            $q = $this->next_question($brief);
            $reply = $this->clarify_reply($brief, $q, $context);
            return $this->payload(
                $reply,
                'clarify',
                $brief['studios'] ?? [],
                $this->actions_from_studios($brief['studios'] ?? []),
                null,
                [],
                $q['quick_replies'] ?? [],
                $brief,
                $context
            );
        }

        if ($phase === 'plan') {
            $reply = $this->plan_reply($brief, $context);
            return $this->payload(
                $reply,
                'plan',
                $brief['studios'] ?? [],
                $this->actions_from_studios($brief['studios'] ?? []),
                null,
                [],
                ['이대로 진행', '프롬프트 만들어줘', '타깃 바꿔줘', '다른 Studio'],
                $brief,
                $context
            );
        }

        // ready — final prompt is secondary / opt-in
        $composed = null;
        $want_prompt = $this->wants_prompt($message) || ($brief['force_prompt'] ?? false);
        if ($want_prompt || $this->ready_enough($brief)) {
            $seed = $this->brief_to_seed($brief);
            $compose = $this->composer->compose($seed, $brief['primary_studio'] ?? null, $context);
            $composed = [
                'seed'              => $seed,
                'draft'             => $compose['composed'] ?? '',
                'fields'            => $compose['fields'] ?? [],
                'requires_approval' => true,
                'studio'            => $compose['studio'] ?? ($brief['primary_studio'] ?? 'image'),
                'secondary'         => true,
                'creative_brief'    => $compose['creative_brief'] ?? [],
                'intent_domain'     => $compose['intent_domain'] ?? '',
                'raw_user_request'  => $compose['raw_user_request'] ?? $seed,
                'prompt_version'    => $compose['prompt_version'] ?? 'spi-assistant-1',
            ];
        }

        $reply = $this->ready_reply($brief, $context, $composed !== null);
        return $this->payload(
            $reply,
            'ready',
            $brief['studios'] ?? [],
            $this->actions_from_studios($brief['studios'] ?? []),
            $composed,
            [],
            ['Studio로 만들기', '프롬프트만 보기', '질문 더 하기'],
            $brief,
            $context
        );
    }

    /**
     * @param array<string, mixed>              $brief
     * @param array<int, array<string, string>> $actions
     * @param array<string, mixed>|null         $composed
     * @param array<int, array<string, mixed>>  $cards
     * @param array<int, string>                $quick
     * @param array<string, mixed>              $context
     * @return array<string, mixed>
     */
    private function payload(
        string $reply,
        string $phase,
        array $studios,
        array $actions,
        ?array $composed,
        array $cards,
        array $quick,
        array $brief,
        array $context
    ): array {
        $korea = $this->korea_authority_note(($brief['goal'] ?? '') . ' ' . ($brief['notes'] ?? ''));
        return [
            'reply'            => $reply,
            'phase'            => $phase,
            'partner_mode'     => 'creative_partner',
            'studio_actions'   => $actions,
            'intent'           => $brief['purpose'] ?? 'general',
            'primary_studio'   => $brief['primary_studio'] ?? null,
            'recommended_studios' => $studios,
            'composed'         => $composed,
            'recommendations'  => $cards,
            'quick_replies'    => array_values(array_filter($quick)),
            'brief'            => $brief,
            'credits_charged'  => false,
            'korea_note'       => $korea,
            'prompt_policy'    => 'ask_first_prompt_secondary',
        ];
    }

    /** @return array<string, mixed> */
    private function empty_brief(): array {
        return [
            'purpose'         => '',
            'goal'            => '',
            'audience'        => '',
            'tone'            => '',
            'format'          => '',
            'notes'           => '',
            'primary_studio'  => null,
            'studios'         => [],
            'answered'        => [],
            'force_prompt'    => false,
            'turn'            => 0,
        ];
    }

    /**
     * @param array<string, mixed> $brief_in
     * @param array<int, mixed>    $history
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function merge_brief(array $brief_in, array $history, string $message, array $context): array {
        $brief = array_merge($this->empty_brief(), is_array($brief_in) ? $brief_in : []);
        $brief['turn'] = (int) ($brief['turn'] ?? 0) + 1;

        $purpose = $this->detect_purpose($message);
        if ($purpose !== '') {
            $brief['purpose'] = $purpose;
        } elseif ($brief['purpose'] === '') {
            $brief['purpose'] = $this->detect_purpose_from_history($history);
        }

        if ($brief['goal'] === '') {
            $brief['goal'] = $message;
        } else {
            $brief['notes'] = trim(($brief['notes'] ?? '') . ' ' . $message);
        }

        $this->absorb_answer($brief, $message);
        $brief['studios'] = $this->studios_for_purpose((string) $brief['purpose']);
        $brief['primary_studio'] = $brief['studios'][0] ?? 'image';

        if ($this->wants_prompt($message)) {
            $brief['force_prompt'] = true;
        }
        if ($this->wants_proceed($message)) {
            $brief['answered']['proceed'] = true;
        }

        if (($context['mode'] ?? '') === 'project' && !empty($context['project']['title'])) {
            $brief['project_title'] = (string) $context['project']['title'];
        }

        return $brief;
    }

    private function detect_purpose(string $message): string {
        $lower = mb_strtolower($message);
        $map = [
            'ad'        => ['광고', '캠페인', '프로모션', '배너'],
            'youtube'   => ['유튜브', 'youtube', '롱폼', '채널'],
            'shorts'    => ['쇼츠', 'shorts', '릴스', '틱톡', '숏폼'],
            'webtoon'   => ['웹툰', '만화', '컷툰'],
            'blog'      => ['블로그', '칼럼', '포스팅', '글 쓰'],
            'music'     => ['음악', 'bgm', '사운드트랙', '비트'],
            'translate' => ['번역', 'translate', '영어로', '일본어', '중국어'],
            'avatar'    => ['아바타', '디지털 휴먼'],
            'voice'     => ['나레이션', '성우', '보이스', '더빙'],
        ];
        foreach ($map as $id => $needles) {
            foreach ($needles as $n) {
                if ($n !== '' && mb_strpos($lower, $n) !== false) {
                    return $id;
                }
            }
        }
        return '';
    }

    /** @param array<int, mixed> $history */
    private function detect_purpose_from_history(array $history): string {
        foreach ($history as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = (string) ($row['content'] ?? $row['text'] ?? '');
            $p = $this->detect_purpose($text);
            if ($p !== '') {
                return $p;
            }
        }
        return 'general';
    }

    /** @param array<string, mixed> $brief */
    private function absorb_answer(array &$brief, string $message): void {
        $lower = mb_strtolower($message);

        if ($brief['audience'] === '' && $this->contains_any($lower, ['대', '타깃', '고객', '시청자', '전연령', '청소년', '직장인', '엄마', '부모'])) {
            $brief['audience'] = $message;
            $brief['answered']['audience'] = true;
        }
        if ($brief['tone'] === '' && $this->contains_any($lower, ['톤', '감성', '진지', '유머', '밝은', '프리미엄', '친근', '시네마틱', '귀여운'])) {
            $brief['tone'] = $message;
            $brief['answered']['tone'] = true;
        }
        if ($brief['format'] === '' && $this->contains_any($lower, ['초', '분', '세로', '가로', '배너', '썸네일', '카드뉴스', '9:16', '16:9', '숏폼', '롱폼'])) {
            $brief['format'] = $message;
            $brief['answered']['format'] = true;
        }

        // Short answers to previous question slots by turn order
        if (mb_strlen($message) <= 40) {
            if (empty($brief['answered']['audience']) && ($brief['turn'] ?? 0) >= 2 && $brief['audience'] === '') {
                $brief['audience'] = $message;
                $brief['answered']['audience'] = true;
            } elseif (empty($brief['answered']['tone']) && ($brief['turn'] ?? 0) >= 3 && $brief['tone'] === '') {
                $brief['tone'] = $message;
                $brief['answered']['tone'] = true;
            } elseif (empty($brief['answered']['format']) && ($brief['turn'] ?? 0) >= 4 && $brief['format'] === '') {
                $brief['format'] = $message;
                $brief['answered']['format'] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $brief
     */
    private function phase_for(array $brief, string $message): string {
        if ($this->wants_prompt($message) || !empty($brief['answered']['proceed']) || $this->wants_proceed($message)) {
            return 'ready';
        }
        if ($this->ready_enough($brief) && ($brief['turn'] ?? 0) >= 3) {
            return 'plan';
        }
        if (($brief['turn'] ?? 0) >= 4 && $this->filled_count($brief) >= 2) {
            return 'plan';
        }
        if (($brief['purpose'] ?? '') === '' && ($brief['turn'] ?? 0) === 1) {
            return 'clarify';
        }
        if ($this->filled_count($brief) < 2) {
            return 'clarify';
        }
        if (($brief['turn'] ?? 0) >= 3) {
            return 'plan';
        }
        return 'clarify';
    }

    /** @param array<string, mixed> $brief */
    private function filled_count(array $brief): int {
        $n = 0;
        foreach (['purpose', 'goal', 'audience', 'tone', 'format'] as $k) {
            if (!empty($brief[$k])) {
                $n++;
            }
        }
        return $n;
    }

    /** @param array<string, mixed> $brief */
    private function ready_enough(array $brief): bool {
        return $this->filled_count($brief) >= 3 || !empty($brief['answered']['proceed']);
    }

    /**
     * @param array<string, mixed> $brief
     * @return array{key:string,text:string,quick_replies:string[]}
     */
    private function next_question(array $brief): array {
        $purpose = (string) ($brief['purpose'] ?? 'general');

        if (empty($brief['purpose']) || $purpose === 'general') {
            return [
                'key' => 'purpose',
                'text' => '좋아요. 어떤 목적의 작품인가요?',
                'quick_replies' => ['광고 만들기', '유튜브 영상', '쇼츠', '웹툰', '블로그', '음악', '번역'],
            ];
        }
        if (empty($brief['answered']['audience']) && $brief['audience'] === '') {
            return [
                'key' => 'audience',
                'text' => $this->audience_question($purpose),
                'quick_replies' => ['20대', '30–40대', '전연령', '직장인', '부모님'],
            ];
        }
        if (empty($brief['answered']['tone']) && $brief['tone'] === '') {
            return [
                'key' => 'tone',
                'text' => $this->tone_question($purpose),
                'quick_replies' => ['밝고 친근하게', '프리미엄', '감성적', '유머러스', '진지하고 신뢰감'],
            ];
        }
        if (empty($brief['answered']['format']) && $brief['format'] === '') {
            return [
                'key' => 'format',
                'text' => $this->format_question($purpose),
                'quick_replies' => $this->format_quick($purpose),
            ];
        }
        return [
            'key' => 'extra',
            'text' => '더 넣고 싶은 디테일이 있나요? (제품명, 핵심 메시지, 금기 사항 등) 없으면 「이대로 진행」이라고 해주세요.',
            'quick_replies' => ['이대로 진행', '프롬프트 만들어줘'],
        ];
    }

    private function audience_question(string $purpose): string {
        $map = [
            'ad'        => '광고 타깃은 누구인가요?',
            'youtube'   => '주요 시청자는 누구인가요?',
            'shorts'    => '쇼츠를 볼 사람은 누구인가요?',
            'webtoon'   => '웹툰 독자층은 누구인가요?',
            'blog'      => '블로그 독자는 누구인가요?',
            'music'     => '이 음악을 들을 상황·청자는?',
            'translate' => '번역문을 읽을 대상은 누구인가요?',
        ];
        return $map[$purpose] ?? '이 작품을 볼 사람은 누구인가요?';
    }

    private function tone_question(string $purpose): string {
        $map = [
            'ad'        => '원하는 광고 톤은요?',
            'youtube'   => '채널 톤은 어떤 느낌이면 좋을까요?',
            'shorts'    => '쇼츠 분위기는요?',
            'webtoon'   => '웹툰 장르·분위기는요?',
            'blog'      => '글 톤은 어떤 스타일이면 좋을까요?',
            'music'     => '음악 분위기는요?',
            'translate' => '번역 톤은 어떤 스타일로 할까요?',
        ];
        return $map[$purpose] ?? '원하는 톤·분위기는요?';
    }

    private function format_question(string $purpose): string {
        $map = [
            'ad'        => '배너, 영상, 카피 중 무엇이 우선인가요?',
            'youtube'   => '대략 길이와 구성은요? (예: 3분 소개, 10분 리뷰)',
            'shorts'    => '길이·비율은요? (예: 15초 세로)',
            'webtoon'   => '몇 컷·어떤 장면부터 시작할까요?',
            'blog'      => '글 분량과 핵심 키워드는요?',
            'music'     => '길이·용도는요? (예: 30초 BGM, 풀곡)',
            'translate' => '원문 언어와 목표 언어는요?',
        ];
        return $map[$purpose] ?? '결과물 형식은 어떻게 할까요?';
    }

    /** @return string[] */
    private function format_quick(string $purpose): array {
        $map = [
            'ad'        => ['이미지 배너', '15초 영상', '카피 먼저'],
            'youtube'   => ['1–3분', '5–10분', '채널 인트로'],
            'shorts'    => ['15초', '30초', '60초'],
            'webtoon'   => ['1화 표지', '3컷 훅', '캐릭터 시트'],
            'blog'      => ['800자', '1500자', '리스트형'],
            'music'     => ['30초 BGM', '1분 루프', '풀 트랙'],
            'translate' => ['한→영', '영→한', '한→일'],
        ];
        return $map[$purpose] ?? ['이미지', '영상', '글'];
    }

    /**
     * @param array<string, mixed> $brief
     * @param array{key:string,text:string,quick_replies:string[]} $q
     * @param array<string, mixed> $context
     */
    private function clarify_reply(array $brief, array $q, array $context): string {
        $lines = [];
        $goal = (string) ($brief['goal'] ?? '');
        $turn = (int) ($brief['turn'] ?? 0);

        if ($turn <= 1) {
            $lines[] = '좋아요. 「' . $goal . '」로 이해했어요.';
            $lines[] = '바로 프롬프트를 만들지 않고, 몇 가지를 여쭤볼게요.';
            $lines[] = '';
            // Multi-question opener for first turn (ChatGPT-like)
            $purpose = (string) ($brief['purpose'] ?? '');
            if ($purpose !== '' && $purpose !== 'general') {
                $lines[] = '- ' . $this->audience_question($purpose);
                $lines[] = '- ' . $this->tone_question($purpose);
                $lines[] = '- ' . $this->format_question($purpose);
                $lines[] = '- 강조하고 싶은 핵심 메시지나 장소가 있나요?';
            } else {
                $lines[] = '- ' . $q['text'];
            }
        } else {
            $lines[] = '좋아요, 반영했습니다.';
            $lines[] = '';
            $lines[] = $q['text'];
        }

        if (($context['mode'] ?? '') === 'project' && !empty($brief['project_title'])) {
            array_unshift($lines, 'Project「' . $brief['project_title'] . '」컨텍스트로 이어갈게요.');
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $context
     */
    private function plan_reply(array $brief, array $context): string {
        $purpose_label = $this->purpose_label((string) ($brief['purpose'] ?? ''));
        $studios = $brief['studios'] ?? [];
        $studio_labels = [];
        foreach ($studios as $s) {
            $studio_labels[] = $this->studio_catalog()[$s]['label'] ?? $s;
        }

        $lines = [];
        $lines[] = '좋습니다. 지금까지 들은 내용으로 다음 작업 구성을 추천합니다.';
        $lines[] = '';
        foreach ($studio_labels as $label) {
            $lines[] = '- ' . $label;
        }
        $lines[] = '';
        $lines[] = '목적: ' . ($purpose_label ?: '자유 기획');
        if (!empty($brief['goal'])) {
            $lines[] = '하고 싶은 것: ' . $brief['goal'];
        }
        if (!empty($brief['audience'])) {
            $lines[] = '타깃: ' . $brief['audience'];
        }
        if (!empty($brief['tone'])) {
            $lines[] = '톤: ' . $brief['tone'];
        }
        if (!empty($brief['format'])) {
            $lines[] = '형식: ' . $brief['format'];
        }
        $lines[] = '';
        $lines[] = '아래 Studio Action을 눌러 기존 Studio로 이동하세요. 자동 생성하지 않습니다.';
        $lines[] = '프롬프트가 필요하면 「프롬프트 만들어줘」라고 말해 주세요.';
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $brief
     * @param array<string, mixed> $context
     */
    private function ready_reply(array $brief, array $context, bool $has_prompt): string {
        $primary = $this->studio_catalog()[$brief['primary_studio'] ?? 'image']['label'] ?? 'Studio';
        $lines = [];
        $lines[] = '준비됐어요. 이제 Creator Workflow로 이어갈 수 있습니다.';
        $lines[] = '추천 시작점: ' . $primary;
        $lines[] = '오른쪽(또는 아래) Studio Action을 누르면 기존 Studio가 열립니다. Assistant는 실행을 대신하지 않습니다.';
        if ($has_prompt) {
            $lines[] = '아래에 최종 Prompt 초안을 보조로 붙여 두었습니다. 승인 후에만 Studio로 전달됩니다.';
        } else {
            $lines[] = '프롬프트 없이도 Studio에서 바로 시작할 수 있고, 「프롬프트 만들어줘」라고 하면 초안을 열어 드릴게요.';
        }
        return implode("\n\n", $lines);
    }

    /** @param array<string, mixed> $brief */
    private function brief_to_seed(array $brief): string {
        $parts = array_filter([
            $brief['goal'] ?? '',
            !empty($brief['purpose']) ? '목적:' . $this->purpose_label((string) $brief['purpose']) : '',
            !empty($brief['audience']) ? '타깃:' . $brief['audience'] : '',
            !empty($brief['tone']) ? '톤:' . $brief['tone'] : '',
            !empty($brief['format']) ? '형식:' . $brief['format'] : '',
            !empty($brief['notes']) ? '메모:' . $brief['notes'] : '',
        ]);
        return implode(' · ', $parts);
    }

    private function purpose_label(string $purpose): string {
        $map = [
            'ad' => '광고', 'youtube' => '유튜브 영상', 'shorts' => '쇼츠',
            'webtoon' => '웹툰', 'blog' => '블로그', 'music' => '음악',
            'translate' => '번역', 'avatar' => '아바타', 'voice' => '보이스',
            'project' => '프로젝트 이어가기', 'general' => '자유 기획',
        ];
        return $map[$purpose] ?? $purpose;
    }

    /** @return string[] */
    private function studios_for_purpose(string $purpose): array {
        $map = [
            'ad'        => ['image', 'video', 'writing'],
            'youtube'   => ['video', 'writing', 'music', 'voice'],
            'shorts'    => ['video', 'music', 'writing'],
            'webtoon'   => ['image', 'writing'],
            'blog'      => ['writing', 'image'],
            'music'     => ['music', 'voice'],
            'translate' => ['translator', 'writing'],
            'avatar'    => ['avatar', 'voice', 'video'],
            'voice'     => ['voice', 'music'],
            'project'   => ['image', 'video', 'writing'],
            'general'   => ['image', 'video', 'writing'],
        ];
        return $map[$purpose] ?? ['image', 'video', 'writing'];
    }

    private function wants_prompt(string $message): bool {
        return $this->contains_any(mb_strtolower($message), ['프롬프트', 'prompt', '초안 만들어', '프롬프트 만들']);
    }

    private function wants_proceed(string $message): bool {
        return $this->contains_any(mb_strtolower($message), ['이대로', '진행', '시작', '만들어줘', 'studio로', '충분']);
    }

    /**
     * @param string[] $studios
     * @return array<int, array<string, string>>
     */
    private function actions_from_studios(array $studios): array {
        $catalog = $this->studio_catalog();
        $actions = [];
        foreach ($studios as $id) {
            if (isset($catalog[$id])) {
                $actions[] = $catalog[$id];
            }
        }
        if (!$actions) {
            return $this->default_actions();
        }
        return $actions;
    }

    /** @return array<string, array<string, string>> */
    private function studio_catalog(): array {
        return [
            'image'      => ['id' => 'image', 'route' => 'image', 'label' => '이미지 만들기', 'module' => 'image-studio'],
            'video'      => ['id' => 'video', 'route' => 'video', 'label' => '영상 만들기', 'module' => 'video-studio'],
            'writing'    => ['id' => 'writing', 'route' => 'writing', 'label' => '글쓰기', 'module' => 'writing'],
            'translator' => ['id' => 'translator', 'route' => 'translator', 'label' => '번역하기', 'module' => 'translator-studio'],
            'music'      => ['id' => 'music', 'route' => 'music', 'label' => '음악 만들기', 'module' => 'music-studio'],
            'voice'      => ['id' => 'voice', 'route' => 'voice', 'label' => '나레이션', 'module' => 'voice-studio'],
            'avatar'     => ['id' => 'avatar', 'route' => 'avatar', 'label' => '아바타', 'module' => 'avatar-studio'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function default_actions(): array {
        $c = $this->studio_catalog();
        return [$c['image'], $c['video'], $c['writing'], $c['translator'], $c['music']];
    }

    /** @param string[] $needles */
    private function contains_any(string $haystack, array $needles): bool {
        foreach ($needles as $n) {
            if ($n !== '' && mb_strpos($haystack, $n) !== false) {
                return true;
            }
        }
        return false;
    }

    private function korea_authority_note(string $message): ?string {
        $needles = ['대통령', '대통령실', '정부', '법령', '통계청', '공공기관', '국가기관', '대한민국'];
        foreach ($needles as $n) {
            if (mb_strpos($message, $n) !== false) {
                return '대한민국 관련 내용은 내부 Source Authority(대통령실 → 정부부처 → 공공기관 → 국가기관 → 그 외)를 따릅니다.';
            }
        }
        return null;
    }
}
