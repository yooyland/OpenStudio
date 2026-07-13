# OpenStudio Architecture Bible

**Product:** YooY AI Studio (OpenStudio)  
**Role:** Canonical design philosophy for all Studio development  
**Status:** Architecture finalized for Canonical Asset Model (docs phase)  
**Entry point:** [`README.md`](../README.md)  
**Companion:** [`UNIVERSAL_ASSET.md`](UNIVERSAL_ASSET.md), [`AI_INPUT_ADAPTER.md`](AI_INPUT_ADAPTER.md), [`LANGUAGE_ASSET.md`](LANGUAGE_ASSET.md), [`SOURCE_AUTHORITY.md`](SOURCE_AUTHORITY.md)

> YooY AI Studio는 도구 모음이 아니라 **AI Creator Operating System**이다.  
> 새 기능은 새 시스템을 만들지 않고 **기존 Core를 확장**한다.

---

## 0. Official architecture decisions (adopted)

1. **`YooY_Gallery_Store` = Canonical Asset Store.**  
   Do not create a Universal Asset Store.
2. **Universal Asset** is an **architecture concept**, not a database.  
   Runtime may later expose a **Thin Facade (Repository)** only — **not implemented in this phase**.
3. Image / Video / Music / Voice / Language / Avatar / Writing, and future OCR / Document / Website / YouTube outputs, all persist through Gallery.
4. **Projects / Community / Marketplace** reference Assets by `gallery_id`; they do not clone Asset bodies.
5. **Writing Studio** does not invent a separate Asset structure — it reuses Language Intelligence Engine → Language Asset → Gallery.
6. **README** is the official product Entry Point; this Bible is the design map for every Studio.

Cursor / team rule: `.cursor/rules/core-architecture-reuse.mdc`

---

## 1. Product principles

1. **One OS, many Studios** — Image / Video / Music / Voice / Avatar / Writing / Translator share Core.
2. **Reuse over rewrite** — Gallery, Projects, Credits, AI Router, Provider contracts are shared.
3. **Production quality** — Mock providers allowed; demo/placeholder business data forbidden.
4. **Korea-first** — Korean Context + internal Source Authority for Korea-related grounding.
5. **Asset-centric** — Features produce Assets into Gallery; History / Projects / Credits hang off Assets.
6. **Document then extend** — Large structural ideas are designed before code.

### One of each

| One | Meaning |
|-----|---------|
| Core | `YooY_Core_Engine` · module discovery · REST |
| AI Router | Provider selection · failover · Mock/Real contract |
| Gallery | Canonical Asset Store |
| Projects | Gallery Asset references |
| Credits | `YooY_Credits_Service` |
| Marketplace | `gallery_id` listings |
| Community | Gallery-backed public feed |

---

## 2. Boot & module map

```text
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

| Concern | Class / doc |
|---------|-------------|
| REST | `YooY_REST_Controller` |
| Secrets | `YooY_Secrets` |
| Jobs | `YooY_Job_Store`, `YooY_Job_Normalizer`, `YooY_Job_Status` |
| Credits | `YooY_Credits_Service` |
| Source Authority constants | `YooY_Source_Authority` (internal, no UI) |

---

## 4. AI Router & Providers

```text
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

## 5. Universal Asset & Platform taxonomy

**Canonical doc:** [`UNIVERSAL_ASSET.md`](UNIVERSAL_ASSET.md)

- **Gallery Store** = Canonical Asset Store (SoT)
- **Universal Asset** = shared naming / lifecycle / mapping convention
- **Thin Facade** = optional future read layer only (not built yet)

| Family | Gallery `type` examples | Producers |
|--------|-------------------------|-----------|
| Image Asset | `image` | Image Studio |
| Video Asset | `video` | Video Studio |
| Music Asset | `music` | Music Studio |
| Voice Asset | `voice` | Voice Studio |
| Language Asset | `translation`, `writing`, … | Translator, **Writing**, future OCR / Rewrite / Summarize / Subtitle |
| Avatar Asset | `avatar` | Avatar Studio |

Shared lifecycle:

```text
Studio Engine → Gallery Store → History (filter) → Projects → Credits → Community / Marketplace (optional)
```

Language pipeline (target shape):

```text
AI Router
      │
Input Adapter
      │
Language Intelligence Engine
      │
Language Asset
      │
Canonical Asset Store (Gallery)
      │
Projects / Community / Marketplace
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
Projects store **references + snapshots**, never a second Asset body.

---

## 8. Credits

| Item | Value |
|------|--------|
| Class | `YooY_Credits_Service` |
| Keys | `yoy_credits_balance`, `yoy_credits_ledger`, `yoy_credits_plan`, … |
| Unlimited | `manage_options` → deducted 0 |

Language Asset rule (Translator): **save success → then deduct**; Mock/Fallback free.

Design extensions (not enforced yet):

- `CREDITS_TRANSACTION.md` — atomic begin/commit/rollback
- `CREDITS_LEDGER_TYPES.md` — type vocabulary

---

## 9. Language Intelligence Engine, Input Adapters & Writing

Translator is **not** a standalone translator product. It is the **Language Intelligence Engine**.

```text
Input Adapter → Content Extractor → Normalized Content
  → Language Engine → Language Asset → Gallery / Credits
