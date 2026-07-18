# Framework Upgrade: Laravel 13 + Filament 5 — Tasks

## Execution Protocol (MANDATORY -- do not skip)

Implement these tasks with the `tlc-spec-driven` skill: **activate it by name and follow its Execute flow and Critical Rules.** Do not search for skill files by filesystem path. The skill is the source of truth for the full flow (per-task cycle, sub-agent delegation, adequacy review, Verifier, discrimination sensor).

**If the skill cannot be activated, STOP and tell the user — do not proceed without it.**

---

**Spec**: `.specs/features/framework-upgrade-laravel-13-filament-5/spec.md`
**Decisions**: `.specs/features/framework-upgrade-laravel-13-filament-5/context.md`
**Design**: none — skipped deliberately. This upgrade introduces no architecture; the target shape is fixed by upstream releases, and the single design-shaped decision (`PageResource` re-derivation) is locked in `context.md` D2.
**Status**: Draft

---

## ⚠️ Known deviation from the standard per-task gate — read before executing

The skill's execution contract requires the test suite to pass before a task is done. **T2 and T8 cannot satisfy that**, and no task decomposition can make them.

Bumping a framework major and running its codemod is a single indivisible operation: the moment `composer.json` changes, the old API is gone and the suite is red until reconciliation finishes. Splitting it further doesn't produce a green intermediate — it produces a *broken* intermediate that can't even boot.

These two tasks therefore carry a **boot gate** instead of a test gate (`composer install` resolves + `php artisan about` succeeds + Pint passes), and they explicitly record the failing-test inventory as their deliverable. The full-suite gate lands on the very next task in the same phase. No phase ends red.

This is called out here rather than silently absorbed. If you'd rather not carry a red commit at all, the alternative is to fold T2–T7 into one large task — which is worse for bisecting, and was rejected for that reason.

---

## Test Coverage Matrix

> Generated from codebase, project guidelines, and spec — confirm before Execute. Guidelines found: `CLAUDE.md`, `.github/copilot-instructions.md`, `phpunit.xml`, `.github/workflows/ci.yml`.

**Framing**: this feature writes almost no new code — it moves existing code across API boundaries. The existing 37-file suite **is** the safety net, so the dominant expectation is *preservation*, not new coverage. New tests are added in exactly one place (T6), where a spec AC (P1.6) has no existing coverage.

| Code Layer | Required Test Type | Coverage Expectation | Location Pattern | Run Command |
| ---------- | ------------------ | -------------------- | ---------------- | ----------- |
| Filament Resources & Resource Pages | feature (Livewire) | Every behavior currently asserted in `PageResourceTest` preserved: list/edit metadata surfaces, publish happy path + public 200, failed-publish rejection. Test bodies change ONLY for framework-mandated API renames | `tests/Feature/Filament/*Test.php` | `php artisan test --testsuite=Feature` |
| Fabricator PageBlocks & Layouts | unit | Every block class loads and registers (incl. parked blocks); `mutateData()` behavior preserved for the four live blocks already under test | `tests/Unit/PageBlocks/*Test.php` | `php artisan test --testsuite=Unit` |
| Domain — models, services, jobs, commands, casts, DTOs | unit + feature | Zero behavior change. Entire existing suite passes unmodified; any edit here is a regression finding, not a fix | `tests/Unit/**`, `tests/Feature/{Commands,Jobs,Services,Database}/**` | `php artisan test` |
| Dependency manifests & infra config (`composer.json`, `docker-compose.yml`, `.github/workflows/ci.yml`, `CLAUDE.md`) | none | Build gate only — verified by resolution, boot, and CI | — | build gate only |

**Anti-regression rule (applies to every task):** the suite currently holds **37 test files**. No task may reduce that count, weaken an assertion, or add `markTestSkipped`. If a test cannot pass after an API rename, that is a finding to surface — never a test to delete.

## Gate Check Commands

> Generated from codebase (`phpunit.xml`, `composer.json`, `.github/workflows/ci.yml`) — confirm before Execute.

| Gate Level | When to Use | Command |
| ---------- | ----------- | ------- |
| Boot | T2 and T8 only — the irreducible dependency-bump tasks | `composer install --no-interaction && php artisan about && vendor/bin/pint --test` |
| Quick | After tasks touching only unit-tested layers | `php artisan test --testsuite=Unit` |
| Full | After tasks touching Filament resources, pages, or domain behavior | `php artisan test` |
| Build | At phase completion and on config-only tasks | `vendor/bin/pint --test && php artisan test && npm run build` |

