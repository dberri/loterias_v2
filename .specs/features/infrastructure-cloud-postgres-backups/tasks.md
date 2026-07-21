# Infrastructure: Laravel Cloud, Postgres, Backups — Tasks

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user — do not proceed without it.**

---

**Design**: `.specs/features/infrastructure-cloud-postgres-backups/design.md`
**Spec**: `.specs/features/infrastructure-cloud-postgres-backups/spec.md`
**Status**: Code-only scope (T1–T10, T12–T18) done — Verifier's original 3 Major + 4 Minor gaps all resolved since (see `.specs/STATE.md`). T11/T19/T20/T21 remain operator-gated, needing Laravel Cloud credentials.

---

## Execution Scope (this run)

Laravel Cloud is **not yet provisioned**, so this run executes the **15 agent-executable tasks** only:

**In scope:** T1–T10, T12–T18 (Phases 1, 2, 4 + T10 + T18)
**Deferred — operator handoff:** 🔒 T11, T19, T20, T21

The deferred tasks are **not cancelled and not satisfiable by documentation.** They remain the only route to INFRA-01/02/03/04/05/11/16/20, and the feature is **not complete** without them. In particular:

- **T11 (production cutover)** — the design flags this window as *closing*: per AD-003 only `draws` exists to migrate today, and that stays true only until page generation runs at scale. The longer this waits, the more expensive it gets.
- **T19 (restore drill)** — INFRA-16 is satisfiable only by executing a restore. Marking it done from a runbook produces false confidence, which is worse than no backup.

After this run, all export/restore **code** is tested and green, but no backup has ever been taken from production and no restore has ever been performed.

---

## Tasks-Time Decisions (resolved here, as the design required)

| # | Question the design deferred to Tasks | Resolution |
| - | ------------------------------------- | ---------- |
| D1 | **Who builds `AlertNotifier`** (circular dep with `automation-and-scheduling`) | **This feature builds it (T1).** The design's mitigation is "whichever feature executes first carries it"; infrastructure is sequenced first. `automation-and-scheduling`'s tasks.md MUST consume it, not rebuild it. Proposed as **AD-011**. |
| D2 | **Export artifact format** | **NDJSON**, per the design's stated leading candidate — restorable with `jq` and a shell loop, which is the exact scenario Layer 2 exists for. |
| D3 | **Where the NUL-byte fix lives** (INFRA-21) | A **custom Eloquent cast** (`App\Casts\NulSafeJson`) applied to `Draw::raw_data`, stripping `\0` on **write**. Covers scrape-time and backfill in one place. See the AD-001 tension note below. |
| D4 | **Restore mechanism** | An artisan command (`app:restore-corpus`), not a runbook alone — the design left this open ("if the Tasks phase finds one warranted"). Warranted: INFRA-16 requires a *timed* execution, and timing a hand-run sequence of shell steps produces a number that measures the operator, not the procedure. |

> **AD-001 tension, resolved explicitly.** AD-001 says `raw_data` is the fact source of truth and must stay byte-faithful to Caixa. D3 strips a byte. This is deliberate and narrow: the NUL appears only as junk padding inside `nomeTimeCoracaoMesSorte`, a field **no `Draw` accessor reads** and no anchored fact derives from. Stripping it alters zero facts. The alternative — changing `raw_data` to a `text` column to sidestep Postgres' `json` validation — was rejected because it discards column-level JSON validity for the project's most important column in order to preserve bytes that carry no information. **T2 must assert this claim, not assume it**: the accessor-parity test is what makes the stripping safe rather than merely convenient.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec — confirm before Execute. Guidelines found: `CLAUDE.md` (project), `.github/copilot-instructions.md`, `phpunit.xml`. No coverage thresholds configured anywhere; **no CI exists at all**, so the strong default applies for depth.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Service (`app/Services/**`) | unit | All branches; 1:1 to spec ACs; every listed edge case | `tests/Unit/Services/**/*Test.php` | `php artisan test --testsuite=Unit` |
| Cast / Model (`app/Casts/**`, `app/Models/**`) | unit | All branches + every accessor touched; real seeded payloads as fixtures, not synthetic | `tests/Unit/Models/*Test.php`, `tests/Unit/Casts/*Test.php` | `php artisan test --testsuite=Unit` |
| Job (`app/Jobs/**`) | feature | Happy path + every failure branch + alert path | `tests/Feature/Jobs/*Test.php` | `php artisan test` |
| Console command (`app/Console/Commands/**`) | feature | Happy path + validation-failure + rollback paths | `tests/Feature/Commands/*Test.php` | `php artisan test` |
| Migration / config / `docker-compose.yml` / CI workflow | none | — (build gate only; correctness proven by the suite running green against the new engine) | — | build gate only |
| Deploy config / cloud provisioning | none — **operator-verified** | Evidence artifact recorded in the task (log line, HTTP status, timing) | — | manual, evidence recorded |

