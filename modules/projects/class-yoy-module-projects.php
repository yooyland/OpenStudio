<?php
if (!defined('ABSPATH')) exit;

/**
 * Projects module — workspace over Gallery Asset references.
 * REST registration is delegated to YooY_Projects_REST (idempotent).
 */
final class YooY_Module_Projects extends YooY_Module_Base {

    /** @var YooY_Project_Store */
    private $store;

    public function __construct() {
        $this->store = new YooY_Project_Store();
    }

    public function id(): string {
        return 'projects';
    }

    public function name(): string {
        return 'Projects';
    }

    public function description(): string {
        return 'User project workspace and generation history.';
    }

    public function version(): string {
        return '1.2.0';
    }

    public function register_rest_routes(): void {
        if (!class_exists('YooY_Projects_REST')) {
            require_once __DIR__ . '/includes/class-projects-rest.php';
        }
        YooY_Projects_REST::register_routes();
    }

    /** Expose store for internal reuse (dashboard, diagnostics). */
    public function store(): YooY_Project_Store {
        return $this->store;
    }
}
