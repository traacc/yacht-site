<?php

namespace App\Filament\User\Resources\RegattaResults;

use App\Filament\User\Resources\RegattaResults\Pages\ManageRegattaResults;
use App\Models\RegattaResultItem;
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
    protected static ?string $model = RegattaResultItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'results';

    public static function getModelLabel(): string
    {
        return 'Результаты'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результаты'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_result_id')
                    ->relationship('regattaResult', 'id')
                    ->required(),
                Select::make('team_id')
                    ->relationship('team', 'name')
                    ->required(),
                Select::make('yacht_id')
                    ->relationship('yacht', 'name')
                    ->nullable(),
                TextInput::make('total_points')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('final_position')
                    ->numeric(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('regattaResult.regatta.name')
                    ->label('Регата'),
                TextEntry::make('team.name')
                    ->label('Команда'),
                TextEntry::make('yacht.name')
                    ->label('Яхта'),
                TextEntry::make('total_points')
                    ->numeric()->label('Очки'),
                TextEntry::make('final_position')
                    ->numeric()
                    ->placeholder('-')->label('Место'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('regattaResult.regatta.name')
                    ->searchable()->label('Регата'),
                TextColumn::make('team.name')
                    ->searchable()->label('Команда'),
                TextColumn::make('yacht.name')
                    ->searchable()->label('Яхта'),
                TextColumn::make('total_points')
                    ->numeric()
                    ->sortable()->label('Очки'),
                TextColumn::make('final_position')
                    ->numeric()
                    ->sortable()->label('Место'),
            ])->stackedOnMobile()
            ->filters([
                //
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                ViewAction::make()->hiddenLabel(),
                //EditAction::make(),
                //DeleteAction::make(),
            ])
            ->toolbarActions([
                /*BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),*/
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaResults::route('/'),
        ];
    }
}
