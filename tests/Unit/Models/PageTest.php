<?php

namespace Tests\Unit\Models\PageTest;

use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('configured page model resolves to app model', function () {
    $pageModel = config('filament-fabricator.page-model');

    expect($pageModel)->toBe(Page::class);
    expect(new $pageModel)->toBeInstanceOf(Page::class);
});

test('page status hydrates as enum', function () {
    $fixture = megaFixture(1);

    $draw = Draw::create([
        'type' => 'megasena',
        'draw_number' => 1,
        'draw_date' => Carbon::createFromFormat('d/m/Y', $fixture['dataApuracao'])->format('Y-m-d'),
        'raw_data' => $fixture,
    ]);

    $page = Page::create([
        'title' => 'Resultado Mega-Sena 1',
        'slug' => 'mega-sena/resultado/1',
        'layout' => 'default',
        'blocks' => [],
        'draw_id' => $draw->id,
        'status' => PageStatus::Generated->value,
    ])->fresh();

    expect($page->status)->toBeInstanceOf(PageStatus::class);
    expect($page->status)->toBe(PageStatus::Generated);
});

function megaFixture(int $drawNumber): array
{
    return json_decode(file_get_contents(database_path("seeders/lotteries/megasena/draws/{$drawNumber}.json")), true, flags: JSON_THROW_ON_ERROR);
}
