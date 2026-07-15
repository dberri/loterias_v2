# LLM Provider Drivers Specification

**Source**: `docs/superpowers/specs/2026-07-11-provider-drivers-design.md` (approved design, transcribed into TLC format and re-grounded against `seo-draw-page-generation`)
**Depends on**: `seo-draw-page-generation` (hard — this feature has no meaning until `BatchContentProvider`, `ContentProviderManager`, `GenerationRequest`/`GenerationResult`/`BatchStatus` and `config/content.php` exist)
**Scope**: Anthropic and Gemini drivers behind the existing `BatchContentProvider` contract, plus a provider-agnostic contract test suite.

## Problem Statement

`seo-draw-page-generation` ships the `BatchContentProvider` seam (AD-005) with exactly one implementation: OpenAI. That seam only pays for itself when a second driver actually drops in without touching pipeline code — until then it is an untested abstraction. This feature adds Anthropic and Gemini drivers with full method parity and proves, via a shared contract suite every driver must pass, that swapping providers is a config change rather than a rewrite. It deliberately stops short of deciding *which* provider to use in production — that is `provider-cost-comparison`.

## Goals

- [ ] Anthropic and Gemini each implement all four `BatchContentProvider` methods (`submitBatch`, `pollBatch`, `fetchResults`, `generateOne`)
- [ ] A single contract test suite runs against every driver — OpenAI included — asserting identical normalized behavior from identical fixtures
- [ ] Changing `config('content.default')` from `openai` to `anthropic` or `gemini` changes which vendor generates pages, with zero changes to `CreatePages`, `CreateContent`, `CheckCompletionBatch`, or `PageAssembler`
- [ ] A one-off provider override is possible for controlled experiments without editing config

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ------- | ------ |
| Cost comparison, price-per-page measurement, provider ranking | Own spec: `docs/superpowers/specs/2026-07-11-provider-cost-comparison-stub.md` — this feature makes the comparison *possible*, it does not perform it |
| Output-quality benchmarking / scoring rubric | Same stub; quality scoring needs a fixed draw sample + rubric that does not exist yet |
| Per-game or per-draw provider routing rules | No evidence any game needs a different vendor; would be inventing a requirement. Revisit only if cost-comparison surfaces a reason |
| Extracting OpenAI logic out of `ContentCreator` into a driver | Already delivered by `seo-draw-page-generation` — see Assumptions row 1 |
| Automatic failover between providers when one is down | Not requested; a failed batch already resolves to `Failed`/re-runnable per DRAWPAGE-03/08. Adding cross-vendor failover would need a retry policy this feature has no basis to invent |

---

## Assumptions & Open Questions

Every ambiguity is resolved or recorded here — nothing is left silently unclear.

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | -------------- | --------- | ---------- |
| The source design's "extract OpenAI into `OpenAIBatchProvider`" work item | **Dropped as redundant.** `seo-draw-page-generation`'s design already creates `App\Services\Providers\OpenAiContentProvider` as a first-class `BatchContentProvider` implementation. There is nothing left to extract once that feature ships. The intent behind it — *prove the OpenAI path really conforms to the contract* — is preserved and strengthened as PROVIDER-04 (OpenAI must pass the same contract suite as the new drivers) | The source design was written without knowledge that seo's design had already promoted OpenAI to a driver. Transcribing the extraction verbatim would create a task that is already done | y — grounded against `.specs/features/seo-draw-page-generation/design.md` §Components |
| Class naming (`OpenAIBatchProvider` vs `OpenAiContentProvider`) | Follow **seo's** naming, since that class ships first: `AnthropicContentProvider`, `GeminiContentProvider` under `app/Services/Providers/` | Consistency with the code that will already exist beats consistency with a doc that never shipped | y |
| Anthropic's and Gemini's actual batch + structured-output API shape | **Not assumed.** Both drivers must be built against live official documentation at implementation time (Knowledge Verification Chain step 3/4). No request/response shape is asserted in this spec or its design | The source design explicitly declines to assert vendor API shape from memory, and so does this spec. Fabricating an API shape here would cascade into wrong tasks and wrong code | n — **must verify at implementation**; this is a required first task, not a footnote |
| A provider that has no native batch primitive | Its driver **emulates** batch semantics behind the same interface: `submitBatch` fans out concurrent single-shot calls, returns a synthetic batch id, and `pollBatch`/`fetchResults` resolve against locally persisted per-item state — preserving `custom_id` correlation. The pipeline cannot tell the difference | Keeps the contract honest: pipeline code depends on the interface's semantics, not on any vendor having a literal batch endpoint. Whether either vendor actually needs this is a verification question, not a design one | y — as a contingency; whether it triggers is unknown until the API check above |
| Where the runtime override is exposed | An optional `--provider=` option on `app:create-pages` and `app:create-content` | Smallest possible surface for "run one experiment without touching config"; matches the source design's "optional override per command execution" | y |
| Which provider `config('content.default')` ships as | `openai` — unchanged | Changing the production default is a *cost/quality* decision this feature is explicitly not qualified to make | y |

