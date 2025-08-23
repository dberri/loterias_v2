# Loterias v2

A Laravel-based lottery data scraping and content generation system that automatically fetches Brazilian lottery results and generates content pages using OpenAI.

## Overview

This application scrapes lottery draw data from the official Caixa Econômica Federal API and generates content pages for lottery results using artificial intelligence. It supports multiple lottery games including Mega-Sena, Lotofácil, and Quina.

## Tech Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vite + TailwindCSS
- **Admin Panel**: Filament 3
- **Database**: SQLite (configurable)
- **AI**: OpenAI API integration
- **Development**: Laravel Sail (Docker)
- **Queue System**: Laravel Jobs with batch processing

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
- Fields: `id`, `type`, `draw_number`, `raw_data`, `timestamps`

### Draw Pages Table
- Contains AI-generated content for each draw
- Fields: `id`, `draw_id`, `title`, `content`, `url`, `batch_id`, `is_published`, `timestamps`

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
Generates AI-powered content for a specific draw that exists in the database.

**Example:**
```bash
sail artisan app:create-content megasena 2500
```

#### `sail artisan app:create-pages [game] [quantity]`
Creates content pages for multiple draws using batch processing. The draws must already exist in the database.

**Example:**
```bash
sail artisan app:create-pages megasena 50
```

## Project Structure

```
app/
├── Console/Commands/     # Artisan commands for scraping and content generation
├── Enums/               # Game enumerations (GamesEnum)
├── Http/Controllers/    # Web controllers
├── Jobs/                # Queue jobs for batch processing
├── Models/              # Eloquent models (Draw, DrawPage, User)
├── Providers/           # Service providers
└── Services/            # Core business logic
    ├── Scraper.php      # Lottery data scraping service
    └── ContentCreator.php # AI content generation service
```

## Services

### Scraper Service
Handles fetching lottery data from the official Caixa API:
- Endpoint: `https://servicebus2.caixa.gov.br/portaldeloterias/api/`
- Supports all configured lottery games
- Automatic error handling and logging

### ContentCreator Service
Manages AI-powered content generation:
- OpenAI integration for content creation
- Batch processing support
- Customizable prompts for different lottery games

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
# Run Pest tests
pest-sail

# Run specific test suite
pest-sail --group=Feature
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
