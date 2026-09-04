<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase 9 — Assistant command/action resolver.
 * Prepares actions for existing Studios/Gallery/Credits. Never auto-generates or spends Credits.
 */
final class YooY_Assistant_Action_Resolver {

    /**
     * @param string               $message
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    public function resolve(string $message, array $context = array()) {
        $message = trim($message);
        if ($message === '') {
            return null;
        }

        $lower = function_exists('mb_strtolower') ? mb_strtolower($message) : strtolower($message);

        // Credits / plan questions (answer, not creation).
        if ($this->matches_any($lower, array('크레딧 얼마나', '크레딧 남', '잔액', 'credit balance', '크레딧 조회'))) {
            return $this->action_payload(
                '크레딧 잔액을 확인할게요.',
                'info',
                array(
                    'type'  => 'show_credits',
                    'risk'  => 'low',
                    'label' => '크레딧 보기',
                ),
                array('크레딧 보기', '플랜 보기')
            );
        }
        if ($this->matches_any($lower, array('내 플랜', '플랜 뭐', '어떤 플랜', 'my plan'))) {
            return $this->action_payload(
                '현재 플랜을 확인할게요.',
                'info',
                array(
                    'type'  => 'show_plan',
                    'risk'  => 'low',
                    'label' => '플랜 보기',
                ),
                array('플랜 보기', '크레딧 보기')
            );
        }

        // Destructive.
        if ($this->matches_any($lower, array('삭제해', '지워줘', '삭제 해', '작품 삭제', 'delete'))) {
            return $this->action_payload(
                '삭제는 되돌리기 어려울 수 있어요. 정말 진행할까요?',
                'confirm',
                array(
                    'type'  => 'confirm_delete',
                    'risk'  => 'high',
                    'label' => '삭제 확인',
                ),
                array('취소', 'Gallery에서 확인')
            );
        }

        // Publish — preview only.
        if ($this->matches_any($lower, array('community에 공개', '커뮤니티에 공개', '공개해줘', 'marketplace에', '마켓플레이스', '공유해줘'))) {
            $target = (strpos($lower, 'market') !== false || strpos($lower, '마켓') !== false) ? 'marketplace' : 'community';
            return $this->action_payload(
                '공개하기 전에 미리보기로 확인할게요. 바로 공개하지는 않습니다.',
                'confirm',
                array(
                    'type'   => 'prepare_publish',
                    'risk'   => 'medium',
                    'target' => $target,
                    'label'  => $target === 'marketplace' ? 'Marketplace 준비' : 'Community 공유 준비',
                ),
                array('공개 준비', '취소')
            );
        }

        // Recent works / gallery.
        if ($this->matches_any($lower, array('최근 작업', '최근 작품', '최근 이미지', '내 작품 보여', '갤러리 보여', '오늘 만든'))) {
            $type = '';
            if (strpos($lower, '이미지') !== false || strpos($lower, 'image') !== false) {
                $type = 'image';
            } elseif (strpos($lower, '영상') !== false || strpos($lower, 'video') !== false) {
                $type = 'video';
            }
            return $this->action_payload(
                'Gallery에서 최근 작품을 보여드릴게요.',
                'navigate',
                array(
                    'type'  => 'show_gallery',
                    'risk'  => 'low',
                    'query' => '',
                    'filter_type' => $type,
                    'label' => 'Gallery 열기',
                ),
                array('Gallery 열기')
            );
        }

        // Find work by keyword (simple).
        if ($this->matches_any($lower, array('찾아줘', '검색해', '어디 있'))) {
            $q = $this->extract_search_query($message);
            if ($q !== '') {
                return $this->action_payload(
                    '“' . $q . '” 관련 작품을 Gallery에서 찾아볼게요.',
                    'navigate',
                    array(
                        'type'  => 'show_gallery',
                        'risk'  => 'low',
                        'query' => $q,
                        'label' => '검색 결과 보기',
                    ),
                    array('Gallery에서 찾기')
                );
            }
        }

        // Project continue.
        if ($this->matches_any($lower, array('프로젝트 계속', '계속 작업', '이 프로젝트', '프로젝트 열어'))) {
            $has_project = !empty($context['project']) && is_array($context['project']);
            if (!$has_project) {
                return $this->action_payload(
                    '활성 프로젝트가 없어요. Projects에서 프로젝트를 열어주세요.',
                    'clarify',
                    array(
                        'type'  => 'open_projects',
                        'risk'  => 'low',
                        'label' => 'Projects 열기',
                    ),
                    array('Projects 열기')
                );
            }
            return $this->action_payload(
                '현재 프로젝트 맥락을 유지한 채 Projects로 이동할게요.',
                'navigate',
                array(
                    'type'       => 'open_project',
                    'risk'       => 'low',
                    'project_id' => (string) ($context['project']['id'] ?? ''),
                    'label'      => '프로젝트 열기',
                ),
                array('프로젝트 열기', '이미지 이어서')
            );
        }

        // Template help.
        if ($this->matches_any($lower, array('템플릿', '어떻게 해야', '추천해줘')) && $this->matches_any($lower, array('광고', '제품', '어떻게', '시작'))) {
            return $this->action_payload(
                '빠른 시작에는 템플릿이 좋아요. Templates에서 골라 이어갈 수 있어요.',
                'info',
                array(
                    'type'  => 'open_templates',
                    'risk'  => 'low',
                    'label' => '템플릿 보기',
                ),
                array('템플릿 보기', '직접 만들기')
            );
        }

        // Deictic without context → clarify.
        if ($this->is_deictic_only($lower) && empty($context['selected_asset']) && empty($context['last_asset'])) {
            return $this->action_payload(
                '어떤 작품을 말씀하시나요? 최근 작품 중 하나를 고르거나, 만들고 싶은 내용을 조금 더 적어 주세요.',
                'clarify',
                array(
                    'type'    => 'clarify_asset',
                    'risk'    => 'low',
                    'label'   => '최근 작품 보기',
                    'options' => $this->recent_options($context),
                ),
                array('최근 작품 보기', '새로 만들기')
            );
        }

        // Creation / remix commands.
        $studio = $this->detect_studio($lower, $context);
        if ($studio !== '') {
            $prompt = $this->creation_prompt($message, $context);
            $title  = $this->studio_label($studio);
            $ref    = $this->reference_asset($context, $lower);

            $reply = $title . '에 준비했어요. Generate는 Studio에서 확인한 뒤 실행해 주세요.';
            if ($ref) {
                $reply = '참고 작품과 함께 ' . $title . '에 준비했어요. Generate는 Studio에서 실행됩니다.';
            }

            return $this->action_payload(
                $reply,
                'action',
                array(
                    'type'            => 'prepare_creation',
                    'risk'            => 'low',
                    'studio'          => $studio,
                    'prompt'          => $prompt,
                    'label'           => $title . '에서 계속',
                    'reference_asset' => $ref,
                    'auto_generate'   => false,
                ),
                array($title . '에서 계속', '다른 Studio')
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<int, string>   $quick
     * @return array<string, mixed>
     */
    private function action_payload(string $reply, string $phase, array $action, array $quick) {
        $studio = isset($action['studio']) ? (string) $action['studio'] : null;
        $actions = array();
        if ($studio) {
            $actions[] = array(
                'id'     => $studio,
                'route'  => $studio,
                'label'  => $this->studio_label($studio) . '에서 계속',
                'module' => $studio . '-studio',
            );
        }

        return array(
            'reply'               => $reply,
            'phase'               => $phase,
            'partner_mode'        => 'command_center',
            'studio_actions'      => $actions,
            'intent'              => isset($action['type']) ? (string) $action['type'] : 'command',
            'primary_studio'      => $studio,
            'recommended_studios' => $studio ? array($studio) : array(),
            'composed'            => null,
            'recommendations'     => array(),
            'quick_replies'       => array_values(array_filter($quick)),
            'brief'               => array(
                'purpose'        => isset($action['type']) ? (string) $action['type'] : '',
                'goal'           => isset($action['prompt']) ? (string) $action['prompt'] : $reply,
                'primary_studio' => $studio,
                'studios'        => $studio ? array($studio) : array(),
            ),
            'credits_charged'     => false,
            'command_action'      => $action,
            'prompt_policy'       => 'prepare_only_no_auto_generate',
        );
    }

