<?php

namespace App\Filament\Fabricator\PageBlocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Z3d0X\FilamentFabricator\PageBlocks\PageBlock;

class RichTextContentBlock extends PageBlock
{
    public static function getBlockSchema(): Block
    {
        return Block::make('rich-text-content')
            ->label('Rich Text Content')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Content Settings')
                    ->schema([
                        TextInput::make('title')
                            ->label('Section Title (Optional)')
                            ->placeholder('Leave empty for no title'),
                            
                        Select::make('title_level')
                            ->label('Title Level')
                            ->options([
                                'h1' => 'H1 (Main Heading)',
                                'h2' => 'H2 (Section Heading)',
                                'h3' => 'H3 (Subsection)',
                                'h4' => 'H4 (Minor Heading)',
                            ])
                            ->default('h2')
                            ->visible(fn ($get) => !empty($get('title'))),
                            
                        RichEditor::make('content')
                            ->label('Content')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('uploads/content'),
                    ])
                    ->columns(2),
                    
                Section::make('Layout & Styling')
                    ->schema([
                        Select::make('container_style')
                            ->label('Container Style')
                            ->options([
                                'default' => 'Default (White background, shadow)',
                                'transparent' => 'Transparent (No background)',
                                'colored' => 'Colored Background',
                                'bordered' => 'Simple Border',
                            ])
                            ->default('default')
                            ->required(),
                            
                        Select::make('background_color')
                            ->label('Background Color')
                            ->options([
                                'blue' => 'Blue',
                                'green' => 'Green',
                                'yellow' => 'Yellow',
                                'red' => 'Red',
                                'purple' => 'Purple',
                                'gray' => 'Gray',
                            ])
                            ->visible(fn ($get) => $get('container_style') === 'colored'),
                            
                        Select::make('text_alignment')
                            ->label('Text Alignment')
                            ->options([
                                'left' => 'Left',
                                'center' => 'Center',
                                'right' => 'Right',
                                'justify' => 'Justify',
                            ])
                            ->default('left'),
                            
                        Select::make('content_width')
                            ->label('Content Width')
                            ->options([
                                'full' => 'Full Width',
                                'container' => 'Container (Max Width)',
                                'narrow' => 'Narrow (Prose Width)',
                            ])
                            ->default('container'),
                            
                        Grid::make(2)
                            ->schema([
                                Toggle::make('add_padding')
                                    ->label('Add Padding')
                                    ->default(true),
                                    
                                Toggle::make('add_margin')
                                    ->label('Add Bottom Margin')
                                    ->default(true),
                            ]),
                    ])
                    ->columns(2),
                    
                Section::make('SEO & Accessibility')
                    ->schema([
                        TextInput::make('anchor_id')
                            ->label('Anchor ID (Optional)')
                            ->placeholder('section-id')
                            ->helperText('Creates a #section-id anchor for navigation')
                            ->regex('/^[a-z0-9-]+$/'),
                            
                        Select::make('schema_type')
                            ->label('Schema.org Type (Optional)')
                            ->options([
                                '' => 'None',
                                'article' => 'Article',
                                'faq' => 'FAQ Section',
                                'how-to' => 'How-to Guide',
                                'news' => 'News Article',
                                'review' => 'Review',
                            ])
                            ->helperText('Adds structured data markup'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function mutateData(array $data): array
    {
        // Generate CSS classes based on settings
        $containerClasses = ['mb-8']; // Base margin
        
        if (!($data['add_margin'] ?? true)) {
            $containerClasses = ['mb-0'];
        }
        
        switch ($data['container_style'] ?? 'default') {
            case 'default':
                $containerClasses[] = 'bg-white rounded-lg shadow-lg';
                break;
            case 'colored':
                $color = $data['background_color'] ?? 'blue';
                $containerClasses[] = "bg-{$color}-50 border border-{$color}-200 rounded-lg";
                break;
            case 'bordered':
                $containerClasses[] = 'border border-gray-200 rounded-lg';
                break;
            case 'transparent':
                // No additional classes for transparent
                break;
        }
        
        if ($data['add_padding'] ?? true) {
            $containerClasses[] = 'p-6';
        }
        
        // Content wrapper classes
        $contentClasses = [];
        
        switch ($data['content_width'] ?? 'container') {
            case 'full':
                $contentClasses[] = 'w-full';
                break;
            case 'narrow':
                $contentClasses[] = 'max-w-prose mx-auto';
                break;
            case 'container':
            default:
                $contentClasses[] = 'max-w-4xl mx-auto';
                break;
        }
        
        switch ($data['text_alignment'] ?? 'left') {
            case 'center':
                $contentClasses[] = 'text-center';
                break;
            case 'right':
                $contentClasses[] = 'text-right';
                break;
            case 'justify':
                $contentClasses[] = 'text-justify';
                break;
            default:
                $contentClasses[] = 'text-left';
                break;
        }
        
        $data['container_classes'] = implode(' ', $containerClasses);
        $data['content_classes'] = implode(' ', $contentClasses);
        
        return $data;
    }
}
