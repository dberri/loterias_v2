<?php

namespace Tests\Feature\Services;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\Scraper;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Covers Scraper's draw_date derivation, including the failure branch.
 *
 * draws.draw_date is NOT NULL, and the column exists so that date-ordered
 * queries can see a draw at all. A payload whose date cannot be parsed must
 * therefore fail loudly rather than persist a row that every such query would
 * silently skip — the row would look present in a count and be missing
 * everywhere it mattered.
 */
class ScraperTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_a_draw_with_the_date_parsed_from_data_apuracao(): void
    {
        $this->fakeCaixaReturning(['dataApuracao' => '25/01/2025'] + $this->payload(2608));

        (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();

        $draw = Draw::where('draw_number', 2608)->firstOrFail();
        $this->assertSame('2025-01-25', $draw->draw_date->format('Y-m-d'));
    }

    public function test_an_unparseable_date_fails_loudly_rather_than_persisting_an_invisible_draw(): void
    {
        Log::spy();
        $this->fakeCaixaReturning(['dataApuracao' => 'not-a-date'] + $this->payload(2608));

        $this->expectException(QueryException::class);

        try {
            (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();
        } finally {
            $this->assertSame(0, Draw::count(), 'A draw with no usable date must not reach the table.');
            Log::shouldHaveReceived('warning')
                ->withArgs(fn (string $message): bool => str_contains($message, 'Failed to parse draw date'))
                ->once();
        }
    }

    public function test_a_payload_with_no_date_at_all_also_fails_loudly(): void
    {
        $payload = $this->payload(2608);
        unset($payload['dataApuracao']);
        $this->fakeCaixaReturning($payload);

        $this->expectException(QueryException::class);

        try {
            (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();
        } finally {
            $this->assertSame(0, Draw::count());
        }
    }

    /**
     * An empty string is not null, so a `?? null` guard does not catch it. The
     * seeded corpus contains this shape, which is why it gets its own case.
     */
    public function test_an_empty_date_string_fails_loudly(): void
    {
        $this->fakeCaixaReturning(['dataApuracao' => ''] + $this->payload(2608));

        $this->expectException(QueryException::class);

        try {
            (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();
        } finally {
            $this->assertSame(0, Draw::count());
        }
    }

    public function test_a_failed_response_persists_nothing_and_does_not_throw(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);

        (new Scraper(GamesEnum::MEGA_SENA, 2608))->scrape();

        $this->assertSame(0, Draw::count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeCaixaReturning(array $payload): void
    {
        Http::fake(['*' => Http::response($payload, 200)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $concurso): array
    {
        $path = database_path("seeders/lotteries/megasena/draws/{$concurso}.json");
        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true);
    }
}
