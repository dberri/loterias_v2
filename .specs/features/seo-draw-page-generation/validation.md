# SEO Draw-Page Generation Validation

**Date**: 2026-07-15 (re-validation — second pass, after T18–T26 execution)
**Spec**: `.specs/features/seo-draw-page-generation/spec.md`
**Diff range**: `9430587..d129a91` (17 committed tasks) **+ uncommitted working-tree changes** (T18–T26 — see Process Note below)
**Verifier**: independent sub-agent (author ≠ verifier) — reviewed via `tlc-spec-driven` Execute/Validate flow

**Previous verdict** (first pass, same date): ❌ Not Ready — Phases 5–6 and T18 unimplemented, `ContentCreator` fatal-error trap live in production entry points.

---

## Process Note (read first)

**None of T18–T26's work is committed.** `git log` still ends at `d129a91` (T17); all of the new/changed code sits as uncommitted working-tree modifications (20 modified files, 10 new files/dirs). This violates the skill's own non-negotiable rule — "One atomic commit per task. Never batch tasks." Functionally the code is sound (see below), but it has no per-task commit boundaries, making it non-revertible task-by-task and leaving `tasks.md`'s checkboxes still all unchecked. **This must be corrected — split and commit the work (ideally one commit per T18–T26, matching the original plan) — before the feature is considered done**, independent of the functional verdict below.

---

## Task Completion

| Task | Status | Notes |
| ---- | ------ | ----- |
| T1–T17 | ✅ Done (committed) | Unchanged from first pass — see prior commits `53a5d60`..`d129a91` |
| T18 Implement `RelatedLinksBlock` | ✅ Done (uncommitted) | Full rewrite: prev/next/pillar/sibling links, gated on a `Published` page via the new `Draw::getDrawPageAttribute()` accessor. 4 tests, all spec-anchored; confirmed discriminating (see Sensor) |
| T19 Rewire `app:create-pages` | ✅ Done (uncommitted) | Now uses `ContentProviderManager`/`GenerationRequest`/`DrawPagePrompt`; creates `Generating` pages with `batch_id`/`provider`; no more `ContentCreator` reference |
| T20 Rewire `CheckCompletionBatch` | ✅ Done (uncommitted) | Uses `pollBatch`/`fetchResults`/`PageAssembler`. **The `expired`/`cancelled` bug is fixed** — both now call `markBatchFailed()`, confirmed by discrimination sensor |
| T21 Rewire `app:create-content` | ✅ Done (uncommitted) | Uses `generateOne()` + shared `PageAssembler`; feature test asserts byte-identical `blocks` to the batch path for the same fake response |
| T22 Retire `ContentCreator` | ⚠️ Partial | `app/Services/ContentCreator.php` still exists and still contains `use App\Models\DrawPage;` (a deleted class). **No longer live-wired** — `grep -rl ContentCreator app/ database/ routes/ tests/` now returns only the file itself, confirming `CreatePages`/`CreateContent`/`CheckCompletionBatch` no longer reference it. It's dead code, not a fatal-error trap anymore, but T22's own done-when ("file deleted", "grep returns nothing") is not met |
| T23 Public draw-page route + publish gate | ✅ Done (uncommitted) | `routes/web.php` adds `/{game}/resultado/{concurso}`, gated on `PageStatus::Published`; killed by discrimination sensor when the gate was removed |
| T24 Draw-page layout + block rendering | ⚠️ Partial | The Blade view (`resources/views/components/filament-fabricator/layouts/draw-page.blade.php`) exists and is tested/working. The PHP `Layouts/DrawPageLayout.php` class named in the task's "Where" was **not** created — only `PillarPageLayout.php` exists in `app/Filament/Fabricator/Layouts/`. Since Fabricator auto-discovers layouts from that directory and `'draw-page'` isn't registered there, `FilamentFabricator::getLayouts()` won't list it, so the admin edit screen's Layout select won't recognize the `'draw-page'` value stored on these pages (cosmetic/admin-UX gap only — the public route bypasses this mechanism entirely and is fully tested) |
| T25 `Article`/`FAQPage` JSON-LD | ✅ Done (uncommitted) | Built into the same Blade view from `Draw` facts + the `faq` block; omits `FAQPage` entirely when no faq items exist (tested both ways) |
| T26 Filament — status visibility + publish action | ✅ Done (uncommitted) | `PageResource` shows status/batch_id/provider/generated_at; `EditPage::publish()` gates on `PageStatus::Generated`, rejects `Failed`; confirmed by discrimination sensor |

