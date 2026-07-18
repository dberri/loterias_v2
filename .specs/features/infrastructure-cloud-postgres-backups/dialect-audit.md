# Dialect Audit — engine-specific SQL ahead of the PostgreSQL cutover

**Requirement**: INFRA-10
**Task**: T3
**Date**: 2026-07-18
**Scope searched**: `app/`, `database/`, `routes/`, `config/` (PHP files only; `vendor/` excluded)

This document records **what was searched**, not only what was found, so a future
reader can distinguish coverage from luck.

---

## Patterns searched

Every pattern below was swept with `grep -rn --include='*.php' -F` across the four
directories above.

| # | Pattern | Why it is a dialect risk | Hits |
| - | ------- | ------------------------ | ---- |
| 1 | `DB::` | Raw query-builder entry point, bypasses Eloquent portability | **2** |
| 2 | `DB::raw` | Literal SQL fragment | 0 |
| 3 | `DB::statement` | Literal DDL/DML | 0 |
| 4 | `unprepared` | Literal SQL | 0 |
| 5 | `whereJsonContains` | MySQL `JSON_CONTAINS` vs Postgres `@>` — different semantics | 0 |
| 6 | `whereJsonPath` | Postgres-only JSON path operator | 0 |
| 7 | `whereJsonLength` | Engine-specific JSON length function | 0 |
| 8 | `whereJsonDoesntContain` | As #5 | 0 |
| 9 | `whereRaw` | Literal SQL predicate | 0 |
| 10 | `selectRaw` | Literal SQL projection | 0 |
| 11 | `orderByRaw` | Literal SQL ordering | 0 |
| 12 | `havingRaw` | Literal SQL having | 0 |
| 13 | `groupByRaw` | Literal SQL grouping | 0 |
| 14 | `json_extract` / `JSON_EXTRACT` | MySQL/SQLite JSON function, absent on Postgres | 0 |
| 15 | `JSON_CONTAINS` | MySQL-only | 0 |
| 16 | `RANDOM()` / `RAND(` | `RAND()` is MySQL, `RANDOM()` is Postgres/SQLite | 0 |
| 17 | `inRandomOrder` | Portable wrapper, but worth locating | 0 |
| 18 | `ILIKE` | Postgres-only case-insensitive match | 0 |
| 19 | `GROUP_CONCAT` | MySQL/SQLite; Postgres uses `string_agg` | 0 |
| 20 | `AUTO_INCREMENT` | MySQL-only DDL | 0 |
| 21 | `FULLTEXT` / `fullText` | Engine-specific index type | 0 |
| 22 | `->change()` | Column alteration — differs sharply across engines | **1** |
| 23 | `enum(` | Native ENUM is MySQL-only | 0 |
| 24 | `unsigned` | No native UNSIGNED on Postgres | **4** |
| 25 | `binary(` | Engine-specific binary column | 0 |
| 26 | `charset` / `collation` | MySQL-only connection attributes | **6** (config only) |
| 27 | `insertOrIgnore` / `upsert(` | Conflict-handling syntax differs | 0 |
| 28 | `toBase()` | Drops to query builder, portability escape hatch | 0 |
| 29 | `year(` | Date-function extraction differs | 0 |

**Total distinct findings: 4** (rows 1, 22, 24, 26). All resolved below.

The near-empty result is the expected outcome of the project's Eloquent-only
convention (`CLAUDE.md`, `.github/copilot-instructions.md`) — but expected is not
verified, which is why the sweep was run rather than assumed.

---

## Findings and resolutions

### F1 — Raw `DB::table('draws')` backfill in `add_draw_date_to_draws_table`

**Location**: `database/migrations/2025_08_24_190138_add_draw_date_to_draws_table.php:22,32`

```php
$draws = DB::table('draws')->whereNotNull('raw_data')->get();
$rawData = json_decode($draw->raw_data, true);
```

**Risk**: `DB::table()` itself is portable (it is the query builder, not raw SQL).
The dialect-sensitive part is `json_decode($draw->raw_data, true)`: it assumes the
driver hands back the `json` column as an undecoded **string**. PDO's `pgsql` driver
does return `json`/`jsonb` as a string, so this happens to work — but the migration
would silently produce `null` rows if any driver ever returned it pre-decoded.

**Resolution**: **Replaced with a portable equivalent.** The decode is now
defensive and no longer depends on driver behaviour:

```php
$rawData = is_array($draw->raw_data)
    ? $draw->raw_data
    : json_decode($draw->raw_data, true);
```

Schema intent is unchanged — this touches only how the already-fetched value is
interpreted. Proven by execution in **T8** (`migrate:fresh` on clean Postgres), not
by reasoning.

---

### F2 — `->change()` to add a NOT NULL constraint after backfill

**Location**: `database/migrations/2025_08_24_190138_add_draw_date_to_draws_table.php:44`

