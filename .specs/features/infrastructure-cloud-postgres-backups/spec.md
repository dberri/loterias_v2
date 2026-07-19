# Infrastructure: Laravel Cloud, Postgres, Backups Specification

**Source**: `docs/superpowers/specs/2026-07-11-infrastructure-cloud-postgres-backups-design.md` (approved design, transcribed into TLC format; ops surface trimmed to match AD-009's minimal posture)
**Depends on**: `seo-draw-page-generation` **soft** — the `pages` table it backs up is created there. No code dependency; this feature can be built in parallel and only its backup targets reference `pages`.
**Blocks**: `automation-and-scheduling` — that feature's scheduled entries are inert until a scheduler and queue worker actually run somewhere.
**Scope**: Deploy target, database standardization on PostgreSQL, and durability of generated content.

## Problem Statement

The app runs on SQLite locally and MySQL in Sail's container, and it runs nowhere else — there is no deploy target, no queue worker, and no scheduler process, which means `automation-and-scheduling`'s scheduled tasks have nothing to execute them. Separately, the AI-generated page corpus is the single most expensive and least reproducible asset the project has: draws can always be re-scraped from Caixa, but regenerating a year of published pages means paying the LLM bill again and accepting that the output will not be identical. Today that corpus exists in exactly one place, with no backup.

## Goals

- [ ] The app runs on Laravel Cloud as three processes — web, queue worker, scheduler — so scheduled automation actually executes
- [ ] PostgreSQL is the database in every environment (local, CI, production), so a dialect bug cannot reach production undetected
- [ ] The generated page corpus survives the loss of the primary database, with a restore path that has been **actually executed at least once**, not just documented
- [ ] Running cost stays low enough that cold starts are an acceptable trade

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ------- | ------- |
| Cost comparison across hosting providers (Vercel, Fly, Render, bare VPS) | Laravel Cloud is a chosen constraint, not an open question. Re-litigating the host is a different project |
| Multi-region high availability, read replicas, failover | Nonsensical for a low-traffic SEO site. An hour of downtime costs approximately nothing here |
| Near-real-time backup (CDC, hourly exports, PITR beyond provider default) | RPO of 24h is a deliberate, accepted constraint — losing at most one day of draws (which are re-scrapeable anyway) is tolerable |
| Metrics exporters, Grafana/Prometheus, APM, `/health` endpoints | AD-009: the observability budget is Laravel's `failed_jobs` + logs + one email + one Filament widget. Infrastructure does not get to reopen that |
| Zero-downtime migration with a maintenance-window-free cutover | A few minutes of downtime on a site with no logged-in users and no writes from the public is free. Engineering around it is waste |
| Scheduled task definitions | Own spec: `automation-and-scheduling` owns *what* is scheduled; this feature owns *that a scheduler exists to run it* |

---

## Assumptions & Open Questions

Every ambiguity is resolved or recorded here — nothing is left silently unclear.

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Postgres in **every** environment, including local dev | Yes — SQLite and MySQL are both retired. Sail's `docker-compose.yml` is repointed to Postgres | Carried from the source design's "Constraints Chosen". Environment parity is the entire point: a JSON query that works on SQLite and breaks on Postgres is exactly the bug this prevents, and it can only be prevented by running the same engine everywhere | y — user-approved constraint in source design |
| Backup strategy | **Dual-layer**: Laravel Cloud native DB backups **+** a nightly app-level export of `draws` and `pages` to object storage | Native backups are the fast full-restore path but are provider-coupled. The app-level export is portable insurance that survives losing the provider account itself. The generated corpus is too expensive to trust to one mechanism | y — user-approved constraint in source design |
| Recovery objectives | RPO 24h, RTO 8h | User-approved in source design. Both are generous because the loss scenario is genuinely cheap: draws are re-scrapeable from Caixa, and the site being stale for a day costs a rounding error in ad revenue | y |
| Runtime posture | Cost-first. Cold starts accepted for web and worker | User-approved. Traffic is search-driven and latency-tolerant; a cold start on an occasional request is not worth paying to avoid | y |
| Separate immutable archive of raw Caixa JSON | **Not built.** `draws.raw_data` is included in the nightly export, which is sufficient | The source design explicitly decided against it. `raw_data` is already the raw payload — a second copy of the same bytes in a different bucket adds a system without adding durability | y |
| Whether the existing MySQL/SQLite data must be migrated to Postgres | **Only `draws`.** Per AD-003, existing `draw_pages` rows are discarded (their format changes entirely) and pages are regenerated. So the cutover moves draws and nothing else | Falls directly out of AD-003 — the page corpus does not exist yet at the time of this migration, which makes the cutover far cheaper than the source design assumed | y — grounded against AD-003 |
| Which Postgres version | Whatever Laravel Cloud provisions by default; local and CI pin to that same major version | Pinning a version in a spec guarantees it is stale before it ships. The requirement is *parity*, not a specific number | y |
| Whether app code currently contains MySQL/SQLite-specific SQL | **Assumed minimal but must be verified.** `Draw`'s accessors read `raw_data` in PHP, not SQL, and the codebase is Eloquent-only per project convention (no raw `DB::`) | Verification is a task, not an assumption I get to hand-wave. Any `whereJsonContains`/`whereJsonPath` usage is the likely dialect landmine | n — **verify during implementation** (bounded, assigned task) |
| **NUL bytes in `raw_data` will corrupt the Postgres migration** | **Confirmed, and the failure mode was corrected on 2026-07-18.** An audit of the 2,608 seeded Mega-Sena payloads (2026-07-13) found **415 of them contain a NUL byte (`\0`) in `nomeTimeCoracaoMesSorte`** — junk padding returned by the Caixa API. ⚠️ The original claim here ("Postgres rejects `\0` in `jsonb`, so ~16% of rows fail to insert") was **measured wrong**: `raw_data` is a **`json`** column, not `jsonb`, and PG 17.10 **accepts** `\0` into `json`. The real failure is **worse** — the rows insert cleanly, then raise SQLSTATE 22P05 on any `->>` extraction, and `ALTER TYPE ... jsonb` is blocked thereafter | Corrects a **loud** predicted failure into a **silent latent** one, which raises the stakes on INFRA-21 rather than lowering them: nothing during cutover would have signalled the problem. Still deliberately NOT fixed in `seo-draw-page-generation` — `raw_data` is the fact source of truth (AD-001), so the fix belongs at the storage boundary. See INFRA-21 and AD-012 | y — **audit confirmed; failure mode re-measured 2026-07-18; fix scoped to INFRA-21** |

**Open questions:** none — all resolved or logged above. The one `n` row is an assigned verification task.

---

## User Stories

### P1: Deployed on Laravel Cloud with a working scheduler and worker ⭐ MVP

**User Story**: As the site operator, I want the app running on Laravel Cloud with a queue worker and a scheduler process, so that automated scraping and page generation actually execute and the public pages are reachable on the internet.

**Why P1**: Everything else in the project is theoretical until it runs somewhere. `automation-and-scheduling` is entirely inert without this.

**Acceptance Criteria**:

1. WHEN the app is deployed THEN Laravel Cloud SHALL run three process types: a **web** process serving public pages and `/admin`, a **queue worker** processing jobs, and a **scheduler** running `schedule:run`.
2. WHEN a job is dispatched (e.g. `CheckCompletionBatch`) THEN the queue worker SHALL pick it up and execute it, and a job that throws SHALL land in Laravel's `failed_jobs` table.
3. WHEN a scheduled task is due THEN the scheduler process SHALL execute it — verified by confirming that a scheduled command actually ran unattended, not merely that the process is alive.
4. WHEN a public draw page at `status = Published` is requested in production THEN it SHALL render (200); WHEN a page at any other status is requested THEN it SHALL 404 (DRAWPAGE-05, verified in the deployed environment rather than only in tests).
5. WHEN a deploy runs THEN migrations SHALL execute against the production Postgres database as part of the deploy pipeline.

**Independent Test**: Deploy; dispatch a trivial job and assert it is consumed by the worker; register a scheduled command that writes a timestamped log line, wait for it to fire unattended, and assert the line exists; request a published page (200) and an unpublished one (404) against the live URL.

---

### P2: PostgreSQL everywhere

**User Story**: As the developer, I want local, CI, and production all running PostgreSQL, so that a dialect-specific bug fails in CI instead of in production.

**Why P2**: The app *works* today on SQLite/MySQL — this is not a functional gap. It is a correctness-of-environment gap, and it must land before there is a production corpus to break.

**Acceptance Criteria**:

1. WHEN the test suite runs in CI THEN it SHALL run against PostgreSQL, not SQLite.
2. WHEN a developer runs Sail locally THEN it SHALL provision PostgreSQL, not MySQL.
3. WHEN every migration in the project runs against a clean PostgreSQL database THEN all SHALL succeed with no dialect errors.
4. WHEN JSON-heavy paths are exercised against PostgreSQL — writing and reading `draws.raw_data` and `pages.blocks`, and every `Draw` accessor that reads out of `raw_data` — THEN each SHALL return values identical to those produced on the previous engine.
5. WHEN the codebase is audited for engine-specific SQL THEN any dialect-dependent construct found SHALL be either replaced with a portable equivalent or documented as intentionally Postgres-specific.
6. WHEN the production cutover completes THEN every row in `draws` SHALL be present in Postgres with `raw_data` intact, verified by row count **and** by sampled deep comparison of `raw_data` payloads.
7. WHEN a `raw_data` payload contains a NUL byte (\0) — as **415 of the 2,608 seeded Mega-Sena payloads do**, in `nomeTimeCoracaoMesSorte` — THEN the system SHALL store it in PostgreSQL such that it remains **readable at SQL level**, and every `Draw` accessor SHALL return the same value it returned pre-migration. **Corrected 2026-07-18 (verified on PG 17.10):** `raw_data` is a `json` column, and `json` **accepts** \0 on insert — only `jsonb` rejects it. The failure is therefore NOT a loud insert error during cutover, as originally specified, but **silent latent corruption**: the rows insert cleanly and then raise SQLSTATE 22P05 on any `->>` extraction, with `ALTER TYPE ... jsonb` blocked thereafter. This SHALL be handled at the **storage boundary** (a cast stripping NUL on write, applied at scrape time and during the backfill) — **not** by rewriting history or sanitizing in the domain layer. See AD-012.
8. WHEN a newly scraped draw arrives from Caixa carrying a NUL byte THEN it SHALL persist successfully — the fix SHALL cover future writes, not merely the one-time backfill.

**Independent Test**: Point CI at Postgres and run the full suite green. Run every migration against an empty Postgres DB. Round-trip a real `raw_data` payload through Postgres and assert every `Draw` accessor returns what it returned before. After cutover, assert `draws` row-count parity and deep-compare a random sample of `raw_data` blobs against the pre-cutover snapshot.

---

### P3: The generated corpus survives losing the database

**User Story**: As the site operator, I want the AI-generated pages backed up in a form I can actually restore, so that a database loss costs me a restore procedure rather than the entire LLM spend and a corpus I can never reproduce identically.

**Why P3**: The corpus is small today, so the loss is currently cheap — but it grows monotonically, and the cost of adding backups rises with it. This must land before the corpus is worth more than the effort.

**Acceptance Criteria**:

1. WHEN a night passes THEN a scheduled export SHALL write `draws` and `pages` to object storage in a portable, provider-independent format, accompanied by a manifest recording the export timestamp, row counts per table, and a checksum of each artifact.
2. WHEN an export completes THEN its manifest checksum SHALL validate against the written artifact; WHEN it does not THEN the export SHALL be treated as failed and SHALL alert via the existing `AlertNotifier` (AUTO-11's de-duplicated email path).
3. WHEN an export job fails THEN it SHALL alert; a silently failing backup is worse than no backup, because it manufactures false confidence.
4. WHEN a restore is performed from an export artifact into an empty database THEN `draws` and `pages` SHALL be fully reconstructed, and a sampled set of previously-`Published` draw pages SHALL render correctly afterward.
5. WHEN the restore procedure is executed for the first time THEN it SHALL be performed **for real** against a scratch database — not merely documented — and the elapsed time SHALL be recorded and confirmed to be within the 8h RTO.
6. WHEN backups are retained THEN daily artifacts SHALL be kept for 35 days and monthly artifacts for 12 months. **Amended 2026-07-18 (AD-013):** the original wording additionally required this be "enforced by object-storage lifecycle rules, not application code". That is **unsatisfiable for the monthly half** — a lifecycle rule matches on prefix, tag and age and cannot express "keep the first export of each month", so monthly selection is necessarily writer-side. Therefore: the **daily** 35-day tier SHALL be a bucket lifecycle rule, and the **monthly** 12-month tier SHALL be produced by `ExportCorpus` promoting an export into a `monthly/{YYYY-MM}/` prefix when that month has none. That prefix SHALL be a **sibling** of `exports/`, never nested within it, so the 35-day rule cannot reach it.

**Independent Test**: Run the export job; assert artifacts and manifest exist and checksums validate. Corrupt an artifact and assert the validation fails and alerts. Drop a scratch database, restore from the artifact, assert row-count parity and that a sample of published pages renders. Time the whole thing.

---

## Edge Cases

- WHEN the export job runs before `seo-draw-page-generation` has shipped (no `pages` table yet) THEN it SHALL export `draws` alone and SHALL NOT fail — this feature must be deployable independently of its soft dependency.
- WHEN the export runs against an empty `pages` table THEN it SHALL produce a valid, empty-but-well-formed artifact with a truthful row count of zero — not an error, and not a silently skipped file.
- WHEN object storage is unreachable at export time THEN the job SHALL fail loudly and alert (AC P3.3), rather than logging and exiting zero.
- WHEN the production cutover's post-migration validation fails THEN the connection config SHALL be reverted to the pre-cutover database and the previous known-good configuration redeployed, with the pre-cutover snapshot left immutable.
- WHEN a cold start delays a request THEN the request SHALL still succeed — cold-start latency is accepted (Assumptions), but a cold start that *errors* is a defect, not a trade-off.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| INFRA-01 | P1: Three process types on Laravel Cloud (web, worker, scheduler) | Design | Deferred — operator-gated (needs Laravel Cloud) |
| INFRA-02 | P1: Queue worker consumes jobs; failures land in `failed_jobs` | Design | Deferred — operator-gated (needs Laravel Cloud) |
| INFRA-03 | P1: Scheduler proven to fire a task unattended | Design | Deferred — operator-gated (needs Laravel Cloud) |
| INFRA-04 | P1: Publish gate verified in the deployed environment (200/404) | Design | Deferred — operator-gated (needs Laravel Cloud) |
| INFRA-05 | P1: Migrations run against production Postgres on deploy | Design | Deferred — operator-gated (needs Laravel Cloud) |
| INFRA-06 | P2: CI runs the suite against Postgres | Execute | Done — Gap 1 (never-executed workflow) resolved: `feat/infrastructure-postgres-backups` pushed, CI observed green (e.g. run 29651679467) |
| INFRA-07 | P2: Local Sail provisions Postgres | Execute | Done |
| INFRA-08 | P2: All migrations succeed on a clean Postgres DB | Execute | Done (manual evidence, not a repeatable test — Gap 4) |
| INFRA-09 | P2: JSON paths (`raw_data`, `blocks`) behave identically on Postgres | Execute | Done |
| INFRA-10 | P2: Engine-specific SQL audited, replaced or documented | Execute | Done |
| INFRA-11 | P2: Cutover — `draws` row-count parity + sampled deep `raw_data` comparison | Design | Deferred — operator-gated (needs real production cutover, T11) |
| INFRA-21 | P2: NUL bytes in `raw_data` (415/2608 real payloads) persist to Postgres without error, on backfill **and** on future scrapes | Execute | Done — strongest-covered requirement in the feature |
| INFRA-22 | P2: Retired engines are genuinely unreachable, not merely absent from app config (framework config merge leaves `sqlite`/`mysql`/`mariadb` live) | Tasks | Open — documented, unmapped (AD-008 amendment) |
| INFRA-12 | P3: Nightly portable export of `draws` + `pages` with manifest | Execute | Done (cron expression is a spec-precision gap — Gap 5) |
| INFRA-13 | P3: Manifest checksum validation; invalid export alerts | Execute | Done |
| INFRA-14 | P3: Export failure alerts (no silent backup failure) | Execute | Done |
| INFRA-15 | P3: Restore reconstructs both tables; sampled pages render | Execute | Done — Gap 2 (no render assertion) resolved: `RestoreCorpusTest.php:174-192` |
| INFRA-16 | P3: Restore executed for real once, timed, within 8h RTO | Design | Deferred — operator-gated (needs a real restore drill, T19) |
| INFRA-17 | P3: Retention — 35 daily, 12 monthly | Execute | Done — Gap 3 (monthly tier unreachable) resolved: `ExportCorpus::promoteToMonthly()`, 7 tests in `ExportCorpusRetentionTest.php` |
| INFRA-18 | Edge case — export works before `pages` exists (independent deployability) | Execute | Done |
| INFRA-19 | Edge case — empty `pages` exports a valid zero-row artifact | Execute | Done |
| INFRA-20 | Edge case — cutover validation failure triggers documented rollback | Design | Deferred — operator-gated (needs real production cutover, T11) |

**ID format:** `INFRA-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 22 total, 21 mapped to tasks, 1 unmapped (INFRA-22, discovered during Execute) ✅ (see `tasks.md` — 21 tasks, 5 phases)

---

## Success Criteria

- [ ] `automation-and-scheduling`'s scheduled sweeps run unattended in production — the infrastructure dependency is discharged
- [ ] The full test suite is green against PostgreSQL in CI, and no environment runs a different engine
- [ ] The database can be dropped and restored from an export artifact, and this has been **done**, not merely written down
- [x] A backup that fails sends an email — no backup ever fails silently
