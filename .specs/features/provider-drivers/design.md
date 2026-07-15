# LLM Provider Drivers Design

**Spec**: `.specs/features/provider-drivers/spec.md`
**Source**: `docs/superpowers/specs/2026-07-11-provider-drivers-design.md`
**Depends on**: `seo-draw-page-generation` (hard) — every symbol this design builds on is created there
**Status**: Approved architecture, **with one blocking verification task** (Anthropic/Gemini API shape — see Risks)

---

## Architecture Overview

Nothing about the pipeline changes. `CreatePages`, `CreateContent`, `CheckCompletionBatch`, and `PageAssembler` already speak only `BatchContentProvider` after `seo-draw-page-generation`. This feature adds two more classes behind that interface and one test suite that proves all three are interchangeable.

The load-bearing idea: **the contract suite is the deliverable, not the drivers.** Two drivers that each pass their own bespoke tests prove nothing about substitutability. One suite, three drivers, identical fixtures, identical asserted output — that is what makes a provider swap a config change.

```mermaid
graph TD
    A["app:create-pages / app:create-content"] --> B["ContentProviderManager::driver(?string)"]
    B -->|config('content.default') or --provider=| C{"BatchContentProvider"}
    C --> D["OpenAiContentProvider<br/>(ships with seo feature)"]
    C --> E["AnthropicContentProvider<br/>(new)"]
    C --> F["GeminiContentProvider<br/>(new)"]

    D --> G["vendor SDK / HTTP"]
    E --> G
    F --> G

    G --> H["Translator: vendor payload → normalized DTO"]
    H --> I["GenerationResult / BatchStatus"]
    I --> J["PageAssembler (unchanged)"]

    K["ProviderContractTest<br/>(parameterized over all drivers)"] -.asserts identical DTOs.-> I
```

---

## Code Reuse Analysis

### Existing Components to Leverage

| Component | Location | How to Use |
| --------- | -------- | ---------- |
| `BatchContentProvider` interface | `app/Contracts/BatchContentProvider.php` (created by seo) | **Unchanged.** If this feature needs to change the interface, that is a signal the seo-era contract was wrong — surface it rather than silently widening it |
| `ContentProviderManager` | `app/Services/ContentProviderManager.php` (created by seo) | Gains `createAnthropicDriver()` / `createGeminiDriver()` methods; the Laravel `Manager` base already resolves `driver($name)` by convention |
| `GenerationRequest`, `GenerationResult`, `BatchStatus` | created by seo | **Unchanged** — these are the contract boundary. All vendor variance is absorbed inside driver classes |
| `OpenAiContentProvider` | `app/Services/Providers/OpenAiContentProvider.php` (created by seo) | Not modified, but newly subjected to the shared contract suite (PROVIDER-04). Any conformance gap it reveals is a bug fixed here |
| `PageAssembler` | `app/Services/PageAssembler.php` (created by seo) | **Unchanged.** Schema validation stays here; drivers must not duplicate it (PROVIDER-12) |
| `config/content.php` | created by seo | Extended with `drivers.anthropic.*` and `drivers.gemini.*` blocks alongside the existing `drivers.openai.*` |
| Laravel HTTP client faking (`Http::fake()`) | framework | Backs every driver test — **no live vendor calls in the suite** |

### Integration Points

| System | Integration Method |
| ------ | ------------------ |
| Commands (`CreatePages`, `CreateContent`) | Gain an optional `--provider=` option passed straight to `ContentProviderManager::driver()`. This is the **only** command-level change in the feature |
| `CheckCompletionBatch` | Must resolve the **same** driver that submitted the batch — reads `Page::provider` for the batch, not `config('content.default')`, since the default may have changed between submit and poll |
| Filament | No change. `Page::provider` is already displayed per the seo design |

---

## Components

### `App\Services\Providers\AnthropicContentProvider`

- **Purpose**: Anthropic implementation of `BatchContentProvider`.
- **Location**: `app/Services/Providers/AnthropicContentProvider.php`
- **Interfaces**: implements `BatchContentProvider` — `submitBatch`, `pollBatch`, `fetchResults`, `generateOne`
- **Dependencies**: an HTTP client + `config('content.drivers.anthropic')`
- **API shape**: ⚠️ **deliberately unspecified.** Must be derived from official Anthropic documentation during implementation (see Risks). This design fixes the *contract*, never the vendor payload.
- **Reuses**: the normalization contract; the typed-error hierarchy below

