# YooY AI Studio 13.0.0 — Publish + Community + Marketplace

## Summary

Phase 6 closes the public loop:

**Create → Gallery → Publish → Community / Marketplace → Public Works → Discover → Remix**

Gallery remains the single source of truth. Community posts and Marketplace listings reference `gallery_id` and do not duplicate media assets.

## Changes

- Gallery **공개하기** publish sheet (Community / Marketplace / 둘 다)
- Community share with caption, duplicate protection, unshare without deleting Gallery
- Marketplace register/delist with duplicate protection; catalog only (no checkout)
- Publication state on Gallery detail + subtle card badges
- Community / Marketplace large-card discovery UX + **따라 만들기** (public-safe remix)
- Public Works feed deduped by `gallery_id`
- Guest Home 12.9.3 rules preserved (no demo filler, public-safe only)
- Honest empty states for Community / Marketplace / Public Works

## Install

1. Upload `yooy-ai-studio-13.0.0-publish-community-marketplace.zip`
2. Extract to `wp-content/plugins/yooy-ai-studio/`
3. Activate / refresh plugin

## Requirements

- WordPress 6.x
- PHP 7.4+

## Package

`yooy-ai-studio-13.0.0-publish-community-marketplace.zip`
