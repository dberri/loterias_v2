<?php

namespace Tests\Unit\PageBlocks;

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Filament\Fabricator\PageBlocks\RelatedLinksBlock;
use App\Models\Draw;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatedLinksBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_mid_series_draw_emits_prev_next_pillar_and_sibling_links(): void
    {
        $this->publishedPillarPages();
        $previous = $this->publishedDrawPage(2606);
        $current = $this->draw(2607);
        $this->publishedDrawPage(2608);

        $data = RelatedLinksBlock::mutateData([
            'draw_id' => $current->id,
        ]);

        $this->assertSame('Concurso anterior', $data['related_links']['previous']['label']);
        $this->assertStringEndsWith('/megasena/resultado/2606', $data['related_links']['previous']['url']);
        $this->assertSame('Próximo concurso', $data['related_links']['next']['label']);
        $this->assertStringEndsWith('/megasena/resultado/2608', $data['related_links']['next']['url']);
        $this->assertSame('Página pilar de Mega Sena', $data['related_links']['pillar']['title']);
        $this->assertCount(2, $data['related_links']['siblings']);
    }

    public function test_concurso_one_emits_no_prev_link(): void
    {
        $this->publishedPillarPages();
        $draw = $this->draw(1);

        $data = RelatedLinksBlock::mutateData([
            'draw_id' => $draw->id,
        ]);

        $this->assertArrayHasKey('pillar', $data['related_links']);
        $this->assertArrayNotHasKey('previous', $data['related_links']);
    }

    public function test_latest_known_draw_emits_no_next_link(): void
    {
        $this->publishedPillarPages();
        $this->publishedDrawPage(2607);
        $draw = $this->draw(2608);

        $data = RelatedLinksBlock::mutateData([
            'draw_id' => $draw->id,
        ]);

        $this->assertArrayNotHasKey('next', $data['related_links']);
        $this->assertArrayHasKey('previous', $data['related_links']);
    }

    public function test_only_published_pages_are_linked(): void
    {
        $this->publishedPillarPages();
        $current = $this->draw(2607);
        $next = $this->draw(2608);
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
    }

    private function draw(int $drawNumber): Draw
    {
        return Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, $drawNumber)->create();
    }

    private function publishedDrawPage(int $drawNumber): Page
    {
        $draw = Draw::where('draw_number', $drawNumber)->first() ?: $this->draw($drawNumber);

        return Page::create([
            'draw_id' => $draw->id,
            'title' => 'Resultado '.$drawNumber,
            'slug' => 'megasena/resultado/'.$drawNumber,
            'layout' => 'draw-page',
            'blocks' => [],
            'status' => PageStatus::Published->value,
        ]);
    }

    private function publishedPillarPages(): void
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
}
