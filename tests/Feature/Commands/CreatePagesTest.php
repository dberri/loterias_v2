<?php

namespace Tests\Feature\Commands;

use App\Contracts\BatchContentProvider;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use App\Services\ContentProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CreatePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_pages_submits_one_batch_and_creates_generating_pages(): void
    {
        $firstDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2607)->create();
        $secondDraw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $provider = $this->recordingProvider('batch-123');
        $this->bindProvider($provider);

        $this->artisan('app:create-pages', [
            'game' => GamesEnum::MEGA_SENA->value,
            'quantity' => 2,
        ])->assertExitCode(0);

        $this->assertCount(2, $provider->submittedRequests);
        $this->assertSame([
            'page_megasena_2608',
            'page_megasena_2607',
        ], array_map(fn ($request) => $request->customId, $provider->submittedRequests));

        $this->assertSame(PageStatus::Generating, $firstDraw->fresh()->page->status);
        $this->assertSame(PageStatus::Generating, $secondDraw->fresh()->page->status);
        $this->assertSame('batch-123', $firstDraw->fresh()->page->batch_id);
        $this->assertSame('openai', $firstDraw->fresh()->page->provider);
    }

    public function test_create_pages_skips_draws_that_already_have_pages_and_limits_to_available_draws(): void
    {
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

        $provider = $this->recordingProvider('batch-456');
        $this->bindProvider($provider);

        $this->artisan('app:create-pages', [
            'game' => GamesEnum::MEGA_SENA->value,
            'quantity' => 5,
        ])->assertExitCode(0);

        $this->assertCount(1, $provider->submittedRequests);
        $this->assertSame('page_megasena_2607', $provider->submittedRequests[0]->customId);
        $this->assertSame(2, Page::count());
        $this->assertSame(PageStatus::Published, $existingDraw->fresh()->page->status);
        $this->assertSame(PageStatus::Generating, $eligibleDraw->fresh()->page->status);
    }

    private function bindProvider(BatchContentProvider $provider): void
    {
        $manager = Mockery::mock(ContentProviderManager::class);
        $manager->shouldReceive('driver')->with('openai')->andReturn($provider);

        $this->app->instance(ContentProviderManager::class, $manager);
    }

    private function recordingProvider(string $batchId): BatchContentProvider
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
}
