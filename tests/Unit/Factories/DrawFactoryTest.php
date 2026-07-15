<?php

namespace Tests\Unit\Factories;

use App\Enums\GamesEnum;
use App\Models\Draw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_builds_real_payloads_for_each_game(): void
    {
        $cases = [
            GamesEnum::MEGA_SENA->value => [2608, 6, true, 'SÃO PAULO, SP', '08/07/2023'],
            GamesEnum::LOTOFACIL->value => [1, 15, false, 'CRUZ ALTA, RS', '06/10/2003'],
            GamesEnum::QUINA->value => [1, 5, false, '', null],
        ];

        foreach ($cases as $game => [$drawNumber, $numberCount, $expectedAccumulated, $expectedLocation, $expectedNextDrawDate]) {
            $draw = Draw::factory()->fixture($game, $drawNumber)->create();

            $this->assertSame($game, $draw->type->value);
            $this->assertCount($numberCount, $draw->drawn_numbers);
            $this->assertContainsOnlyString($draw->drawn_numbers);
            $this->assertIsBool($draw->is_accumulated);
            $this->assertSame($expectedAccumulated, $draw->is_accumulated);
            $this->assertIsFloat($draw->main_prize);
            $this->assertIsInt($draw->main_prize_winners);
            $this->assertIsString($draw->formatted_main_prize);
            $this->assertSame($expectedLocation, $draw->location);
            $this->assertSame($expectedNextDrawDate, $draw->next_draw_date);
        }
    }

    public function test_accumulated_and_with_winners_states_are_distinct(): void
    {
        $accumulated = Draw::factory()->accumulated()->create();
        $withWinners = Draw::factory()->withWinners()->create();

        $this->assertSame(0, $accumulated->main_prize_winners);
        $this->assertGreaterThan(0, $withWinners->main_prize_winners);
    }

    public function test_winner_cities_state_uses_a_fixture_with_city_data(): void
    {
        $draw = Draw::factory()->withWinnerCities()->create();

        $this->assertNotEmpty($draw->raw_data['listaMunicipioUFGanhadores']);
        $this->assertSame('CRUZ ALTA, RS', $draw->location);
    }
}
