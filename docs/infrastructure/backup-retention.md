# Backup artifact retention

**Requirement**: INFRA-17 — daily artifacts retained 35 days, monthly artifacts retained 12 months.
**Task**: T17
**Date**: 2026-07-18

> ## ⚠️ Status: DOCUMENTED, NOT APPLIED
>
> No object-storage bucket has been provisioned for this project yet. Everything
> below is the configuration to apply, written to be reproducible; **none of it
> is currently in force.** Until a bucket exists and the policy below is applied
> to it, artifacts accumulate forever and INFRA-17 is unmet.
>
> The monthly tier's writer IS implemented and tested — see
> [How the monthly tier gets written](#how-the-monthly-tier-gets-written) below.

---

## Why a lifecycle policy and not application code

Retention is enforced by the **bucket's lifecycle policy**, never by a scheduled
job that deletes old objects.

A lifecycle policy cannot forget to run. A cleanup job can: it can be unscheduled
by a bad deploy, fail silently, or be starved by a queue backlog — and every one
of those failures is invisible, because the symptom is *files that are still
there*, which looks exactly like a working system. The failure mode of the
lifecycle policy is the reverse: if the rule is wrong, objects disappear, which
is loud.

The same reasoning is why the retention window is not configurable from the
application. Anything the app can change, a bug in the app can change.

---

## Prefix layout

| Prefix | Contents | Written by | Retention |
| ------ | -------- | ---------- | --------- |
| `exports/{YYYY-MM-DD}/` | `draws.ndjson`, `pages.ndjson`, `manifest.json` | `App\Jobs\ExportCorpus`, nightly at 03:30 | 35 days |
| `monthly/{YYYY-MM}/` | a promoted copy of one daily export per month | **nothing yet — see the open gap** | 365 days |

**The two prefixes must be siblings, not nested.** Putting monthly artifacts at
`exports/monthly/…` would place them inside the daily rule's prefix, and S3
applies every matching rule — so the 35-day expiration would delete the monthly
copies too, silently reducing 12-month retention to 35 days. This is the single
easiest way to get this configuration wrong.

---

## Applied configuration

### AWS CLI

Save as `lifecycle.json`:

```json
{
  "Rules": [
    {
      "ID": "daily-exports-expire-after-35-days",
      "Status": "Enabled",
      "Filter": { "Prefix": "exports/" },
      "Expiration": { "Days": 35 },
      "AbortIncompleteMultipartUpload": { "DaysAfterInitiation": 7 }
    },
    {
      "ID": "monthly-exports-expire-after-12-months",
      "Status": "Enabled",
      "Filter": { "Prefix": "monthly/" },
      "Expiration": { "Days": 365 },
      "AbortIncompleteMultipartUpload": { "DaysAfterInitiation": 7 }
    }
  ]
}
```

Apply and then verify — applying without reading it back is the same mistake the
export itself refuses to make:

```bash
aws s3api put-bucket-lifecycle-configuration \
  --bucket "$BACKUP_AWS_BUCKET" \
  --lifecycle-configuration file://lifecycle.json

aws s3api get-bucket-lifecycle-configuration --bucket "$BACKUP_AWS_BUCKET"
```

### Terraform

```hcl
resource "aws_s3_bucket_lifecycle_configuration" "backups" {
  bucket = aws_s3_bucket.backups.id

  rule {
    id     = "daily-exports-expire-after-35-days"
    status = "Enabled"
    filter { prefix = "exports/" }
    expiration { days = 35 }
    abort_incomplete_multipart_upload { days_after_initiation = 7 }
  }

  rule {
    id     = "monthly-exports-expire-after-12-months"
    status = "Enabled"
    filter { prefix = "monthly/" }
    expiration { days = 365 }
    abort_incomplete_multipart_upload { days_after_initiation = 7 }
  }
}
```

`365` days rather than a calendar year of months: S3 lifecycle expiration is
expressed in days only. Twelve monthly artifacts are always covered, because the
oldest one is at most 11 months plus a few days old when the next is written.

### Non-AWS object storage

The `backups` disk uses the `s3` driver, which also covers Cloudflare R2,
MinIO, DigitalOcean Spaces, and Backblaze B2 (S3-compatible endpoint). All four
accept the same `PutBucketLifecycleConfiguration` call, so the `lifecycle.json`
above transfers unchanged; only the `--endpoint-url` differs. This portability is
deliberate — Layer 2 exists to survive losing the provider, so its retention
configuration should not be provider-locked either.

---

## How the monthly tier gets written

`App\Jobs\ExportCorpus` promotes an export into `monthly/{YYYY-MM}/` whenever
that month has no artifact yet, then verifies the copied bytes against the same
manifest checksums before writing the manifest. A half-copied month therefore
never carries a manifest vouching for it, and a monthly artifact is checkable by
exactly the same procedure as a daily one.

**Why this is application code and not a lifecycle rule.** S3 rules expire
objects by prefix, tag and age; they cannot express "keep the first export of
each month". Selecting the monthly artifact is inherently writer-side, so
INFRA-17's original "enforced by lifecycle policy, not application code" was
unsatisfiable for this half. The requirement was amended rather than quietly
left broken — see **AD-013**.

**Promotion keys on absence, not on the calendar.** The job asks "does this
month have an artifact?", not "is today the 1st". A month whose first nights
failed is still covered by the next successful run, matching the self-healing
posture of AD-007. A "promote only on the 1st" rule would lose an entire month
to one bad night.

**The sibling-prefix rule is load-bearing.** See the Prefix layout section:
nesting `monthly/` under `exports/` would place the 12-month tier inside the
35-day rule's prefix and silently delete it. The test
`test_the_monthly_tier_is_a_sibling_of_the_daily_prefix_not_nested_inside_it`
exists to make that mistake fail loudly.

**Status:** the writer is implemented and tested. The lifecycle rules themselves
are **still unapplied** — no bucket exists yet. Until they are applied, nothing
expires at all.

---

## Verifying it after an account change

Retention is the sort of configuration that survives in someone's memory and
nowhere else, so after any account, bucket, or provider change:

1. `aws s3api get-bucket-lifecycle-configuration --bucket "$BACKUP_AWS_BUCKET"` returns both rules, both `Enabled`.
2. `aws s3 ls "s3://$BACKUP_AWS_BUCKET/exports/"` shows no prefix older than 35 days.
3. The oldest object under `monthly/` is no more than ~12 months old (once the gap above is closed).

Record the date of the check here when it is performed.

| Date verified | Bucket | Result |
| ------------- | ------ | ------ |
| _not yet performed — no bucket provisioned_ | — | — |
