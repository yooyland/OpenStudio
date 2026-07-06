<?php
if (!defined('ABSPATH')) exit;

final class YooY_Module_Registry {

    /** @var array<string, YooY_Module_Interface> */
    private array $modules = [];

    public function register(YooY_Module_Interface $module): void {
        $id = $module->id();
        if (isset($this->modules[$id])) {
            return;
        }
        $this->modules[$id] = $module;
    }

    public function get(string $id): ?YooY_Module_Interface {
        return $this->modules[$id] ?? null;
    }

    /** @return array<string, YooY_Module_Interface> */
    public function all(): array {
        return $this->modules;
    }

    public function ids(): array {
        return array_keys($this->modules);
    }

    public function configs(): array {
        $configs = [];
        foreach ($this->modules as $module) {
            $configs[] = $module->get_config();
        }
        return $configs;
    }

    public function count(): int {
        return count($this->modules);
    }
}
