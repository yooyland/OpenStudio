<?php
if (!defined('ABSPATH')) exit;

/**
 * Credits for Translator Studio.
 * Only successful real-provider translations deduct. Mock and fallback never deduct.
 */
final class YooY_Translator_Credits {

    /** Characters per credit unit for live providers. */
    const CHARS_PER_CREDIT = 500;

    /** Minimum credits for a live translation request. */
    const MIN_CREDITS = 1;

    public function estimate(array $params, string $provider_id = 'mock'): int {
        if (!$this->is_billable_provider($provider_id)) {
            return 0;
        }
        $count = isset($params['character_count'])
            ? (int) $params['character_count']
            : YooY_Translator_Validator::char_count((string) ($params['text'] ?? ''));
        if ($count <= 0) {
            return 0;
        }
        $units = (int) ceil($count / self::CHARS_PER_CREDIT);
        return max(self::MIN_CREDITS, $units);
    }

    public function is_billable_provider(string $provider_id): bool {
        $id = sanitize_key($provider_id);
        if ($id === '' || $id === 'mock' || $id === 'mock-translator' || $id === 'auto') {
            return false;
        }
        // Real providers currently: openai / openai-translator
        return in_array($id, ['openai', 'openai-translator'], true);
    }

    public function is_billable_result(string $provider_id, bool $fallback_used): bool {
        if ($fallback_used) {
            return false;
        }
        return $this->is_billable_provider($provider_id);
    }

    public function can_afford(int $user_id, array $params, string $provider_id): bool {
        $cost = $this->estimate($params, $provider_id);
        if ($cost <= 0) {
            return true;
        }
        if (!class_exists('YooY_Credits_Service')) {
            return true;
        }
        return (new YooY_Credits_Service())->can_afford($user_id, $cost);
    }

    /**
     * Plan-only (no ledger). Used for response meta / previews.
     *
     * @return array{cost:int,deducted:int,unlimited:bool,provider:string,skipped?:bool,reason?:string}
     */
    public function plan_debit(int $user_id, array $params, string $provider_id, bool $fallback_used = false): array {
        $billable = $this->is_billable_result($provider_id, $fallback_used);
        $cost = $billable ? $this->estimate($params, $provider_id) : 0;
        $unlimited = false;
        if (class_exists('YooY_Credits_Service')) {
            $unlimited = (new YooY_Credits_Service())->is_unlimited($user_id);
        }
        return [
            'cost'      => $cost,
            'deducted'  => 0,
            'unlimited' => $unlimited,
            'provider'  => $provider_id,
            'skipped'   => !$billable,
            'reason'    => $billable ? '' : ($fallback_used ? 'fallback' : 'mock'),
        ];
    }

    /**
     * Deduct only for successful real-provider translations (not mock, not fallback).
     *
     * @return array{cost:int,deducted:int,unlimited:bool,provider:string,skipped?:bool,reason?:string,balance?:int}
     */
    public function deduct(int $user_id, array $params, string $provider_id, bool $fallback_used = false): array {
        $plan = $this->plan_debit($user_id, $params, $provider_id, $fallback_used);
        if (!empty($plan['skipped']) || $plan['cost'] <= 0) {
            return $plan;
        }

        if (!class_exists('YooY_Credits_Service')) {
            return $plan;
        }

        $label = 'Translation: ' . mb_substr((string) ($params['text'] ?? ''), 0, 40);
        if (!function_exists('mb_substr')) {
            $label = 'Translation: ' . substr((string) ($params['text'] ?? ''), 0, 40);
        }

        $result = (new YooY_Credits_Service())->deduct(
            $user_id,
            (int) $plan['cost'],
            $label,
            'translator-studio',
            [
                'provider'        => $provider_id,
                'character_count' => (int) ($params['character_count'] ?? 0),
                'mode'            => (string) ($params['mode'] ?? ''),
            ]
        );

        return [
            'cost'      => (int) $plan['cost'],
            'deducted'  => (int) ($result['deducted'] ?? 0),
            'unlimited' => !empty($result['unlimited']),
            'provider'  => $provider_id,
            'skipped'   => false,
            'reason'    => '',
            'balance'   => isset($result['balance']) ? (int) $result['balance'] : null,
        ];
    }
}
