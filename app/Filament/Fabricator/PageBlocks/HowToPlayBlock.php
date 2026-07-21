<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class HowToPlayBlock extends PageBlock
{
    protected static string $name = 'how-to-play';

    public static function defineBlock(Block $block): Block
    {
        return $block
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
