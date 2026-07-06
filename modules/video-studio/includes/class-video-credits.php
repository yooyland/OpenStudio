<?php
if (!defined('ABSPATH')) exit;

final class YooY_Video_Credits {

    private YooY_Studio_Credits $credits;

    public function __construct(?YooY_Studio_Credits $credits = null) {
        $this->credits = $credits ?? new YooY_Studio_Credits('video-studio');
    }

    public function service(): YooY_Studio_Credits {
        return $this->credits;
    }

    public function estimate(array $params): int {
        $duration = (int) ($params['duration'] ?? 5);
        $quality  = $params['quality'] ?? 'standard';
        $base     = ['draft' => 20, 'standard' => 50, 'pro' => 100][$quality] ?? 50;
        return $base + max(0, $duration - 5) * 5;
    }

    public function can_afford(int $user_id, array $params): bool {
        return $this->credits->can_afford($user_id, $this->estimate($params));
    }

    public function deduct(int $user_id, int $cost, string $label = 'Video generation'): array {
        return $this->credits->deduct($user_id, $cost, $label);
    }
}
