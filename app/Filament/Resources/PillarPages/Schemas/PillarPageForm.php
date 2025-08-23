<?php

namespace App\Filament\Resources\PillarPages\Schemas;

use App\Enums\GamesEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;

class PillarPageForm
{
    public static function configure(?array $schema = null): array
    {
        return [
                Select::make('game')
                    ->label('Game')
                    ->options([
                        GamesEnum::MEGA_SENA->value => 'Mega Sena',
                        GamesEnum::LOTOFACIL->value => 'Lotofácil',
                        GamesEnum::QUINA->value => 'Quina',
                    ])
                    ->required(),

                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255),

                Select::make('layout')
                    ->label('Layout')
                    ->options([
                        'pillar-page' => 'Pillar Page Layout',
                    ])
                    ->default('pillar-page')
                    ->required(),

                PageBuilder::make('content')
                    ->label('Content')
                    ->required(),
            ];
    }
}
