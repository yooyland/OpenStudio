<?php
if (!defined('ABSPATH')) exit;

final class YooY_Studio_Credits {

    private YooY_Credits_Service $service;
    private string $module;

    public function __construct(string $module, ?YooY_Credits_Service $service = null) {
        $this->module  = $module;
        $this->service = $service ?? new YooY_Credits_Service();
    }

    public function service(): YooY_Credits_Service {
        return $this->service;
    }

    public function balance(int $user_id): int {
        return $this->service->balance($user_id);
    }

    public function is_unlimited(int $user_id): bool {
        return $this->service->is_unlimited($user_id);
    }

    public function can_afford(int $user_id, int $cost): bool {
        return $this->service->can_afford($user_id, $cost);
    }

    public function deduct(int $user_id, int $cost, string $label): array {
        return $this->service->deduct($user_id, $cost, $label, $this->module);
    }

    public function snapshot(int $user_id): array {
        return $this->service->snapshot($user_id);
    }
}
