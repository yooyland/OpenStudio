# YooY AI Studio / OpenStudio

YooY AI Studio는 YooY Land의 공식 AI 콘텐츠 제작 플랫폼입니다.  
단순 WordPress 플러그인이 아니라, 한국 사용자를 위한 **AI Creator OS**를 목표로 합니다.

- GitHub: https://github.com/yooyland/OpenStudio

## 목표

Runway, Canva, ChatGPT, Midjourney, ElevenLabs, Suno의 장점을 하나로 통합하되,  
**한국 정서·광고·영상·쇼핑몰·유튜브·SNS·음악·드라마·영화** 생성에 최적화된 플랫폼을 만듭니다.

## 프로젝트 구조

```text
OpenStudio/
│
├── plugin/
│   └── yooy-ai-studio/
│
├── modules/
│
├── providers/
│
├── assets/
│
├── docs/
│
├── roadmap/
│
├── tests/
│
└── README.md
```

## 폴더 역할

### `plugin/yooy-ai-studio/`

WordPress 플러그인 진입점입니다. WordPress에 설치되는 실제 배포 단위이며, Studio UI 셸, 숏코드, 훅 등록, 모듈·프로바이더 로딩을 담당합니다.

- 플러그인 메인 파일 (`yoy-ai-studio.php`)
- WordPress Admin 메뉴 및 설정 화면
- 프론트엔드 Studio Shell (SPA 형태 UI)
- `modules/`, `providers/`를 로드하는 Bootstrap 계층

### `modules/`

기능별 독립 모듈을 개발하는 핵심 디렉터리입니다. 모든 비즈니스 로직은 모듈 단위로 분리하며, PHP / JS / CSS / REST API를 각 모듈 내부에서 관리합니다.

예시 모듈:

- `core` — 라우터, Korean Context Engine
- `generators` — Video, Image, Music, Voice, Avatar, Writing
- `credits` — 크레딧 엔진, 결제 연동
- `gallery` — Official Showcase, Community, My Works
- `marketplace` — 프롬프트 마켓
- `community` — 커뮤니티 기능
- `admin` — 관리자 콘솔
- `prompt-engine` — 프롬프트 엔진

### `providers/`

외부 AI API 연동 계층입니다. OpenAI, Runway, Replicate, ElevenLabs, Suno 등 실제 Provider와, API 미연결 시 사용하는 **Mock Provider**를 이곳에서 관리합니다.

- Provider 인터페이스 및 공통 계약 정의
- Provider별 요청/응답 어댑터
- AI Router를 통한 Provider 선택 및 Failover
- Mock Provider — 실제 API와 동일한 인터페이스로 동작하는 개발용 구현

### `assets/`

프로젝트 전역에서 공유하는 정적 리소스입니다. 플러그인 전용 에셋(`plugin/yooy-ai-studio/assets/`)과 구분되며, 디자인 시스템·브랜드·공통 미디어를 보관합니다.

- 아이콘, 일러스트, 브랜드 이미지
- 공통 폰트, 디자인 토큰
- 쇼케이스·템플릿용 샘플 미디어

### `docs/`

아키텍처, API 명세, 모듈 설계, 개발 가이드 등 프로젝트 문서를 관리합니다.

- 시스템 아키텍처 (`ARCHITECTURE.md`)
- 모듈·Provider 인터페이스 명세
- REST API 문서
- 개발·배포 가이드

### `roadmap/`

버전별 기능 로드맵과 릴리스 계획을 관리합니다.

- 메이저/마이너 버전별 목표
- 기능 우선순위 및 마일스톤
- 릴리스 노트 초안

### `tests/`

모듈·Provider·API에 대한 테스트 코드를 관리합니다. Production 수준 품질을 유지하기 위한 자동화 테스트 계층입니다.

- 단위 테스트 (모듈, Provider)
- 통합 테스트 (REST API, WordPress 훅)
- Mock Provider 동작 검증

## 개발 원칙

1. **모듈화** — 모든 기능은 독립 Module로 개발
2. **Production 수준** — 실제 API 연결 구조 우선, 미연결 시 Mock Provider 사용
3. **유지보수성** — 관심사 분리, 명확한 디렉터리 구조, Git 기준 버전 관리
4. **한국 컨텍스트** — Korean Context Engine을 모든 Generator에 통합

## WordPress 사용법

`plugin/yooy-ai-studio`를 WordPress 플러그인으로 설치한 뒤, Studio 페이지에 아래 숏코드를 배치합니다.

```text
[yoy_ai_studio]
```

## 관련 문서

- [아키텍처](docs/ARCHITECTURE.md)
- [10.0 로드맵](roadmap/VERSION-10.md)
