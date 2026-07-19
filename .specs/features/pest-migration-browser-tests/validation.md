# Pest Migration & Browser Tests — Validation Notes

## T12 — Screenshot-on-failure verified empirically (PEST-12)

**Why**: pest-plugin-browser has an open upstream issue (pest#1543) reporting that
automatic failure screenshots are sometimes not generated even with `--headed`.
Design decided this must be verified empirically rather than assumed working.

**Method**:

1. Added a deliberately false assertion to `tests/Browser/SmokeTest.php`:
   `->assertSee('T12-deliberately-nonexistent-marker-lm3x9q')` after the existing
   `assertNoJavaScriptErrors()` call.
2. Cleared `tests/Browser/Screenshots/` (directory did not exist beforehand).
3. Ran `php artisan test --testsuite=Browser --filter=SmokeTest`.

**Result**: **PASS — screenshots ARE captured.**

- The test failed as expected, with the runner reporting:
  `A screenshot of the page has been saved to [Tests/Browser/Screenshots/the_homepage_loads_in_a_real_browser_with_no_javascript_errors]`.
- `tests/Browser/Screenshots/the_homepage_loads_in_a_real_browser_with_no_javascript_errors.png`
  was confirmed present on disk (11.0K PNG) and, on inspection, showed the actual
  failing page state (the app's "Hello world!" homepage) — a real capture, not an
  empty/corrupt file.
- The filename is derived from the failing test's description, matching the
  design's stated behavior ("named after the failing test").

**Cleanup**: the throwaway assertion was reverted immediately after confirming
the artifact (git diff on `tests/Browser/SmokeTest.php` showed zero changes
after revert); the full suite was re-run and confirmed green (212 passed, 874
assertions) before this task's commit. The throwaway break was never committed.

**Conclusion**: PEST-12 is satisfied on this environment/plugin version
(`pestphp/pest-plugin-browser` v4.3.1). pest#1543 does not reproduce here.

---

## Independent Verifier Pass — 2026-07-19

**Scope**: commits `5f451d7..HEAD` (`2318b4c` through `cd0ca93`, T8–T15). Author of
this section did not write the implementation; re-derived every claim from the
spec, design and diff rather than trusting tasks.md's checked boxes.

### Verdict: **PASS**, with one spec-precision gap noted below (non-blocking)

### Gates run directly (not taken on trust)

| Gate | Command | Result |
| ---- | ------- | ------ |
| Unit+Feature | `php artisan test --testsuite=Unit,Feature` | **198 passed, 854 assertions** — exact match to recorded baseline, no regression |
| Full suite (incl. Browser) | `php artisan test` | **212 passed, 874 assertions**, ~17-18s wall time — matches implementer's reported numbers exactly |
| Lint | `vendor/bin/pint --dirty` | Clean, 0 files touched |
| No class-based tests | `grep -rl "extends TestCase" tests/` | empty (exit 1) |
| No PHPUnit method syntax | `grep -rln "public function test_" tests/` | empty (exit 1) |
| CI run cited by T13 | `gh api repos/dberri/loterias_v2/actions/runs/29685939957` | **`conclusion: success`**, `head_branch: test/pest-migration-browser-tests`, `head_sha: b2c651e…` (matches T13's own commit), job log confirms it ran `php artisan test --testsuite=Unit,Feature`. Confirmed real and green, not just cited. |

### Per-AC evidence (re-derived from the diff, not from tasks.md's checkboxes)

- **PEST-05** (editor shows existing title) — `tests/Browser/AdminEditsPageTest.php:52` `->assertValue('[id="form.title"]', $originalTitle)`. Matches spec P1-AC3 exactly (value, not just visible text).
- **PEST-06** (edit + save + confirmation) — `AdminEditsPageTest.php:53-55` `->fill(...)->press('Save changes')->assertSee('Saved')`. Matches P1-AC4.
- **PEST-07** (public reflects edit; status intact) — `AdminEditsPageTest.php:64-68`: `assertSourceHas($newTitle)`, `assertSourceMissing($originalTitle)` (both asserted separately, per lesson L-005), and `expect($page->fresh()->status)->toBe(PageStatus::Published)`. **Gap**: spec's P1-AC5 text explicitly requires "the response SHALL be 200," and the Edge Cases section separately states "the test SHALL assert 200 explicitly rather than asserting on an error page's body." No `assertStatus(200)`/`assertOk()` call exists anywhere in `AdminEditsPageTest.php` or `DrawPageRendersBlocksTest.php` (confirmed via grep — zero matches). In practice a 404/error page would not contain the new title so `assertSourceHas` would still fail closed, but the explicit textual requirement (asserting the status code, not inferring it from content) was not honored literally. Minor — does not change the PASS verdict, but is a real spec-precision gap worth a follow-up task if this spec is revisited.
- **PEST-08** (all blocks render, named failure) — `DrawPageRendersBlocksTest.php:24-71`, one `assertSee()` per marker per block, 10 tests. Independently re-verified via discrimination sensor (below), not just re-run of the implementer's own claim.
- **PEST-09** (re-runnable, no manual cleanup) — verified directly: ran the full suite three times in this session (baseline, post-mutation, post-revert), all green with `RefreshDatabase` handling isolation; no manual DB state was touched between runs.
- **PEST-10** (auth boundary) — `AdminEditsPageTest.php:22-26` unauthenticated `visit('/admin/pages')` asserts `assertPathIs('/admin/login')` + `assertDontSee('Add to blocks')`; `:45-49` logs in through real `/admin/login` form, asserts `assertPathIs('/admin')`. No `actingAs()` anywhere in the file (confirmed by reading it in full) — the design's explicit prohibition is honored.
- **PEST-11** (no JS console errors) — `DrawPageRendersBlocksTest.php:74-77`, dedicated test calling `assertNoJavaScriptErrors()`.
- **PEST-12** (failure screenshot) — implementer's empirical check in validation.md above, **independently reproduced** by this Verifier via a different mutation (see Discrimination Sensor below): a real PNG landed at `tests/Browser/Screenshots/faq_renders_its_marker.png`, named for the failing test, confirming PEST-12 a second time on a different test/block.
- **PEST-13** (isolated suite, clear missing-browser guidance) — `phpunit.xml:14-16` adds a `Browser` testsuite; `php artisan test --testsuite=Unit,Feature` run directly, confirmed it does not execute or reference any Browser test. `CLAUDE.md:42-45` documents the Playwright install command a missing-browser failure would point a contributor to.
- **PEST-14** (docs) — `CLAUDE.md:30-47` re-read in full: the stale "PHPUnit, not Pest" claim is gone, replaced with accurate `--testsuite=Unit,Feature`/`--testsuite=Browser` commands and the Playwright install steps; notes local-only-by-design.
- **PEST-15** (CI excludes Browser) — `.github/workflows/ci.yml` runs `php artisan test --testsuite=Unit,Feature` with an explanatory comment; confirmed live via `gh api` above — real, green, on the right commit.

### Discrimination sensor (independent from the implementer's `hero-section` check)

Chose **`faq.blade.php`** — a different block than the one the implementer already emptied — specifically to get independent confirmation the tests actually discriminate rather than re-running the same check.

1. Backed up `resources/views/components/filament-fabricator/page-blocks/faq.blade.php`, then overwrote it with a single comment line (emptying its rendered output, including the unconditional `{{ $title }}` line that carries the marker).
2. Ran `php artisan test --testsuite=Browser`. Result: **`faq renders its marker`** failed — the *only* test to fail — naming exactly the mutated block: `Expected to see text [Marker-Faq-mypsBwOl]... but it was not found`. All other 10 block tests (`hero-section`, `results-grid`, `individual-draw-details`, `related-links`, `rich-text-content`, `statistics-cards`, `latest-results`, `number-generator`, `how-to-play`, no-JS-errors) plus both `AdminEditsPageTest` tests and the smoke test stayed green — 13 passed, 1 failed. A real screenshot (`tests/Browser/Screenshots/faq_renders_its_marker.png`) was written, giving independent, second-source confirmation of PEST-12.
3. Restored `faq.blade.php` from the backup. Verified `git diff` on the file was empty (0 lines) before proceeding.
4. Re-ran `php artisan test` (full suite): **212 passed, 874 assertions**, confirming full recovery.

Sensor confirms the block-rendering tests are load-bearing, not decorative, independent of the implementer's own `hero-section` check.

### `App\Models\User implements FilamentUser` — assessed as legitimate, not scope creep

Read `vendor/filament/filament/src/Http/Middleware/Authenticate.php:32-40` directly: when a User model does not implement `FilamentUser`, the panel's `Authenticate` middleware allows access **only** when `config('app.env') === 'local'`. `phpunit.xml:24` sets `APP_ENV=testing`, not `local` — so any test that drives the panel through its real HTTP middleware stack (which a browser test, by definition, must) would 403 without this fix, regardless of what this feature was trying to test. `tests/Feature/Filament/PageResourceTest.php` never hits this because it drives Livewire components directly, bypassing the panel's HTTP middleware entirely — confirmed by reading the vendor middleware source, not just accepting the commit message's claim. This is a genuine, pre-existing, environment-specific defect that block T10 outright; the fix (`canAccessPanel(): true`) is the minimal, standard remedy Filament's own interface doc prescribes. Judged in-scope and necessary, not scope creep.

### `AdminEditsPageTest` using a minimal blocks-empty page instead of `DrawPageFixture` — plausible, reasonable deviation

Did not reproduce the full ~3-minute wait (not required), but confirmed structurally: `app/Filament/Resources/PageResource.php:56` uses Filament-Fabricator's `PageBuilder::make('blocks')`, a Livewire-backed Builder field that instantiates a full sub-schema per configured block instance on page load. `DrawPageFixture` seeds all 10 implemented blocks, several with multiple fields (checkboxes, color pickers, category selects) — a Builder field mounting 10 such sub-schemas simultaneously is a plausible source of multi-minute editor load time, consistent with known Filament Builder-field performance characteristics. `AdminEditsPageTest.php`'s flow only needs one editable title field and a public URL, neither of which requires any blocks, so the deviation is well-scoped to what T10 actually tests — and `DrawPageFixture` is still the fixture `DrawPageRendersBlocksTest.php` (T11) genuinely needs. Reasonable, not a shortcut that weakens coverage.

### Summary

All 15 in-scope requirements (PEST-05 through PEST-15) have a real, spec-matching assertion, independently re-derived rather than taken from tasks.md's checked boxes. Both numeric gates (198/854 Unit+Feature, 212/874 full suite) reproduce exactly. The CI-green claim was independently confirmed live via `gh api`, not just cited. The discrimination sensor was re-run against a block the implementer had not already tested and passed. The one finding — `assertStatus(200)`/`assertOk()` never explicitly called despite the spec's literal text requiring it — is a real precision gap but does not create a false-positive risk in practice (content assertions fail closed against error pages) and does not change the overall verdict.

**Ranked gaps** (none blocking):
1. (Minor) PEST-07's explicit "response SHALL be 200" / edge-case "assert 200 explicitly" requirement has no literal `assertStatus(200)` in either browser test file — content assertions cover the same failure mode in practice but not to the letter of the spec.

---

## Gap closed — explicit 200 status assertion added (2026-07-19)

Closed the Verifier's one ranked gap. `pestphp/pest-plugin-browser` has no `assertStatus()`/`assertOk()` (confirmed by reading every `Concerns/*.php` trait under its `Api/` namespace — it drives a real browser, not an HTTP client, and its `goto()` wrapper discards the underlying Playwright response object entirely). Used the browser's own Navigation Timing API instead, which Chromium exposes synchronously and which reflects the real HTTP response status of the navigation:

```php
->assertScript('performance.getEntriesByType("navigation")[0].responseStatus', 200)
```

**Verified this actually discriminates**, not just trivially true: ran it against a real 404 (`/megasena/resultado/999999999`, a non-existent draw) in a throwaway scratch test — failed with `Expected JavaScript expression [...] to evaluate to 200 ... but got 404`, then deleted the scratch test (never committed).

Added to every public-page visit that previously lacked it:
- `tests/Browser/AdminEditsPageTest.php` — the post-edit public-page visit.
- `tests/Browser/DrawPageRendersBlocksTest.php` — all 11 tests (10 block tests + the no-JS-errors test).

Full suite re-run after the change: **212 passed, 886 assertions** (874 → 886, +12 — one new status assertion per updated test, no other change). `vendor/bin/pint --dirty` clean. Browser suite re-run twice consecutively, both green (PEST-09 unaffected).

PEST-07 and PEST-08 are now covered to the letter of the spec, not just in practical effect.
