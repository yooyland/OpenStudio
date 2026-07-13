# AI Input Adapter Architecture

**Status:** Design only — not implemented in runtime.  
**Related:** `docs/TRANSLATOR_SOURCE_TYPES.md`, `docs/LANGUAGE_ASSET.md`, `docs/ARCHITECTURE_BIBLE.md`

## Purpose

Source Type(Text / File / Website / Image / Audio / Video / YouTube)는
“입력 UI 모드”가 아니라, 원본을 **Language Intelligence Engine**에 넣기 위한
**Input Adapter**이다.

Translator Core는 입력 형식마다 다른 번역기를 두지 않는다.
모든 Adapter는 동일한 파이프라인을 거친다.

```
Input Adapter
    ↓
Content Extractor
    ↓
Normalized Content
    ↓
Language Intelligence Engine  (Translator Core)
    ↓
Language Asset
    ↓
Gallery → History → Projects → Credits → Community(optional)
```

## Hard rules

1. Source Type / Adapter는 **자체 번역을 하지 않는다.**
2. 반드시 Content Extractor → Normalized Content를 거친 뒤 Engine을 호출한다.
3. 새 Source Type 추가 시 **추가하는 것:** Input Adapter + Content Extractor (+ UI 탭)
4. 새 Source Type 추가 시 **수정하지 않는 것:**
   - Translator Core (translate orchestration after normalized text)
   - Gallery Store / History filter
   - Projects / Credits / Language Asset schema (reserved meta만 사용)
   - AI Router / Provider contracts
5. 새 Gallery / Credits / History / Projects Store를 만들지 않는다.

## Layers

### 1. Input Adapter

역할: UI·REST 입력을 Adapter 컨텍스트로 정규화한다.

| Adapter id | Accepts | Notes |
|------------|---------|--------|
| `text` | `text` body | Current — extract = identity |
| `file` | attachment / upload | MIME → file extractor |
| `website` | `source_url` | SSRF-safe fetch → HTML extractor |
| `image` | image attachment | OCR extractor |
| `audio` | audio attachment | Speech extractor |
| `video` | video attachment | Media / subtitle path |
| `youtube` | YouTube URL | Subtitle extractor |

Future interface sketch (not wired):

```
YooY_Translator_Input_Adapter
  id(): string
  accepts(array $request): bool
  build_context(array $request): array   // source_type, urls, ids, …
```

### 2. Content Extractor

역할: Adapter 컨텍스트 → **Normalized Content** (plain text + optional structured spans).

| Extractor | Used by | Output |
|-----------|---------|--------|
| Identity / Text | text | source text as-is |
| HTML Extractor | website | title + body text |
| PDF Extractor | file (pdf) | text layers |
| DOCX Extractor | file (docx) | paragraphs |
| Generic Document | pptx/xlsx/txt/md/csv/html/srt/vtt | text |
| OCR Extractor | image | recognized text |
| Speech Extractor | audio | transcript |
| Media Subtitle | video | cues / SRT-like |
| Subtitle Extractor | youtube | caption tracks |

Future interface sketch (not wired):

```
YooY_Content_Extractor
  id(): string
  supports(array $context): bool
  extract(array $context): NormalizedContent
```

### 3. Normalized Content

Engine이 받는 유일한 입력 형태 (개념 스키마):

```
{
  "text": "…",                 // required — Language Engine input
  "source_type": "website",
  "source_title": "…",
  "source_url": "…",
  "source_excerpt": "…",
  "source_metadata": { … },    // extractor-specific, opaque to Core
  "language_hint": "ko",       // optional
  "segments": [ … ]            // optional (subtitles, pages)
}
```

Translator Core (`YooY_Translator_Service`)는 Normalized Content의 `text`(+ mode/lang)만
기존 Provider 경로로 번역한다. Extractor별 분기 없음.

### 4. Language Intelligence Engine

현재: `modules/translator-studio/`  
`validate → provider → Language Asset save → Credits`

Writing / OCR / Rewrite / Summarize / Subtitle도 장기적으로 동일 Engine·Asset을 재사용한다.

### 5. Language Asset → Core Stores

- Persist: `YooY_Gallery_Store` (`type=translation` 등)
- History: Gallery filter
- Projects: `YooY_Gallery_Actions`
- Credits: `YooY_Credits_Service`

## Extension recipe (new Source Type)

1. Document adapter id + extractor in this file + `TRANSLATOR_SOURCE_TYPES.md`
2. Add UI tab status `planned` → later `available`
3. Implement Adapter + Extractor classes (new files under translator or `modules/content-extractors/`)
4. Register in an Adapter Registry (future) — **do not** fork `translate()` per type
5. Map extractor output → Normalized Content → call existing Engine
6. Set `meta.source_type` (+ reserved source_* fields) on Language Asset

## Security (extractors)

Website / File 보안 요구사항은 `docs/TRANSLATOR_SOURCE_TYPES.md` 및
Source Authority (`docs/SOURCE_AUTHORITY.md`)를 따른다.
Website Adapter는 대통령실 등 공식 도메인 우선 정책을 **내부적으로** 적용할 수 있으나
사용자 UI에 노출하지 않는다.

## Current runtime

| Layer | Runtime today |
|-------|----------------|
| Input Adapter | Implicit `source_type=text` only |
| Content Extractor | Identity (textarea) |
| Language Engine | Implemented |
| Language Asset / Gallery / Credits | Implemented |
| File/Website/Image/Audio/Video/YouTube adapters | UI Coming Soon only |

## Non-goals (this design phase)

- Implementing extractors
- Fetch / upload / OCR / STT / YouTube APIs
- Changing Credits formulas per Source Type
- New Stores
