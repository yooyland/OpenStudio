<?php
if (!defined('ABSPATH')) exit;

/**
 * Visual QA Lite — dimensional heuristic scores (no vision model).
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
        $preset = (string) ($context['art_direction'] ?? $context['preset'] ?? '');
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
        $notes = [];

        if ($user !== '' && $final !== '' && mb_strtolower(trim($user)) === mb_strtolower(trim($final))) {
            $flags[] = 'user_equals_final';
            $notes[] = 'Final prompt was not orchestrated.';
            $scores['prompt_fidelity'] = 25;
            $scores['premium_feel'] = 35;
        } elseif (preg_match('/CORE SCENE|VISUAL DIRECTION|PREMIUM BIAS/u', $final)) {
            $scores['prompt_fidelity'] = 88;
            $notes[] = 'Structured art-direction brief detected.';
        }

        $final_l = mb_strtolower($final);
        $user_l = mb_strtolower($user);

        if (preg_match('/premium|refined|sophisticated|editorial|cinematic|picture-book/u', $final_l)) {
            $scores['premium_feel'] = 86;
        } else {
            $scores['premium_feel'] = 48;
            $flags[] = 'premium_feel_weak';
        }

        if (preg_match('/composition|depth|hierarchy|widescreen|rule-of-thirds|focal/u', $final_l)) {
            $scores['composition_quality'] = 84;
        } else {
            $scores['composition_quality'] = 55;
            $flags[] = 'composition_thin';
        }

        if (preg_match('/anatomy|skin|character|subject|hero|creature/u', $final_l)) {
            $scores['subject_beauty'] = 82;
        } elseif (in_array($domain, ['portrait', 'lifestyle', 'storybook', 'fantasy'], true)) {
            $scores['subject_beauty'] = 52;
            $flags[] = 'subject_guidance_weak';
        }

        if (preg_match('/lighting|moonlight|golden.?hour|soft.?key|cinematic light|aurora/u', $final_l)) {
            $scores['lighting_quality'] = 85;
        } else {
            $scores['lighting_quality'] = 58;
        }

        if (mb_strlen($final) >= 400 || preg_match('/high-detail|materials|micro|texture|detail/u', $final_l)) {
            $scores['detail_richness'] = 84;
        } else {
            $scores['detail_richness'] = 56;
            $flags[] = 'detail_thin';
        }

        $risk_scan = $final_l;
        // Strip guidance that mentions risks only to forbid them.
        $risk_scan = preg_replace('/\b(avoid|avoids|do not|don\'t|never|not)\b[^.;]*/iu', ' ', $risk_scan) ?? $risk_scan;
        $risk_scan = preg_replace('/avoid:\s*.*?(?=(important:|premium bias:|quality constraints:|setting detail:|atmosphere detail:|landmarks:|tone cue:|quality escalator:|$))/isu', ' ', $risk_scan) ?? $risk_scan;
        $risk_scan = preg_replace('/purpose:\s*.*?(?=(visual direction:|composition:|lighting:|$))/isu', ' ', $risk_scan) ?? $risk_scan;
        $risk_scan = preg_replace('/important:\s*.*?(?=(setting detail:|atmosphere detail:|landmarks:|tone cue:|quality escalator:|premium bias:|$))/isu', ' ', $risk_scan) ?? $risk_scan;
        $risk_scan = preg_replace('/non-kitschy|not toddler clipart|indoor bedroom framing|child observer unless asked|disney-theme-park kitsch avoided|disney-park look|kitschy disney/u', ' ', $risk_scan) ?? $risk_scan;
        $has_premium_guard = (bool) preg_match('/premium bias:|avoid:|quality escalator:/u', $final_l);
        if ($has_premium_guard) {
            $scores['non_kitschy_score'] = 88;
        } elseif (preg_match('/\bbedroom\b|child observer|looking at a picture|\bclipart\b|\bkitschy\b|toy-like/u', $risk_scan)) {
            $scores['non_kitschy_score'] = 40;
            $flags[] = 'concept_risk_indoor_or_kitsch';
            $notes[] = 'Possible kitsch/indoor dilution language outside AVOID clauses.';
        } else {
            $scores['non_kitschy_score'] = 88;
        }

        if ($title === '' || preg_match('/광고\s*이미지|이미지\s*\(\d+\)|작품\s*\(\d+\)|generated|untitled/iu', $title)) {
            $scores['title_quality'] = 30;
            $flags[] = 'title_mechanical';
        } elseif (mb_strlen($title) >= 4 && mb_strlen($title) <= 24) {
            $scores['title_quality'] = 90;
        } else {
            $scores['title_quality'] = 70;
        }

        // Concept tokens present
        if (preg_match('/고래|whale/u', $user_l) && !preg_match('/고래|whale/u', $final_l)) {
            $scores['prompt_fidelity'] = min($scores['prompt_fidelity'], 45);
            $flags[] = 'subject_fidelity_risk';
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
            'score'                 => $overall,
            'pass'                  => $pass,
            'scores'                => $scores,
            'flags'                 => array_values(array_unique($flags)),
            'notes'                 => $notes,
            'suggest_premium_retry' => $suggest,
            'retry_policy'          => $suggest ? 'user_confirm_premium_retry' : 'none',
        ];
    }
}
