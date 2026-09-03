# YooY AI Studio 12.9.1 — Integration Audit + Bug Fix

## Summary

Phase 5.5 is a full product UX integration audit after Phases 1–5. No Phase 6 features. Fixes guest→login continuity, Writing generate + Gallery/Project linking, Active Project association on generate, and key Korean empty-state labels.

## Requirements

- WordPress 6.x
- PHP 7.4+

## Install

1. Upload `yooy-ai-studio-12.9.1-integration-audit.zip`
2. Activate **YooY AI Studio**
3. Open Studio shell / Home

## Changes

- Resume `yoy_pending_after_auth` after WP login (remix / generate / template / upload)
- Persist Home composer prompt/attachment and Template context before auth gate
- Writing generate via existing AI Router → Gallery (`type=writing`) + optional Project link
- Gallery Store: writing text assets valid; `maybe_link_project` on save
- Image/Video/Music/Voice/Avatar gallery saves carry `project_id` from Active Project
- Guest Home upload/URL requires login without losing pending intent
- Korean empty states for Gallery / Projects / History; Project delete toast

## Package

`yooy-ai-studio-12.9.1-integration-audit.zip`
