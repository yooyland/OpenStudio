# OpenStudio Architecture Bible

**Product:** YooY AI Studio (OpenStudio)  
**Role:** Canonical design philosophy for all future versions  
**Companion docs:** linked below — this file is the map; details live in topic docs.

> YooY AI Studio는 도구 모음이 아니라 **AI Creator Operating System**이다.  
> 새 기능은 새 시스템을 만들지 않고 **기존 Core를 확장**한다.

---

## 1. Product principles

1. **One OS, many Studios** — Image / Video / Music / Voice / Avatar / Writing / Translator share Core.
2. **Reuse over rewrite** — Gallery, Projects, Credits, AI Router, Provider contracts are shared.
3. **Production quality** — Mock providers allowed; demo/placeholder business data forbidden.
4. **Korea-first** — Korean Context + internal Source Authority for Korea-related grounding.
5. **Asset-centric** — Features produce Assets into Gallery; History/Projects/Credits hang off Assets.
6. **Document then extend** — Large structural ideas (Transactions, Input Adapters) are designed before code.

Cursor / team rule: `.cursor/rules/core-architecture-reuse.mdc`

---

## 2. Boot & module map

```
plugin/yooy-ai-studio/yoy-ai-studio.php
  → YooY_Core_Engine::boot()
  → glob modules/*/module.php
  → YooY_Module_Registry
  → YooY_REST_Controller  (namespace yoy-ai-studio/v1)
```

| Layer | Location |
|-------|----------|
| Core | `plugin/yooy-ai-studio/includes/core/` |
| Modules | `modules/*/` |
| Providers | `providers/` |
| Studio UI | `plugin/yooy-ai-studio/assets/`, `templates/` |

**Discovery:** each `module.php` returns a `YooY_Module_Interface` implementation (`YooY_Module_Base`).

---

## 3. Core Engine

`YooY_Core_Engine` — central hub: register modules, expose services, no Studio-specific business logic.

Surrounding core pieces:

| Concern | Class / doc |
|---------|-------------|
| REST | `YooY_REST_Controller` |
| Secrets | `YooY_Secrets` |
| Jobs | `YooY_Job_Store`, `YooY_Job_Normalizer`, `YooY_Job_Status` |
| Credits | `YooY_Credits_Service` |
| Source Authority constants | `YooY_Source_Authority` (internal, no UI) |

---

## 4. AI Router & Providers

```
Studio request
  → Provider Resolver / Catalog
  → Mock or Real Provider
  → Normalized job / result
  → Studio Gallery save
```

| Piece | Path |
|-------|------|
| AI Router module | `modules/ai-router/` (`YooY_AI_Router_Dispatcher`) |
| Catalog | `YooY_Provider_Catalog` |
| Resolver | `YooY_Provider_Resolver` |
| Providers | `providers/*` implementing studio interfaces |

**Rule:** Studio modules do not hardcode a second provider stack. Failover / mock mirrors real schemas.

---

## 5. Platform Asset taxonomy (6)

See `docs/LANGUAGE_ASSET.md`.

| Family | Gallery `type` examples | Producers |
|--------|-------------------------|-----------|
| Image Asset | `image` | Image Studio |
| Video Asset | `video` | Video Studio |
| Music Asset | `music` | Music Studio |
| Voice Asset | `voice` | Voice Studio |
| Language Asset | `translation`, `writing`, … | Translator, Writing, future OCR/Rewrite/… |
| Avatar Asset | `avatar` | Avatar Studio |

Shared lifecycle:

```
Studio Engine → Gallery Store → History (filter) → Projects → Credits → Community (optional)
```

---

## 6. Gallery (Source of Truth for works)

| Item | Value |
|------|--------|
| Class | `YooY_Gallery_Store` |
| Storage | user_meta `yoy_gallery_items` |
| Actions | `YooY_Gallery_Actions` (favorite, project link, delete cleanup) |

**History** is not a separate DB — it is `list(type=…)` (e.g. Translator History = `type=translation`).

**My Works** = Gallery UI (`works` route).

---

## 7. Projects

| Item | Value |
|------|--------|
| Class | `YooY_Project_Store` |
| Storage | user_meta `yoy_projects` |
| Link | Gallery `meta.project_id` + project `assets[]` via `link_gallery_item` |

Do not invent per-studio project tables.

---

## 8. Credits

| Item | Value |
|------|--------|
| Class | `YooY_Credits_Service` |
| Keys | `yoy_credits_balance`, `yoy_credits_ledger`, `yoy_credits_plan`, … |
| Unlimited | `manage_options` → deducted 0 |

