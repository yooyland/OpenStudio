# YooY AI Studio Architecture

> **Canonical map:** [`docs/ARCHITECTURE_BIBLE.md`](ARCHITECTURE_BIBLE.md)  
> This file is a short overview. Prefer the Bible for design decisions.

## Product principle
YooY AI Studio is not a single generator. It is an AI Creator OS.

## Core Engine
`YooY_Core_Engine` is the central hub that boots, registers, and connects all modules.

- `plugin/yooy-ai-studio/includes/core/` — Engine, Registry, REST Controller
- `modules/*/module.php` — Module entry points (auto-discovered)
- REST namespace: `yoy-ai-studio/v1`

### Connected modules
- AI Router — provider selection and failover
- **Video / Image / Music / Avatar / Voice / Writing / Translator Studios**
- Credits, Gallery, Projects, Prompt Library, Marketplace, Community, Settings, Admin Console

## Core themes (see Bible)

| Theme | Doc |
|-------|-----|
| Asset taxonomy (6) | `LANGUAGE_ASSET.md` |
| Language Engine + Input Adapters | `AI_INPUT_ADAPTER.md`, `TRANSLATOR_SOURCE_TYPES.md` |
| Source Authority (internal) | `SOURCE_AUTHORITY.md` |
| Credits Transaction / ledger types | `CREDITS_TRANSACTION.md`, `CREDITS_LEDGER_TYPES.md` |

## Result lifecycle
Generate → Gallery Asset → History / My Works → Projects → Credits → optional Community / Marketplace.
