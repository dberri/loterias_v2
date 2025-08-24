<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Enums\GamesEnum;
use App\Models\Draw;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class LatestResultsBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('latest-results')
            ->label('Latest Results')
            ->icon('heroicon-o-trophy')
            ->schema([
                TextInput::make('title')
                    ->label('Block Title')
                    ->default('Últimos Resultados')
                    ->required(),
                    
                Select::make('lottery_type')
                    ->label('Lottery Type')
                    ->options([
                        'all' => 'All Lotteries',
                        'megasena' => 'Mega Sena',
                        'lotofacil' => 'Lotofácil',
                        'quina' => 'Quina',
                    ])
                    ->default('all')
                    ->required(),
                    
                TextInput::make('limit')
                    ->label('Number of Results to Show')
                    ->numeric()
                    ->default(5)
                    ->minValue(1)
                    ->maxValue(20)
                    ->required(),
                    
                Toggle::make('show_prizes')
                    ->label('Show Prize Information')
                    ->default(true),
                    
                Toggle::make('show_dates')
                    ->label('Show Draw Dates')
                    ->default(true),
                    
                Toggle::make('link_to_details')
                    ->label('Link to Individual Draw Pages')
                    ->default(true),
            ]);
    }

    public static function mutateData(array $data): array
    {
        $query = Draw::query()
            ->with(['page'])
            ->orderBy('draw_number', 'desc');
            
        if ($data['lottery_type'] !== 'all') {
            $query->where('type', $data['lottery_type']);
        }
        
        $data['results'] = $query->limit($data['limit'] ?? 5)->get();
        
        return $data;
    }
}