**25 of 26 tasks functionally complete** (T22 partial — cleanup only, no live impact). Test count: 57 → **76** (+19 new tests, all feature-level: `Commands`, `Jobs`, `Filament`, `DrawPageRenderingTest`, plus `RelatedLinksBlockTest`).

---

## Spec-Anchored Acceptance Criteria

### P1: Batch-generated, fact-accurate draw page (MVP)

| Criterion (WHEN X THEN Y) | Spec-defined outcome | `file:line` + assertion | Result |
| -------------------------- | --------------------- | ------------------------ | ------ |
| AC1: `app:create-pages` selects, submits one batch, creates `Generating` pages | one `submitBatch()`, `Page` rows at `Generating` w/ `batch_id`/`provider` | `tests/Feature/Commands/CreatePagesTest.php:19-41` — asserts exact `customId` list, batch id, provider | ✅ PASS |
| AC2: valid response → assembled `blocks`, `Generated`/`Published` | spine+enrichment+related-links order; status per config | `tests/Unit/Services/PageAssemblerTest.php` (block order, auto-publish tests) + `tests/Feature/Jobs/CheckCompletionBatchTest.php` (job → assembler wiring) | ✅ PASS |
| AC3: invalid response → `Failed`, logged, re-runnable | no partial `blocks` | `PageAssemblerTest` (4 rejection causes) + `CreateContentTest.php:61-82` (re-run after failure) | ✅ PASS |
| AC4: anchored blocks resolve facts live, never from AI | every fact traces to `raw_data`; contradictory AI payload has no effect | `HeroSectionBlockTest`, `ResultsGridBlockTest`, `IndividualDrawDetailsBlockTest`, `RelatedLinksBlockTest` — all include a "contradictory/gated" assertion | ✅ PASS (all 4 anchored blocks now covered, including `related-links`) |
| AC5: `Published`→200, else→404 | route-level gate | `tests/Feature/DrawPageRenderingTest.php:14-31` — `test_public_route_requires_published_pages` (200 + 3× 404) | ✅ PASS — confirmed by discrimination sensor (mutant killed) |
| AC6: `Published` page emits `Article`+`FAQPage` JSON-LD | built from facts + `faq` block | `DrawPageRenderingTest.php` — "emits article and faq json ld when faq exists" / "omits...when no faq block exists" | ✅ PASS |
| AC7: `CheckCompletionBatch` polls/completes/routes through assembler | `in_progress`→redispatch (unchanged), `completed`→`fetchResults`+assembler | `tests/Feature/Jobs/CheckCompletionBatchTest.php` — all 4 status branches | ✅ PASS |

**Status**: ✅ All 7 P1 criteria pass with precise, spec-defined assertions. **The spec's own Independent Test scenario (seed draw → `app:create-pages` → dispatch `CheckCompletionBatch` → `Generated` → publish → `GET` 200 with drawn numbers in HTML) is now exercised end-to-end** across `CreatePagesTest`, `CheckCompletionBatchTest`, `PageResourceTest`, and `DrawPageRenderingTest`.

### P2: Synchronous single-draw regeneration

| Criterion | Evidence | Result |
| --------- | -------- | ------ |
| AC1: `generateOne()` + shared `PageAssembler`, no batch/queue | `CreateContentTest.php:21-59` — asserts `blocks` byte-identical to `(new PageAssembler)->assemble(...)` for the same fake response | ✅ PASS |
| AC2: sync failure parity with P1 AC3 | `CreateContentTest.php:61-82` | ✅ PASS |

**Status**: ✅ All covered.

### P3: Auto-publish flag

| Criterion | Evidence | Result |
| --------- | -------- | ------ |
| AC1: `auto_publish=false` → `Generated`, not publicly reachable | `PageAssemblerTest` (status) + `DrawPageRenderingTest` (404 for non-Published) together close the loop this pass left open last time | ✅ PASS |
| AC2: `auto_publish=true` → `Published` directly | `PageAssemblerTest` "auto publish false keeps generated and true publishes" | ✅ PASS |

**Status**: ✅ All covered — previously flagged as unreachable (no route existed); now closed.

---

## Discrimination Sensor

Ran 6 targeted mutations this pass — 4 on new T18–T26 code (highest risk: newly-introduced gates), plus re-confirmation on 2 from Phase 1–4 was not repeated (already confirmed first pass). All mutations applied in the real working tree (already uncommitted/dirty), tested, then reverted via backup copy — verified `git status`/`php artisan test` clean-green before and after.

