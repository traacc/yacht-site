<?php

namespace App\Filament\Resources\RegattaEvents;

use App\Filament\Resources\RegattaEvents\Pages\ManageRegattaEvents;
use App\Models\RegattaEvents;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegattaEventsResource extends Resource
{
    protected static ?string $model = RegattaEvents::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getModelLabel(): string
    {
        return 'Событие регаты'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'События регаты'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название события')
                    ->required(),
                TextInput::make('description')
                    ->label('Описание')
                    ->placeholder('Введите описание события'),
                Select::make('regatta_id')
                    ->label('Регата')
                    ->relationship('regatta', 'name')
                    ->required(),
                TextInput::make('event_number')
                    ->label('Номер события')
                    ->placeholder('Порядковый номер')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('event_datetime')
                    ->label('Дата и время'),
                Select::make('event_type')
                    ->label('Тип события')
                    ->options(['schedule' => 'Расписание', 'race' => 'Гонка'])
                    ->default('schedule')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Описание')
                    ->searchable(),
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable(),
                TextColumn::make('event_number')
                    ->label('Номер')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('event_datetime')
                    ->label('Дата/время')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Тип')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaEvents::route('/'),
        ];
    }
}
