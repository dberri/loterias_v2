<?php

namespace Tests\Unit\Models\DrawTest;

use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('page relation returns related page', function () {
    $draw = createDraw(2608);

    $page = Page::create([
        'title' => 'Resultado Mega-Sena 2608',
        'slug' => 'mega-sena/resultado/2608',
        'layout' => 'default',
        'blocks' => [],
        'draw_id' => $draw->id,
        'status' => PageStatus::Generating->value,
    ]);

    expect($draw->page->is($page))->toBeTrue();
});

test('scope without page excludes draws with pages in any status', function () {
    $eligibleDraw = createDraw(2608);
    $statuses = PageStatus::cases();

    foreach ($statuses as $index => $status) {
        $draw = createDraw($index + 1);

        Page::create([
            'title' => "Resultado {$draw->draw_number}",
            'slug' => "mega-sena/resultado/{$draw->draw_number}",
            'layout' => 'default',
            'blocks' => [],
            'draw_id' => $draw->id,
            'status' => $status->value,
        ]);
    }

    $result = Draw::query()->withoutPage()->pluck('draw_number')->all();

    expect($result)->toBe([$eligibleDraw->draw_number]);
});

function createDraw(int $drawNumber): Draw
{
    $fixture = fixture($drawNumber);

    return Draw::create([
        'type' => 'megasena',
        'draw_number' => $drawNumber,
        'draw_date' => Carbon::createFromFormat('d/m/Y', $fixture['dataApuracao'])->format('Y-m-d'),
        'raw_data' => $fixture,
    ]);
}

function fixture(int $drawNumber): array
{
    return json_decode(file_get_contents(database_path("seeders/lotteries/megasena/draws/{$drawNumber}.json")), true, flags: JSON_THROW_ON_ERROR);
}
