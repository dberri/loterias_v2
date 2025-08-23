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
                    ->getSearchResultsUsing(fn (string $search): array => 
                        Draw::where('draw_number', 'like', "%{$search}%")
                            ->orWhere('game', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn($draw) => [
                                $draw->id => "{$draw->game} - Concurso {$draw->draw_number} ({$draw->draw_date->format('d/m/Y')})"
                            ])
                            ->toArray()
                    )
                    ->getOptionLabelUsing(fn ($value): ?string => 
                        Draw::find($value)?->let(fn($draw) => 
                            "{$draw->game} - Concurso {$draw->draw_number} ({$draw->draw_date->format('d/m/Y')})"
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
        if (!empty($data['draw_id'])) {
            $draw = Draw::with(['drawPage'])->find($data['draw_id']);
            $data['draw'] = $draw;
            
            if ($draw && ($data['show_comparison'] ?? false)) {
                $previousDraw = Draw::where('game', $draw->game)
                    ->where('draw_date', '<', $draw->draw_date)
                    ->orderBy('draw_date', 'desc')
                    ->first();
                $data['previous_draw'] = $previousDraw;
            }
            
            if ($draw && ($data['show_statistics'] ?? false)) {
                // Get number frequency statistics for this game
                $allDraws = Draw::where('game', $draw->game)
                    ->whereNotNull('numbers')
                    ->get();
                    
                $numberStats = [];
                foreach ($allDraws as $historicalDraw) {
                    $numbers = json_decode($historicalDraw->numbers, true);
                    if (is_array($numbers)) {
                        foreach ($numbers as $number) {
                            $numberStats[$number] = ($numberStats[$number] ?? 0) + 1;
                        }
                    }
                }
                
                arsort($numberStats);
                $data['number_frequency'] = $numberStats;
            }
        }
        
        return $data;
    }
}
