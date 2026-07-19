<?php

namespace Tests\Feature\Commands\CreatePagesTest;

use App\Contracts\BatchContentProvider;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use App\Services\ContentProviderManager;
use Mockery;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('create pages submits one batch and creates generating pages', function () {
    $firstDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2607)->create();
    $secondDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    $provider = recordingProvider('batch-123');
    bindProvider($provider);

    $this->artisan('app:create-pages', [
        'game' => GamesEnum::MEGA_SENA->value,
        'quantity' => 2,
    ])->assertExitCode(0);

    expect($provider->submittedRequests)->toHaveCount(2);
    expect(array_map(fn ($request) => $request->customId, $provider->submittedRequests))->toBe([
        'page_megasena_2608',
        'page_megasena_2607',
    ]);

    expect($firstDraw->fresh()->page->status)->toBe(PageStatus::Generating);
    expect($secondDraw->fresh()->page->status)->toBe(PageStatus::Generating);
    expect($firstDraw->fresh()->page->batch_id)->toBe('batch-123');
    expect($firstDraw->fresh()->page->provider)->toBe('openai');
});

test('create pages skips draws that already have pages and limits to available draws', function () {
    $eligibleDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2607)->create();
    $existingDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();

    Page::create([
        'draw_id' => $existingDraw->id,
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'megasena/resultado/2608',
        'layout' => 'draw-page',
        'blocks' => [],
        'status' => PageStatus::Published->value,
    ]);

    $provider = recordingProvider('batch-456');
    bindProvider($provider);

    $this->artisan('app:create-pages', [
        'game' => GamesEnum::MEGA_SENA->value,
        'quantity' => 5,
    ])->assertExitCode(0);

    expect($provider->submittedRequests)->toHaveCount(1);
    expect($provider->submittedRequests[0]->customId)->toBe('page_megasena_2607');
    expect(Page::count())->toBe(2);
    expect($existingDraw->fresh()->page->status)->toBe(PageStatus::Published);
    expect($eligibleDraw->fresh()->page->status)->toBe(PageStatus::Generating);
});

function bindProvider(BatchContentProvider $provider): void
{
    $manager = Mockery::mock(ContentProviderManager::class);
    $manager->shouldReceive('driver')->with('openai')->andReturn($provider);

    app()->instance(ContentProviderManager::class, $manager);
}

function recordingProvider(string $batchId): BatchContentProvider
{
    return new class($batchId) implements BatchContentProvider
    {
        public array $submittedRequests = [];

        public function __construct(private readonly string $batchId) {}

        public function submitBatch(iterable $requests): string
        {
            $this->submittedRequests = is_array($requests) ? $requests : iterator_to_array($requests, false);

            return $this->batchId;
        }

        public function pollBatch(string $id): \App\DTOs\BatchStatus
        {
            return \App\DTOs\BatchStatus::Completed;
        }

        public function fetchResults(string $id): iterable
        {
            return [];
        }

        public function generateOne(\App\DTOs\GenerationRequest $request): \App\DTOs\GenerationResult
        {
            return \App\DTOs\GenerationResult::invalid($request->customId, null, 'Not implemented.');
        }
    };
}
