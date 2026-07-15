<?php

namespace Tests\Unit\Services;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Services\PageAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

    public function test_block_order_keeps_the_app_spine_first_ai_blocks_in_order_and_related_links_last(): void
    {
        $draw = $this->draw();

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

        $this->assertSame(
            [
                'hero-section',
                'results-grid',
                'individual-draw-details',
                'faq',
                'rich-text-content',
                'how-to-play',
                'related-links',
            ],
            array_column($page->blocks, 'type'),
        );
        $this->assertNotNull($page->generated_at);
    }

    public function test_auto_publish_false_keeps_generated_and_true_publishes(): void
    {
        $draw = $this->draw();
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

        $this->assertSame(PageStatus::Generated, $draftPage->status);
        $this->assertSame(PageStatus::Published, $publishedPage->status);
        Config::set('content.auto_publish', false);
    }

    public function test_auto_publish_true_does_not_override_invalid_results(): void
    {
        Config::set('content.auto_publish', true);
        $draw = $this->draw();

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

        $this->assertSame(PageStatus::Failed, $page->status);
        $this->assertNotSame(PageStatus::Published, $page->status);
        Config::set('content.auto_publish', false);
    }

    private function draw(): Draw
    {
        return Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    }
}
