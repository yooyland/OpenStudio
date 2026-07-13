# YooY AI Studio — Source Authority Policy (Internal)

> Internal development policy for Phase 2+ content grounding.  
> **Not a user-facing feature.** Do not surface this document as a product setting, menu, banner, or onboarding tip.

## Purpose

When Korean Context Engine, Writing, Translator, Research, Chat (and similar internal pipelines) search, verify, or ground Korea-related facts, prefer **official primary sources** over secondary media aggregation.

This file is the canonical policy for a future **Source Authority Layer**. Until that layer ships, implementers follow these rules in prompts and retrieval design without adding new UI.

## Scope

| In scope (internal only) | Out of scope |
|--------------------------|--------------|
| Korean Context Engine | New menus / screens / banners |
| Writing Studio pipelines | New admin “Source Authority” settings UI |
| Translator Studio (when grounding facts) | Emphasizing this policy to end users |
| Research / Chat / prompt composition | Full-site crawling or bulk mirroring of official sites |
| Future Source Authority Layer | Changing existing citation UI chrome |

## Authority order (Korea)

1. **Primary official source for the domain** (see tables below).
2. Other **Korean government / statutory** portals for the same domain.
3. Peer-reviewed or institutional primary data when government primary is unavailable.
4. Reputable secondary reporting — only when primary is unavailable; never as the sole authority for contested official facts.

Never invent citations. If primary confirmation is missing, prefer uncertainty over fabricated attribution.

## Domain priorities

### Presidency / Blue House equivalents

For content about the **President**, **Presidential Office**, speeches, briefings, schedules, national vision, or national agenda items:

- **Highest priority:** [https://www.president.go.kr/](https://www.president.go.kr/)
- Use only pages/materials needed for the specific claim (targeted fetch), not wholesale site replication.

### Law, statistics, courts, elections, finance

Prefer the **direct responsible official body** for that domain, for example:

| Domain | Prefer |
|--------|--------|
| Statutes / regulations | Official statute / ministry / National Assembly legislative portals |
| Statistics | Statistics Korea (KOSTAT) and issuing ministry releases |
| Court judgments | Courts / judiciary official publications |
| Elections | National Election Commission |
| Finance / markets | Financial Services Commission, Bank of Korea, relevant supervisory bodies |

Exact URLs may evolve; always resolve to the current official host for that institution.

## Product / UX constraints

1. Do **not** create user-visible menus, screens, banners, or settings for this policy.
2. Do **not** specially expose or emphasize “source priority policy” to general users.
3. When a feature already requires attribution, use the **existing citation UI** only — no new citation chrome in this phase.
4. Do **not** modify existing Studio Shell menus solely to advertise this policy.
5. Do **not** crawl or bulk-copy official sites. Fetch **minimal, claim-specific** materials under applicable terms of use.

## Implementation notes (future Source Authority Layer)

- Constants live in `plugin/yooy-ai-studio/includes/core/class-yoy-source-authority.php` (policy constants only; no admin UI hooks).
- Cursor / agent rule: `.cursor/rules/source-authority.mdc`.
- Pipelines should consult these constants when composing research or Korea-grounded prompts.
- Translator: apply when generating or verifying Korea-specific factual claims — not for ordinary bilingual phrase translation.

## Non-goals

- SEO scraping farms
- Mirroring president.go.kr or other portals into the plugin
- User education campaigns about “official sources first”
- Blocking all non-official sources (secondary may still inform style/context when labeled appropriately in internal metadata)

---

Last updated for baseline: OpenStudio 11.15.1 development snapshot era / Phase 2+.