| Mutation | File:line | Description | Killed? |
| -------- | --------- | ------------ | ------- |
| 1 | `routes/web.php` (`Page::query()->...->where('status', ...)`) | Removed the `PageStatus::Published` filter from the public route query | ✅ Killed — `DrawPageRenderingTest::test_public_route_requires_published_pages` failed (200 instead of 404) |
| 2 | `app/Jobs/CheckCompletionBatch.php:46` | Disabled the `Expired`/`Failed` branch (`if (false)` instead of the real condition) | ✅ Killed — both `expired` and `cancelled` `CheckCompletionBatchTest` cases failed (`Generating` instead of `Failed`) |
| 3 | `app/Filament/Resources/PageResource/Pages/EditPage.php:30` | Disabled the `status !== Generated` guard on `publish()` | ✅ Killed — `test_failed_page_cannot_be_published_via_the_action` failed (no validation error raised) |
| 4 | `app/Filament/Fabricator/PageBlocks/RelatedLinksBlock.php:63-65` | Removed the `Published`-page gate on `publishedDrawLink()` | ✅ Killed — 2 of 4 `RelatedLinksBlockTest` cases failed (one with a null-property fatal, confirming the gate is load-bearing, not just decorative) |

**Sensor depth**: lightweight (4 targeted mutations on the highest-risk new code — publish gate, route gate, batch-failure branch, related-links published-only guarantee)
**Result**: 4/4 killed this pass (6/6 cumulative across both validation passes) — ✅ PASS. Every gate this feature depends on for correctness (publish-status route gate, batch failure handling, Filament publish-action guard, related-links published-only linking) is empirically proven to be load-bearing, not decorative.

---

## Code Quality

| Principle | Status | Notes |
| --------- | ------ | ----- |
| Minimum code | ✅ | No speculative abstractions added |
| Surgical changes | ⚠️ | `app/Models/Draw.php` picked up several accessors beyond T5's scope this pass (`game`, `numbers`, `accumulated`, `estimatedPrize`, `winnersCount`, `prizeDistribution` — aliases of existing accessors, apparently for Blade convenience). Not harmful (all still derive from `raw_data`, consistent with AD-001) but not traceable to any task's done-when either — minor scope creep worth a follow-up trim |
| No scope creep | ⚠️ | See above; otherwise clean |
| Matches patterns | ✅ | — |
| Spec-anchored outcome check | ✅ | New tests assert exact statuses, exact block counts/positions, exact custom_id lists — not vague "an assertion exists" checks |
| Per-layer Coverage Expectation met | ✅ | Feature-test layer (commands/jobs/routes/Filament) now has real coverage — the gap flagged in the first pass is closed |
| Every test maps to a spec requirement | ✅ | No unclaimed tests found |
| Documented guidelines followed | ✅ | `CLAUDE.md`/`.github/copilot-instructions.md` conventions (Livewire tests, factories, feature-test bias) followed |

---

## Edge Cases (spec.md)

- [x] Batch `expired`/`cancelled` → all `Page` rows for that batch → `Failed`, logged — **now handled**, confirmed by discrimination sensor
- [x] Disallowed duplicate `enrichment_blocks` type → invalid response — handled (confirmed first pass)
- [x] No `faq` block → `FAQPage` JSON-LD omitted — handled, tested both ways
- [x] `quantity` > available draws → processes only what exists, no error — `CreatePagesTest::test_create_pages_skips_draws_that_already_have_pages_and_limits_to_available_draws`
- [x] Existing `Page` (any status) excluded from `scopeWithoutPage()` — handled (confirmed first pass) + re-confirmed via the same test above

All 5 spec.md edge cases now handled and tested.

---

## Gate Check

- **Gate command**: `php artisan test`
- **Result**: 76 passed (366 assertions), 0 failed, 0 skipped — exit code 0
- **Test count before this pass**: 57 (T1–T17 baseline)
- **Test count after this pass**: 76
- **Delta**: +19 new tests — `tests/Feature/Commands/{CreatePagesTest,CreateContentTest}.php`, `tests/Feature/Jobs/CheckCompletionBatchTest.php`, `tests/Feature/Filament/PageResourceTest.php`, `tests/Feature/DrawPageRenderingTest.php`, `tests/Unit/PageBlocks/RelatedLinksBlockTest.php`
- **Skipped tests**: none
- **Failures**: none

---

## Fix Plans

### Fix 1 (Process — do before calling this done): Commit the work

