# Credits Transaction (Design — not implemented)

Future atomic unit for Studio generations that touch Credits + Gallery (+ Projects / Community / Marketplace / Cloud Sync).

**Status:** Extension point only. Translator Phase 2 still uses explicit Save → Deduct.

## Target flow

```
BEGIN transaction
  → Provider call
  → Language Asset / Gallery save
  → Credits deduct (ledger)
  → optional Project / Community / Marketplace hooks
COMMIT

on failure → ROLLBACK (refund ledger + compensate side effects)
```

## Core interface (stub)

`plugin/yooy-ai-studio/includes/core/interface-yoy-credits-transaction.php`

- `YooY_Credits_Transaction_Interface`
- **Not** bootstrapped in `yoy-ai-studio.php`
- **Not** called by Translator / Image / Video yet

## Design rules

1. Reuse `YooY_Credits_Service` — do not invent a second ledger.
2. Gallery remains Source of Truth for assets.
3. Rollback uses existing `adjust_balance` / refund patterns.
4. Unlimited Admin short-circuits deduct inside the same transaction API.

## Out of scope (now)

- DB transactions / locking
- Distributed saga across Cloud Sync
- Changing Image/Video deduct-before-gallery order
