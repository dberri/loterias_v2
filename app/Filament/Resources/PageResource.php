<?php

namespace App\Filament\Resources;

use App\Enums\PageStatus;
use App\Filament\Resources\PageResource\Pages;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Z3d0X\FilamentFabricator\Facades\FilamentFabricator;
use Z3d0X\FilamentFabricator\Forms\Components\PageBuilder;
use Z3d0X\FilamentFabricator\Models\Contracts\Page as PageContract;
use Z3d0X\FilamentFabricator\View\ResourceSchemaSlot;

class PageResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModel(): string
    {
        return FilamentFabricator::getPageModel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(3)
            ->schema([
                Group::make()
                    ->schema([
                        Group::make()->schema(FilamentFabricator::getSchemaSlot(ResourceSchemaSlot::BLOCKS_BEFORE)),

                        PageBuilder::make('blocks')
                            ->label('Blocks'),

                        Group::make()->schema(FilamentFabricator::getSchemaSlot(ResourceSchemaSlot::BLOCKS_AFTER)),
                    ])
                    ->columnSpan(2),

                Group::make()
                    ->columnSpan(1)
                    ->schema([
                        Group::make()->schema(FilamentFabricator::getSchemaSlot(ResourceSchemaSlot::SIDEBAR_BEFORE)),

                        Section::make('Page Settings')
                            ->schema([
                                Placeholder::make('page_url')
                                    ->label('URL')
                                    ->visible(fn (?PageContract $record) => config('filament-fabricator.routing.enabled') && filled($record))
                                    ->content(fn (?PageContract $record) => FilamentFabricator::getPageUrlFromId($record?->id)),

                                TextInput::make('title')
                                    ->label('Title')
                                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?PageContract $record): void {
                                        if (! $get('is_slug_changed_manually') && filled($state) && blank($record)) {
                                            $set('slug', Str::slug($state, language: config('app.locale', 'en')));
                                        }
                                    })
                                    ->debounce('500ms')
                                    ->required(),

                                Hidden::make('is_slug_changed_manually')
                                    ->default(false)
                                    ->dehydrated(false),

                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('parent_id', $get('parent_id')))
                                    ->afterStateUpdated(fn (Set $set) => $set('is_slug_changed_manually', true))
                                    ->rule(function ($state) {
                                        return function (string $attribute, $value, \Closure $fail) use ($state) {
                                            if ($state !== '/' && (Str::startsWith($value, '/') || Str::endsWith($value, '/'))) {
                                                $fail('The slug cannot start or end with a slash.');
                                            }
                                        };
                                    })
                                    ->required(),

                                Select::make('layout')
                                    ->label('Layout')
                                    ->options(FilamentFabricator::getLayouts())
                                    ->default(fn () => FilamentFabricator::getDefaultLayoutName())
                                    ->live()
                                    ->required(),

                                Select::make('parent_id')
                                    ->label('Parent')
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->relationship(
                                        'parent',
                                        'title',
                                        function (Builder $query, ?PageContract $record): void {
                                            if (filled($record)) {
                                                $query->where('id', '!=', $record->id);
                                            }
                                        },
                                    ),
                            ]),

                        Section::make('Generation')
                            ->schema([
                                Placeholder::make('status')
                                    ->label('Status')
                                    ->content(fn (?PageContract $record) => $record?->status?->value ?? '-'),
                                Placeholder::make('batch_id')
                                    ->label('Batch ID')
                                    ->content(fn (?PageContract $record) => $record?->batch_id ?: '-'),
                                Placeholder::make('provider')
                                    ->label('Provider')
                                    ->content(fn (?PageContract $record) => $record?->provider ?: '-'),
                                Placeholder::make('generated_at')
                                    ->label('Generated At')
                                    ->content(fn (?PageContract $record) => $record?->generated_at?->format('d/m/Y H:i') ?? '-'),
                            ]),

                        Group::make()->schema(FilamentFabricator::getSchemaSlot(ResourceSchemaSlot::SIDEBAR_AFTER)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PageStatus $state): string => $state->value)
                    ->color(fn (PageStatus $state): string => match ($state) {
                        PageStatus::Generating => 'gray',
                        PageStatus::Generated => 'warning',
                        PageStatus::Published => 'success',
                        PageStatus::Failed => 'danger',
                    }),
                TextColumn::make('batch_id')
                    ->label('Batch ID')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('provider')
                    ->label('Provider')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('generated_at')
                    ->label('Generated At')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
                TextColumn::make('layout')
                    ->label('Layout')
                    ->badge()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('parent.title')
                    ->label('Parent')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn ($state) => $state ?? '-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        PageStatus::Generating->value => 'Generating',
                        PageStatus::Generated->value => 'Generated',
                        PageStatus::Published->value => 'Published',
                        PageStatus::Failed->value => 'Failed',
                    ]),
                SelectFilter::make('layout')
                    ->label('Layout')
                    ->options(FilamentFabricator::getLayouts()),
            ])
            ->actions([
                ViewAction::make()
                    ->visible(config('filament-fabricator.enable-view-page')),
                EditAction::make(),
                Action::make('visit')
                    ->label('Visit')
                    ->url(fn (?PageContract $record) => FilamentFabricator::getPageUrlFromId($record->id, true) ?: null)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->openUrlInNewTab()
                    ->color('success')
                    ->visible(config('filament-fabricator.routing.enabled')),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Z3d0X\FilamentFabricator\Resources\PageResource\Pages\ListPages::route('/'),
            'create' => \Z3d0X\FilamentFabricator\Resources\PageResource\Pages\CreatePage::route('/create'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
        ];
    }
}
