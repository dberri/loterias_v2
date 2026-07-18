# Infrastructure: Laravel Cloud, Postgres, Backups — Validation

**Date**: 2026-07-18
**Spec**: `.specs/features/infrastructure-cloud-postgres-backups/spec.md`
**Diff range**: `b5f2924..HEAD` (17 commits)
**Verifier**: independent sub-agent (author ≠ verifier), evidence-or-zero
**Environment**: PostgreSQL 17.10 via `docker compose up -d pgsql` (local port 5433)

**Verdict**: ⚠️ **FAIL — 3 in-scope requirements not fully met.**

The verdict is *not* a criticism of test quality. The discrimination sensor killed
**10 of 10** injected faults, including every mutation the orchestrator singled out as
high-risk. The failure is one of **completeness**: three in-scope requirements have
acceptance criteria that no evidence discharges, two of them stated explicitly in the
tasks' own "Done when" lists.

---

## Scope

**In scope**: T1–T10, T12–T18 → INFRA-06/07/08/09/10/12/13/14/15/17/18/19/21, INFRA-22 (open).

**Deferred — operator-gated, NOT failures**: T11, T19, T20, T21 → INFRA-01, INFRA-02,
INFRA-03, INFRA-04, INFRA-05, INFRA-11, INFRA-16, INFRA-20. Laravel Cloud is not
provisioned; these requirements are expected to be unmet and are excluded from the verdict.

---

## Task Completion

| Task | Status | Notes |
| ---- | ------ | ----- |
| T1 `AlertNotifier` | ✅ Done | 8 unit tests, all Done-when criteria discharged |
| T2 `NulSafeJson` | ✅ Done | 12 unit tests; parity vs legacy `array` cast proven |
| T3 Dialect audit | ✅ Done | `dialect-audit.md`, grep sweep recorded |
| T4 Sail → Postgres | ✅ Done | `postgres:17` container healthy, verified running |
| T5 Default connection | ⚠️ Partial | `default => pgsql` ✅; retirement incomplete → INFRA-22 |
| T6 Suite → Postgres | ✅ Done | `JsonRoundTripTest.php:46` asserts `pgsql` driver |
| T7 CI workflow | ❌ **Unmet** | Workflow has never executed — see Gap 1 |
| T8 Migrations green | ✅ Done | Evidence-only commit; legitimate (see Claims) |
| T9 JSON round-trip | ✅ Done | 15 tests; also fixed a real `Scraper` bug |
| T10 Cutover command | ✅ Done | 8 tests; tolerance function is genuinely independent |
| T12 Export draws | ✅ Done | 5 tests |
| T13 Export pages + edges | ✅ Done | 7 tests; both edge cases discriminate |
| T14 Manifest + self-verify | ✅ Done | 9 tests; read-back verification proven load-bearing |
| T15 Alerting | ✅ Done | 6 tests |
| T16 Schedule | ✅ Done | 2 tests; ⚠️ spec-precision gap on cron expression |
| T17 Retention | ⚠️ Partial | Monthly tier unreachable — see Gap 3 |
| T18 Restore command | ⚠️ Partial | Reconstruction ✅; render assertion missing — see Gap 2 |

---

## Spec-Anchored Acceptance Criteria

