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

        switch ($status) {
            case 'queued':
            case 'pending':
            case 'submitted':
            case 'created':
                return self::QUEUED;
            case 'running':
            case 'processing':
            case 'rendering':
            case 'in_progress':
            case 'in-progress':
            case 'active':
                return self::RUNNING;
            case 'completed':
            case 'complete':
            case 'succeeded':
            case 'success':
            case 'done':
                return self::COMPLETED;
            case 'failed':
            case 'error':
            case 'cancelled':
            case 'canceled':
                return self::FAILED;
            default:
                return in_array($status, self::all(), true) ? $status : self::RUNNING;
        }
    }
}
