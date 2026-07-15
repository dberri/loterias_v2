# Automation & Scheduling Specification

**Source**: **Not** a transcription. Rebuilt from scratch on 2026-07-13 — the source doc (`docs/superpowers/specs/2026-07-11-automation-and-scheduling-design.md`) contradicted AD-003/AD-006 and described models that do not exist. See `context.md` for the full defect list.
**Context**: `.specs/features/automation-and-scheduling/context.md` (decisions gathered 2026-07-13)
**Depends on**: `seo-draw-page-generation` (hard — `Page`, `PageStatus`, `PageAssembler`, `config('content.auto_publish')`, and the batch pipeline must all exist)
**Scope**: Scheduling, unattended operation, and failure visibility for the existing scrape → generate → publish pipeline.

## Problem Statement

Every step of the pipeline is a command someone has to remember to run. `app:scrape-draw` fetches a draw, `app:create-pages` submits a batch, and a human promotes the result to `Published` — but nothing runs unless a human runs it, and nothing tells anyone when a run fails. The commercial goal is ad traffic from a steadily growing page corpus; that requires new draws becoming live pages without supervision, and it requires a broken pipeline to announce itself rather than silently stop producing pages for a month.

## Goals

- [ ] A new draw becomes a `Generated` page with zero human action — scraped and batch-generated on a schedule
- [ ] The pipeline is self-healing: a failed or missed run is corrected by the next run, with no retry queue, dead-letter table, or checksum machinery
- [ ] A broken pipeline (stalled scraping, expired batch, piling `Failed` pages) sends an email rather than going quiet
- [ ] Automation is provably correct with `content.auto_publish` both `false` (v1 default) and `true` (the eventual unsupervised state)

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ------- | ------- |
| The `content.auto_publish` flag itself | Already delivered by `seo-draw-page-generation` (AD-006). This feature consumes it; it does not build it |
| Deciding *when* to flip `auto_publish` to `true` | An operator judgment call about prompt quality, not a system behavior. Automation must simply be correct either way |
| Queue worker + scheduler provisioning on the deploy target | Own spec: `infrastructure-cloud-postgres-backups`. This feature assumes a working worker and scheduler exist |
| Per-game auto-publish trust counters ("publish game X after N clean reviews") | Needs a review-tracking substrate that does not exist. Deferred (`context.md` → Deferred Ideas) |
| Cadence-aware burst polling around expected draw times | Rejected for v1: buys freshness that has no SEO value here, at the cost of a draw-schedule table that must be maintained as Caixa changes it |
| Backfilling historical draws | `app:scrape-draws {game} {quantity} {latest_draw_number}` already covers this manually. Automation keeps up with new draws; it does not go backwards |
| Metrics exporters, dashboards, `/health` endpoints, worker heartbeats, circuit breakers, custom dead-letter tables, per-queue concurrency caps | Ops inflation for a solo-operated, low-volume site. Laravel's built-in `failed_jobs` + logs + email + one Filament widget is the entire observability budget (AD-009) |
| Retry/backoff policy with jitter for scrapes | Structurally unnecessary under a daily sweep — tomorrow's run *is* the retry |

---

## Assumptions & Open Questions

Every ambiguity is resolved or recorded here — nothing is left silently unclear.

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| A working queue worker and scheduler exist in the deploy target | Assumed, not provisioned. This feature registers scheduled tasks; making `schedule:run` and `queue:work` actually run reliably is `infrastructure-cloud-postgres-backups`'s job | Splitting these keeps each feature independently shippable; automation is testable locally without a deploy target | y — explicit boundary, agreed in discuss |
| Which days/times each Caixa game draws | **Deliberately not encoded anywhere.** The sweep runs daily per game regardless; a non-draw day is a harmless no-op | Chosen in discuss: a schedule table is a maintenance liability that buys freshness with no SEO value for these queries. Also avoids asserting draw schedules from memory, which the Knowledge Verification Chain forbids | y — decided in discuss |
| Exact scheduled run times | Config-driven, set at implementation to sit after each game's latest plausible draw time | A time-of-day is a tuning value, not a testable behavior | y — agent's discretion per `context.md` |
| Alert threshold values (stale-draw gap, `Failed`-page count) | Config-driven with sensible defaults chosen at implementation | Same reasoning — thresholds must be tunable without a code change | y — agent's discretion per `context.md` |
| What "a game has gone stale" means, given draw days are not encoded | A configurable max-gap **in days** since that game's most recent `Draw`, per game. Not derived from a draw calendar (there isn't one) | Falls out of the no-schedule-table decision: staleness must be expressed in a way that needs no calendar | y — consistent consequence of an explicit decision |
| Whether a scrape failure should block that day's generation sweep | No. The sweeps are fully decoupled; generation runs against whatever `scopeWithoutPage()` returns, regardless of scrape outcome | This *is* the self-healing property — coupling them would reintroduce the failure cascade the decoupling exists to prevent | y — decided in discuss |
| Whether an alert email failing to send should fail the health check | No. A mail failure is logged and the check exits successfully | An unsendable alert must not itself become a pipeline failure that generates another unsendable alert | y — non-obvious but forced; recorded rather than left to implementation |