**Fixture rule (project-specific, carried from the seed-data audit in STATE.md):** tests touching `raw_data` MUST draw fixtures from `database/seeders/lotteries/megasena/draws/` — including at least one of the **415 NUL-bearing payloads** and one **pre-2483 zero-padded** payload. Synthetic or newest-draw-only fixtures hide both known corpus defects.

## Gate Check Commands

> Generated from codebase — confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After tasks with unit tests only | `php artisan test --testsuite=Unit` |
| Full | After tasks with feature tests | `php artisan test` |
| Build | After phase completion or config-only tasks | `vendor/bin/pint --dirty && php artisan test` |

> ⚠️ **Gate commands change meaning at T6.** Once `phpunit.xml` points at Postgres, every gate requires a running Postgres (`./vendor/bin/sail up -d`). Tasks T1–T5 can gate against the current SQLite config; T6 onward cannot. This is a real workflow consequence of AD-008, not an incidental detail.

---

## Execution Plan

Phases are ordered and run sequentially — each phase completes before the next begins, and tasks within a phase execute in order.

### Phase 1: Shared prerequisites & dialect safety (3 tasks)

Everything downstream depends on these. `AlertNotifier` unblocks both this feature and `automation-and-scheduling`; the NUL cast must exist before any row is written to Postgres.

```
T1 → T2 → T3
```

### Phase 2: Postgres everywhere (6 tasks)

```
T4 → T5 → T6 → T7 → T8 → T9
```

### Phase 3: Production cutover (2 tasks — **operator-gated**)

```
T10 → T11
```

### Phase 4: Layer 2 export (6 tasks)

```
T12 → T13 → T14 → T15 → T16 → T17
```

### Phase 5: Restore & deploy (4 tasks — **operator-gated**)

```
T18 → T19 → T20 → T21
```

---

## Task Breakdown

### T1: Create `AlertNotifier` service

**What**: A de-duplicated alert service that sends one email per distinct alert key per suppression window.
**Where**: `app/Services/AlertNotifier.php`
**Depends on**: None
**Reuses**: Laravel `Mail` + `Cache` facades; `config/mail.php`
**Requirement**: INFRA-14 (shared prerequisite; also serves `automation-and-scheduling` AUTO-11)

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `notify(string $key, string $message)` sends an email on first call for a key
- [x] A repeat call with the same key inside the suppression window sends **nothing** (this is the whole point — a backup broken for a week emails once, not seven times)
- [x] The window is configurable and expiry restores sending
- [x] Distinct keys never suppress each other
- [x] Gate check passes: `php artisan test --testsuite=Unit`
- [x] Test count: ≥6 new tests pass (no silent deletions)

**Tests**: unit
**Gate**: quick
**Commit**: `feat(alerts): add de-duplicated AlertNotifier service`

---

### T2: Create NUL-safe JSON cast and apply to `Draw::raw_data`

**What**: A custom cast that strips NUL bytes on write so Postgres' `json` type accepts Caixa payloads, applied at the storage boundary.
**Where**: `app/Casts/NulSafeJson.php` (new), `app/Models/Draw.php` (modify `casts()`)
**Depends on**: None
**Reuses**: `Draw::casts()` (currently `'raw_data' => 'array'`)
**Requirement**: INFRA-21

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Cast strips `\0` on write; read path returns an array identical to the previous `'array'` cast for NUL-free payloads
- [x] A real NUL-bearing seeded payload round-trips and **every** `Draw` accessor returns the same value it returned under the old cast (this is the assertion that licenses D3 — see the AD-001 tension note)
- [x] `nomeTimeCoracaoMesSorte` is present post-write, minus the NUL — the field is not dropped
- [x] Fixtures come from the real seeded corpus per the Fixture rule, including ≥1 of the 415 NUL-bearing files
- [x] Gate check passes: `php artisan test --testsuite=Unit`
- [x] Test count: ≥8 new tests pass

**Tests**: unit
**Gate**: quick
**Commit**: `fix(draws): strip NUL bytes from raw_data at the storage boundary`

---

### T3: Dialect audit — resolve engine-specific SQL

