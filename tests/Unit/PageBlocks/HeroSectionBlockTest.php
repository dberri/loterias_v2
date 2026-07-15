<?php

namespace Tests\Unit\PageBlocks;

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Filament\Fabricator\PageBlocks\HeroSectionBlock;
use App\Models\Draw;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeroSectionBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutate_data_uses_draw_facts_and_page_relation(): void
    {
        $draw = $this->draw(GamesEnum::MEGA_SENA, 2608);
        $page = $this->pageForDraw($draw);

        $data = HeroSectionBlock::mutateData([
            'draw_id' => $draw->id,
            'title' => 'AI title',
            'drawn_numbers' => ['99', '98'],
            'formatted_main_prize' => 'R$ 1,00',
            'is_accumulated' => false,
        ]);

        $this->assertSame($draw->drawn_numbers, $data['drawn_numbers']);
        $this->assertSame($draw->formatted_main_prize, $data['formatted_main_prize']);
        $this->assertSame($draw->is_accumulated, $data['is_accumulated']);
        $this->assertSame($draw->game_name, $data['game_name']);
        $this->assertSame($draw->draw_number, $data['draw_number']);
        $this->assertSame($draw->draw_date?->toDateString(), $data['draw_date']);
        $this->assertSame($page->id, $data['page']->id);
    }

    public function test_mutate_data_normalizes_old_corpus_format_dezenas(): void
    {
        $draw = $this->draw(GamesEnum::MEGA_SENA, 1);

        $data = HeroSectionBlock::mutateData([
            'draw_id' => $draw->id,
            'drawn_numbers' => ['004', '005', '030', '033', '041', '052'],
        ]);

        $this->assertSame($draw->drawn_numbers, $data['drawn_numbers']);
        $this->assertSame(6, count($data['drawn_numbers']));
        $this->assertSame(2, strlen($data['drawn_numbers'][0]));
    }

    public function test_mutate_data_normalizes_new_corpus_format_dezenas(): void
    {
        $draw = $this->draw(GamesEnum::MEGA_SENA, 2608);

        $data = HeroSectionBlock::mutateData([
            'draw_id' => $draw->id,
            'drawn_numbers' => ['99', '98'],
        ]);

        $this->assertSame($draw->drawn_numbers, $data['drawn_numbers']);
        $this->assertSame(6, count($data['drawn_numbers']));
        $this->assertSame(2, strlen($data['drawn_numbers'][0]));
    }

    public function test_contradictory_ai_payload_does_not_change_the_block_output(): void
    {
        $draw = $this->draw(GamesEnum::MEGA_SENA, 2608);

        $data = HeroSectionBlock::mutateData([
            'draw_id' => $draw->id,
            'drawn_numbers' => ['00', '01', '02', '03', '04', '05'],
            'formatted_main_prize' => 'R$ 1,00',
            'is_accumulated' => false,
            'location' => 'Nowhere',
        ]);

        $this->assertSame($draw->drawn_numbers, $data['drawn_numbers']);
        $this->assertSame($draw->formatted_main_prize, $data['formatted_main_prize']);
        $this->assertSame($draw->is_accumulated, $data['is_accumulated']);
        $this->assertSame($draw->location, $data['location']);
    }

    private function draw(GamesEnum $game, int $drawNumber): Draw
    {
        return Draw::factory()->fixture($game->value, $drawNumber)->create();
    }

    private function pageForDraw(Draw $draw): Page
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
}
