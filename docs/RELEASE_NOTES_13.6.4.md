# YooY AI Studio 13.6.4 — Runtime Contract (Critical Globals)

## Summary

Admin/dev runtime contract check for critical YooY globals at Studio boot and per-page hydrate. Missing globals log developer diagnostics (admin/debug only) and show a Korean user-facing fallback — never silent fail, never duplicate globals.

## Changes

- New `assets/js/runtime-contract.js` → `window.YooYRuntimeContract`
  - `verifyBoot()`: `YooYCore`, `YooYCore.projects`, `YooYProjectsAPI` (alias of Core.projects), `YooYOriginalImageViewer`, `YooYStudio`
  - `verifyPage(route)`: Gallery / Studio / Canvas globals required by the active route
  - Soft-heal: alias `YooYProjectsAPI` ↔ `YooYCore.projects` only (no second client)
  - User copy: `일부 기능을 불러오지 못했습니다. 잠시 후 새로고침해 주세요.`
  - Dev console: `[YooYRuntimeContract] missing required globals` when `isAdmin` / `debug`
- Enqueued after `yoy-ai-studio-core`; `studio.js` depends on it
- `studio.js` / `creative-canvas.js`: boot + route + mount failure paths use contract fallback UI
- CSS: `.yai-empty--contract`

## Install

1. Upload ZIP to `wp-content/plugins/` (or replace `yooy-ai-studio/`)
2. Activate / refresh OPcache if needed
3. Open Creator OS shell; with WP admin + missing script, expect console diagnostic + Korean toast/empty state

## Requirements

- WordPress 6.x
- PHP 7.4+
