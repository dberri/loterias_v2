<?php

namespace Tests\Feature\Jobs\CheckCompletionBatchTest;

use App\Contracts\BatchContentProvider;
use App\DTOs\BatchStatus;
use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Jobs\CheckCompletionBatch;
use App\Models\Draw;
use App\Models\Page;
use App\Services\ContentProviderManager;
use Illuminate\Support\Facades\Queue;
use Mockery;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('in progress re dispatches itself after ten minutes', function () {
    $provider = batchProvider(BatchStatus::InProgress, []);
    bindProvider($provider);
    Queue::fake();

    (new CheckCompletionBatch('batch-123'))->handle();

    Queue::assertPushed(CheckCompletionBatch::class, function (CheckCompletionBatch $job): bool {
        return $job->batchId === 'batch-123';
    });
});

test('completed batches assemble pages from custom id keyed results', function () {
    $firstDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2607)->create();
    $secondDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    createGeneratingPage($firstDraw, 'batch-123');
    createGeneratingPage($secondDraw, 'batch-123');

    $provider = batchProvider(BatchStatus::Completed, [
        'page_megasena_2607' => GenerationResult::invalid('page_megasena_2607', ['title' => ''], 'Missing required title.'),
        'page_megasena_2608' => GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [],
        ]),
    ]);
    bindProvider($provider);

    (new CheckCompletionBatch('batch-123'))->handle();

    expect($firstDraw->fresh()->page->status)->toBe(PageStatus::Failed);
    expect($firstDraw->fresh()->page->blocks)->toBe([]);
    expect($secondDraw->fresh()->page->status)->toBe(PageStatus::Generated);
    expect($secondDraw->fresh()->page->layout)->toBe('draw-page');
    expect(array_column($secondDraw->fresh()->page->blocks, 'type'))->toBe(['hero-section', 'results-grid', 'individual-draw-details', 'related-links']);
});

test('expired batches mark every page failed', function () {
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    createGeneratingPage($draw, 'batch-expired');

    $provider = batchProvider(BatchStatus::Expired, []);
    bindProvider($provider);

    (new CheckCompletionBatch('batch-expired'))->handle();

    expect($draw->fresh()->page->status)->toBe(PageStatus::Failed);
    expect($draw->fresh()->page->generated_at)->toBeNull();
});

test('cancelled batches mark every page failed', function () {
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    createGeneratingPage($draw, 'batch-cancelled');

    $provider = batchProvider(BatchStatus::Failed, []);
    bindProvider($provider);

    (new CheckCompletionBatch('batch-cancelled'))->handle();

    expect($draw->fresh()->page->status)->toBe(PageStatus::Failed);
    expect($draw->fresh()->page->generated_at)->toBeNull();
});

function bindProvider(BatchContentProvider $provider): void
{
    $manager = Mockery::mock(ContentProviderManager::class);
    $manager->shouldReceive('driver')->andReturn($provider);

    app()->instance(ContentProviderManager::class, $manager);
}

function batchProvider(BatchStatus $status, array $results): BatchContentProvider
{
    return new class($status, $results) implements BatchContentProvider
    {
        public function __construct(private readonly BatchStatus $status, private readonly array $results) {}

        public function submitBatch(iterable $requests): string
        {
            return 'batch-123';
        }

        public function pollBatch(string $id): BatchStatus
        {
            return $this->status;
        }

        public function fetchResults(string $id): iterable
        {
            return $this->results;
        }

        public function generateOne(\App\DTOs\GenerationRequest $request): GenerationResult
        {
            return GenerationResult::invalid($request->customId);
        }
    };
}

function createGeneratingPage(Draw $draw, string $batchId): Page
{
    return Page::create([
        'draw_id' => $draw->id,
        'title' => 'Resultado '.$draw->draw_number,
        'slug' => $draw->type->value.'/resultado/'.$draw->draw_number,
        'layout' => 'draw-page',
        'blocks' => [],
        'status' => PageStatus::Generating->value,
        'batch_id' => $batchId,
        'provider' => 'openai',
    ]);
}
