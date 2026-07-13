<?php
if (!defined('ABSPATH')) exit;

/**
 * Subject / domain preservation + quality scoring.
 */
final class YooY_Studio_Prompt_Validator {

    /**
     * @param array<string, mixed> $brief
     * @param string               $final_prompt
     * @param string               $composed_domain
     * @return array{ok:bool,code:string,message:string,rewrite:bool}
     */
    public function validate(array $brief, string $final_prompt, string $composed_domain): array {
        $domain = (string) ($brief['content_domain'] ?? 'general');
        $final_l = mb_strtolower($final_prompt);

        if ($this->domain_mismatch($domain, $composed_domain, $final_l)) {
            return [
                'ok'      => false,
                'code'    => 'prompt_domain_mismatch',
                'message' => 'Final prompt domain does not match user intent domain.',
                'rewrite' => true,
            ];
        }

        if (!empty($brief['wants_political']) || $domain === 'politics') {
            if ($this->looks_like_product_packshot($final_l)) {
                return [
                    'ok'      => false,
                    'code'    => 'unrelated_product_injection',
                    'message' => 'Product photography language injected into political request.',
                    'rewrite' => true,
                ];
            }
            if (!$this->subject_present($brief, $final_prompt)) {
                return [
                    'ok'      => false,
                    'code'    => 'primary_subject_lost',
                    'message' => 'Primary subject missing from final prompt.',
                    'rewrite' => true,
                ];
            }
        }

        if (!empty($brief['wants_product']) && $this->looks_like_politics($final_l) && $domain !== 'politics') {
            return [
                'ok'      => false,
                'code'    => 'prompt_domain_mismatch',
                'message' => 'Political template injected into product request.',
                'rewrite' => true,
            ];
        }

        return [
            'ok'      => true,
            'code'    => 'ok',
            'message' => 'Pass',
            'rewrite' => false,
        ];
    }

    /**
     * @param array<string, mixed> $brief
     * @param string               $final_prompt
     * @param array<string, mixed> $validation
     * @return array{score:int,breakdown:array<string,int>}
     */
    public function score(array $brief, string $final_prompt, array $validation): array {
        $breakdown = [
            'subject_clarity'     => $this->subject_present($brief, $final_prompt) ? 20 : 4,
            'intent_match'        => !empty($validation['ok']) ? 15 : 4,
            'domain_match'        => ($validation['code'] ?? '') === 'prompt_domain_mismatch' ? 2 : 15,
            'composition'         => mb_strlen($final_prompt) > 80 ? 10 : 5,
            'required_coverage'   => $this->required_coverage($brief, $final_prompt),
            'forbidden_protection'=> $this->forbidden_protection($brief, $final_prompt),
            'studio_suitability'  => 10,
            'length'              => (mb_strlen($final_prompt) >= 60 && mb_strlen($final_prompt) <= 900) ? 5 : 2,
            'contradiction'       => ($validation['code'] ?? '') === 'unrelated_product_injection' ? 0 : 10,
        ];
        $score = array_sum($breakdown);
        return ['score' => (int) $score, 'breakdown' => $breakdown];
    }

    private function domain_mismatch(string $user_domain, string $composed_domain, string $final_l): bool {
        if ($user_domain === 'politics' && ($composed_domain === 'product' || $this->looks_like_product_packshot($final_l))) {
            return true;
        }
        if (in_array($user_domain, ['product', 'ecommerce'], true) && ($composed_domain === 'politics' || $this->looks_like_politics($final_l))) {
            return true;
        }
        return false;
    }

    private function looks_like_product_packshot(string $final_l): bool {
        // Ignore negation clauses ("not cosmetics") — only flag affirmative product-ad framing.
        $stripped = preg_replace('/\bnot\b[^.]*?(cosmetic|perfume|skincare|merchandise|packshot|product photography)[^.]*\.?/i', '', $final_l) ?? $final_l;
        return (bool) preg_match(
            '/premium product photography|hero product(?:\s+as|\s+shot)?|skincare product|product pedestal|ecommerce product shot|(?<!unrelated\s)merchandise packshot|bottle as the clear focal/i',
            $stripped
        ) || (bool) preg_match('/\b(cosmetic bottle|perfume bottle)\b/i', $stripped);
    }

    private function looks_like_politics(string $final_l): bool {
        return (bool) preg_match('/political editorial|election campaign|civic campaign|lee jae-myung|policy message/i', $final_l);
    }

    /** @param array<string, mixed> $brief */
    private function subject_present(array $brief, string $final_prompt): bool {
        $final_l = mb_strtolower($final_prompt);
        foreach ((array) ($brief['entities'] ?? []) as $e) {
            if (!is_array($e)) {
                continue;
            }
            $en = mb_strtolower((string) ($e['name_en'] ?? ''));
            $ko = (string) ($e['name'] ?? '');
            if ($en !== '' && mb_strpos($final_l, $en) !== false) {
                return true;
            }
            if ($ko !== '' && mb_strpos($final_prompt, $ko) !== false) {
                return true;
            }
        }
        $primary = mb_strtolower((string) ($brief['primary_subject'] ?? ''));
        if ($primary === '') {
            return true;
        }
        // Token overlap for long Korean subjects translated into English brief
        if (mb_strpos($final_l, 'lee jae-myung') !== false || mb_strpos($final_l, 'political') !== false) {
            if (!empty($brief['wants_political'])) {
                return true;
            }
        }
        $tokens = preg_split('/[\s,]+/u', $primary) ?: [];
        $hits = 0;
        foreach ($tokens as $tok) {
            $tok = trim($tok);
            if (mb_strlen($tok) < 4) {
                continue;
            }
            if (mb_strpos($final_l, mb_strtolower($tok)) !== false) {
                $hits++;
            }
        }
        return $hits >= 1;
    }

    /** @param array<string, mixed> $brief */
    private function required_coverage(array $brief, string $final_prompt): int {
        $req = (array) ($brief['required_elements'] ?? []);
        if (!$req) {
            return 8;
        }
        $final_l = mb_strtolower($final_prompt);
        $hit = 0;
        foreach ($req as $r) {
            $r = mb_strtolower((string) $r);
            if ($r !== '' && mb_strpos($final_l, $r) !== false) {
                $hit++;
            }
        }
        $ratio = $hit / max(1, count($req));
        return (int) round(10 * $ratio);
    }

    /** @param array<string, mixed> $brief */
    private function forbidden_protection(array $brief, string $final_prompt): int {
        if (empty($brief['wants_political']) && ($brief['content_domain'] ?? '') !== 'politics') {
            return 10;
        }
        return $this->looks_like_product_packshot(mb_strtolower($final_prompt)) ? 0 : 10;
    }
}