| Requirement / AC | Spec-defined outcome | `file:line` + assertion | Result |
| ---------------- | -------------------- | ----------------------- | ------ |
| **INFRA-06** (P2.1) CI runs suite on Postgres | Suite runs against PostgreSQL in CI | `.github/workflows/ci.yml:16` `image: postgres:17`; `tests/Feature/Database/JsonRoundTripTest.php:46` — `assertSame('pgsql', DB::connection()->getDriverName())` | ⚠️ **PARTIAL** — locally proven; CI has **never executed** (`gh run list` → `[]`, commits unpushed) |
| **INFRA-07** (P2.2) Sail provisions Postgres | Local Sail brings up Postgres, not MySQL | `docker-compose.yml` `pgsql` service; `config/database.php:19` — `'default' => env('DB_CONNECTION', 'pgsql')`; container `loterias_v2-pgsql-1 postgres:17 (healthy)` verified running | ✅ PASS |
| **INFRA-08** (P2.3) All migrations on clean PG | `migrate:fresh` succeeds, zero dialect errors | `dialect-audit.md` T8 evidence block; full suite green on PG (157 tests) | ✅ PASS (caveat: backfill evidence is a one-time manual run, not a repeatable test — see Gap 4) |
| **INFRA-09** (P2.4) JSON paths identical | Every `Draw` accessor returns pre-migration values | `tests/Unit/Casts/NulSafeJsonTest.php:57-71` — parity loop over 11 accessors × 5 real fixtures vs legacy `'array'` cast; `JsonRoundTripTest.php:66` | ✅ PASS |
| **INFRA-10** (P2.5) Engine-specific SQL audited | Each finding replaced or documented as intentional | `dialect-audit.md` (grep sweep recorded); `RestoreCorpus.php:187-197` documents `setval` as intentionally PG-specific | ✅ PASS |
| **INFRA-12** (P3.1) Nightly export + manifest | Export writes both tables + manifest w/ timestamp, counts, checksums | `ExportCorpusTest.php:28`; `ExportCorpusManifestTest.php:45,58,86,101`; `ExportCorpusScheduleTest.php:19` | ✅ PASS (⚠️ cron expression is a spec-precision gap — Gap 5) |
| **INFRA-13** (P3.2) Checksum validation; invalid → alert | Mismatch ⇒ export **failed** + alerts | `ExportCorpusManifestTest.php:135,154`; `ExportCorpusAlertingTest.php:37` — `Mail::assertSent(OperatorAlert::class)` | ✅ PASS |
| **INFRA-14** (P3.3) Export failure alerts | Failure alerts; never exits zero silently | `ExportCorpusAlertingTest.php:47` — `assertTrue($threw, 'The export swallowed…')` + `Mail::assertSent`; `:78` failed_jobs; `:97` dedup | ✅ PASS |
| **INFRA-15** (P3.4) Restore reconstructs both tables **and sampled published pages render** | Both tables reconstructed **and** sampled `Published` pages **render correctly** | Reconstruction: `RestoreCorpusTest.php:33,48,66,160`. **Rendering: no evidence — no `assertOk()`/HTTP assertion exists in the file** | ⚠️ **PARTIAL** — second half of the AC uncovered (Gap 2) |
| **INFRA-17** (P3.6) 35 daily / 12 monthly | Daily 35d **and** monthly 12mo retained | `docs/infrastructure/backup-retention.md` daily rule ✅; monthly rule matches **nothing** — `ExportCorpus.php:64` writes only `exports/{Y-m-d}/` | ⚠️ **PARTIAL** — monthly tier unreachable (Gap 3) |
| **INFRA-18** (edge) Export before `pages` exists | Exports draws alone, succeeds | `ExportCorpusPagesTest.php:73` — `assertExists(draws)` + `assertMissing(pages)`; `ExportCorpusManifestTest.php:116` | ✅ PASS |
| **INFRA-19** (edge) Empty `pages` → valid zero-row artifact | Present, well-formed, truthful count 0 — not skipped | `ExportCorpusPagesTest.php:89` — `assertExists()` + `assertSame('', …)` + `assertCount(0, …)`; `ExportCorpusManifestTest.php:76` | ✅ PASS |
| **INFRA-21** (P2.7/P2.8) NUL bytes readable at SQL level | Stored so it stays **readable at SQL level**; accessors unchanged; covers future scrapes | `JsonRoundTripTest.php:102` — `expectException(QueryException)` on `->>` for a NUL written *around* the cast; `:125` — `assertSame('', $row->v)` for one written *through* it; `:136` — newly-scraped NUL payload persists; `NulSafeJsonTest.php:83` — field not dropped | ✅ **PASS — strongest area in the feature** |
| **INFRA-22** (open) Retired engines unreachable | Not satisfiable by app config alone (framework merge) | `.specs/STATE.md:65` AD-008 amendment; no test, no task | ⚠️ **OPEN** — correctly documented, unmapped to any task |

**Status**: 9 PASS · 3 PARTIAL · 1 OPEN · 1 spec-precision gap

---

## Discrimination Sensor