```

Canonical design: [`AI_INPUT_ADAPTER.md`](AI_INPUT_ADAPTER.md)  
Language Asset: [`LANGUAGE_ASSET.md`](LANGUAGE_ASSET.md)  
Source Type UI/REST table: [`TRANSLATOR_SOURCE_TYPES.md`](TRANSLATOR_SOURCE_TYPES.md)

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

### Writing Studio (mandatory)

Writing Studio는 **별도 Asset Store / Asset family DB를 만들지 않는다.**

| Writing must use | Must not create |
|------------------|-----------------|
| Language Intelligence Engine (shared path) | Writing-only Gallery |
| Language Asset (`type` e.g. `writing`) | Writing History Store |
| Canonical Gallery Store | Writing Credits ledger |
| Projects / Credits / Community via `gallery_id` | Parallel Writing Asset schema |

```text
Writing Studio → (Language Engine path) → Language Asset → Gallery
```

---

## 10. Marketplace & Community

| Module | Persistence | Asset link |
|--------|-------------|------------|
| Marketplace | option `yoy_marketplace_catalog` (+ user listings) | `gallery_id` + listing snapshot |
| Community | option `yoy_community_feed` | `gallery_id` + feed snapshot |
| Public works | Gallery-backed public feed helpers | Gallery SoT |

Feature flags / enable options exist; content comes from Stores, not hardcoded demos.  
**Never clone full Asset payloads** into Marketplace/Community as a second SoT.

---

## 11. Korean Context Engine & Source Authority

| Topic | Doc / code |
|-------|------------|
| Source Authority (internal) | [`SOURCE_AUTHORITY.md`](SOURCE_AUTHORITY.md), `YooY_Source_Authority` |
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
| Universal Asset Thin Facade | `UNIVERSAL_ASSET.md` | **No** (docs only) |
| Credits Transaction | `CREDITS_TRANSACTION.md`, `interface-yoy-credits-transaction.php` | No |
| Ledger types | `CREDITS_LEDGER_TYPES.md` | Doc only |
| Cost Strategy | `modules/translator-studio/includes/cost/interface-translator-cost-strategy.php` | No |
| Input Adapter / Extractor (non-Text) | `AI_INPUT_ADAPTER.md` | Text path only |
| Language Asset UUID chain | `LANGUAGE_ASSET.md` reserved meta | No |

---

## 14. What never to do

- Second Gallery / History / Credits / Projects store for one Studio
- **New Universal Asset Store** (Gallery is already Canonical)
- Writing-specific Asset / History / Credits stores
- Per-Source-Type translation engines that bypass Normalized Content
- Cloning Asset bodies into Projects / Community / Marketplace
- User UI for Source Authority
- Demo catalogs as production API responses
- PHP 8-only syntax (minimum PHP 7.4) — `.cursor/rules/php-74-compatibility.mdc`

---

## 15. Document index

| Doc | Topic |
|-----|--------|
| `README.md` | **Official Entry Point** — product face |
| `ARCHITECTURE_BIBLE.md` | **This file** — OS map / Studio law |
| `ARCHITECTURE.md` | Short overview |
| `UNIVERSAL_ASSET.md` | Canonical Asset Store · Facade rules |
| `LANGUAGE_ASSET.md` | Language Asset meta |
| `AI_INPUT_ADAPTER.md` | Adapter → Extractor → Engine |
| `TRANSLATOR_SOURCE_TYPES.md` | Source Type table, REST, security |
| `SOURCE_AUTHORITY.md` | Korea official sources |
| `CREDITS_TRANSACTION.md` | Atomic credits design |
| `CREDITS_LEDGER_TYPES.md` | Ledger type vocabulary |
| `CONTRIBUTING.md` / `ROADMAP.md` | Dev rules / version entry |

---

## 16. Evolution rule

기능이 늘수록 문서와 Core 계약을 먼저 갱신한다.

```text
Idea → Architecture Bible / topic doc → minimal Core extension → Studio UI
```

다음 안정 확장 순서(권장):

```text
README + Bible + UNIVERSAL_ASSET (done)
  → Website Adapter
  → File / OCR / other Adapters
  → optional Thin Asset Facade (approval)
```

OpenStudio grows by **architecture documentation + Core reuse**, not by accumulating parallel systems.  
이 단계부터는 기능 나열보다 **설계 완성도**가 우선이다.
