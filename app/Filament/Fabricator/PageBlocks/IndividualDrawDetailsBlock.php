<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Models\Draw;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class IndividualDrawDetailsBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('individual-draw-details')
            ->label('Individual Draw Details')
            ->icon('heroicon-o-document-text')
            ->schema([
                Select::make('draw_id')
                    ->label('Select Draw')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Draw::where('draw_number', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn ($draw) => [
                            $draw->id => "{$draw->type} - Concurso {$draw->draw_number}",
                        ])
                        ->toArray()
                    )
                    ->getOptionLabelUsing(fn ($value): ?string => Draw::find($value)?->let(fn ($draw) => "{$draw->type} - Concurso {$draw->draw_number}"
                    )
                    ),

                Toggle::make('show_prize_breakdown')
                    ->label('Show Prize Breakdown')
                    ->default(true),

                Toggle::make('show_winners_by_tier')
                    ->label('Show Winners by Prize Tier')
                    ->default(true),

                Toggle::make('show_statistics')
                    ->label('Show Number Statistics')
                    ->default(true),

                Toggle::make('show_comparison')
                    ->label('Show Comparison with Previous Draw')
                    ->default(false),

                TextInput::make('custom_title')
                    ->label('Custom Title (Optional)')
                    ->placeholder('Will use draw info if empty'),
            ]);
    }

    public static function mutateData(array $data): array
    {
        if (! empty($data['draw_id'])) {
            $draw = Draw::with(['page'])->find($data['draw_id']);
            $data['draw'] = $draw;

            if ($draw && ($data['show_comparison'] ?? false)) {
                $previousDraw = Draw::where('type', $draw->type)
                    ->where('draw_date', '<', $draw->draw_date)
                    ->orderBy('draw_date', 'desc')
                    ->first();
                $data['previous_draw'] = $previousDraw;
            }

            if ($draw) {
                $rateio = $draw->raw_data['listaRateioPremio'] ?? [];
                $data['location'] = $draw->location;
                $data['is_accumulated'] = $draw->is_accumulated;
                $data['next_draw_estimate'] = $draw->next_draw_estimate;
                $data['main_prize'] = $draw->main_prize;
                $data['main_prize_winners'] = $draw->main_prize_winners;
                $data['formatted_main_prize'] = $draw->formatted_main_prize;
                $data['prize_tiers'] = collect($rateio)
                    ->map(fn (array $tier): array => [
                        'faixa' => $tier['faixa'] ?? null,
                        'numeroDeGanhadores' => $tier['numeroDeGanhadores'] ?? null,
                        'valorPremio' => $tier['valorPremio'] ?? null,
                    ])
                    ->values()
                    ->all();
                $data['winner_cities'] = collect($draw->raw_data['listaMunicipioUFGanhadores'] ?? [])
                    ->map(fn (array $city): array => [
                        'municipio' => $city['municipio'] ?? null,
                        'uf' => $city['uf'] ?? null,
                        'ganhadores' => $city['ganhadores'] ?? null,
                    ])
                    ->values()
                    ->all();
            }
        }

        return $data;
    }
}
