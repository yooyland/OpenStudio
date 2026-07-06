<?php
if (!defined('ABSPATH')) exit;

final class YooY_Core_Engine {

    private static ?self $instance = null;

    private YooY_Module_Registry $registry;

    private bool $booted = false;

    private function __construct() {
        $this->registry = new YooY_Module_Registry();
    }

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void {
        if ($this->booted) {
            return;
        }

        $this->load_modules();
        $this->init_modules();
        $this->booted = true;

        do_action('yoy_ai_studio_core_booted', $this);
    }

    public function registry(): YooY_Module_Registry {
        return $this->registry;
    }

    public function module(string $id): ?YooY_Module_Interface {
        return $this->registry->get($id);
    }

    public function register_module(YooY_Module_Interface $module): void {
        $this->registry->register($module);
    }

    public function version(): string {
        return YOY_AI_STUDIO_VERSION;
    }

    public function status(): array {
        return [
            'engine'     => 'YooY AI Studio Core Engine',
            'version'    => $this->version(),
            'modules'    => $this->registry->count(),
            'module_ids' => $this->registry->ids(),
            'rest_base'  => rest_url('yoy-ai-studio/v1'),
            'providers'  => is_dir(YOY_AI_STUDIO_PROVIDERS_DIR),
        ];
    }

    private function load_modules(): void {
        if (!is_dir(YOY_AI_STUDIO_MODULES_DIR)) {
            return;
        }

        $files = glob(YOY_AI_STUDIO_MODULES_DIR . '*/module.php');
        if (!$files) {
            return;
        }

        sort($files);

        foreach ($files as $file) {
            if (!is_readable($file)) {
                continue;
            }

            $module = include $file;
            if ($module instanceof YooY_Module_Interface) {
                $this->registry->register($module);
            }
        }
    }

    private function init_modules(): void {
        foreach ($this->registry->all() as $module) {
            $module->init($this);
        }
    }
}
