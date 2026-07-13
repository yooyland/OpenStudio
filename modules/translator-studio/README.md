# Translator Studio (Version 1 — Phase 1)

Context-aware text translation module for YooY AI Studio.

## Phase 1 scope

- Studio Shell menu + SPA mount
- Languages / modes / Mock provider
- `POST /translator-studio/translate`
- `GET /translator-studio/languages`
- UI: dual-panel editor, swap, copy, reset, loading/errors

## Not in Phase 1

- Gallery / My Works save
- History
- Credits ledger deduct
- OpenAI / Google / DeepL
- Admin settings
- OCR / speech / documents

## REST

Namespace: `yoy-ai-studio/v1`

| Method | Path | Auth |
|--------|------|------|
| GET | `/translator-studio/config` | public |
| GET | `/translator-studio/languages` | public |
| GET | `/translator-studio/modes` | public |
| GET | `/translator-studio/providers` | public |
| POST | `/translator-studio/translate` | logged-in + nonce |

## Manual checklist

1. Activate plugin — no fatals
2. Left nav shows Translator (번역)
3. SPA open — no JS console errors
4. Languages load
5. Mock translate known KO↔EN phrases
6. Empty text blocked
7. Same source/target blocked
8. 20,000 char limit
9. Duplicate request blocked while in flight
10. Image / Video / Music / Voice / Gallery / Projects still work

## Next (Phase 2)

- `type=translation` Gallery Store save (relax `has_valid_asset` for translation only)
- History via Gallery
- Credits deduct on success
- OpenAI chat translator provider
