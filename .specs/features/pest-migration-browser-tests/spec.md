# Pest Migration & Browser Tests Specification

## Problem Statement

The suite (198 tests, 851 assertions, 37 files) is entirely PHPUnit-class-based and entirely **below the HTTP boundary** — it asserts controllers, models, jobs and Livewire components in isolation. Nothing exercises the app as a user experiences it, so a defect that only manifests in a real browser is invisible to the suite. AD-014 is the precedent: draw-page JSON-LD was absent in a clean environment while every test stayed green, and the defect shipped to `main`. This feature adds a real-browser layer that closes the admin-edit → public-render loop, and standardises the suite on Pest 4 to get there.

## Goals

- [ ] Suite runs on Pest 4 with **zero test loss**: 198 tests / 851 assertions preserved or exceeded at every checkpoint.
- [ ] All 37 test files converted to Pest function syntax; no PHPUnit `TestCase` subclasses remain under `tests/`.
- [ ] One end-to-end browser test proves: an admin can open a Fabricator page in Filament, edit it, save it, and see the edit rendered on the public draw-page URL.
- [ ] A second browser test proves every block configured on a page renders its content on the public page.

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ------- | ------ |
| Browser tests **running** in CI | User decision: browser tests are too slow for CI for now. CI keeps running the Unit + Feature suites and must **explicitly exclude** the Browser suite (PEST-15). Deferred as PEST-F1. |
| Exhaustive user-path coverage | User's stated scope is a starting beachhead, not full coverage. Two flows only. |
| `pest-plugin-mutate` / `pest-plugin-arch` usage | Installed transitively as Pest dependencies; writing mutation or architecture tests is a separate decision. |
| Fixing AD-014's unknown root cause | Pre-existing, unrelated to this diff. |
| Fixing the missing-stylesheet defect (lesson L-011) | Pre-existing bug in `draw-page.blade.php` (never calls `FilamentFabricator::getStyles()`). **A browser test may surface it as an unstyled page.** User decision: this gets its own spec, tracked here as follow-up **PEST-F2**; not fixed in this feature. |
| INFRA-22 (`sqlite`/`mysql` still reachable) | Unrelated open item. |

---

## Assumptions & Open Questions

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| Pest requires a PHPUnit 11→12 major bump | Accept the bump | Verified by `composer require --dry-run`: `pest ^4.7` requires `phpunit/phpunit ^12.5.30`; there is no Pest 4 line compatible with PHPUnit 11. Not optional. | y (measured) |
| Existing 198 tests survive PHPUnit 12 | Verify empirically at a dedicated checkpoint **before** any conversion begins | PHPUnit 12 removes deprecated APIs. Splitting "runner bump" from "syntax conversion" means a failure is attributable to exactly one cause. | n — task gate |
| Full conversion is mechanical and safe | Convert in file-batches, running the suite after each batch | 37 files is a large diff over a green suite that just survived a framework upgrade. Batching bounds the blast radius. | n — task gate |
| Browser tests can use `RefreshDatabase` | Assume yes; **verify in Design** before writing tests | Pest 4's browser plugin serves the app from the same PHP process (amphp/http-server), which should keep the test transaction visible to the request. If false, fall back to `DatabaseTruncation`. This is the single largest technical unknown. | n — Design gate |
| Browser suite is a separate testsuite | Add a `Browser` testsuite so `php artisan test` can exclude it | Browser tests are slow and need Playwright; they must not make the default suite unrunnable for someone without browsers installed. | y (decision) |
| Local-only browser tests mean PRs are unguarded | Record as an explicit, accepted gap; do not silently drop it | Lesson L-004: a test that never runs in CI is not protecting anything. Logged here per lesson L-006 (documenting a gap does not discharge it) as follow-up **PEST-F1**. | y (user decision) |
| Test DB is Postgres on port **5433** | Inherit from `.env`; do not hardcode | Port 5432 on this machine belongs to an unrelated project (`product-catalog-pgsql-1`). Hardcoding 5432 would silently test the wrong database. | y (measured) |
| Filament login is enabled | Browser test authenticates through the real `/admin/login` form | `AdminPanelProvider` calls `->login()`; `UserFactory` exists. | y (measured) |
| Public draw pages require `status = Published` | Seed the fixture page as `Published` | `routes/web.php` filters on `PageStatus::Published`. A `Generated` page 404s. | y (measured) |

**Open questions:** none — all resolved or logged above.

---

## Implicit-Requirement Dimensions Sweep

Large scope — every dimension resolves to a requirement or an explicit N/A.

