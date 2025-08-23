<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Models\Draw;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class StatisticsCardsBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('statistics-cards')
            ->label('Statistics Cards')
            ->icon('heroicon-o-chart-bar')
            ->schema([
                TextInput::make('title')
                    ->label('Section Title')
                    ->default('Estatísticas das Loterias')
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
                    
                Toggle::make('show_total_draws')
                    ->label('Show Total Draws')
                    ->default(true),
                    
                Toggle::make('show_total_winners')
                    ->label('Show Total Winners')
                    ->default(true),
                    
                Toggle::make('show_accumulated_count')
                    ->label('Show Accumulated Count')
                    ->default(true),
                    
                Toggle::make('show_biggest_prize')
                    ->label('Show Biggest Prize')
                    ->default(true),
                    
                Toggle::make('show_latest_draw')
                    ->label('Show Latest Draw Info')
                    ->default(true),
                    
                Toggle::make('show_next_estimated')
                    ->label('Show Next Estimated Prize')
                    ->default(false),
            ]);
    }

    public static function mutateData(array $data): array
    {
        $statistics = [];
        
        if ($data['lottery_type'] === 'all') {
            // Get statistics for all lotteries
            $games = ['megasena', 'lotofacil', 'quina'];
            
            foreach ($games as $game) {
                $gameStats = self::getGameStatistics($game, $data);
                $statistics[$game] = $gameStats;
            }
        } else {
            // Get statistics for specific lottery
            $statistics[$data['lottery_type']] = self::getGameStatistics($data['lottery_type'], $data);
        }
        
        $data['statistics'] = $statistics;
        
        return $data;
    }
    
    private static function getGameStatistics(string $game, array $config): array
    {
        $query = Draw::where('game', $game);
        
        $stats = [
            'game_name' => ucfirst(str_replace(['_', 'mega', 'sena'], [' ', 'Mega', 'Sena'], $game)),
        ];
        
        if ($config['show_total_draws'] ?? false) {
            $stats['total_draws'] = $query->count();
        }
        
        if ($config['show_total_winners'] ?? false) {
            $stats['total_winners'] = $query->sum('winners_count') ?: 0;
        }
        
        if ($config['show_accumulated_count'] ?? false) {
            $stats['accumulated_count'] = $query->where('accumulated', true)->count();
        }
        
        if ($config['show_biggest_prize'] ?? false) {
            $stats['biggest_prize'] = $query->max('estimated_prize') ?: 0;
        }
        
        if ($config['show_latest_draw'] ?? false) {
            $latestDraw = $query->orderBy('draw_date', 'desc')->first();
            $stats['latest_draw'] = $latestDraw;
        }
        
        if ($config['show_next_estimated'] ?? false) {
            // This would typically come from an external API or be calculated
            // For now, we'll use a placeholder
            $stats['next_estimated'] = 50000000; // R$ 50 million placeholder
        }
        
        return $stats;
    }
}
