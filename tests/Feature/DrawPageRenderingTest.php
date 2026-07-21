<?php

namespace Tests\Feature\DrawPageRenderingTest;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use App\Services\PageAssembler;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('public route requires published pages', function () {
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    $page = publishedPage($draw, GenerationResult::valid('page_megasena_2608', [
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
});

test('draw page layout renders drawn numbers from raw data and normalizes old format numbers', function () {
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 1)->create();
    publishedPage($draw, GenerationResult::valid('page_megasena_1', [
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
});

test('draw page layout emits article and faq json ld when faq exists', function () {
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    publishedPage($draw, GenerationResult::valid('page_megasena_2608', [
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
    $nodes = jsonLdNodes($html);

    $this->assertContains('Article', array_column($nodes, '@type'), jsonLdDiagnostic($html));
    $this->assertContains('FAQPage', array_column($nodes, '@type'), jsonLdDiagnostic($html));
});

test('draw page layout omits faq json ld when no faq block exists', function () {
    $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA->value, 2608)->create();
    publishedPage($draw, GenerationResult::valid('page_megasena_2608', [
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'megasena/resultado/2608',
        'meta_description' => 'Resumo do concurso 2608',
        'enrichment_blocks' => [],
    ]));

    $html = $this->get('/megasena/resultado/2608')->assertOk()->getContent();
    $nodes = jsonLdNodes($html);

    $this->assertContains('Article', array_column($nodes, '@type'), jsonLdDiagnostic($html));
    $this->assertNotContains('FAQPage', array_column($nodes, '@type'));
});

function publishedPage(Draw $draw, GenerationResult $result)
{
    $page = (new PageAssembler)->assemble($draw, $result);
    $page->update(['status' => PageStatus::Published->value]);

    return $page->fresh(['draw']);
}

/**
 * @return array<int, array<string, mixed>>
 */
function jsonLdNodes(string $html): array
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
function jsonLdDiagnostic(string $html): string
{
    preg_match_all('/<script type="application\\/ld\\+json">\\s*(.*?)\\s*<\\/script>/s', $html, $matches);

    $raw = $matches[1] ?? [];

    $page = Page::query()->first();

    return sprintf(
        "ld+json script tags found: %d; parsed nodes: %d; 'ld+json' substring present: %s; "
        .'stack rendered: %s.'."\n"
        .'page rows: %d; draw rows: %d; page->draw_id: %s; page->draw resolves: %s; '
        ."draw_date: %s; game_name: %s.\nRaw script bodies: %s",
        count($raw),
        count(jsonLdNodes($html)),
        str_contains($html, 'ld+json') ? 'yes' : 'NO',
        str_contains($html, '</head>') ? 'head closed' : 'NO HEAD',
        Page::query()->count(),
        Draw::query()->count(),
        var_export($page?->draw_id, true),
        $page?->draw === null ? 'NULL <-- article json-ld is skipped when this is null' : 'yes',
        var_export($page?->draw?->draw_date?->toAtomString(), true),
        var_export($page?->draw?->game_name, true),
        json_encode(array_map(fn (string $b): string => mb_substr($b, 0, 300), $raw)),
    );
}
