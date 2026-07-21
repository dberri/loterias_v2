<?php

namespace App\Services\Content;

use App\DTOs\GenerationRequest;
use App\Models\Draw;

final class DrawPagePrompt
{
    private const VERSION = '2026-07-15.v1';

    private const ENRICHMENT_TYPES = [
        'rich-text',
        'hot-cold-analysis',
        'comparison-previous',
        'faq',
        'how-to-play',
    ];

    public static function version(): string
    {
        return self::VERSION;
    }

    public static function systemPrompt(): string
    {
        return 'Você é um jornalista brasileiro especializado em resultados de loteria. Escreva em português do Brasil, com tom jornalístico, sem inventar ou alterar qualquer número, prêmio, data ou quantidade de ganhadores. Use um gancho SEO natural com "resultado {jogo} concurso {n}" na abertura e produza uma estrutura clara, escaneável e factual.';
    }

    public static function context(Draw $draw): array
    {
        return [
            'custom_id' => GenerationRequest::buildCustomId($draw->type, $draw->draw_number),
            'game' => $draw->type->value,
            'game_name' => $draw->game_name,
            'draw_number' => $draw->draw_number,
            'draw_date' => $draw->draw_date?->toDateString(),
            'drawn_numbers' => $draw->drawn_numbers,
            'location' => $draw->location,
            'is_accumulated' => $draw->is_accumulated,
            'main_prize' => $draw->main_prize,
            'main_prize_winners' => $draw->main_prize_winners,
            'formatted_main_prize' => $draw->formatted_main_prize,
            'next_draw_date' => $draw->next_draw_date,
            'next_draw_number' => $draw->next_draw_number,
            'prev_draw_number' => $draw->prev_draw_number,
            'next_draw_estimate' => $draw->next_draw_estimate,
        ];
    }

    public static function prompt(Draw $draw): string
    {
        return json_encode(self::context($draw), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'x-non_repeating_types' => self::ENRICHMENT_TYPES,
            'required' => [
                'title',
                'slug',
                'meta_description',
                'enrichment_blocks',
            ],
            'properties' => [
                'title' => [
                    'type' => 'string',
                ],
                'slug' => [
                    'type' => 'string',
                ],
                'meta_description' => [
                    'type' => 'string',
                ],
                'enrichment_blocks' => [
                    'type' => 'array',
                    'items' => [
                        'anyOf' => [
                            self::proseBlockSchema('rich-text'),
                            self::commentaryBlockSchema('hot-cold-analysis'),
                            self::commentaryBlockSchema('comparison-previous'),
                            self::faqBlockSchema(),
                            self::proseBlockSchema('how-to-play'),
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function enrichmentTypes(): array
    {
        return self::ENRICHMENT_TYPES;
    }

    private static function proseBlockSchema(string $type): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['type', 'html'],
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => [$type],
                ],
                'html' => [
                    'type' => 'string',
                ],
            ],
        ];
    }

    private static function commentaryBlockSchema(string $type): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['type', 'commentary'],
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => [$type],
                ],
                'commentary' => [
                    'type' => 'string',
                ],
            ],
        ];
    }

    private static function faqBlockSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['type', 'items'],
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['faq'],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['question', 'answer'],
                        'properties' => [
                            'question' => [
                                'type' => 'string',
                            ],
                            'answer' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
