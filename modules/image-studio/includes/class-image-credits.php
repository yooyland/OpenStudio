<?php
if (!defined('ABSPATH')) exit;

final class YooY_Image_Credits {

    private YooY_Credits_Service $credits;

    public function __construct(?YooY_Credits_Service $credits = null) {
        $this->credits = $credits ?? new YooY_Credits_Service();
    }

    public function service(): YooY_Credits_Service {
        return $this->credits;
    }

    public function estimate(array $params): int {
        $count   = min(4, max(1, (int) ($params['image_count'] ?? 1)));
        $quality = $params['quality'] ?? 'standard';
        $base    = ['draft' => 5, 'standard' => 10, 'hd' => 20][$quality] ?? 10;
        $mode    = $params['mode'] ?? '';

        if ($mode !== '') {
            return ['edit' => 8, 'upscale' => 15, 'inpaint' => 12, 'outpaint' => 12][$mode] ?? 10;
        }

        return $base * $count;
    }

    public function can_afford(int $user_id, array $params): bool {
        return $this->credits->can_afford($user_id, $this->estimate($params));
    }

    public function deduct(int $user_id, int $cost, string $label = 'Image generation'): array {
        return $this->credits->deduct($user_id, $cost, $label, 'image-studio');
    }
}
