# Project Workspace

**Status:** Phase 3-A Foundation (UI / context)  
**Route id (unchanged):** `project-detail`  
**REST (unchanged):** `/yoy-ai-studio/v1/projects*`

---

## Product model

```text
Home
 └─ Projects
     └─ Project Workspace   ← user-facing name for project-detail
         ├─ Overview / Assets / History / Notes / Settings
         ├─ Members / Timeline (Reserved — “준비 중”)
         └─ Studio Launcher → global Studio routes
 └─ Gallery                 ← Canonical Asset Store (My Works library)
 └─ Marketplace / Community / Credits / Settings
```

- **Project** = Creator Workspace (hub for work under one brief)
- **Gallery** = Canonical Asset Store (`YooY_Gallery_Store`)
- Projects hold **`gallery_id` references (+ display snapshots)** only — no Asset body clone
- **Global Studio sidebar entry is retained** (Video / Image / Music / Voice / Avatar / Writing / Translator)
- When an **Active Project** is set, Studio screens show a compact **Project Context Banner**

---

## Active Project Context

Client helper: `YooYActiveProject` (`assets/js/active-project.js`)

| API | Behavior |
|-----|----------|
| `set({ id, name })` | sessionStorage only |
| `get()` / `getId()` | read current |
| `clear()` | remove |
| `subscribe(fn)` | change listener + `yoy:active-project` event |

Does **not** persist full project payloads. Does **not** force `project_id` into generate requests (Phase 3-B).

---

## Workspace tabs (V1)

| Tab | Status |
|-----|--------|
| Overview | Implemented — meta + Studio Launcher + recent works |
| Assets | Implemented — Gallery `project_id` filter; Open / Preview / Remove from Project / Go to Source Studio |
| History | Implemented — same Gallery filter, `created_at` sort (no new History Store) |
| Notes | Minimal — `notes` + `notes_updated_at` on `YooY_Project_Store` |
| Settings | Implemented — name / description / category / visibility / language / cover / delete |
| Members | Reserved UI |
| Timeline | Reserved UI |

---

## Create → Workspace

Create Modal fields: Name*, Description, Category, Visibility (default private), Language (default ko), Cover URL optional.  
On success → Active Project set → **Workspace Overview** (`project-detail`). Never stay on the empty list.

---

## Shared “Save to Project” UX

`window.YooYStudioSaveToProject(galleryId)`:

1. If no projects → Create Project Dialog (with `work_ids`) → Workspace  
2. If projects exist → Project picker  

Translator **Project 저장** uses this shared entry (no Translator-only create path).

---

## Non-goals (this phase)

- New Stores / Core / Asset schema
- Bulk `project_id` injection on all Studio generate calls
- Removing sidebar Studio items
- Real Members collaboration or Timeline engine
- Version bump / Commit / Tag automation
