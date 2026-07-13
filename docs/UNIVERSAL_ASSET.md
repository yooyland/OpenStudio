# Universal Asset

**Status:** Official architecture (docs only — Thin Facade **not** implemented yet)  
**Canonical storage:** `YooY_Gallery_Store` (`user_meta` `yoy_gallery_items`)  
**Related:** `ARCHITECTURE_BIBLE.md`, `LANGUAGE_ASSET.md`, `AI_INPUT_ADAPTER.md`

> Universal Asset은 **저장소가 아니다.**  
> Gallery Store가 Canonical Asset Store이며, Universal Asset은 그 위의 **Architecture 개념**이다.

---

## 1. Canonical Asset Store

| Item | Value |
|------|--------|
| Store | `YooY_Gallery_Store` |
| Persistence | WordPress `user_meta` key `yoy_gallery_items` |
| Actions | `YooY_Gallery_Actions` (favorite, project link, delete) |
| Role | **Sole Source of Truth** for all Studio works |

### Decision

| Option | Verdict |
|--------|---------|
| **A** Gallery = Canonical Asset Store + optional Thin Facade | **Adopted** |
| **B** Docs-only convention | Layer that this file formalizes |
| **C** New Universal Asset Store / DB / ID scheme | **Rejected** |

Do **not** create:

- `Universal_Asset_Store`
- Parallel Asset tables / options
- Studio-specific Asset databases
- A second ID system that replaces Gallery `id`

---

## 2. Universal Asset 규약

모든 Studio 결과물은 **하나의 Asset 규약**을 따른다.

1. Asset은 Gallery Item으로 저장된다.
2. `id` (Gallery item id)가 플랫폼 전역 참조 키다.
3. Asset family는 `type`으로 구분한다.
4. 타입 전용 필드는 top-level을 늘리지 않고 **`meta`**에 둔다.
5. Projects / Community / Marketplace는 Asset을 **복제하지 않고** `gallery_id`로 참조한다.
6. History는 별도 Store가 아니라 Gallery `filter(type=…)`.
7. Job Store(`yoy_job_store`)는 실행 상태용이며 Asset SoT가 아니다.

### Platform Asset families

| Family | Gallery `type` examples | Notes |
|--------|-------------------------|--------|
| Image Asset | `image` | Image Studio |
| Video Asset | `video` | Video Studio |
| Music Asset | `music` | Music Studio |
| Voice Asset | `voice` | Voice Studio |
| Language Asset | `translation`, `writing`, … | Translator, Writing, future OCR / Rewrite / Summarize / Subtitle |
| Avatar Asset | `avatar` | Avatar Studio |

입력 매체(OCR / Document / Website / YouTube)는 **Asset family가 아니라 Input Adapter**다.  
추출 결과는 Normalized Content → Engine → 위 family 중 하나로 Gallery에 저장된다.

---

## 3. Gallery 필드 매핑

| Universal concept | Gallery field today | Notes |
|-------------------|---------------------|--------|
| `id` | `id` | Canonical reference key |
| `type` | `type` | Family discriminator |
| `title` | `title` | |
| `prompt` | `prompt` / `meta.user_prompt` | |
| `provider` / `model` | top-level | |
| `user_id` | `user_id` | |
| `project_id` | `meta.project_id` | |
| `credits_used` | `credits_used` | |
| `favorite` | `favorite` | |
| `visibility` | `public` / related flags | |
| `attachment_id` | `attachment_id` | Media attachment |
| `primary_asset_url` | `image_url` / `output_url` / enrich `asset_url` | Naming varies — Facade may alias |
| `metadata` | `meta` | Opaque bag; **preserve wholesale** |
| `source_type` | `meta.source_type` | Language / Translator |
| `asset_uuid` | reserved `meta` | Not written yet |
| `parent_asset_uuid` / `revision` / `workflow_id` / `pipeline_step` | reserved `meta` | Pipeline / Language chain |

**Rule:** Studio-specific payloads (`translated_text`, `negative_prompt`, lyrics, etc.) live in `meta`, not a parallel store.

---

## 4. Asset Lifecycle

```text
Generate / Import
  → Store Asset (Gallery = Canonical Asset Store)
  → My Works (Gallery UI)
  → History (Gallery filter by type)
  → Project (gallery_id reference + snapshot)
  → Community / Marketplace (optional, gallery_id + listing snapshot)
  → Reuse / Remix / Revision
```

Credits hang off the same lifecycle (estimate → save → deduct / refund).  
Ledger may later optionally reference `gallery_id`; that does not create a second Asset SoT.

---

## 5. Thin Facade (Repository) — not implemented

Universal Asset의 런타임 형태는 **Thin Facade**뿐이다.

| Allowed | Forbidden |
|---------|-----------|
| Read aliases (`primary_asset_url`, typed helpers) | New persistence |
| Normalize field access for Studios | New ID scheme |
| Documented `meta` helpers | Dual-write to a second Store |
| Optional class e.g. `YooY_Asset_Repository` wrapping Gallery | Migration that replaces Gallery ids |

**This phase:** documentation only. Do **not** implement the Facade until explicitly approved.

---

## 6. 금지사항

- 새 Universal Asset Store / DB / option 키
- Image / Video / Music / Voice / Language / Avatar / Writing 별도 Asset DB
- Project / Community / Marketplace에 Asset 본문 복제 (snapshot 메타만 허용)
- History 전용 Store
- Source Type / Adapter마다 Gallery 복제
- Job Store를 Asset SoT로 사용
- Gallery `id`를 무시하는 새 전역 Asset UUID 단독 체계 (예약 `meta`는 보조일 뿐)

---

## 7. Studio Mapping

| Producer / input | Uses Canonical Gallery? | Asset family / note |
|------------------|-------------------------|---------------------|
| Image Studio | Yes | Image |
| Video Studio | Yes | Video |
| Music Studio | Yes | Music |
| Voice Studio | Yes | Voice |
| Avatar Studio | Yes | Avatar |
| Writing Studio | Yes | **Language Asset** — no separate Writing Asset Store |
| Translator Studio | Yes | Language Asset (`type=translation`) |
| Future OCR | Yes | Language (via Input Adapter → Engine) |
| Future Document / File | Yes | Language (via File Adapter) |
| Future Website | Yes | Language (via Website Adapter) |
| Future YouTube | Yes | Language (via YouTube Adapter) |
| Projects | Reference only | `gallery_id` |
| Community | Reference only | `gallery_id` |
| Marketplace | Reference only | `gallery_id` |

### Writing Studio (explicit)

Writing은 별도 Asset 구조를 만들지 않는다.

```text
Writing Studio → Language Intelligence Engine (shared) → Language Asset → Gallery
```

Translator와 동일한 Language Asset / Gallery / Credits / Projects 경로를 재사용한다.  
상세: `LANGUAGE_ASSET.md`, Bible §9 / Writing rule.

---

## 8. Platform pipeline (reference)

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

비-Language Studio도 동일하게 **AI Router → Studio Engine → Gallery → Projects / Credits / Community** 패턴을 따른다.

---

## 9. Evolution

```text
Docs (this file + Bible) → optional Thin Facade (approval) → never a second Store
```

다음 확장 우선순위(런타임): Website Adapter부터 Adapter 단위로 추가.  
Asset persistence는 항상 Gallery다.