### `App\Services\Providers\GeminiContentProvider`

- **Purpose**: Gemini implementation of `BatchContentProvider`.
- **Location**: `app/Services/Providers/GeminiContentProvider.php`
- **Interfaces**: identical to above
- **API shape**: ⚠️ same caveat — verified at implementation, not asserted here.
- **Reuses**: same

### `App\Exceptions\Providers\*` — typed provider errors

- **Purpose**: Make retry semantics a function of error *class*, so no caller ever regex-matches a vendor message string.
- **Location**: `app/Exceptions/Providers/`
- **Interfaces**:
  - `ProviderError` (abstract base)
  - `AuthError` — credentials missing/rejected. **Never retry.**
  - `RateLimitError` — carries an optional retry-after hint. **Retry with backoff.**
  - `TransientTransportError` — timeout, 5xx, connection reset. **Retry.**
  - `PermanentProviderError` — malformed request, unsupported model, quota exhausted. **Never retry.**
- **Dependencies**: none
- **Reuses**: n/a — new. Every driver's vendor-SDK exceptions are caught and re-thrown as one of these; a raw vendor exception escaping a driver is a contract violation the suite tests for.

### `App\Services\Providers\Concerns\EmulatesBatching` (contingent)

- **Purpose**: Only built **if** the API verification finds a vendor without a native batch primitive.
- **Location**: `app/Services/Providers/Concerns/EmulatesBatching.php`
- **Interfaces**: fans `submitBatch` out into concurrent `generateOne` calls, persists per-item state under a synthetic batch id, and resolves `pollBatch`/`fetchResults` from that state — reporting `in_progress` until every item is terminal (PROVIDER-14).
- **Dependencies**: cache or a small table for per-item state (decided at Tasks time, only if needed)
- **Reuses**: the driver's own `generateOne`
- **Note**: This component may never be written. It exists in the design so that an unfavorable API-verification result is a *known contingency* rather than a mid-implementation surprise that reopens the architecture.

### `Tests\Feature\Providers\ProviderContractTest`

- **Purpose**: The actual deliverable — one suite, every driver, identical fixtures, identical asserted normalized output.
- **Location**: `tests/Feature/Providers/ProviderContractTest.php`
- **Interfaces**: a PHPUnit data provider yielding each configured driver; each test method runs the full body once per driver
- **Dependencies**: `Http::fake()` with per-vendor canned payloads; shared `GenerationRequest` fixtures
- **Reuses**: n/a — new
- **Covers**: PROVIDER-01 through PROVIDER-08, PROVIDER-12, PROVIDER-13, PROVIDER-14

---

## Data Models

**No schema changes.** `Page::provider` already exists (seo design, `pages` table migration) and already stores the driver name. This feature only ever writes more distinct values into that column.

`config/content.php` gains sibling driver blocks:

```php
'default' => env('CONTENT_PROVIDER', 'openai'),

'drivers' => [
    'openai'    => [ /* existing, unchanged */ ],
    'anthropic' => ['key' => env('ANTHROPIC_API_KEY'), 'model' => env('ANTHROPIC_MODEL')],
    'gemini'    => ['key' => env('GEMINI_API_KEY'),    'model' => env('GEMINI_MODEL')],
],
```

Model identifiers are left to `env` deliberately — pinning a model string in a spec guarantees it is stale before the code ships.

---

## Error Handling Strategy

| Error Scenario | Handling | User Impact |
| -------------- | -------- | ----------- |
| Missing/invalid credentials | Driver raises `AuthError` on first vendor call. `app:create-pages` exits non-zero **before** any `Page` row is written (PROVIDER-11) | No orphaned `Generating` pages pointing at a batch that was never accepted |
| Vendor rate limit (429) | Driver raises `RateLimitError` with the vendor's retry-after hint when present | Caller can back off on error class, not on string matching |
| Vendor 5xx / timeout | Driver raises `TransientTransportError` | Queue retry applies |
| Vendor returns an unrecognized batch state | Normalized to `failed`; raw state logged verbatim (PROVIDER-05) | A vendor adding a new state can never be silently read as "still working" |
| `pollBatch` on an unknown batch id | Normalized to `failed`, not `in_progress` (PROVIDER-13) | `CheckCompletionBatch` cannot self-re-dispatch forever against a nonexistent batch |
| Result carries an unknown or duplicate `custom_id` | That **item** fails and is logged; sibling items in the batch still assemble (PROVIDER-07) | One bad correlation cannot take down an otherwise good batch |
| Vendor response is well-formed but its content violates the app's JSON schema | Driver returns a `GenerationResult` **marked invalid** — it does not throw (PROVIDER-12) | Schema enforcement stays in `PageAssembler` (DRAWPAGE-03); drivers never duplicate business validation |
| A raw vendor SDK exception escapes a driver | Contract-suite failure | Caught in CI, not production |

