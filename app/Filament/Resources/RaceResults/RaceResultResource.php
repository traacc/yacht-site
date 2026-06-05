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

    protected static ?int $navigationSort = 10;

    public static function getModelLabel(): string
    {
        return 'Результат гонки'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результат гонок'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('regatta_result_items_id')
                    ->required(),
                TextInput::make('position')
                    ->numeric(),
                TextInput::make('points')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                //TextInput::make('penalty_code'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('regatta_result_items_id')
                    ->searchable(),
                TextColumn::make('regatta_entry_id')
                    ->searchable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('points')
                    ->numeric()
                    ->sortable(),
                /*
                TextColumn::make('penalty_code')
                    ->searchable(),
                */
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
            'index' => ManageRaceResults::route('/'),
        ];
    }
}
