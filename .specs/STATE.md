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
- **Amendment (2026-07-18, verified)**: AD-008 is **only partially enforced in practice**. Laravel 11+ merges the framework's bundled `config/database.php`, so deleting the `sqlite`/`mysql`/`mariadb` blocks from the app config does NOT remove them — `config('database.connections')` still returns `sqlite, mysql, mariadb, pgsql, sqlsrv`. The default is correctly `pgsql` and every environment points at Postgres, but `DB_CONNECTION=sqlite` still yields a working SQLite connection — exactly the fallback AD-008 exists to eliminate. Same root cause as the 112 PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecations now flagged across the suite. Tracked as **INFRA-22**; open.
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

### AD-011
- **Decision**: `App\Services\AlertNotifier` is built **once, by `infrastructure-cloud-postgres-backups` (T1)**, as the shared prerequisite that breaks the circular dependency between that feature and `automation-and-scheduling`. `automation-and-scheduling` **consumes** it (AUTO-11) and MUST NOT re-implement it.
- **Reason**: `infrastructure` needs `AlertNotifier` for export-failure alerts, while `automation` needs the scheduler/worker that `infrastructure` builds — a naive task ordering deadlocks. `AlertNotifier` is small and has no infrastructure dependency, so it is the natural seam to cut. Infrastructure is sequenced first, so it carries it.
- **Trade-off**: `infrastructure` builds a service whose primary consumer is another feature, so its de-dup semantics must be designed for both callers rather than just export failures.
- **Scope**: `infrastructure-cloud-postgres-backups` (builds); binding on `automation-and-scheduling` (consumes, does not rebuild). Implements the mitigation recorded in that feature's design Risks table.
- **Date**: 2026-07-18
- **Status**: active

### AD-012
- **Decision**: NUL bytes (`\0`) in `draws.raw_data` are stripped **on write, at the storage boundary**, via a custom Eloquent cast (`App\Casts\NulSafeJson`) — covering both the Postgres backfill and all future scrapes. Retyping `raw_data` from `json` to `text` to sidestep PostgreSQL's validation was considered and **rejected**.
- **Reason**: 415 of the 2,608 real seeded Mega-Sena payloads carry a NUL inside `nomeTimeCoracaoMesSorte` (confirmed by audit, re-verified 2026-07-18). **Corrected 2026-07-18 — the original reason named the wrong failure mode.** `draws.raw_data` is `$table->json()` → PostgreSQL **`json`**, not `jsonb`. Measured directly on PG 17.10: `json` **accepts** \0 on insert; only `jsonb` rejects it. So the cutover would NOT have failed loudly on 415 rows — they would have inserted cleanly and become **permanently unreadable at SQL level** (`->>` raises SQLSTATE 22P05 `unsupported Unicode escape sequence`, and a later `ALTER TYPE ... jsonb` is consequently blocked). The cast is therefore **more** load-bearing than first believed: it is the only thing standing between the corpus and silent latent corruption, not merely a convenience that avoids a noisy insert error. The NUL is junk padding in a field no `Draw` accessor reads and no anchored fact derives from, so stripping it alters zero facts.
- **Trade-off**: A narrow, deliberate exception to AD-001's byte-faithfulness rule. It is licensed **only** by the accessor-parity test in T2/T9 — if that test is ever removed, the exception is no longer justified.
- **Lesson**: A spec that asserts engine behaviour (“Postgres rejects X”) must name the exact column type it assumes. `json` and `jsonb` differ precisely here, and picking the wrong one inverts the failure mode from loud to silent.
- **Scope**: `infrastructure-cloud-postgres-backups` (INFRA-21); binding on any future feature that writes `raw_data`. Narrows AD-001 at the storage boundary only — the domain layer still treats `raw_data` as the fact source of truth.
- **Date**: 2026-07-18
- **Status**: active

