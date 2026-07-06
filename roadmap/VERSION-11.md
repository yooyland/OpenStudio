# Version 11 Roadmap

## v11.0 — Production Foundation ✅
- Core: `YooY_Job_Store`, `YooY_Job_Normalizer`, `YooY_Credits_Service`
- Image Studio end-to-end (credits, poll, gallery, result actions)
- Gallery/Community/Marketplace static data removal
- Mock providers without placehold.co

## v11.1 — Video Studio ✅
- `YooY_Studio_Credits` shared helper
- Video: Job Store history, Gallery Store, credits estimate/deduct
- Video: async poll + Result Actions frontend
- ai-router: `dispatch_video()` + poll delegation

## v11.2 — Music Studio ✅
- Music: Job Store history, Gallery Store only
- Music: credits estimate/deduct via `YooY_Studio_Credits`
- Music: async poll + Result Actions (download, copy, regenerate, publish, marketplace, project)
- Mock Music Provider async via `YooY_Mock_Job_Engine`
- ai-router: `dispatch_music()` + poll delegation
- Gallery: `publish` + `project` actions
- Projects: demo seed removed

## v11.3 — Voice & Avatar (partial)
- Voice/Avatar history/gallery → unified Store

## v11.4+ — Remaining
- Voice/Avatar credits + Result Actions frontend
- Voice/Avatar ai-router dispatch
- Writing Studio
- Admin API key settings UI
