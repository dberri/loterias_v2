<?php

namespace Tests\Feature\Commands\CreateContentTest;

use App\Contracts\BatchContentProvider;
use App\DTOs\GenerationRequest;
use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Services\ContentProviderManager;
use App\Services\PageAssembler;
use Mockery;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('create content uses the shared assembler and matches the expected blocks', function () {
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

    $provider = syncProvider($result);
    bindProvider($provider);

    $this->artisan('app:create-content', [
        'game' => GamesEnum::MEGA_SENA->value,
        'draw_number' => 2608,
    ])->assertExitCode(0);

    $expected = (new PageAssembler)->assemble($draw->fresh(), $result);
    $page = $draw->fresh()->page;

    expect($page->blocks)->toBe($expected->blocks);
    expect($page->status)->toBe($expected->status);
    expect($page->provider)->toBe('openai');
    expect($page->blocks)->toHaveCount(6);
    expect($page->blocks[0]['type'])->toBe('hero-section');
    expect($page->blocks[5]['type'])->toBe('related-links');
});

test('create content marks invalid responses failed and re runnable', function () {
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    $result = GenerationResult::invalid('page_megasena_2608', ['title' => ''], 'Missing required title.');

    $provider = syncProvider($result);
    bindProvider($provider);

    $this->artisan('app:create-content', [
        'game' => GamesEnum::MEGA_SENA->value,
        'draw_number' => 2608,
    ])->assertExitCode(0);

    $page = $draw->fresh()->page;
    expect($page->status)->toBe(PageStatus::Failed);
    expect($page->blocks)->toBe([]);
    expect($page->provider)->toBe('openai');

    $page->update(['status' => PageStatus::Generated->value]);

    expect($page->fresh()->status)->toBe(PageStatus::Generated);
});

function bindProvider(BatchContentProvider $provider): void
{
    $manager = Mockery::mock(ContentProviderManager::class);
    $manager->shouldReceive('driver')->with('openai')->andReturn($provider);

    app()->instance(ContentProviderManager::class, $manager);
}

function syncProvider(GenerationResult $result): BatchContentProvider
{
    return new class($result) implements BatchContentProvider
    {
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
