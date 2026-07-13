# Translator Studio

Language Intelligence Engine for YooY AI Studio. Produces **Language Assets** into Gallery.

Baseline snapshot: `v11.15.1-development-snapshot` (`1d7cf9d`).
Release candidate: **11.16.0**.

## Source Types (= Input Adapters)

| Source Type | 상태 | 역할 |
|-------------|------|------|
| Text | Available | Identity Adapter |
| File | Planned | Document Adapter + Extractors |
| Website | Planned | HTML Adapter + Extractor |
| Image | Planned | OCR Adapter |
| Audio | Planned | Speech Adapter |
| Video | Planned | Media / subtitle Adapter |
| YouTube | Planned | Subtitle Adapter |

설계: `docs/AI_INPUT_ADAPTER.md` · 표/REST: `docs/TRANSLATOR_SOURCE_TYPES.md` · OS: `docs/ARCHITECTURE_BIBLE.md`

새 Source Type은 Adapter + Extractor만 추가한다. Translator Core / Gallery / Credits는 수정하지 않는다.

## Phase status

| Phase | Scope | Status |
|-------|--------|--------|
| 1 | Mock + OpenAI + same-lang gate | Code + prior live OpenAI smoke |
| 2-A | Gallery + History | Code complete |
| 2-B | My Works + Projects | Code complete |
| 2-C | Credits ledger | Code complete |
| 2-D | RC package 11.16.0 | Packaging done — live Whois pending |
| **Source Types** | Multi-input foundation (UI + meta + validator) | **Code complete** — Text only runtime |

## Extension points (not implemented)

- `docs/ARCHITECTURE_BIBLE.md` — OS canonical map
- `docs/AI_INPUT_ADAPTER.md` — Adapter → Extractor → Engine
- `docs/CREDITS_TRANSACTION.md`
- `docs/CREDITS_LEDGER_TYPES.md`
- `docs/LANGUAGE_ASSET.md` (asset_uuid chain)
- Cost Strategy interface under `includes/cost/`
- File/Website/Image/Audio/Video/YouTube extract pipelines

## Reuse

No new Gallery / Credits / History / Projects stores. Core only.