- **Root cause**: T18–T26 were implemented but never committed; all sit as working-tree diffs against `d129a91`.
- **Fix task**: Split the current diff into per-task commits matching `tasks.md`'s intended commit messages (`feat(blocks): implement RelatedLinksBlock internal linking`, `refactor(commands): rewire app:create-pages onto BatchContentProvider`, etc.) and commit them in dependency order (T18→T19→...→T26).
- **Priority**: Blocker for calling Execute "done" per the skill's own rules — functionally the code is fine, but there's currently no atomic, revertible history for any of this work.

### Fix 2 (Minor): Finish `ContentCreator` retirement (T22)

- **Root cause**: `app/Services/ContentCreator.php` was never deleted, despite no longer being referenced anywhere live.
- **Fix task**: Delete the file; confirm `grep -r ContentCreator app/ database/ routes/ tests/` returns nothing; re-run the full suite to confirm no hidden dependency.
- **Priority**: Minor — it's inert dead code today (previous pass's fatal-error risk is gone since nothing calls it), but leaving a class that references an already-deleted model is exactly the kind of trap the previous validation pass warned about.

### Fix 3 (Minor): Register a `DrawPageLayout` class (T24)

- **Root cause**: Task named `app/Filament/Fabricator/Layouts/DrawPageLayout.php` in its "Where"; only the Blade view was created.
- **Fix task**: Add a minimal `DrawPageLayout extends Layout` class (mirroring `PillarPageLayout.php`) so `'draw-page'` appears in `FilamentFabricator::getLayouts()` and the admin Layout select recognizes the value already stored on every generated page.
- **Priority**: Minor — cosmetic/admin-UX only; the public route and rendering path don't use this mechanism and are fully tested.

### Fix 4 (Minor): Trim unscoped `Draw` accessor aliases

- **Root cause**: `game`, `numbers`, `accumulated`, `estimatedPrize`, `winnersCount`, `prizeDistribution` accessors were added to `Draw.php` without a corresponding task/test requiring them.
- **Fix task**: Confirm which (if any) are actually consumed by the new Blade views/blocks; delete the rest, or backfill a task/test justifying each one that's kept.
- **Priority**: Minor — not incorrect, just untraceable scope creep worth a cleanup pass.

---

## Requirement Traceability Update

| Requirement | Previous Status | New Status |
| ----------- | ---------------- | ----------- |
| DRAWPAGE-01 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-02 | ⚠️ Partial | ✅ Verified |
| DRAWPAGE-03 | ⚠️ Partial | ✅ Verified |
| DRAWPAGE-04 | ⚠️ Partial | ✅ Verified |
| DRAWPAGE-05 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-06 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-07 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-08 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-09 | ✅ Verified | ✅ Verified |
| DRAWPAGE-10 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-11 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-12 | ⚠️ Partial | ✅ Verified |
| DRAWPAGE-13 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-14 | ❌ Needs Fix | ✅ Verified |
| DRAWPAGE-15 | ⚠️ Partial | ✅ Verified |
| DRAWPAGE-16 | ✅ Verified | ✅ Verified |

**16 of 16 requirements verified.**

---

## Summary

**Overall**: ⚠️ Issues (functionally ready; two Minor cleanup items + one Process blocker before calling Execute complete)

**Spec-anchored check**: 16/16 requirements verified with precise, spec-defined assertions
**Sensor**: 4/4 new mutations killed this pass (6/6 cumulative) — every gate is empirically load-bearing
**Gate**: 76 passed, 0 failed

**What works**: All 16 spec requirements and all 5 documented edge cases now pass with spec-precise tests. The P1 MVP's own Independent Test scenario is fully exercised end-to-end. The batch-level `expired`/`cancelled` bug the spec explicitly called out to fix is fixed and verified.

**Issues found**:
1. **Process (must fix before "done")** — none of T18–T26 is committed; needs to be split into atomic per-task commits.
2. **Minor** — `ContentCreator.php` (T22) still exists as dead code referencing a deleted class; delete it.
3. **Minor** — `DrawPageLayout.php` (T24) was never created; admin Layout dropdown won't recognize `'draw-page'`.
4. **Minor** — a handful of unscoped `Draw` accessor aliases were added without a task/test trail; confirm usage and trim unused ones.

**Next steps**: Commit T18–T26 as atomic per-task commits (Fix 1), then apply Fixes 2–4 as a small follow-up cleanup task, then re-run `vendor/bin/pint --dirty` and the full suite once more before considering the feature closed.
