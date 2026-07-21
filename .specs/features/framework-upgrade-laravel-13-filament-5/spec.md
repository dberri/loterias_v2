# Framework Upgrade: Laravel 13 + Filament 5 Specification

**Scope**: Dependency upgrade only — `laravel/framework` 12→13, `filament/filament` 3→5, `z3d0x/filament-fabricator` 2.6→4.1, plus the PHP/toolchain floor those require.
**Decisions**: `context.md` (sequencing, `PageResource` strategy, PHP target)

## Problem Statement

The app runs Laravel 12.25 and Filament 3.3.37. Laravel 13 shipped 2026-03-17 and Filament 5 is current; staying put means falling off the security-fix window (Laravel 12 security patches end Q1 2027) and accumulating an upgrade debt that compounds with every Filament major. Filament in particular is now two majors behind, and the v3→v4 hop is where nearly all the breaking changes live — the longer it waits, the more local code is written against an API that no longer exists upstream.

The upgrade is not optional-shaped work: `z3d0x/filament-fabricator` — which owns the `pages` table, the public routing, and the admin page builder that the entire content pipeline depends on — has **no release supporting Laravel 13 on Filament 3**. Its 2.7.0 caps at Laravel 12; its 3.1.0 requires Filament 4. Laravel 13 is therefore gated behind the Filament upgrade, and the two cannot be decoupled.

## Goals

- [ ] Run Laravel 13.x, Filament 5.x, and `filament-fabricator` 4.1.x with a fully green test suite
- [ ] Preserve every admin-panel and public-site behavior currently asserted by tests — this upgrade changes versions, not features
- [ ] Reduce the local fork of fabricator's `PageResource` to only this app's intentional additions (per `context.md` D2)
- [ ] Close the local(8.5)/container(8.3) PHP mismatch
- [ ] Leave each phase independently green, committed, and revertable

## Non-Goals / Out of Scope

Explicitly excluded. Documented to prevent scope creep during a change that touches nearly every Filament file.

| Excluded | Reason |
| -------- | ------ |
| Any new feature, block, page, or admin capability | This is a version upgrade. New behavior gets its own spec |
| Adopting Filament 5 additions (Blueprint AI tool, new component types) | Opportunity, not a requirement. Separate spec once we're on v5 |
| Finishing the parked/commented-out PageBlocks (`StatisticsCardsBlock`, `SimulationBlock`, `NumberGeneratorBlock`, `TimelineBlock`, `ComparisonTableBlock`, `LatestResultsBlock`) | Pre-existing state per `CLAUDE.md`. They must *compile and register* after the upgrade; making them *work* is `seo-draw-page-generation` follow-up scope |
| Fixing `HeroSectionBlock::mutateData()`'s reference to the non-existent `Draw::drawPage` relation | Pre-existing bug, unrelated to the upgrade. Port it as-is; do not "fix while we're in there" |
| Bumping PHPUnit past 11.x, or migrating to Pest | Laravel 13 accepts `^11.5.50`. A test-framework major on top of a framework major is avoidable risk. Optional follow-up |
| `openai-php/laravel` 0.15 → 0.20 | Independent of this upgrade; unconstrained by Laravel 13. Separate bump |
| Removing the unused `pestphp/pest-plugin` entry from `allow-plugins` | Cosmetic. Do not bundle |
| Migrating away from the `filament-fabricator` dependency | Strategic question, not an upgrade question |

---

## Assumptions & Open Questions

