<?php
if (!defined('ABSPATH')) exit;

interface YooY_Music_Provider_Interface {

    public function id(): string;

    public function name(): string;

    public function models(): array;

    public function generate(array $params): array;

    public function status(string $job_id): array;
}