**Depth**: P0-full (data integrity — the corpus is the project's least reproducible asset).
All mutations were injected in the working tree, tested, then reverted with `git checkout`.

| # | File:line | Mutation | Killed? |
| - | --------- | -------- | ------- |
| 1 | `app/Casts/NulSafeJson.php:43` | `set()` no longer strips NUL (`json_encode($value)` raw) | ✅ Killed — 3 tests |
| 2 | `app/Casts/NulSafeJson.php:62` | Cast **mangles**: drops any key whose value empties after stripping | ✅ Killed — 3 tests, incl. `test_nome_time_coracao_mes_sorte_survives…:90` and `…differs_only_by_the_stripped_nul:111` |
| 3 | `app/Console/Commands/CutoverDraws.php:110` | **Tolerance accepts arbitrary value differences** — compares top-level key *count* instead of deep equality | ✅ Killed — 3 tests, incl. `test_validation_fails_when_a_sampled_payload_value_was_corrupted` |
| 4 | `app/Console/Commands/CutoverDraws.php:95` | Sample `limit(0)` — deep comparison never executes | ✅ Killed — 4 tests |
| 5 | `app/Jobs/ExportCorpus.php:124` | `verifyArtifacts()` no-op — trusts the manifest instead of reading back from disk | ✅ Killed — 2 tests (T14) |
| 6 | `app/Jobs/ExportCorpus.php:53` | `AlertNotifier::notify()` removed from the failure path | ✅ Killed — 3 tests (T15) |
| 7 | `app/Console/Commands/RestoreCorpus.php:99` | `resetSequences()` call removed | ✅ Killed — `test_new_rows_can_still_be_written_after_a_restore` |
| 8 | `app/Jobs/ExportCorpus.php:83` | Empty `pages` table **skips** the artifact entirely | ✅ Killed — 3 tests (INFRA-19 discriminates present-empty vs missing) |
| 9 | `app/Jobs/ExportCorpus.php:167` | `pagesTableExists()` always `true` — absent-table guard removed | ✅ Killed — 2 tests (INFRA-18) |
| 10 | `app/Jobs/ExportCorpus.php:196` | Manifest row count hard-coded to `0` | ✅ Killed — 5 tests across export **and** restore |
| 11 | `app/Console/Commands/RestoreCorpus.php:67` | Pre-import checksum gate disabled | ✅ Killed — `test_it_refuses_a_corrupt_artifact_without_importing_anything` |
| 12 | `app/Services/Scraper.php:47` | `draw_date` no longer set (T9's fix reverted) | ✅ Killed — `test_a_newly_scraped_draw_carrying_a_nul_byte_persists` |

**Result**: **12/12 killed — 0 survived.** No fix task arises from the sensor.

The two mutations the orchestrator flagged as most important both died cleanly:
mutation **3** (silent payload corruption that row counts cannot see, per corrected AD-012)
and mutation **5** (checksum self-verification). The test suite genuinely discriminates
the feature's highest-risk behaviours.

---

## Claims Checked

| Claim | Verdict | Evidence |
| ----- | ------- | -------- |
| Batch 2: T15's dedup test would pass under an infinite suppression window; window-expiry is covered in `AlertNotifierTest` | ✅ **CONFIRMED** | Genuine coverage exists: `AlertNotifierTest.php:58` (`travel(61)->minutes()` ⇒ `assertSentCount(2)`), `:70` (configurable window), `:82` (config fallback, 29min suppressed / 31min sent). The deferral is honest, not a dodge. |
| Batch 2: T10's tolerance derives NUL-stripping independently of `NulSafeJson`, so a cast bug cannot cancel itself out | ✅ **CONFIRMED — independence is real, not nominal** | `CutoverDraws.php:136` derives the escape via `trim((string) json_encode(chr(0)), '"')` → ` `, and strips it from the *source* encoding only. It never calls `NulSafeJson`. Mutations 1 and 2 (cast bugs) were killed by cast tests, and mutation 3 (tolerance bug) was killed by cutover tests — the two are independently sensored. |
| Batch 1: T8's commit contains no code changes (evidence-only) | ✅ **LEGITIMATE — not a missed problem** | `git show --stat e3ec4ea` → only `dialect-audit.md`, +64 lines. The declared Test Coverage Matrix assigns migrations "none — build gate only", so an evidence-only commit is contract-compliant. The concern that `migrate:fresh` exercises neither the backfill nor `->change()` is **valid and was anticipated**: the audit records rolling back 3 migrations, inserting 6 real payloads (4 NUL-bearing), and re-running forward, with the resulting `draw_date` values and `is_nullable = NO` captured. Both paths were genuinely exercised. **Caveat**: as a one-time manual run, not a repeatable test — see Gap 4. |
| Batch 1: T9 modified `Scraper.php` outside its declared test-only scope | ✅ **FIX IS CORRECT, and tested** | `Scraper.php:47` now sets `draw_date` from `dataApuracao`; `drawDateFrom()` returns `null` on an unparseable date, which trips the NOT NULL constraint — a loud failure, matching its own docblock, not a workaround that silently persists a bad row. Mutation 12 confirms coverage. **But**: the unparseable-date branch itself has no test, and `app/Services/**` is a "unit, all branches" layer in the matrix — see Gap 6. The scope excursion was justified (the bug blocked T9) but should have carried a branch test. |
| T7's CI workflow has never executed → UNMET | ✅ **CONFIRMED UNMET** | `gh run list` → `[]`; all 17 commits unpushed (`git log origin/main..HEAD`). T7's own Done-when: *"a workflow file that has never executed is not CI."* |
| Batch 2: INFRA-17's monthly retention is unsatisfiable as specified | ⚠️ **REASONING SOUND, CONCLUSION IS A PARTIAL DODGE** | The technical claim is correct: S3 lifecycle rules filter by prefix/tag/age and cannot select "the first export of each month"; promotion is necessarily writer-side. Documenting it (`backup-retention.md:128-146`) was the **right call** and is unusually honest. But the leap to "outside T17's scope / adding it would be scope creep" does not hold — INFRA-17 is a *requirement*, and satisfying it is not scope creep. The promotion is a small, testable change (copy the artifact to `monthly/` when `now()->day === 1`). No follow-up task was created, so the requirement is left half-met with no owner. See Gap 3. |

---

## Gate Check

- **Gate command**: `php artisan test` (Build gate per `tasks.md`)
- **Result**: **157 tests, 0 failures**, 715 assertions (156 reported `deprecated`, 1 `passed` — all executed and passed)
- **Test count before feature**: 77
- **Test count after feature**: 157
- **Delta**: **+80 tests**
- **Skipped**: none
- **Failures**: none
- **Deprecations**: 156 × `PDO::MYSQL_ATTR_SSL_CA` — pre-existing PHP 8.5 issue from the framework's bundled `config/database.php`, tracked as INFRA-22. Not a regression; same root cause as the AD-008 amendment.

**Test integrity**: no test count decrease, no deletions, no weakened assertions observed in the diff.

---

## Code Quality

| Principle | Status |
| --------- | ------ |
| Minimum code | ✅ |
| Surgical changes | ✅ |
| No scope creep | ⚠️ T9 touched `Scraper.php` outside its declared scope — justified (blocking bug), correctly fixed, but shipped without a branch test |
| Matches patterns | ✅ Eloquent-only, promoted constructor properties, explicit return types, `casts()` method, PHPDoc over inline comments |
| Spec-anchored outcome check | ⚠️ 1 spec-precision gap (cron expression) |
| Per-layer Coverage Expectation met | ⚠️ `app/Services/Scraper.php` gained a branch with no unit test (matrix requires "all branches") |
| Every test maps to a requirement — no unclaimed tests | ✅ Every new test file carries an INFRA-NN docblock |
| Documented guidelines followed | ✅ `CLAUDE.md`, `.github/copilot-instructions.md`, `phpunit.xml`; `pint` clean |

**Notable quality strengths**: comments explain *why* rather than *what* throughout
(`ExportCorpus.php:97-103`, `RestoreCorpus.php:51-56`, `CutoverDraws.php:122-131`), and the
fixture rule was honoured — tests draw from the real seeded corpus including NUL-bearing
and pre-2483 zero-padded payloads, never synthetic data.

**One documentation defect**: `app/Casts/NulSafeJson.php:11` still states *"PostgreSQL's
json/jsonb types reject \0"* — the pre-correction claim that AD-012 explicitly overturned on
2026-07-18. The code is right; the docblock preserves the wrong mental model at exactly the
spot a future reader would trust it.

---

## Edge Cases

- [x] Export before `pages` exists → `ExportCorpusPagesTest.php:73`
- [x] Empty `pages` → valid zero-row artifact → `ExportCorpusPagesTest.php:89`
- [x] Object storage unreachable → fails loudly + alerts → `ExportCorpusAlertingTest.php:47`
- [x] Cutover validation failure → no config switch, source untouched → `CutoverDrawsTest.php:138,158`
- [ ] Cold start succeeds slowly rather than erroring → **deferred** (T20, operator-gated)

---

## Ranked Gaps

### Gap 1 — INFRA-06: CI has never executed (Major)

- **Requirement**: INFRA-06 / T7 Done-when: *"The workflow is observed passing on a real run."*
- **Evidence**: `gh run list` → `[]`; 17 commits unpushed.
- **Root cause**: Work was never pushed; nothing about the workflow file itself is known to be wrong.
- **Fix task**: Push the branch, observe the run, record the run URL in `dialect-audit.md`. Fix whatever the first real run surfaces (a workflow that has never run is usually wrong on the first try — `composer install` flags, PG host, `pint --test`).
- **Priority**: Major

### Gap 2 — INFRA-15: no post-restore render assertion (Major)

- **Requirement**: Spec AC P3.4 — *"a sampled set of previously-`Published` draw pages SHALL render correctly afterward."*
- **Evidence**: `tests/Feature/Commands/RestoreCorpusTest.php` contains no `assertOk`/`assertStatus`/HTTP assertion. `:66` asserts blocks and status survive, which is reconstruction, not rendering.
- **Why it matters**: Data reconstruction and renderability are different claims. A restore that rebuilds rows whose `blocks` no longer resolve against current block classes passes every existing assertion and still leaves an unusable site — the exact false confidence this feature exists to prevent.
- **Note**: T19 (deferred) lists the render check, but INFRA-15 belongs to T18, which is in scope, and the assertion is trivially available — `routes/web.php:13` serves `/{game}/resultado/{concurso}`, and `tests/Feature/DrawPageRenderingTest.php:27` already demonstrates the pattern.
- **Fix task**: Add `test_a_restored_published_page_still_renders()` to `RestoreCorpusTest` — restore, then `$this->get("/megasena/resultado/{$concurso}")->assertOk()`.
- **Priority**: Major

### Gap 3 — INFRA-17: monthly retention tier is unreachable (Major)

- **Requirement**: Spec AC P3.6 — *"monthly artifacts for 12 months."*
- **Evidence**: `ExportCorpus.php:64` writes only `exports/{Y-m-d}/`; `backup-retention.md:128-146` concedes the `monthly/` rule *"currently matches nothing and the effective retention is 35 days for everything."*
- **Assessment**: The reasoning is sound and the honesty is commendable, but the requirement was left half-met with no owner and no follow-up task.
- **Fix task**: Promote one artifact per month writer-side — copy to `monthly/{YYYY-MM}/` when `now()->day === 1` (or on the first export of a month), with a test asserting the promoted copy exists and that a non-first-of-month export does not promote. Additionally: the lifecycle policy itself has never been applied (no bucket provisioned) — that half is legitimately operator-gated and should be handed off explicitly.
- **Priority**: Major

### Gap 4 — INFRA-08: migration backfill has no repeatable coverage (Minor)

- **Requirement**: INFRA-08.
- **Evidence**: `dialect-audit.md` T8 block records a manual rollback-insert-replay. No automated test exercises the data-bearing backfill or the `->change()` NOT NULL path; `migrate:fresh` on an empty table exercises neither.
- **Assessment**: Contract-compliant (the matrix assigns migrations no tests) and the manual evidence is real and thorough. But a one-time manual run does not survive a future edit to that migration.
- **Fix task**: Either add a feature test that seeds `draws`, runs the migration forward, and asserts populated `draw_date` + `is_nullable = NO`; or amend the Test Coverage Matrix to state that data-bearing migrations require a test while pure-schema ones do not.
- **Priority**: Minor

### Gap 5 — INFRA-12: cron expression is not spec-anchored (Minor, spec-precision)

- **Requirement**: Spec AC P3.1 says only *"WHEN a night passes"*; T16 says *"nightly, off-peak."* Neither defines a time.
- **Evidence**: `ExportCorpusScheduleTest.php:24` — `assertSame('30 3 * * *', $event->expression)`.
- **Assessment**: The assertion mirrors the implementation's chosen value rather than a spec-defined outcome. 03:30 is a sensible reading of "off-peak", but the test would fail on any equally spec-compliant time, which makes it a change-detector for that line.
- **Fix task**: Either pin the window in the spec ("between 02:00 and 05:00 local"), or assert the property the spec actually states — that the schedule runs daily and falls in an off-peak window.
- **Priority**: Minor

### Gap 6 — `Scraper::drawDateFrom()` fallback branch untested (Minor)

- **Requirement**: Test Coverage Matrix — `app/Services/**` → unit, "All branches".
- **Evidence**: `Scraper.php:60-79`; no `ScraperTest` exists. Only the happy path is covered, indirectly via `JsonRoundTripTest.php:136`.
- **Fix task**: Add a unit test asserting that a payload with a missing or unparseable `dataApuracao` fails loudly (constraint violation) and logs the warning — never silently persists.
- **Priority**: Minor

### Gap 7 — `NulSafeJson` docblock states the corrected-away failure mode (Minor)

- **Evidence**: `app/Casts/NulSafeJson.php:11` — *"PostgreSQL's json/jsonb types reject \0"*. AD-012 (2026-07-18) established that `json` **accepts** `\0`; only `jsonb` rejects it, and the whole point of the correction is that the failure is silent, not loud.
- **Fix task**: Correct the docblock to match AD-012 and `JsonRoundTripTest.php:95-101`.
- **Priority**: Minor

---

## Requirement Traceability Update

| Requirement | Previous | New |
| ----------- | -------- | --- |
| INFRA-06 | Pending | ⚠️ Partial — CI never executed |
| INFRA-07 | Pending | ✅ Verified |
| INFRA-08 | Pending | ✅ Verified (manual evidence) |
| INFRA-09 | Pending | ✅ Verified |
| INFRA-10 | Pending | ✅ Verified |
| INFRA-12 | Pending | ✅ Verified |
| INFRA-13 | Pending | ✅ Verified |
| INFRA-14 | Pending | ✅ Verified |
| INFRA-15 | Pending | ⚠️ Partial — render assertion missing |
| INFRA-17 | Pending | ⚠️ Partial — monthly tier unreachable |
| INFRA-18 | Pending | ✅ Verified |
| INFRA-19 | Pending | ✅ Verified |
| INFRA-21 | Pending | ✅ Verified |
| INFRA-22 | Open | Open — documented, unmapped |
| INFRA-01/02/03/04/05/11/16/20 | Pending | 🔒 Deferred — operator-gated |

---

## Summary

**Overall**: ⚠️ **Issues — not ready to close, close to it.**

**Spec-anchored check**: 9/13 in-scope requirements fully met · 3 partial · 1 spec-precision gap
**Sensor**: **12/12 mutations killed, 0 survived**
**Gate**: 157 passed, 0 failed (+80 over the 77-test baseline)
**Working tree**: clean — every mutation reverted, tree byte-identical to the pre-verification state

**What works, and works well**: The NUL-byte handling (INFRA-21) is the strongest work in the
feature — the paired tests at `JsonRoundTripTest.php:102/125` assert the *corrected* AD-012
failure mode directly against PG 17, proving the cast is load-bearing rather than
precautionary. The cutover tolerance function is genuinely independent of the cast it
validates, so a cast bug cannot cancel itself out; the sensor confirmed this by killing
faults on both sides separately. The export/restore path refuses corrupt artifacts before
writing a row, and every failure path is audible. Test fixtures use the real seeded corpus
throughout, honouring the project's fixture rule.

**What is missing**: three requirements have acceptance criteria with no discharging
evidence — CI has never run, no test proves a restored page renders, and the monthly
retention tier has no writer. None is a test-quality problem; all three are completeness.

**Both prior batches' self-reports were accurate this round.** Every claim checked held up
under independent evidence, including the two the batches flagged against themselves.
The one place they were too generous to themselves is INFRA-17: the technical reasoning is
correct, but "out of scope" understates that a requirement was left unowned.

**Next steps**: Gaps 1–3 (Major) before this feature closes. Gaps 4–7 (Minor) can ride along
or be batched. Then the operator handoff for T11/T19/T20/T21 remains the only route to the
eight deferred requirements — and per `tasks.md`, the T11 cutover window is closing.
