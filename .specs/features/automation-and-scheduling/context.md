# Automation & Scheduling Context

**Gathered:** 2026-07-13
**Spec:** `.specs/features/automation-and-scheduling/spec.md`
**Status:** Ready for design

---

## Why this context.md exists (and the source design doc does not)

The source document `docs/superpowers/specs/2026-07-11-automation-and-scheduling-design.md` was **not transcribed**. It was rebuilt from scratch, because it contradicted decisions already approved in `.specs/STATE.md` and described a system that does not exist:

| Source doc says | Reality | Verdict |
| --------------- | ------- | ------- |
| Persists "Content records" / "Content drafts" | AD-003: draw content lives on Fabricator `Page` rows; `DrawPage` is dropped. There is no `Content` model, planned or real | Contradicts an approved decision |
| A per-draw `GenerateDrawPage` job carrying `draw_id + payload` | The pipeline is batch-based (`app:create-pages` → `submitBatch` → `CheckCompletionBatch`). One job per draw silently discards the Batch API's cost advantage — the reason the batch path exists at all | Contradicts the architecture's economic rationale |
| A `PendingReview` state | AD-006 fixes the machine at `Generating → Generated → Published (+ Failed)` | Invents a state |
| A custom `failed_jobs` migration (uuid, job_type, draw_id, payload, traceback…) | Laravel already ships a `failed_jobs` table | Would collide with the framework |
| `app:scrape-draws --game=XXX` | Real signature is positional: `app:scrape-draws {game} {quantity} {latest_draw_number}` | Wrong CLI contract |
| Checksum-based idempotency on draws | Dedup is already solved by `Draw::scopeWithoutPage()` (DRAWPAGE-12) | Invents a parallel mechanism for a solved problem |
| Circuit breakers, 3 named queues with concurrency caps, worker heartbeat rows, `/health` endpoints, metrics exporters, dashboards | A solo-operated SEO site generating a handful of pages per week | Ops inflation — see the posture decision below |

Everything below was decided with the user on 2026-07-13, grounded in what actually exists.

---

## Feature Boundary

Make the existing pipeline run hands-off: scheduled scraping of new draws, scheduled batch generation of pages for draws that lack one, and enough failure visibility that a broken pipeline announces itself instead of going quiet. The publish gate itself (`content.auto_publish`) is **not** built here — it already exists per AD-006. This feature guarantees automation behaves correctly with the flag in **either** position.

---

## Implementation Decisions

### Polling strategy — fixed daily sweep

- One scheduled scrape per game per day, run after the latest plausible draw time for that game.
- The scheduler holds **no knowledge of which days each game draws.** It sweeps daily regardless; on a non-draw day (or before results are posted) the Caixa API simply has nothing new and the run is a no-op.
- **Rationale:** a result that lands a few hours late has no SEO cost — these pages compete on existing-draw queries, not on breaking news. Buying freshness with a cadence table (and the maintenance burden of keeping it correct as Caixa changes schedules) was judged a bad trade.
- Draw days/times are therefore **not** encoded anywhere. This is a deliberate non-feature.

### Pipeline chaining — decoupled schedules

- Scraping and generation are **two independent scheduled tasks**. A scrape never enqueues generation.
- The generation sweep runs `app:create-pages` and picks up whatever `Draw::scopeWithoutPage()` returns — regardless of when, how, or whether it was scraped by the scheduler or by hand.
- **Rationale:** preserves the Batch API economics (one batch for the day's draws, not one batch per draw), keeps `scopeWithoutPage()` as the single dedup mechanism, and makes the system self-healing — a scrape that fails on Tuesday is simply picked up by Wednesday's sweep with no retry machinery, no dead-letter queue, no checksum.
- This is the decision that kills the source doc's `GenerateDrawPage`-per-draw job.

### Publish policy — manual gate, flipped by hand

- Automation ships with `content.auto_publish = false`. Generated pages accumulate at `status = Generated` and are promoted in Filament by a human.
- The flag is flipped globally, by hand, once prompt quality is trusted. There is **no** per-game trust counter, no auto-approval heuristic, no review-tracking mechanism (all of that would be new scope with no existing substrate).
- **This feature's obligation is that automation is correct under both flag settings** — it does not decide when the flag flips.

### Failure visibility — email + Filament widget

- **Email**, via Laravel's existing mail config, on conditions that mean the pipeline is actually broken:
  - a game has produced no new draw for longer than its expected gap
  - a batch reached `expired`/`cancelled`
  - `Failed` pages exceed a threshold
- **Filament widget** on the admin dashboard showing at-a-glance counts: pages by status, last successful scrape per game, current batch state.
- **Explicitly rejected:** metrics exporters, Grafana-style dashboards, `/health` endpoints, worker heartbeat tables, circuit breakers, per-queue concurrency caps, a custom dead-letter table. Laravel's built-in `failed_jobs` table plus the log plus these two surfaces are the entire observability budget.

### Agent's Discretion

- Exact scheduled run times (e.g. `dailyAt('23:30')`) — to be set at implementation from each game's latest plausible draw time; a config value, not a spec commitment.
- Threshold values for the alert conditions (how many `Failed` pages, how long a "too long since last draw" gap is) — sensible defaults chosen at implementation, config-driven so they can be tuned without a code change.
- Filament widget layout/composition.
- Whether the health check is its own artisan command or a scheduled closure.

### Declined / Undiscussed Gray Areas → Assumptions

- **Queue driver / worker topology** — not discussed; belongs to `infrastructure-cloud-postgres-backups`, which owns the deploy target. Recorded in the spec's Assumptions table: this feature assumes *a* working queue worker and *a* working scheduler exist, and does not provision them.
- **Backfill of historical draws** — the existing `app:scrape-draws {game} {quantity} {latest_draw_number}` already covers manual backfill. Automation does not backfill; it only keeps up with new draws. Logged as an assumption.

---

## Specific References

No product references were offered. The governing preference expressed throughout is **operational minimalism**: this is a low-traffic, solo-operated site, and every piece of ops machinery is a liability until something actually breaks. When in doubt during design and implementation, choose the option with fewer moving parts.

---

## Deferred Ideas

- **Per-game auto-publish trust counters** (auto-publish game X after N clean reviews) — real idea, needs a review-tracking substrate that does not exist. Revisit after the flag has been globally true for a while.
- **Cadence-aware burst polling** (poll every 15min around expected draw time) — rejected for v1 as a bad freshness-for-maintenance trade, not as a bad idea. Revisit only if page freshness is ever shown to affect ranking for these queries.
- **Retry/backoff policy with jitter for scrapes** — the daily sweep makes this unnecessary (tomorrow's run *is* the retry). Would become relevant only if the sweep moved to a tighter cadence.
