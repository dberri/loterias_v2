<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class FaqBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('faq')
            ->label('FAQ Block')
            ->icon('heroicon-o-question-mark-circle')
            ->schema([
                TextInput::make('title')
                    ->label('Section Title')
                    ->default('Perguntas Frequentes')
                    ->required(),
                    
                Select::make('layout_style')
                    ->label('Layout Style')
                    ->options([
                        'accordion' => 'Accordion (Expandable)',
                        'grid' => 'Grid Layout',
                        'list' => 'Simple List',
                    ])
                    ->default('accordion')
                    ->required(),
                    
                Select::make('category')
                    ->label('FAQ Category')
                    ->options([
                        'general' => 'Geral',
                        'megasena' => 'Mega Sena',
                        'lotofacil' => 'Lotofácil',
                        'quina' => 'Quina',
                        'prizes' => 'Prêmios',
                        'how_to_play' => 'Como Jogar',
                        'technical' => 'Técnico',
                    ])
                    ->default('general'),
                    
                Repeater::make('faqs')
                    ->label('FAQ Items')
                    ->schema([
                        TextInput::make('question')
                            ->label('Question')
                            ->required()
                            ->columnSpanFull(),
                            
                        RichEditor::make('answer')
                            ->label('Answer')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'link',
                                'bulletList',
                                'orderedList',
                            ]),
                            
                        Select::make('category')
                            ->label('Question Category')
                            ->options([
                                'general' => 'Geral',
                                'megasena' => 'Mega Sena',
                                'lotofacil' => 'Lotofácil',
                                'quina' => 'Quina',
                                'prizes' => 'Prêmios',
                                'how_to_play' => 'Como Jogar',
                                'technical' => 'Técnico',
                            ])
                            ->default('general'),
                    ])
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                    ->minItems(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function mutateData(array $data): array
    {
        $data['title'] = $data['title'] ?? 'Perguntas Frequentes';
        $data['layout_style'] = $data['layout_style'] ?? 'accordion';
        $data['category'] = $data['category'] ?? 'general';
        $data['faqs'] = $data['faqs'] ?? [];

        // Filter FAQs by category if specified
        if (!empty($data['category']) && $data['category'] !== 'general') {
            $data['faqs'] = collect($data['faqs'] ?? [])
                ->filter(fn ($faq) => ($faq['category'] ?? 'general') === $data['category'])
                ->values()
                ->toArray();
        }

        return $data;
    }
}
