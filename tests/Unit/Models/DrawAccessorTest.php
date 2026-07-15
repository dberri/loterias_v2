<?php

namespace Tests\Unit\Models;

use App\Models\Draw;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawAccessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_drawn_numbers_are_normalized_for_old_and_new_corpus_formats(): void
    {
        $oldFormatDraw = $this->createDraw(1);
        $newFormatDraw = $this->createDraw(2608);

        $this->assertSame(['04', '05', '30', '33', '41', '52'], $oldFormatDraw->drawn_numbers);
        $this->assertSame(['07', '13', '17', '24', '29', '52'], $newFormatDraw->drawn_numbers);
    }

    public function test_next_draw_date_returns_null_for_empty_string_and_date_when_present(): void
    {
        $missingDateDraw = $this->createDraw(1);
        $presentDateDraw = $this->createDraw(2608);

        $this->assertNull($missingDateDraw->next_draw_date);
        $this->assertSame('08/07/2023', $presentDateDraw->next_draw_date);
    }

    public function test_previous_draw_number_and_next_draw_estimate_are_exposed(): void
    {
        $firstDraw = $this->createDraw(1);
        $recentDraw = $this->createDraw(2608);

        $this->assertSame(0, $firstDraw->prev_draw_number);
        $this->assertSame(9000000.0, $recentDraw->next_draw_estimate);
    }

    private function createDraw(int $drawNumber): Draw
    {
        $fixture = $this->fixture($drawNumber);

        return Draw::create([
            'type' => 'megasena',
            'draw_number' => $drawNumber,
            'draw_date' => Carbon::createFromFormat('d/m/Y', $fixture['dataApuracao'])->format('Y-m-d'),
            'raw_data' => $fixture,
        ]);
    }

    private function fixture(int $drawNumber): array
    {
        return json_decode(file_get_contents(database_path("seeders/lotteries/megasena/draws/{$drawNumber}.json")), true, flags: JSON_THROW_ON_ERROR);
    }
}
