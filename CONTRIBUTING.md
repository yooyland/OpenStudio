# Contributing — OpenStudio / YooY AI Studio

OpenStudio에 기여하기 전에 아래를 읽으세요.

## 설계 권위

1. [`docs/ARCHITECTURE_BIBLE.md`](docs/ARCHITECTURE_BIBLE.md) — OS 철학
2. Topic docs (`AI_INPUT_ADAPTER`, `UNIVERSAL_ASSET`, `LANGUAGE_ASSET`, …)
3. Cursor rules in [`.cursor/rules/`](.cursor/rules/)

README는 입구일 뿐, **설계 충돌 시 Bible이 우선**입니다.

## 절대 규칙

- 새 Gallery / History / Credits / Projects Store를 만들지 않는다
- Gallery Store가 Canonical Asset Store다 (`docs/UNIVERSAL_ASSET.md`)
- Writing Studio도 Language Asset / Gallery를 재사용한다 (별도 Writing Asset Store 금지)
- Projects / Community / Marketplace는 `gallery_id` 참조만 사용한다
- Translator 입력 확장은 Input Adapter + Content Extractor만 추가한다
- Source Authority는 내부 정책만 — 사용자 UI에 노출하지 않는다
- Production 품질 — 데모·하드코딩 비즈니스 데이터 금지 (Mock Provider는 허용)
- PHP **7.4** 호환 유지
- Universal Asset Thin Facade는 승인 전에는 구현하지 않는다

## 작업 흐름

```
의도 → 관련 docs 갱신(필요 시) → 최소 코드 변경 → 회귀 확인
→ 문서 커밋과 기능 커밋 분리 권장
```

## 커밋

- 문서만: `docs(…): …`
- 기능: `feat(scope): …`
- 시크릿 · ZIP · `dist/` · 작업용 이미지 커밋 금지

## 릴리스

WordPress 패키징·PHP 7.4·activation audit — `.cursor/rules/wordpress-release.mdc`

## PR 체크리스트

- [ ] 기존 Studio(Image/Video/Music/Voice/…) 회귀 없음
- [ ] 새 Store / 이중 SoT 없음
- [ ] 관련 docs / Bible 링크 갱신 여부 검토
- [ ] `node --check` (변경된 JS)