    /**
     * @param array<int, string> $needles
     */
    private function matches_any(string $haystack, array $needles): bool {
        foreach ($needles as $n) {
            if ($n !== '' && strpos($haystack, $n) !== false) {
                return true;
            }
        }
        return false;
    }

    private function is_deictic_only(string $lower): bool {
        if (!$this->matches_any($lower, array('이거', '이걸', '이것', '방금', '아까', '이 작품', '그거', '그걸'))) {
            return false;
        }
        // If also has a clear studio verb, not "only".
        if ($this->detect_studio($lower, array()) !== '') {
            return false;
        }
        return (function_exists('mb_strlen') ? mb_strlen($lower) : strlen($lower)) < 28;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function detect_studio(string $lower, array $context): string {
        if ($this->matches_any($lower, array('번역', 'translate', '영어로', '일본어로'))) {
            return 'translator';
        }
        if ($this->matches_any($lower, array('영상', '비디오', 'video', '쇼츠', 'shorts', '릴스', '유튜브'))) {
            return 'video';
        }
        if ($this->matches_any($lower, array('음악', 'bgm', 'music', '멜로디', '사운드트랙'))) {
            return 'music';
        }
        if ($this->matches_any($lower, array('나레이션', '읽어줘', '읽어 줘', '음성', 'tts', '보이스', '더빙'))) {
            return 'voice';
        }
        if ($this->matches_any($lower, array('아바타', 'avatar'))) {
            return 'avatar';
        }
        if ($this->matches_any($lower, array('글 써', '글쓰기', '블로그', '소개글', '카피', '문구', '스크립트', '원고', '써줘'))) {
            return 'writing';
        }
        if ($this->matches_any($lower, array('이미지', '그림', '포스터', '사진', '제품샷', '광고 이미지', '그려'))) {
            return 'image';
        }
        // "만들어줘" with deictic + image context → often remix to video/image
        if ($this->matches_any($lower, array('만들어', '바꿔', '수정', '이어서')) && !empty($context['selected_asset'])) {
            $type = (string) (($context['selected_asset']['type'] ?? ''));
            if ($type === 'image' && $this->matches_any($lower, array('영상', 'video'))) {
                return 'video';
            }
            if ($type === 'image') {
                return 'image';
            }
        }
        if ($this->matches_any($lower, array('만들어줘', '만들어 줘', '생성해')) && !$this->matches_any($lower, array('프로젝트', '크레딧', '플랜', '공개', '삭제'))) {
            return 'image';
        }
        return '';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function creation_prompt(string $message, array $context): string {
        $msg = trim($message);
        if ($this->matches_any(function_exists('mb_strtolower') ? mb_strtolower($msg) : strtolower($msg), array('이거', '이걸', '방금', '아까'))) {
            $asset = $this->reference_asset($context, $msg);
            if ($asset && !empty($asset['title'])) {
                return $msg . ' (참고: ' . $asset['title'] . ')';
            }
        }
        return $msg;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function reference_asset(array $context, string $lower) {
        if (!empty($context['selected_asset']) && is_array($context['selected_asset'])) {
            return $context['selected_asset'];
        }
        if ($this->matches_any($lower, array('이거', '이걸', '방금', '아까', '이 작품')) && !empty($context['last_asset']) && is_array($context['last_asset'])) {
            return $context['last_asset'];
        }
        return null;
    }

    private function studio_label(string $studio): string {
        $map = array(
            'image'      => 'Image Studio',
            'video'      => 'Video Studio',
            'writing'    => 'Writing Studio',
            'music'      => 'Music Studio',
            'voice'      => 'Voice Studio',
            'avatar'     => 'Avatar Studio',
            'translator' => 'Translator',
        );
        return isset($map[$studio]) ? $map[$studio] : $studio;
    }

    private function extract_search_query(string $message): string {
        $msg = trim($message);
        $msg = preg_replace('/(찾아줘|검색해줘|검색해|어디 있어|어디 있니|찾아 봐)/u', '', $msg);
        return trim((string) $msg);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, string>>
     */
    private function recent_options(array $context): array {
        $out = array();
        $recent = isset($context['recent_assets']) && is_array($context['recent_assets']) ? $context['recent_assets'] : array();
        foreach (array_slice($recent, 0, 4) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out[] = array(
                'id'    => (string) ($item['gallery_id'] ?? ''),
                'label' => (string) ($item['title'] !== '' ? $item['title'] : ($item['type'] ?? '작품')),
                'type'  => (string) ($item['type'] ?? ''),
            );
        }
        return $out;
    }
}
