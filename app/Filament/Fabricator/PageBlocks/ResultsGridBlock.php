<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Models\Draw;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class ResultsGridBlock extends PageBlock
{
    protected static string $name = 'results-grid';

    public static function defineBlock(Block $block): Block
    {
        return $block
            ->label('Results Grid')
            ->icon('heroicon-o-table-cells')
            ->schema([
                TextInput::make('title')
                    ->label('Block Title')
                    ->default('Resultados dos Sorteios')
                    ->required(),

                Select::make('lottery_type')
                    ->label('Lottery Type')
                    ->options([
                        'megasena' => 'Mega Sena',
                        'lotofacil' => 'Lotofácil',
                        'quina' => 'Quina',
                    ])
                    ->required(),

                TextInput::make('results_per_page')
                    ->label('Results Per Page')
                    ->numeric()
                    ->default(20)
                    ->minValue(5)
                    ->maxValue(50)
                    ->required(),

                DatePicker::make('date_from')
                    ->label('Filter From Date (Optional)')
                    ->format('Y-m-d'),

                DatePicker::make('date_to')
                    ->label('Filter To Date (Optional)')
                    ->format('Y-m-d'),

                Toggle::make('show_accumulated_only')
                    ->label('Show Only Accumulated Draws')
                    ->default(false),

                Toggle::make('enable_pagination')
                    ->label('Enable Pagination')
                    ->default(true),
            ]);
    }

    public static function mutateData(array $data): array
    {
        $data = array_merge([
            'title' => 'Resultados dos Sorteios',
            'lottery_type' => 'megasena',
            'results_per_page' => 20,
            'date_from' => null,
            'date_to' => null,
            'show_accumulated_only' => false,
            'enable_pagination' => true,
        ], $data);

        $drawId = $data['draw_id'] ?? null;

        if ($drawId) {
            $draw = Draw::query()->with(['page'])->find($drawId);

            if ($draw) {
                $data['lottery_type'] = $draw->type->value;
                $data['enable_pagination'] = false;
                $data['results_per_page'] = 1;
                $data['results'] = collect([$draw]);

                return $data;
            }
        }

        $query = Draw::query()
            ->with(['page'])
            ->where('type', $data['lottery_type'])
            ->orderBy('draw_number', 'desc');

        // Apply date filters if provided
        if (! empty($data['date_from'])) {
            $query->whereDate('draw_date', '>=', $data['date_from']);
        }

        if (! empty($data['date_to'])) {
            $query->whereDate('draw_date', '<=', $data['date_to']);
        }

        // Filter accumulated draws only
        if ($data['show_accumulated_only'] ?? false) {
            $query->where('raw_data->acumulado', true);
        }

        if ($data['enable_pagination'] ?? true) {
            $data['results'] = $query->paginate($data['results_per_page'] ?? 20);
        } else {
            $data['results'] = $query->limit($data['results_per_page'] ?? 20)->get();
        }

        return $data;
    }
}
