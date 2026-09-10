<?php
if (!defined('ABSPATH')) exit;

/**
 * Prompt / result metadata QA (NOT pixel vision).
 * Does not inspect the generated bitmap unless a future vision path is wired.
 */
final class YooY_Image_Visual_QA {

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function assess(array $context): array {
        $user = (string) ($context['user_prompt'] ?? $context['raw_user_request'] ?? '');
        $final = (string) ($context['final_prompt'] ?? $context['prompt'] ?? '');
        $domain = sanitize_key((string) ($context['intent_domain'] ?? $context['domain'] ?? ''));
        $title = (string) ($context['display_title'] ?? $context['title'] ?? '');
        $composer_score = (int) ($context['composer_quality_score'] ?? 0);

        $scores = [
            'prompt_fidelity'     => 70,
            'premium_feel'        => 70,
            'composition_quality' => 70,
            'subject_beauty'      => 70,
            'lighting_quality'    => 70,
            'detail_richness'     => 70,
            'non_kitschy_score'   => 70,
            'title_quality'       => 70,
        ];
        $flags = [];
        $notes = [
            'QA mode: prompt_metadata_qa — scores reflect prompt structure/heuristics, not vision inspection of pixels.',
        ];

        if ($user !== '' && $final !== '' && mb_strtolower(trim($user)) === mb_strtolower(trim($final))) {
            $flags[] = 'user_equals_final';
            $notes[] = 'Final prompt was not orchestrated.';
            $scores['prompt_fidelity'] = 25;
            $scores['premium_feel'] = 35;
        } elseif (preg_match('/P1 CORE SUBJECT|CORE SCENE|USER REQUEST \(fidelity\)/u', $final)) {
            $scores['prompt_fidelity'] = 90;
            $notes[] = 'Fidelity lock / structured brief detected.';
        } elseif (preg_match('/CORE SCENE|VISUAL DIRECTION|PREMIUM BIAS/u', $final)) {
            $scores['prompt_fidelity'] = 84;
        }

        $final_l = mb_strtolower($final);
        $user_l = mb_strtolower($user);

        // Bloat penalty
        $premium_hits = preg_match_all('/\bpremium\b/u', $final_l);
        if ($premium_hits !== false && $premium_hits > 4) {
            $flags[] = 'prompt_bloat_premium';
            $scores['premium_feel'] = min(78, 70 + min(8, $premium_hits));
            $notes[] = 'Repeated premium adjectives detected (bloat).';
        } elseif (preg_match('/refined|sophisticated|editorial|picture-book|contemporary/u', $final_l)) {
            $scores['premium_feel'] = 86;
        } else {
            $scores['premium_feel'] = 48;
            $flags[] = 'premium_feel_weak';
        }

        if (preg_match('/composition|depth|hierarchy|widescreen|layered|focal/u', $final_l)) {
            $scores['composition_quality'] = 84;
        } else {
            $scores['composition_quality'] = 55;
            $flags[] = 'composition_thin';
        }

        if (preg_match('/anatomy|skin|character|subject|hero|creature|wardrobe|gaze/u', $final_l)) {
            $scores['subject_beauty'] = 82;
        } elseif (in_array($domain, ['portrait', 'lifestyle', 'storybook', 'fantasy'], true)) {
            $scores['subject_beauty'] = 52;
            $flags[] = 'subject_guidance_weak';
        }

        if (preg_match('/lighting|moonlight|daylight|soft.?key|aurora|softbox/u', $final_l)) {
            $scores['lighting_quality'] = 85;
        } else {
            $scores['lighting_quality'] = 58;
        }

        if (mb_strlen($final) >= 400 || preg_match('/high-detail|materials|micro|texture|detail|layered/u', $final_l)) {
            $scores['detail_richness'] = 84;
        } else {
            $scores['detail_richness'] = 56;
            $flags[] = 'detail_thin';
        }

        $has_premium_guard = (bool) preg_match('/premium bias:|avoid:|quality escalator:|p1 core/u', $final_l);
        $scores['non_kitschy_score'] = $has_premium_guard ? 88 : 70;

        if ($title === '' || preg_match('/광고\s*이미지|이미지\s*\(\d+\)|작품\s*\(\d+\)|generated|untitled|quality\s*escalator/iu', $title)) {
            $scores['title_quality'] = 30;
            $flags[] = 'title_mechanical';
        } elseif (mb_strlen($title) >= 4 && mb_strlen($title) <= 24) {
            $scores['title_quality'] = 90;
        } else {
            $scores['title_quality'] = 70;
        }

        if (preg_match('/고래|whale/u', $user_l) && !preg_match('/고래|whale/u', $final_l)) {
            $scores['prompt_fidelity'] = min($scores['prompt_fidelity'], 45);
            $flags[] = 'subject_fidelity_risk';
        }
        if (preg_match('/펭귄|팽귄|penguin/u', $user_l) && !preg_match('/펭귄|팽귄|penguin/u', $final_l)) {
            $scores['prompt_fidelity'] = min($scores['prompt_fidelity'], 45);
            $flags[] = 'subject_fidelity_risk';
        }

        // Heuristic only: prompt asked for blank packaging.
        $unrequested_text_policy = false;
        if (in_array($domain, ['product', 'beauty', 'ecommerce'], true)
            && !preg_match('/텍스트|로고|타이포|label|logo|typography/u', $user_l)
            && preg_match('/blank|unbranded|no invented|no.*label text/u', $final_l)) {
            $unrequested_text_policy = true;
            $notes[] = 'Blank-packaging policy present in prompt; pixel OCR/vision not run.';
        }

        if ($composer_score > 0 && $composer_score < 70) {
            $flags[] = 'composer_low_score';
            $scores['premium_feel'] = min($scores['premium_feel'], 60);
        }

        $overall = (int) round(array_sum($scores) / max(1, count($scores)));
        $pass = $overall >= 72
            && $scores['premium_feel'] >= 65
            && $scores['composition_quality'] >= 60
            && $scores['subject_beauty'] >= 55
            && !in_array('user_equals_final', $flags, true);

        $suggest = !$pass
            || $scores['premium_feel'] < 70
            || $scores['composition_quality'] < 65
            || $scores['subject_beauty'] < 60;

        return [
            'mode'                       => 'prompt_metadata_qa',
            'inspects_pixels'            => false,
            'score'                      => $overall,
            'pass'                       => $pass,
            'scores'                     => $scores,
            'flags'                      => array_values(array_unique($flags)),
            'notes'                      => $notes,
            'unrequested_text_detected'  => false, // cannot claim without vision
            'unrequested_text_policy_on' => $unrequested_text_policy,
            'suggest_premium_retry'      => $suggest,
            'suggest_textless_retry'     => $unrequested_text_policy,
            'retry_policy'               => $suggest ? 'user_confirm_premium_retry' : 'none',
        ];
    }
}
