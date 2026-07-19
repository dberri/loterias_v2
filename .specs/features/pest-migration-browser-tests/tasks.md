# Pest Migration & Browser Tests — Tasks

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user — do not proceed without it.**

---

**Design**: `.specs/features/pest-migration-browser-tests/design.md`
**Status**: Draft

---

## Baseline (measured 2026-07-18, not assumed)

`php artisan test` → **198 passed, 851 assertions, 37 files.** Every parity gate below is stated against this.

---

## Test Coverage Matrix

> Generated from codebase + project guidelines. Guidelines found: `CLAUDE.md` (testing section, Pint requirement), `.github/copilot-instructions.md`, `phpunit.xml`, `.github/workflows/ci.yml`.
>
> **This feature is unusual: the deliverable *is* tests.** For conversion tasks the "test" is a parity gate on the converted files themselves; for browser tasks the test is the browser test being written.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Dependency / config (`composer.json`, `phpunit.xml`, `.gitignore`, CI yml) | none — gate only | Suite parity gate must pass; no new test owed | — | `php artisan test` |
| Test bootstrap (`tests/Pest.php`) | none — gate only | Proven by the whole suite continuing to pass | `tests/Pest.php` | `php artisan test` |
| Converted test files | parity gate | Test count never drops below 198; no test silently deleted or skipped | `tests/{Unit,Feature}/**` | `php artisan test --testsuite=Unit,Feature` |
| Browser fixture helper | integration | Exercised by the browser tests that consume it; no standalone test | `tests/Browser/Fixtures/**` | `php artisan test --testsuite=Browser` |
| Browser flows | e2e | 1:1 to spec ACs — every AC of PEST-05/06/07/08/10/11 asserted | `tests/Browser/*Test.php` | `php artisan test --testsuite=Browser` |
| Docs (`CLAUDE.md`, `STATE.md`) | none | — | — | — |

## Gate Check Commands

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Quick | After a conversion batch | `php artisan test --testsuite=Unit,Feature` |
| Full | After browser tasks | `php artisan test` (all suites, incl. Browser) |
| Build | After phase completion | `vendor/bin/pint --dirty && php artisan test` |

**Parity rule (applies to every gate in Phases 1–2):** record the printed test count. Phase 1 gates on **exactly 198 passed / 851 assertions**. Phase 2 gates on **≥ 198 passed**, never fewer. Assertion count is *not* strictly gated in Phase 2 — drift rewrites `$this->assertTrue()` into `expect()->toBeTrue()`, which may legitimately shift the assertion tally without changing coverage.

---

## Execution Plan

### Phase 1: Runner swap — two isolated risks, two gates

```
T1 → T2
```

### Phase 2: Syntax conversion — batched, parity-gated

```
T3 → T4 → T5 → T6 → T7
```

### Phase 3: Browser infrastructure

```
T8 → T9
```

### Phase 4: Browser flows

```
T10 → T11 → T12
```

### Phase 5: Integration, docs, decision record

```
T13 → T14 → T15
```

---

## Task Breakdown

### T1: Bump PHPUnit 11 → 12 in isolation

**What**: Raise `phpunit/phpunit` to `^12.5` with no other test-tooling change, proving the major bump alone breaks nothing.
**Where**: `composer.json`, `composer.lock`
**Depends on**: None
**Reuses**: existing `phpunit.xml` verbatim
**Requirement**: PEST-01, PEST-02

**Why isolated**: verified by dry-run that PHPUnit 12 resolves without Pest. Bumping it separately from the Pest install means a red suite has exactly one possible cause. Do not combine with T2.

**Done when**:
- [x] `composer.json` requires `phpunit/phpunit ^12.5`; no `pestphp/*` yet
- [x] Gate passes: `php artisan test` → **exactly 198 passed, 851 assertions**
- [x] Any deviation from 198/851 is investigated and explained **before** committing — a changed count means silent skipping, not success

