<?php

namespace Tests\Feature\Commands;

use App\Contracts\BatchContentProvider;
use App\DTOs\GenerationRequest;
use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Services\ContentProviderManager;
use App\Services\PageAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CreateContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_content_uses_the_shared_assembler_and_matches_the_expected_blocks(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $result = GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [
                [
                    'type' => 'rich-text',
                    'html' => '<p>Texto</p>',
                ],
                [
                    'type' => 'faq',
                    'items' => [
                        ['question' => 'Pergunta?', 'answer' => '<p>Resposta</p>'],
                    ],
                ],
            ],
        ]);

        $provider = $this->syncProvider($result);
        $this->bindProvider($provider);

        $this->artisan('app:create-content', [
            'game' => GamesEnum::MEGA_SENA->value,
            'draw_number' => 2608,
        ])->assertExitCode(0);

        $expected = (new PageAssembler)->assemble($draw->fresh(), $result);
        $page = $draw->fresh()->page;

        $this->assertSame($expected->blocks, $page->blocks);
        $this->assertSame($expected->status, $page->status);
        $this->assertSame('openai', $page->provider);
        $this->assertCount(6, $page->blocks);
        $this->assertSame('hero-section', $page->blocks[0]['type']);
        $this->assertSame('related-links', $page->blocks[5]['type']);
    }

    public function test_create_content_marks_invalid_responses_failed_and_re_runnable(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $result = GenerationResult::invalid('page_megasena_2608', ['title' => ''], 'Missing required title.');

        $provider = $this->syncProvider($result);
        $this->bindProvider($provider);

        $this->artisan('app:create-content', [
            'game' => GamesEnum::MEGA_SENA->value,
            'draw_number' => 2608,
        ])->assertExitCode(0);

        $page = $draw->fresh()->page;
        $this->assertSame(PageStatus::Failed, $page->status);
        $this->assertSame([], $page->blocks);
        $this->assertSame('openai', $page->provider);

        $page->update(['status' => PageStatus::Generated->value]);

        $this->assertSame(PageStatus::Generated, $page->fresh()->status);
    }

    private function bindProvider(BatchContentProvider $provider): void
    {
        $manager = Mockery::mock(ContentProviderManager::class);
        $manager->shouldReceive('driver')->with('openai')->andReturn($provider);

        $this->app->instance(ContentProviderManager::class, $manager);
    }

    private function syncProvider(GenerationResult $result): BatchContentProvider
    {
        return new class($result) implements BatchContentProvider {
            public function __construct(private readonly GenerationResult $result) {}

            public function submitBatch(iterable $requests): string
            {
                return 'batch-123';
            }

            public function pollBatch(string $id): \App\DTOs\BatchStatus
            {
                return \App\DTOs\BatchStatus::Completed;
            }

            public function fetchResults(string $id): iterable
            {
                return [];
            }

            public function generateOne(GenerationRequest $request): GenerationResult
            {
                return $this->result;
            }
        };
    }
}
