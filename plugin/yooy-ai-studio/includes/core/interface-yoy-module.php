<?php
if (!defined('ABSPATH')) exit;

interface YooY_Module_Interface {

    public function id(): string;

    public function name(): string;

    public function description(): string;

    public function version(): string;

    public function init(YooY_Core_Engine $core): void;

    public function register_rest_routes(): void;

    public function get_config(): array;
}
