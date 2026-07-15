<?php

namespace Tests\Unit\Services;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Services\PageAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageAssemblerTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidCases(): array
    {
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
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_results_are_marked_failed_without_writing_blocks(GenerationResult $result, string $reason): void
    {
        $draw = $this->draw();

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

        $this->assertSame(PageStatus::Failed, $page->status);
        $this->assertSame([], $page->blocks);
        $this->assertSame($draw->id, $page->draw_id);
    }

    public function test_failed_page_can_be_re_run_and_saved_with_valid_blocks(): void
    {
        $draw = $this->draw();
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

        $this->assertSame(PageStatus::Generated, $page->status);
        $this->assertCount(4, $page->blocks);
        $this->assertSame('hero-section', $page->blocks[0]['type']);
        $this->assertSame('related-links', $page->blocks[3]['type']);
    }

    private function draw(): Draw
    {
        return Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    }
}
