<?php
if (!defined('ABSPATH')) exit;

final class YooY_Credits_Service {

    private const BALANCE_KEY = 'yoy_credits_balance';
    private const LEDGER_KEY  = 'yoy_credits_ledger';

    public function balance(int $user_id): int {
        $balance = (int) get_user_meta($user_id, self::BALANCE_KEY, true);
        if ($balance === 0 && !metadata_exists('user', $user_id, self::BALANCE_KEY)) {
            $balance = $this->is_unlimited($user_id) ? 999999 : 100;
            update_user_meta($user_id, self::BALANCE_KEY, $balance);
        }
        return $balance;
    }

    public function is_unlimited(int $user_id): bool {
        return user_can($user_id, 'manage_options');
    }

    public function can_afford(int $user_id, int $cost): bool {
        if ($this->is_unlimited($user_id)) return true;
        return $this->balance($user_id) >= $cost;
    }

    public function deduct(int $user_id, int $cost, string $label = 'Generation', string $module = 'core'): array {
        if ($this->is_unlimited($user_id)) {
            return ['balance' => 999999, 'unlimited' => true, 'deducted' => 0];
        }

        $balance = $this->balance($user_id);
        if ($balance < $cost) {
            throw new Exception('Insufficient credits. Required: ' . $cost . ', Available: ' . $balance);
        }

        $new_balance = $balance - $cost;
        update_user_meta($user_id, self::BALANCE_KEY, $new_balance);
        $this->append_ledger($user_id, [
            'id'         => 'tx_' . wp_generate_uuid4(),
            'type'       => 'deduct',
            'amount'     => -$cost,
            'label'      => $label,
            'module'     => $module,
            'created_at' => gmdate('c'),
        ]);

        return ['balance' => $new_balance, 'unlimited' => false, 'deducted' => $cost];
    }

    public function append_ledger(int $user_id, array $entry): void {
        $ledger = get_user_meta($user_id, self::LEDGER_KEY, true);
        $ledger = is_array($ledger) ? $ledger : [];
        array_unshift($ledger, $entry);
        update_user_meta($user_id, self::LEDGER_KEY, array_slice($ledger, 0, 200));
    }

    public function ledger(int $user_id): array {
        $stored = get_user_meta($user_id, self::LEDGER_KEY, true);
        if (is_array($stored) && !empty($stored)) return $stored;

        $seed = [
            ['id' => 'tx_welcome', 'type' => 'grant', 'amount' => 100, 'label' => 'Welcome bonus', 'module' => 'credits', 'created_at' => gmdate('c')],
        ];
        update_user_meta($user_id, self::LEDGER_KEY, $seed);
        return $seed;
    }

    public function snapshot(int $user_id): array {
        return [
            'balance'   => $this->balance($user_id),
            'unlimited' => $this->is_unlimited($user_id),
            'plan'      => $this->is_unlimited($user_id) ? 'admin' : 'free',
            'currency'  => 'KRW',
        ];
    }
}