---

## Execution Plan

Phases are ordered and run sequentially — each phase completes before the next begins, and tasks within a phase execute in order.

### Phase 1: Filament 3 → 4 (Laravel 12) — the heavy lift

```
T1 → T2 → T3 → T4 → T5 → T6 → T7
```

### Phase 2: Filament 4 → 5 + Livewire 4

```
T8 → T9 → T10 → T11
```

### Phase 3: Laravel 12 → 13 + PHP 8.5

```
T12 → T13 → T14 → T15
```

---

## Task Breakdown

### T1: Add Larastan dev dependency

**What**: Install Larastan v3+ as a dev dependency, required by the `filament-v4` upgrade script.
**Where**: `composer.json` (require-dev)
**Depends on**: None
**Reuses**: Existing `require-dev` block
**Requirement**: P1.7

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `larastan/larastan` ^3.0 present in `require-dev`
- [x] `composer install` resolves with no conflict against Laravel 12 / Filament 3
- [x] Gate passes: `vendor/bin/pint --test && php artisan test && npm run build`
- [x] Test count: 37 files, full suite green (unchanged from baseline) — actual verified baseline is 35 files/183 tests; see batch summary deviation note

**Tests**: none (manifest layer) · **Gate**: build
**Commit**: `chore(deps): add larastan for the filament v4 upgrade script`

---

### T2: Bump to Filament 4 + fabricator 3.1 and run the codemod

**What**: Move `filament/filament` to ^4.0 and `z3d0x/filament-fabricator` to ^3.1, install `filament/upgrade:^4.0`, run `vendor/bin/filament-v4`, and commit the script's output after reviewing it.
**Where**: `composer.json`, `composer.lock`, `app/Filament/**`
**Depends on**: T1
**Reuses**: —
**Requirement**: P1.1, P1.2

**Tools**: MCP: NONE · Skill: NONE

**⚠️ Red-tolerant task** — see the deviation note above. The suite WILL fail here; that is expected and is the input to T3–T7.

**Done when**:

- [x] `composer show` reports `filament/filament` ^4.x and `z3d0x/filament-fabricator` ^3.1.x; `laravel/framework` still ^12.x; `php` still `^8.2` — pinned to `^4.11.5` specifically (resolved to v4.12.1), not `^4.0`, to clear 4 Composer-blocked security advisories affecting all earlier v4 releases; see commit `135cf5b` body
- [x] `vendor/bin/filament-v4` has been run and **its full diff read and reviewed**, not blind-accepted — the vendor guide states it does not cover all breaking changes
- [x] The failing-test inventory is captured verbatim into the task's commit body — this list is the working checklist for T3–T7
- [x] Gate passes: `composer install --no-interaction && php artisan about && vendor/bin/pint --test`

**Tests**: none (no behavior authored) · **Gate**: boot
**Commit**: `chore(filament)!: bump to filament v4 + fabricator 3.1, run codemod`

---

### T3: Re-derive PageResource from the fabricator 3.1 vendor resource

**What**: Rebuild `PageResource` on top of fabricator 3.1's own `PageResource` and re-apply only this app's additions, per `context.md` D2.
**Where**: `app/Filament/Resources/PageResource.php`
**Depends on**: T2
**Reuses**: `vendor/z3d0x/filament-fabricator/src/Resources/PageResource.php` (the new base), existing `PageStatus` enum
**Requirement**: P1.3

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] File is derived from the installed vendor `PageResource`, not from the previous v3-era fork
- [x] Exactly these app additions are re-applied, and nothing else: Generation section (status, batch_id, provider, generated_at placeholders); status/batch_id/provider/generated_at table columns; status + layout `SelectFilter`s; `visit` action — the `visit` action is now vendor's own equivalent (see commit `dcc7b19` body for why re-forking it wasn't needed)
- [x] A diff against the vendor file shows only those additions
- [x] `Forms\Form` → Schema API, `Forms\{Get,Set}`, `Forms\Components\{Section,Group}`, and `Tables\Actions\*` all migrated to their v4 homes
- [x] Gate passes: `php artisan test` — blocked by the T6-owned `PageBlock::defineBlock()` fatal recorded in T2; `PageResourceTest` verified green via a scratch/reverted patch (see commit `dcc7b19` body)
- [x] Test count: 37 files, `PageResourceTest` green — actual verified file count is 35 (see T1 deviation note)

