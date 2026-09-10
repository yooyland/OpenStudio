<?php
if (!defined('ABSPATH')) exit;

/**
 * Lightweight post-compose / post-generation visual QA (heuristic).
 * Does not call a vision model — records confidence for UI / optional premium retry.
 */
final class YooY_Image_Visual_QA {

    /**
     * @param array<string, mixed> $context
     * @return array{
     *   score:int,
     *   pass:bool,
     *   flags:string[],
     *   notes:string[],
     *   suggest_premium_retry:bool
     * }
     */
    public static function assess(array $context): array {
        $user = (string) ($context['user_prompt'] ?? $context['raw_user_request'] ?? '');
        $final = (string) ($context['final_prompt'] ?? $context['prompt'] ?? '');
        $domain = sanitize_key((string) ($context['intent_domain'] ?? $context['domain'] ?? ''));
        $preset = (string) ($context['art_direction'] ?? $context['preset'] ?? '');
        $composer_score = (int) ($context['composer_quality_score'] ?? 0);

        $flags = [];
        $notes = [];
        $score = 78;

        if ($user !== '' && $final !== '' && mb_strtolower(trim($user)) === mb_strtolower(trim($final))) {
            $flags[] = 'user_equals_final';
            $notes[] = 'Final prompt was not orchestrated (equals user prompt).';
            $score -= 25;
        }

        if ($final === '' || mb_strlen($final) < 80) {
            $flags[] = 'thin_final_prompt';
            $notes[] = 'Final prompt is too thin for premium direction.';
            $score -= 15;
        }

        $final_l = mb_strtolower($final);
        $user_l = mb_strtolower($user);
        // Ignore avoidance / negation clauses when scanning for kitsch/indoor risk words.
        $risk_scan = $final_l;
        $risk_scan = preg_replace('/\b(avoid|avoids|do not|don\'t|never|not)\b[^.;]*/iu', ' ', $risk_scan) ?? $risk_scan;
        $risk_scan = preg_replace('/avoid:\s*.*?(?=(important:|premium bias:|quality constraints:|$))/isu', ' ', $risk_scan) ?? $risk_scan;
        $risk_scan = preg_replace('/non-kitschy|not toddler clipart|not an indoor mural|flat mural look|indoor bedroom framing|child observer unless asked/u', ' ', $risk_scan) ?? $risk_scan;

        if (preg_match('/\bbedroom\b|child observer|looking at a picture/u', $risk_scan)
            && preg_match('/고래|펭귄|팽귄|whale|penguin|판타지|fantasy|하늘을/u', $user_l)) {
            $flags[] = 'concept_risk_indoor_or_kitsch';
            $notes[] = 'Final prompt may dilute adventure into indoor/kitsch framing.';
            $score -= 18;
        }

        if (in_array($domain, ['storybook', 'fantasy'], true)
            || preg_match('/STORYBOOK|FANTASY/u', $preset)) {
            if (!preg_match('/premium|refined|sophisticated|editorial|cinematic|picture-book/u', $final_l)) {
                $flags[] = 'premium_feel_weak';
                $notes[] = 'Storybook/fantasy final prompt lacks premium visual bias language.';
                $score -= 12;
            }
            if (preg_match('/어린이|child/u', $user_l) && !preg_match('/adventure|whale|penguin|고래|펭귄|literal|CORE SCENE/u', $final_l)) {
                $flags[] = 'subject_fidelity_risk';
                $notes[] = 'Child-audience request may have lost adventure subject fidelity.';
                $score -= 10;
            }
        }

        if (in_array($domain, ['portrait', 'lifestyle'], true)
            && !preg_match('/anatomy|skin|hands|natural/u', $final_l)) {
            $flags[] = 'human_quality_weak';
            $notes[] = 'Human domain prompt missing anatomy/skin guidance.';
            $score -= 8;
        }

        if ($composer_score > 0 && $composer_score < 70) {
            $flags[] = 'composer_low_score';
            $notes[] = 'Prompt intelligence quality score is low (' . $composer_score . ').';
            $score -= 10;
        }

        if (preg_match('/CORE SCENE|VISUAL DIRECTION|QUALITY CONSTRAINTS/u', $final)) {
            $score += 6;
            $notes[] = 'Structured art-direction brief detected.';
        }

        if ($score > 100) {
            $score = 100;
        }
        if ($score < 0) {
            $score = 0;
        }

        $pass = $score >= 70 && !in_array('user_equals_final', $flags, true);
        $suggest = !$pass || in_array('premium_feel_weak', $flags, true)
            || in_array('concept_risk_indoor_or_kitsch', $flags, true);

        return [
            'score'                  => $score,
            'pass'                   => $pass,
            'flags'                  => array_values(array_unique($flags)),
            'notes'                  => $notes,
            'suggest_premium_retry'  => $suggest,
        ];
    }
}
