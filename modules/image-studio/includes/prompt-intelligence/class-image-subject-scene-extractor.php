<?php
if (!defined('ABSPATH')) exit;

/**
 * Subject / Scene Extractor — concept fidelity anchors for art direction.
 */
final class YooY_Image_Subject_Scene_Extractor {

    /**
     * @param array<string, mixed> $intent
     * @return array{subject:string,action:string,setting:string,mood:string,must_include:string[]}
     */
    public static function extract(string $normalized, array $intent = []): array {
        $raw = $normalized !== '' ? $normalized : (string) ($intent['raw_user_request'] ?? '');
        $lower = mb_strtolower($raw);
        $must = [];

        $subject = (string) ($intent['primary_subject'] ?? '');
        if ($subject === '' || mb_strlen($subject) < 8) {
            $subject = mb_substr($raw, 0, 180);
        }

        $action = '';
        if (preg_match('/(타고|날|여행|이야기|걷|대화|riding|flying|walking|talking)/u', $raw, $m)) {
            $action = (string) $m[1];
        }
        $setting = '';
        if (preg_match('/(밤하늘|바다|해변|아파트|단지|스튜디오|도시|night sky|beach|apartment)/u', $raw, $m)) {
            $setting = (string) $m[1];
        }
        $mood = (string) ($intent['tone'] ?? '');
        if ($mood === '') {
            if (preg_match('/세련|고급|프리미엄|premium|refined|elegant/u', $lower)) {
                $mood = 'refined premium';
            } elseif (preg_match('/따뜻|감동|warm|emotional/u', $lower)) {
                $mood = 'warm emotional';
            } else {
                $mood = 'professional';
            }
        }

        foreach (['고래', '펭귄', '팽귄', '에펠', '피라미드', '빅벤', '여신상', '오로라', 'whale', 'penguin'] as $token) {
            if (mb_strpos($lower, mb_strtolower($token)) !== false) {
                $must[] = $token;
            }
        }

        return [
            'subject'      => $subject,
            'action'       => $action,
            'setting'      => $setting,
            'mood'         => $mood,
            'must_include' => array_values(array_unique($must)),
        ];
    }
}