**Tests**: feature · **Gate**: full
**Commit**: `refactor(filament): re-derive PageResource from the fabricator v4 base`

---

### T4: Port EditPage's publish action to v4 header actions

**What**: Migrate the custom `publish` action from `getActions()` to v4's header-action API and update the `Action` import.
**Where**: `app/Filament/Resources/PageResource/Pages/EditPage.php`
**Depends on**: T3
**Reuses**: `PageStatus` enum, existing `ValidationException` guard
**Requirement**: P1.5

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `getActions()` migrated to the v4 header-action method; `Filament\Pages\Actions\Action` replaced with `Filament\Actions\Action` (the import was already fixed by T2's codemod)
- [x] `parent::` call updated to the matching parent method so vendor actions are still merged, not dropped
- [x] `requiresConfirmation()` and `color('success')` preserved
- [x] Publishing a `Generated` page still promotes it to `Published` and its public route returns 200
- [x] Publishing a `Failed` page still raises a validation error on `status` and leaves it `Failed`
- [x] Gate passes: `php artisan test` — blocked by the same T6-owned blocker; both publish cases verified green via scratch patch (see commit `11c12cb`)
- [x] Test count: 37 files; both publish cases in `PageResourceTest` green — actual verified file count is 35 (see T1 deviation note)

**Tests**: feature · **Gate**: full
**Commit**: `refactor(filament): port the publish action to v4 header actions`

---

### T5: Port DrawResource and its resource pages

**What**: Migrate `DrawResource`, `ListDraws`, and `ViewDraw` to the v4 API (Infolist → Schema, table actions, form/table signatures).
**Where**: `app/Filament/Resources/DrawResource.php`, `app/Filament/Resources/DrawResource/Pages/{ListDraws,ViewDraw}.php`
**Depends on**: T2
**Reuses**: Existing read-only resource shape (no create/edit — draws come only from the scraper)
**Requirement**: P1.2

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `Filament\Infolists\Infolist` and its `Components\{Section,TextEntry,RepeatableEntry}` migrated to their v4 homes
- [x] `Tables\Actions\{ViewAction,EditAction}` migrated to `Filament\Actions\*`
- [x] Resource remains list + view only — no create/edit surface introduced
- [x] `/admin/draws` list and a draw's view page both render — verified via a throwaway scratch test, deleted after use (see commit `639c21b`); no permanent test exists for this resource
- [x] Gate passes: `php artisan test` — blocked by the same T6-owned blocker; verified green (except the known blocker) via scratch patch
- [x] Test count: 37 files, full suite green — actual verified file count is 35 (see T1 deviation note)

**Tests**: feature · **Gate**: full
**Commit**: `refactor(filament): port DrawResource and its pages to v4`

---

### T6: Port PageBlocks and Layouts, and add block-registration coverage

**What**: Migrate all 15 PageBlock classes and 2 Layout classes to the v4 API, and add the missing test asserting every block registers.
**Where**: `app/Filament/Fabricator/PageBlocks/*.php`, `app/Filament/Fabricator/Layouts/*.php`, new `tests/Unit/PageBlocks/BlockRegistrationTest.php`
**Depends on**: T2
**Reuses**: Existing `tests/Unit/PageBlocks/*Test.php` patterns; `FilamentFabricator::getLayouts()`
**Requirement**: P1.6

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] All 15 blocks migrated: `Forms\Components\Builder\Block` and the `TextInput`/`Select`/`Toggle`/`RichEditor`/`Repeater`/`FileUpload`/`DatePicker`/`Textarea` imports resolved to their v4 homes — actual verified directory contains 14 block files, not 15 (see commit body); none of the 8 named component imports had actually moved namespace in v4 (all still resolve under `Filament\Forms\Components\*`), so the real work was restructuring `getBlockSchema()` into the `$name` property + `defineBlock(Block $block): Block` pattern fabricator 3.1's `PageBlock` base class now requires
- [x] Parked/commented blocks (`StatisticsCardsBlock`, `SimulationBlock`, `NumberGeneratorBlock`, `TimelineBlock`, `ComparisonTableBlock`, `LatestResultsBlock`) **load and register** — they are NOT made functional (out of scope)
- [x] `HeroSectionBlock::mutateData()`'s broken `Draw::drawPage` reference is ported **as-is** — pre-existing bug, explicitly out of scope. Correction: this reference actually lives in `RelatedLinksBlock.php` (lines 63/71), not `HeroSectionBlock` — CLAUDE.md/spec.md misattribute it. Left unmodified in either case.
- [x] New test asserts all 15 block classes instantiate and every one appears in the registered block set — asserts 14, the actual verified count
- [x] Existing `mutateData()` assertions for the 4 live blocks still pass unmodified
- [x] Gate passes: `php artisan test` — full suite green (198 passed, 0 failed) after fixing two blocking pre-existing bugs in `IndividualDrawDetailsBlock.php` (see commit body)
- [x] Test count: 38 files (37 + 1 new), full suite green — actual verified count is 36 files (35 + 1 new); 198 tests passed

