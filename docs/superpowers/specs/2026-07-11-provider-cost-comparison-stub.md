# LLM Provider Cost Comparison — Spec Stub

**Date:** 2026-07-11
**Status:** Backlog (deferred)
**Depends on:** [Provider Drivers Design](./2026-07-11-provider-drivers-design.md)

## Intent

Compare cost + output quality across already-implemented providers and define a production selection policy.

## Context

Provider implementation work is now captured in:

- [LLM Provider Drivers (OpenAI extraction + Anthropic + Gemini) — Design](./2026-07-11-provider-drivers-design.md)

This stub now tracks only the deferred cost/quality comparison work.

## Open questions

- What fixed draw sample and rubric should be used for quality scoring?
- Which cost inputs should be normalized (input/output tokens, retries, failed runs)?
- How should tie-breakers work when cost is lower but quality is borderline?
