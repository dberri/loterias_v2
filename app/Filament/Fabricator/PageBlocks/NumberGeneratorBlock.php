<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class NumberGeneratorBlock extends PageBlock
{
    protected static string $name = 'number-generator';

    public static function defineBlock(Block $block): Block
    {
        return $block
            ->label('Number Generator')
            ->icon('heroicon-o-sparkles')
            ->schema([
                TextInput::make('title')
                    ->label('Block Title')
                    ->default('Gerador de Números')
                    ->required(),

                TextInput::make('description')
                    ->label('Description')
                    ->default('Gere números aleatórios para suas apostas nas loterias')
                    ->columnSpanFull(),

                Select::make('default_lottery')
                    ->label('Default Lottery')
                    ->options([
                        'megasena' => 'Mega Sena (6 números de 1 a 60)',
                        'lotofacil' => 'Lotofácil (15 números de 1 a 25)',
                        'quina' => 'Quina (5 números de 1 a 80)',
                    ])
                    ->default('megasena')
                    ->required(),

                Toggle::make('allow_lottery_selection')
                    ->label('Allow Users to Select Lottery Type')
                    ->default(true),

                Toggle::make('show_statistics')
                    ->label('Show Number Statistics')
                    ->default(true),

                Toggle::make('save_generated_numbers')
                    ->label('Allow Users to Save Generated Numbers')
                    ->default(false),

                TextInput::make('primary_color')
                    ->label('Primary Color (Hex)')
                    ->default('#3B82F6')
                    ->regex('/^#[0-9A-Fa-f]{6}$/'),
            ]);
    }

    public static function mutateData(array $data): array
    {
        // Define lottery configurations
        $lotteryConfigs = [
            'megasena' => [
                'name' => 'Mega Sena',
                'numbers_to_pick' => 6,
                'min_number' => 1,
                'max_number' => 60,
                'description' => 'Escolha 6 números de 1 a 60',
            ],
            'lotofacil' => [
                'name' => 'Lotofácil',
                'numbers_to_pick' => 15,
                'min_number' => 1,
                'max_number' => 25,
                'description' => 'Escolha 15 números de 1 a 25',
            ],
            'quina' => [
                'name' => 'Quina',
                'numbers_to_pick' => 5,
                'min_number' => 1,
                'max_number' => 80,
                'description' => 'Escolha 5 números de 1 a 80',
            ],
        ];

        $data['lottery_configs'] = $lotteryConfigs;
        $data['default_config'] = $lotteryConfigs[$data['default_lottery']];

        return $data;
    }
}
