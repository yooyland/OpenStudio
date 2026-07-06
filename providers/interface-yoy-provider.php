<?php
if (!defined('ABSPATH')) exit;

interface YooY_Provider_Interface {

    public function id(): string;

    public function name(): string;

    public function types(): array;

    public function models(): array;

    public function status(string $job_id): array;
}
