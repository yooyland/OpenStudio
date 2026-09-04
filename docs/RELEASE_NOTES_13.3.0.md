# Release Notes — 13.3.0 (Phase 9)

## Summary

AI Assistant becomes the **Universal Creator Command Center** — orchestration and navigation over existing Studios, Gallery, Projects, Credits, and Publish flows. No second Assistant, Router, Gallery, or Credits ledger.

## Version

- Plugin: **13.3.0**
- Package: `yooy-ai-studio-13.3.0-assistant-command-center.zip`
- Requires: WordPress 6.x, PHP 7.4+

## Changes

- Command/action resolver: create prepare, gallery search, credits/plan, project open, publish preview, delete confirm, deictic clarify
- Assistant UI: action cards, context chips (project / selected asset / attachment), session context, `yoy:creation-success` follow-up
- Reuses Import Engine for attachments; Gallery pending query from Assistant; existing publish sheet / delete confirm
- Home: optional 「AI와 상의하기」; onboarding: 「AI Assistant에게 물어보기」
- **No auto-Generate / no Assistant Credits spend** — prepare Studio → user reviews → Generate

## Install

1. Upload/extract ZIP to `wp-content/plugins/yooy-ai-studio/`
2. Activate plugin
3. Open Creator OS shell / AI Assistant

## Notes

- Conversation remains session-only (no new chat DB)
- Publish / delete never silent
- Guest: auth flow + pending assistant message when supported
