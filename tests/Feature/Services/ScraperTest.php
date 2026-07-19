<?php

namespace Tests\Feature\Services\ScraperTest;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\Scraper;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Covers Scraper's draw_date derivation, including the failure branch.
 *
 * draws.draw_date is NOT NULL, and the column exists so that date-ordered
 * queries can see a draw at all. A payload whose date cannot be parsed must
 * therefore fail loudly rather than persist a row that every such query would
 * silently skip — the row would look present in a count and be missing
 * everywhere it mattered.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it persists a draw with the date parsed from data apuracao', function () {
    fakeCaixaReturning(['dataApuracao' => '25/01/2025'] + payload(2608));

    (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();

    $draw = Draw::where('draw_number', 2608)->firstOrFail();
    expect($draw->draw_date->format('Y-m-d'))->toBe('2025-01-25');
});

test('an unparseable date fails loudly rather than persisting an invisible draw', function () {
    Log::spy();
    fakeCaixaReturning(['dataApuracao' => 'not-a-date'] + payload(2608));

    $this->expectException(QueryException::class);

    try {
        (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();
    } finally {
        expect(Draw::count())->toBe(0, 'A draw with no usable date must not reach the table.');
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'Failed to parse draw date'))
            ->once();
    }
});

test('a payload with no date at all also fails loudly', function () {
    $data = payload(2608);
    unset($data['dataApuracao']);
    fakeCaixaReturning($data);

    $this->expectException(QueryException::class);

    try {
        (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();
    } finally {
        expect(Draw::count())->toBe(0);
    }
});

/**
 * An empty string is not null, so a `?? null` guard does not catch it. The
 * seeded corpus contains this shape, which is why it gets its own case.
 */
test('an empty date string fails loudly', function () {
    fakeCaixaReturning(['dataApuracao' => ''] + payload(2608));

    $this->expectException(QueryException::class);

    try {
        (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();
    } finally {
        expect(Draw::count())->toBe(0);
    }
});

test('a failed response persists nothing and does not throw', function () {
    Http::fake(['*' => Http::response(null, 503)]);

    (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();

    expect(Draw::count())->toBe(0);
});

/**
 * @param  array<string, mixed>  $data
 */
function fakeCaixaReturning(array $data): void
{
    Http::fake(['*' => Http::response($data, 200)]);
}

/**
 * @return array<string, mixed>
 */
function payload(int $concurso): array
{
    $path = database_path("seeders/lotteries/megasena/draws/{$concurso}.json");
    expect($path)->toBeFile();

    return json_decode(file_get_contents($path), true);
}
