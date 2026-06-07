<?php

namespace App\Filament\Resources\Helps;

use App\Filament\Resources\Helps\Pages\ManageHelpCategories;
use App\Models\HelpCategory;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HelpCategoryResource extends Resource
{
    protected static ?string $model = HelpCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static bool $shouldRegisterNavigation = false;

    public static function getModelLabel(): string
    {
        return 'Категория помощи';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Категории помощи';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Название')
                    ->placeholder('Введите название')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set) =>
                        $set('slug', $state ? Str::slug($state) : '')
                    ),

                TextInput::make('slug')
                    ->label('Slug')
                    ->placeholder('avtomaticheski-zapolnyaetsya')
                    ->required()
                    ->unique('help_category', 'slug', ignoreRecord: true),

                Textarea::make('description')
                    ->label('Описание')
                    ->placeholder('Краткое описание категории')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),

                TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('helps_count')
                    ->label('Записей')
                    ->counts('helps')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->emptyStateHeading('Категорий пока нет')
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать категорию'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHelpCategories::route('/'),
        ];
    }
}
