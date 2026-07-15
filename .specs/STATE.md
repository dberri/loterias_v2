# STATE

## Decisions

### AD-001
- **Decision**: The app owns all factual data (drawn numbers, prizes, winner counts); the AI only ever writes prose that references facts it is given — it never emits a factual number as data.
- **Reason**: A results/lottery site that shows a wrong number is a trust- and SEO-killer; this is a hard guardrail, not a style preference.
- **Trade-off**: Every anchored fact must be resolved live from `Draw::raw_data` via `mutateData()` rather than trusted from the AI response, adding a validation/assembly layer the AI-only alternative wouldn't need.
- **Scope**: `seo-draw-page-generation` and every dependent feature that renders draw facts (additional-lotteries, player-tools).
- **Date**: 2026-07-11
- **Status**: active

### AD-002
- **Decision**: Draw pages use a hybrid block layout — a fixed spine of anchored blocks (`hero`, `results-grid`, `draw-details`, `related-links`) at guaranteed positions, with the AI freely selecting, ordering, and writing a subset of enrichment blocks between them.
- **Reason**: Guarantees SEO structure and cross-page consistency (spine) while still letting the AI vary angle/depth per draw (enrichment) to avoid cross-concurso boilerplate.
- **Trade-off**: The AI-eligible block set must be enumerated and schema-constrained (`enrichment_blocks[].type` enum); the AI cannot introduce a wholly new block type without a code change.
- **Scope**: `seo-draw-page-generation` and any future page type built on the same Fabricator block pattern.
- **Date**: 2026-07-11
- **Status**: active

### AD-003
- **Decision**: Draw pages are Fabricator `Page` rows (via an app-owned `App\Models\Page` subclass with `draw_id`/`batch_id`/`provider`/`status`/`generated_at` columns), not a separate model/table. `App\Models\DrawPage` and the `draw_pages` table are dropped.
- **Reason**: One page model means Fabricator's routing, rendering, and Filament admin editor all apply for free — fewer moving parts than maintaining a parallel content model.
- **Trade-off**: Existing `DrawPage` rows are discarded and must be regenerated (content format changes entirely); any code still referencing `DrawPage` must be repointed.
- **Scope**: `seo-draw-page-generation`; binding on any future feature that touches draw content persistence.
- **Date**: 2026-07-11
- **Status**: active

### AD-004
- **Decision**: The public URL scheme for an individual draw page is `/{game}/resultado/{concurso}` (e.g. `/mega-sena/resultado/2500`).
- **Reason**: Puts the high-intent "resultado" keyword in the path with a stable, unique identifier (concurso number) as the final segment.
- **Trade-off**: Requires verifying Fabricator can route a slash-containing slug; an explicit fallback route is the mitigation if not (see `seo-draw-page-generation` design.md, Assumptions).
- **Scope**: `seo-draw-page-generation`; any future lottery/game added under `additional-lotteries-stub.md` must follow this same scheme.
- **Date**: 2026-07-11
- **Status**: active

### AD-005
- **Decision**: LLM content generation goes through a provider-agnostic `BatchContentProvider` interface (`submitBatch`/`pollBatch`/`fetchResults`/`generateOne`), resolved via a `ContentProviderManager`; v1 ships only the OpenAI driver.
- **Reason**: Keeps pipeline/domain code (commands, jobs, `PageAssembler`) ignorant of which vendor produced a page, so Anthropic/Gemini drivers are drop-in additions rather than rewrites.
- **Trade-off**: v1 pays the cost of an abstraction layer before a second provider exists to justify it; accepted because the cost-comparison sub-project (`provider-cost-comparison-stub.md`) is already planned to need it.
- **Scope**: `seo-draw-page-generation` (interface + OpenAI driver); binding on `provider-cost-comparison` (implements the same interface for Anthropic/Gemini).
- **Date**: 2026-07-11
- **Status**: active

### AD-006
- **Decision**: Pages move through a publish gate `Generating → Generated → Published` (+ `Failed`); only `Published` pages are served publicly. A config flag `content.auto_publish` (default `false`) can skip the manual `Generated → Published` review step.
- **Reason**: While the prompt is being tuned, AI output needs human review before going live; the flag is the seam that lets a later automation project run unsupervised once output is trusted.
- **Trade-off**: v1 requires a manual Filament step to publish anything; accepted as correct default behavior, not a limitation to work around.
- **Scope**: `seo-draw-page-generation`; binding on `automation-and-scheduling-stub.md`, which builds on this exact flag.
- **Date**: 2026-07-11
- **Status**: active

