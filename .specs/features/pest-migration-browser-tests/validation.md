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