### AD-013
- **Decision**: Backup retention is split by mechanism, not held to one. The **daily 35-day tier** is an object-storage lifecycle rule. The **monthly 12-month tier** is produced by `ExportCorpus` copying an export into a `monthly/{YYYY-MM}/` prefix when that month has none — application code, deliberately. `monthly/` is a **sibling** of `exports/`, never nested inside it.
- **Reason**: The original INFRA-17 required retention be "enforced by lifecycle rules, not application code". For the monthly tier that is not a preference but an impossibility: lifecycle rules match on prefix, tag and age, and cannot express "keep the first export of each month". Selecting a monthly artifact is inherently an act of the process that writes it. Left as specified, the monthly rule matched nothing and effective retention was silently 35 days for everything — the 12-month tier existed only on paper.
- **Trade-off**: Reintroduces application code into a durability path the design wanted to keep declarative, so a bug in the writer can now cost the long-term tier (mitigated: promotion is keyed on *absence* of the month's artifact, so the next successful run retries it, per AD-007's self-healing posture). The sibling-prefix requirement is load-bearing and easy to undo by accident — nesting `monthly/` under `exports/` would let the 35-day expiry silently delete the 12-month backups.
- **Scope**: `infrastructure-cloud-postgres-backups` (INFRA-17). Amends the spec's AC P3.6 and the design's Retention component.
- **Lesson**: "Enforce this with configuration, not code" is an architectural preference that can quietly encode an impossibility. Check that the configuration primitive can actually express the rule before making it a requirement — otherwise the requirement is satisfied by a rule that matches nothing, which looks identical to success.
- **Date**: 2026-07-18
- **Status**: active

### AD-014
- **Decision**: Structured data (JSON-LD) is emitted **inline in the page body**, never pushed to the base layout's `head` stack. Binding on every current and future page type.
- **Reason**: `@push('head')` in `draw-page.blade.php` silently emitted **nothing** on CI — zero script tags, the substring absent from the response — while passing on PHP 8.3, 8.4 and 8.5 locally, with a cleared view cache and with CI's environment overlaid. Diagnostics proved the data was correct at render time (`$page->draw` resolved; `draw_date` and `game_name` populated), so the push content was lost across the component boundary rather than never generated. Google reads JSON-LD anywhere in the document, so the stack bought nothing and cost the entire SEO payload wherever it misbehaves.
- **Trade-off**: Structured data now sits in `<body>` rather than `<head>`, which reads as less conventional to a human skimming the source. Accepted: convention is worth nothing against markup that vanishes silently. The root cause of the stack loss is **still unknown** — this avoids the mechanism rather than fixing it, so if `@push`/`@stack` is ever reintroduced across a component boundary, this class of failure returns. As of 2026-07-18 no app view uses `@push`, `@prepend` or `@stack` (verified by grep).
- **Scope**: `seo-draw-page-generation` and any future page type built on the Fabricator block pattern.
- **Lesson**: The failure mode that matters here is **silence**. The page returned 200 and rendered correctly while the payload it existed to emit was absent — nothing observable distinguished working from broken. Markup whose only job is to be machine-read needs a test that asserts it is *present*, because no human will notice its absence.
- **Date**: 2026-07-18
- **Status**: active

### AD-015
- **Decision**: `openai-php/laravel` moves from `0.15.0` to `^0.20.0` as part of the Laravel 13 bump (T12), not as a separately-scheduled bump.
- **Reason**: `framework-upgrade-laravel-13-filament-5`'s spec.md Non-Goals table listed this bump as "Independent of this upgrade; unconstrained by Laravel 13. Separate bump" — that reasoning was disproven during T12. `openai-php/laravel` 0.15.0 requires `laravel/framework ^11.29|^12.12` with no 0.15.x release supporting Laravel 13; `^0.20.0` is the first line to declare `^13.0` support (confirmed via installed package metadata: `laravel/framework ^11.29|^12.12|^13.0`). There is no way to land `laravel/framework ^13` without it — it was never optional.
- **Trade-off**: Pulls in an OpenAI SDK bump (`openai-php/client` tracks in lockstep) as a forced side effect of a framework bump, rather than on its own review cycle. Also forced dev-only bumps of `laravel/boost`, `laravel/tinker`, `nunomaduro/collision` (zero app usage, Composer-resolved).
- **Lesson**: A spec's "unconstrained by X" claim about a transitive dependency is a testable assertion, not a given — check the dependency's own constraint declaration before writing it into a Non-Goals/Out-of-Scope table, the same way AD-013 learned to check whether a configuration primitive could express a stated rule.
- **Scope**: `framework-upgrade-laravel-13-filament-5` (T12); binding on any future feature that bumps `laravel/framework` while `openai-php/laravel` stays below `^0.20.0`.
- **Date**: 2026-07-18
- **Status**: active