### AD-007
- **Decision**: Automation runs as **two decoupled scheduled sweeps** — a daily per-game scrape sweep and a separate daily per-game generation sweep — plus a daily health check. A scrape never enqueues generation; the generation sweep picks up whatever `Draw::scopeWithoutPage()` returns. `scopeWithoutPage()` is the **sole** dedup mechanism (no checksums, no idempotency keys, no duplicate-detection table).
- **Reason**: Makes the pipeline self-healing — the next scheduled run *is* the retry — which structurally removes the need for retry policies, backoff with jitter, dead-letter tables, and per-draw generate jobs. It also preserves the Batch API economics (one batch per game per day, not one batch per draw).
- **Trade-off**: A new draw can take up to ~24h longer to become a page than an event-driven pipeline would allow. Accepted: these pages compete on existing-draw queries, not breaking news, so freshness has no measurable SEO value here.
- **Scope**: `automation-and-scheduling`; binding on `additional-lotteries` (sweeps iterate `GamesEnum`, so a new game requires no scheduler change).
- **Date**: 2026-07-13
- **Status**: active

### AD-008
- **Decision**: PostgreSQL is the canonical database engine in **every** environment — local (Sail), CI, and production. SQLite and MySQL are retired and removed from config, not left as fallbacks.
- **Reason**: A dialect bug can only be caught before production if every environment runs the same engine. `draws.raw_data` and `pages.blocks` are JSON columns central to the whole app; a silent serialization difference between engines would corrupt every `Draw` accessor at once.
- **Trade-off**: Loses the zero-setup local SQLite path — a developer with broken Docker cannot trivially run the app. Accepted deliberately: an optional parity is not parity, and leaving SQLite configured guarantees someone eventually ships a dialect bug.
- **Scope**: `infrastructure-cloud-postgres-backups`; binding on every feature that writes a migration or a query.
- **Date**: 2026-07-13
- **Status**: active

### AD-009
- **Decision**: The project's entire observability surface is: Laravel's built-in `failed_jobs` table, the application log, one de-duplicated alert email, and one Filament dashboard widget. Explicitly rejected: metrics exporters, Grafana/Prometheus, APM, `/health` endpoints, worker heartbeat tables, circuit breakers, named queues with concurrency caps, and custom dead-letter tables.
- **Reason**: This is a solo-operated, low-traffic site generating a handful of pages per week. Every piece of ops machinery is a moving part that can itself break, monitoring a system that cannot meaningfully be overloaded.
- **Trade-off**: No historical metrics or trend analysis; diagnosing a subtle degradation means reading logs. Accepted — at this volume, the alternative costs more to maintain than the failures it would catch.
- **Scope**: `automation-and-scheduling` and `infrastructure-cloud-postgres-backups`. Binding: infrastructure work does not get to reopen this.
- **Date**: 2026-07-13
- **Status**: active

### AD-010
- **Decision**: Every LLM provider driver implements **full four-method parity** (`submitBatch`/`pollBatch`/`fetchResults`/`generateOne`) from day one, and all drivers are held to a **single shared contract test suite** with identical fixtures. Provider selection is a global `config('content.default')` plus an optional per-run `--provider=` override.
- **Reason**: A driver implementing only `generateOne` cannot be swapped in for production page generation, which makes it useless for the cost comparison the abstraction exists to enable. Per-driver test files drift toward asserting what each driver *does* rather than what the contract *requires*; one shared suite makes non-substitutability a test failure.
- **Trade-off**: Higher up-front cost per driver, and a vendor without a native batch primitive must have batching emulated inside its driver rather than bending the interface.
- **Scope**: `provider-drivers`; binding on `provider-cost-comparison`. Extends AD-005.
- **Date**: 2026-07-13
- **Status**: active

## Handoff

- **Features with Specify + Design complete**: `seo-draw-page-generation`, `provider-drivers`, `automation-and-scheduling`, `infrastructure-cloud-postgres-backups`. **`seo-draw-page-generation` now also has `tasks.md` (26 tasks, 6 phases, 16/16 requirements mapped, all three pre-approval gates passing).**
- **Phase / Task**: `seo-draw-page-generation` Execute is **functionally complete (T1–T26), re-verified ⚠️ Issues on 2026-07-15 (second pass)** — see `.specs/features/seo-draw-page-generation/validation.md`. All 16 requirements now pass with spec-precise tests (76 tests, 0 failed); the P1 MVP's Independent Test scenario runs end-to-end; the `expired`/`cancelled` batch bug is fixed. **Not yet committed**: T18–T26 exist only as uncommitted working-tree changes — must be split into atomic per-task commits before this is "done" (violates the skill's one-commit-per-task rule). Three Minor cleanups also outstanding: `ContentCreator.php` (T22) still exists as unreferenced dead code referencing a deleted `DrawPage` class; `DrawPageLayout.php` (T24) was never created so the admin Layout dropdown won't recognize `'draw-page'`; a handful of untraceable `Draw` accessor aliases (`game`, `numbers`, `accumulated`, etc.) were added without a task/test trail. Next: commit the diff per-task, apply the three Minor fixes, re-run `pint --dirty && php artisan test`, then this feature can move to `infrastructure-cloud-postgres-backups` per the original sequencing plan.

### Seed-data audit (2026-07-13) — findings that changed the task breakdown

`database/seeders/lotteries/megasena/draws/` holds **2,608 real Caixa payloads** (concursos 1–2608, latest 05/07/2023 — the corpus is ~3 years stale). Auditing them against `Draw`'s accessors found:

