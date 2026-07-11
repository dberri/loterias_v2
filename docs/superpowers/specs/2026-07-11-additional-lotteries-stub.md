# Additional Lotteries — Spec Stub

**Date:** 2026-07-11
**Status:** Backlog (not yet designed)
**Depends on:** [SEO Draw-Page Generation](./2026-07-11-seo-draw-page-generation-design.md)

## Intent

Expand beyond Mega-Sena / Quina / Lotofácil to more Caixa games (Lotomania, Timemania, Dupla Sena, Dia de Sorte, +Milionária, etc.) to widen SEO surface area.

## Likely scope

- Add cases to `App\Enums\GamesEnum`.
- Per-game factual-context mapping (each game's `raw_data` faixas/fields differ).
- Per-game prompt tuning (rules, tiers, terminology).
- Verify the anchored blocks (results-grid, draw-details) handle each game's number ranges / faixa counts.
- Pillar page per game (ties into a future pillar-page spec).

## Open questions

- Which games first, by search volume / effort?
- Do any games have `raw_data` shapes that break the current accessor assumptions?
- Games with two draws (e.g. Dupla Sena) — how do results-grid/draw-details represent multiple draws?
