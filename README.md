# YooY AI Studio / OpenStudio

**YooY AI Studio**는 YooY Land의 **AI Creator Operating System**입니다.

단순 번역기·이미지 생성기가 아니라,  
Video · Image · Music · Voice · Avatar · Writing · Translator가  
**동일한 Core** 위에서 동작하는 통합 제작 플랫폼입니다.

이 README는 OpenStudio의 **공식 Entry Point(얼굴)** 입니다.  
설계 기준은 Architecture Bible과 topic docs에 있습니다.

- GitHub: https://github.com/yooyland/OpenStudio
- 배포 단위: WordPress 플러그인 `plugin/yooy-ai-studio/`
- 현재 버전: **11.17.0**

---

## 핵심 철학

새로운 기능이 필요할 때 **새 시스템을 만들지 않는다.**  
기존 Core를 확장한다.

| 하나 | 의미 |
|------|------|
| **하나의 Core** | `YooY_Core_Engine` · 모듈 디스커버리 · REST |
| **하나의 AI Router** | Provider 선택 · Failover · Mock/Real 동일 계약 |
| **하나의 Gallery** | Canonical Asset Store (`yoy_gallery_items`) · My Works · History 필터 |
| **하나의 Projects** | **Project Workspace** — Gallery Asset 참조로 Creator 작업 묶음 · Active Project Context |
| **하나의 Credits** | `YooY_Credits_Service` · ledger |
| **하나의 Marketplace** | Gallery `gallery_id` 기반 등록 |
| **하나의 Community** | Gallery 공개 작품 피드 |

상세 철학: [`docs/ARCHITECTURE_BIBLE.md`](docs/ARCHITECTURE_BIBLE.md)  
Project Workspace: [`docs/PROJECT_WORKSPACE.md`](docs/PROJECT_WORKSPACE.md)

Language 경로의 목표 구조:

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

## Architecture Diagram

```text
                    ┌─────────────────────────┐
                    │   Studio Shell (SaaS UI) │
                    └───────────┬─────────────┘
                                │
     ┌──────────────┬───────────┼───────────┬──────────────┐
     ▼              ▼           ▼           ▼              ▼
 Image/Video    Music/Voice  Avatar     Writing      Translator
 Music/…        …            …          …         (Language Engine)
     │              │           │           │              │
     │              │           │           │     Input Adapter
     │              │           │           │        → Extractor
     │              │           │           │        → Normalized Content
     └──────────────┴───────────┴───────────┴──────┬───────┘
                                                   ▼
                                         AI Router / Providers
                                                   ▼
                              ┌────────────────────────────────┐
                              │  Gallery = Canonical Asset SoT │
                              └────────────┬───────────────────┘
           ┌───────────────┬───────────────┼───────────────┐
           ▼               ▼               ▼               ▼
        History         Projects       Credits        Community /
      (type filter)   (gallery_id)    (ledger)       Marketplace
```

시스템 요약: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)

---

## Studio 목록

| Studio | 역할 |
|--------|------|
| **Video** | 영상 생성 · 스토리보드 |
| **Image** | 이미지 생성 · 편집 |
| **Music** | 음악 · 가사 |
| **Voice** | TTS · 보이스 |
| **Avatar** | 아바타 · 립싱크 |
| **Writing** | 글쓰기 — **Language Asset / Gallery 재사용** (별도 Asset Store 없음) |
| **Translator** | Language Intelligence Engine |
| **Gallery** | My Works · 전체 Asset |
| **Projects** | 작업 묶음 (`gallery_id`) |
| **Credits** | 잔액 · 플랜 |
| **Marketplace** | 프롬프트·에셋 등록 (`gallery_id`) |
| **Community** | 공개 피드 (`gallery_id`) |
| **Admin Console** | Provider · 운영 |

숏코드:

```text
[yoy_ai_studio]
```

---

## Language Intelligence Engine

Translator는 독립 번역 앱이 아닙니다.  
Writing · OCR · Subtitle · Rewrite · Summarize 등이 재사용할 **Language Intelligence Engine**입니다.

흐름:

```text
Normalized Content → Translator Core → Language Asset → Gallery → Credits
```

- Language Asset 규약: [`docs/LANGUAGE_ASSET.md`](docs/LANGUAGE_ASSET.md)
- Source Type 표: [`docs/TRANSLATOR_SOURCE_TYPES.md`](docs/TRANSLATOR_SOURCE_TYPES.md)

---

## AI Input Adapter

Source Type(Text / File / Website / Image / Audio / Video / YouTube)는  
UI 라벨이 아니라 **Input Adapter**입니다.

```text
Input Adapter → Content Extractor → Normalized Content
→ Language Intelligence Engine → Language Asset
```

Adapter는 자체 번역을 하지 않습니다.  
새 입력 방식은 **Adapter + Extractor만 추가**하고, Translator Core / Gallery / Credits는 수정하지 않습니다.