**Open questions:** none — all resolved or logged above. The one `n` row is a bounded, assigned verification task, not an unresolved decision.

---

## User Stories

### P1: Anthropic and Gemini drivers with proven contract parity ⭐ MVP

**User Story**: As the site operator, I want Anthropic and Gemini to be usable as content providers by changing one config value, so that the provider abstraction I paid for in v1 is actually load-bearing and I am not locked to OpenAI.

**Why P1**: This is the entire feature. An abstraction with one implementation is a guess; an abstraction with three implementations and a shared contract suite is a fact.

**Acceptance Criteria**:

1. WHEN the driver for `anthropic` or `gemini` is resolved from `ContentProviderManager` THEN it SHALL return an object implementing `BatchContentProvider` with all four methods (`submitBatch`, `pollBatch`, `fetchResults`, `generateOne`) functional — no method may throw "not implemented".
2. WHEN the shared contract test suite runs against any driver (`openai`, `anthropic`, `gemini`) with the same `GenerationRequest` fixtures and the same faked vendor HTTP responses THEN each driver SHALL produce identical normalized `GenerationResult` and `BatchStatus` values.
3. WHEN a vendor reports a batch state THEN the driver SHALL normalize it to exactly one of `in_progress`, `completed`, `failed`, `expired`; WHEN the vendor reports a state the driver does not recognize THEN it SHALL normalize to `failed` and log the raw vendor state verbatim.
4. WHEN `config('content.default')` is changed to `anthropic` or `gemini` THEN `app:create-pages`, `app:create-content`, and `CheckCompletionBatch` SHALL operate unchanged — no file under `app/Console/Commands/`, `app/Jobs/`, or `App\Services\PageAssembler` may reference a concrete provider class or branch on provider identity.
5. WHEN `fetchResults()` returns items THEN each SHALL be correlated to its request by `custom_id`; WHEN a result carries a `custom_id` that was never submitted, or two results carry the same `custom_id` THEN the driver SHALL fail that item (not the whole batch) and log the correlation error.
6. WHEN a driver's vendor call fails THEN it SHALL raise one of the typed errors `AuthError`, `RateLimitError`, `TransientTransportError`, or `PermanentProviderError` — never a raw vendor SDK exception — so that retry semantics are decided by error *class*, not by parsing vendor message strings.

**Independent Test**: Run the contract suite three times, once per driver, with vendor HTTP faked and identical fixtures; assert byte-identical normalized DTOs. Then set `config('content.default')` to each provider in turn, run `app:create-pages` against a seeded draw, and assert a `Page` reaches `status = Generated` with `provider` recorded correctly — with no command/job code differing between runs.

---

### P2: Per-run provider override

**User Story**: As the person evaluating providers, I want to run one generation against a non-default provider without editing config, so I can compare output side by side on the same draw.

**Why P2**: The pipeline is fully functional without it (P1 already allows switching via config), but every future cost/quality comparison depends on being able to run provider A and provider B against the *same* draw without a config edit between them.

**Acceptance Criteria**:

1. WHEN `app:create-content {game} {concurso} --provider=anthropic` runs THEN the system SHALL generate that page via the Anthropic driver regardless of `config('content.default')`, and SHALL record `provider = 'anthropic'` on the resulting `Page`.
2. WHEN `--provider=` is omitted THEN the system SHALL use `config('content.default')` (existing behavior, unchanged).
3. WHEN `--provider=` names a driver that is not configured THEN the command SHALL exit non-zero with an error naming the unknown driver, and SHALL NOT create, modify, or fail any `Page` row.

