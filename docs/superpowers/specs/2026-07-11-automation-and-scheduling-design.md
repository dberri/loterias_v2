# Automation & Scheduling — Design

Date: 2026-07-11
Status: Ready for review
Depends on: ./2026-07-11-seo-draw-page-generation-design.md

## Intent

Run the whole pipeline hands-off: scheduled scrape → generate → publish, so new draws become live pages without supervision while retaining safe manual controls and observability.

## Scope

- Schedule and run scrapers for each game according to cadence.
- Enqueue and process page-generation jobs for draws that lack pages.
- Controlled auto-publish toggle (content.auto_publish) to allow review for early rollout.
- Monitoring, alerting, and runbooks for failures and backpressure.
- Admin tooling to inspect, requeue, and force-publish failed work.
- Deployment notes for reliable cron/worker execution in target infra.

## Approaches (considered)

1. Laravel Scheduler + Queue Workers (Recommended)
   - Leverage artisan commands, Laravel queue system, existing developer familiarity.
   - Pros: simple local dev story, minimal infra changes, clear job lifecycle and DLQ handling.
   - Cons: requires deployment guidance for cron/worker supervisors.

2. Kubernetes CronJobs + Distributed Workers
   - Pros: k8s-native scheduling, fine-grained scaling, pod-level isolation.
   - Cons: operational complexity; heavier CI/CD changes.

3. Managed Scheduler → Webhook → Enqueue (e.g., Cloud Scheduler/EventBridge)
   - Pros: managed timing accuracy and reliability.
   - Cons: cloud-provider integration and lock-in.

Recommendation: start with Laravel Scheduler + Queue Workers, document infra variants for future migration.

## Architecture: Components & Data Flow

Components:
- Scheduler: Laravel scheduled tasks (artisan app:scrape-draws --game=XXX, app:create-pages)
- Scraper service: commands that call Caixa API / scrape sources and normalize into canonical Draw model
- Queue system: separate queues for scrape, generate, publish; Laravel queue workers process jobs
- Page generator: queued job that runs content generation prompts and produces Content records
- Publish worker: job that applies publishing rules (auto_publish toggle, review queue)
- Monitoring & alerts: metrics exporter, dashboard, and email alerter
- Admin UI / CLI: inspect failed_jobs, requeue, force-publish

Data flow:
1. Cron triggers scheduled artisan scrape commands per game.
2. Scrape command fetches source; on new/updated draw enqueues GenerateDrawPage job with draw_id + payload.
3. Generate job calls the generator, persists Content draft with checksum and job status.
4. Publish job runs if auto_publish=true; otherwise leave in PendingReview state for manual approval.
5. Any failure follows retry/backoff policy; on exhaustion move to failed_jobs (DLQ) and send alert.

Key properties: idempotency, observable job lifecycle, bounded concurrency per game, and clear manual override.

## Scheduling & Cadence (detailed)

- Source of truth: official draw schedule (when available). If not available, use config/schedules.php per game.
- Timezone: store schedules in America/Sao_Paulo; cron runs in UTC but scheduler computes local times.
- Polling strategy:
  - Low-frequency games (weekly): once per day.
  - Daily/high-frequency games: every 6 hours.
  - Near expected draw times: switch to aggressive polling (every 5–15 minutes) starting 2 hours before scheduled draw time until result recorded.
- Lookahead: scrape for announced draws up to 7 days ahead for scheduling and page pre-creation.
- Backfill window: commands support --since / --until to backfill missed draws.
- Idempotency: scrapers generate draw checksum and only enqueue if checksum changed or no content exists.

## Retry & Backoff Policy

- Scrape jobs:
  - Attempts: max 5
  - Backoff schedule: 1m, 5m, 20m, 1h, 4h (+/- 20% jitter)
  - Respect HTTP Retry-After and 429 responses
- Generation jobs:
  - Attempts: max 3
  - Backoff schedule: 30s, 5m, 30m (+/- jitter)
- Non-retryable: deterministic 4xx (unless Retry-After/429) and parsing logic that deterministically fails for the same payload.
- Dead-letter: failed_jobs table with job_type, draw_id, payload, attempts, error, traceback, last_attempt_at.
- Circuit breaker: if >10 consecutive failures for a given game within 1 hour, pause polling for that game for 1 hour and send an alert.

## Queues & Concurrency

- Separate named queues: scrape, generate, publish
- Concurrency caps and worker counts are deploy-time configurations; default conservative settings:
  - scrape: 2 concurrent
  - generate: 4 concurrent
  - publish: 2 concurrent
- Prioritization: generate higher priority than batch scrape to avoid backlog.