```php
$table->date('draw_date')->nullable(false)->change();
```

**Risk**: Column alteration is the single most dialect-divergent DDL operation.
On Postgres this compiles to `ALTER TABLE ... ALTER COLUMN ... TYPE date` plus
`SET NOT NULL`, and `SET NOT NULL` **fails outright** if any row still holds `NULL`.
On MySQL the same statement is a `MODIFY COLUMN`. Laravel 11+ implements `change()`
natively (no `doctrine/dbal` dependency), so no extra package is required.

**Resolution**: **Documented as portable, verification deferred to execution.**
Left unchanged — the construct is correct on Postgres and rewriting it would alter
schema intent (the NOT NULL constraint is the point of the migration). On a fresh
database the preceding backfill loop is a no-op over an empty table, so no `NULL`
rows can remain. Verified by **T8** running `migrate:fresh` against clean Postgres.

---

### F3 — `unsignedInteger` / `unsignedTinyInteger` in the jobs table

**Location**: `database/migrations/0001_01_01_000002_create_jobs_table.php:18-21`

**Risk**: PostgreSQL has no `UNSIGNED` modifier.

**Resolution**: **No action — portable by framework.** These are Laravel's own
framework-default migrations, and Laravel's Postgres grammar maps
`unsignedInteger` → `integer` and `unsignedTinyInteger` → `smallint`. The
unsignedness is not enforced at the database level on Postgres, which is standard
Laravel behaviour on this engine and affects no application logic here (these
columns are written only by the framework's queue driver). Verified by T8.

---

### F4 — `charset` / `collation` connection attributes

**Location**: `config/database.php:54,55,74,75,93,108`

**Risk**: `utf8mb4` / `utf8mb4_unicode_ci` are MySQL-only values. The `pgsql` and
`sqlsrv` blocks carry a `charset` key of `utf8`, which Postgres accepts.

**Resolution**: **Resolved by deletion in T5.** The `mysql` and `mariadb`
connection blocks (which hold the MySQL-only values at lines 54-55 and 74-75) are
removed entirely when Postgres becomes the sole configured engine per AD-008. The
`pgsql` block's `charset => utf8` is valid and stays.

> Side effect worth noting: those same blocks are the source of the
> `PDO::MYSQL_ATTR_SSL_CA is deprecated since 8.5` notice emitted on every test
> run under PHP 8.5. Deleting them removes the deprecation as well.

---

### F5 — Framework config merging keeps the retired connections reachable ⚠️ residual

**Discovered**: during T5, after deleting the `sqlite`, `mysql`, and `mariadb`
connection blocks from `config/database.php`.

**Observation**: Laravel 11+ merges the framework's own
`vendor/laravel/framework/config/database.php` into the application's config, so
deleting a connection block from the app file does **not** remove the connection:

```
$ php artisan tinker --execute='echo implode(", ", array_keys(config("database.connections")));'
sqlite, mysql, mariadb, pgsql, sqlsrv
```

`config('database.default')` is correctly `pgsql`, and every environment
(local, `phpunit.xml`, CI) points at Postgres — so nothing runs on another engine
today.

**Status**: **Open — partially resolves AD-008.** The task's literal requirement
(blocks deleted, not commented out) is met, and the practical fallback path is
closed for all configured environments. But AD-008's stronger claim — that SQLite
and MySQL are "removed from config, not left as fallbacks" — is not fully true at
the framework level: a developer who explicitly sets `DB_CONNECTION=sqlite` would
still get a working SQLite connection.

Deliberately **not** worked around here: suppressing the framework's merge would
mean inventing a mechanism no task asked for. Flagged for an explicit decision
rather than silently accepted.

---

## Application-layer JSON handling

No SQL-level JSON access exists anywhere in the codebase. Every `Draw` accessor
(`getDrawnNumbersAttribute`, `getMainPrizeAttribute`, `getLocationAttribute`, and
the rest) reads out of `$this->raw_data` **in PHP**, after the cast has decoded it.
`Page::blocks` behaves the same way. This is the structural reason the engine
migration is low-risk, and it is asserted rather than trusted by the round-trip
tests in **T9**.

The one storage-boundary difference that is *not* benign — PostgreSQL rejecting
` ` inside a `json` column — is handled by `App\Casts\NulSafeJson` (T2,
AD-012), not by anything in this audit.

---

## T8 execution evidence — verified, not reasoned about

Every "verified by T8" claim above was discharged by running against a real
PostgreSQL 17.10 instance on 2026-07-18. **No migration required a fix**; the
audit's F1 change (made in T3) was the only alteration, and it was defensive
rather than corrective.

### 1. `migrate:fresh` on an empty database

The `loterias` database was dropped and recreated, then:

```
$ php artisan migrate:fresh --force
   INFO  Running migrations.
   ... 11 migrations, all DONE, zero dialect errors
```

### 2. The data-bearing backfill path (the one that actually mattered)

`migrate:fresh` runs the `add_draw_date_to_draws_table` backfill against an
**empty** `draws` table, so it proves nothing about F1 or F2. To exercise the real
path, the last three migrations were rolled back, six real Caixa payloads
(concursos 1, 500, 2194, 2482, 2500, 2608 — four of them NUL-bearing) were
inserted, and the migration was re-run forward:

```
 draw_number | draw_date
-------------+------------
           1 | 1996-03-11
         500 | 2003-09-27
        2194 | 2019-10-02
        2482 | 2022-05-18
        2500 | 2022-07-13
        2608 | 2023-07-05
```

- **F1 resolved.** The backfill decoded `raw_data` correctly on Postgres and
  populated every row — `dataApuracao` in `d/m/Y` parsed to the right dates. The
  driver does return the `json` column as a string, as predicted; the defensive
  `is_array()` guard costs nothing and removes the dependency on that behaviour.
- **F2 resolved.** `->change()` to NOT NULL succeeded **with rows present**:

```
 column_name | data_type | is_nullable
-------------+-----------+-------------
 draw_date   | date      | NO
 raw_data    | json      | NO
```

- **F3 resolved.** The framework's `unsignedInteger`/`unsignedTinyInteger` columns
  in the jobs table created without error.
- **Bonus:** the `down()` methods of the three rolled-back migrations also executed
  cleanly on Postgres, so the migration set is reversible on this engine.

`raw_data` is confirmed to be a genuine Postgres `json` column — which is exactly
why `NulSafeJson` (T2) is load-bearing rather than precautionary.

### 3. Full suite

`php artisan test` against Postgres: **98 tests, 0 failures**, versus a
pre-existing baseline of 77. No test was deleted or skipped.

---

## ⚠️ T9 finding — the NUL-byte premise in the spec is inaccurate for this schema

The spec (P2 AC 7), `STATE.md`'s seed-data audit, and AD-012 all state that
**PostgreSQL rejects ` ` in `jsonb`**, and conclude that "~16% of existing rows
will **fail to insert** during cutover". Measured against PostgreSQL 17.10, the
claim about `jsonb` is true but the conclusion about **this** schema is not:
`draws.raw_data` is `$table->json(...)`, which Laravel maps to Postgres **`json`**,
not `jsonb`.

Measured behaviour (` ` is how `json_encode` always emits a NUL):

| Operation | `json` column | `jsonb` column |
| --------- | ------------- | -------------- |
| `INSERT` a payload containing ` ` | ✅ **accepted** | ❌ rejected — `unsupported Unicode escape sequence` |
| Read it back with `->>` (text extraction) | ❌ **fails**, SQLSTATE 22P05 | n/a |
| `ALTER COLUMN ... TYPE jsonb` | ❌ **fails**, SQLSTATE 22P05 | n/a |

**What this changes:** the cutover would **not** have failed loudly on 415 rows.
Those rows would have inserted successfully and become **permanently unreadable at
the SQL level** — any `->>`/`#>>` extraction, any cast to text, and any future
migration to `jsonb` errors on them. That is a *worse* failure mode than the one
the spec anticipated, because it is silent.

**What this does not change:** AD-012's decision. `NulSafeJson` is still the right
fix and is still load-bearing — it is simply preventing silent read-time corruption
and a blocked `jsonb` migration, rather than preventing an insert failure. The
rejected alternative (retyping `raw_data` to `text`) looks *worse* under the
corrected facts, since it would discard JSON validity to preserve bytes that make
the column unreadable.

Both behaviours are asserted in `tests/Feature/Database/JsonRoundTripTest.php`
(`test_a_nul_byte_written_around_the_cast_becomes_unreadable_at_sql_level` and
`test_a_nul_bearing_payload_written_through_the_cast_stays_readable_at_sql_level`)
so the corrected premise is enforced by the suite, not just recorded here.

**Recommended follow-up (not actioned — outside T9's scope):** correct the wording
of spec P2 AC 7, the `STATE.md` seed-data audit bullet, and AD-012's *Reason*
field. The decisions they justify all stand; only the mechanism is misstated.

---

## PostgreSQL version parity

| Environment | Postgres major version | Source |
| ----------- | ---------------------- | ------ |
| Local (Sail) | **17** | `docker-compose.yml`, `pgsql` service (T4) |
| CI (GitHub Actions) | **17** | `.github/workflows/ci.yml` service container (T7) |
| Production (Laravel Cloud) | _to be confirmed at provisioning_ | 🔒 T11/T20 — operator-gated |

Parity is the requirement, not a specific number. Local and CI are pinned to the
same major version; production must be confirmed to match when Laravel Cloud is
provisioned, and this table updated if it differs.
