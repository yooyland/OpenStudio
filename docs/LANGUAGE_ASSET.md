# Language Asset

YooY AI Studio 공통 **Language Asset** 규약이다.

Translator는 단독 번역 결과가 아니라 Language Intelligence Engine이 생성하는
Language Asset의 첫 Producer이다.

## Platform Asset taxonomy (6)

| Asset family | Gallery `type` (examples) | Producers |
|--------------|---------------------------|-----------|
| Image Asset | `image` | Image Studio |
| Video Asset | `video` | Video Studio |
| Music Asset | `music` | Music Studio |
| Voice Asset | `voice` | Voice Studio |
| Language Asset | `translation`, `writing`, … | Translator, Writing, OCR, PDF, Subtitle, Rewrite, Summarize |
| Avatar Asset | `avatar` | Avatar Studio |

모든 Asset은 동일한 흐름을 공유한다:

```
Studio Engine → Gallery Store → History (filter) → Projects → Credits → Community(optional)
```

Language 입력은 Source Type UI 너머 **Input Adapter → Content Extractor → Normalized Content**
경로로 Engine에 들어간다. 상세: `docs/AI_INPUT_ADAPTER.md`.

새 Store / Gallery / Credits / History / Projects를 만들지 않는다.

## Identity

| Field | Status | Notes |
|-------|--------|--------|
| Gallery `id` | **Active** | e.g. `tr_<uuid>` — current SoT key for History/Projects |
| `meta.asset_uuid` | **Reserved** | Stable Language Asset UUID for cross-studio chains (not written yet) |
| `meta.parent_asset_uuid` | **Reserved** | Parent in OCR→Translate→Rewrite→Summary→Subtitle chain |

Gallery `id` remains the operational key. `asset_uuid` is for future chain graphs that must survive gallery_id remaps.

## Canonical Language fields (현재 저장)

| Field | Where | Notes |
|-------|--------|--------|
| `type` | top | `translation` (writing 등은 기존 type 유지) |
| `prompt` / `user_prompt` | top | source text |
| `provider` / `model` | top | |
| `credits_used` | top | |
| `favorite` / `public` / `created_at` | top | |
| `meta.translated_text` | meta | |
| `meta.source_language` | meta | |
| `meta.target_language` | meta | |
| `meta.mode` | meta | |
| `meta.detected_language` | meta | |
| `meta.character_count` | meta | |
| `meta.project_id` | meta | Projects link |
| `meta.fallback_*` | meta | observability |
| `meta.source_type` | meta | `text` (active); file/website/… planned |

상세 Source Type: `docs/TRANSLATOR_SOURCE_TYPES.md`

`YooY_Gallery_Store::normalize()`는 `item.meta` 배열을 **통째로 보존**한다.

## Reserved Metadata (미저장)

| Key | Purpose |
|-----|---------|
| `meta.asset_uuid` | Language Asset UUID (chain identity) |
| `meta.parent_asset_uuid` | Parent Language Asset UUID |
| `meta.parent_asset_id` | Parent Gallery id (legacy alias) |
| `meta.revision` | Revision number |
| `meta.revision_of` | Previous asset uuid/id |
| `meta.origin` | Producer: translator / ocr / rewrite / … |
| `meta.asset_origin` | Alias of origin (compat) |
| `meta.asset_category` | e.g. `language` |
| `meta.asset_version` | Schema / producer version |
| `meta.asset_source` | Input channel (paste, upload, url, …) |
| `meta.pipeline` | Ordered stage ids |
| `meta.pipeline_step` | Current step id |
| `meta.workflow_id` | Multi-step workflow instance |
| `meta.source_url` … `processing_status` | Input/output source fields — see `docs/TRANSLATOR_SOURCE_TYPES.md` |

### Parent / Chain (설계만)

```
OCR ──parent_asset_uuid──► Translation ──► Rewrite ──► Summary ──► Subtitle
```

### Revision (설계만)

1. **New Asset** — 새 Gallery id (현재 Translator 동작)
2. **Revision** — `revision` + `revision_of` (미구현)

## Credits rule (Language Asset)

1. Mock / Mock Fallback → 무료
2. Real provider + **Gallery 저장 성공** → `YooY_Credits_Service::deduct`
3. 저장 실패 → 차감 금지
4. Unlimited Admin → deducted 0
5. Future atomicity → `docs/CREDITS_TRANSACTION.md` (미구현)

## Cost Strategy (설계만)

Provider별 요금: `interface-translator-cost-strategy.php` (미연결).
현재 임시식: `max(1, ceil(chars/500))` for OpenAI.

## Source of truth

- Persist: `YooY_Gallery_Store` (`yoy_gallery_items`)
- History: `list(type=translation)` filter
- Projects: `YooY_Gallery_Actions::save_to_project`
- Credits: `YooY_Credits_Service`
- Ledger types: `docs/CREDITS_LEDGER_TYPES.md`
