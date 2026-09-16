# YooY AI Studio 13.6.3 — Beauty Campaign Orchestration Fix

**Baseline:** 13.6.2

## Root cause
1. Emotion keyword `화` matched inside `화장품` → anger/intense
2. Flat `beauty` domain routed through product packshot composer + blank packaging
3. Poster/ad intent collapsed to product-only still life

## Fix
- Beauty taxonomy: `beauty_model_campaign` / `beauty_poster_editorial` / `beauty_product_packshot`
- Emotion fallback: refined / elegant / radiant for beauty
- Brand token preservation for short marks like VVR
- Campaign art direction + anti-cheap rules
- Creative beauty titles
