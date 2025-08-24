<?php

namespace App\Filament\Resources\DrawResource\Pages;

use App\Filament\Resources\DrawResource;
use Filament\Infolists\Components\{
    RepeatableEntry,
    Section,
    TextEntry,
};
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewDraw extends ViewRecord
{
    protected static string $resource = DrawResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informações do Sorteio')
                    ->schema([
                        TextEntry::make('game_name')
                            ->label('Jogo'),
                        TextEntry::make('draw_number')
                            ->label('Concurso'),
                        TextEntry::make('draw_date')
                            ->label('Data de Apuração')
                            ->date('d/m/Y'),
                        TextEntry::make('location')
                            ->label('Local do Sorteio'),
                        TextEntry::make('is_accumulated')
                            ->label('Acumulado')
                            ->formatStateUsing(fn ($state = false) => $state ? 'Sim' : 'Não')
                            ->badge()
                            ->color(fn ($state = false) => $state ? 'warning' : 'success'),
                    ])->columns(2),

                Section::make('Dezenas Sorteadas')
                    ->schema([
                        TextEntry::make('drawn_numbers')
                            ->label('Dezenas')
                            ->size('lg')
                            ->weight('bold'),
                    ]),

                Section::make('Premiação')
                    ->schema([
                        RepeatableEntry::make('raw_data.listaRateioPremio')
                            ->label('Rateio de Prêmios')
                            ->schema([
                                TextEntry::make('descricaoFaixa')
                                    ->label('Faixa'),
                                TextEntry::make('numeroDeGanhadores')
                                    ->label('Ganhadores'),
                                TextEntry::make('valorPremio')
                                    ->label('Valor do Prêmio')
                                    ->formatStateUsing(fn ($state = null) => $state ? 'R$ '.number_format($state, 2, ',', '.') : 'N/A'),
                            ])->columns(3),
                    ]),

                Section::make('Ganhadores por Localidade')
                    ->schema([
                        RepeatableEntry::make('raw_data.listaMunicipioUFGanhadores')
                            ->label('Municípios/UFs dos Ganhadores')
                            ->schema([
                                TextEntry::make('municipio')
                                    ->label('Município'),
                                TextEntry::make('uf')
                                    ->label('UF'),
                                TextEntry::make('ganhadores')
                                    ->label('Ganhadores'),
                            ])->columns(3),
                    ])
                    ->visible(fn ($record) => ! empty($record->raw_data['listaMunicipioUFGanhadores'])),

                Section::make('Próximo Concurso')
                    ->schema([
                        TextEntry::make('next_draw_number')
                            ->label('Próximo Concurso'),
                        TextEntry::make('next_draw_date')
                            ->label('Data do Próximo Sorteio'),
                        TextEntry::make('raw_data.valorEstimadoProximoConcurso')
                            ->label('Valor Estimado')
                            ->formatStateUsing(fn ($state = null) => $state ? 'R$ '.number_format($state, 2, ',', '.') : 'N/A'),
                    ])->columns(3),

                Section::make('Informações Adicionais')
                    ->schema([
                        TextEntry::make('raw_data.valorArrecadado')
                            ->label('Valor Arrecadado')
                            ->formatStateUsing(fn ($state = null) => $state ? 'R$ '.number_format($state, 2, ',', '.') : 'N/A'),
                        TextEntry::make('raw_data.valorAcumuladoProximoConcurso')
                            ->label('Valor Acumulado Próximo Concurso')
                            ->formatStateUsing(fn ($state = null) => $state ? 'R$ '.number_format($state, 2, ',', '.') : 'N/A'),
                        TextEntry::make('raw_data.valorAcumuladoConcursoEspecial')
                            ->label('Valor Acumulado Concurso Especial')
                            ->formatStateUsing(fn ($state = null) => $state ? 'R$ '.number_format($state, 2, ',', '.') : 'N/A'),
                        TextEntry::make('created_at')
                            ->label('Importado em')
                            ->dateTime('d/m/Y H:i:s'),
                    ])->columns(2),
            ]);
    }
}