- **`listaDezenas` format changes mid-corpus.** Concursos 1–2482 are 3-digit zero-padded (`"004"`); 2483+ (from 21/05/2022) are 2-digit (`"20"`). `getDrawnNumbersAttribute()` returns the raw array, so pages for 95% of the corpus would render `004 005 030`. **Fixturing from the newest draw would hide this entirely.** → seo T5.
- **`dataProximoConcurso` is `""`, not null, on 281 draws.** `?? null` does not catch an empty string, so `next_draw_date` returns `""`. → seo T5.
- **`numeroConcursoAnterior` and `valorEstimadoProximoConcurso`** are present in all 2,608 payloads but have **no accessor**, though `related-links` and `draw-details` both need them. → seo T5, T17, T18.
- **Only Mega-Sena is seeded** — zero Lotofácil/Quina payloads. Lotofácil has 15 dezenas / 5 faixas and Quina 5 / 4, vs Mega's 6 / 3, so anchored blocks are untested exactly where they'd break. → seo T6 captures real payloads for both via `app:scrape-draw`.
- **Schema is otherwise stable**: all 8 accessor-required keys present in all 2,608 payloads; 591 draws with faixa-1 winners, 2,017 accumulated, 568 with winner cities — both branches have abundant real fixtures.
- **⚠️ Cross-feature blocker: 415 payloads contain NUL bytes (` `) in `nomeTimeCoracaoMesSorte`.** Harmless on MySQL/SQLite; **PostgreSQL rejects ` ` in `jsonb`**, so ~16% of `draws` rows would fail to insert during the cutover. Recorded as **INFRA-21**. Deliberately **not** fixed in seo — `raw_data` must stay byte-faithful to Caixa (AD-001), so the fix belongs at the storage boundary and must cover future scrapes, not just the backfill.
- **Completed this session (2026-07-13)**:
  - `provider-drivers/{spec,design}.md` — transcribed; the source doc's "extract OpenAI into a driver" work item was **dropped as redundant** (seo's design already makes `OpenAiContentProvider` a first-class driver) and replaced with PROVIDER-04: OpenAI must pass the shared contract suite.
  - `automation-and-scheduling/{context,spec,design}.md` — **rebuilt from scratch, not transcribed.** The 2026-07-11 source doc contradicted AD-003 (invented "Content records"), AD-006 (invented a `PendingReview` state), the batch architecture (per-draw `GenerateDrawPage` job), and the real CLI signatures; it also specified a colliding custom `failed_jobs` migration and a large ops surface. Full defect table in that feature's `context.md`.
  - `infrastructure-cloud-postgres-backups/{spec,design}.md` — transcribed; ops surface trimmed per AD-009, and quarterly restore drills replaced with **one executed drill** (a skipped recurring drill manufactures false confidence).
  - `STATE.md` — AD-007 through AD-010.
- **In-progress**: none
- **Next step**: The critical-path question is **sequencing**, not more specs. `seo-draw-page-generation` is the hard dependency of everything else and has no tasks and no code — it must be broken into tasks and executed first. Recommended order:
  1. **`seo-draw-page-generation`** — Tasks → Execute. Everything else is blocked on the symbols it creates (`Page`, `PageStatus`, `PageAssembler`, `BatchContentProvider`, `config/content.php`).
  2. **`infrastructure-cloud-postgres-backups`** — the Postgres cutover is cheapest **right now**, because per AD-003 only `draws` exists to migrate. That window closes as soon as page generation runs at scale.
  3. **`automation-and-scheduling`** — needs the scheduler/worker from (2).
  4. **`provider-drivers`** — independent of (2) and (3); can run in parallel once (1) lands.
- **Blockers / known traps**:
  - **Circular dependency**: `automation-and-scheduling` builds `AlertNotifier`, which `infrastructure`'s export-failure alerting reuses; `infrastructure` builds the scheduler/worker that `automation`'s scheduled entries need. **Break the cycle by building `AlertNotifier` first as a shared prerequisite** — it is a small service with no infrastructure dependency. Decide this explicitly at Tasks time; do not discover it mid-execution.
  - **`provider-drivers` has a blocking verification task**: Anthropic's and Gemini's batch + structured-output API shapes are deliberately **not asserted anywhere** in its spec or design. They must be verified against official docs before any driver code is written. The design ships an intentionally empty "Verified Vendor API Shapes" appendix for this.
  - **`infrastructure` has a blocking dialect audit** (INFRA-10) before cutover. Expected finding is near-zero (Eloquent-only convention), but expected ≠ verified.
- **Still un-designed (stubs only)**: `additional-lotteries`, `player-tools`, `provider-cost-comparison` (the last is deliberately deferred). These need genuine brainstorming, not transcription — they carry real open questions (which games first? how do two-draw games like Dupla Sena render? client- vs server-side tools?).
- **Uncommitted files**: all of `.specs/` (untracked)
- **Branch**: main
