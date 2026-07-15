# LLM Provider Drivers (OpenAI extraction + Anthropic + Gemini) — Design

**Date:** 2026-07-11  
**Status:** ✅ Superseded — spec + design complete at `.specs/features/provider-drivers/`  
**Depends on:** [SEO Draw-Page Generation](./2026-07-11-seo-draw-page-generation-design.md)

> **One item below was dropped in the 2026-07-13 transcription:** the "extract the OpenAI path into an
> `OpenAIBatchProvider`" work. The SEO draw-page design already creates `App\Services\Providers\OpenAiContentProvider`
> as a first-class `BatchContentProvider` implementation — once that ships, there is nothing left to extract. The
> intent behind it (*prove the OpenAI path really conforms to the contract*) is preserved as **PROVIDER-04**: OpenAI
> must pass the same shared contract suite as the new drivers. Class naming also follows SEO's (`*ContentProvider`).

## Intent

Implement production-ready provider drivers for Anthropic and Gemini behind the existing `BatchContentProvider` abstraction, and extract the current OpenAI path into an explicit `OpenAIBatchProvider` driver with no behavior change. This design intentionally excludes cost/quality benchmarking.

## Scope

### In scope

- `OpenAIBatchProvider` extraction from current flow (compatibility refactor only).
- New `AnthropicBatchProvider` with full method parity.
- New `GeminiBatchProvider` with full method parity.
- Provider manager wiring and config for default selection plus optional runtime override.
- Normalized DTO and status compatibility across all providers.
- Error normalization and provider-agnostic command/job behavior.
- Test coverage for contract parity and provider-specific mappings.

### Out of scope

- Cost comparison or automated provider ranking.
- Quality benchmarking framework.
- Per-game provider routing rules.

## Key decisions

1. **Single app contract, provider-specific translators.** Each provider owns vendor API details; app code only consumes normalized DTOs.
2. **OpenAI becomes a first-class driver.** Existing behavior is preserved while being decoupled into the same contract used by new providers.
3. **Global default provider + optional override.** Configuration remains simple and operationally predictable.
4. **Full parity from day one.** Anthropic and Gemini implement `submitBatch`, `pollBatch`, `fetchResults`, and `generateOne`.

## Architecture

The pipeline remains provider-agnostic:

- Commands/jobs call `BatchContentProvider` through `ContentProviderManager`.
- Provider implementations convert normalized requests into native API calls.
- Native responses are converted back into normalized `GenerationResult` and `BatchStatus`.
- `PageAssembler` and downstream persistence/routing remain unchanged by vendor choice.

This keeps provider substitution a configuration decision, not a workflow rewrite.

## Components

### Provider implementations

- `OpenAIBatchProvider`
  - Extract current OpenAI logic into dedicated class.
  - Keep request/response semantics unchanged.
- `AnthropicBatchProvider`
  - Implement batch submit/poll/fetch and one-shot generation.
  - Map native response structures to normalized DTOs.
- `GeminiBatchProvider`
  - Implement batch submit/poll/fetch and one-shot generation.
  - Map native response structures to normalized DTOs.

### Shared app-layer pieces

- `ContentProviderManager`
  - Resolves default driver from config.
  - Supports optional override per command execution.
- `GenerationRequest`, `GenerationResult`, `BatchStatus`
  - Remain the single contract boundary.
- Shared schema validation step before `PageAssembler`
  - Enforces parseability and required fields independent of provider.

## Data flow

### Batch path

1. `app:create-pages` builds normalized generation requests and resolves provider.
2. Provider `submitBatch` returns native batch id; app stores `batch_id`, `provider`, `status=Generating`.
3. `CheckCompletionBatch` resolves same provider and calls `pollBatch`.
4. On completion, `fetchResults` returns normalized results keyed by `custom_id`.
5. `PageAssembler` builds page blocks/content; pages become `Generated`/`Published` (or `Failed`).

### Synchronous path

1. `app:create-content` resolves provider and calls `generateOne`.
2. Response follows the same normalization and assembly path used by batch results.

## Error handling

- Normalize vendor states to `in_progress|completed|failed|expired`.
- Unknown vendor states are treated as explicit failures with raw state logged.
- Correlation via `custom_id` is mandatory; missing/duplicate IDs fail item-level processing.
- Use typed provider errors to preserve retry semantics:
  - `AuthError`
  - `RateLimitError`
  - `TransientTransportError`
  - `PermanentProviderError`
- Structured-output/schema failures mark page `Failed` with actionable metadata.

## Configuration

`config/content.php` keeps a single default driver and model/credential settings per provider:

- `default`: `openai|anthropic|gemini`
- `drivers.openai.*`
- `drivers.anthropic.*`
- `drivers.gemini.*`

Commands may accept an optional provider override for controlled experiments without changing defaults.

## Testing strategy

### Contract tests (all drivers)

- Same fixtures, same expected normalized outputs.
- Verify method parity and identical status normalization behavior.

### Provider-specific tests

- Request payload construction per API.
- Batch state mapping and polling transitions.
- Result parsing and `custom_id` correlation handling.
- Structured-output parsing edge cases.

### Feature tests

- Commands/jobs remain provider-agnostic by faking manager/provider resolution.
- Validate no command/job branching on provider-specific classes.
- Validate OpenAI extraction has no regressions versus current behavior.

## Acceptance criteria

1. Anthropic and Gemini both support full `BatchContentProvider` parity.
2. OpenAI logic is extracted to `OpenAIBatchProvider` with no behavior regression.
3. Provider swap via config/override works without command/job code changes.
4. Result correlation and schema validation behavior are consistent across providers.

## Implementation notes

- Verify Anthropic and Gemini current API details during implementation (batch mechanics and structured-output path), but keep normalization contract fixed.
- If provider APIs differ materially, adaptation occurs inside provider classes only.

## Deferred backlog

Cost/quality comparison remains a separate follow-up spec and is not required for this implementation.
