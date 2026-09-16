# YooY AI Studio 13.6.1 — Projects / Workspace UX Cleanup

**Baseline:** 13.6.0  
**Package:** `yooy-ai-studio-13.6.1-php74.zip`

## Changes

- Project Workspace: remove duplicate page-level「← 프로젝트로」; keep only chrome `#yai-nav-back`
- Project cards: large/card cover via `YooYGalleryImage.pickUrl` + Gallery enrich; premium empty placeholder
- Action row: horizontal Korean buttons with hierarchy (계속 작업하기 / 이름 변경 / 삭제)
- Workspace meta Korean (`N개 작품 · 공개/비공개`); Settings/Delete → 설정/삭제
- Tabs: 개요 / 작품 / Canvas / 기록 / 메모 / AI Assistant / Studio / 설정
- Empty Overview: single consolidated empty state

## Install

1. Upload ZIP to `wp-content/plugins/`
2. Activate **YooY AI Studio**
3. Hard-refresh Creator OS (`Ctrl+F5`)

## Requirements

- WordPress 6.x
- PHP 7.4+
