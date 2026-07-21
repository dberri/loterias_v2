<?php

namespace Tests\Feature\Filament\PageResourceTest;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Draw;
use App\Models\Page;
use App\Models\User;
use App\Services\PageAssembler;
use Livewire\Livewire;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('page list and edit surfaces show generation metadata', function () {
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
});

test('draw page layout is registered for the admin dropdown', function () {
    expect(FilamentFabricator::getLayouts())->toHaveKey('draw-page');
});

test('publish action promotes generated page and public route returns 200', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    $page = publishedPageCandidate($draw);

    Livewire::test(EditPage::class, ['record' => $page->getKey()])
        ->call('publish');

    expect($page->fresh()->status)->toBe(PageStatus::Published);
    $this->get('/megasena/resultado/2608')->assertOk();
});

test('failed page cannot be published via the action', function () {
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

    expect($page->fresh()->status)->toBe(PageStatus::Failed);
});

function publishedPageCandidate(Draw $draw): Page
{
    $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'megasena/resultado/2608',
        'meta_description' => 'Resumo do concurso 2608',
        'enrichment_blocks' => [],
    ]));

    return $page->fresh();
}