**Tests**: none (gate only) · **Gate**: build
**Commit**: `chore(test): bump phpunit to 12.x`

---

### T2: Install Pest 4 + drift and add the Pest bootstrap

**What**: Add `pestphp/pest ^4.7` and `pestphp/pest-plugin-drift ^4.1`, create `tests/Pest.php`, keep every existing test file untouched.
**Where**: `composer.json`, `tests/Pest.php` (new), `phpunit.xml`
**Depends on**: T1
**Reuses**: `Tests\TestCase::ensureViteManifest()` — moved to a `beforeEach` hook
**Requirement**: PEST-01, PEST-02

**Done when**:
- [x] `tests/Pest.php` binds `Tests\TestCase` + `RefreshDatabase` to `Feature`, and `Tests\TestCase` **without** `RefreshDatabase` to `Unit`
- [x] `ensureViteManifest()` runs via `beforeEach` for all suites
- [x] **Zero** test files converted in this task
- [x] Gate passes: `php artisan test` → **exactly 198 passed, 851 assertions** under the Pest runner
- [x] `vendor/bin/pint --dirty` clean

**Tests**: none (gate only) · **Gate**: build
**Commit**: `chore(test): add pest 4 runner and bootstrap`

---

### T3: Convert `tests/Unit` — models, casts, config, DTOs, factories

**What**: Run drift over the 8 non-block, non-service Unit files, then hand-review the diff.
**Where**: `tests/Unit/{ExampleTest,Config/BackupDiskConfigTest,Casts/NulSafeJsonTest,Models/{DrawTest,PageTest,DrawAccessorTest},DTOs/GenerationRequestTest,Factories/{PageFactoryTest,DrawFactoryTest}}.php`
**Depends on**: T2
**Requirement**: PEST-03, PEST-04

**Done when**:
- [x] Every listed file uses Pest function syntax; none extends `Tests\TestCase`
- [x] Hand-review confirms **no assertion was dropped** and no test renamed into meaninglessness
- [x] `AD-012` guard intact: `NulSafeJsonTest`'s accessor-parity assertions survive verbatim in meaning — this test **licenses** AD-012's exception to AD-001 and must not be weakened
- [x] Gate passes: `php artisan test --testsuite=Unit,Feature` → **≥ 198 passed**

**Tests**: parity gate · **Gate**: quick
**Commit**: `test(pest): convert unit model, cast and factory tests`

---

### T4: Convert `tests/Unit` — page blocks and services

**What**: Run drift over the 11 remaining Unit files, then hand-review.
**Where**: `tests/Unit/PageBlocks/*` (5), `tests/Unit/Services/*` (3), `tests/Unit/Services/Providers/OpenAiContentProviderTest.php`, `tests/Unit/Services/Content/DrawPagePromptTest.php`
**Depends on**: T3
**Requirement**: PEST-03, PEST-04

**Done when**:
- [x] All listed files converted; none extends `Tests\TestCase`
- [x] `ContentProviderManagerTest` still enforces AD-010's shared-contract intent (drift must not split shared datasets into per-driver duplicates)
- [x] Gate passes: `php artisan test --testsuite=Unit,Feature` → **≥ 198 passed**

**Tests**: parity gate · **Gate**: quick
**Commit**: `test(pest): convert unit page-block and service tests`

---

### T5: Convert `tests/Feature/Commands` and `tests/Feature/Jobs`

**What**: Run drift over the 11 command/job files, then hand-review.
**Where**: `tests/Feature/Commands/*` (4), `tests/Feature/Jobs/*` (7)
**Depends on**: T4
**Requirement**: PEST-03, PEST-04

**Done when**:
- [x] All 11 files converted
- [x] `ExportCorpusScheduleTest` still asserts the *property* the spec states, not a literal it never fixed (lesson L-007) — do not let drift harden a schedule assertion
- [x] `ExportCorpusRetentionTest`'s AD-013 sibling-prefix assertions survive intact
- [x] Gate passes: `php artisan test --testsuite=Unit,Feature` → **≥ 198 passed**