| Assumption | Chosen default | Rationale | Confirmed? |
| ---------- | -------------- | --------- | ---------- |
| The `filament-v4` script reaches our code but not all of it | Run it, then manually reconcile; treat script output as a starting diff to review, never as authoritative | Filament's own v4 guide states the script "handles many small changes" but "explicitly doesn't cover all breaking changes" | y — vendor-documented |
| `filament/upgrade` ships one script per major, so v3→v5 is two tool installs | Install `filament/upgrade:^4.0` → run `vendor/bin/filament-v4`; later install `^5.0` → run `vendor/bin/filament-v5` | Verified on Packagist: v4.12.1 ships `bin/filament-v4`, v5.7.1 ships only `bin/filament-v5`. There is no single v3→v5 script | y — verified against registry |
| The `filament-v4` script requires a static analyser | PHPStan v2+ / Larastan v3+ must be installed as a dev dependency for the duration of Phase 1, then removed | Filament v4 guide states the script requires it. Neither is currently in `composer.json` | y — vendor-documented |
| No custom Filament theme migration is needed | Skip the Tailwind v3→v4 theme step entirely | `resources/css/app.css` is 23 bytes (`@import 'tailwindcss';`), `package.json` already pins `tailwindcss ^4.1.12`, and `AdminPanelProvider` registers no `viteTheme()`. The v4 guide's heaviest manual step does not apply here | y — verified in codebase |
| No custom Livewire components need porting to Livewire 4 | Livewire 4 arrives transitively via Filament 5; no app-owned component migration | `grep -rn "Livewire" app/ resources/views/` returns no app-owned components. Livewire appears only as Filament's dependency and in `tests/Feature/Filament/PageResourceTest.php` as `Livewire::test()` | y — verified in codebase |
| Fabricator's own breaking changes across 2→4 are undocumented | Read the vendor diff (`vendor/z3d0x/filament-fabricator`) after each bump rather than relying on release notes | Its v3.0.0/v4.0.0 release notes say only "Migrate to Filament v4" / "Filament v5 Compatibility" with no breaking-change list. PRs #237 and #260 are the actual record | y — verified; drives the D2 re-derivation approach |
| `Draw`/`Page` models, services, jobs, and commands are upgrade-neutral | Expect no changes outside `app/Filament/**`, `composer.json`, `docker-compose.yml`, and test files | These layers use Eloquent and Laravel APIs that Laravel 13 did not break (zero-breaking-change release). Filament imports are confined to `app/Filament/**` | n — verify by test run; a failure here is a finding, not a silent fix |

**Open questions**: none — resolved above or in `context.md`.

---

## Current-State Inventory

Grounding facts established during Specify. Use these rather than re-deriving.

| Fact | Value |
| ---- | ----- |
| Filament surface | 2 Resources (`DrawResource`, `PageResource`), 2 Fabricator Layouts, 15 PageBlocks, 3 Resource Pages |
| Filament imports needing namespace rewrites | `Forms\Form`, `Infolists\Infolist`, `Forms\Get`/`Set`, `Forms\Components\{Section,Group,Grid}`, `Tables\Actions\*`, `Pages\Actions\Action` |
| Highest-risk file | `app/Filament/Resources/PageResource.php` — a fork of the vendor resource (see `context.md` D2) |
| Second-highest-risk file | `app/Filament/Resources/PageResource/Pages/EditPage.php` — extends the vendor `EditPage` and overrides `getActions()` |
| Test suite | 37 test files; `tests/Feature/Filament/PageResourceTest.php` is the admin-behavior gate |
| Frontend | Tailwind 4 already in place; no custom Filament theme |
| Container | Sail runtime PHP 8.3, Postgres 17 (note: `CLAUDE.md` says MySQL — stale, out of scope) |

**Target versions**: `laravel/framework` ^13.0 · `filament/filament` ^5.0 · `z3d0x/filament-fabricator` ^4.1 · `php` ^8.3 (Sail runtime 8.5) · `phpunit/phpunit` ^11.5.50

---

## User Stories

### P1: Filament 3 → 4 on Laravel 12 ⭐ the heavy lift

**User Story**: As the maintainer, I want the admin panel running on Filament 4 with every current behavior intact, so that the remaining hops are mechanical.

**Why P1**: Effectively all breaking changes in this upgrade live in this hop. It is also the phase that unblocks Laravel 13, since fabricator 3.1.0 is the first release supporting Laravel 13 — and it requires Filament 4.

**Acceptance Criteria**:

