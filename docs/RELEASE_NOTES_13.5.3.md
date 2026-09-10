# YooY AI Studio 13.5.3 — Premium Image Quality Acceptance Gate

**Date:** 2026-09-10  
**Requires:** WordPress 6.x, PHP 7.4+  
**Baseline:** 13.5.2 orchestration

## Summary

Post-orchestration acceptance hardening: prompt fidelity lock (P1), bloat compression, portrait/size routing fixes, honest prompt-metadata QA labeling, differentiated retry actions, and title quality gates — without claiming pixel vision QA.

## Key fixes

- P1 CORE SUBJECT/ACTION/SETTING lock + bloat compressor (`spi-image-orch-2`)
- `3:4` → `1024x1536` (no opaque `auto`)
- Portrait/화보 before brand commercial hijack
- Lifestyle lighting diversity (not always golden hour)
- Product blank-packaging reinforcement + textless retry CTA
- Titles: reject orchestration meta; architecture/product/portrait work titles
- Retry: 「더 고급스럽게」 vs 「다른 시안」 vs 「텍스트 없는 버전」 (user confirm + credits hint)
- Visual QA labeled `prompt_metadata_qa` (`inspects_pixels: false`)
- Permanent golden set: `docs/fixtures/image-quality-acceptance-gate.json`

## Install

Atomic deploy + opcache reset.
