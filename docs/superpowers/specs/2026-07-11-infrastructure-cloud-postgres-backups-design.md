# Infrastructure: Laravel Cloud, Postgres, Backups — Design

Date: 2026-07-11
Status: Ready for review
Depends on: loosely on all other specs (hosts them); no hard code dependency

## Intent

Deploy on Laravel Cloud with low-cost, low-maintenance operations and durable recovery for generated content and draw history.

## Scope

- Laravel Cloud deployment shape for app, worker, and scheduler.
- Standardize database behavior on PostgreSQL across environments.
- Define migration path from current local SQLite/MySQL usage to Postgres-first parity.
- Define dual-layer backup policy and restore runbook.
- Define cost controls, operational alerts, and acceptance criteria.

## Constraints Chosen

- Environment parity: all environments target PostgreSQL behavior.
- Backup strategy: dual-layer (Laravel Cloud native backups plus app-level exports).
- Recovery targets: RPO 24h, RTO 8h.
- Runtime posture: cost-first, cold starts tolerated for web and workers.
- Separate immutable raw payload archive: not required.

## Approaches Considered

1. Laravel Cloud-first minimal custom ops (Recommended)
   - Use Laravel Cloud for runtime and native DB backups; add lightweight app-level exports for critical tables.
   - Pros: fastest delivery, lowest ops burden, good fit for a small team.
   - Cons: some provider coupling unless export format stays portable.

2. Portable-by-default platform pattern
   - Add stricter provider-agnostic ops abstractions and restore tooling from day one.
   - Pros: easier future migration.
   - Cons: more initial complexity and slower delivery.

3. App-managed durability heavy
   - Prioritize app-managed backup and restore mechanisms over provider features.
   - Pros: strongest independence from provider.
   - Cons: highest maintenance and largest operational surface.

Recommendation:
- Adopt approach 1 with two guardrails: provider-agnostic export format and quarterly restore drills.

## Architecture

### Runtime Topology

- Web service on Laravel Cloud (cold starts acceptable).
- Queue worker service on Laravel Cloud for background jobs.
- Scheduler process on Laravel Cloud for recurring tasks.

### Database

- PostgreSQL is the canonical database target for application behavior.
- Local development runs against Postgres (docker profile) for parity.
- CI runs tests against Postgres to prevent dialect regressions.

### Data Durability

- Layer 1: Laravel Cloud native database backups.
- Layer 2: nightly app-level exports of critical tables (`draws`, `pages`) plus manifest metadata.

## Postgres Migration Strategy

### Rollout Phases

1. Expand and harden
   - Review migrations, indexes, and queries for Postgres compatibility.
   - Remove MySQL-only assumptions from query and JSON handling.
2. Parity validation
   - Run app and CI on Postgres.
   - Execute integration tests against JSON-heavy paths (`draws.raw_data`, `pages.blocks`).
3. Production cutover
   - Snapshot source DB.
   - Provision and migrate schema on target Postgres.
   - Backfill data with integrity checks.
   - Perform final delta sync in a short maintenance window.
   - Switch connection and verify post-cutover checks.

### Data Validation Gates

- Row-count parity for critical tables (`draws`, `pages`, and queue-related tables if used).
- Random sampled record parity checks for JSON-heavy rows.
- Public read-path smoke tests and admin read-path smoke tests.

### Rollback

- Keep pre-cutover snapshot immutable.
- If critical post-cutover checks fail, revert connection config and redeploy previous known-good configuration.

## Backup and Restore Design

### Backup Policy

- Native backups: Laravel Cloud managed backups.
- App-level exports: nightly exports for `draws` and `pages`.
- Export manifest includes:
  - export timestamp
  - schema/application version
  - row counts by table
  - checksum/hash metadata for artifact validation

### Retention

- Native backups: provider default schedule, with at least 7 daily restore points.
- App exports:
  - daily artifacts retained for 35 days
  - monthly artifacts retained for 12 months

### Recovery Objectives

- RPO: 24 hours.
- RTO: 8 hours.

### Restore Workflow

1. Declare incident and freeze writes.
2. Choose restore source:
   - Native backup for full-database restoration.
   - App export for targeted content restoration.
3. Restore into staging first.
4. Validate integrity:
   - table row counts
   - key draw/page existence checks
   - spot-check recent concursos
5. Promote restored state to production.
6. Run post-incident verification and capture follow-ups.

### Restore Drills

- Frequency: quarterly.
- Drill pass criteria:
  - completion within 8 hours
  - validation checks pass
  - representative draw pages render correctly
- Each drill records elapsed time, issues, and runbook updates.

## Operations and Cost Controls

### Runtime Policy

- Cost-first defaults: tolerate cold starts for web and workers.
- Keep scheduler always running with minimal footprint.
- Scale workers conservatively based on sustained backlog, not short spikes.

### Cost Guardrails

- Monthly budget alerts at 70%, 90%, and 100%.
- Track and review major cost drivers:
  - database storage and IO
  - worker runtime
  - backup and export storage
- Remediation order when over threshold:
  1. tune polling cadence and queue concurrency
  2. optimize retention and storage classes
  3. revisit architecture only if needed

### Operational Alerts

- Queue backlog above threshold.
- Failed job growth trend.
- Missed scheduler heartbeats/executions.
- Backup/export job failures.

### Minimal Runbook Coverage

- Restart workers/scheduler safely.
- Requeue failed jobs.
- Decide between native restore and app-export restore paths.

## Testing and Acceptance Criteria

### Infra Readiness

1. Laravel Cloud deployment succeeds for web, worker, and scheduler.
2. Postgres migrations succeed across environments.
3. JSON-heavy read/write paths function correctly under Postgres.

### Backup and Restore

1. Nightly export job completes and artifacts validate.
2. Quarterly restore drill meets RTO 8h target.
3. Restored data passes integrity checks and key page render checks.

### Continuity

1. Draw ingestion and page rendering work after cutover.
2. No data loss for generated/published page content across migration and rollback tests.

## Deferred Items (Out of Scope)

- Detailed provider cost comparison across non-Laravel-Cloud platforms.
- Multi-region high-availability architecture.
- Hourly export cadence or near-real-time CDC pipelines.

## Next Step

After spec approval, invoke writing-plans to produce the implementation plan for:
- local/CI Postgres standardization,
- production cutover checklist,
- backup/export automation,
- restore drill automation and runbook tasks.

---
Spec author: dberri