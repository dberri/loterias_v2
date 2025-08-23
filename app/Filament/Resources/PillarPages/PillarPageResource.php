<?php

namespace App\Filament\Resources\PillarPages;

use App\Filament\Resources\PillarPages\Pages\CreatePillarPage;
use App\Filament\Resources\PillarPages\Pages\EditPillarPage;
use App\Filament\Resources\PillarPages\Pages\ListPillarPages;
use App\Filament\Resources\PillarPages\Schemas\PillarPageForm;
use App\Filament\Resources\PillarPages\Tables\PillarPagesTable;
use App\Models\PillarPage;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Tables\Table;

class PillarPageResource extends Resource
{
    protected static ?string $model = PillarPage::class;

    protected static ?string $navigationLabel = 'Pillar Pages';

    protected static ?string $modelLabel = 'Pillar Page';

    protected static ?string $pluralModelLabel = 'Pillar Pages';

    protected static ?string $navigationIcon = 'heroicon-o-document';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema(PillarPageForm::configure());
    }

    public static function table(Table $table): Table
    {
        return PillarPagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPillarPages::route('/'),
            'create' => CreatePillarPage::route('/create'),
            'edit' => EditPillarPage::route('/{record}/edit'),
        ];
    }
}