**Tests**: parity gate · **Gate**: quick
**Commit**: `test(pest): convert command and job feature tests`

---

### T6: Convert remaining `tests/Feature`

**What**: Run drift over the last 7 Feature files, then hand-review.
**Where**: `tests/Feature/{ExampleTest,DrawPageRenderingTest,Filament/PageResourceTest,Services/ScraperTest,Database/{JsonRoundTripTest,DrawDateBackfillMigrationTest}}.php`
**Depends on**: T5
**Requirement**: PEST-03, PEST-04

**Done when**:
- [x] All listed files converted
- [x] `DrawPageRenderingTest`'s AD-014 JSON-LD presence assertions survive intact — this is the test standing in for a production SEO defect
- [x] Gate passes: `php artisan test --testsuite=Unit,Feature` → **≥ 198 passed**

**Tests**: parity gate · **Gate**: quick
**Commit**: `test(pest): convert remaining feature tests`

---

### T7: Retire the class-based base and prove zero remnants

**What**: Reduce/remove `tests/TestCase.php` to whatever `Pest.php` still needs, and prove no class-based PHPUnit test remains.
**Where**: `tests/TestCase.php`, `tests/Pest.php`
**Depends on**: T6
**Requirement**: PEST-04

**Done when**:
- [x] `grep -rl "extends TestCase" tests/` returns **nothing**
- [x] `grep -rln "public function test_" tests/` returns **nothing**
- [x] Gate passes: `vendor/bin/pint --dirty && php artisan test` → **≥ 198 passed**

**Tests**: parity gate · **Gate**: build
**Commit**: `test(pest): retire class-based phpunit base`

---

### T8: Install the browser plugin and prove the harness runs

**What**: Add `pestphp/pest-plugin-browser`, install Playwright, add the `Browser` testsuite, gitignore screenshots, and land **one** smoke test that proves the harness actually drives a browser.
**Where**: `composer.json`, `package.json`, `phpunit.xml`, `.gitignore`, `tests/Browser/SmokeTest.php` (new)
**Depends on**: T7
**Requirement**: PEST-01, PEST-09, PEST-13

**Done when**:
- [x] `npm install playwright@latest && npx playwright install` documented and run
- [x] `phpunit.xml` has a `Browser` testsuite pointing at `tests/Browser`
- [x] `.gitignore` contains `tests/Browser/Screenshots`
- [x] Smoke test visits `/` and calls `assertNoJavaScriptErrors()`; passes
- [x] **Run it twice consecutively — both green** (PEST-09, no manual DB cleanup)
- [x] `php artisan test --testsuite=Unit,Feature` still **≥ 198**, and does not execute browser tests

**Tests**: e2e (smoke) · **Gate**: full
**Commit**: `test(browser): add pest browser plugin and smoke test`

---

### T9: Build the draw-page browser fixture

**What**: A helper producing one `Draw` + one **Published** `Page` whose blocks carry unique, assertable markers.
**Where**: `tests/Browser/Fixtures/DrawPageFixture.php` (new)
**Depends on**: T8
**Reuses**: `DrawFactory::fixture()`, `PageFactory::published()`
**Requirement**: PEST-07, PEST-08

**Done when**:
- [x] Returns `draw`, `page`, and a `markers` map of block type → unique string
- [x] Page status is `PageStatus::Published` (AD-006 — otherwise the public route 404s)
- [x] Covers only the **10 implemented** blocks; the 4 stub blocks are excluded with an inline comment naming PEST-F3
- [x] Consumed by a passing assertion in the smoke test or a temporary check

**Tests**: integration (via consumers) · **Gate**: full
**Commit**: `test(browser): add published draw-page fixture`

---

### T10: Browser test — admin edits a page, public page reflects it

