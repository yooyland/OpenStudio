<?php
if (!defined('ABSPATH')) exit;

interface YooY_Voice_Provider_Interface {

    public function id(): string;

    public function name(): string;

    public function models(): array;

    public function speak(array $params): array;

    public function clone_voice(array $params): array;

    public function status(string $job_id): array;
}