**What**: Audit the codebase for engine-specific constructs and fix or document each finding.
**Where**: `database/migrations/2025_08_24_190138_add_draw_date_to_draws_table.php` (modify), `.specs/features/infrastructure-cloud-postgres-backups/dialect-audit.md` (new)
**Depends on**: None
**Reuses**: existing migration
**Requirement**: INFRA-10

**Tools**: MCP: NONE · Skill: NONE

**Known findings to resolve (audit is NOT starting from zero):**

- `add_draw_date_to_draws_table` uses raw `DB::table('draws')` and `json_decode($draw->raw_data, true)` on a raw column value. On Postgres the driver returns the `json` column as a string, so `json_decode` still works — but this must be **proven by running it**, not reasoned about.
- The same migration calls `->change()` to add a NOT NULL constraint after backfill; verify against Postgres.

**Done when**:

- [x] `grep` sweep completed for `DB::`, `whereJsonContains`, `whereJsonPath`, `whereRaw`, `selectRaw`, `orderByRaw` across `app/`, `database/`, `routes/` — results recorded in the audit doc
- [x] Every finding is either replaced with a portable equivalent or documented as intentionally Postgres-specific with a reason
- [x] The audit doc lists what was searched, so a future reader can tell coverage from luck
- [x] Gate check passes: `vendor/bin/pint --dirty && php artisan test`

**Tests**: none (matrix: migrations = none; correctness is proven by T8 running the suite on Postgres)
**Gate**: build
**Commit**: `chore(db): audit and resolve engine-specific SQL ahead of Postgres cutover`

---

### T4: Repoint Sail to PostgreSQL

**What**: Replace the MySQL service in `docker-compose.yml` with Postgres, matching the major version Laravel Cloud provisions.
**Where**: `docker-compose.yml`
**Depends on**: None
**Reuses**: existing Sail service definitions, `sail` network/volume conventions
**Requirement**: INFRA-07

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `mysql` service replaced by `pgsql`; `sail-mysql` volume replaced by `sail-pgsql`
- [x] `laravel.test.depends_on` updated
- [x] Healthcheck uses `pg_isready`
- [x] Testing-database bootstrap script repointed to Sail's Postgres equivalent
- [x] `./vendor/bin/sail up -d` brings up a healthy Postgres container
- [x] Postgres major version recorded in the audit doc for parity tracking

**Tests**: none (matrix: docker-compose = none)
**Gate**: build
**Commit**: `chore(sail): replace MySQL service with PostgreSQL`

---

### T5: Make Postgres the default connection; retire SQLite and MySQL

**What**: Promote `pgsql` to the default connection and **remove** the `sqlite` and `mysql` connection blocks.
**Where**: `config/database.php`, `.env.example`
**Depends on**: T4
**Reuses**: existing `pgsql` connection block
**Requirement**: INFRA-07 (AD-008)

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `'default' => env('DB_CONNECTION', 'pgsql')`
- [x] `sqlite` and `mysql` connection arrays **deleted**, not commented out — a commented fallback is still an invitation (AD-008)
- [x] `.env.example` updated to Postgres credentials matching `docker-compose.yml`
- [x] `database/database.sqlite` removed if present, and the `post-create-project-cmd` composer hook that touches it updated

**Verifier note (2026-07-18)**: all four literal items above are satisfied, but retirement is incomplete in *effect* — Laravel 11+ merges the framework's own bundled `config/database.php`, so `sqlite`/`mysql`/`mariadb` stay reachable via `DB_CONNECTION` despite deletion here. Tracked as the still-open **INFRA-22**, not a failure of this task's own checklist.

**Tests**: none (matrix: config = none)
**Gate**: build
**Commit**: `chore(db): make PostgreSQL the default connection and retire SQLite/MySQL`

---

### T6: Repoint the test suite at PostgreSQL

**What**: Change `phpunit.xml` test env from SQLite in-memory to Postgres.
**Where**: `phpunit.xml`
**Depends on**: T5
**Reuses**: existing `<php>` env block
**Requirement**: INFRA-06

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `DB_CONNECTION=pgsql` and a dedicated test database configured; `DB_DATABASE=:memory:` removed
- [x] The existing suite runs against Postgres (failures are expected here and are T8's job to resolve — this task's gate is that the suite *executes*, not that it is green)
- [x] From this task onward, all gates require `sail up -d`

**Tests**: none (matrix: config = none)
**Gate**: build
**Commit**: `test: run the suite against PostgreSQL`

---

### T7: Create CI running the suite against PostgreSQL

