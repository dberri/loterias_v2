<?php

namespace Tests\Feature;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Services\PageAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DrawPageRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_route_requires_published_pages(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $page = $this->publishedPage($draw, GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [],
        ]));

        $this->get('/megasena/resultado/2608')->assertOk();

        $page->update(['status' => PageStatus::Generating->value]);
        $this->get('/megasena/resultado/2608')->assertNotFound();

        $page->update(['status' => PageStatus::Generated->value]);
        $this->get('/megasena/resultado/2608')->assertNotFound();

        $page->update(['status' => PageStatus::Failed->value]);
        $this->get('/megasena/resultado/2608')->assertNotFound();

        $this->get('/megasena/resultado/999999')->assertNotFound();
    }

    public function test_draw_page_layout_renders_drawn_numbers_from_raw_data_and_normalizes_old_format_numbers(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 1)->create();
        $page = $this->publishedPage($draw, GenerationResult::valid('page_megasena_1', [
            'title' => 'Resultado Mega-Sena concurso 1',
            'slug' => 'megasena/resultado/1',
            'meta_description' => 'Resumo do concurso 1',
            'enrichment_blocks' => [
                [
                    'type' => 'rich-text',
                    'html' => '<p>Primeiro bloco</p>',
                ],
                [
                    'type' => 'how-to-play',
                    'html' => '<p>Segundo bloco</p>',
                ],
            ],
        ]));

        $html = $this->get('/megasena/resultado/1')->assertOk()->getContent();

        foreach ($draw->drawn_numbers as $number) {
            $this->assertStringContainsString($number, $html);
        }

        $this->assertStringContainsString('Primeiro bloco', $html);
        $this->assertStringContainsString('Segundo bloco', $html);
        $this->assertTrue(strpos($html, 'Números Sorteados') < strpos($html, 'Primeiro bloco'));
        $this->assertTrue(strpos($html, 'Segundo bloco') < strpos($html, 'Links relacionados'));
        $this->assertStringNotContainsString('004', $html);
    }

    public function test_draw_page_layout_emits_article_and_faq_json_ld_when_faq_exists(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $this->publishedPage($draw, GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [
                [
                    'type' => 'faq',
                    'items' => [
                        [
                            'question' => 'Qual foi o concurso?',
                            'answer' => '<p>2608</p>',
                        ],
                    ],
                ],
            ],
        ]));

        $html = $this->get('/megasena/resultado/2608')->assertOk()->getContent();
        $nodes = $this->jsonLdNodes($html);

        $this->assertContains('Article', array_column($nodes, '@type'), $this->jsonLdDiagnostic($html));
        $this->assertContains('FAQPage', array_column($nodes, '@type'), $this->jsonLdDiagnostic($html));
    }

    public function test_draw_page_layout_omits_faq_json_ld_when_no_faq_block_exists(): void
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
        $this->publishedPage($draw, GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [],
        ]));

        $html = $this->get('/megasena/resultado/2608')->assertOk()->getContent();
        $nodes = $this->jsonLdNodes($html);

        $this->assertContains('Article', array_column($nodes, '@type'), $this->jsonLdDiagnostic($html));
        $this->assertNotContains('FAQPage', array_column($nodes, '@type'));
    }

    private function publishedPage(Draw $draw, GenerationResult $result)
    {
        $page = (new PageAssembler)->assemble($draw, $result);
        $page->update(['status' => PageStatus::Published->value]);

        return $page->fresh(['draw']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function jsonLdNodes(string $html): array
    {
        preg_match_all('/<script type="application\\/ld\\+json">\\s*(.*?)\\s*<\\/script>/s', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $json): ?array => json_decode(trim($json), true))
            ->filter(fn (?array $node): bool => is_array($node))
            ->values()
            ->all();
    }

    /**
     * A bare "array does not contain 'Article'" says nothing about WHY: a script
     * tag that was never emitted, one emitted with a body json_encode() refused
     * to produce, and one whose JSON is malformed all fail identically. This
     * distinguishes them, so a failure that only reproduces on another machine
     * is still diagnosable from the log alone.
     */
    private function jsonLdDiagnostic(string $html): string
    {
        preg_match_all('/<script type="application\\/ld\\+json">\\s*(.*?)\\s*<\\/script>/s', $html, $matches);

        $raw = $matches[1] ?? [];

        return sprintf(
            "ld+json script tags found: %d; parsed nodes: %d; 'ld+json' substring present: %s; "
            ."stack rendered: %s.\nRaw script bodies: %s",
            count($raw),
            count($this->jsonLdNodes($html)),
            str_contains($html, 'ld+json') ? 'yes' : 'NO',
            str_contains($html, '</head>') ? 'head closed' : 'NO HEAD',
            json_encode(array_map(fn (string $b): string => mb_substr($b, 0, 300), $raw)),
        );
    }
}