## Monitoring & Alerts

Metrics to collect:
- scrape_success_rate, generate_success_rate
- jobs_enqueued, jobs_processed, job_latency per queue
- failed_jobs_count, dlq_size
- queue_depth per queue
- worker_heartbeats, last_success per game
- consecutive_failures per game

Dashboards:
- System overview: workers, queue depths, DLQ size
- Per-game panels: last_success_time, failures, backlog
- Recent errors stream linking to logs and failed_jobs entries

Alert thresholds (email to team inbox):
- scrape failure rate >20% over 15m
- failed_jobs >10 in 1h
- queue backlog >50 pending pages
- worker heartbeat missing >5m
- circuit breaker triggered for a game

Alert format: subject "[loterias_v2] Automation Alert: <type>"; body includes game, draw_id, job_id, error, logs link, remediation steps.

Runbook (first-response):
1. Confirm alert context and view logs/failed_jobs.
2. Check worker supervisors and restart if necessary.
3. If transient, requeue failing jobs; if persistent, pause polling for the affected game.
4. If source-rate-limited, honor Retry-After and increase polling backoff; notify stakeholders.
5. If DLQ grows, open GitHub issue with samples and assign triage.

Healthchecks: expose /health for scheduler and workers; heartbeat entries in DB updated every minute.

## Error Handling & Idempotency

- Idempotency key: draw_id + source + job_type. Persist last-processed checksum on content records.
- Deduping: enqueue only when no content exists for draw_id or checksum differs.
- Atomicity: wrap content creation + job-state updates in DB transactions.
- Locking: optimistic concurrency via updated_at / version; advisory locks for long-running transforms.
- DLQ & metadata: failed_jobs stores actionable metadata for triage and requeue.
- Retention & privacy: purge failed_jobs older than 90 days; redact sensitive API responses in stored errors.

Example failed_jobs schema (migration sketch):

- id (uuid)
- job_type (string)
- draw_id (string)
- payload (jsonb)
- attempts (int)
- error (text)
- traceback (text)
- last_attempt_at (timestamp)
- created_at, updated_at

## Admin & Operations

- Admin UI (Filament or small Laravel dashboard) to:
  - View recent failed_jobs and error samples
  - Requeue job or force-publish a content record
  - Toggle content.auto_publish flag and view recent auto-publishes
- CLI helpers: artisan commands to requeue DLQ entries, pause/resume polling per game, and backfill ranges
- Deployment docs: systemd / supervisor / k8s manifests examples to run scheduler and queue workers. Document required environment variables and queue supervisor config.

## Testing & Acceptance Criteria

Unit tests:
- Scraper: validate parsing across sample payloads; simulate 429/5xx and assert retry behavior.
- Generator: given prompt inputs, validate content shape and checksum behavior.
- Jobs: assert state transitions, DLQ writes, idempotency enforcement.

Integration tests:
- Mock source APIs; run scheduled command end-to-end (schedule → scrape → enqueue → generate → content record persisted).
- Concurrency tests: simulate concurrent runs to exercise optimistic locking and deduping.

Staging E2E:
- Full pipeline on staging verifying published page appears when auto_publish=true and held when false.
- Failure injection to ensure alerts, DLQ, and circuit breaker work.

Acceptance criteria (must pass):
1. Scheduled scrapes create generation jobs for new draws.
2. No duplicate pages for same draw_id; idempotency enforced.
3. Retry/backoff and DLQ behavior implemented and populated as expected.
4. Alerts are delivered to team inbox for thresholds defined in Monitoring & Alerts.
5. Auto-publish toggle controls publish behavior safely.

## Deployment & Infra Options

- Minimal: systemd/supervisor to run `php artisan schedule:run` every minute and queue workers via `php artisan queue:work --queue=...` with process supervisors.
- Preferred (cloud-ready): run scheduler on a small managed instance and workers in scaled instances/tasks. Document k8s CronJob examples for later migration.
- Optional: integrate Cloud Scheduler/EventBridge for exact timing if infra matures.

## Runbook (detailed)

- Triage steps for a scrape failure alert:
  1. Open failed_jobs entry; inspect traceback and payload.
  2. Re-run scrape command for this draw with --debug and capture logs.
  3. If parsing changed (site structure), add parser update task and pause polling for game.
  4. If transient error (rate limit), wait and requeue; consider increasing backoff window.

## Next steps

1. User review of this spec and requested edits.
2. After approval, invoke writing-plans skill to generate implementation tasks and make concrete code changes.
3. Implement scheduler commands, jobs, failed_jobs migration, admin UI, and monitoring dashboards.

---
Spec author: dberri

