<?php

namespace App\Filament\Resources;

use App\Enums\GamesEnum;
use App\Filament\Resources\DrawResource\Pages;
use App\Models\Draw;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DrawResource extends Resource
{
    protected static ?string $model = Draw::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
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
                Tables\Columns\TextColumn::make('draw_date')
                    ->label('Data Apuração')
                    ->formatStateUsing(function ($state, $record) {
                        return \Carbon\Carbon::createFromFormat('d/m/Y', $record?->raw_data['dataApuracao'] ?? null)->format('d/m/Y');
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('draw_number')
                    ->label('Concurso')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_accumulated')
                    ->label('Acumulado')
                    ->boolean(),
                Tables\Columns\TextColumn::make('formatted_main_prize')
                    ->label('Prêmio Principal'),
                Tables\Columns\TextColumn::make('main_prize_winners')
                    ->label('Ganhadores'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo de Jogo')
                    ->options([
                        'MEGA_SENA' => 'Mega Sena',
                        'LOTOFACIL' => 'Lotofácil',
                        'QUINA' => 'Quina',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListDraws::route('/'),
            'view' => Pages\ViewDraw::route('/{record}'),
        ];
    }
}