---

## Risks & Concerns

| Concern | Impact | Mitigation |
| ------- | ------ | ---------- |
| **Anthropic and Gemini batch + structured-output API shapes are not known and are NOT asserted anywhere in this design** | Any code written from a remembered API shape would be confidently wrong and would cascade into wrong tasks, wrong tests, and wrong drivers | **The first task in `tasks.md` is a verification task**: consult official Anthropic and Gemini documentation (Context7 → official docs → web), and record the actual batch mechanics and structured-output path in this design's Appendix *before* any driver code is written. No driver task may start until that task closes. This is the single biggest risk in the feature and it is handled by sequencing, not by guessing |
| One or both vendors may have **no native batch primitive** comparable to OpenAI's | `submitBatch`/`pollBatch`/`fetchResults` would have no direct vendor analogue, tempting a mid-build architecture change | `EmulatesBatching` (above) is pre-designed as the contingency. The interface does not bend; the driver absorbs the difference |
| `CheckCompletionBatch` may resolve the *default* provider rather than the batch's *originating* provider | If the default changes between submit and poll, the job would poll vendor B for a batch that lives at vendor A — pages stick in `Generating` forever | Job resolves the driver from `Page::provider` for that `batch_id`, never from config. Called out as an explicit AC-bearing task (PROVIDER-06 territory) and asserted in the contract suite |
| The seo-era `OpenAiContentProvider` was written against no contract suite | It may not actually conform to the normalization rules the new drivers are held to (e.g. unknown-state handling) | PROVIDER-04 subjects it to the same suite. A conformance gap found there is a **bug fixed in this feature**, not a reason to weaken the suite |
| Interface drift: the temptation to widen `BatchContentProvider` to fit an awkward vendor | Would defeat the entire purpose — a leaky abstraction is worse than none | Hard rule: vendor variance is absorbed **inside** driver classes. Any proposed interface change must be escalated as a seo-design amendment, not made silently |
| Three drivers, no cost/quality data | Someone may be tempted to pick a "winner" during this feature | Explicitly out of scope; `config('content.default')` stays `openai`. Provider selection is `provider-cost-comparison`'s job |

> All flagged concerns have a mitigation — no unmitigated risk remains open.

---

## Tech Decisions (only non-obvious ones)

| Decision | Choice | Rationale |
| -------- | ------ | --------- |
| Full four-method parity from day one | Anthropic and Gemini implement all four methods immediately; no "batch later" phase | A driver that implements only `generateOne` cannot be swapped in for production page generation, which makes it useless for the cost comparison this feature exists to enable. Partial parity is indistinguishable from no driver |
| One parameterized contract suite, not per-driver test files | `ProviderContractTest` data-provides over drivers | Per-driver test files drift: each ends up asserting what its driver *does* rather than what the contract *requires*. A shared suite makes non-substitutability a test failure |
| `CheckCompletionBatch` resolves provider from `Page::provider`, not config | Batch-originating provider is authoritative | The default can legitimately change between batch submit and batch completion (they are ~24h apart). Reading config at poll time is a latent, rare, hard-to-debug bug — designed out rather than documented around |
| Drivers do not validate business schema | They only normalize; `PageAssembler` validates | Two validation sites drift. Keeping business rules in one place is why `PageAssembler` was made the single assembly point in the seo design |
| API shape verified at implementation, not designed from memory | Documented as a blocking first task | Per the Knowledge Verification Chain: fabricating an API is worse than admitting the gap. This design fixes the contract and leaves the payload to documentation |

> **Project-level decision**: full-parity drivers + global default with per-run override is recorded as `AD-010` in `.specs/STATE.md`.

---

## Appendix: Verified Vendor API Shapes

> **Empty by design.** Populated by the blocking verification task before any driver code is written. Each entry must cite the documentation source consulted.

| Vendor | Batch submit | Batch poll | Result fetch | Structured output | Source consulted |
| ------ | ------------ | ---------- | ------------ | ----------------- | ---------------- |
| Anthropic | _pending verification_ | | | | |
| Gemini | _pending verification_ | | | | |
