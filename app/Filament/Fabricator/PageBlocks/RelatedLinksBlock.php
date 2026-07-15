<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use Filament\Forms\Components\Builder\Block;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class RelatedLinksBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('related-links')
            ->schema([]);
    }

    public static function mutateData(array $data): array
    {
        $drawId = $data['draw_id'] ?? null;

        if (! $drawId) {
            $data['related_links'] = [];

            return $data;
        }

        $draw = Draw::with(['page'])->find($drawId);

        if (! $draw) {
            $data['related_links'] = [];

            return $data;
        }

        $links = array_filter([
            'previous' => self::publishedDrawLink($draw, $draw->prev_draw_number, 'Concurso anterior'),
            'next' => self::publishedDrawLink($draw, $draw->next_draw_number, 'Próximo concurso'),
            'pillar' => self::publishedPillarLink($draw),
            'siblings' => self::publishedSiblingLinks($draw),
        ]);

        $data['draw'] = $draw;
        $data['related_links'] = $links;

        return $data;
    }

    private static function publishedDrawLink(Draw $draw, ?int $drawNumber, string $label): ?array
    {
        if (! filled($drawNumber)) {
            return null;
        }

        $relatedDraw = Draw::query()
            ->with(['page'])
            ->where('type', $draw->type)
            ->where('draw_number', $drawNumber)
            ->first();

        if (! $relatedDraw?->drawPage) {
            return null;
        }

        return [
            'type' => 'draw',
            'label' => $label,
            'title' => sprintf('%s concurso %d', $draw->game_name, $relatedDraw->draw_number),
            'url' => url($relatedDraw->drawPage->slug),
        ];
    }

    private static function publishedPillarLink(Draw $draw): ?array
    {
        $page = Page::query()
            ->where('slug', $draw->type->value)
            ->where('status', PageStatus::Published->value)
            ->first();

        if (! $page) {
            return null;
        }

        return [
            'type' => 'pillar',
            'label' => sprintf('%s', $draw->game_name),
            'title' => sprintf('Página pilar de %s', $draw->game_name),
            'url' => url($page->slug),
        ];
    }

    private static function publishedSiblingLinks(Draw $draw): array
    {
        return collect(GamesEnum::cases())
            ->reject(fn (GamesEnum $game): bool => $game === $draw->type)
            ->map(function (GamesEnum $game): ?array {
                $page = Page::query()
                    ->where('slug', $game->value)
                    ->where('status', PageStatus::Published->value)
                    ->first();

                if (! $page) {
                    return null;
                }

                return [
                    'type' => 'sibling',
                    'label' => match ($game) {
                        GamesEnum::MEGA_SENA => 'Mega Sena',
                        GamesEnum::LOTOFACIL => 'Lotofácil',
                        GamesEnum::QUINA => 'Quina',
                    },
                    'title' => sprintf('Ver %s', match ($game) {
                        GamesEnum::MEGA_SENA => 'Mega Sena',
                        GamesEnum::LOTOFACIL => 'Lotofácil',
                        GamesEnum::QUINA => 'Quina',
                    }),
                    'url' => url($page->slug),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
