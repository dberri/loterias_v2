<?php

use App\Enums\GamesEnum;
use App\Models\Draw;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('factory builds real payloads for each game', function () {
    $cases = [
        GamesEnum::MEGA_SENA->value => [2608, 6, true, 'SÃO PAULO, SP', '08/07/2023'],
        GamesEnum::LOTOFACIL->value => [1, 15, false, 'CRUZ ALTA, RS', '06/10/2003'],
        GamesEnum::QUINA->value => [1, 5, false, '', null],
    ];

    foreach ($cases as $game => [$drawNumber, $numberCount, $expectedAccumulated, $expectedLocation, $expectedNextDrawDate]) {
        $draw = Draw::factory()->fixture($game, $drawNumber)->create();

        expect($draw->type->value)->toBe($game);
        expect($draw->drawn_numbers)->toHaveCount($numberCount);
        $this->assertContainsOnlyString($draw->drawn_numbers);
        expect($draw->is_accumulated)->toBeBool();
        expect($draw->is_accumulated)->toBe($expectedAccumulated);
        expect($draw->main_prize)->toBeFloat();
        expect($draw->main_prize_winners)->toBeInt();
        expect($draw->formatted_main_prize)->toBeString();
        expect($draw->location)->toBe($expectedLocation);
        expect($draw->next_draw_date)->toBe($expectedNextDrawDate);
    }
});

test('accumulated and with winners states are distinct', function () {
    $accumulated = Draw::factory()->accumulated()->create();
    $withWinners = Draw::factory()->withWinners()->create();

    expect($accumulated->main_prize_winners)->toBe(0);
    expect($withWinners->main_prize_winners)->toBeGreaterThan(0);
});

test('winner cities state uses a fixture with city data', function () {
    $draw = Draw::factory()->withWinnerCities()->create();

    expect($draw->raw_data['listaMunicipioUFGanhadores'])->not->toBeEmpty();
    expect($draw->location)->toBe('CRUZ ALTA, RS');
});
