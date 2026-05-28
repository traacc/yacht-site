<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\YachtDocumentTypes\Pages\ManageDocumentTypes;
use App\Models\YachtDocumentType;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class YachtDocumentTypeResource extends Resource
{
    protected static ?string $model = YachtDocumentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Типы документов';

    protected static ?string $title = 'Типы документов';

    protected static ?int $navigationSort = 51;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    public static function getModelLabel(): string
    {
        return 'Тип документов';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Типы документов';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([


                TextInput::make('label')
                    ->label('Название')
                    ->placeholder('ORC-сертификат')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, Set $set) => $set('key', Str::slug($state, '_'))),

                Textarea::make('description')
                    ->label('Описание')
                    ->rows(2)
                    ->maxLength(500),

                Toggle::make('is_configurable')
                    ->label('Обязателен')
                    ->default(true),

                TextInput::make('sort_order')
                    ->label('Порядок')
                    ->numeric()
                    ->default(0),
                TextInput::make('key')
                    ->label('Ключ')
                    ->placeholder('orc_certificate')
                    ->required()
                    ->unique(table: 'yacht_document_types', column: 'key', ignoreRecord: true)
                    ->helperText('Уникальный строковый идентификатор. Только латиница, цифры и подчёркивание.')
                    ->regex('/^[a-z][a-z0-9_]+$/')
                    ->maxLength(100)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Описание')
                    ->limit(40)
                    ->toggleable(),

                IconColumn::make('is_configurable')
                    ->label('Настраиваемый')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->emptyStateHeading('Типов документов пока нет');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentTypes::route('/'),
        ];
    }
}