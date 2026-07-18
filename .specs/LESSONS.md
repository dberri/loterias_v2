# LESSONS — auto-maintained by scripts/lessons.py

> Machine-owned. Do NOT hand-edit. Changes are overwritten on the next `lessons.py` write.
> Canonical state lives in `.specs/lessons.json`. Edit lessons only via the script.
> promote_threshold=2 distinct features · window_days=45 · quarantine_threshold=2

## Confirmed (load these at Specify/Design)

Corroborated across multiple features. Safe to apply as guidance.

_none_

## Candidates (under observation — do NOT load as guidance yet)

Seen once or not yet corroborated. Tracked, not trusted.

### L-001 — When a task retires a model/service (e.g. DrawPage, ContentCreator), grep for remaining references across the whole entry-point chain (commands, jobs) before marking the retiring task done, not just in the model layer — dangling old code left wired into live console commands becomes a fatal-error trap, not just dead code.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `app/Services,app/Console/Commands,app/Jobs` · harmful: 0
- features: seo-draw-page-generation
- evidence: DRAWPAGE-01,07,08,13,14 / tasks.md T19-T22 (app/Services,app/Console/Commands,app/Jobs)
- last seen: 2026-07-15T17:57:37Z

### L-002 — For a multi-phase tasks.md, verify progress against actual git log + file diffs per task, not against tasks.md checkboxes — an implementing agent may commit real work through several phases while never ticking a single checkbox, making the file itself an unreliable progress signal.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `.specs/features` · harmful: 0
- features: seo-draw-page-generation
- evidence: tasks.md Phase 5-6 (T18-T26) (.specs/features)
- last seen: 2026-07-15T17:57:37Z

### L-003 — An agent can execute every remaining task in a plan correctly and still skip the skill's atomic-commit-per-task rule entirely, leaving a large uncommitted diff with no per-task revert boundary — always check 'git status' for uncommitted work spanning multiple tasks, not just 'git log', when verifying a resumed Execute pass.
- signal: `spec_deviation` · recurrence: 1 feature(s) · scope: `.specs/features` · harmful: 0
- features: seo-draw-page-generation
- evidence: tasks.md Execution Protocol / Critical Rules (one atomic commit per task) (.specs/features)
- last seen: 2026-07-15T18:40:17Z

### L-004 — A CI workflow file is not CI until a real run is observed passing; push and cite the run URL before marking the task done.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `ci` · harmful: 0
- features: infrastructure-cloud-postgres-backups
- evidence: INFRA-06 / .github/workflows/ci.yml (ci)
- last seen: 2026-07-18T12:09:27Z

### L-005 — When an acceptance criterion names two outcomes joined by AND, assert both separately — reconstructing data is not the same claim as rendering it.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `testing` · harmful: 0
- features: infrastructure-cloud-postgres-backups
- evidence: INFRA-15 / tests/Feature/Commands/RestoreCorpusTest.php (testing)
- last seen: 2026-07-18T12:09:27Z

### L-006 — Documenting why a requirement cannot be met as specified does not discharge it; record the residual gap as a follow-up task with an owner.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `process` · harmful: 0
- features: infrastructure-cloud-postgres-backups
- evidence: INFRA-17 / docs/infrastructure/backup-retention.md:128 (process)
- last seen: 2026-07-18T12:09:27Z

### L-007 — Do not assert an exact literal the spec never fixed; either pin the value in the spec or assert the property the spec actually states.
- signal: `spec_precision_gap` · recurrence: 1 feature(s) · scope: `testing` · harmful: 0
- features: infrastructure-cloud-postgres-backups
- evidence: INFRA-12 / tests/Feature/Jobs/ExportCorpusScheduleTest.php:24 (testing)
- last seen: 2026-07-18T12:09:27Z

### L-008 — A production-code fix made during a test-only task still owes a test for the branch it introduces.
- signal: `ac_gap` · recurrence: 1 feature(s) · scope: `testing` · harmful: 0
- features: infrastructure-cloud-postgres-backups
- evidence: app/Services/Scraper.php:60 (testing)
- last seen: 2026-07-18T12:09:27Z

## Quarantined (failed when applied — ignore)

A confirmed lesson that recurred alongside failure. Kept for the maintainer to review.

_none_
