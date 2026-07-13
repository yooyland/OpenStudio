<?php
if (!defined('ABSPATH')) exit;

/**
 * WordPress registration welcome — Free plan + 100 credits grant.
 */
final class YooY_User_Welcome {

    public static function register(): void {
        add_action('user_register', [__CLASS__, 'on_user_register'], 10, 1);
    }

    public static function on_user_register(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        $credits = new YooY_Credits_Service();
        $credits->grant_welcome_bonus($user_id);
    }
}
