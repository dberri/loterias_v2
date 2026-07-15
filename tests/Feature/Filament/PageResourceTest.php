<?php

namespace Tests\Feature\Filament;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Draw;
use App\Models\Page;
use App\Models\User;
use App\Services\PageAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

class PageResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_list_and_edit_surfaces_show_generation_metadata(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $page = Page::create([
            'draw_id' => $draw->id,
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'layout' => 'draw-page',
            'blocks' => [],
            'status' => PageStatus::Generated->value,
            'batch_id' => 'batch-123',
            'provider' => 'openai',
            'generated_at' => now(),
        ]);

        Livewire::test(\Z3d0X\FilamentFabricator\Resources\PageResource\Pages\ListPages::class)
            ->assertSee('Status')
            ->assertSee('Batch ID')
            ->assertSee('Provider')
            ->assertSee('Generated At');

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->assertSee('Status')
            ->assertSee('Batch ID')
            ->assertSee('Provider')
            ->assertSee('Generated At');
    }

    public function test_draw_page_layout_is_registered_for_the_admin_dropdown(): void
    {
        $this->assertArrayHasKey('draw-page', FilamentFabricator::getLayouts());
    }

    public function test_publish_action_promotes_generated_page_and_public_route_returns_200(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $page = $this->publishedPageCandidate($draw);

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->call('publish');

        $this->assertSame(PageStatus::Published, $page->fresh()->status);
        $this->get('/megasena/resultado/2608')->assertOk();
    }

    public function test_failed_page_cannot_be_published_via_the_action(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $page = Page::create([
            'draw_id' => $draw->id,
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'layout' => 'draw-page',
            'blocks' => [],
            'status' => PageStatus::Failed->value,
            'batch_id' => 'batch-123',
            'provider' => 'openai',
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->call('publish')
            ->assertHasErrors(['status']);

        $this->assertSame(PageStatus::Failed, $page->fresh()->status);
    }

    private function publishedPageCandidate(Draw $draw): Page
    {
        $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [],
        ]));

        return $page->fresh();
    }
}
