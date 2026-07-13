# YooY AI Studio 11.16.0 — Release Notes

**Status:** Production Release Candidate (Translator Language Engine Phase 2)

**Baseline:** `v11.15.1-development-snapshot` (`1d7cf9d`)

## Summary

Translator is the first producer of the shared **Language Asset** pipeline:
Gallery → History (filter) → Projects → Credits (Core ledger).

## Included

- Phase 1: Mock + OpenAI Translator, same-language gate
- Phase 2-A: Gallery `type=translation`, History UI
- Phase 2-B: My Works + Projects via Gallery Actions
- Phase 2-C: Credits estimate / can_afford / deduct / refund / Unlimited Admin
- Phase 2-D: Version bump, packaging, extension-point docs for future atomicity

## Design-only (not runtime)

- Credits Transaction interface — `docs/CREDITS_TRANSACTION.md`
- Ledger type vocabulary — `docs/CREDITS_LEDGER_TYPES.md`
- Cost Strategy interface — not wired
- Language Asset UUID / chain reserved meta — not written

## Install

1. Upload `yooy-ai-studio-11.16.0-php74.zip`
2. Extract to `wp-content/plugins/yooy-ai-studio/`
3. Activate **YooY AI Studio**
4. Shortcode: `[yoy_ai_studio]`

## Requirements

- WordPress 6.x
- PHP 7.4+

## Live verification checklist (operator)

See `modules/translator-studio/README.md` Phase 2-D section.
Code packaging does **not** substitute live Whois verification.