**Open questions:** none — all resolved or logged above.

---

## User Stories

### P1: Unattended scrape → generate ⭐ MVP

**User Story**: As the site operator, I want new draws to be scraped and turned into `Generated` pages on a schedule, so that the page corpus grows without me running commands.

**Why P1**: This is the feature. Without it, "automation" is just the manual pipeline with extra documentation.

**Acceptance Criteria**:

1. WHEN the scheduler runs the daily scrape sweep THEN the system SHALL invoke `app:scrape-draw {game}` once per game in `GamesEnum`, each fetching that game's next unknown draw.
2. WHEN a scheduled scrape finds no new draw (the draw has not happened, or results are not yet posted) THEN the system SHALL treat this as a **successful no-op** — no error, no alert, no failed job, no state change.
3. WHEN a scheduled scrape fails (network error, Caixa API error, unparseable response) THEN the system SHALL log the failure and exit without affecting any other game's scrape and without blocking that day's generation sweep.
4. WHEN the scheduler runs the daily generation sweep THEN the system SHALL invoke `app:create-pages {game} {quantity}` per game, which selects draws via `Draw::scopeWithoutPage()` (DRAWPAGE-12) and submits them as a **single batch per game** — never one batch or one job per draw.
5. WHEN the generation sweep runs and no draws lack a page THEN the system SHALL complete as a successful no-op (DRAWPAGE-11's existing behavior, exercised here on a schedule).
6. WHEN a draw was missed by a failed scrape on day N THEN the generation sweep on day N+1 SHALL pick it up automatically once a subsequent scrape succeeds, with no retry queue, dead-letter entry, or manual intervention.
7. WHEN the pipeline runs unattended end to end THEN the only dedup mechanism SHALL be `Draw::scopeWithoutPage()` — no checksum, no idempotency key, no duplicate-detection table.

**Independent Test**: Freeze time; fake the Caixa API to return a new draw for one game and nothing for the others; run the scheduled scrape sweep and assert exactly one new `Draw` and zero errors. Fake the provider at the `BatchContentProvider` interface; run the generation sweep and assert exactly one batch was submitted containing exactly that draw, and one `Page` exists at `status = Generating`. Re-run both sweeps and assert **nothing new is created** (idempotency via `scopeWithoutPage`).

---

### P2: Failure visibility

**User Story**: As the site operator, I want an email when the pipeline is actually broken and a dashboard widget I can glance at, so that a silently dead pipeline can't go unnoticed for weeks.

**Why P2**: The pipeline functions without it — but an unsupervised pipeline you can't see is exactly how you discover in September that generation stopped in July.

**Acceptance Criteria**:

1. WHEN a scheduled health check runs AND a game's most recent `Draw` is older than that game's configured max-gap THEN the system SHALL send an alert email identifying the game and the age of its latest draw.
2. WHEN a scheduled health check runs AND the count of `Page` rows at `status = Failed` exceeds the configured threshold THEN the system SHALL send an alert email with that count.
3. WHEN a batch reaches `expired` or `cancelled` THEN the system SHALL send an alert email identifying the batch (DRAWPAGE-08 already marks its pages `Failed`; this adds the operator signal).
4. WHEN an alert condition is detected AND has already been alerted on and is still ongoing THEN the system SHALL NOT re-send an identical email on every subsequent check — an unresolved condition alerts once, not daily forever.
5. WHEN sending an alert email fails THEN the system SHALL log the mail failure and the health check SHALL still exit successfully — a broken mailer must not become a second failing pipeline.
6. WHEN an admin opens the Filament dashboard THEN a widget SHALL display `Page` counts by `PageStatus`, and the most recent `Draw` date per game.
7. WHEN no alert condition is met THEN the health check SHALL send no email at all — silence means healthy.

**Independent Test**: Seed a `Draw` older than the configured gap for one game; run the health check with `Mail::fake()` and assert exactly one email naming that game. Run it again and assert **no second email** (AC 4). Seed `Failed` pages past the threshold and assert the corresponding email. Seed a clean state and assert zero emails sent. Render the Filament widget and assert its counts match the seeded data.

---

### P3: Correct behavior under auto-publish

**User Story**: As the site operator, once I trust the prompt, I want to flip `content.auto_publish` to `true` and have the scheduled pipeline produce live pages with no further changes.

**Why P3**: v1 runs with the flag `false` and a human in the loop. This story only asserts that flipping the flag needs no code change — it does not flip it.

**Acceptance Criteria**:

1. WHEN `config('content.auto_publish')` is `false` AND the scheduled pipeline runs end to end THEN successfully generated pages SHALL land at `status = Generated` and SHALL NOT be publicly reachable (DRAWPAGE-15, exercised through the scheduler).
2. WHEN `config('content.auto_publish')` is `true` AND the scheduled pipeline runs end to end THEN successfully generated pages SHALL land at `status = Published` and SHALL be reachable at `/{game}/resultado/{concurso}` with no human action anywhere in the chain (DRAWPAGE-16, exercised through the scheduler).
3. WHEN the flag is `true` THEN a page that fails validation SHALL still land at `status = Failed` and SHALL NOT be published — auto-publish SHALL NOT bypass the validation gate.

**Independent Test**: Run the full scheduled pipeline twice against the same faked provider response with the flag `false` then `true`; assert the resulting `Page::status` differs accordingly and that only the `true` run yields a 200 at the public route. Then run with the flag `true` and a *schema-invalid* faked response; assert `status = Failed` and a 404.

---

## Edge Cases

- WHEN two scheduled runs of the same sweep overlap (a slow run still executing when the next fires) THEN the system SHALL prevent concurrent execution (Laravel's `withoutOverlapping()`), so a slow Caixa response cannot cause double-scraping or double-batching.
- WHEN the Caixa API returns a draw the system already has THEN the scrape SHALL be a no-op — no duplicate `Draw` row (existing `app:scrape-draws` skip behavior, now exercised on a schedule).
- WHEN `app:create-pages` runs while a previous batch for the same game is still `in_progress` THEN the draws in that batch already have `Page` rows (at `status = Generating`), so `scopeWithoutPage()` excludes them and they SHALL NOT be resubmitted in a second batch.
- WHEN every game's scrape fails on the same day (e.g. Caixa is down entirely) THEN each failure SHALL be logged independently and the stale-draw health check SHALL eventually alert — but a single bad day SHALL NOT alert, since the max-gap threshold spans multiple days.
- WHEN a game is added to `GamesEnum` (per `additional-lotteries`) THEN it SHALL be picked up by both sweeps and by the health check **without** a code change to the scheduler — the sweeps iterate the enum, they do not hardcode a game list.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| AUTO-01 | P1: Daily scrape sweep, one run per game from `GamesEnum` | Design | Pending |
| AUTO-02 | P1: No new draw → successful no-op, no alert | Design | Pending |
| AUTO-03 | P1: Scrape failure is isolated — no cascade, does not block generation | Design | Pending |
| AUTO-04 | P1: Daily generation sweep — one batch per game via `scopeWithoutPage()` | Design | Pending |
| AUTO-05 | P1: Generation sweep with nothing to do → successful no-op | Design | Pending |
| AUTO-06 | P1: Self-healing — a missed draw is picked up by a later sweep, no retry machinery | Design | Pending |
| AUTO-07 | P1: `scopeWithoutPage()` is the sole dedup mechanism | Design | Pending |
| AUTO-08 | P2: Stale-draw email alert (per-game configurable max gap) | Design | Pending |
| AUTO-09 | P2: `Failed`-page threshold email alert | Design | Pending |
| AUTO-10 | P2: Expired/cancelled batch email alert | Design | Pending |
| AUTO-11 | P2: Alert de-duplication — an ongoing condition alerts once, not every run | Design | Pending |
| AUTO-12 | P2: Mail failure is logged; health check still exits successfully | Design | Pending |
| AUTO-13 | P2: Filament widget — page counts by status, latest draw per game | Design | Pending |
| AUTO-14 | P2: Healthy state sends zero emails | Design | Pending |
| AUTO-15 | P3: `auto_publish = false` → `Generated`, not publicly reachable | Design | Pending |
| AUTO-16 | P3: `auto_publish = true` → `Published`, live, zero human action | Design | Pending |
| AUTO-17 | P3: `auto_publish = true` does NOT bypass validation — invalid still `Failed` | Design | Pending |
| AUTO-18 | Edge case — `withoutOverlapping()` prevents concurrent sweep runs | Design | Pending |
| AUTO-19 | Edge case — in-flight batch's draws are not resubmitted | Design | Pending |
| AUTO-20 | Edge case — sweeps iterate `GamesEnum`; a new game needs no scheduler change | Design | Pending |

**ID format:** `AUTO-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 20 total, 0 mapped to tasks, 20 unmapped ⚠️ (tasks.md not yet generated)

---

## Success Criteria

- [ ] Nobody runs a command for a week and the page corpus still grows
- [ ] Killing the scheduler for three days and restarting it produces the missed pages, with no manual backfill and no retry queue
- [ ] A deliberately broken Caixa API produces exactly one alert email, not one per day forever
- [ ] Flipping `content.auto_publish` to `true` requires editing one config value and nothing else
- [ ] The entire observability surface is: Laravel's `failed_jobs` table, the log, one email, one Filament widget — no new infrastructure
