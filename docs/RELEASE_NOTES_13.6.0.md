# YooY AI Studio 13.6.0 — Creative Canvas v1 + Premium Quality v2

**Date:** 2026-09-16  
**Requires:** WordPress 6.x, PHP 7.4+  
**Package:** `yooy-ai-studio-13.6.0-creative-canvas-premium-quality.zip`

## Summary

Ships **YooY Creative Canvas v1** (Project visual AI workflow board) and **Premium Image Quality Upgrade v2** (Composition Planner, expanded art-direction presets, anti-cheap guardrails, short-prompt premium rescue, poetic titles). Hardens Assistant → Studio handoff and extends original-viewer / Canvas entry points.

## Creative Canvas v1

- Project Workspace tab: **Canvas**
- Nodes: prompt / note / reference_image / generated_image (gallery_id SoT)
- Edges: generated_from and related relation types
- Pan / zoom / drag / autosave layout
- Entry: Gallery **Canvas에 추가**, Image Studio **Canvas로 보내기**, Assistant **Canvas에서 이어가기**
- Studio generate from canvas prompt can return result node linked to source

## Premium Quality v2

- New `YooY_Image_Composition_Planner` in orchestration pipeline (`spi-image-orch-3`)
- Expanded presets (beauty campaign, archviz, product hero, Korean brand, social ad, family lifestyle, real-estate campaign, …)
- Internal anti-cheap negatives + composition injection
- Short prompts escalate to premium-biased briefs by default
- Title overhaul: poetic domain titles (no “광고 이미지 (N)”)

## Assistant

- Clearer approve errors + retry route
- Success toast after handoff
- Applies `yoy_assistant_project_id` into Image Studio / Active Project
- Canvas continue CTA

## Install

Atomic deploy of listed modules/assets; flush opcache after PHP changes.
