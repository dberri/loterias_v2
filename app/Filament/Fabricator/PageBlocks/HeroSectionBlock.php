<?php

namespace App\Filament\Fabricator\PageBlocks;

use App\Models\Draw;
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
        $data = array_merge([
            'title' => null,
            'subtitle' => null,
            'description' => null,
            'primary_cta_text' => 'Ver Resultados',
            'primary_cta_url' => null,
            'secondary_cta_text' => null,
            'secondary_cta_url' => null,
            'background_style' => 'gradient-blue',
            'background_image' => null,
            'text_alignment' => 'center',
            'show_lottery_highlights' => false,
            'height' => 'medium',
            'latest_results' => [],
        ], $data);

        $drawId = $data['draw_id'] ?? null;

        if ($drawId) {
            $draw = Draw::with(['page'])->find($drawId);

            if ($draw) {
                $data['draw'] = $draw;
                $data['page'] = $draw->page;
                $data['game_name'] = $draw->game_name;
                $data['draw_number'] = $draw->draw_number;
                $data['draw_date'] = $draw->draw_date?->toDateString();
                $data['drawn_numbers'] = $draw->drawn_numbers;
                $data['formatted_main_prize'] = $draw->formatted_main_prize;
                $data['main_prize'] = $draw->main_prize;
                $data['main_prize_winners'] = $draw->main_prize_winners;
                $data['is_accumulated'] = $draw->is_accumulated;
                $data['location'] = $draw->location;
            }
        }

        if ($data['show_lottery_highlights'] ?? false) {
            $data['latest_results'] = Draw::with(['page'])
                ->orderBy('draw_date', 'desc')
                ->limit(3)
                ->get()
                ->groupBy(fn ($draw) => $draw->type->value)
                ->map(fn ($draws) => $draws->first());
        }

        return $data;
    }
}
