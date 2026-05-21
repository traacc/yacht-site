<?php

namespace App\Filament\Resources\RaceResults;

use App\Filament\Resources\RaceResults\Pages\ManageRaceResults;
use App\Models\RaceResult;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RaceResultResource extends Resource
{
    protected static ?string $model = RaceResult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getModelLabel(): string
    {
        return 'Результат гонки'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результаты гонки'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event_id')
                    ->label('Событие')
                    ->required(),
                TextInput::make('regatta_entry_id')
                    ->label('Заявка')
                    ->required(),
                TextInput::make('position')
                    ->label('Позиция')
                    ->placeholder('Занятое место')
                    ->numeric(),
                TextInput::make('points')
                    ->label('Очки')
                    ->placeholder('Количество очков')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('penalty_code')
                    ->label('Код штрафа')
                    ->placeholder('Например: DNF, DSQ'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('event_id')
                    ->label('Событие')
                    ->searchable(),
                TextColumn::make('regatta_entry_id')
                    ->label('Заявка')
                    ->searchable(),
                TextColumn::make('position')
                    ->label('Позиция')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('points')
                    ->label('Очки')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('penalty_code')
                    ->label('Штраф')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
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
            'index' => ManageRaceResults::route('/'),
        ];
    }
}
