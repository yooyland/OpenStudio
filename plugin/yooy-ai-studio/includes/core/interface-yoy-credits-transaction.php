<?php
if (!defined('ABSPATH')) exit;

/**
 * Future atomic Credits + Asset transaction (DESIGN ONLY — not wired).
 *
 * See docs/CREDITS_TRANSACTION.md.
 * Do not require this file from yoy-ai-studio.php until a real implementation ships.
 */
interface YooY_Credits_Transaction_Interface {

    /**
     * @param array $context studio, provider, estimate, gallery_type, …
     * @return string transaction id
     */
    public function begin(int $user_id, array $context): string;

    /**
     * Commit after provider + asset save + deduct succeeded.
     */
    public function commit(string $transaction_id): void;

    /**
     * Compensate ledger / side effects after a failed step.
     */
    public function rollback(string $transaction_id): void;
}
