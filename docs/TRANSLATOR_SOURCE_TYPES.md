# Translator Source Types

Translator를 **다중 입력형 Language Intelligence Engine**으로 확장하기 위한 Source Type 규약이다.

> **Architecture:** Source Type = **Input Adapter**.  
> Full pipeline design: [`docs/AI_INPUT_ADAPTER.md`](AI_INPUT_ADAPTER.md)  
> OS map: [`docs/ARCHITECTURE_BIBLE.md`](ARCHITECTURE_BIBLE.md)

공통 흐름 (모든 Source Type 공통 — 새 Store 금지):

```
Input Adapter
→ Content Extractor
→ Normalized Content
→ Language Intelligence Engine
→ Language Asset (Gallery type=translation)
→ History / My Works / Projects / Credits
```

Adapter는 자체 번역을 하지 않는다. Extractor가 Normalized Content를 만든 뒤
동일한 Translator Core만 사용한다.

## Source Type 정의

| id | UI label | History badge | Adapter role | Status (v1) |
|----|----------|---------------|--------------|-------------|
| `text` | Text | TEXT | Identity extract | **Available** |
| `file` | File | FILE | Document extractors | Planned |
| `website` | Website | WEB | HTML Extractor | Planned |
| `image` | Image | OCR | OCR Extractor | Planned |
| `audio` | Audio | AUDIO | Speech Extractor | Planned |
| `video` | Video | VIDEO | Media / subtitle | Planned |
| `youtube` | YouTube | YOUTUBE | Subtitle Extractor | Planned |

기본값: `text`. 미지정 시 Validator가 `text`로 정규화한다 (REST 하위호환).

## Extension rule

새 Source Type = **Input Adapter + Content Extractor 추가만**.  
Translator Core / Gallery / Projects / Credits / History / Language Asset 스키마는 수정하지 않는다
(reserved meta 필드 사용).

## UI

- 세그먼트/가로 스크롤 탭으로 Source Type 선택
- Available: Text 패널 (기존 원문/결과 UI)
- Planned: Coming Soon 배너 + scaffold UI (업로드·URL 등 **비활성**)
- 메시지: `해당 입력 방식은 다음 단계에서 제공될 예정입니다.`

## REST

`POST /translator-studio/translate`

```json
{
  "source_type": "text",
  "text": "…",
  "source_language": "auto",
  "target_language": "en",
  "mode": "natural"
}
```

- `source_type` 생략 → `text`
- `text` 외 → `source_type_not_implemented` (400)

향후:

```json
{ "source_type": "website", "source_url": "https://example.com" }
{ "source_type": "file", "source_attachment_id": 123 }
```

## Language Asset metadata

**Active (Text 저장 시)**

- `meta.source_type` = `text`
- 기존 번역 필드 유지

**Reserved (미저장)**

`source_url`, `source_title`, `source_mime_type`, `source_filename`, `source_filesize`,
`source_attachment_id`, `source_external_id`, `source_provider`, `source_content_hash`,
`source_excerpt`, `source_metadata`, `output_type`, `output_attachment_id`,
`output_filename`, `processing_status`

(+ 기존 Language Asset reserved: `asset_uuid`, `parent_asset_uuid`, `pipeline`, …)

## Pipeline (향후)

| Adapter | Extractor | Notes |
|---------|-----------|--------|
| text | Identity | Current |
| file | PDF / DOCX / … | Per MIME |
| website | HTML Extractor | See security + Source Authority |
| image | OCR Extractor | |
| audio | Speech Extractor | |
| video | Media / subtitle | SRT/VTT |
| youtube | Subtitle Extractor | |

## Website 보안 (설계)

- SSRF 방지, localhost/사설 IP 차단
- `http`/`https`만, redirect 제한, timeout, 최대 응답 크기
- robots/이용약관 고려, script·악성 HTML 제거
- Source Authority: 대한민국 공식자료는 `docs/SOURCE_AUTHORITY.md` (UI 없음)

## File 보안 (설계)

- MIME·확장자 검증, 크기 제한, 악성 파일 차단
- 업로드 권한, 임시파일 정리, private 저장, 사용자 격리

## 구현 우선순위

1. Text (done — identity adapter)
2. Website Adapter + HTML Extractor
3. File Adapter + document extractors
4. Image Adapter + OCR
5. Audio / Video / YouTube

## Code map

- Enum: `YooY_Translator_Validator::source_types()`
- Config: `GET …/translator-studio/config` → `source_types`
- Save: `YooY_Translator_Gallery` → `meta.source_type`
- Adapter design: `docs/AI_INPUT_ADAPTER.md`
