<?php

namespace Tests\Unit\PageBlocks\RelatedLinksBlockTest;

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Filament\Fabricator\PageBlocks\RelatedLinksBlock;
use App\Models\Draw;
use App\Models\Page;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('mid series draw emits prev next pillar and sibling links', function () {
    publishedPillarPages();
    $previous = publishedDrawPage(2606);
    $current = draw(2607);
    publishedDrawPage(2608);

    $data = RelatedLinksBlock::mutateData([
        'draw_id' => $current->id,
    ]);

    expect($data['related_links']['previous']['label'])->toBe('Concurso anterior');
    expect($data['related_links']['previous']['url'])->toEndWith('/megasena/resultado/2606');
    expect($data['related_links']['next']['label'])->toBe('Próximo concurso');
    expect($data['related_links']['next']['url'])->toEndWith('/megasena/resultado/2608');
    expect($data['related_links']['pillar']['title'])->toBe('Página pilar de Mega Sena');
    expect($data['related_links']['siblings'])->toHaveCount(2);
});

test('concurso one emits no prev link', function () {
    publishedPillarPages();
    $draw = draw(1);

    $data = RelatedLinksBlock::mutateData([
        'draw_id' => $draw->id,
    ]);

    expect($data['related_links'])->toHaveKey('pillar');
    $this->assertArrayNotHasKey('previous', $data['related_links']);
});

test('latest known draw emits no next link', function () {
    publishedPillarPages();
    publishedDrawPage(2607);
    $draw = draw(2608);

    $data = RelatedLinksBlock::mutateData([
        'draw_id' => $draw->id,
    ]);

    $this->assertArrayNotHasKey('next', $data['related_links']);
    expect($data['related_links'])->toHaveKey('previous');
});

test('only published pages are linked', function () {
    publishedPillarPages();
    $current = draw(2607);
    $next = draw(2608);
    Page::create([
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'megasena/resultado/2608',
        'layout' => 'draw-page',
        'blocks' => [],
        'draw_id' => $next->id,
        'status' => PageStatus::Generating->value,
    ]);

    $data = RelatedLinksBlock::mutateData([
        'draw_id' => $current->id,
    ]);

    $this->assertArrayNotHasKey('next', $data['related_links']);
});

function draw(int $drawNumber): Draw
{
    return Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, $drawNumber)->create();
}

function publishedDrawPage(int $drawNumber): Page
{
    $draw = Draw::where('draw_number', $drawNumber)->first() ?: draw($drawNumber);

    return Page::create([
        'draw_id' => $draw->id,
        'title' => 'Resultado '.$drawNumber,
        'slug' => 'megasena/resultado/'.$drawNumber,
        'layout' => 'draw-page',
        'blocks' => [],
        'status' => PageStatus::Published->value,
    ]);
}

function publishedPillarPages(): void
{
    foreach ([GamesEnum::MEGA_SENA, GamesEnum::LOTOFACIL, GamesEnum::QUINA] as $game) {
        Page::firstOrCreate(
            ['slug' => $game->value],
            [
                'title' => ucfirst($game->value),
                'layout' => 'pillar-page',
                'blocks' => [],
                'status' => PageStatus::Published->value,
            ],
        );
    }
}
