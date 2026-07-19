<?php

namespace Tests\Unit\PageBlocks\ResultsGridBlockTest;

use App\Enums\GamesEnum;
use App\Filament\Fabricator\PageBlocks\ResultsGridBlock;
use App\Models\Draw;
use Illuminate\Database\Eloquent\Collection;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function gameProvider(): array
{
    return [
        'mega-sena' => [GamesEnum::MEGA_SENA, 2608, 6],
        'lotofacil' => [GamesEnum::LOTOFACIL, 1, 15],
        'quina' => [GamesEnum::QUINA, 1, 5],
    ];
}

test('mutate data returns real draw models for each game and ignores ai payload', function (GamesEnum $game, int $drawNumber, int $expectedNumberCount) {
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

    expect($data)->toHaveKey('results');
    expect($data['results'])->toBeInstanceOf(Collection::class);
    expect($data['results']->count())->toBe(1);

    $firstResult = $data['results']->first();

    expect($firstResult)->toBeInstanceOf(Draw::class);
    expect($firstResult->drawn_numbers)->toBe($draw->drawn_numbers);
    expect($firstResult->draw_number)->toBe($draw->draw_number);
    expect($firstResult->draw_date?->toDateString())->toBe($draw->draw_date?->toDateString());
    expect($firstResult->drawn_numbers)->toHaveCount($expectedNumberCount);
})->with(gameProvider());

test('mutate data filters accumulated draws without hardcoded counts', function () {
    Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 10)->create();
    Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 1)->create();

    $data = ResultsGridBlock::mutateData([
        'lottery_type' => GamesEnum::LOTOFACIL->value,
        'results_per_page' => 20,
        'enable_pagination' => false,
        'show_accumulated_only' => true,
    ]);

    expect($data['results'])->toBeInstanceOf(Collection::class);
    expect($data['results']->count())->toBe(1);
    expect($data['results']->first()->is_accumulated)->toBeTrue();
});
