# Projects Server Validation Checklist

Release Candidate: `yooy-ai-studio-11.16.0-projects-fix-rc1.zip`  
Plugin version: **11.16.0** (unchanged)  
Scope: Projects REST recovery + workspace UI wiring only  
Status: **code packaged / live server not yet verified**

Do not check an item unless it was confirmed on the live WordPress host.

---

## A. REST Health

Request (logged-in browser or authenticated client):

```http
GET /wp-json/yoy-ai-studio/v1/core/rest-health
```

Fallback if pretty permalinks fail:

```http
GET /index.php?rest_route=/yoy-ai-studio/v1/core/rest-health
```

Confirm:

- [ ] Response success
- [ ] Registered routes include `/projects`
- [ ] Registered routes include `/projects/(?P<id>…)`
- [ ] Projects module appears loaded (module list / health payload)
- [ ] No `rest_no_route` for Projects endpoints

---

## B. 목록 (List)

- [ ] Log in to Studio
- [ ] Open Projects page
- [ ] No `REST API Route Not Found — GET /projects`
- [ ] Empty state shows when user has no projects
- [ ] Empty CTA “첫 프로젝트 만들기” (or equivalent) works
- [ ] Existing projects render as cards/list when present

---

## C. 생성 (Create)

- [ ] Create opens modal/dialog
- [ ] Project name required validation works
- [ ] Description optional
- [ ] Create Project succeeds
- [ ] List refreshes immediately with the new project
- [ ] Detail view opens (or is reachable) after create
- [ ] Failed create shows friendly error (no raw stack/HTML)

---

## D. 수정 (Update / Rename)

- [ ] Rename/Edit succeeds
- [ ] Title/description persist after full page refresh
- [ ] Editing another user’s project ID returns not-found / forbidden (IDOR blocked)

---

## E. 삭제 (Delete)

- [ ] Delete asks for confirmation
- [ ] Delete succeeds
- [ ] Project removed from list after refresh
- [ ] Deleted project detail URL no longer loads the project

---

## F. Asset 연결

- [ ] Link own Gallery Asset to project
- [ ] Asset appears on project detail
- [ ] Duplicate link is blocked or idempotent (no double count abuse)
- [ ] Unlink / remove asset from project works
- [ ] Linking another user’s `gallery_id` is rejected
- [ ] Asset body is not duplicated into Projects Store (Gallery remains SoT)

---

## G. 회귀 (Regression)

Confirm each studio/surface still loads and performs its primary action:

- [ ] Translator
- [ ] Gallery
- [ ] History
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

1. Upload/replace plugin with `yooy-ai-studio-11.16.0-projects-fix-rc1.zip`
2. Ensure `yooy-ai-studio/modules/projects/includes/class-projects-rest.php` exists on server
3. Hard-refresh Studio assets (cache bust) after deploy
4. Run A → G in order
5. Record pass/fail with timestamp and host URL in the release thread

**Not verified on live server as of packaging time.**
