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
    public static function getBlockSchema(): Block
    {
        return Block::make('results-grid')
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
        $query = Draw::query()
            ->with(['drawPage'])
            ->where('game', $data['lottery_type'])
            ->orderBy('draw_date', 'desc');
            
        // Apply date filters if provided
        if (!empty($data['date_from'])) {
            $query->whereDate('draw_date', '>=', $data['date_from']);
        }
        
        if (!empty($data['date_to'])) {
            $query->whereDate('draw_date', '<=', $data['date_to']);
        }
        
        // Filter accumulated draws only
        if ($data['show_accumulated_only'] ?? false) {
            $query->where('accumulated', true);
        }
        
        if ($data['enable_pagination'] ?? true) {
            $data['results'] = $query->paginate($data['results_per_page'] ?? 20);
        } else {
            $data['results'] = $query->limit($data['results_per_page'] ?? 20)->get();
        }
        
        return $data;
    }
}
