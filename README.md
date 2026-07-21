# Loterias v2

A Laravel-based lottery data scraping and content generation system that automatically fetches Brazilian lottery results and generates content pages using OpenAI.

## Overview

This application scrapes lottery draw data from the official Caixa Econômica Federal API and generates content pages for lottery results using artificial intelligence. It supports multiple lottery games including Mega-Sena, Lotofácil, and Quina.

## Tech Stack

- **Backend**: Laravel 13 (PHP 8.3+)
- **Frontend**: Vite + TailwindCSS v4
- **Admin Panel**: Filament 5 + Filament Fabricator (CMS-style page builder)
- **Database**: SQLite locally by default; Sail provisions PostgreSQL for the container environment
- **AI**: OpenAI API integration
- **Development**: Laravel Sail (Docker)

## Features

- 🎲 **Multi-Game Support**: Mega-Sena, Lotofácil, and Quina
- 📊 **Data Scraping**: Automated fetching from official lottery APIs
- 🤖 **AI Content Generation**: Uses OpenAI to create content pages
- 📝 **Batch Processing**: Efficient processing of multiple draws
- 🏗️ **Admin Panel**: Filament-based administration interface
- 🐳 **Docker Ready**: Configured with Laravel Sail

## Quick Start

### Prerequisites

- Docker and Docker Compose
- PHP 8.2+ (if running locally)
- Composer

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd loterias_v2
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure environment variables**
   ```bash
   # Required: OpenAI API configuration
   OPENAI_API_KEY=your_openai_api_key
   OPENAI_ORGANIZATION=your_organization_id
   
   # Database (SQLite is default)
   DB_CONNECTION=sqlite
   DB_DATABASE=/path/to/database/database.sqlite
   ```

5. **Start with Laravel Sail**
   ```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail artisan migrate
   ```

6. **Build frontend assets**
   ```bash
   ./vendor/bin/sail npm run dev
   ```

## Database Schema

### Draws Table
- Stores raw lottery draw data from the official API
- Fields: `id`, `type`, `draw_number`, `draw_date`, `raw_data`, `timestamps`

### Pages Table
- Filament Fabricator's `pages` table, extended with draw-specific columns
- Fields include `id`, `draw_id`, `title`, `slug`, `layout`, `blocks` (JSON), `status`, `batch_id`, `provider`, `generated_at`, `timestamps`
- `status` is one of `generating`, `generated`, `published`, `failed` (`App\Enums\PageStatus`)
- A page only appears on its public URL once `status` is `published`

## Commands

### Data Scraping

#### `sail artisan app:scrape-draw [game] [draw-number]`
Fetches data for a specific draw from the lottery API and stores it in the database.

**Parameters:**
- `game`: One of `megasena`, `lotofacil`, or `quina`
- `draw-number`: (Optional) Specific draw number to fetch

**Example:**
```bash
sail artisan app:scrape-draw megasena 2500
```

#### `sail artisan app:scrape-draws [game] [quantity] [latest_draw_number]`
Fetches multiple draws for a specific game. Checks the database to avoid duplicates and only fetches missing draws.

**Parameters:**
- `game`: Lottery game type
- `quantity`: Number of draws to fetch
- `latest_draw_number`: Starting point for fetching

**Example:**
```bash
sail artisan app:scrape-draws megasena 10 2500
```

### Content Generation

#### `sail artisan app:create-content [game] [draw-number]`
Generates AI-powered content for a specific draw that already exists in the database, synchronously (one blocking OpenAI call). Creates or updates the draw's `Page` row with a real title, slug, and rendered content blocks. This is the fastest path to a single publishable page — see [Publishing a page end-to-end](#publishing-a-page-end-to-end) below.

**Example:**
```bash
sail artisan app:create-content megasena 2500
```

#### `sail artisan app:create-pages [game] [quantity]`
Submits an OpenAI Batch job to generate content for multiple draws that don't have a page yet. Creates `Page` rows immediately with `status = generating` and empty content, then dispatches `App\Jobs\CheckCompletionBatch` (delayed 10 minutes) to poll the batch and fill in the real content once it completes. Requires a queue worker (`sail artisan queue:work`) running to process that job.

**Example:**
```bash
sail artisan app:create-pages megasena 50
```

## Publishing a page end-to-end

This is the full, verified sequence to take one draw from "no data" to a live public page, using the synchronous single-draw commands (no queue worker needed).

