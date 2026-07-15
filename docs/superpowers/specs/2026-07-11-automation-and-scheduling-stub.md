# Automation & Scheduling — Spec Stub

**Date:** 2026-07-11
**Status:** ✅ Superseded — spec + design complete at `.specs/features/automation-and-scheduling/`
**Depends on:** [SEO Draw-Page Generation](./2026-07-11-seo-draw-page-generation-design.md)

> ⚠️ **The 2026-07-11 design doc for this feature was rejected and rebuilt (2026-07-13).**
> `./2026-07-11-automation-and-scheduling-design.md` contradicted approved decisions AD-003 and AD-006
> (it invented "Content records" and a `PendingReview` state), replaced the batch pipeline with a per-draw
> job, specified a custom `failed_jobs` migration that collides with Laravel's own, and used CLI signatures
> the commands do not have. **Do not implement from it.** The authoritative spec is
> `.specs/features/automation-and-scheduling/` — see its `context.md` for the full defect table.
>
> The open questions below were all resolved in the 2026-07-13 discuss pass: daily fixed sweep (no draw-schedule
> table), decoupled scrape/generate schedules, manual publish gate flipped by hand, email + Filament widget.

## Intent

Run the whole pipeline hands-off: scheduled scrape → generate → publish, so new draws become live pages without supervision.

## Likely scope

- Laravel scheduler entries for `app:scrape-draw(s)` per game on the real draw cadence.
- Scheduled `app:create-pages` for draws-without-a-page.
- Flip `content.auto_publish` to `true` once prompt quality is trusted (the seam defined in the draw-page spec).
- Health/monitoring: alert when scraping fails, a batch expires, or pages pile up in `Failed`.
- Queue worker + scheduler must run reliably in the deploy target (ties into the infra spec).

## Open questions

- Draw schedules per game (days/times); how far ahead to poll.
- Retry/backoff policy for failed scrapes and failed generations.
- Do we ever auto-publish, or always hold a review queue for the first N of each game?
- Where do alerts go (email, push, log-only)?
