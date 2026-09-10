# YooY AI Studio 13.5.0 — Image Quality Orchestration + Intelligent Title Engine

**Date:** 2026-09-10  
**Package:** `yooy-ai-studio-13.5.0-image-quality-orchestration.zip`  
**Requires:** WordPress 6.x, PHP 7.4+

## Summary

Generation-quality and asset-title upgrade for Image Studio. Short user prompts now receive intent classification, art-direction presets, structured commercial prompts, and creative Korean display titles — without a second Router/Gallery/provider stack.

## Changes

### Quality orchestration
- Intent categories expanded (storybook, beauty, cinematic, fantasy, …)
- Internal art-direction presets (`PREMIUM_COMMERCIAL`, `EDITORIAL_PORTRAIT`, `LUXURY_PRODUCT`, `BEAUTY_CAMPAIGN`, `ARCHITECTURAL_VISUALIZATION`, `MODERN_STORYBOOK`, …)
- Domain composers use CORE SCENE / PURPOSE / DIRECTION / COMPOSITION / LIGHTING / MATERIALS / COLOR / CONSTRAINTS / AVOID structure
- Human / storybook / beauty / architecture policies strengthened (literal story fidelity; no invented product text)

### Intelligent titles
- Replaced mechanical titles like `한국 광고 이미지 (9)`
- Display titles summarize the visual idea; purpose words (`광고`, `이미지`) and `(N)` counters removed from visible titles
- Uniqueness remains via `gallery_id`

### Quality defaults / UI
- Default remains quality-biased (`premium` → provider `high`)
- Low prompt-intelligence confidence may offer「다른 시안 만들기」(refill only — user presses 생성하기)
- Assistant「승인하고 Studio로」handoff preserved (`skipDirtyCheck`)

## Install

1. Upload/replace `yooy-ai-studio` plugin ZIP
2. Activate if needed
3. Open Creator OS dashboard / Image Studio

## Notes

- Credits: unchanged rates; quality mode still uses HD/high mapping
- No marketing “guarantee” copy added
