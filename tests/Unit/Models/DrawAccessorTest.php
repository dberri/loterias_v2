<?php

namespace Tests\Unit\Models\DrawAccessorTest;

use App\Models\Draw;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('drawn numbers are normalized for old and new corpus formats', function () {
    $oldFormatDraw = createDraw(1);
    $newFormatDraw = createDraw(2608);

    expect($oldFormatDraw->drawn_numbers)->toBe(['04', '05', '30', '33', '41', '52']);
    expect($newFormatDraw->drawn_numbers)->toBe(['07', '13', '17', '24', '29', '52']);
});

test('next draw date returns null for empty string and date when present', function () {
    $missingDateDraw = createDraw(1);
    $presentDateDraw = createDraw(2608);

    expect($missingDateDraw->next_draw_date)->toBeNull();
    expect($presentDateDraw->next_draw_date)->toBe('08/07/2023');
});

test('previous draw number and next draw estimate are exposed', function () {
    $firstDraw = createDraw(1);
    $recentDraw = createDraw(2608);

    expect($firstDraw->prev_draw_number)->toBe(0);
    expect($recentDraw->next_draw_estimate)->toBe(9000000.0);
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
