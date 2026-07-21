<?php

namespace Tests\Unit\Services\PageAssemblerTest;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Services\PageAssembler;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

dataset('invalidCases', function () {
    return [
        'malformed-json' => [
            GenerationResult::invalid('page_megasena_2608', null, 'Malformed JSON response.'),
            'Malformed JSON response.',
        ],
        'unknown-type' => [
            GenerationResult::valid('page_megasena_2608', [
                'title' => 'Resultado',
                'slug' => 'mega-sena/resultado/2608',
                'meta_description' => 'Resumo',
                'enrichment_blocks' => [
                    [
                        'type' => 'bogus',
                        'html' => '<p>oops</p>',
                    ],
                ],
            ]),
            'Unknown or disallowed enrichment block type.',
        ],
        'duplicate-type' => [
            GenerationResult::valid('page_megasena_2608', [
                'title' => 'Resultado',
                'slug' => 'mega-sena/resultado/2608',
                'meta_description' => 'Resumo',
                'enrichment_blocks' => [
                    [
                        'type' => 'faq',
                        'items' => [
                            ['q' => 'Pergunta 1', 'a' => 'Resposta 1'],
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'items' => [
                            ['q' => 'Pergunta 2', 'a' => 'Resposta 2'],
                        ],
                    ],
                ],
            ]),
            'Duplicate enrichment block type [faq].',
        ],
        'empty-prose' => [
            GenerationResult::valid('page_megasena_2608', [
                'title' => 'Resultado',
                'slug' => 'mega-sena/resultado/2608',
                'meta_description' => 'Resumo',
                'enrichment_blocks' => [
                    [
                        'type' => 'rich-text',
                        'html' => '',
                    ],
                ],
            ]),
            'Missing required prose.',
        ],
    ];
});

test('invalid results are marked failed without writing blocks', function (GenerationResult $result, string $reason) {
    $draw = draw();

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($draw, $result, $reason): bool {
            return $message === 'Draw page generation failed.'
                && $context['custom_id'] === $result->customId
                && $context['draw_id'] === $draw->id
                && $context['game'] === $draw->type->value
                && $context['draw_number'] === $draw->draw_number
                && $context['reason'] === $reason;
        });

    $page = (new PageAssembler)->assemble($draw, $result);
    $page->refresh();

    expect($page->status)->toBe(PageStatus::Failed);
    expect($page->blocks)->toBe([]);
    expect($page->draw_id)->toBe($draw->id);
})->with('invalidCases');

test('failed page can be re run and saved with valid blocks', function () {
    $draw = draw();
    $assembler = new PageAssembler;

    Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context) use ($draw): bool {
        return $message === 'Draw page generation failed.'
            && $context['draw_id'] === $draw->id;
    });

    $assembler->assemble($draw, GenerationResult::invalid('page_megasena_2608', null, 'Malformed JSON response.'));

    $page = $assembler->assemble($draw, GenerationResult::valid('page_megasena_2608', [
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'mega-sena/resultado/2608',
        'meta_description' => 'Resumo do concurso 2608',
        'enrichment_blocks' => [],
    ]));
    $page->refresh();

    expect($page->status)->toBe(PageStatus::Generated);
    expect($page->blocks)->toHaveCount(4);
    expect($page->blocks[0]['type'])->toBe('hero-section');
    expect($page->blocks[3]['type'])->toBe('related-links');
});

test('block order keeps the app spine first ai blocks in order and related links last', function () {
    $draw = draw();

    $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'mega-sena/resultado/2608',
        'meta_description' => 'Resumo do concurso 2608',
        'enrichment_blocks' => [
            [
                'type' => 'faq',
                'items' => [
                    ['q' => 'Pergunta 1', 'a' => 'Resposta 1'],
                ],
            ],
            [
                'type' => 'rich-text',
                'html' => '<p>Texto</p>',
            ],
            [
                'type' => 'how-to-play',
                'html' => '<p>Como jogar</p>',
            ],
        ],
    ]));

    expect(array_column($page->blocks, 'type'))->toBe([
        'hero-section',
        'results-grid',
        'individual-draw-details',
        'faq',
        'rich-text-content',
        'how-to-play',
        'related-links',
    ]);
    expect($page->generated_at)->not->toBeNull();
});

test('auto publish false keeps generated and true publishes', function () {
    $draw = draw();
    $result = GenerationResult::valid('page_megasena_2608', [
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'mega-sena/resultado/2608',
        'meta_description' => 'Resumo do concurso 2608',
        'enrichment_blocks' => [],
    ]);

    Config::set('content.auto_publish', false);
    $draftPage = (new PageAssembler)->assemble($draw, $result);
    $draftPage->refresh();

    Config::set('content.auto_publish', true);
    $publishedPage = (new PageAssembler)->assemble($draw, $result);
    $publishedPage->refresh();

    expect($draftPage->status)->toBe(PageStatus::Generated);
    expect($publishedPage->status)->toBe(PageStatus::Published);
    Config::set('content.auto_publish', false);
});

test('auto publish true does not override invalid results', function () {
    Config::set('content.auto_publish', true);
    $draw = draw();

    $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
        'title' => '',
        'slug' => 'mega-sena/resultado/2608',
        'meta_description' => 'Resumo',
        'enrichment_blocks' => [
            [
                'type' => 'faq',
                'items' => [
                    ['q' => 'Pergunta 1', 'a' => 'Resposta 1'],
                ],
            ],
        ],
    ]));
    $page->refresh();

    expect($page->status)->toBe(PageStatus::Failed);
    $this->assertNotSame(PageStatus::Published, $page->status);
    Config::set('content.auto_publish', false);
});

function draw(): Draw
{
    return Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
}
