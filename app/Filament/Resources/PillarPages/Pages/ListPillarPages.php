<?php

namespace App\Filament\Resources\PillarPages\Pages;

use App\Filament\Resources\PillarPages\PillarPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPillarPages extends ListRecords
{
    protected static string $resource = PillarPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
