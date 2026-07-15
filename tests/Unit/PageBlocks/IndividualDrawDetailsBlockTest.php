<?php

namespace Tests\Unit\PageBlocks;

use App\Enums\GamesEnum;
use App\Filament\Fabricator\PageBlocks\IndividualDrawDetailsBlock;
use App\Models\Draw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndividualDrawDetailsBlockTest extends TestCase
{
    use RefreshDatabase;

    public static function gameProvider(): array
    {
        return [
            'mega-sena' => [GamesEnum::MEGA_SENA, 2608],
            'lotofacil' => [GamesEnum::LOTOFACIL, 1],
            'quina' => [GamesEnum::QUINA, 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('gameProvider')]
    public function test_mutate_data_returns_every_faixa_with_correct_values_for_each_game(
        GamesEnum $game,
        int $drawNumber,
    ): void {
        $draw = Draw::factory()->fixture($game->value, $drawNumber)->create();

        $data = IndividualDrawDetailsBlock::mutateData([
            'draw_id' => $draw->id,
            'show_prize_breakdown' => true,
            'show_winners_by_tier' => true,
            'show_statistics' => true,
        ]);

        $expectedTiers = $draw->raw_data['listaRateioPremio'] ?? [];
        $this->assertSame(count($expectedTiers), count($data['prize_tiers']));

        foreach ($expectedTiers as $index => $tier) {
            $this->assertSame($tier['faixa'], $data['prize_tiers'][$index]['faixa']);
            $this->assertSame($tier['numeroDeGanhadores'], $data['prize_tiers'][$index]['numeroDeGanhadores']);
            $this->assertSame($tier['valorPremio'], $data['prize_tiers'][$index]['valorPremio']);
        }

        $this->assertSame($draw->location, $data['location']);
        $this->assertSame($draw->is_accumulated, $data['is_accumulated']);
        $this->assertSame($draw->next_draw_estimate, $data['next_draw_estimate']);
        $this->assertSame(count($draw->raw_data['listaMunicipioUFGanhadores'] ?? []), count($data['winner_cities']));
    }

    public function test_accumulated_draw_renders_without_error_and_keeps_zero_winner_tier(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 10)->create();

        $data = IndividualDrawDetailsBlock::mutateData([
            'draw_id' => $draw->id,
            'show_prize_breakdown' => true,
            'show_winners_by_tier' => true,
            'show_statistics' => true,
        ]);

        $this->assertTrue($draw->is_accumulated);
        $this->assertSame(0, $data['main_prize_winners']);
        $this->assertIsString($data['formatted_main_prize']);
        $this->assertSame(0, $data['prize_tiers'][0]['numeroDeGanhadores']);
    }

    public function test_draw_with_winners_and_winner_cities_renders_both(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 1)->create();

        $data = IndividualDrawDetailsBlock::mutateData([
            'draw_id' => $draw->id,
            'show_prize_breakdown' => true,
            'show_winners_by_tier' => true,
            'show_statistics' => true,
        ]);

        $this->assertNotEmpty($data['winner_cities']);
        $this->assertGreaterThan(0, $data['main_prize_winners']);
    }

    public function test_contradictory_ai_payload_does_not_change_output(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();

        $data = IndividualDrawDetailsBlock::mutateData([
            'draw_id' => $draw->id,
            'show_prize_breakdown' => true,
            'show_winners_by_tier' => true,
            'show_statistics' => true,
            'location' => 'Nowhere',
            'is_accumulated' => false,
            'next_draw_estimate' => 1,
            'prize_tiers' => [
                [
                    'faixa' => 1,
                    'numeroDeGanhadores' => 999,
                    'valorPremio' => 1,
                ],
            ],
            'winner_cities' => [
                ['municipio' => 'Foo', 'uf' => 'ZZ', 'ganhadores' => 1],
            ],
        ]);

        $this->assertSame($draw->location, $data['location']);
        $this->assertSame($draw->is_accumulated, $data['is_accumulated']);
        $this->assertSame($draw->next_draw_estimate, $data['next_draw_estimate']);
        $this->assertSame($draw->raw_data['listaRateioPremio'][0]['numeroDeGanhadores'], $data['prize_tiers'][0]['numeroDeGanhadores']);
        $this->assertSame(count($draw->raw_data['listaMunicipioUFGanhadores'] ?? []), count($data['winner_cities']));
    }
}
