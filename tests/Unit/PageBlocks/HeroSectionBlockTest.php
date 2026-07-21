<?php

namespace Tests\Unit\PageBlocks\HeroSectionBlockTest;

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Filament\Fabricator\PageBlocks\HeroSectionBlock;
use App\Models\Draw;
use App\Models\Page;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('mutate data uses draw facts and page relation', function () {
    $draw = draw(GamesEnum::MEGA_SENA, 2608);
    $page = pageForDraw($draw);

    $data = HeroSectionBlock::mutateData([
        'draw_id' => $draw->id,
        'title' => 'AI title',
        'drawn_numbers' => ['99', '98'],
        'formatted_main_prize' => 'R$ 1,00',
        'is_accumulated' => false,
    ]);

    expect($data['drawn_numbers'])->toBe($draw->drawn_numbers);
    expect($data['formatted_main_prize'])->toBe($draw->formatted_main_prize);
    expect($data['is_accumulated'])->toBe($draw->is_accumulated);
    expect($data['game_name'])->toBe($draw->game_name);
    expect($data['draw_number'])->toBe($draw->draw_number);
    expect($data['draw_date'])->toBe($draw->draw_date?->toDateString());
    expect($data['page']->id)->toBe($page->id);
});

test('mutate data normalizes old corpus format dezenas', function () {
    $draw = draw(GamesEnum::MEGA_SENA, 1);

    $data = HeroSectionBlock::mutateData([
        'draw_id' => $draw->id,
        'drawn_numbers' => ['004', '005', '030', '033', '041', '052'],
    ]);

    expect($data['drawn_numbers'])->toBe($draw->drawn_numbers);
    expect(count($data['drawn_numbers']))->toBe(6);
    expect(strlen($data['drawn_numbers'][0]))->toBe(2);
});

test('mutate data normalizes new corpus format dezenas', function () {
    $draw = draw(GamesEnum::MEGA_SENA, 2608);

    $data = HeroSectionBlock::mutateData([
        'draw_id' => $draw->id,
        'drawn_numbers' => ['99', '98'],
    ]);

    expect($data['drawn_numbers'])->toBe($draw->drawn_numbers);
    expect(count($data['drawn_numbers']))->toBe(6);
    expect(strlen($data['drawn_numbers'][0]))->toBe(2);
});

test('contradictory ai payload does not change the block output', function () {
    $draw = draw(GamesEnum::MEGA_SENA, 2608);

    $data = HeroSectionBlock::mutateData([
        'draw_id' => $draw->id,
        'drawn_numbers' => ['00', '01', '02', '03', '04', '05'],
        'formatted_main_prize' => 'R$ 1,00',
        'is_accumulated' => false,
        'location' => 'Nowhere',
    ]);

    expect($data['drawn_numbers'])->toBe($draw->drawn_numbers);
    expect($data['formatted_main_prize'])->toBe($draw->formatted_main_prize);
    expect($data['is_accumulated'])->toBe($draw->is_accumulated);
    expect($data['location'])->toBe($draw->location);
});

function draw(GamesEnum $game, int $drawNumber): Draw
{
    return Draw::factory()->fixture($game->value, $drawNumber)->create();
}

function pageForDraw(Draw $draw): Page
{
    return Page::create([
        'draw_id' => $draw->id,
        'title' => 'Resultado '.$draw->draw_number,
        'slug' => $draw->type->value.'/resultado/'.$draw->draw_number,
        'layout' => 'default',
        'blocks' => [],
        'status' => PageStatus::Published->value,
    ]);
}
