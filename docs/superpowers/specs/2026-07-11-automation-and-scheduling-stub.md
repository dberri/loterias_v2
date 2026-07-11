# Automation & Scheduling — Spec Stub

**Date:** 2026-07-11
**Status:** Backlog (not yet designed)
**Depends on:** [SEO Draw-Page Generation](./2026-07-11-seo-draw-page-generation-design.md)

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