**What**: A GitHub Actions workflow — **there is currently no CI in this repo at all**, so this is net-new, not a repoint.
**Where**: `.github/workflows/ci.yml` (new)
**Depends on**: T6
**Reuses**: `phpunit.xml` env from T6
**Requirement**: INFRA-06

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Workflow runs on push and PR
- [x] Postgres service container at the same major version as T4
- [x] Steps: checkout → PHP setup → `composer install` → `php artisan migrate --force` → `php artisan test`
- [x] `vendor/bin/pint --test` enforced as a lint gate
- [x] The workflow is observed passing on a real run — a workflow file that has never executed is not CI

**Resolved since validation.md (2026-07-18)**: at verification time this task was `❌ Unmet` (**Gap 1**) — the branch was unpushed and `gh run list` returned `[]`. Since then `feat/infrastructure-postgres-backups` was pushed and CI observed green multiple times (e.g. run `29651679467`, 2026-07-18T16:17:54Z; `gh run list --repo dberri/loterias_v2` shows further green runs on `chore/upgrade-laravel-filament` and `test/pest-migration-browser-tests` since, confirming the workflow itself is sound). Gap 1 is closed.

**Tests**: none (matrix: CI config = none)
**Gate**: build
**Commit**: `ci: add GitHub Actions workflow running the suite on PostgreSQL`

---

### T8: Make all migrations and the full suite green on clean Postgres

**What**: Run every migration against an empty Postgres DB and fix whatever breaks, until the pre-existing suite is fully green.
**Where**: `database/migrations/**` (modify as needed)
**Depends on**: T3, T7
**Reuses**: T3's audit findings
**Requirement**: INFRA-08

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `php artisan migrate:fresh` succeeds against an empty Postgres DB with zero dialect errors
- [x] The **entire pre-existing suite** passes on Postgres — baseline is the count from `seo-draw-page-generation`'s validation (76 tests); a lower count means tests were deleted, not fixed
- [x] Any migration change preserves the original schema intent — no column silently retyped to dodge an error
- [x] Gate check passes: `vendor/bin/pint --dirty && php artisan test`

**Tests**: none directly (matrix: migrations = none); **the gate is the entire existing suite**
**Gate**: build
**Commit**: `fix(db): resolve migration dialect errors on PostgreSQL`

---

### T9: Prove JSON round-trip and accessor parity on Postgres

**What**: Tests asserting `draws.raw_data` and `pages.blocks` round-trip through Postgres with byte-for-byte accessor equality, including NUL-bearing and zero-padded payloads.
**Where**: `tests/Feature/Database/JsonRoundTripTest.php` (new)
**Depends on**: T2, T8
**Reuses**: seeded corpus fixtures; `Draw` accessors; `Page` model
**Requirement**: INFRA-09, INFRA-21

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] A real `raw_data` payload written to and read from Postgres returns identical values from **every** `Draw` accessor
- [x] A NUL-bearing payload persists without error (INFRA-21 proven against the actual engine, not asserted in prose)
- [x] A **newly scraped** draw carrying a NUL persists — covers future writes, not just the backfill (spec AC P2.8)
- [x] A pre-2483 zero-padded payload (`"004"`) round-trips unchanged — the format shift is preserved, not normalized
- [x] `pages.blocks` round-trips with nested block structure intact
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥8 new tests pass

**Tests**: feature
**Gate**: full
**Commit**: `test(db): assert JSON round-trip and accessor parity on PostgreSQL`

---

### T10: Build the cutover backfill command with validation and rollback

**What**: An artisan command that backfills `draws` into Postgres and validates the result, refusing to proceed on mismatch.
**Where**: `app/Console/Commands/CutoverDraws.php` (new)
**Depends on**: T9
**Reuses**: `Draw` model, `NulSafeJson` cast from T2
**Requirement**: INFRA-11, INFRA-20

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Reads from the pre-cutover source connection and writes `draws` to Postgres
- [x] Validates **row-count parity AND deep comparison of a random sample of `raw_data`** — row counts alone cannot detect serialization corruption, which is the exact failure this migration risks
- [x] Deep comparison tolerates **only** the NUL stripping from T2 and nothing else; any other delta fails validation
- [x] On validation failure: exits non-zero with the specific mismatch, leaves the source snapshot untouched, and does not switch config (INFRA-20)
- [x] `--dry-run` reports what would move without writing
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥7 new tests pass, covering happy path, count mismatch, and payload corruption

**Tests**: feature
**Gate**: full
**Commit**: `feat(db): add validated draws cutover command with rollback safety`

---

### T11: Execute the production cutover 🔒 **operator-gated**

