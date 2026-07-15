<?php

namespace Tests\Unit\Models;

use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_relation_returns_related_page(): void
    {
        $draw = $this->createDraw(2608);

        $page = Page::create([
            'title' => 'Resultado Mega-Sena 2608',
            'slug' => 'mega-sena/resultado/2608',
            'layout' => 'default',
            'blocks' => [],
            'draw_id' => $draw->id,
            'status' => PageStatus::Generating->value,
        ]);

        $this->assertTrue($draw->page->is($page));
    }

    public function test_scope_without_page_excludes_draws_with_pages_in_any_status(): void
    {
        $eligibleDraw = $this->createDraw(2608);
        $statuses = PageStatus::cases();

        foreach ($statuses as $index => $status) {
            $draw = $this->createDraw($index + 1);

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

        $this->assertSame([$eligibleDraw->draw_number], $result);
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
