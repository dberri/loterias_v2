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
> There is also an unresolved prerequisite for the monthly tier — see
> [Open gap](#open-gap-nothing-writes-the-monthly-tier-yet) below.

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

## Open gap: nothing writes the monthly tier yet

`App\Jobs\ExportCorpus` writes only to `exports/{YYYY-MM-DD}/`. **No artifact is
ever placed under `monthly/`**, so the second rule above currently matches
nothing and the effective retention is 35 days for everything.

A lifecycle policy cannot close this on its own: S3 rules can expire objects by
age and prefix, but cannot promote "the first export of each month" into a
longer-lived tier. Selecting which artifact is the monthly one is necessarily a
writer-side decision — either a copy to the `monthly/` prefix or an object tag —
and both are application changes outside T17's scope (T17 covers the lifecycle
policy; adding untested job behaviour here would be scope creep).

**Consequence:** INFRA-17 is half met. The 35-day daily tier is fully specified
and applicable; the 12-month monthly tier is specified but unreachable until the
export promotes one artifact per month. This is recorded rather than quietly
dropped — a retention policy believed to be running when it is not is the same
class of false confidence as an unverified backup.

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