설계: [`docs/AI_INPUT_ADAPTER.md`](docs/AI_INPUT_ADAPTER.md)

현재 런타임: **Text만 Available**. 나머지 Source Type은 UI foundation(Coming Soon).  
다음 확장 권장 순서: **Website Adapter**부터.

---

## Asset 구조 (Universal Asset)

**Universal Asset은 저장소가 아니라 Architecture 개념**입니다.

| 원칙 | 내용 |
|------|------|
| Canonical Store | `YooY_Gallery_Store` |
| 신규 Store | **만들지 않음** |
| 런타임 (향후) | Thin Facade / Repository만 (현재 미구현) |
| 참조 | Projects / Community / Marketplace → `gallery_id` |

플랫폼 Asset 6종:

| Family | Gallery `type` 예 |
|--------|-------------------|
| Image Asset | `image` |
| Video Asset | `video` |
| Music Asset | `music` |
| Voice Asset | `voice` |
| Language Asset | `translation`, `writing`, … |
| Avatar Asset | `avatar` |

공통 생명주기:

```text
Generate / Import → Gallery Asset → My Works → Project
→ Community / Marketplace (선택) → Reuse / Remix
```

규약: [`docs/UNIVERSAL_ASSET.md`](docs/UNIVERSAL_ASSET.md)

---

## Source Authority

대한민국 관련 정보(대통령실·법률·통계 등)는  
**내부 Source Authority** 정책으로만 처리합니다.

- 사용자 메뉴 · 설정 · 배너 **없음**
- 대통령·대통령실 관련: [`https://www.president.go.kr/`](https://www.president.go.kr/) 우선

정책: [`docs/SOURCE_AUTHORITY.md`](docs/SOURCE_AUTHORITY.md)

---

## 개발 원칙

1. **Reuse over rewrite** — Core / Gallery / Projects / Credits / Router 재사용
2. **Production quality** — Mock은 허용, 데모·가짜 비즈니스 데이터 금지
3. **PHP 7.4+** — WordPress 6.x 대상
4. **모듈화** — `modules/*/module.php` 자동 등록
5. **아키텍처 문서 선행** — 큰 구조 변경은 Bible·topic doc을 먼저 갱신
6. **최소 변경** — 기존 Studio 회귀 방지
7. **설계 완성도 우선** — 병렬 시스템보다 Canonical 모델 유지

규칙 상세: [`CONTRIBUTING.md`](CONTRIBUTING.md) · [`.cursor/rules/`](.cursor/rules/)

---

## 문서 인덱스

README는 **프로젝트 입구**입니다. 설계 기준은 아래 문서에 있습니다.

```text
README.md                          ← 입구 (이 파일)
└── docs/
    ├── ARCHITECTURE_BIBLE.md      ← 전체 철학 · OS 맵 (Studio 개발 기준)
    ├── ARCHITECTURE.md            ← 시스템 구조 요약
    ├── AI_INPUT_ADAPTER.md        ← 입력 체계 (Adapter → Extractor)
    ├── LANGUAGE_ASSET.md          ← Language Asset
    ├── UNIVERSAL_ASSET.md         ← Canonical Asset · Facade 규약
    ├── TRANSLATOR_SOURCE_TYPES.md ← Source Type 표 · 보안
    ├── SOURCE_AUTHORITY.md        ← 공식 출처 정책 (내부)
    ├── CREDITS_TRANSACTION.md     ← Credits 원자성 (설계)
    └── CREDITS_LEDGER_TYPES.md    ← Ledger type 어휘
CONTRIBUTING.md                    ← 개발 규칙
ROADMAP.md                         ← 버전 계획 입구
```

| 문서 | 설명 |
|------|------|
| [Architecture Bible](docs/ARCHITECTURE_BIBLE.md) | 전체 철학 · Studio 개발 기준 |
| [Architecture](docs/ARCHITECTURE.md) | 시스템 구조 |
| [Universal Asset](docs/UNIVERSAL_ASSET.md) | Canonical Asset Store |
| [AI Input Adapter](docs/AI_INPUT_ADAPTER.md) | 입력 체계 |
| [Language Asset](docs/LANGUAGE_ASSET.md) | 언어 자산 |
| [Source Authority](docs/SOURCE_AUTHORITY.md) | 공식 출처 정책 |
| [Contributing](CONTRIBUTING.md) | 개발 규칙 |
| [Roadmap](ROADMAP.md) | 버전 계획 |

---

## 저장소 구조 (요약)

```text
OpenStudio/
├── plugin/yooy-ai-studio/   # WordPress 플러그인 · Studio Shell
├── modules/                 # 기능 모듈 (Gallery, Credits, Studios, …)
├── providers/               # Mock / Real AI Providers
├── docs/                    # 아키텍처 · 정책
├── roadmap/                 # 버전별 로드맵
├── scripts/                 # 패키징 · audit
└── README.md
```

---

## 라이선스 · 문의

YooY Land · OpenStudio  
이슈와 PR은 GitHub 저장소를 이용하세요.
