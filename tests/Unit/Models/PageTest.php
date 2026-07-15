<?php

namespace Tests\Unit\Models;

use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_page_model_resolves_to_app_model(): void
    {
        $pageModel = config('filament-fabricator.page-model');

        $this->assertSame(Page::class, $pageModel);
        $this->assertInstanceOf(Page::class, new $pageModel);
    }

    public function test_page_status_hydrates_as_enum(): void
    {
        $fixture = $this->megaFixture(1);

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

        $this->assertInstanceOf(PageStatus::class, $page->status);
        $this->assertSame(PageStatus::Generated, $page->status);
    }

    private function megaFixture(int $drawNumber): array
    {
        return json_decode(file_get_contents(database_path("seeders/lotteries/megasena/draws/{$drawNumber}.json")), true, flags: JSON_THROW_ON_ERROR);
    }
}