### AD-016
- **Decision**: Pest is the test-runner standard for this codebase going forward — every test file uses Pest function syntax, no class-based `Tests\TestCase` subclasses remain under `tests/`. A real-browser `Browser` testsuite (Pest 4 + `pestphp/pest-plugin-browser` + Playwright) is the **required guard for any change to a public-facing template** (Fabricator layouts/page blocks): such changes must be accompanied by a browser test proving the change is actually visible on a real rendered page, not just asserted below the HTTP boundary. The Browser suite is **local-only by design** — it does not run in CI.
- **Reason**: The suite (198 tests, 854 assertions, entirely PHPUnit-class-based before this feature) sat entirely below the HTTP boundary — models, jobs, Livewire components in isolation. AD-014 is the precedent this feature exists to answer: draw-page JSON-LD was silently absent in a clean environment while all 198 tests stayed green, and the defect shipped to `main`. Writing this feature's own browser tests surfaced a **second, independent instance of the same class of gap**: `App\Models\User` didn't implement Filament's `FilamentUser` contract, so Filament's panel `Authenticate` middleware 403'd every authenticated admin user outside `APP_ENV=local` — invisible to the existing Filament coverage (`PageResourceTest`) because it drives Livewire components directly and never passes through the panel's real HTTP middleware stack. A test one layer below the real boundary missed it a second time; only actually driving `/admin/login` through a real browser found it.
- **Trade-off**: Browser tests are slow relative to the rest of the suite (Playwright cold-start, real HTTP/DB round-trips) and need a one-time local Playwright install; CI does not run them (see PEST-F1 below), which is an accepted, explicit gap, not an oversight — a browser test that never runs anywhere is not free even if unmeasured, and a test that never runs in CI is not protecting a PR (lesson L-004 from this feature's own tasks.md). Also **discovered empirically**: the Filament page-builder admin editor renders every configured block's full form schema on load, so editing a page seeded with all 10 implemented blocks (`tests/Browser/Fixtures/DrawPageFixture.php`) takes on the order of **three minutes** per visit versus ~1.5s for a blocks-empty page. `tests/Browser/AdminEditsPageTest.php` therefore seeds its own minimal page rather than reusing the shared fixture; `DrawPageFixture` remains correct and necessary for `tests/Browser/DrawPageRendersBlocksTest.php`, where the full block set is exactly what's under test.
- **Scope**: `pest-migration-browser-tests`; binding on any future feature that adds or edits a Fabricator layout or page block — it should add or extend a `tests/Browser/*Test.php` case, not rely on Livewire/HTTP-layer tests alone.
- **Lesson**: "One layer below the real thing" is not a fixed distance — AD-014's defect lived at the Blade/stack boundary, this feature's `FilamentUser` gap lived at the HTTP-middleware boundary, one layer further out again. The generalizable guard is PEST-08/PEST-11 (assert every configured block's content is actually visible, assert zero JS console errors) plus PEST-10 (drive the real auth boundary, never `actingAs()`) — not a single fixed assertion.
- **Date**: 2026-07-19
- **Status**: active

## Handoff

- **Features with Specify + Design complete**: `seo-draw-page-generation` (done), `infrastructure-cloud-postgres-backups` (code scope done), `provider-drivers`, `automation-and-scheduling`, `framework-upgrade-laravel-13-filament-5` (**done, Verifier PASS**).
- **`feat/infrastructure-postgres-backups`** → **PR #1** (https://github.com/dberri/loterias_v2/pull/1). **CI green: 178 passed.** Not merged.
- **`chore/upgrade-laravel-filament`** (this session): all 15 tasks (T1-T15, 3 phases) complete and committed; Verifier PASS with 0 ranked gaps. No PR opened yet. Working tree clean over `app/`. Not merged, not based on the infra branch (forked from `main`/an earlier point — confirm base before opening a PR, since infra's PR #1 is also unmerged).
- **`test/pest-migration-browser-tests`** (this session): all 15 tasks (T1-T15, 5 phases) complete and committed. Pushed to origin; **CI observed green** (https://github.com/dberri/loterias_v2/actions/runs/29685939957 — "Run test suite" step ran `php artisan test --testsuite=Unit,Feature`). **Feature-level Verifier PASS**, one gap found and closed. No PR opened yet.

### `framework-upgrade-laravel-13-filament-5` — COMPLETE, Verifier PASS

Filament 3→4→5, Livewire 4, Laravel 12→13, PHP 8.5 Sail runtime, in three phase-batch sub-agents plus an independent Verifier (author ≠ verifier). Suite: 198 tests, 0 failing throughout (tasks.md's stated 37→38 file counts were stale; real baseline was 36 files/198 tests the whole time — never reduced). Verifier's discrimination sensor: 4 mutations, 3 killed, 1 survived (`PageResource`'s `visit`-action URL generation has no dedicated test — optional follow-up, not a regression). Full report: `.specs/features/framework-upgrade-laravel-13-filament-5/validation.md`.

**AD-015 recorded**: spec.md's Non-Goals table wrongly called the `openai-php/laravel` 0.15→0.20 bump "independent"/"unconstrained by Laravel 13" — it was actually forced (0.15.0 has no release supporting `laravel/framework ^13`). Verified independently by the Verifier against the installed package's own composer constraints.

**One out-of-scope defect surfaced, not fixed (pre-existing, predates this feature's diff range)**: public draw pages never call `FilamentFabricator::getStyles()`/register styles, so `draw-page.blade.php` ships with no `<link rel="stylesheet">` at all. Confirmed via `git show` that this predates T8-T11 (which touched zero `app/Filament/**` files). Candidate for a `seo-draw-page-generation` follow-up bug.

### `pest-migration-browser-tests` — COMPLETE, Verifier PASS

Phase 1-2 (T1-T7, prior session): PHPUnit 11→12, Pest 4 + drift install, full conversion of all 37 original test files to Pest function syntax, class-based `Tests\TestCase` retired. Phase 3-5 (T8-T15, this session): `pestphp/pest-plugin-browser` + Playwright installed, a `Browser` testsuite added and excluded from CI, and two real-browser flows landed — the admin-edit → public-render loop (`tests/Browser/AdminEditsPageTest.php`) and all-blocks-render (`tests/Browser/DrawPageRendersBlocksTest.php`, one test per block so a failure names exactly which block broke).

**Feature-level Verifier ran and returned PASS** (fresh sub-agent, author ≠ verifier; independently re-derived every AC from spec.md and re-ran every gate itself rather than trusting tasks.md's checked boxes). One non-blocking spec-precision gap was found (PEST-07/PEST-08's literal "response SHALL be 200" wording had no explicit `assertStatus(200)` — content assertions covered the same failure mode in practice but not to the letter) and was closed immediately after: `pest-plugin-browser` has no `assertStatus()`/`assertOk()`, so an explicit Navigation-Timing-API status check (`assertScript('performance.getEntriesByType("navigation")[0].responseStatus', 200)`) was added to every public-page visit, verified to genuinely discriminate against a real 404 before rollout. Full report: `.specs/features/pest-migration-browser-tests/validation.md`.

**Baseline, updated**: `php artisan test` → **212 passed, 886 assertions** (198 passed / 854 assertions Unit+Feature — unchanged from the Phase 1-2 baseline — plus 14 passed / 32 assertions across the new Browser suite: 1 smoke, 2 admin-edit, 11 block-render). `php artisan test --testsuite=Unit,Feature` (what CI runs) is unaffected by the Browser suite's existence.

**Empirically verified, not assumed**:
- PEST-09 (re-runnable, no manual DB cleanup): Browser suite run twice consecutively, both green.
- PEST-12 (failure screenshots, given open upstream pest#1543): a deliberately broken assertion produced a real PNG in `tests/Browser/Screenshots/` named for the failing test; does not reproduce the upstream bug on `pest-plugin-browser` v4.3.1 here. Full note: `.specs/features/pest-migration-browser-tests/validation.md`.
- PEST-08's discrimination check: emptied `hero-section.blade.php`, only the `hero-section` block test went red (the other 10 stayed green), confirming the tests actually discriminate; file restored, verified zero diff.
- T13 (CI excludes Browser): real CI run observed green after push — https://github.com/dberri/loterias_v2/actions/runs/29685939957.

**One genuine, previously-invisible defect found and fixed while writing T10** (recorded as part of AD-016): `App\Models\User` didn't implement Filament's `FilamentUser` contract, so the panel's `Authenticate` middleware 403'd every authenticated user outside `APP_ENV=local`. Fixed by implementing `canAccessPanel(): true` — the standard minimal fix Filament's own interface doc recommends. Invisible to `PageResourceTest` because that test drives Livewire components directly, never through the panel's real HTTP middleware.

**Open follow-ups, logged not fixed (all pre-existing or explicitly deferred by user decision)**:
- **PEST-F1**: browser tests do not run in CI (too slow for now, explicit user decision) — a real gap, not silently dropped.
- **PEST-F2**: `draw-page.blade.php` never calls `FilamentFabricator::getStyles()`, so public draw pages ship with no stylesheet at all — pre-existing (predates this feature), gets its own spec.
- **PEST-F3**: 4 page blocks (`breadcrumb`, `comparison-table`, `simulation`, `timeline`) are registered/selectable in the admin but are unimplemented `//` stub templates — excluded from the block-rendering test with a named reason, not silently dropped.

Full task-by-task detail: `.specs/features/pest-migration-browser-tests/tasks.md`. All 15 tasks complete, Verifier PASS, gap closed. Not merged; no PR opened yet.

### `infrastructure-cloud-postgres-backups` — code scope COMPLETE, operator scope OPEN

Executed T1–T10 and T12–T18 (17 of 21 tasks) as two sequential batches plus an independent verification pass. Test suite **77 → 157**, no deletions. Verifier injected 12 behaviour-level mutations, all 12 killed; report in that feature's `validation.md`.

**Verifier's original verdict (2026-07-18) was ⚠️ Issues, not a clean PASS** — 3 Major gaps (CI workflow never executed; no post-restore render assertion for INFRA-15; monthly retention tier unreachable for INFRA-17) and 4 Minor gaps (migration backfill had no repeatable test; schedule test asserted a literal cron string instead of the spec's actual property; `Scraper`'s unparseable-date branch was untested; `NulSafeJson`'s docblock still stated the pre-AD-012 failure mode). **All 7 gaps have since been resolved** by follow-up work (confirmed by re-reading the current code and tests directly, not assumed from the stale report): CI observed green multiple times since (e.g. run `29651679467`); `RestoreCorpusTest.php` now has `test('a previously published page still renders after restore', ...)`; `ExportCorpus::promoteToMonthly()` now exists with 7 dedicated tests in `ExportCorpusRetentionTest.php`; `DrawDateBackfillMigrationTest.php` now seeds real data and asserts the backfill; `ExportCorpusScheduleTest.php` now asserts the off-peak *property* rather than a literal expression; `ScraperTest.php` now covers the unparseable/missing-date branches; `NulSafeJson`'s docblock correctly states the failure is silent, not loud. `tasks.md` and `spec.md`'s requirement traceability table have been updated to reflect this (2026-07-19).

**Deliberately NOT done — needs Laravel Cloud credentials, and none is satisfiable by documentation:**
- **T11 production cutover** — ⏳ **time-sensitive.** Per AD-003 only `draws` needs migrating today; that stops being true once page generation runs at scale, and the migration gets more expensive from then on. This is the one deferred task with a real clock.
- **T19 restore drill** — INFRA-16 closes only by *executing* a restore and recording elapsed time.
- **T20/T21** Laravel Cloud deploy + in-production verification.

**Consequence to state plainly**: export and restore code is tested and green, but **no backup has ever been taken from production and no restore has ever been performed.**

### Findings that changed the specs (all measured, not assumed)

1. **AD-012 corrected.** `raw_data` is a `json` column, not `jsonb`. Postgres `json` **accepts** NUL; only `jsonb` rejects it. The predicted loud insert failure was actually **silent latent corruption** — rows insert clean, then `->>` raises SQLSTATE 22P05 and `ALTER TYPE ... jsonb` is blocked. Makes `NulSafeJson` more load-bearing than the spec that justified it.
2. **AD-013 added.** INFRA-17's "enforced by lifecycle rules, not application code" was **unsatisfiable** for the monthly tier — S3 cannot express "keep the first export of each month". Monthly retention was silently 35 days. Now written by `ExportCorpus` into a **sibling** `monthly/` prefix (nesting it under `exports/` would let the 35-day rule delete the 12-month tier).
3. **AD-014 added.** Draw-page JSON-LD was **absent in a clean environment** — a production SEO defect live in `main` since seo was marked verified. See AD-014.

### Open items

- **INFRA-22** (open, unmapped): AD-008 only partially enforced. Laravel 11+ merges the framework's bundled `config/database.php`, so `sqlite`/`mysql`/`mariadb` stay reachable despite deletion and `DB_CONNECTION=sqlite` still yields a working connection. Same root cause as the PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecations in test output.
- **AD-014's root cause is unknown** — the fix avoids `@push`/`@stack` rather than explaining it.
- **PEST-F1** (open, `pest-migration-browser-tests`): browser tests do not run in CI — local-only by explicit user decision, tracked as an accepted, non-silent gap.
- **PEST-F2** (open, `pest-migration-browser-tests`): `draw-page.blade.php` never registers Fabricator styles, so public draw pages ship with no stylesheet. Pre-existing; gets its own spec.
- **PEST-F3** (open, `pest-migration-browser-tests`): 4 registered page blocks (`breadcrumb`, `comparison-table`, `simulation`, `timeline`) are unimplemented `//` stubs, selectable in the admin with no warning that they render nothing.
- Migration backfill, Scraper fallback branch and the schedule assertion were all closed post-verification.

### Next step

Three independent branches now sit ready, unmerged: `feat/infrastructure-postgres-backups` (PR #1, CI green), `chore/upgrade-laravel-filament` (Verifier PASS, no PR yet — open one), and `test/pest-migration-browser-tests` (T8-T15 complete, CI observed green, Verifier not yet dispatched — dispatch it next, then open a PR). Merge PR #1, then **`infrastructure` T11 (cutover)** while it is still cheap. Decide merge order across all three branches before opening PRs for the other two — the upgrade branch changes `composer.json`/`docker-compose.yml`/CI broadly, and the pest-migration branch also changes `composer.json`/`package.json`/`phpunit.xml`/`.github/workflows/ci.yml`, so both will conflict with anything infra touches in those files and likely with each other. After all three land: `automation-and-scheduling` — it consumes `AlertNotifier` (AD-011) and must **not** rebuild it. `provider-drivers` is independent and can run in parallel; it still carries a blocking research task (Anthropic/Gemini batch + structured-output API shapes are deliberately unasserted).

### Standing lesson from this session

Every one of the three spec corrections above was found by **executing** something — a query against a real engine, a lifecycle rule against real S3 semantics, the suite on a machine that wasn't the author's. None was found by review. The pipeline's spec/design/tasks/verify stages all ran on one machine and all passed; the first execution elsewhere surfaced a live production defect in minutes.
