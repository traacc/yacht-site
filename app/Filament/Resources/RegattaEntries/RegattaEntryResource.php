<?php

namespace App\Filament\Resources\RegattaEntries;

use App\Filament\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\RegattaEntry;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegattaEntryResource extends Resource
{
    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getModelLabel(): string
    {
        return 'Заявка на регату'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на регату'; // Название во множественном числе
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
                    ->relationship('yacht', 'name'),
                Select::make('status')
                    ->label('Статус')
                    ->options([
            'pending' => 'На рассмотрении',
            'approved' => 'Одобрена',
            'rejected' => 'Отклонена',
            'withdrawn' => 'Отозвана',
        ])
                    ->default('pending')
                    ->required(),
                DateTimePicker::make('submitted_at')
                    ->label('Дата подачи'),
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
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('submitted_at')
                    ->label('Подана')
                    ->dateTime()
                    ->sortable(),
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
            'index' => ManageRegattaEntries::route('/'),
        ];
    }
}
