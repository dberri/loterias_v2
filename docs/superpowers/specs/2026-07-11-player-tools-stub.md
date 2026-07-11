# Player Tools — Spec Stub

**Date:** 2026-07-11
**Status:** Backlog (not yet designed)
**Depends on:** [SEO Draw-Page Generation](./2026-07-11-seo-draw-page-generation-design.md) (reuses parked block classes)

## Intent

Engagement + long-tail SEO tools: random number generator, "did I win?" checker, bet simulator, probability comparisons.

## Likely scope

- Revive the parked Fabricator blocks: `NumberGeneratorBlock`, `SimulationBlock`, `StatisticsCardsBlock`, `ComparisonTableBlock`, `TimelineBlock`, `LatestResultsBlock`.
- "Did I win?" — compare user-entered numbers against a draw's real `raw_data`.
- Number generator — per-game valid ranges/quantities.
- Probability/odds comparison across games.
- These live mostly on tool/pillar pages, not individual draw pages (though a draw page may CTA into them).

## Open questions

- Client-side (Livewire/Alpine) vs server-computed for generator/checker?
- Do tools need persistence (saved bets, accounts) or fully stateless?
- Which tool drives the most traffic/engagement — build first?
