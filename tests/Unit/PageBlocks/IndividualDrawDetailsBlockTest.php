<?php

namespace Tests\Unit\PageBlocks\IndividualDrawDetailsBlockTest;

use App\Enums\GamesEnum;
use App\Filament\Fabricator\PageBlocks\IndividualDrawDetailsBlock;
use App\Models\Draw;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function gameProvider(): array
{
    return [
        'mega-sena' => [GamesEnum::MEGA_SENA, 2608],
        'lotofacil' => [GamesEnum::LOTOFACIL, 1],
        'quina' => [GamesEnum::QUINA, 1],
    ];
}

test('mutate data returns every faixa with correct values for each game', function (GamesEnum $game, int $drawNumber) {
    $draw = Draw::factory()->fixture($game->value, $drawNumber)->create();

    $data = IndividualDrawDetailsBlock::mutateData([
        'draw_id' => $draw->id,
        'show_prize_breakdown' => true,
        'show_winners_by_tier' => true,
        'show_statistics' => true,
    ]);

    $expectedTiers = $draw->raw_data['listaRateioPremio'] ?? [];
    expect(count($data['prize_tiers']))->toBe(count($expectedTiers));

    foreach ($expectedTiers as $index => $tier) {
        expect($data['prize_tiers'][$index]['faixa'])->toBe($tier['faixa']);
        expect($data['prize_tiers'][$index]['numeroDeGanhadores'])->toBe($tier['numeroDeGanhadores']);
        expect($data['prize_tiers'][$index]['valorPremio'])->toBe($tier['valorPremio']);
    }

    expect($data['location'])->toBe($draw->location);
    expect($data['is_accumulated'])->toBe($draw->is_accumulated);
    expect($data['next_draw_estimate'])->toBe($draw->next_draw_estimate);
    expect(count($data['winner_cities']))->toBe(count($draw->raw_data['listaMunicipioUFGanhadores'] ?? []));
})->with(gameProvider());

test('accumulated draw renders without error and keeps zero winner tier', function () {
    $draw = Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 10)->create();

    $data = IndividualDrawDetailsBlock::mutateData([
        'draw_id' => $draw->id,
        'show_prize_breakdown' => true,
        'show_winners_by_tier' => true,
        'show_statistics' => true,
    ]);

    expect($draw->is_accumulated)->toBeTrue();
    expect($data['main_prize_winners'])->toBe(0);
    expect($data['formatted_main_prize'])->toBeString();
    expect($data['prize_tiers'][0]['numeroDeGanhadores'])->toBe(0);
});

test('draw with winners and winner cities renders both', function () {
    $draw = Draw::factory()->fixture(GamesEnum::LOTOFACIL->value, 1)->create();

    $data = IndividualDrawDetailsBlock::mutateData([
        'draw_id' => $draw->id,
        'show_prize_breakdown' => true,
        'show_winners_by_tier' => true,
        'show_statistics' => true,
    ]);

    expect($data['winner_cities'])->not->toBeEmpty();
    expect($data['main_prize_winners'])->toBeGreaterThan(0);
});

test('contradictory ai payload does not change output', function () {
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

    expect($data['location'])->toBe($draw->location);
    expect($data['is_accumulated'])->toBe($draw->is_accumulated);
    expect($data['next_draw_estimate'])->toBe($draw->next_draw_estimate);
    expect($data['prize_tiers'][0]['numeroDeGanhadores'])->toBe($draw->raw_data['listaRateioPremio'][0]['numeroDeGanhadores']);
    expect(count($data['winner_cities']))->toBe(count($draw->raw_data['listaMunicipioUFGanhadores'] ?? []));
});
