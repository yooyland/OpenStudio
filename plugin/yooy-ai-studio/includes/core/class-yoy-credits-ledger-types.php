<?php
if (!defined('ABSPATH')) exit;

/**
 * Documented ledger type vocabulary (NOT enforced at write time yet).
 * See docs/CREDITS_LEDGER_TYPES.md.
 */
final class YooY_Credits_Ledger_Types {

    const DEDUCT       = 'deduct';
    const GRANT        = 'grant';
    const PURCHASE     = 'purchase';
    const REFUND       = 'refund';
    const BONUS        = 'bonus';
    const PROMOTION    = 'promotion';
    const SUBSCRIPTION = 'subscription';
    const MARKETPLACE  = 'marketplace';
    const SYSTEM       = 'system';
    const ADMIN        = 'admin';

    /**
     * @return string[]
     */
    public static function all(): array {
        return [
            self::DEDUCT,
            self::GRANT,
            self::PURCHASE,
            self::REFUND,
            self::BONUS,
            self::PROMOTION,
            self::SUBSCRIPTION,
            self::MARKETPLACE,
            self::SYSTEM,
            self::ADMIN,
        ];
    }
}
