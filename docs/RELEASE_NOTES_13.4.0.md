# Release Notes — 13.4.0

**YooY AI Studio** — Phase 10 MY / Profile / Settings / Help / Account

## Summary

Authenticated users get a single **MY** account hub for profile, plan, credits summary, preferences, help, and account security — without duplicating Credits/Settings backends.

## Changes

- New route `my` — account home with section navigation
- Profile edit via existing `user-profile` REST (`display_name`, `bio`; email read-only)
- Settings edits via existing `settings` REST (korean context, auto-save, notifications, quality)
- Plan/credits summaries reuse `YooY_Credits_Service` / credits overview
- Account delete via WordPress `wp_delete_user` with `DELETE` confirmation
- Password reset uses WordPress lost-password URL
- MY dropdown + sidebar Settings/Help point into MY sections
- Admin tools remain separated under MY dropdown / sidebar

## Install

1. Upload/replace plugin package `yooy-ai-studio-13.4.0-my-account-ux.zip`
2. Hard-refresh Creator OS (`?ver=13.4.0`)

## Requirements

- WordPress 6.x
- PHP 7.4+