| Dimension | Resolution |
| --------- | ---------- |
| Input validation & bounds | N/A because this feature adds test infrastructure; it introduces no user-facing input surface. |
| Failure / partial-failure states | **PEST-02, PEST-06** — a mid-conversion batch failure must leave the suite runnable and the cause attributable to one batch. |
| Idempotency / retry / duplicate handling | **PEST-09** — browser tests must be re-runnable without manual DB cleanup between runs. |
| Auth boundaries & rate limits | **PEST-10** — the admin flow authenticates through the real login form; an unauthenticated visit to `/admin` must not reach the editor. |
| Concurrency / ordering | N/A because the browser suite runs serially by decision (`--parallel` is not enabled for it); Playwright state is per-test. |
| Data lifecycle / expiry | N/A because tests own ephemeral fixtures torn down per test. |
| Observability | **PEST-12** — a failing browser test must emit a screenshot/artifact, otherwise a headless failure is undiagnosable. |
| External-dependency failure | **PEST-13** — Playwright browsers absent must fail with a clear, actionable message, not an opaque crash, and must not break the non-browser suite. |
| State-transition integrity | **PEST-07** — page `status` must remain `Published` across the admin edit, or the public assertion silently tests a 404 instead. |

---

## User Stories

### P1: Suite runs on Pest 4 with zero test loss ⭐ MVP

**User Story**: As the developer, I want the existing suite running on Pest 4 so that browser testing becomes available without losing any coverage I already have.

**Why P1**: The browser plugin cannot be installed without it. This is the foundation, and it is the step that carries regression risk to 198 green tests.

**Acceptance Criteria**:

1. WHEN `composer install` runs THEN the lockfile SHALL contain `pestphp/pest ^4.7`, `pestphp/pest-plugin-browser ^4.3` and `phpunit/phpunit ^12.5`.
2. WHEN the suite is run immediately after the dependency bump and **before** any test file is converted THEN it SHALL report **198 passed, 851 assertions** — identical to the recorded baseline.
3. WHEN any conversion batch completes THEN the suite SHALL report a passing count **greater than or equal to 198**, and SHALL never report fewer.
4. WHEN conversion is complete THEN **zero** files under `tests/` SHALL extend `Tests\TestCase` as a class-based PHPUnit test.
5. WHEN a conversion batch fails THEN the failure SHALL be isolated to the files in that batch, with the suite still executable.

**Independent Test**: `php artisan test` reports ≥198 passing; `grep -rl "extends TestCase" tests/` returns nothing.

---

### P1: Admin edits a page in Filament and the edit appears publicly ⭐ MVP

**User Story**: As the developer, I want a browser test that drives the real admin editor and then the real public page so that I can verify my own work end-to-end instead of trusting isolated unit assertions.

**Why P1**: This is the explicit ask, and it is the loop AD-014 proved is unguarded.

**Acceptance Criteria**:

1. WHEN an unauthenticated browser visits `/admin/pages` THEN the system SHALL redirect to the login form and SHALL NOT render the page editor. *(PEST-10)*
2. WHEN a seeded user submits valid credentials at `/admin/login` THEN the system SHALL land on the authenticated admin panel.
3. WHEN the authenticated browser opens the edit screen for a seeded draw page THEN the editor SHALL display that page's existing title in an editable field. *(PEST-05 — "see a page in Filament")*
4. WHEN the browser changes the page title to a distinct, test-generated value and saves THEN the system SHALL persist the change and show a success confirmation. *(PEST-06)*
5. WHEN the browser then visits that page's public URL `/{game}/resultado/{concurso}` THEN the response SHALL be 200 and the rendered page SHALL contain the **new** title and SHALL NOT contain the old one. *(PEST-07)*
6. WHEN the edit completes THEN the page's `status` SHALL still be `Published`. *(PEST-07, state-transition integrity)*

**Independent Test**: Run the browser test alone; it drives login → edit → save → public visit and asserts the new title is visible publicly.

---

### P1: All configured blocks render their content publicly ⭐ MVP

**User Story**: As the developer, I want a browser test asserting that every block on a page actually renders, so that a block silently emitting nothing is a test failure rather than a discovery in production.

**Why P1**: This is the direct, generalised guard against the AD-014 class of failure — markup that is absent while the page still returns 200.

**Acceptance Criteria**:

1. WHEN a page is seeded with a known set of blocks each carrying distinctive content THEN visiting its public URL SHALL render text unique to **each** block. *(PEST-08)*
2. WHEN a block's content is absent from the rendered page THEN the test SHALL fail naming which block was missing. *(PEST-08 — diagnosability)*
3. WHEN the page renders THEN the browser SHALL report **zero uncaught JavaScript console errors**. *(PEST-11)*

**Independent Test**: Seed a multi-block page, run the test, confirm it fails if any single block's Blade template is emptied.

---

### P2: Browser suite is isolated and diagnosable

