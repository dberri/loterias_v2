<?php

namespace Tests\Browser\Fixtures;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Models\Page;
use Illuminate\Support\Str;

/**
 * Builds one Draw + one Published Page whose blocks each carry a unique,
 * assertable marker, so a browser test can prove every block actually
 * renders its own content (PEST-08) rather than asserting the page merely
 * returns 200.
 *
 * Only the 10 implemented page blocks are covered here. `breadcrumb`,
 * `comparison-table`, `simulation` and `timeline` are unimplemented `//`
 * stub templates (see design.md "Blocks Covered by PEST-08") and are
 * intentionally excluded — tracked as follow-up PEST-F3, not silently
 * dropped.
 */
class DrawPageFixture
{
    /**
     * @return array{draw: Draw, page: Page, markers: array<string, string>}
     */
    public static function make(): array
    {
        $draw = Draw::factory()->fixture(GamesEnum::MEGA_SENA, 2608)->create();

        $markers = [
            'hero-section' => 'Marker-HeroSection-'.Str::random(8),
            'results-grid' => 'Marker-ResultsGrid-'.Str::random(8),
            'individual-draw-details' => 'Marker-IndividualDrawDetails-'.Str::random(8),
            'faq' => 'Marker-Faq-'.Str::random(8),
            // related-links renders no configurable title of its own — with no other
            // published sibling/previous/next draw pages seeded, it deterministically
            // falls back to this literal empty-state string, which appears nowhere
            // else on the page. That string is this block's marker.
            'related-links' => 'Nenhum link relacionado publicado no momento.',
            'rich-text-content' => 'Marker-RichTextContent-'.Str::random(8),
            'statistics-cards' => 'Marker-StatisticsCards-'.Str::random(8),
            'latest-results' => 'Marker-LatestResults-'.Str::random(8),
            'number-generator' => 'Marker-NumberGenerator-'.Str::random(8),
            'how-to-play' => 'Marker-HowToPlay-'.Str::random(8),
        ];

        $blocks = [
            [
                'type' => 'hero-section',
                'data' => [
                    'title' => $markers['hero-section'],
                    'draw_id' => $draw->id,
                ],
            ],
            [
                'type' => 'results-grid',
                'data' => [
                    'title' => $markers['results-grid'],
                    'lottery_type' => $draw->type->value,
                    'draw_id' => $draw->id,
                ],
            ],
            [
                'type' => 'individual-draw-details',
                'data' => [
                    'draw_id' => $draw->id,
                    'custom_title' => $markers['individual-draw-details'],
                ],
            ],
            [
                'type' => 'faq',
                'data' => [
                    'title' => $markers['faq'],
                    'layout_style' => 'accordion',
                    'category' => 'general',
                    'faqs' => [],
                ],
            ],
            [
                'type' => 'related-links',
                'data' => [
                    'draw_id' => $draw->id,
                ],
            ],
            [
                'type' => 'rich-text-content',
                'data' => [
                    'title' => $markers['rich-text-content'],
                ],
            ],
            [
                'type' => 'statistics-cards',
                'data' => [
                    'title' => $markers['statistics-cards'],
                    'lottery_type' => 'all',
                    'show_total_draws' => true,
                    'show_total_winners' => true,
                    'show_accumulated_count' => true,
                    'show_biggest_prize' => true,
                    'show_latest_draw' => true,
                    'show_next_estimated' => false,
                ],
            ],
            [
                'type' => 'latest-results',
                'data' => [
                    'title' => $markers['latest-results'],
                    'lottery_type' => 'all',
                    'limit' => 5,
                    'show_prizes' => true,
                    'show_dates' => true,
                    'link_to_details' => true,
                ],
            ],
            [
                'type' => 'number-generator',
                'data' => [
                    'title' => $markers['number-generator'],
                    'description' => 'Gere números aleatórios para suas apostas nas loterias',
                    'default_lottery' => 'megasena',
                    'allow_lottery_selection' => true,
                    'show_statistics' => true,
                    'save_generated_numbers' => false,
                    'primary_color' => '#3B82F6',
                ],
            ],
            [
                'type' => 'how-to-play',
                'data' => [
                    'title' => $markers['how-to-play'],
                    'content' => '<p>Conteúdo de teste.</p>',
                ],
            ],
        ];

        $page = Page::factory()->published()->create([
            'draw_id' => $draw->id,
            'title' => 'Resultado Mega-Sena concurso '.$draw->draw_number,
            'slug' => $draw->type->value.'/resultado/'.$draw->draw_number,
            'layout' => 'draw-page',
            'blocks' => $blocks,
        ]);

        return [
            'draw' => $draw,
            'page' => $page,
            'markers' => $markers,
        ];
    }
}