1. **Scrape the draw** — fetches the raw result from the Caixa API and stores it in the `draws` table.
   ```bash
   sail artisan app:scrape-draw megasena 2500
   ```
   Omit the draw number to fetch the next undiscovered draw (`sail artisan app:scrape-draw megasena`). To backfill a range instead, use `sail artisan app:scrape-draws megasena 10 2500` (fetches 10 draws working backward from #2500, skipping any already in the database).

2. **Generate the content** — calls OpenAI and assembles the page's content blocks (hero, results grid, draw details, etc.) automatically. No manual block editing is required.
   ```bash
   sail artisan app:create-content megasena 2500
   ```
   The resulting `Page` row is created with `status = generated` — content is ready, but not yet public.

   > If `CONTENT_AUTO_PUBLISH=true` is set in `.env`, the page is created already `published` and you can skip step 3.

3. **Publish the page** — in the admin panel:
   - Visit `/admin/pages` and open the page for that draw (title looks like "Resultado Mega Sena concurso 2500").
   - Click the **Publish** button in the page header. This flips `status` from `generated` to `published` (it only works from `generated` — pages still `generating` or `failed` can't be published this way).

4. **View the public page** at:
   ```
   /megasena/resultado/2500
   ```
   This route explicitly requires `status = published`; anything else 404s.

## Project Structure

```
app/
├── Console/Commands/     # Artisan commands for scraping and content generation
├── Enums/               # Game enumerations (GamesEnum), page status (PageStatus)
├── Filament/            # Admin resources, Fabricator layouts/blocks, dashboard widgets
├── Jobs/                # Queue jobs (e.g. CheckCompletionBatch for OpenAI batch polling)
├── Models/              # Eloquent models (Draw, Page, User)
├── Providers/           # Service providers
└── Services/            # Core business logic
    ├── Scraper.php               # Lottery data scraping service
    ├── ContentProviderManager.php # Resolves the configured content driver (e.g. OpenAI)
    ├── Providers/OpenAiContentProvider.php # Single + batch OpenAI generation
    └── PageAssembler.php         # Turns a generation result into a Page's content blocks
```

## Services

### Scraper Service
Handles fetching lottery data from the official Caixa API:
- Endpoint: `https://servicebus2.caixa.gov.br/portaldeloterias/api/`
- Supports all configured lottery games
- Automatic error handling and logging

### Content generation services
- **`ContentProviderManager`** resolves the active content driver (`content.default` config, `openai` by default).
- **`OpenAiContentProvider`** calls OpenAI, either synchronously for one draw (`generateOne`) or as a batch job for many (`submitBatch`).
- **`PageAssembler`** takes a generation result and a `Draw`, and builds/saves the `Page` row's title, slug, and content blocks — this is what makes `app:create-content` produce a page with no manual page-builder editing needed.

## Development

### Local Development
```bash
# Start the development server
sail up -d

# Run migrations
sail artisan migrate

# Generate test data
sail artisan db:seed

# Watch for frontend changes
sail npm run dev
```

### Testing
```bash
# All suites, including Browser (needs Playwright installed locally, see below)
sail artisan test

# Fast suite only — no browser required, what CI runs
sail artisan test --testsuite=Unit,Feature

# Browser suite only (Pest 4 + Playwright), local-only by design
sail artisan test --testsuite=Browser

# Run a single test by name
sail artisan test --filter=test_name
```

The `Browser` suite drives a real Chromium browser via `pestphp/pest-plugin-browser` + Playwright. One-time local setup:
```bash
npm install playwright@latest
npx playwright install
```

### Code Style
```bash
# Format code with Laravel Pint
vendor/bin/pint
```

## API Integration

The application integrates with the official Brazilian lottery API provided by Caixa Econômica Federal. No API key is required for accessing lottery data.

## Environment Variables

### Required
- `OPENAI_API_KEY`: Your OpenAI API key
- `OPENAI_ORGANIZATION`: Your OpenAI organization ID

### Optional
- `OPENAI_REQUEST_TIMEOUT`: API request timeout (default: 30 seconds)
- `CONTENT_AUTO_PUBLISH`: when `true`, pages generated by `app:create-content`/`app:create-pages` are created already `published`, skipping the manual admin-panel publish step (default: `false`)
- `CONTENT_DEFAULT`: content provider driver to use (default: `openai`)
- Database configuration variables
- Laravel standard environment variables

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests and ensure code quality
5. Submit a pull request

## Support

For issues and questions:
- Check the Laravel documentation
- Review the OpenAI API documentation
- Create an issue in the repository