**Tests**: unit · **Gate**: full
**Commit**: `refactor(filament): port page blocks and layouts to v4, cover registration`

---

### T7: Reconcile v4 behavior changes and close Phase 1

**What**: Work through the explicit v4 behavior-change list from the spec, decide each one, and remove the upgrade tooling.
**Where**: `app/Filament/Resources/PageResource.php`, `composer.json`
**Depends on**: T3, T4, T5, T6
**Reuses**: The spec's "Explicit v4 behavior changes to reconcile" table
**Requirement**: P1.4, P1.7

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] `Grid`/`Section`/`Group` full-width defaults audited; `columnSpanFull()` applied where v3 width was intended — audited: no change needed (see commit body)
- [x] The 2:1 sidebar split still renders correctly under v4's `>= lg` `columnSpan()` default — audited: no change needed (see commit body)
- [x] Deferred table filters decided **explicitly** — either accept the new default or call `deferFilters(false)`; record which and why in the commit body — decided: `deferFilters(false)` added to preserve pre-upgrade instant-apply UX
- [x] Slug `unique(ignoreRecord: true, modifyRuleUsing: ...)` parent-scoping still enforced — audited: unchanged since T3, already matches v4 default
- [x] `FileUpload` usage audited for the private-by-default visibility change — audited: only usage is in `HeroSectionBlock.php` (outside this task's scope), already explicit `->visibility('public')`
- [x] `larastan/larastan` and `filament/upgrade` removed from `require-dev`
- [x] Gate passes: `vendor/bin/pint --test && php artisan test && npm run build`
- [x] Test count: 38 files, full suite green — **Phase 1 ends green** — actual verified count is 36 files, 198 tests passed

**Tests**: feature · **Gate**: build
**Commit**: `fix(filament): reconcile v4 behavior changes, drop upgrade tooling`

---

### T8: Bump to Filament 5 + fabricator 4.1 and run the v5 codemod

**What**: Move to `filament/filament` ^5.0, `z3d0x/filament-fabricator` ^4.1, and `php: ^8.3`; install `filament/upgrade:^5.0` and run `vendor/bin/filament-v5`.
**Where**: `composer.json`, `composer.lock`, `app/Filament/**`
**Depends on**: T7
**Reuses**: —
**Requirement**: P2.1, P2.2

**Tools**: MCP: NONE · Skill: NONE

**⚠️ Red-tolerant task** — same rationale as T2. Expected to be much smaller: v5 is functionally identical to v4 apart from Livewire 4.

**Done when**:

- [x] `composer show` reports `filament/filament` ^5.x, `z3d0x/filament-fabricator` ^4.1.x, `livewire/livewire` ^4.x — resolved v5.7.1 / v4.1.0 / v4.3.3
- [x] `composer.json` requires `php: ^8.3` (forced by fabricator 4.1.0); `laravel/framework` still ^12.x
- [x] `vendor/bin/filament-v5` run and its diff reviewed — made zero changes to app/Filament/**
- [x] Failing-test inventory captured into the commit body — actual result: NONE, full suite already green (198 passed, 0 failed) after the bump; see commit `97c054b`
- [x] Gate passes: `composer install --no-interaction && php artisan about && vendor/bin/pint --test`

**Tests**: none (no behavior authored) · **Gate**: boot
**Commit**: `chore(filament)!: bump to filament v5 + fabricator 4.1, run codemod`

---

### T9: Reconcile Livewire 4 and restore a green suite

**What**: Fix whatever the v5 codemod left behind — principally Livewire 4 changes surfacing through `Livewire::test()` in the Filament feature tests.
**Where**: `app/Filament/**`, `tests/Feature/Filament/PageResourceTest.php`
**Depends on**: T8
**Reuses**: Livewire 4 upgrade guide; existing `Livewire::test()` assertions
**Requirement**: P2.3

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] All `Livewire::test()` assertions exercise the real components under Livewire 4 — verified all 4 call sites in `PageResourceTest.php` (lines 40, 46, 66, 90) mount real Filament/Livewire components and pass
- [x] Test bodies changed ONLY for framework-mandated API renames — no assertion weakened, no case dropped — actual result: zero renames were needed; the file is byte-for-byte unchanged from Phase 1 (see commit body)
- [x] Gate passes: `php artisan test`
- [x] Test count: 38 files, full suite green — actual verified count is 36 files, 198 tests passed (unchanged from T8; no reduction)

**Tests**: feature · **Gate**: full
**Commit**: `fix(filament): reconcile livewire 4 changes across the panel`

---

### T10: Re-verify PageResource against the fabricator 4.1 vendor source

**What**: Diff `PageResource` against fabricator 4.1's vendor resource and re-derive if upstream changed shape between 3.1 and 4.1 — the final state D2 asks for.
**Where**: `app/Filament/Resources/PageResource.php`
**Depends on**: T9
**Reuses**: `vendor/z3d0x/filament-fabricator/src/Resources/PageResource.php` (4.1)
**Requirement**: P1.3, P2.4

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [x] Diff against the 4.1 vendor file shows **only** this app's four intentional additions (Generation section, extra columns, status/layout filters, `visit` action) — verified via `diff -u vendor/.../PageResource.php app/Filament/Resources/PageResource.php`; also carries the pre-existing T7 decision (`->deferFilters(false)`), which is not new drift, just not itemized in this list
- [x] Any upstream drift between 3.1 and 4.1 has been absorbed rather than overwritten — actual finding: zero upstream drift; fabricator's `PageResource` shape is byte-identical in structure between 3.1.0 and 4.1.0 aside from formatting; no reconciliation was needed
- [x] Page-builder block editor still adds, reorders, and persists blocks — automated coverage: `PageResourceTest::test_page_list_and_edit_surfaces_show_generation_metadata` mounts `EditPage` via `Livewire::test()`, binding the `PageBuilder` field without error; full interactive add/reorder/persist smoke deferred to T11 (browser smoke), per spec.md's Verification Gates split between automated suite and admin smoke
- [x] Gate passes: `php artisan test`
- [x] Test count: 38 files, full suite green — actual verified count is 36 files, 198 tests passed (unchanged)

**Tests**: feature · **Gate**: full
**Commit**: `refactor(filament): re-verify PageResource against the fabricator 4.1 base`

---

### T11: Close Phase 2 — smoke the panel and drop upgrade tooling

**What**: Remove `filament/upgrade`, validate the `filament:upgrade` composer hook still resolves on v5, and smoke the admin + public surfaces.
**Where**: `composer.json`
**Depends on**: T10
**Reuses**: Existing `post-autoload-dump` script block
**Requirement**: P2.4, P2.5, P2.6

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [ ] `filament/upgrade` removed from `require-dev`
- [ ] The `@php artisan filament:upgrade` entry in `post-autoload-dump` **verified to still exist as a command on v5** — removed from the script block if it does not
- [ ] `/admin`, Pages list, and Page edit all render with no browser console errors
- [ ] A published draw page (`/megasena/resultado/2608`) returns 200 and renders styled
- [ ] Gate passes: `vendor/bin/pint --test && php artisan test && npm run build`
- [ ] Test count: 38 files, full suite green — **Phase 2 ends green**

**Tests**: feature · **Gate**: build
**Commit**: `chore(filament): drop upgrade tooling, verify v5 panel surfaces`

---

### T12: Bump Laravel to 13 and PHPUnit to ^11.5.50

**What**: Move `laravel/framework` to ^13.0 and `phpunit/phpunit` to ^11.5.50 (Laravel 13's floor; we are on 11.5.34).
**Where**: `composer.json`, `composer.lock`
**Depends on**: T11
**Reuses**: —
**Requirement**: P3.1, P3.4

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [ ] `composer show` reports `laravel/framework` ^13.x with `filament/filament` ^5.x and `z3d0x/filament-fabricator` ^4.1.x unchanged
- [ ] `phpunit/phpunit` at ^11.5.50+; `phpunit.xml` needs no schema change (staying on 11.x is deliberate — see spec Out of Scope)
- [ ] Domain commands behave as their tests assert — `app:scrape-draw`, `app:create-content`, `app:create-pages`
- [ ] Gate passes: `vendor/bin/pint --test && php artisan test && npm run build`
- [ ] Test count: 38 files, full suite green

**Tests**: unit + feature · **Gate**: build
**Commit**: `chore(deps)!: upgrade to laravel 13`

---

### T13: Bump the Sail runtime to PHP 8.5

**What**: Move Sail's build context and image from the 8.3 runtime to 8.5, then re-resolve dependencies on the new image.
**Where**: `docker-compose.yml`
**Depends on**: T12
**Reuses**: Existing Sail service definition (Postgres 17 service untouched)
**Requirement**: P3.2, P3.3

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [ ] Build context is `./vendor/laravel/sail/runtimes/8.5` and image is `sail-8.5/app`
- [ ] `composer update` resolves inside the 8.5 container with no platform conflict
- [ ] **Fallback honored**: if any transitive dependency blocks on 8.5, drop to the 8.4 runtime and record the blocking package in `context.md` D3 — do not force the resolution
- [ ] Full suite passes *inside the container*, not just on the host CLI
- [ ] Gate passes: `vendor/bin/pint --test && php artisan test && npm run build`
- [ ] Test count: 38 files, full suite green

**Tests**: none (infra config) · **Gate**: build
**Commit**: `chore(docker): move the sail runtime to php 8.5`

---

### T14: Align CI's PHP version with the runtime

**What**: Bump `php-version` in the CI workflow from 8.3 to match whatever T13 landed on.
**Where**: `.github/workflows/ci.yml`
**Depends on**: T13
**Reuses**: Existing workflow (Postgres 17 service, Pint + migrate + test steps unchanged)
**Requirement**: P3.2

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [ ] `shivammathur/setup-php` `php-version` matches the Sail runtime from T13 — parity is the requirement, mirroring the existing Postgres-parity note in the workflow
- [ ] Declared extensions still cover what the suite needs (`pdo_pgsql`, `mbstring`, `bcmath`, `intl`, `zip`)
- [ ] CI passes green on the branch
- [ ] Gate passes: `vendor/bin/pint --test && php artisan test && npm run build`
- [ ] Test count: 38 files, full suite green

**Tests**: none (infra config) · **Gate**: build
**Commit**: `ci: align the php version with the sail runtime`

---

### T15: Update CLAUDE.md to the new stack versions

**What**: Correct the Tech Stack section to state the shipped Laravel, Filament, and PHP versions.
**Where**: `CLAUDE.md`
**Depends on**: T14
**Reuses**: Existing Tech Stack section
**Requirement**: P3.6

**Tools**: MCP: NONE · Skill: NONE

**Done when**:

- [ ] Tech Stack reads Laravel 13, PHP 8.3+ (Sail image 8.5), Filament 5
- [ ] Fabricator reference updated to v4
- [ ] Only version facts changed — the stale "Sail provisions MySQL" line stays (it is wrong, but it is out of scope; flag it, don't fix it)
- [ ] Gate passes: `vendor/bin/pint --test && php artisan test && npm run build`
- [ ] Test count: 38 files, full suite green — **Phase 3 ends green**

**Tests**: none (docs) · **Gate**: build
**Commit**: `docs: update CLAUDE.md for laravel 13 + filament 5`

---

## Phase Execution Map

```
Phase 1 → Phase 2 → Phase 3

Phase 1:  T1 ──→ T2 ──→ T3 ──→ T4 ──→ T5 ──→ T6 ──→ T7
                          └─────┴──────┴──────┘
                          (T3,T4 chain; T5,T6 fan from T2; all converge on T7)

Phase 2:  T8 ──→ T9 ──→ T10 ──→ T11

Phase 3:  T12 ──→ T13 ──→ T14 ──→ T15
```

Execution is strictly sequential — there is no intra-phase parallelism.

---

## Task Granularity Check

| Task | Scope | Status |
| ---- | ----- | ------ |
| T1: Add Larastan | 1 manifest edit | ✅ Granular |
| T2: Bump + codemod | 1 dependency operation | ✅ Granular (indivisible — see deviation note) |
| T3: Re-derive PageResource | 1 file | ✅ Granular |
| T4: Port EditPage | 1 file | ✅ Granular |
| T5: Port DrawResource + 2 pages | 3 files, one cohesive resource | ⚠️ OK — cohesive |
| T6: Port 17 block/layout classes + 1 test | 18 files | ⚠️ OK — single mechanical rename across one uniform layer; splitting adds ceremony, not safety |
| T7: Reconcile v4 behaviors | 1 file + manifest | ✅ Granular |
| T8: Bump + codemod | 1 dependency operation | ✅ Granular (indivisible) |
| T9: Reconcile Livewire 4 | Cross-cutting fix pass | ⚠️ OK — scope bounded by T8's failure inventory |
| T10: Re-verify PageResource | 1 file | ✅ Granular |
| T11: Drop tooling + smoke | 1 manifest + verification | ✅ Granular |
| T12: Laravel 13 bump | 1 manifest edit | ✅ Granular |
| T13: Sail 8.5 | 1 file | ✅ Granular |
| T14: CI php-version | 1 file | ✅ Granular |
| T15: CLAUDE.md | 1 file | ✅ Granular |

No ❌ — nothing requires splitting.

---

## Diagram-Definition Cross-Check

| Task | Depends On (body) | Diagram Shows | Status |
| ---- | ----------------- | ------------- | ------ |
| T1 | None | (phase entry) | ✅ Match |
| T2 | T1 | T1 → T2 | ✅ Match |
| T3 | T2 | T2 → T3 | ✅ Match |
| T4 | T3 | T3 → T4 | ✅ Match |
| T5 | T2 | T2 → T5 | ✅ Match |
| T6 | T2 | T2 → T6 | ✅ Match |
| T7 | T3, T4, T5, T6 | T3/T4/T5/T6 → T7 | ✅ Match |
| T8 | T7 | T7 → T8 (phase boundary) | ✅ Match |
| T9 | T8 | T8 → T9 | ✅ Match |
| T10 | T9 | T9 → T10 | ✅ Match |
| T11 | T10 | T10 → T11 | ✅ Match |
| T12 | T11 | T11 → T12 (phase boundary) | ✅ Match |
| T13 | T12 | T12 → T13 | ✅ Match |
| T14 | T13 | T13 → T14 | ✅ Match |
| T15 | T14 | T14 → T15 | ✅ Match |

No task depends on a later phase. All dependencies point backward or within-phase. ✅

---

## Test Co-location Validation

| Task | Code Layer Created/Modified | Matrix Requires | Task Says | Status |
| ---- | --------------------------- | --------------- | --------- | ------ |
| T1 | Dependency manifest | none | none | ✅ OK |
| T2 | Dependency manifest + Filament (codemod) | none¹ | none | ✅ OK¹ |
| T3 | Filament Resource | feature | feature | ✅ OK |
| T4 | Filament Resource Page | feature | feature | ✅ OK |
| T5 | Filament Resource + Pages | feature | feature | ✅ OK |
| T6 | PageBlocks & Layouts | unit | unit | ✅ OK |
| T7 | Filament Resource + manifest | feature | feature | ✅ OK |
| T8 | Dependency manifest + Filament (codemod) | none¹ | none | ✅ OK¹ |
| T9 | Filament Resources + feature tests | feature | feature | ✅ OK |
| T10 | Filament Resource | feature | feature | ✅ OK |
| T11 | Manifest + smoke verification | feature | feature | ✅ OK |
| T12 | Dependency manifest (domain-wide impact) | unit + feature | unit + feature | ✅ OK |
| T13 | Infra config | none | none | ✅ OK |
| T14 | Infra config | none | none | ✅ OK |
| T15 | Docs | none | none | ✅ OK |

¹ **T2/T8 justification** — `Tests: none` here is NOT test deferral. These tasks author no behavior; they apply a vendor codemod. The behavior they disturb is covered by the *existing* suite, and that suite is re-asserted at full strength by the very next task in the same phase (T3 and T9). No code ships unverified, and no test is postponed to a later phase.

No ❌ VIOLATION.

---

## Batch Packing (for Execute)

15 tasks, packed on phase boundaries at ~7 tasks per batch:

| Batch | Phases | Tasks | Count |
| ----- | ------ | ----- | ----- |
| 1 | Phase 1 | T1–T7 | 7 |
| 2 | Phase 2 | T8–T11 | 4 |
| 3 | Phase 3 | T12–T15 | 4 |

This yields more than one batch, so sub-agent delegation must be **offered** before Execute — see the recommendation in chat.

After T15, a fresh Verifier runs automatically (author ≠ verifier) and writes `validation.md`.
