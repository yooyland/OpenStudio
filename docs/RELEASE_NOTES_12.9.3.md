# YooY AI Studio 12.9.3 — Guest Home Runtime Fix

## Summary

Corrective patch for logged-out Home. Phase 4.5 intent was not visible because
author CSS overrode `[hidden]`, Guest sections resolved private `user` options,
and theme CSS could wrap "회원가입".

## Requirements

- WordPress 6.x
- PHP 7.4+

## Install

1. Upload `yooy-ai-studio-12.9.3-guest-home-runtime-fix.zip`
2. Activate **YooY AI Studio**
3. Open Studio as a logged-out visitor and verify Home

## Changes

- Back button: CSS `display:none !important` for `[hidden]` / `body.yai-route-home`
- Body boots with `yai-route-home` so Back never paints on first load
- Guest Home sections remap to Discovery defaults (community/templates/guide)
- Official/demo/placeholder thumbs excluded from Guest feed/sections
- Platform feed seed no longer pads community/marketplace with showcase demos
- Sidebar/topbar "회원가입" nowrap + keep-all against theme word-break

## Package

`yooy-ai-studio-12.9.3-guest-home-runtime-fix.zip`
