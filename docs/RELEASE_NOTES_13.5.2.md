# YooY AI Studio 13.5.2 — Premium Image Prompt Orchestration Hardening

**Date:** 2026-09-10  
**Requires:** WordPress 6.x, PHP 7.4+

## Summary

Introduces an explicit **Premium Image Prompt Orchestration Layer** so short user prompts expand into art-directed final prompts, with transparent admin debug traces, dimensional Visual QA Lite, and creative display titles.

## Pipeline

1. Input Normalizer  
2. Intent Analyzer  
3. Subject / Scene Extractor  
4. Quality Escalator  
5. Art Direction Preset Selector  
6. Domain Prompt Composer  
7. Negative Guidance Injector  
8. Final Prompt Builder  
9. Provider / Quality Selector  
10. Visual QA Lite  
11. Title Generator (Gallery SoT)

## Preset mapping (canonical)

| Intent cues | Preset |
|---|---|
| 어린이 + 그림책 + 세련/고급 | `MODERN_STORYBOOK_PREMIUM` |
| 판타지 + 세련/고급 | `PREMIUM_FANTASY_ILLUSTRATION` |
| 뷰티 | `BEAUTY_EDITORIAL_PREMIUM` |
| 제품 | `LUXURY_PRODUCT_CAMPAIGN` |
| 건축 | `ARCHITECTURAL_VISUALIZATION_PREMIUM` |
| 라이프/시네마틱 | `CINEMATIC_LIFESTYLE_PREMIUM` |
| 인물 | `EDITORIAL_PORTRAIT_PREMIUM` |
| 기본 | `GENERAL_PHOTOREAL_PREMIUM` |

Legacy aliases (`MODERN_STORYBOOK`, etc.) still resolve to `*_PREMIUM` ids.

## Other

- Common + genre negative guidance on all generations  
- `generation_trace`: user / normalized / intent / preset / provider / model / quality / size / negatives / final / title / QA scores  
- Visual QA dimensions + 「더 고급스럽게 재시도」 (user confirm; no silent re-charge)  
- Titles: no `광고 이미지` / `작품 (N)` — e.g. `오로라 너머의 여행`, `달빛을 타는 푸른 고래`

## Install

Replace plugin ZIP or atomic deploy of changed modules + assets; reset opcache after PHP changes.