Language Asset rule (Translator): **save success → then deduct**; Mock/Fallback free.

Design extensions (not enforced yet):

- `docs/CREDITS_TRANSACTION.md` — atomic begin/commit/rollback
- `docs/CREDITS_LEDGER_TYPES.md` — type vocabulary

---

## 9. Language Intelligence Engine & Input Adapters

Translator is **not** a standalone translator product. It is the Language Intelligence Engine.

```
Input Adapter → Content Extractor → Normalized Content
  → Language Engine → Language Asset → Gallery / Credits
```

Canonical design: **`docs/AI_INPUT_ADAPTER.md`**  
Source Type UI/REST table: **`docs/TRANSLATOR_SOURCE_TYPES.md`**

| Source / Adapter | Extractor (future) | Runtime today |
|------------------|--------------------|---------------|
| Text | Identity | Available |
| File | PDF/DOCX/… Extractors | UI only |
| Website | HTML Extractor | UI only |
| Image | OCR Extractor | UI only |
| Audio | Speech Extractor | UI only |
| Video | Subtitle / media | UI only |
| YouTube | Subtitle Extractor | UI only |

**Extension rule:** add Adapter + Extractor only; do not fork Translator Core / Gallery / Credits.

---

## 10. Marketplace & Community

| Module | Persistence |
|--------|-------------|
| Marketplace | option `yoy_marketplace_catalog` (+ user listings) |
| Community | option `yoy_community_feed` |
| Public works | Gallery-backed public feed helpers |

Feature flags / enable options exist; content comes from Stores, not hardcoded demos.

---

## 11. Korean Context Engine & Source Authority

| Topic | Doc / code |
|-------|------------|
| Source Authority (internal) | `docs/SOURCE_AUTHORITY.md`, `YooY_Source_Authority` |
| Presidency priority | `https://www.president.go.kr/` |
| Korean Context | Studio-level localization hooks; Admin roadmap for dedicated engine |

**No user-facing** Source Authority menus, banners, or settings.

Website Input Adapter (future) must respect Source Authority internally when grounding Korea topics.

---

## 12. Admin Console & Diagnostics

- `modules/admin-console/` + WP admin wrappers
- Providers, credits, diagnostics, official showcase tooling
- Does not replace Studio SaaS UI (Creator OS shell)

UI philosophy: `.cursor/rules/creator-os-saas-ui.mdc`

---

## 13. Cross-cutting design stubs

| Stub | Doc / file | Wired? |
|------|------------|--------|
| Credits Transaction | `docs/CREDITS_TRANSACTION.md`, `interface-yoy-credits-transaction.php` | No |
| Ledger types | `docs/CREDITS_LEDGER_TYPES.md` | Doc only |
| Cost Strategy | `modules/translator-studio/includes/cost/interface-translator-cost-strategy.php` | No |
| Input Adapter / Extractor | `docs/AI_INPUT_ADAPTER.md` | No |
| Language Asset UUID chain | `docs/LANGUAGE_ASSET.md` reserved meta | No |

---

## 14. What never to do

- Second Gallery / History / Credits / Projects store for one Studio
- Per-Source-Type translation engines that bypass Normalized Content
- User UI for Source Authority
- Demo catalogs as production API responses
- PHP 8-only syntax (minimum PHP 7.4) — `.cursor/rules/php-74-compatibility.mdc`

---

## 15. Document index

| Doc | Topic |
|-----|--------|
| `docs/ARCHITECTURE_BIBLE.md` | **This file** — OS map |
| `docs/ARCHITECTURE.md` | Short overview (points here) |
| `docs/LANGUAGE_ASSET.md` | Asset taxonomy + Language meta |
| `docs/AI_INPUT_ADAPTER.md` | Adapter → Extractor → Engine |
| `docs/TRANSLATOR_SOURCE_TYPES.md` | Source Type table, REST, security |
| `docs/SOURCE_AUTHORITY.md` | Korea official sources |
| `docs/CREDITS_TRANSACTION.md` | Atomic credits design |
| `docs/CREDITS_LEDGER_TYPES.md` | Ledger type vocabulary |
| `docs/RELEASE_NOTES_*.md` | Version notes |

---

## 16. Evolution rule

기능이 늘수록 문서와 Core 계약을 먼저 갱신한다.

```
Idea → Architecture Bible / topic doc → minimal Core extension → Studio UI
```

OpenStudio grows by **architecture documentation + Core reuse**, not by accumulating parallel systems.
