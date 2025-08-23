<?php

namespace App\Filament\Resources\PillarPages\Pages;

use App\Filament\Resources\PillarPages\PillarPageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPillarPage extends EditRecord
{
    protected static string $resource = PillarPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
