<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class ComparisonTableBlock extends PageBlock
{
    protected static string $name = 'comparison-table';

    public static function defineBlock(Block $block): Block
    {
        return $block
            ->schema([
                //
            ]);
    }

    public static function mutateData(array $data): array
    {
        return $data;
    }
}