**Independent Test**: Run `app:create-content` twice on the same draw with `--provider=openai` then `--provider=anthropic`, faking both vendors; assert each run records the correct `provider` on the page. Run a third time with `--provider=bogus`; assert non-zero exit and zero `Page` writes.

---

## Edge Cases

- WHEN a driver's credentials are missing or invalid THEN it SHALL raise `AuthError` at the first vendor call, and `app:create-pages` SHALL exit non-zero **without** creating any `Page` rows in `Generating` — a batch that was never accepted must not leave orphaned pages waiting on a batch id that does not exist.
- WHEN a vendor returns a structurally valid response whose *content* fails the app's JSON schema THEN the driver SHALL return a `GenerationResult` marked invalid rather than throwing — schema enforcement is `PageAssembler`'s job (DRAWPAGE-03), and the driver must not duplicate or pre-empt it.
- WHEN a vendor rate-limits (HTTP 429 or equivalent) THEN the driver SHALL raise `RateLimitError` carrying the vendor's retry-after hint when one is supplied.
- WHEN `pollBatch` is called with a batch id the vendor does not recognize THEN the driver SHALL normalize to `failed` (not `in_progress`), so `CheckCompletionBatch` cannot self-re-dispatch forever against a batch that will never exist.
- WHEN a driver emulates batching (Assumptions row 4) THEN `pollBatch` SHALL still return `in_progress` until every fanned-out item is terminal, so `CheckCompletionBatch`'s existing polling loop needs no special-casing.

---

## Requirement Traceability

| Requirement ID | Story | Phase | Status |
| -------------- | ----- | ----- | ------ |
| PROVIDER-01 | P1: Anthropic driver — full four-method parity | Design | Pending |
| PROVIDER-02 | P1: Gemini driver — full four-method parity | Design | Pending |
| PROVIDER-03 | P1: Shared contract suite — identical normalized output across drivers | Design | Pending |
| PROVIDER-04 | P1: OpenAI driver passes the same contract suite (validates the seo-era driver) | Design | Pending |
| PROVIDER-05 | P1: Batch-state normalization, incl. unknown state → `failed` + raw log | Design | Pending |
| PROVIDER-06 | P1: Pipeline is provider-agnostic — no concrete provider reference in commands/jobs/assembler | Design | Pending |
| PROVIDER-07 | P1: `custom_id` correlation; unknown/duplicate id fails the item, not the batch | Design | Pending |
| PROVIDER-08 | P1: Typed provider errors (`AuthError`/`RateLimitError`/`TransientTransportError`/`PermanentProviderError`) | Design | Pending |
| PROVIDER-09 | P2: `--provider=` override on both commands | Design | Pending |
| PROVIDER-10 | P2: Unknown `--provider=` value → non-zero exit, zero `Page` writes | Design | Pending |
| PROVIDER-11 | Edge case — auth failure creates no orphaned `Generating` pages | Design | Pending |
| PROVIDER-12 | Edge case — schema-invalid content returns an invalid result, does not throw | Design | Pending |
| PROVIDER-13 | Edge case — unknown batch id normalizes to `failed`, not an infinite poll | Design | Pending |
| PROVIDER-14 | Edge case — emulated batching still reports `in_progress` until all items terminal | Design | Pending |

**ID format:** `PROVIDER-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 14 total, 0 mapped to tasks, 14 unmapped ⚠️ (tasks.md not yet generated)

---

## Success Criteria

- [ ] Flipping `config('content.default')` between all three providers produces a working page each time, with zero diff in `app/Console/Commands/`, `app/Jobs/`, or `PageAssembler`
- [ ] The contract suite is a single test class parameterized over drivers — adding a fourth provider means adding a driver and a fixture set, not a new test file
- [ ] `grep -r` across commands, jobs, and `PageAssembler` finds no reference to `OpenAi`, `Anthropic`, or `Gemini` by name
- [ ] Every vendor API shape used by the Anthropic and Gemini drivers is traceable to official documentation consulted at implementation time — nothing recalled from memory
