<?php
if (!defined('ABSPATH')) exit;

final class YooY_Music_Credits {

    private YooY_Studio_Credits $credits;

    public function __construct(?YooY_Studio_Credits $credits = null) {
        $this->credits = $credits ?? new YooY_Studio_Credits('music-studio');
    }

    public function service(): YooY_Studio_Credits {
        return $this->credits;
    }

    public function balance(int $user_id): int {
        return $this->credits->balance($user_id);
    }

    public function is_unlimited(int $user_id): bool {
        return $this->credits->is_unlimited($user_id);
    }

    public function can_afford(int $user_id, $params): bool {
        $cost = is_array($params) ? $this->estimate($params) : (int) $params;
        return $this->credits->can_afford($user_id, $cost);
    }

    public function deduct(int $user_id, int $cost, string $label = 'Music generation'): array {
        return $this->credits->deduct($user_id, $cost, $label);
    }

    public function estimate(array $params): int {
        $duration = (int) ($params['duration'] ?? 120);
        $base     = 20;
        $quality  = ($params['audio_quality'] ?? 'standard') === 'high' ? 10 : 0;
        return $base + (int) floor($duration / 30) * 5 + $quality;
    }
}