1. WHEN `composer show` runs after this phase THEN the system SHALL report `filament/filament` at ^4.x and `z3d0x/filament-fabricator` at ^3.1, with `laravel/framework` still at ^12.x and `php` still `^8.2`.
2. WHEN the `filament-v4` script has been run and its diff reconciled THEN the codebase SHALL contain no import of a Filament v3 namespace that v4 removed — specifically none of `Filament\Forms\Form`, `Filament\Infolists\Infolist`, `Filament\Forms\{Get,Set}`, `Filament\Forms\Components\{Section,Group,Grid}`, `Filament\Tables\Actions\*`, or `Filament\Pages\Actions\*`.
3. WHEN `app/Filament/Resources/PageResource.php` is inspected after this phase THEN it SHALL be derived from the vendor `PageResource` shipped in the installed fabricator version, carrying only this app's additions: the Generation section (status, batch_id, provider, generated_at placeholders), the status/batch_id/provider/generated_at table columns, the status and layout filters, and the `visit` action.
4. WHEN `php artisan test` runs THEN the full suite SHALL pass with no test weakened, skipped, or deleted, and `tests/Feature/Filament/PageResourceTest.php` SHALL pass unmodified except for framework-mandated API renames.
5. WHEN the `publish` action is invoked on a `Generated` page THEN the page SHALL transition to `Published` and its public route SHALL return 200; WHEN invoked on a `Failed` page THEN it SHALL raise a validation error on `status` and leave the page `Failed` — i.e. the `getActions()` → v4 header-action rename SHALL NOT change the action's behavior or its confirmation requirement.
6. WHEN the layout dropdown is populated in the admin THEN `FilamentFabricator::getLayouts()` SHALL still contain `draw-page`, and all 15 PageBlock classes SHALL load without error (including the parked ones, which need only to register, not to function).
7. WHEN this phase completes THEN PHPStan/Larastan SHALL have been removed from `composer.json` if it was added solely to satisfy the upgrade script.

**Explicit v4 behavior changes to reconcile** (from the vendor guide — each is a decision point, not an automatic accept):

| Change | Required response |
| ------ | ----------------- |
| `Grid`/`Section`/`Fieldset` no longer span full width by default | Audit `PageResource`'s `Group`/`Section` layout; apply `columnSpanFull()` where v3 width is intended |
| `columnSpan()` now targets `>= lg` by default | Verify the existing `columnSpan(2)` / `columnSpan(1)` split still renders as a 2:1 sidebar layout |
| Table filters are now deferred (require a click) | Accept the new default, or call `deferFilters(false)` to preserve current UX — decide explicitly |
| `unique()` `ignoreRecord` now defaults `true` | The slug rule already passes `ignoreRecord: true` explicitly; confirm the `modifyRuleUsing` parent-scoping still applies |
| `make()` signatures changed | Any custom `make()` override moves to `getDefaultName()` / `setUp()` |
| File visibility defaults to private on non-local disks | Audit the `FileUpload` usage in the PageBlocks |

---

### P2: Filament 4 → 5

**User Story**: As the maintainer, I want to land on Filament 5 and Livewire 4, so that the panel is on the current major and fabricator can reach its Laravel 13-capable release.

**Why P2**: Small by design — v5 is functionally identical to v4 apart from Livewire 4 support. It is separated from P1 so that any regression here is unambiguously attributable to Livewire 4 rather than to the v4 API migration.

**Acceptance Criteria**:

1. WHEN `composer show` runs after this phase THEN the system SHALL report `filament/filament` at ^5.x, `z3d0x/filament-fabricator` at ^4.1.x, and `livewire/livewire` at ^4.x.
2. WHEN `composer.json` is inspected THEN the `php` constraint SHALL be `^8.3` — required by fabricator 4.1.0 — and `laravel/framework` SHALL still be ^12.x.
3. WHEN `php artisan test` runs THEN the full suite SHALL pass, with `Livewire::test()`-based assertions in `tests/Feature/Filament/PageResourceTest.php` still exercising the real components under Livewire 4.
4. WHEN the admin panel is loaded in a browser THEN `/admin`, the Pages list, and the Page edit screen SHALL render without console errors, and the page-builder block editor SHALL still add, reorder, and persist blocks.
5. WHEN `npm run build` runs THEN the frontend build SHALL succeed and the public draw page SHALL render with styling intact.
6. WHEN the `filament/upgrade` dev dependency is no longer needed THEN it SHALL be removed from `composer.json`.

