# SEO Draw-Page Generation Specification

**Source**: `docs/superpowers/specs/2026-07-11-seo-draw-page-generation-design.md` (approved design, transcribed into TLC format — no decisions re-opened)
**Scope**: The individual draw page (`/{game}/resultado/{concurso}`) and its AI generation pipeline.

## Problem Statement

Scraped draws (`Draw::raw_data`) currently produce, at best, a `DrawPage` row whose `title`/`content` sit at `'Pending'` until a batch job fills them in with unstructured prose — there's no guarantee the numbers in that prose match the facts, no fixed SEO structure, and no publish gate. The commercial goal is ad traffic, and a results site that gets a number wrong loses trust and rankings fast. This feature turns each draw into a high-quality, structurally consistent, factually guaranteed content page whose layout and prose are AI-managed but whose facts are app-owned.

## Goals

- [x] Every draw without a page can be turned into a batch-generated, fact-accurate, SEO-structured `Page` reachable at `/{game}/resultado/{concurso}` once published
- [x] Drawn numbers, prizes, and winner counts displayed on a draw page always trace back to `Draw::raw_data`, never to AI output
- [x] A single page model (Fabricator `Page`) replaces the separate `DrawPage` table, so routing/rendering/admin editing come for free
- [x] The LLM provider is swappable (OpenAI ships in v1) without touching pipeline code

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
| ------- | ------ |
| Scheduled/hands-off scrape → generate → publish automation | Own spec: `docs/superpowers/specs/2026-07-11-automation-and-scheduling-stub.md` |
| Laravel Cloud deploy, Postgres migration, backups | Own spec: `docs/superpowers/specs/2026-07-11-infrastructure-cloud-postgres-backups-stub.md` |
| Additional Caixa games beyond Mega-Sena / Lotofácil / Quina | Own spec: `docs/superpowers/specs/2026-07-11-additional-lotteries-stub.md` |
| Player tools (number generator, "did I win?" checker, simulator) | Own spec: `docs/superpowers/specs/2026-07-11-player-tools-stub.md`; the blocks they need (`SimulationBlock`, `NumberGeneratorBlock`, `TimelineBlock`, `StatisticsCardsBlock`, `ComparisonTableBlock`, `LatestResultsBlock`) are parked, not deleted |
| Anthropic / Gemini provider drivers + cost comparison | Own spec: `docs/superpowers/specs/2026-07-11-provider-cost-comparison-stub.md`; v1 ships the `BatchContentProvider` interface + OpenAI driver only |
| Pillar/home/results-listing pages | Out of the individual-draw-page boundary; different spec per `docs/arquitetura-da-informação.md` |

---

## Assumptions & Open Questions

Every ambiguity is resolved or recorded here — nothing is left silently unclear. This feature transcribes an already-approved design doc rather than opening fresh discussion, so items the source doc itself flagged as "verify during implementation" are carried over as logged assumptions, not re-litigated.

| Assumption / decision | Chosen default | Rationale | Confirmed? |
| --------------------- | --------------- | --------- | ---------- |
| Fabricator route resolution for a slash-containing slug (`mega-sena/resultado/2500`) | Try Fabricator's native slug routing first; if it can't resolve a slug containing `/`, fall back to an explicit `Route::get('/{game}/resultado/{concurso}')` that resolves the `Page` by `draw_id` | Source design flags this as unverified; either way the public URL scheme is fixed, so the fallback is a routing-layer implementation detail, not a spec change | n — verify during implementation (Design §5) |
| Anthropic/Gemini batch + structured-output API shape | Not assumed for v1; only the OpenAI driver is built against `BatchContentProvider` | Source design explicitly declines to assert vendor API shape from memory; those drivers are a separate spec (`provider-cost-comparison-stub.md`) that will verify against live docs when built | n — deferred, not needed for this feature |
| Where prompt/schema versions live (`config/prompts` vs. a versioned class) | A dedicated, diffable, version-controlled location under `app/` (final shape decided at Task-breakdown time, not a behavioral requirement) | Source design says "keep prompt versions in a dedicated, diffable location (e.g. ...)" without committing to one; it's a code-organization choice, not a testable behavior | y — non-behavioral, low-risk to leave open |
| Batch-level failure (`expired`/`cancelled`, not per-draw) handling | All `Page` rows tied to that `batch_id` transition to `status = Failed` and the failure is logged | Found during codebase grounding: `CheckCompletionBatch::handle()` currently just `return`s on `expired`/`cancelled`, leaving pages stuck in `Generating` forever with no operator signal. The source design's stated failure semantics ("Failed, logged, re-runnable") are extended here to cover the batch-level case, since leaving pages permanently stuck contradicts that stated intent | y — consistent extension of an explicit source-doc principle, not a new decision |

