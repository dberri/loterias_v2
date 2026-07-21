<?php

namespace App\Filament\Resources;

use App\Enums\GamesEnum;
use App\Filament\Resources\DrawResource\Pages\ListDraws;
use App\Filament\Resources\DrawResource\Pages\ViewDraw;
use App\Models\Draw;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DrawResource extends Resource
{
    protected static ?string $model = Draw::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Jogo')
                    ->formatStateUsing(fn (GamesEnum $state): string => match ($state) {
                        GamesEnum::MEGA_SENA => 'Mega Sena',
                        GamesEnum::LOTOFACIL => 'Lotofácil',
                        GamesEnum::QUINA => 'Quina',
                        default => $state->value,
                    })
                    ->badge()
                    ->color(fn (GamesEnum $state): string => match ($state) {
                        GamesEnum::MEGA_SENA => 'success',
                        GamesEnum::LOTOFACIL => 'info',
                        GamesEnum::QUINA => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('draw_date')
                    ->label('Data Apuração')
                    ->formatStateUsing(function ($state, $record) {
                        return Carbon::createFromFormat('d/m/Y', $record?->raw_data['dataApuracao'] ?? null)->format('d/m/Y');
                    })
                    ->sortable(),
                TextColumn::make('draw_number')
                    ->label('Concurso')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_accumulated')
                    ->label('Acumulado')
                    ->boolean(),
                TextColumn::make('formatted_main_prize')
                    ->label('Prêmio Principal'),
                TextColumn::make('main_prize_winners')
                    ->label('Ganhadores'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de Jogo')
                    ->options([
                        'MEGA_SENA' => 'Mega Sena',
                        'LOTOFACIL' => 'Lotofácil',
                        'QUINA' => 'Quina',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('draw_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDraws::route('/'),
            'view' => ViewDraw::route('/{record}'),
        ];
    }
}
