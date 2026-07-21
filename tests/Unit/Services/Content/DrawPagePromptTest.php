<?php

namespace Tests\Unit\Services\Content\DrawPagePromptTest;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\Content\DrawPagePrompt;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * @return array<string, array{0: GamesEnum, 1: int}>
 */
dataset('gamesProvider', function () {
    return [
        'mega-sena' => [GamesEnum::MEGA_SENA, 2608],
        'lotofacil' => [GamesEnum::LOTOFACIL, 1],
        'quina' => [GamesEnum::QUINA, 1],
    ];
});

test('context builder uses draw accessors for each game', function (GamesEnum $game, int $drawNumber) {
    $draw = Draw::factory()->fixture($game->value, $drawNumber)->create();
    $context = DrawPagePrompt::context($draw);

    expect($context['game'])->toBe($draw->type->value);
    expect($context['game_name'])->toBe($draw->game_name);
    expect($context['draw_number'])->toBe($draw->draw_number);
    expect($context['draw_date'])->toBe($draw->draw_date?->toDateString());
    expect($context['drawn_numbers'])->toBe($draw->drawn_numbers);
    expect($context['location'])->toBe($draw->location);
    expect($context['is_accumulated'])->toBe($draw->is_accumulated);
    expect($context['main_prize'])->toBe($draw->main_prize);
    expect($context['main_prize_winners'])->toBe($draw->main_prize_winners);
    expect($context['formatted_main_prize'])->toBe($draw->formatted_main_prize);
    expect($context['next_draw_date'])->toBe($draw->next_draw_date);
    expect($context['next_draw_number'])->toBe($draw->next_draw_number);
    expect($context['prev_draw_number'])->toBe($draw->prev_draw_number);
    expect($context['next_draw_estimate'])->toBe($draw->next_draw_estimate);
    expect($context['custom_id'])->toBe('page_'.$draw->type->value.'_'.$draw->draw_number);
})->with('gamesProvider');

test('schema exposes a closed enrichment type enum', function () {
    $schema = DrawPagePrompt::schema();
    $variants = $schema['properties']['enrichment_blocks']['items']['anyOf'];

    $types = array_map(fn (array $variant) => $variant['properties']['type']['enum'][0], $variants);

    expect($types)->toBe(['rich-text', 'hot-cold-analysis', 'comparison-previous', 'faq', 'how-to-play']);
    expect($schema['x-non_repeating_types'])->toBe(['rich-text', 'hot-cold-analysis', 'comparison-previous', 'faq', 'how-to-play']);
});

test('schema satisfies OpenAI strict structured-outputs constraints', function () {
    assertStrictJsonSchema(DrawPagePrompt::schema());
});

test('prompt version and prompt are defined in one place', function () {
    expect(DrawPagePrompt::version())->toBe('2026-07-15.v1');
    $this->assertStringContainsString('resultado {jogo} concurso {n}', DrawPagePrompt::systemPrompt());
});

/**
 * Recursively asserts every object node satisfies OpenAI's strict json_schema
 * mode: additionalProperties must be false, and every declared property key
 * must appear in required.
 */
function assertStrictJsonSchema(array $node, string $path = '$'): void
{
    if (($node['type'] ?? null) === 'object') {
        expect($node['additionalProperties'] ?? null)
            ->toBeFalse("Expected additionalProperties=false at {$path}");

        $properties = $node['properties'] ?? [];
        $required = $node['required'] ?? [];

        foreach (array_keys($properties) as $key) {
            expect(in_array($key, $required, true))
                ->toBeTrue("Expected \"{$key}\" to be required at {$path}");
        }

        foreach ($properties as $key => $propertySchema) {
            assertStrictJsonSchema($propertySchema, "{$path}.properties.{$key}");
        }
    }

    if (($node['type'] ?? null) === 'array' && isset($node['items'])) {
        assertStrictJsonSchema($node['items'], "{$path}.items");
    }

    foreach (['anyOf', 'oneOf'] as $union) {
        foreach ($node[$union] ?? [] as $index => $variant) {
            assertStrictJsonSchema($variant, "{$path}.{$union}[{$index}]");
        }
    }
}
