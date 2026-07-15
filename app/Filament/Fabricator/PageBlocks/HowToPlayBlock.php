<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class HowToPlayBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('how-to-play')
            ->schema([
                TextInput::make('title')
                    ->label('Section Title')
                    ->default('Como jogar')
                    ->required(),
                RichEditor::make('content')
                    ->label('Content')
                    ->required(),
            ]);
    }

    public static function mutateData(array $data): array
    {
        $data['title'] = $data['title'] ?? 'Como jogar';
        $data['content'] = $data['content'] ?? ($data['html'] ?? null);

        return $data;
    }
}
