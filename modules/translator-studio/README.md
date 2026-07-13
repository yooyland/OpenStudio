# Translator Studio

Language Intelligence Engine for YooY AI Studio. Produces **Language Assets** into Gallery.

Baseline snapshot: `v11.15.1-development-snapshot` (`1d7cf9d`).
Release candidate: **11.16.0**.

## Phase status

| Phase | Scope | Status |
|-------|--------|--------|
| 1 | Mock + OpenAI + same-lang gate | Code + prior live OpenAI smoke |
| 2-A | Gallery + History | Code complete |
| 2-B | My Works + Projects | Code complete |
| 2-C | Credits ledger | Code complete |
| **2-D** | RC verify + version + ZIP + tag | Packaging in progress — **live Whois pending operator** |

## Phase 2-D live checklist (operator — not claimed here)

1. OpenAI translate → text result OK
2. Credits balance decreases; ledger `deduct` row
3. Mock / Mock Fallback → balance unchanged
4. Gallery `type=translation` row; History groups/badges
5. Project 저장 → Project detail shows translation
6. Admin Unlimited → deducted 0
7. Regression: Image / Video / Music / Voice / Avatar / Writing / Gallery / Projects / Credits / Marketplace / Community / Admin Console

## Extension points (not implemented)

- `docs/CREDITS_TRANSACTION.md`
- `docs/CREDITS_LEDGER_TYPES.md`
- `docs/LANGUAGE_ASSET.md` (asset_uuid chain)
- Cost Strategy interface under `includes/cost/`

## Reuse

No new Gallery / Credits / History / Projects stores. Core only.