**What**: The headline flow: auth boundary → login → open editor → edit title → save → public page shows the change.
**Where**: `tests/Browser/AdminEditsPageTest.php` (new)
**Depends on**: T9
**Requirement**: PEST-05, PEST-06, PEST-07, PEST-10

**Done when**:
- [x] Unauthenticated `visit('/admin/pages')` asserts redirect to login and **does not** render the editor (PEST-10 AC1)
- [x] Logs in through the **real `/admin/login` form** — `actingAs()` is forbidden here, it would bypass the boundary under test
- [x] Editor displays the page's existing title (PEST-05)
- [x] Title changed to a distinct generated value, saved, confirmation asserted (PEST-06)
- [x] Public URL asserts **new title present AND old title absent** — both, separately (lesson L-005)
- [x] Page `status` re-read from DB and still `Published` (PEST-07 AC6)
- [x] Gate passes: `php artisan test`

**Tests**: e2e · **Gate**: full
**Commit**: `test(browser): assert admin page edit reaches the public page`

---

### T11: Browser test — every implemented block renders

**What**: Assert each of the 10 implemented blocks emits its marker, and the page raises no JS errors.
**Where**: `tests/Browser/DrawPageRendersBlocksTest.php` (new)
**Depends on**: T9
**Requirement**: PEST-08, PEST-11

**Done when**:
- [x] Each of the 10 markers asserted **individually**, so a failure names the missing block (PEST-08 AC2) — not one bulk assertion
- [x] `assertNoJavaScriptErrors()` passes (PEST-11)
- [x] **Discrimination check performed and recorded**: empty one block's Blade template, confirm the test goes red naming that block, then restore. A test that survives this is not testing anything.
- [x] If the missing-stylesheet defect (PEST-F2) causes noise, record it — do **not** weaken the test to accommodate it
- [x] Gate passes: `php artisan test`

**Tests**: e2e · **Gate**: full
**Commit**: `test(browser): assert all implemented blocks render`

---

### T12: Verify screenshot-on-failure empirically

**What**: Prove Pest's automatic failure screenshot actually lands, given open upstream bug pest#1543.
**Where**: throwaway assertion in an existing browser test (not committed as a failing test)
**Depends on**: T10, T11
**Requirement**: PEST-12

**Done when**:
- [x] A browser assertion is deliberately broken; `tests/Browser/Screenshots/` is confirmed to receive a file named for the failing test
- [x] The break is reverted; suite green
- [x] Result recorded in `validation.md` — **if screenshots do NOT appear, say so plainly and escalate**; do not quietly mark PEST-12 satisfied

**Tests**: none (empirical verification) · **Gate**: full
**Commit**: `test(browser): verify failure screenshots are captured`

---

### T13: Exclude the Browser suite from CI

**What**: Change CI's bare `php artisan test` to an explicit suite list so browser tests never run there.
**Where**: `.github/workflows/ci.yml`
**Depends on**: T12
**Requirement**: PEST-13, PEST-15

**Done when**:
- [ ] CI runs `php artisan test --testsuite=Unit,Feature`
- [ ] No Node/Playwright/`ext-sockets` step added — CI must not need them
- [ ] **A real CI run is observed green and its URL cited** (lesson L-004 — a workflow file is not CI until a run passes)

**Tests**: none · **Gate**: build
**Commit**: `ci: run unit and feature suites, exclude browser`

---

### T14: Correct `CLAUDE.md`

**What**: Replace the now-false "PHPUnit, not Pest — `pestphp` is not installed" claim and document the browser workflow.
**Where**: `CLAUDE.md`
**Depends on**: T13
**Requirement**: PEST-14

**Done when**:
- [ ] The stale claim is gone
- [ ] Documents: `php artisan test`, `--testsuite=Unit,Feature`, `--testsuite=Browser`, and the Playwright install command
- [ ] Notes that browser tests are local-only by design (PEST-F1)

**Tests**: none · **Gate**: none
**Commit**: `docs: correct testing section for pest and browser tests`

---

### T15: Record AD-016 and the follow-ups

