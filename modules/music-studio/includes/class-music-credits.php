<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Credits {

    public function balance(int $user_id): int {
        $balance = (int) get_user_meta($user_id, 'yoy_credits_balance', true);
        if ($balance === 0 && !metadata_exists('user', $user_id, 'yoy_credits_balance')) {
            $balance = user_can($user_id, 'manage_options') ? 999999 : 100;
            update_user_meta($user_id, 'yoy_credits_balance', $balance);
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

    public function deduct(int $user_id, int $cost, string $label = 'Music generation'): array {
        if ($this->is_unlimited($user_id)) {
            return ['balance' => 999999, 'unlimited' => true, 'deducted' => 0];
        }

        $balance = $this->balance($user_id);
        if ($balance < $cost) {
            throw new Exception('Insufficient credits. Required: ' . $cost . ', Available: ' . $balance);
        }

        $new_balance = $balance - $cost;
        update_user_meta($user_id, 'yoy_credits_balance', $new_balance);

        $ledger = get_user_meta($user_id, 'yoy_credits_ledger', true);
        $ledger = is_array($ledger) ? $ledger : [];
        array_unshift($ledger, [
            'id'         => 'tx_' . wp_generate_uuid4(),
            'type'       => 'deduct',
            'amount'     => -$cost,
            'label'      => $label,
            'module'     => 'music-studio',
            'created_at' => gmdate('c'),
        ]);
        update_user_meta($user_id, 'yoy_credits_ledger', array_slice($ledger, 0, 100));

        return ['balance' => $new_balance, 'unlimited' => false, 'deducted' => $cost];
    }

    public function estimate(array $params): int {
        $duration = (int) ($params['duration'] ?? 120);
        $base     = 20;
        $quality  = ($params['audio_quality'] ?? 'standard') === 'high' ? 10 : 0;
        return $base + (int) floor($duration / 30) * 5 + $quality;
    }
}