**What**: Run the real cutover against production data.
**Where**: production environment (no repo file)
**Depends on**: T10
**Requirement**: INFRA-11, INFRA-20

> 🔒 **An agent cannot complete this task.** It requires production credentials and an operator decision. A sub-agent must stop here and hand back.

**Done when**:

- [ ] Source DB snapshotted and the snapshot confirmed immutable
- [ ] Postgres provisioned; migrations run
- [ ] `app:cutover-draws` executed; validation passed
- [ ] Public + `/admin` read-path smoke checks pass post-switch
- [ ] Row count and sample-comparison output recorded as the evidence artifact
- [ ] Rollback path confirmed available (not exercised unless needed)

**Tests**: none — operator-verified with recorded evidence
**Gate**: manual

---

### T12: Create `ExportCorpus` job exporting `draws` to NDJSON

**What**: The queued job's first slice — stream `draws` to an NDJSON artifact on the configured disk.
**Where**: `app/Jobs/ExportCorpus.php` (new), `config/filesystems.php` (modify)
**Depends on**: T1
**Reuses**: `Draw` model (read-only), existing `s3` disk scaffold
**Requirement**: INFRA-12

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Writes `exports/{YYYY-MM-DD}/draws.ndjson`, one row per line
- [x] Streams rather than loading 2,600+ rows into memory at once
- [x] Export never mutates `Draw` — read-only path
- [x] NDJSON is valid line-by-line and reconstructs the original rows
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥5 new tests pass

**Tests**: feature
**Gate**: full
**Commit**: `feat(backup): export draws to NDJSON via ExportCorpus job`

---

### T13: Export `pages`, handling missing and empty tables

**What**: Add the `pages` export slice with the two independent-deployability edge cases.
**Where**: `app/Jobs/ExportCorpus.php` (modify)
**Depends on**: T12
**Reuses**: `Page` model
**Requirement**: INFRA-12, INFRA-18, INFRA-19

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Exports `pages.ndjson` alongside `draws.ndjson`
- [x] **`pages` table absent** → exports `draws` alone and **succeeds** (INFRA-18)
- [x] **`pages` empty** → produces a valid, well-formed, zero-row artifact with a truthful count of `0` — not a skipped file, so an empty backup stays distinguishable from a missing one (INFRA-19)
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥6 new tests pass, one per edge case above

**Tests**: feature
**Gate**: full
**Commit**: `feat(backup): export pages with absent- and empty-table handling`

---

### T14: Write and self-verify the export manifest

**What**: Emit `manifest.json` and validate it by **re-reading** the written artifacts.
**Where**: `app/Jobs/ExportCorpus.php` (modify)
**Depends on**: T13
**Requirement**: INFRA-12, INFRA-13

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Manifest records export timestamp, per-table row counts, per-artifact sha256, and app/schema version
- [x] After writing, the job **re-reads each artifact from the disk** and recomputes its checksum — "the write returned success" is not verification
- [x] Checksum mismatch → the export is marked **failed**, not merely logged
- [x] A test corrupts an artifact post-write and asserts the job detects it — the feature's single most dangerous failure is a silently corrupt backup, so it needs a test that manufactures one
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥6 new tests pass

**Tests**: feature
**Gate**: full
**Commit**: `feat(backup): add self-verifying export manifest with checksums`

---

### T15: Alert on export failure