**Open questions:** none — all resolved or logged above.

---

## User Stories

### P1: Batch-generated, fact-accurate draw page ⭐ MVP

**User Story**: As the site's SEO/content owner, I want every scraped draw to become a structurally consistent, factually accurate content page once reviewed and published, so that it ranks for queries like "resultado mega-sena concurso 2500" without ever showing a wrong number.

**Why P1**: This is the core pipeline — data model, block catalog, AI contract, provider abstraction, batch pipeline, rendering/routing, and the publish gate. Nothing else in this feature (or the dependent backlog specs) works without it.

**Acceptance Criteria**:

1. WHEN `app:create-pages {game} {quantity}` runs THEN the system SHALL select up to `quantity` draws of that game without a page (`Draw::scopeWithoutPage`), build one `GenerationRequest` per draw (`custom_id = page_{type}_{concurso}`), submit them as a single provider batch via `submitBatch()`, and create one `Page` row per draw with `status = Generating`, `batch_id`, and `provider` set.
2. WHEN a draw's AI response parses successfully against the enforced JSON schema (valid `type` enum values, no disallowed duplicates, all required prose non-empty) THEN the system SHALL assemble that page's `blocks` as `[hero, results-grid, draw-details]` + the AI's ordered enrichment blocks + `[related-links]`, set `title`/`slug`/`meta_description` from the AI response, and set `status = Generated` (or `Published` directly when `config('content.auto_publish')` is `true`) with `generated_at` set to the current time.
3. WHEN a draw's AI response fails to parse, includes an enrichment block with an unknown/disallowed `type`, or has empty/missing required prose THEN the system SHALL leave that page's `status = Failed`, write a log entry describing the failure, and leave the page re-runnable (no partial/corrupt `blocks` written).
4. WHEN any anchored block (`hero`, `results-grid`, `draw-details`, `related-links`) renders THEN the system SHALL resolve every factual value (drawn numbers, concurso, date, prize tiers, winners by faixa, location, accumulated flag, next-draw estimate, prev/next concurso, sibling games) live from `Draw::raw_data` via `mutateData()` — the AI response SHALL NOT be the source of any factual number.
5. WHEN a `Page` has `status = Published` THEN requesting `/{game}/resultado/{concurso}` SHALL render that page's full block list; WHEN a `Page` has `status = Generating`, `Generated`, or `Failed` THEN the same public route SHALL return 404 (the page remains viewable via the Filament admin preview).
6. WHEN a `Published` draw page is rendered THEN the layout head SHALL include `Article` and `FAQPage` JSON-LD built from draw facts and the `faq` enrichment block's AI-authored Q&A (when present).
7. WHEN `CheckCompletionBatch` polls a batch that is still `in_progress` THEN it SHALL re-dispatch itself after a 10-minute delay (existing behavior, unchanged); WHEN the batch reaches `completed` THEN it SHALL fetch results keyed by `custom_id` and route each through the shared `PageAssembler` per criteria 2–3 above.

**Independent Test**: Seed a `Draw` without a page, run `app:create-pages`, fake the provider at the `BatchContentProvider` interface to return a valid schema-conforming response, dispatch `CheckCompletionBatch` synchronously, assert the resulting `Page` has `status = Generated` with the expected block order; publish it via Filament and assert `GET /{game}/resultado/{concurso}` returns 200 with the drawn numbers from `raw_data` present in the rendered HTML and JSON-LD.

---

### P2: Synchronous single-draw regeneration for prompt tuning

**User Story**: As the person tuning the AI prompt, I want to regenerate one draw's page synchronously and see the result in seconds, so I can iterate on prompt/schema quality without waiting on a batch turnaround.

**Why P2**: Not required for the pipeline to function end-to-end, but the source design calls out prompt quality as "the core, most-struggled-with piece" — without a fast iteration loop, tuning the P1 pipeline is impractical.

**Acceptance Criteria**:

1. WHEN `app:create-content {game} {concurso}` runs THEN the system SHALL call the provider's `generateOne()` for that draw and route the result through the same `PageAssembler` used by the batch path (P1 AC 2), producing an identically structured page without submitting a batch.
2. WHEN the synchronous call's response fails the same validation as P1 AC 3 THEN the system SHALL apply the identical failure handling (`status = Failed`, logged, re-runnable).

**Independent Test**: Run `app:create-content` against a seeded draw with the provider faked at the interface; assert the resulting `Page`'s `blocks` match what the batch path would produce for the same fake response, with no batch/queue involvement.

