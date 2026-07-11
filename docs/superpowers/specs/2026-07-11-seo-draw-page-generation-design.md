# SEO Draw-Page Generation — Design

**Date:** 2026-07-11
**Status:** Approved (design), pending implementation plan
**Scope:** The individual draw page (`/{game}/resultado/{concurso}`) and its AI generation pipeline. Everything else from the original brain-dump is decomposed into separate specs (see Backlog).

## Goal

Turn each scraped lottery draw into a high-quality, SEO-oriented content page whose **layout and prose are AI-managed** but whose **facts are app-owned**. This is the core, most-struggled-with piece of the project: making each draw page read well, stay factually correct, and rank for queries like *"resultado mega-sena concurso 2500"*. Once this is pinned, the deferred sub-projects (automation, infra, more lotteries, tools) build on it.

## Key Decisions

1. **App owns facts, AI owns prose.** Drawn numbers, prizes, and winner counts always come from `Draw::raw_data`. The LLM never emits factual numbers — it only writes narrative that *references* facts we provide. This is a hard guardrail: wrong numbers on a results site are a trust- and SEO-killer.
2. **Hybrid layout.** A fixed spine of anchored factual blocks (hero, results grid, draw details) guarantees SEO structure + consistency. The AI freely chooses, orders, and writes a set of enrichment blocks in between. `related-links` is anchored at the bottom.
3. **One page model.** Draw pages are Fabricator `Page` rows. The separate `DrawPage` model/table is dropped; `pages` gains `draw_id`, `batch_id`, `provider`, `status`, `generated_at`. Fewer moving parts; free routing/rendering/admin editor.
4. **URL scheme:** `/{game}/resultado/{concurso}` (e.g. `/mega-sena/resultado/2500`) — high-intent "resultado" keyword in the path, stable/unique concurso as ID.
5. **Generation mechanism:** one structured batch response per draw (approach A). The LLM returns an ordered enrichment-block list + prose + SEO title/meta in a single schema-constrained JSON.
6. **Provider-agnostic LLM layer** so OpenAI / Anthropic / Gemini are swappable on cost. v1 ships the OpenAI driver.
7. **Publish gate:** `Generating → Generated → Published` (+ `Failed`). Pages hold in `Generated` for review while the prompt is tuned; a config flag flips to auto-publish for unsupervised operation later.

## 1. Data Model

- **`App\Models\Page`** — subclass of `Z3d0X\FilamentFabricator\Models\Page`, registered via the `filament-fabricator.page-model` config key so we can add columns and casts.
- **Migration on `pages`** — add:
  - `draw_id` — nullable FK → `draws`, `nullOnDelete`, unique
  - `batch_id` — nullable string, indexed
  - `provider` — nullable string (which LLM provider owns the batch)
  - `status` — string, default `pending`, indexed (cast to `PageStatus`)
  - `generated_at` — nullable timestamp
  - Keep Fabricator's `title`, `slug`, `layout`, `blocks`, `parent_id`.
- **`App\Enums\PageStatus`** — `Generating`, `Generated`, `Published`, `Failed`.
- **`Draw::page()`** — `hasOne(Page::class)` on `draw_id`; `scopeWithoutPage()` unchanged.
- **`DrawPage` model + `draw_pages` table are dropped.** Existing `DrawPage` rows are discarded and regenerated (content format changes entirely). A migration drops the table.

## 2. Block Catalog (v1)

### Anchored blocks — app-assembled, fixed positions, facts resolved live from the `Draw` via `mutateData()`

| Pos | Block | Facts (app) | Prose (AI) |
|-----|-------|-------------|------------|
| 1 | `hero` | drawn numbers, concurso, date | `hero_headline`, `hero_subtitle` |
| 2 | `results-grid` | drawn numbers | — |
| 3 | `draw-details` | prize tiers, winners by faixa, location, accumulated flag, next-draw estimate | — |
| last | `related-links` | prev/next concurso, pillar page, sibling games | — |

### AI-eligible enrichment blocks — AI selects a subset, orders them, writes their prose; any numbers inside are app-computed at render

