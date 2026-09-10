# YooY AI Studio 13.5.4 — Global Original Image Viewer

**Date:** 2026-09-10  
**Requires:** WordPress 6.x, PHP 7.4+  
**Package:** `yooy-ai-studio-13.5.4-global-original-image-viewer.zip`

## Summary

Adds one shared full-resolution image viewer (`YooYOriginalImageViewer`) so Gallery, Image Studio, Projects, Community, and Marketplace open the same canonical original/full source — never thumbnails or card derivatives.

## Viewer UX

- Dark full-screen overlay above `.yai-app` (`z-index: 1000030`)
- Fit / 100% / zoom ± / pan when zoomed / download / close
- Natural dimensions + MIME meta
- Escape, `+`/`-`, `0` (fit)
- Body scroll lock; pan stays inside the viewer
- Error: “원본 이미지를 불러오지 못했습니다.” + retry

## Entry points

| Surface | Behavior |
|---|---|
| Gallery detail | Image click or **원본 보기** |
| Image Studio result | Result image click or **원본 보기** |
| Project Main Work | Cover click / **원본 보기** |
| Project Assets | Asset thumb click |
| Community / Marketplace | Public-safe thumb / **원본 보기** |

Source resolution: `YooYGalleryImage.pickUrl(..., 'full')` → original → largest available.

## Install

Atomic deploy of viewer assets + wiring + version bump; flush opcache after PHP enqueue changes.
