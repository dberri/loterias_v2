<?php

namespace Tests\Unit\PageBlocks;

use App\Enums\GamesEnum;
use App\Filament\Fabricator\PageBlocks\ResultsGridBlock;
use App\Models\Draw;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultsGridBlockTest extends TestCase
{
    use RefreshDatabase;

    public static function gameProvider(): array
    {
        return [
            'mega-sena' => [GamesEnum::MEGA_SENA, 2608, 6],
            'lotofacil' => [GamesEnum::LOTOFACIL, 1, 15],
            'quina' => [GamesEnum::QUINA, 1, 5],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('gameProvider')]
    public function test_mutate_data_returns_real_draw_models_for_each_game_and_ignores_ai_payload(
        GamesEnum $game,
        int $drawNumber,
        int $expectedNumberCount,
    ): void {
        $draw = Draw::factory()->fixture($game->value, $drawNumber)->create();

        $data = ResultsGridBlock::mutateData([
            'lottery_type' => $game->value,
            'results_per_page' => 20,
            'enable_pagination' => false,
            'results' => [
                [
                    'draw_number' => 9999,
                    'drawn_numbers' => ['00'],
                ],
            ],
        ]);

        $this->assertArrayHasKey('results', $data);
        $this->assertInstanceOf(Collection::class, $data['results']);
        $this->assertSame(1, $data['results']->count());

        $firstResult = $data['results']->first();

        $this->assertInstanceOf(Draw::class, $firstResult);
        $this->assertSame($draw->drawn_numbers, $firstResult->drawn_numbers);
        $this->assertSame($draw->draw_number, $firstResult->draw_number);
        $this->assertSame($draw->draw_date?->toDateString(), $firstResult->draw_date?->toDateString());
        $this->assertCount($expectedNumberCount, $firstResult->drawn_numbers);
    }

    public function test_mutate_data_filters_accumulated_draws_without_hardcoded_counts(): void
    {
        Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 10)->create();
        Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 1)->create();

        $data = ResultsGridBlock::mutateData([
            'lottery_type' => GamesEnum::LOTOFACIL->value,
            'results_per_page' => 20,
            'enable_pagination' => false,
            'show_accumulated_only' => true,
        ]);

        $this->assertInstanceOf(Collection::class, $data['results']);
        $this->assertSame(1, $data['results']->count());
        $this->assertTrue($data['results']->first()->is_accumulated);
    }
}
