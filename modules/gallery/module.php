<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/class-gallery-store.php';
require_once __DIR__ . '/includes/class-gallery-aggregator.php';
require_once __DIR__ . '/includes/class-gallery-actions.php';
require_once __DIR__ . '/class-yoy-module-gallery.php';

if (!function_exists('yoy_gallery_capture')) {
    function yoy_gallery_capture(int $user_id, array $entry, string $type, string $studio): void {
        $module = YooY_Core_Engine::instance()->registry()->get('gallery');
        if ($module instanceof YooY_Module_Gallery) {
            $module->capture_item($user_id, $entry, $type, $studio);
        }
    }
}

return new YooY_Module_Gallery();
