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
                    ->required(),
                TextInput::make('description'),
                Select::make('regatta_id')
                    ->relationship('regatta', 'name')
                    ->required(),
                TextInput::make('event_number')
                    ->required()
                    ->numeric(),
                DateTimePicker::make('event_datetime'),
                Select::make('event_type')
                    ->options(['schedule' => 'Schedule', 'race' => 'Race'])
                    ->default('schedule')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('regatta.name')
                    ->searchable(),
                TextColumn::make('event_number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('event_datetime')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('event_type')
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
