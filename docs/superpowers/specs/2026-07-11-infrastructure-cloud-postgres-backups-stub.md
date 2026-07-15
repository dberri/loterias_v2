# Infrastructure: Laravel Cloud, Postgres, Backups — Spec Stub

**Date:** 2026-07-11
**Status:** ✅ Superseded — spec + design complete at `.specs/features/infrastructure-cloud-postgres-backups/`
**Depends on:** loosely on all other specs (hosts them); no hard code dependency.

> The open questions below were resolved on 2026-07-13: Postgres in **all** environments (AD-008, SQLite/MySQL
> retired); dual-layer backups (Laravel Cloud native + portable NDJSON export to object storage); no separate raw-JSON
> archive (`raw_data` is already in the export); cold starts accepted for web/worker but **not** the scheduler.
> Note the cutover is far cheaper than this stub assumed — per AD-003 only `draws` needs migrating, since pages are
> regenerated. **That window closes once page generation runs at scale.**

## Intent

Deploy to Laravel Cloud with low-cost, low-maintenance operations, and never lose the AI-generated content.

## Likely scope

- **Laravel Cloud deploy** — app + queue worker + scheduler.
- **MySQL → PostgreSQL migration.** Sail's docker-compose currently provisions MySQL; Laravel Cloud's serverless Postgres is cheaper. Need a migration path for schema + data (and to confirm nothing relies on MySQL-specific behavior; `raw_data`/`blocks` are JSON columns — verify JSON usage is Postgres-compatible).
- **Backups** of `draws` (raw facts) and `pages` (AI content) so generated content survives. Decide cadence + retention + restore test.

## Open questions

- Local dev stays SQLite/MySQL while prod is Postgres? Or move all envs to Postgres for parity?
- Backup mechanism: Laravel Cloud native backups vs application-level export vs both.
- Do we snapshot the raw Caixa JSON separately (cheap insurance for re-generation)?
- Serverless cold-start acceptable for a mostly-SEO/read traffic profile?