---

### P3: Laravel 12 → 13 + PHP 8.5

**User Story**: As the maintainer, I want the app on Laravel 13 and the container on PHP 8.5, so that we are inside the current support window and the local and container runtimes match.

**Why P3**: Laravel 13 shipped with zero breaking changes, so this is the lowest-risk hop and belongs last, where a failure is cleanly attributable to the framework bump or the PHP bump rather than to Filament.

**Acceptance Criteria**:

1. WHEN `composer show` runs after this phase THEN the system SHALL report `laravel/framework` at ^13.x, with `filament/filament` ^5.x and `z3d0x/filament-fabricator` ^4.1.x unchanged from P2.
2. WHEN `docker-compose.yml` is inspected THEN the Sail build context and image SHALL reference the 8.5 runtime, and `composer.json` SHALL require `php: ^8.3`.
3. WHEN `composer update` runs against the PHP 8.5 image THEN it SHALL resolve with no platform conflict; IF any transitive dependency blocks on 8.5 THEN the runtime SHALL fall back to 8.4 and the blocking package SHALL be recorded in `context.md` D3.
4. WHEN `php artisan test` runs inside the 8.5 container THEN the full suite SHALL pass with `phpunit/phpunit` at ^11.5.50 or higher.
5. WHEN the domain commands run against fixture data — `app:scrape-draw`, `app:create-content`, `app:create-pages` — THEN each SHALL behave as its existing tests assert, confirming the scraper/OpenAI/queue layers are unaffected by the framework bump.
6. WHEN this phase completes THEN `CLAUDE.md` SHALL be updated to state the new Laravel, Filament, and PHP versions.

---

## Verification Gates

Every phase shares the same gate. A phase is done only when all four pass — self-assessment does not close a phase.

| Gate | Command / check |
| ---- | --------------- |
| Automated suite | `php artisan test` — full suite green, nothing skipped or weakened |
| Admin smoke | `/admin` loads; Pages list renders; Page edit renders; block editor adds/reorders/persists; `publish` promotes a `Generated` page |
| Public smoke | A published draw page (`/megasena/resultado/2608`) returns 200 and renders styled |
| Build + style | `npm run build` succeeds; `vendor/bin/pint --dirty` clean |

**Commit discipline**: one atomic commit per task, never batched across phases. Each phase ends on a commit whose tree is independently green, so any phase can be reverted without unwinding the others.

**Rollback**: `composer.lock` is committed. Reverting a phase's commits and running `composer install` restores the previous dependency tree exactly.

---

## Traceability

| Requirement | Verified by |
| ----------- | ----------- |
| P1.1, P2.1, P2.2, P3.1, P3.2 | `composer show` / `composer.json` / `docker-compose.yml` inspection |
| P1.2 | Repo-wide grep for removed v3 namespaces |
| P1.3 | Diff of `PageResource.php` against the installed vendor `PageResource` |
| P1.4, P2.3, P3.4 | `php artisan test` |
| P1.5 | `tests/Feature/Filament/PageResourceTest.php` — publish/failed-publish cases |
| P1.6 | `PageResourceTest::test_draw_page_layout_is_registered_for_the_admin_dropdown` + block class-load check |
| P2.4, P2.5 | Manual admin + public smoke; `npm run build` |
| P3.3 | `composer update` on the 8.5 image |
| P3.5 | `tests/Feature/Commands/*`, `tests/Feature/Services/ScraperTest.php`, `tests/Feature/Jobs/*` |
| P3.6 | `CLAUDE.md` diff |
