<?php
if (!defined('ABSPATH')) exit;

/**
 * Translator Credits — thin adapter over YooY_Credits_Service.
 * Mock / Mock Fallback never deduct. Real provider deducts only after Language Asset save.
 */
final class YooY_Translator_Credits {

    /** Characters per credit unit for live providers. */
    const CHARS_PER_CREDIT = 500;

    /** Minimum credits for a live translation request. */
    const MIN_CREDITS = 1;

    /** @var YooY_Credits_Service|null */
    private $service;

    public function __construct(?YooY_Credits_Service $service = null) {
        $this->service = $service;
    }

    public function service(): ?YooY_Credits_Service {
        if ($this->service instanceof YooY_Credits_Service) {
            return $this->service;
        }
        if (!class_exists('YooY_Credits_Service')) {
            return null;
        }
        $this->service = new YooY_Credits_Service();
        return $this->service;
    }

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

    /**
     * Resolve which provider id to use for pre-flight estimate/can_afford when client sends auto.
     */
    public function resolve_estimate_provider(string $requested, bool $openai_ready): string {
        $id = sanitize_key($requested);
        if ($id === '' || $id === 'auto') {
            return $openai_ready ? 'openai' : 'mock';
        }
        return $id;
    }

    public function is_billable_provider(string $provider_id): bool {
        $id = sanitize_key($provider_id);
        if ($id === '' || $id === 'mock' || $id === 'mock-translator' || $id === 'auto') {
            return false;
        }
        return in_array($id, ['openai', 'openai-translator'], true);
    }

    public function is_billable_result(string $provider_id, bool $fallback_used): bool {
        if ($fallback_used) {
            return false;
        }
        return $this->is_billable_provider($provider_id);
    }

    public function is_unlimited(int $user_id): bool {
        $svc = $this->service();
        return $svc ? $svc->is_unlimited($user_id) : false;
    }

    public function can_afford(int $user_id, array $params, string $provider_id): bool {
        $cost = $this->estimate($params, $provider_id);
        if ($cost <= 0) {
            return true;
        }
        $svc = $this->service();
        if (!$svc) {
            // Billing core unavailable — allow mock paths only.
            return !$this->is_billable_provider($provider_id);
        }
        return $svc->can_afford($user_id, $cost);
    }

    /**
     * Snapshot + estimate for REST / UI (mirrors Image Studio estimate response shape).
     *
     * @return array
     */
    public function estimate_payload(int $user_id, array $params, string $provider_id): array {
        $cost = $this->estimate($params, $provider_id);
        $svc = $this->service();
        $snap = $svc ? $svc->snapshot($user_id) : [
            'balance'   => 0,
            'unlimited' => false,
            'plan'      => 'free',
        ];
        return array_merge($snap, [
            'estimate'           => $cost,
            'can_afford'         => $this->can_afford($user_id, $params, $provider_id),
            'provider'           => $provider_id,
            'chars_per_credit'   => self::CHARS_PER_CREDIT,
            'billable'           => $this->is_billable_provider($provider_id),
        ]);
    }

    /**
     * Plan-only preview (no ledger). Used for response meta when no deduct needed.
     *
     * @return array{cost:int,deducted:int,unlimited:bool,provider:string,skipped?:bool,reason?:string,balance?:int}
     */
    public function plan_debit(int $user_id, array $params, string $provider_id, bool $fallback_used = false): array {
        $billable = $this->is_billable_result($provider_id, $fallback_used);
        $cost = $billable ? $this->estimate($params, $provider_id) : 0;
        $svc = $this->service();
        $unlimited = $svc ? $svc->is_unlimited($user_id) : false;
        $balance = $svc ? (int) ($svc->snapshot($user_id)['balance'] ?? 0) : 0;
        $reason = '';
        if (!$billable) {
            $reason = $fallback_used ? 'fallback' : 'mock';
        }
        return [
            'cost'      => $cost,
            'deducted'  => 0,
            'unlimited' => $unlimited,
            'provider'  => $provider_id,
            'skipped'   => !$billable,
            'reason'    => $reason,
            'balance'   => $unlimited ? 999999 : $balance,
        ];
    }

    /**
     * Ledger deduct via YooY_Credits_Service. Call ONLY after Language Asset save success.
     *
     * @return array{cost:int,deducted:int,unlimited:bool,provider:string,skipped?:bool,reason?:string,balance?:int}
     * @throws YooY_Translator_Exception
     */
    public function deduct(int $user_id, array $params, string $provider_id, bool $fallback_used = false, array $meta = []): array {
        $plan = $this->plan_debit($user_id, $params, $provider_id, $fallback_used);
        if (!empty($plan['skipped']) || (int) $plan['cost'] <= 0) {
            return $plan;
        }

        $svc = $this->service();
        if (!$svc) {
            throw new YooY_Translator_Exception('크레딧 시스템을 사용할 수 없습니다.', 'billing_unavailable', 503);
        }

        if ($svc->is_unlimited($user_id)) {
            $plan['deducted'] = 0;
            $plan['skipped'] = false;
            $plan['reason'] = 'unlimited_admin';
            $plan['balance'] = 999999;
            return $plan;
        }

        $cost = (int) $plan['cost'];
        $label = $this->build_label($params);
        try {
            $result = $svc->deduct($user_id, $cost, $label, 'translator-studio', array_merge([
                'studio'   => 'translator-studio',
                'provider' => $provider_id,
                'status'   => 'completed',
            ], $meta));
        } catch (Exception $e) {
            throw new YooY_Translator_Exception(
                '크레딧이 부족합니다. 필요: ' . $cost,
                'insufficient_credits',
                402
            );
        }

        return [
            'cost'      => $cost,
            'deducted'  => (int) ($result['deducted'] ?? $cost),
            'unlimited' => !empty($result['unlimited']),
            'provider'  => $provider_id,
            'skipped'   => false,
            'reason'    => '',
            'balance'   => (int) ($result['balance'] ?? 0),
        ];
    }

    /**
     * Rollback: restore credits after a failed post-deduct step.
     * Uses existing Credits_Service::adjust_balance (grant ledger entry).
     *
     * @return array Snapshot-like result
     */
    public function refund(int $user_id, int $amount, string $label = 'Translator refund', array $meta = []): array {
        if ($amount <= 0) {
            return ['balance' => 0, 'refunded' => 0];
        }
        $svc = $this->service();
        if (!$svc || $svc->is_unlimited($user_id)) {
            return ['balance' => 999999, 'refunded' => 0, 'unlimited' => true];
        }
        $snap = $svc->adjust_balance(
            $user_id,
            $amount,
            $label,
            'translator-studio',
            array_merge(['studio' => 'translator-studio', 'status' => 'refunded'], $meta)
        );
        return array_merge($snap, ['refunded' => $amount]);
    }

    private function build_label(array $params): string {
        $text = (string) ($params['text'] ?? '');
        if (function_exists('mb_substr')) {
            $snippet = mb_substr($text, 0, 40, 'UTF-8');
        } else {
            $snippet = substr($text, 0, 40);
        }
        $snippet = trim(preg_replace('/\s+/', ' ', $snippet) ?? $snippet);
        return $snippet !== '' ? ('Translator: ' . $snippet) : 'Translator';
    }
}
