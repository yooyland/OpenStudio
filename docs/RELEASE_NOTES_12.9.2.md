# YooY AI Studio 12.9.2 — Writing Real Provider Fix

## Summary

Phase 5.6 connects Writing Studio to the existing OpenAI Chat Completions path
(same `yoy_openai_api_key` as Translator / Import). Mock draft is no longer used
for normal production users.

## Requirements

- WordPress 6.x
- PHP 7.4+
- OpenAI API key configured (`yoy_openai_api_key`) for Writing generation

## Install

1. Upload `yooy-ai-studio-12.9.2-writing-provider-fix.zip`
2. Activate **YooY AI Studio**
3. Open Writing Studio and generate

## Changes

- `dispatch_writing` calls real OpenAI chat via shared helper `YooY_OpenAI_Chat`
- YooY 추천 (`provider=auto`) selects available OpenAI text path
- Purpose / tone / length applied via system prompt
- Credits: check balance → generate → charge once on success (5 credits, existing estimate)
- Gallery writing text assets + Project link preserved
- Job Normalizer accepts text-only writing/translation output
- Gallery detail shows writing body (`meta.content`)
- Mock draft only when `provider=mock` and WP_DEBUG/YOOY_DEBUG
- Double-click generation guard in Writing UI

## Package

`yooy-ai-studio-12.9.2-writing-provider-fix.zip`
