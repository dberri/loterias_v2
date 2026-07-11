# LLM Provider Cost Comparison — Spec Stub

**Date:** 2026-07-11
**Status:** Backlog (not yet designed)
**Depends on:** [SEO Draw-Page Generation](./2026-07-11-seo-draw-page-generation-design.md) (implements the `BatchContentProvider` interface it defines)

## Intent

Build Anthropic and Gemini drivers behind the `BatchContentProvider` interface and compare cost + output quality against OpenAI, then pick the cheapest provider that meets the quality bar.

## Likely scope

- `AnthropicBatchProvider` (Message Batches + structured output) — verify current API via the `claude-api` skill.
- `GeminiBatchProvider` (batch mode + `responseSchema`) — verify current API.
- Normalize each to the interface's `submitBatch` / `pollBatch` / `fetchResults` / `generateOne`.
- Cost + quality benchmark on a fixed sample of draws (same prompt/context, compare token cost and page quality).

## Open questions

- Do all three support async batch with per-request `custom_id` correlation the same way? (Confirm live.)
- Structured-output fidelity per provider (does the JSON stay schema-valid without repair?).
- Is a single default provider enough, or route per-game / per-cost-tier?
