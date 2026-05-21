<?php

namespace App\Filament\Resources\RegattaResults;

use App\Filament\Resources\RegattaResults\Pages\ManageRegattaResults;
use App\Models\RegattaResult;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegattaResultResource extends Resource
{
    protected static ?string $model = RegattaResult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getModelLabel(): string
    {
        return 'Результат'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результаты'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_id')
                    ->label('Регата')
                    ->relationship('regatta', 'name')
                    ->required(),
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->required(),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->relationship('yacht', 'name')
                    ->required(),
                TextInput::make('total_points')
                    ->label('Общее количество очков')
                    ->placeholder('Сумма очков за все гонки')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('final_position')
                    ->label('Итоговое место')
                    ->placeholder('Занятое место в регате')
                    ->numeric(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('regatta.name')
                    ->label('Регата'),
                TextEntry::make('team.name')
                    ->label('Команда'),
                TextEntry::make('yacht.name')
                    ->label('Яхта'),
                TextEntry::make('total_points')
                    ->numeric(),
                TextEntry::make('final_position')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable(),
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->searchable(),
                TextColumn::make('yacht.name')
                    ->label('Яхта')
                    ->searchable(),
                TextColumn::make('total_points')
                    ->label('Очки')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('final_position')
                    ->label('Место')
                    ->numeric()
                    ->sortable(),
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
                ViewAction::make(),
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
            'index' => ManageRegattaResults::route('/'),
        ];
    }
}