**User Story**: As the developer, I want browser tests separated from the fast suite and diagnosable when they fail, so that they don't make everyday testing slow or opaque.

**Why P2**: Not required to demonstrate the flows, but the difference between a browser suite that gets used and one that gets disabled after a week.

**Acceptance Criteria**:

1. WHEN `php artisan test --testsuite=Unit,Feature` runs THEN it SHALL execute without Playwright installed and SHALL NOT run browser tests. *(PEST-13)*
2. WHEN Playwright browsers are missing and a browser test runs THEN the failure message SHALL state how to install them. *(PEST-13)*
3. WHEN a browser test fails THEN a screenshot of the failing page state SHALL be written to a known artifact path. *(PEST-12)*
4. WHEN the browser suite is run twice consecutively THEN the second run SHALL pass without manual database cleanup. *(PEST-09)*
5. WHEN CI runs THEN it SHALL execute the Unit and Feature suites and SHALL NOT execute the Browser suite. *(PEST-15)* — CI currently runs bare `php artisan test`, which would pick up browser tests and fail on missing Playwright; the exclusion is an explicit workflow change, not a no-op.

**Independent Test**: Run the non-browser suites on a machine without Playwright; confirm green. Run a browser test twice; confirm green both times.

---

### P3: Documentation reflects reality

**User Story**: As a future contributor (or agent), I want `CLAUDE.md` to describe the real test setup so that I don't act on a stale claim.

**Why P3**: Docs-only, but `CLAUDE.md` currently asserts the exact opposite of what will be true.

**Acceptance Criteria**:

1. WHEN this feature is complete THEN `CLAUDE.md` SHALL NOT contain the claim "PHPUnit, not Pest — `pestphp` is not installed". *(PEST-14)*
2. WHEN a contributor reads `CLAUDE.md` THEN it SHALL state how to run the browser suite and how to install Playwright browsers. *(PEST-14)*

---

## Edge Cases

- WHEN the test database is reached on the default port 5432 THEN it is the **wrong database** (an unrelated project) — configuration SHALL inherit `.env`'s 5433, never hardcode.
- WHEN a Fabricator block stores `blocks` JSON THEN an edit through Filament SHALL NOT corrupt unrelated blocks on the same page.
- WHEN the seeded page's `status` is not `Published` THEN the public route 404s — the test SHALL assert 200 explicitly rather than asserting on an error page's body.
- WHEN the Vite manifest is absent THEN `Tests\TestCase::ensureViteManifest()` stubs it; the browser suite SHALL retain equivalent behavior after conversion (a real browser will request these assets).
- WHEN Filament's editor uses Livewire-driven async saves THEN the test SHALL wait for the save confirmation rather than asserting immediately after click.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| PEST-01 | P1: Pest 4 runner | Execute | Done |
| PEST-02 | P1: Pest 4 runner (baseline parity gate) | Execute | Done |
| PEST-03 | P1: Pest 4 runner (conversion parity) | Execute | Done |
| PEST-04 | P1: Pest 4 runner (no class-based tests remain) | Execute | Done |
| PEST-05 | P1: Admin edit flow (see page in editor) | Design | Pending |
| PEST-06 | P1: Admin edit flow (edit + save) | Design | Pending |
| PEST-07 | P1: Admin edit flow (public reflects edit; status intact) | Design | Pending |
| PEST-08 | P1: Block rendering (all blocks visible) | Design | Pending |
| PEST-09 | P2: Browser suite (re-runnable) | Design | Pending |
| PEST-10 | P1: Admin edit flow (auth boundary) | Design | Pending |
| PEST-11 | P1: Block rendering (no console errors) | Design | Pending |
| PEST-12 | P2: Browser suite (failure screenshots) | Design | Pending |
| PEST-13 | P2: Browser suite (isolated; clear missing-browser error) | Design | Pending |
| PEST-14 | P3: Documentation | Design | Pending |
| PEST-15 | P2: CI runs Unit+Feature, excludes Browser | Design | Pending |
| PEST-F1 | Follow-up: browser tests running in CI | Deferred | Open |
| PEST-F2 | Follow-up: draw-page stylesheet defect (own spec) | Deferred | Open |

**Coverage:** 15 in-scope requirements + 2 deferred follow-ups.

---

## Success Criteria

- [ ] `php artisan test` reports ≥198 passing, 0 failing, with zero PHPUnit class-based tests remaining.
- [ ] A single browser test drives login → edit page in Filament → save → public URL shows the edit.
- [ ] Emptying any one block's Blade template causes the block-rendering browser test to fail (discrimination check).
- [ ] The non-browser suite runs green on a machine with no Playwright installed.
- [ ] `CLAUDE.md` describes the real setup.
