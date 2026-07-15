<?php

namespace Tests\Unit\Services\Content;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\Content\DrawPagePrompt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DrawPagePromptTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: GamesEnum, 1: int}>
     */
    public static function gamesProvider(): array
    {
        return [
            'mega-sena' => [GamesEnum::MEGA_SENA, 2608],
            'lotofacil' => [GamesEnum::LOTOFACIL, 1],
            'quina' => [GamesEnum::QUINA, 1],
        ];
    }

    #[DataProvider('gamesProvider')]
    public function test_context_builder_uses_draw_accessors_for_each_game(GamesEnum $game, int $drawNumber): void
    {
        $draw = Draw::factory()->fixture($game->value, $drawNumber)->create();
        $context = DrawPagePrompt::context($draw);

        $this->assertSame($draw->type->value, $context['game']);
        $this->assertSame($draw->game_name, $context['game_name']);
        $this->assertSame($draw->draw_number, $context['draw_number']);
        $this->assertSame($draw->draw_date?->toDateString(), $context['draw_date']);
        $this->assertSame($draw->drawn_numbers, $context['drawn_numbers']);
        $this->assertSame($draw->location, $context['location']);
        $this->assertSame($draw->is_accumulated, $context['is_accumulated']);
        $this->assertSame($draw->main_prize, $context['main_prize']);
        $this->assertSame($draw->main_prize_winners, $context['main_prize_winners']);
        $this->assertSame($draw->formatted_main_prize, $context['formatted_main_prize']);
        $this->assertSame($draw->next_draw_date, $context['next_draw_date']);
        $this->assertSame($draw->next_draw_number, $context['next_draw_number']);
        $this->assertSame($draw->prev_draw_number, $context['prev_draw_number']);
        $this->assertSame($draw->next_draw_estimate, $context['next_draw_estimate']);
        $this->assertSame('page_'.$draw->type->value.'_'.$draw->draw_number, $context['custom_id']);
    }

    public function test_schema_exposes_a_closed_enrichment_type_enum(): void
    {
        $schema = DrawPagePrompt::schema();

        $this->assertSame(
            ['rich-text', 'hot-cold-analysis', 'comparison-previous', 'faq', 'how-to-play'],
            $schema['properties']['enrichment_blocks']['items']['properties']['type']['enum'],
        );
        $this->assertSame(
            ['rich-text', 'hot-cold-analysis', 'comparison-previous', 'faq', 'how-to-play'],
            $schema['x-non_repeating_types'],
        );
    }

    public function test_prompt_version_and_prompt_are_defined_in_one_place(): void
    {
        $this->assertSame('2026-07-15.v1', DrawPagePrompt::version());
        $this->assertStringContainsString('resultado {jogo} concurso {n}', DrawPagePrompt::systemPrompt());
    }
}
