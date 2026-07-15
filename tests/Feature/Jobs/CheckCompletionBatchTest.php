<?php

namespace Tests\Feature\Jobs;

use App\Contracts\BatchContentProvider;
use App\DTOs\BatchStatus;
use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Jobs\CheckCompletionBatch;
use App\Models\Draw;
use App\Models\Page;
use App\Services\ContentProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CheckCompletionBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_progress_re_dispatches_itself_after_ten_minutes(): void
    {
        $provider = $this->batchProvider(BatchStatus::InProgress, []);
        $this->bindProvider($provider);
        Queue::fake();

        (new CheckCompletionBatch('batch-123'))->handle();

        Queue::assertPushed(CheckCompletionBatch::class, function (CheckCompletionBatch $job): bool {
            return $job->batchId === 'batch-123';
        });
    }

    public function test_completed_batches_assemble_pages_from_custom_id_keyed_results(): void
    {
        $firstDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2607)->create();
        $secondDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $this->createGeneratingPage($firstDraw, 'batch-123');
        $this->createGeneratingPage($secondDraw, 'batch-123');

        $provider = $this->batchProvider(BatchStatus::Completed, [
            'page_megasena_2607' => GenerationResult::invalid('page_megasena_2607', ['title' => ''], 'Missing required title.'),
            'page_megasena_2608' => GenerationResult::valid('page_megasena_2608', [
                'title' => 'Resultado Mega-Sena concurso 2608',
                'slug' => 'megasena/resultado/2608',
                'meta_description' => 'Resumo do concurso 2608',
                'enrichment_blocks' => [],
            ]),
        ]);
        $this->bindProvider($provider);

        (new CheckCompletionBatch('batch-123'))->handle();

        $this->assertSame(PageStatus::Failed, $firstDraw->fresh()->page->status);
        $this->assertSame([], $firstDraw->fresh()->page->blocks);
        $this->assertSame(PageStatus::Generated, $secondDraw->fresh()->page->status);
        $this->assertSame('draw-page', $secondDraw->fresh()->page->layout);
        $this->assertSame(['hero-section', 'results-grid', 'individual-draw-details', 'related-links'], array_column($secondDraw->fresh()->page->blocks, 'type'));
    }

    public function test_expired_batches_mark_every_page_failed(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $this->createGeneratingPage($draw, 'batch-expired');

        $provider = $this->batchProvider(BatchStatus::Expired, []);
        $this->bindProvider($provider);

        (new CheckCompletionBatch('batch-expired'))->handle();

        $this->assertSame(PageStatus::Failed, $draw->fresh()->page->status);
        $this->assertNull($draw->fresh()->page->generated_at);
    }

    public function test_cancelled_batches_mark_every_page_failed(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $this->createGeneratingPage($draw, 'batch-cancelled');

        $provider = $this->batchProvider(BatchStatus::Failed, []);
        $this->bindProvider($provider);

        (new CheckCompletionBatch('batch-cancelled'))->handle();

        $this->assertSame(PageStatus::Failed, $draw->fresh()->page->status);
        $this->assertNull($draw->fresh()->page->generated_at);
    }

    private function bindProvider(BatchContentProvider $provider): void
    {
        $manager = Mockery::mock(ContentProviderManager::class);
        $manager->shouldReceive('driver')->andReturn($provider);

        $this->app->instance(ContentProviderManager::class, $manager);
    }

    private function batchProvider(BatchStatus $status, array $results): BatchContentProvider
    {
        return new class($status, $results) implements BatchContentProvider {
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

    private function createGeneratingPage(Draw $draw, string $batchId): Page
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
}
