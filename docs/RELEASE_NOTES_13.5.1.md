# YooY AI Studio 13.5.1 — Premium Visual Quality Hardening

**Date:** 2026-09-10  
**Requires:** WordPress 6.x, PHP 7.4+

## Summary

Hardens image quality orchestration for storybook/fantasy/portrait/product/architecture, adds admin/debug final-prompt transparency, lightweight visual QA with optional premium retry, and cleaner creative titles.

## Changes

- Premium visual bias + shared negative guidance on all art-direction presets
- `PREMIUM_FANTASY_ILLUSTRATION` / stronger `MODERN_STORYBOOK` routing when users ask for modern/sophisticated/cover quality
- `generation_trace` on jobs: user vs final prompt, provider/model/quality/size/mode/preset/negatives/refs/QA
- Admin/Debug pipeline panel in Image Studio result board
- Visual QA heuristics + 「더 고급스럽게 재시도」 (explicit user action; no silent double charge)
- Title: aurora whale/penguin → `오로라 너머의 여행`

## Install

Replace plugin ZIP / atomic deploy of changed modules + assets; opcache reset if PHP changed.