- `rich-text` — journalistic narrative body (AI writes HTML)
- `hot-cold-analysis` — AI `commentary`; frequency numbers computed from historical draws
- `comparison-previous` — AI `commentary`; diff vs previous concurso computed by app
- `faq` — AI Q&A pairs (feeds FAQ JSON-LD)
- `how-to-play` — AI evergreen per-game prose

**Final assembled page** = `[hero, results-grid, draw-details]` + `[AI enrichment blocks, in AI's order]` + `[related-links]`.

We reuse/repair the matching existing block classes (`HeroSectionBlock`, `ResultsGridBlock`, `FaqBlock`, `RichTextContentBlock`, etc.). Blocks meant for pillar/tool/home pages (`SimulationBlock`, `NumberGeneratorBlock`, `TimelineBlock`, `StatisticsCardsBlock`, `ComparisonTableBlock`, `LatestResultsBlock`) are **out of scope** here — parked for later sub-projects.

## 3. AI Generation Contract + SEO Prompt Strategy

### Response schema (enforced via provider structured-output; guaranteed to parse)

```json
{
  "title": "Resultado da Mega-Sena concurso 2500: números e premiação",
  "meta_description": "Confira o resultado da Mega-Sena concurso 2500 de 21/08/2025: dezenas sorteadas, ganhadores e valores.",
  "hero_headline": "Mega-Sena 2500 acumula e prêmio vai a R$ 50 milhões",
  "hero_subtitle": "Ninguém acertou as seis dezenas no sorteio de quinta-feira",
  "enrichment_blocks": [
    { "type": "rich-text", "html": "<p>…</p>" },
    { "type": "hot-cold-analysis", "commentary": "…" },
    { "type": "faq", "items": [{ "q": "…", "a": "…" }] },
    { "type": "how-to-play", "html": "<p>…</p>" }
  ]
}
```

- `type` is an `enum` limited to the AI-eligible set. Server-side validation additionally rejects duplicates-where-not-allowed and empty prose.
- The LLM never emits factual numbers as data — only prose referencing the facts it is given.

### Factual context (read-only input to the model)

Reuses the existing `replacePlaceholders` field set: drawn numbers, date, location, accumulated flag, prize tiers + winner counts per faixa, winner cities, next-draw date/estimate — passed as a compact labelled block the prose must draw from.

### System prompt strategy (the artifact we iterate on to fix quality)

- Brazilian Portuguese, journalist persona; **congratulatory** tone when there is a jackpot winner, **anticipation** tone when it accumulates (keeps existing tone logic).
- SEO directives: a *unique angle* per draw (avoid cross-concurso boilerplate), natural use of *"resultado {jogo} concurso {n}"* + date in the opening, scannable subheadings, depth without padding, E-E-A-T-style factual confidence.
- FAQ items target real searcher questions ("Quanto pagou a Mega-Sena 2500?", "Quais os números mais sorteados?", "Quando é o próximo sorteio?").
- Explicit "do not invent or alter any number, prize, date, or winner count" guardrail.

### Schema.org

The app (not the AI) renders `Article` + `FAQPage` JSON-LD in the layout head from draw facts + the FAQ block, for rich results.

### Prompt as a living artifact

The system prompt + json_schema are the things tuned most. Keep prompt versions in a dedicated, diffable location (e.g. `config/prompts` or a versioned class). The synchronous `app:create-content` command runs the *same* assembler as the batch path, so a single page can be regenerated and eyeballed in seconds during tuning — no batch wait.

## 4. Pipeline + Provider-Agnostic LLM Layer

### Provider abstraction

- **`BatchContentProvider` interface:**
  - `submitBatch(iterable<GenerationRequest>): string` — returns a batch id
  - `pollBatch(string $id): BatchStatus` — normalized: `in_progress` / `completed` / `failed` / `expired`
  - `fetchResults(string $id): iterable<GenerationResult>` — keyed by `custom_id`
  - `generateOne(GenerationRequest): GenerationResult` — synchronous, for the iteration path