**What**: Wire every export failure path to `AlertNotifier`.
**Where**: `app/Jobs/ExportCorpus.php` (modify)
**Depends on**: T1, T14
**Reuses**: `AlertNotifier` from T1
**Requirement**: INFRA-13, INFRA-14

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Checksum mismatch → alert
- [x] Storage unreachable → job fails **loudly** and alerts, rather than logging and exiting zero
- [x] Failed job lands in Laravel's `failed_jobs` table (AD-009 — no custom dead-letter table)
- [x] Repeated nightly failures produce **one** email, not one per night (exercises T1's dedup through the real caller)
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥5 new tests pass

**Tests**: feature
**Gate**: full
**Commit**: `feat(backup): alert on export failure via AlertNotifier`

---

### T16: Schedule the nightly export

**What**: Register `ExportCorpus` in the scheduler.
**Where**: `routes/console.php` (modify)
**Depends on**: T15
**Reuses**: Laravel scheduler
**Requirement**: INFRA-12

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Job scheduled nightly, off-peak
- [x] `withoutOverlapping` so a slow export cannot stack
- [x] A test asserts the schedule entry is registered with the expected cron expression
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥2 new tests pass

**Tests**: feature
**Gate**: full
**Commit**: `feat(backup): schedule nightly corpus export`

---

### T17: Configure retention lifecycle

**What**: Object-storage lifecycle rules — 35 daily, 12 monthly.
**Where**: `docs/infrastructure/backup-retention.md` (new) + bucket configuration
**Depends on**: T16
**Requirement**: INFRA-17

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Daily artifacts retained 35 days; monthly retained 12 months
- [x] Enforced by **bucket lifecycle policy, not application code** for the daily tier; the monthly tier is necessarily writer-side (see note) — both are exercised
- [x] Applied configuration documented so it is reproducible after an account change
- [x] Gate check passes: `vendor/bin/pint --dirty && php artisan test`

**Gap 3 resolved since validation.md (2026-07-18)**: at verification time `ExportCorpus.php` wrote only `exports/{Y-m-d}/`, so the monthly tier matched no real artifact. Since then `ExportCorpus::promoteToMonthly()` (`app/Jobs/ExportCorpus.php:132-159`) was added — it copies the export into a sibling `monthly/{Y-m}/` prefix (never nested under `exports/`, so the 35-day daily expiry cannot delete it), keyed on absence so a month whose first night failed still gets promoted from the next successful run, and self-verifies its own checksums. Fully covered by `tests/Feature/Jobs/ExportCorpusRetentionTest.php` (7 tests: promotion, sibling-prefix placement, no duplicate promotion within a month, one artifact per month, late promotion after a missed first night, checksum and byte parity against the source daily). AD-013 records the underlying decision (a bucket lifecycle rule cannot express "first export of the month" — promotion is necessarily writer-side). The bucket lifecycle policy itself has still never been applied to a real bucket (operator-gated, T20/T21).

**Tests**: none (matrix: infra config = none)
**Gate**: build
**Commit**: `docs(backup): document and apply artifact retention lifecycle`

---

### T18: Build the restore command

**What**: An artisan command reconstructing `draws` and `pages` from an export artifact into an empty database.
**Where**: `app/Console/Commands/RestoreCorpus.php` (new), `docs/infrastructure/restore-runbook.md` (new)
**Depends on**: T14
**Reuses**: NDJSON artifacts, manifest from T14
**Requirement**: INFRA-15

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Restores both tables from an artifact directory into an empty DB
- [x] Validates each artifact's checksum against the manifest **before** importing — restoring from a corrupt artifact is worse than refusing
- [x] Asserts row-count parity against the manifest after import
- [x] The runbook documents the `jq`-and-shell-loop fallback path, so the artifact stays restorable without this application's code (the reason NDJSON was chosen in D2)
- [x] Gate check passes: `php artisan test`
- [x] Test count: ≥6 new tests pass, including a corrupt-artifact refusal

**Gap 2 resolved since validation.md (2026-07-18)**: at verification time this checklist's literal items were all satisfied, but never captured the second half of INFRA-15's actual spec AC ("sampled `Published` pages render correctly" after restore, not just that rows reconstruct), and `RestoreCorpusTest.php` had no `assertOk()`/HTTP assertion. Since then `test('a previously published page still renders after restore', ...)` was added (`tests/Feature/Commands/RestoreCorpusTest.php:174-192`) — it publishes a page, confirms it renders, exports, empties the DB, confirms a 404, restores, and confirms `assertOk()` again. INFRA-15 is now fully covered, not just reconstruction.

**Tests**: feature
**Gate**: full
**Commit**: `feat(backup): add corpus restore command and runbook`

---

### T19: Execute the restore drill for real 🔒 **operator-gated**

**What**: Perform an actual restore into a scratch database and record the elapsed time.
**Where**: scratch environment (no repo file); result appended to `docs/infrastructure/restore-runbook.md`
**Depends on**: T18
**Requirement**: INFRA-16

> 🔒 **An agent cannot complete this task, and it must not be marked done by writing documentation.** INFRA-16 is satisfiable only by executing a restore. This is the task most likely to be skipped under delivery pressure, and skipping it converts the entire backup layer into false confidence.

**Done when**:

- [ ] A scratch database is dropped and restored from a real export artifact
- [ ] Row-count parity verified for both tables
- [ ] A sample of previously-`Published` draw pages **renders correctly** after restore
- [ ] **Elapsed time recorded** and confirmed within the 8h RTO — the recorded number is the deliverable
- [ ] Result appended to the runbook with the date executed

**Tests**: none — operator-verified with recorded evidence
**Gate**: manual

---

### T20: Declare the three Laravel Cloud process types 🔒 **operator-gated**

**What**: Deploy configuration for web, worker, and scheduler processes, plus migrations on deploy.
**Where**: Laravel Cloud deploy configuration (+ any committed config the platform reads)
**Depends on**: T11
**Requirement**: INFRA-01, INFRA-05

> 🔒 Requires Laravel Cloud account access.

**Done when**:

- [ ] **web** serves public pages and `/admin`; cold starts accepted
- [ ] **worker** runs `queue:work`
- [ ] **scheduler** runs `schedule:run` every minute and is **kept warm** — the deliberate exception to the cost-first posture, because a cold-starting scheduler misses ticks silently, with no error and no retry
- [ ] Deploy pipeline runs `php artisan migrate --force` against production Postgres
- [ ] A cold start on a web request **succeeds slowly** rather than erroring — a cold start that errors is a defect, not a trade-off

**Tests**: none — operator-verified
**Gate**: manual

---

### T21: Verify worker, scheduler, and publish gate in production 🔒 **operator-gated**

**What**: Prove the deployed environment actually works, rather than that the processes exist.
**Where**: deployed environment
**Depends on**: T20
**Requirement**: INFRA-02, INFRA-03, INFRA-04

> 🔒 Requires access to the deployed environment. This task is the spec's P1 Independent Test, executed.

**Done when**:

- [ ] A dispatched job is observed **consumed by the worker**
- [ ] A job that throws is observed landing in `failed_jobs`
- [ ] A scheduled command writing a timestamped log line is confirmed to have **fired unattended** — process liveness is not evidence that the scheduler runs anything (INFRA-03)
- [ ] A `Published` draw page returns **200** at the live URL
- [ ] A page at any other status returns **404** (DRAWPAGE-05 verified in the deployed environment, not only in tests)
- [ ] All five observations recorded as evidence artifacts

**Tests**: none — operator-verified with recorded evidence
**Gate**: manual

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5

Phase 1:  T1 ──→ T2 ──→ T3
Phase 2:  T4 ──→ T5 ──→ T6 ──→ T7 ──→ T8 ──→ T9
Phase 3:  T10 ──→ T11 🔒
Phase 4:  T12 ──→ T13 ──→ T14 ──→ T15 ──→ T16 ──→ T17
Phase 5:  T18 ──→ T19 🔒 ──→ T20 🔒 ──→ T21 🔒
```

Cross-phase dependency edges (dependencies point backward only):

```
T1 ──────────────────────────→ T12, T15
T2 ──→ T9,  T10
T3 ──→ T8
T7 ──→ T8 ──→ T9 ──→ T10 ──→ T11 ──→ T20 ──→ T21
T14 ─→ T18 ──→ T19
```

**Batch packing (~7 tasks/worker, whole phases only):**

| Batch | Phases | Tasks | Count |
| ----- | ------ | ----- | ----- |
| 1 | Phase 1 + Phase 2 | T1–T9 | 9 |
| 2 | Phase 3 (T10) + Phase 4 + T18 | T10, T12–T18 | 8 |
| — | Phase 5 remainder 🔒 | T11, T19, T20, T21 | deferred to operator |

Batch 1 is 9 (over the ~7 budget) because Phase 2 is a tight dependency chain — T5 cannot precede T4, T8 cannot precede T7 — and splitting it would cut mid-chain. Legitimate fat batch, not a smell.

Batch 2 absorbs T18 (restore command) because T18's only dependency is T14, which lands earlier in the same batch, and leaving it alone would spawn a one-task worker.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1 | 1 service | ✅ Granular |
| T2 | 1 cast + 1 model line | ✅ Granular (cohesive — the cast is inert unapplied) |
| T3 | 1 audit + 1 migration | ✅ Granular |
| T4 | 1 config file | ✅ Granular |
| T5 | 1 config file (+ env example) | ✅ Granular |
| T6 | 1 config file | ✅ Granular |
| T7 | 1 workflow file | ✅ Granular |
| T8 | migrations, bounded by "suite green" | ✅ Granular (single outcome) |
| T9 | 1 test file | ✅ Granular |
| T10 | 1 command | ✅ Granular |
| T11 | 1 operation | ✅ Granular |
| T12 | 1 job (draws slice) | ✅ Granular |
| T13 | 1 job (pages slice) | ✅ Granular |
| T14 | 1 job (manifest) | ✅ Granular |
| T15 | 1 job (alerting) | ✅ Granular |
| T16 | 1 schedule entry | ✅ Granular |
| T17 | 1 lifecycle policy | ✅ Granular |
| T18 | 1 command + runbook | ✅ Granular |
| T19 | 1 drill | ✅ Granular |
| T20 | 1 deploy config | ✅ Granular |
| T21 | 1 verification pass | ✅ Granular |

> T12–T15 deliberately slice one file across four tasks. Each adds an independently testable behavior (rows → edge cases → integrity → alerting), and each is separately revertable. This is cohesive slicing, not artificial splitting.

---

## Diagram-Definition Cross-Check

| Task | Depends On (task body) | Diagram Shows | Status |
| ---- | ---------------------- | ------------- | ------ |
| T1 | None | None | ✅ Match |
| T2 | None | None | ✅ Match |
| T3 | None | None | ✅ Match |
| T4 | None | None | ✅ Match |
| T5 | T4 | T4 → T5 | ✅ Match |
| T6 | T5 | T5 → T6 | ✅ Match |
| T7 | T6 | T6 → T7 | ✅ Match |
| T8 | T3, T7 | T3 → T8; T7 → T8 | ✅ Match |
| T9 | T2, T8 | T2 → T9; T8 → T9 | ✅ Match |
| T10 | T9 | T9 → T10 | ✅ Match |
| T11 | T10 | T10 → T11 | ✅ Match |
| T12 | T1 | T1 → T12 | ✅ Match |
| T13 | T12 | T12 → T13 | ✅ Match |
| T14 | T13 | T13 → T14 | ✅ Match |
| T15 | T1, T14 | T1 → T15; T14 → T15 | ✅ Match |
| T16 | T15 | T15 → T16 | ✅ Match |
| T17 | T16 | T16 → T17 | ✅ Match |
| T18 | T14 | T14 → T18 | ✅ Match |
| T19 | T18 | T18 → T19 | ✅ Match |
| T20 | T11 | T11 → T20 | ✅ Match |
| T21 | T20 | T20 → T21 | ✅ Match |

No task depends on a later phase. T12/T15 depend on T1 (Phase 1) and T18 on T14 (Phase 4) — both backward. ✅

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Service | unit | unit | ✅ OK |
| T2 | Cast + Model | unit | unit | ✅ OK |
| T3 | Migration | none | none | ✅ OK |
| T4 | docker-compose | none | none | ✅ OK |
| T5 | config | none | none | ✅ OK |
| T6 | config | none | none | ✅ OK |
| T7 | CI config | none | none | ✅ OK |
| T8 | Migration | none | none (gate = full suite) | ✅ OK |
| T9 | Test-only | feature | feature | ✅ OK |
| T10 | Console command | feature | feature | ✅ OK |
| T11 | Operational | none (operator-verified) | none | ✅ OK |
| T12 | Job | feature | feature | ✅ OK |
| T13 | Job | feature | feature | ✅ OK |
| T14 | Job | feature | feature | ✅ OK |
| T15 | Job | feature | feature | ✅ OK |
| T16 | Job schedule | feature | feature | ✅ OK |
| T17 | Infra config | none | none | ✅ OK |
| T18 | Console command | feature | feature | ✅ OK |
| T19 | Operational | none (operator-verified) | none | ✅ OK |
| T20 | Deploy config | none (operator-verified) | none | ✅ OK |
| T21 | Operational | none (operator-verified) | none | ✅ OK |

No `Tests: none` is justified by "tested in another task". Every `none` maps to a matrix layer that requires none. ✅

---

## Requirement Traceability

| Requirement ID | Task(s) | Status |
| -------------- | ------- | ------ |
| INFRA-01 | T20 | In Tasks |
| INFRA-02 | T21 | In Tasks |
| INFRA-03 | T21 | In Tasks |
| INFRA-04 | T21 | In Tasks |
| INFRA-05 | T20 | In Tasks |
| INFRA-06 | T6, T7 | In Tasks |
| INFRA-07 | T4, T5 | In Tasks |
| INFRA-08 | T8 | In Tasks |
| INFRA-09 | T9 | In Tasks |
| INFRA-10 | T3 | In Tasks |
| INFRA-11 | T10, T11 | In Tasks |
| INFRA-12 | T12, T13, T14 | In Tasks |
| INFRA-13 | T14, T15 | In Tasks |
| INFRA-14 | T15 | In Tasks |
| INFRA-15 | T18 | In Tasks |
| INFRA-16 | T19 | In Tasks |
| INFRA-17 | T17 | In Tasks |
| INFRA-18 | T13 | In Tasks |
| INFRA-19 | T13 | In Tasks |
| INFRA-20 | T10, T11 | In Tasks |
| INFRA-21 | T2, T9 | In Tasks |

**Coverage: 21 of 21 requirements mapped to tasks. 0 unmapped. ✅**
