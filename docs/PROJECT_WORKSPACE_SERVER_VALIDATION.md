# Project Workspace — Server Smoke Validation

Release Candidate: `yooy-ai-studio-11.16.0-project-workspace-rc1.zip`  
Plugin version: **11.16.0** (unchanged)  
Scope: Phase 3-A Project Workspace Foundation  
Status: **packaged / live server not yet verified**

Check an item only after confirming it on the live WordPress host.

---

## A. Create

- [ ] Create opens modal
- [ ] Project Name required
- [ ] Description / Category / Visibility / Language / Cover optional
- [ ] Visibility defaults to private
- [ ] Language defaults to ko (or expected default)
- [ ] Create succeeds
- [ ] Lands on **Workspace Overview** (not project list)
- [ ] Active Project is set after create

## B. Workspace

- [ ] Route remains `project-detail` (URL/hash behavior intact)
- [ ] Title shows Project Workspace / project name
- [ ] Overview shows meta + Studio Launcher + recent works
- [ ] Assets lists Gallery items for `project_id`
- [ ] Asset filters work (All / Image / Video / …)
- [ ] Remove from Project unlinks only (Gallery original remains)
- [ ] History shows Gallery filter sorted by created_at
- [ ] Notes save + notes_updated_at updates
- [ ] Settings update name/description/category/visibility/language/cover
- [ ] Settings Delete removes project link (not Gallery bodies)
- [ ] Members shows **준비 중 / Soon** (no fake features)
- [ ] Timeline shows **준비 중 / Soon** (no fake features)

## C. Active Project

- [ ] Studio Launcher keeps Active Project and opens Studio route
- [ ] Studio page shows Project Context Banner when active
- [ ] Open Workspace returns to project-detail
- [ ] Change / Select Project works
- [ ] Clear Project hides/idle banner
- [ ] Same browser session: Active Project survives Studio navigation / soft refresh
- [ ] New browser session: Active Project starts empty (sessionStorage)

## D. Recent Works

- [ ] Open works
- [ ] Preview works
- [ ] Add to Project → picker when projects exist
- [ ] Add to Project with **no** projects → Create Dialog → auto-link → Workspace

## E. Translator

- [ ] Project 저장 uses shared flow (not silent “My Project” only)
- [ ] Existing project can be selected
- [ ] No projects → Create Dialog → link translation `gallery_id`
- [ ] Linked translation appears under Workspace Assets / History
- [ ] Gallery item body is not duplicated

## F. 보안

- [ ] Other user’s project id returns not found / forbidden
- [ ] Other user’s gallery_id cannot be linked
- [ ] Delete only own projects
- [ ] Logged-out user cannot mutate projects

## G. 회귀

- [ ] Translator (text path)
- [ ] Gallery
- [ ] Credits
- [ ] Image
- [ ] Video
- [ ] Music
- [ ] Voice
- [ ] Avatar
- [ ] Writing
- [ ] Admin Console

---

## Deploy notes

1. Upload `yooy-ai-studio-11.16.0-project-workspace-rc1.zip`
2. Confirm `assets/js/active-project.js` is enqueued before `studio.js`
3. Hard-refresh Studio assets
4. Run A → G
5. Only after this RC passes on live should Phase 3-B (`project_id` on generate) start

**Not verified on live server as of packaging time.**