- **Normalized DTOs:** `GenerationRequest { customId, systemPrompt, context, jsonSchema }`, `GenerationResult { customId, parsedJson | error }`. Nothing downstream knows which vendor produced the page.
- Each provider impl encapsulates its own batch mechanics **and** its own structured-output mechanism (OpenAI json_schema, Anthropic tool-use/JSON, Gemini responseSchema — all normalize to "JSON matching this schema").
- **Selection** via a Laravel `Manager` (`ContentProviderManager`) + `config/content.php` (`default` driver, per-driver `model` + credentials). Commands and the job depend on the interface, resolved from the container.

### Pipeline (provider-agnostic)

- **`app:create-pages {game} {quantity}`** — selects draws-without-a-page, builds `GenerationRequest`s (`custom_id = page_{type}_{concurso}`), calls `submitBatch`, creates `Page` rows in `Generating` with `batch_id` + `provider`.
- **`CheckCompletionBatch`** (existing self-re-dispatching job) — `pollBatch`; on `completed`, `fetchResults` → shared **`PageAssembler`** → `Published`/`Failed` pages. Reads `provider` from the pages tied to the batch.
- **`app:create-content {game} {concurso}`** — `generateOne` → same `PageAssembler`, for prompt iteration.
- Scraping commands (`app:scrape-draw`, `app:scrape-draws`) are unchanged.

### Verification flagged for implementation (not asserted from memory)

The current OpenAI Batch API shape, and whether/how Anthropic (Message Batches) and Gemini expose batch + structured output **today**, get confirmed against live docs/SDKs when each driver is built (use the `claude-api` skill for the Anthropic path). v1 ships the **OpenAI driver** (matches the existing `openai-php/laravel` dependency); the interface + config make the others drop-in later.

## 5. Rendering & Routing

- Draw pages are Fabricator `Page`s and render through Fabricator's layout + blocks.
- **Open item to verify during implementation:** whether Fabricator routing handles a slash-containing slug (`mega-sena/resultado/2500`). If not, fallback is an explicit `Route::get('/{game}/resultado/{concurso}')` resolving the `Page` by draw. Either way the URL scheme is fixed.
- A `draw-page` layout renders the block list; anchored/hybrid blocks pull live facts + computed stats from the `Draw` via `mutateData()`. JSON-LD in the layout head.
- **Only `Published` pages are served publicly**; `Generated`/`Failed` return 404 (viewable via Filament admin preview).

## 6. Validation & Publish Gate

- Structured outputs make the response conform; still validate server-side (types, `type` enum, required non-empty prose).
- Failure path: `status = Failed`, logged (replacing the current silent `// @todo log error` spots), re-runnable.
- **Publish gate:** while tuning the prompt, generated pages land in `Generated` (not public); flip to `Published` from Filament after review. Config flag `content.auto_publish` (default `false`) makes the pipeline write `Published` directly once output is trusted — the seam the automation sub-project builds on.

## 7. Testing

- **Unit:**
  - `PageAssembler`: fake `GenerationResult` JSON + `Draw` fixture → correct ordered `blocks`, title, slug; covers malformed/invalid-JSON → `Failed`.
  - Factual-context builder (reused `replacePlaceholders`) against realistic `raw_data` fixtures.
  - JSON-schema validation: valid / unknown-`type` / empty-prose fixtures.
- **Feature:**
  - `app:create-pages` builds correct `GenerationRequest`s, marks pages `Generating` (provider faked at the interface — no live HTTP).
  - `CheckCompletionBatch` turns a sample results batch into correctly assembled, published `Page`s.
  - Routing: a `Published` draw page renders expected HTML + `Article`/`FAQPage` JSON-LD; `Generated`/`Failed` → 404.
- Tests fake at the **`BatchContentProvider` interface**, so the suite is provider-agnostic and needs no live API. `Draw` factories get realistic `raw_data` fixtures seeded from real Caixa responses.
- **Prompt eval (dev workflow, not CI):** `app:create-content` on a handful of seeded draws to eyeball output while tuning.

## Out of Scope (see Backlog)

Automation/scheduling, infrastructure (Laravel Cloud, Postgres migration, backups), additional lotteries, player tools, and the Anthropic/Gemini driver cost comparison each get their own spec.