---

### P3: Auto-publish flag for trusted pipelines

**User Story**: As the site operator, once I trust the AI output, I want successfully generated pages to publish immediately instead of waiting for manual review, so the pipeline can eventually run unsupervised.

**Why P3**: Explicitly the seam a later automation sub-project builds on (`automation-and-scheduling-stub.md`), not something exercised in normal v1 operation — v1 defaults to manual review.

**Acceptance Criteria**:

1. WHEN `config('content.auto_publish')` is `false` (the default) THEN a successfully assembled page SHALL land at `status = Generated`, not publicly reachable, pending manual promotion to `Published` via Filament.
2. WHEN `config('content.auto_publish')` is `true` THEN a successfully assembled page SHALL be written directly with `status = Published`.

**Independent Test**: Toggle the config value in two test runs against the same faked provider response; assert the resulting `Page::status` differs accordingly and that only the `Published` case is reachable at the public route.

---

## Edge Cases

- WHEN a batch reaches `expired` or `cancelled` (not a per-draw failure) THEN the system SHALL set `status = Failed` on every `Page` row tied to that `batch_id` and log the batch-level failure (see Assumptions table — extends existing silent no-op).
- WHEN `enrichment_blocks` contains a `type` that is not allowed to repeat, repeated THEN the system SHALL treat the whole response as invalid and apply P1 AC 3's failure handling.
- WHEN a draw has no `faq` enrichment block in the AI response THEN the system SHALL omit `FAQPage` JSON-LD for that page rather than emitting an empty/invalid block.
- WHEN `app:create-pages` is run with a `quantity` larger than the number of draws currently without a page THEN the system SHALL process only the draws actually available, without erroring.
- WHEN a draw already has a `Page` (any status) THEN `Draw::scopeWithoutPage()` SHALL exclude it from selection by `app:create-pages`, preventing duplicate generation.

---

## Requirement Traceability

Each requirement gets a unique ID for tracking across design, tasks, and validation.

| Requirement ID | Story | Phase | Status |
| --------------- | ------------------------- | ------ | ------- |
| DRAWPAGE-01 | P1: Batch-generated page — batch submission | Execute | Done |
| DRAWPAGE-02 | P1: Batch-generated page — successful assembly | Execute | Done |
| DRAWPAGE-03 | P1: Batch-generated page — validation failure handling | Execute | Done |
| DRAWPAGE-04 | P1: Batch-generated page — facts sourced live, never from AI | Execute | Done |
| DRAWPAGE-05 | P1: Batch-generated page — publish gate / public routing | Execute | Done |
| DRAWPAGE-06 | P1: Batch-generated page — JSON-LD | Execute | Done |
| DRAWPAGE-07 | P1: Batch-generated page — batch polling/completion | Execute | Done |
| DRAWPAGE-08 | Edge case — batch-level `expired`/`cancelled` handling | Execute | Done |
| DRAWPAGE-09 | Edge case — disallowed duplicate enrichment block type | Execute | Done |
| DRAWPAGE-10 | Edge case — missing `faq` block omits `FAQPage` JSON-LD | Execute | Done |
| DRAWPAGE-11 | Edge case — `quantity` exceeds available draws | Execute | Done |
| DRAWPAGE-12 | Edge case — `scopeWithoutPage` prevents duplicate generation | Execute | Done |
| DRAWPAGE-13 | P2: Synchronous regeneration — same assembler | Execute | Done |
| DRAWPAGE-14 | P2: Synchronous regeneration — failure parity | Execute | Done |
| DRAWPAGE-15 | P3: Auto-publish flag — default manual gate | Execute | Done |
| DRAWPAGE-16 | P3: Auto-publish flag — direct publish when enabled | Execute | Done |

**ID format:** `DRAWPAGE-NN`

**Status values:** Pending → In Design → In Tasks → Implementing → Verified

**Coverage:** 16 total, 16 mapped to tasks, 0 unmapped ✅ (see `tasks.md` → Requirement Coverage)

---

## Success Criteria

How we know the feature is successful:

- [x] A draw with no page can go from `app:create-pages` → batch completion → `Generated` → (manual or auto) `Published` → live at `/{game}/resultado/{concurso}` with zero manual data entry
- [x] No AI-authored number ever appears on a rendered draw page — every fact traces to `Draw::raw_data` via `mutateData()`
- [x] A malformed/invalid AI response never produces a partially-assembled or publicly reachable page — it always resolves to `Failed`, logged, re-runnable
- [x] `DrawPage` model/table are fully retired; all draw content lives on Fabricator `Page` rows
