# YooY AI Studio 13.6.2 — Creative Canvas Projects API Fix

**Baseline:** 13.6.1  
**Package:** `yooy-ai-studio-13.6.2-php74.zip`

## Root cause

`creative-canvas.js` resolved `window.YooYAIStudioCore` (does not exist).  
Canonical global is `window.YooYCore` / `YooYCore.projects`.

## Fix

- Resolve Projects API at call time via `YooYProjectsAPI` === `YooYCore.projects`
- Classify load errors (client / auth / not found / network)
- Empty Canvas onboarding (not an error)
- Persist node position via `updateCanvasNode` + viewport save

## Requirements

- WordPress 6.x / PHP 7.4+
