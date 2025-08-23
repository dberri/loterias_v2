<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class HeroSectionBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('hero-section')
            ->label('Hero Section')
            ->icon('heroicon-o-star')
            ->schema([
                TextInput::make('title')
                    ->label('Main Title')
                    ->required()
                    ->columnSpanFull(),
                    
                TextInput::make('subtitle')
                    ->label('Subtitle')
                    ->columnSpanFull(),
                    
                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),
                    
                TextInput::make('primary_cta_text')
                    ->label('Primary CTA Text')
                    ->default('Ver Resultados'),
                    
                TextInput::make('primary_cta_url')
                    ->label('Primary CTA URL')
                    ->url(),
                    
                TextInput::make('secondary_cta_text')
                    ->label('Secondary CTA Text (Optional)'),
                    
                TextInput::make('secondary_cta_url')
                    ->label('Secondary CTA URL')
                    ->url(),
                    
                Select::make('background_style')
                    ->label('Background Style')
                    ->options([
                        'gradient-blue' => 'Blue Gradient',
                        'gradient-green' => 'Green Gradient',
                        'gradient-purple' => 'Purple Gradient',
                        'solid-blue' => 'Solid Blue',
                        'solid-green' => 'Solid Green',
                        'image' => 'Background Image',
                    ])
                    ->default('gradient-blue')
                    ->required(),
                    
                FileUpload::make('background_image')
                    ->label('Background Image')
                    ->image()
                    ->directory('hero-backgrounds')
                    ->visibility('public')
                    ->hidden(fn (callable $get) => $get('background_style') !== 'image'),
                    
                Select::make('text_alignment')
                    ->label('Text Alignment')
                    ->options([
                        'left' => 'Left',
                        'center' => 'Center',
                        'right' => 'Right',
                    ])
                    ->default('center'),
                    
                Toggle::make('show_lottery_highlights')
                    ->label('Show Latest Lottery Highlights')
                    ->default(false),
                    
                Select::make('height')
                    ->label('Section Height')
                    ->options([
                        'small' => 'Small (300px)',
                        'medium' => 'Medium (400px)',
                        'large' => 'Large (500px)',
                        'full' => 'Full Screen',
                    ])
                    ->default('medium'),
            ]);
    }

    public static function mutateData(array $data): array
    {
        if ($data['show_lottery_highlights'] ?? false) {
            // Get latest results for highlights
            $data['latest_results'] = \App\Models\Draw::with(['drawPage'])
                ->orderBy('draw_date', 'desc')
                ->limit(3)
                ->get()
                ->groupBy('game')
                ->map(fn($draws) => $draws->first());
        }
        
        return $data;
    }
}
