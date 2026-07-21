# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Goal

A Laravel app that scrapes Brazilian lottery results (Mega-Sena, Lotofácil, Quina) from the official Caixa Econômica Federal API and uses OpenAI to auto-generate SEO-oriented content pages from them. The commercial goal is ad traffic, so new pages/content features should be considered through an SEO/traffic lens (see `docs/arquitetura-da-informação.md` for the intended site IA — home, per-game pillar pages, results listings, individual draw pages, stats/tools, blog).

## Tech Stack

- Laravel 13, PHP 8.3+ (Sail image uses 8.5)
- Filament 5 (`/admin` panel) + `z3d0x/filament-fabricator` v4 (CMS-style page builder)
- `openai-php/laravel` for OpenAI integration
- SQLite by default locally; Sail's docker-compose provisions MySQL for the container environment
- Vite + Tailwind CSS v4
- Laravel Sail for local Docker dev

## Commands

Local dev typically runs through Sail; substitute `php artisan` for `sail artisan` if running outside Docker.

```bash
./vendor/bin/sail up -d               # start containers
sail artisan migrate                  # run migrations
sail artisan db:seed                  # seed (DrawSeeder, UserFactory)
sail npm run dev                      # Vite dev server
sail npm run build                    # production frontend build
```

Testing (Pest 4; PHPUnit 12 is the underlying runner, but every test file uses Pest function syntax — no class-based `Tests\TestCase` subclasses remain):

```bash
php artisan test                        # ALL suites, including Browser — needs Playwright installed (see below)
php artisan test --testsuite=Unit,Feature  # fast suite, no browser required — what CI runs
php artisan test --testsuite=Browser    # browser-only suite (Pest 4 + Playwright), local-only by design
php artisan test --filter=test_name
vendor/bin/pest                         # equivalent to `php artisan test`
```

The `Browser` testsuite (`tests/Browser/`) drives a real Chromium browser via `pestphp/pest-plugin-browser` + Playwright — it exercises the actual admin-edit → public-render loop (login through `/admin/login`, edit a Fabricator page, confirm the public draw page reflects it) and asserts every implemented page block renders its content. It is **not** run in CI (too slow for now; tracked as follow-up PEST-F1) and requires a one-time local setup:

```bash
npm install playwright@latest
npx playwright install
```

Failing browser tests automatically save a screenshot to `tests/Browser/Screenshots/` (gitignored) named after the failing test.

Code style:

```bash
vendor/bin/pint --dirty               # format changed files before finishing a task
```

Domain artisan commands (see `app/Console/Commands/`):

```bash
php artisan app:scrape-draw {game} {draw_number?}          # fetch one draw (latest if number omitted)
php artisan app:scrape-draws {game} {quantity} {latest_draw_number}  # backfill many, skips existing
php artisan app:create-content {game} {draw_number}        # sync OpenAI content for one draw
php artisan app:create-pages {game} {quantity}              # batch OpenAI content for draws without a page
```

`{game}` is one of `megasena`, `lotofacil`, `quina` (`App\Enums\GamesEnum`).

## Architecture

### Data pipeline

`Draw` (raw scraped data) → `DrawPage` (AI-generated article), 1:1 via `Draw::page()` / `DrawPage::belongsTo`.

1. **`App\Services\Scraper`** — hits `https://servicebus2.caixa.gov.br/portaldeloterias/api/{game}/{drawNumber}`, rotates a User-Agent list, and stores the full JSON response in `Draw::raw_data` (cast to array). If no draw number is given it infers "latest known + 1". `Draw` has accessor methods (`getMainPrizeAttribute`, `getDrawnNumbersAttribute`, `getIsAccumulatedAttribute`, etc.) that all read out of `raw_data` — there are no dedicated columns for these fields, so any new derived value should be added as an accessor rather than a migration.
2. **`App\Services\ContentCreator`** — builds a Portuguese, SEO-oriented journalistic prompt per game (placeholder-based templates keyed by `GamesEnum`, filled from `Draw::raw_data`) and calls OpenAI chat completions.
   - `createContent(Draw $draw)`: synchronous single-page generation (used by `app:create-content`).
   - `createContentForDraws(Collection $draws)`: writes a JSONL batch file to `storage/app/private/commands.jsonl`, uploads it, and starts an OpenAI Batch (`app:create-pages`). Creates `DrawPage` rows with `title`/`content` = `'Pending'` and a `batch_id` up front, then relies on the batch job to fill in real content later.
3. **`App\Jobs\CheckCompletionBatch`** — queued job that self-re-dispatches every 10 minutes until the OpenAI batch is no longer `in_progress`; on `completed` it downloads the output file and calls `ContentCreator::updatePagesContent()`, which matches `custom_id` (`prompt_{type}_{draw_number}`) back to the `Draw`/`DrawPage` rows and writes the generated article body into `DrawPage::content`.

Batch-generated titles/meta tags are not implemented yet (`generateTitle()`/`metaTagsGenerator()` in `ContentCreator` are stubs); new `DrawPage` rows are created with `title = 'Pending'`.

### Admin panel (Filament)

- `App\Providers\Filament\AdminPanelProvider` registers the `admin` panel at `/admin`, auto-discovers Resources/Pages/Widgets, and loads the `FilamentFabricatorPlugin`.
- `App\Filament\Resources\DrawResource` is a read-only-ish view over `Draw` (list + view only, no create/edit — draws are only ever produced by the scraper).
- **Filament Fabricator** (`z3d0x/filament-fabricator`) provides the `pages` table (`title`, `slug`, `layout`, `blocks` JSON, self-referencing `parent_id`) and drives the public-facing content pages. Layouts live in `app/Filament/Fabricator/Layouts/`, block definitions in `app/Filament/Fabricator/PageBlocks/` (one class per reusable content block: hero, FAQ, results grid, stats cards, number generator, etc.). `routes/web.php` resolves `/{slug}` by looking up a `Page` and rendering `components.filament-fabricator.layouts.{$page->layout}`.
- Several PageBlocks currently have chunks of logic commented out (e.g. `IndividualDrawDetailsBlock`, `StatisticsCardsBlock`, `ResultsGridBlock`) from getting the fabricator wired up — check a block's current state before assuming its `mutateData`/schema is fully live. Note `HeroSectionBlock::mutateData()` references `Draw::drawPage` and a `game` attribute that don't exist on the model (the real relation is `page()`); treat that method as unfinished rather than a working example to copy.

### Conventions (from `.github/copilot-instructions.md` / Laravel Boost)

- PHP: constructor property promotion, explicit return types, curly braces always, prefer PHPDoc over inline comments.
- Eloquent over raw `DB::`/query builder; eager-load to avoid N+1; casts defined via a `casts()` method (see `Draw`), not the `$casts` property.
- Filament: use `php artisan make:filament-*` generators and static `make()` chains; resources under `app/Filament/Resources`, fabricator blocks/layouts under the paths above.
- Run `vendor/bin/pint --dirty` before finishing a change.
- Don't add documentation files unless explicitly requested.
