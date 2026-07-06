<?php
if (!defined('ABSPATH')) exit;

final class YooY_Job_Status {

    public const QUEUED    = 'queued';
    public const RUNNING   = 'running';
    public const COMPLETED = 'completed';
    public const FAILED    = 'failed';

    public static function all(): array {
        return [self::QUEUED, self::RUNNING, self::COMPLETED, self::FAILED];
    }

    public static function is_terminal(string $status): bool {
        return in_array($status, [self::COMPLETED, self::FAILED], true);
    }

    public static function normalize(string $status): string {
        $status = strtolower(trim($status));

        return match ($status) {
            'queued', 'pending', 'submitted', 'created' => self::QUEUED,
            'running', 'processing', 'in_progress', 'in-progress', 'active' => self::RUNNING,
            'completed', 'complete', 'succeeded', 'success', 'done' => self::COMPLETED,
            'failed', 'error', 'cancelled', 'canceled' => self::FAILED,
            default => in_array($status, self::all(), true) ? $status : self::RUNNING,
        };
    }
}