**What**: Append the decision record and the three deferred follow-ups to project memory.
**Where**: `.specs/STATE.md`
**Depends on**: T14
**Requirement**: PEST-F1, PEST-F2, PEST-F3

**Done when**:
- [ ] **AD-016** appended: Pest is the runner standard; browser tests are the required guard for changes to public-facing templates; browser suite is local-only (with the accepted-risk rationale)
- [ ] Handoff section lists **PEST-F1** (CI), **PEST-F2** (stylesheet defect — own spec), **PEST-F3** (4 stub blocks) as open
- [ ] Baseline updated: new suite totals recorded

**Tests**: none · **Gate**: none
**Commit**: `docs(state): record AD-016 and pest migration follow-ups`

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3 → Phase 4 → Phase 5

Phase 1:  T1 ──→ T2
Phase 2:  T3 ──→ T4 ──→ T5 ──→ T6 ──→ T7
Phase 3:  T8 ──→ T9
Phase 4:  T10 ──→ T11 ──→ T12
Phase 5:  T13 ──→ T14 ──→ T15
```

**Batch packing**: 15 tasks → Phase 1+2 = 7 tasks (batch 1), Phase 3+4+5 = 8 tasks (batch 2). Two batches → sub-agent offer applies.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1 | 1 dependency bump | ✅ |
| T2 | 1 bootstrap file + config | ✅ |
| T3–T6 | 1 cohesive file batch each (8/11/11/7) | ✅ cohesive |
| T7 | 1 file retirement + proof | ✅ |
| T8 | 1 plugin + harness proof | ✅ |
| T9 | 1 helper class | ✅ |
| T10 | 1 test file | ✅ |
| T11 | 1 test file | ✅ |
| T12 | 1 verification | ✅ |
| T13 | 1 workflow file | ✅ |
| T14 | 1 doc file | ✅ |
| T15 | 1 memory file | ✅ |

---

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram | Status |
| ---- | ----------------- | ------- | ------ |
| T1 | None | — | ✅ |
| T2 | T1 | T1→T2 | ✅ |
| T3 | T2 | T2→T3 (phase edge) | ✅ |
| T4 | T3 | T3→T4 | ✅ |
| T5 | T4 | T4→T5 | ✅ |
| T6 | T5 | T5→T6 | ✅ |
| T7 | T6 | T6→T7 | ✅ |
| T8 | T7 | T7→T8 (phase edge) | ✅ |
| T9 | T8 | T8→T9 | ✅ |
| T10 | T9 | T9→T10 (phase edge) | ✅ |
| T11 | T9 | T10→T11 ⚠️ | ✅ resolved — T11 truly depends only on T9; sequential order is a batching artifact, not a data dependency |
| T12 | T10, T11 | T11→T12 | ✅ |
| T13 | T12 | T12→T13 (phase edge) | ✅ |
| T14 | T13 | T13→T14 | ✅ |
| T15 | T14 | T14→T15 | ✅ |

No task depends on a later phase.

---

## Test Co-location Validation

| Task | Layer | Matrix Requires | Task Says | Status |
| ---- | ----- | --------------- | --------- | ------ |
| T1 | dependency/config | none — gate | none (gate: exact parity) | ✅ |
| T2 | test bootstrap | none — gate | none (gate: exact parity) | ✅ |
| T3–T6 | converted tests | parity gate | parity gate | ✅ |
| T7 | converted tests | parity gate | parity gate | ✅ |
| T8 | browser flows | e2e | e2e (smoke) | ✅ |
| T9 | browser fixture | integration | integration via consumers | ✅ |
| T10 | browser flows | e2e | e2e | ✅ |
| T11 | browser flows | e2e | e2e | ✅ |
| T12 | — (verification) | none | none | ✅ |
| T13 | CI config | none | none | ✅ |
| T14 | docs | none | none | ✅ |
| T15 | docs | none | none | ✅ |

No violations. `Tests: none` appears only where the matrix says none.
