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

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;

class RegattaEntryResource extends Resource
{
    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'results';


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
                    ->required()->columnSpanFull(),
                Select::make('team_id')
                    ->label('Команда')
                    ->relationship('team', 'name')
                    ->required()->columnSpanFull(),
                Select::make('yacht_id')
                    ->label('Яхта')
                    ->relationship('yacht', 'name')->columnSpanFull(),
                Placeholder::make('team.organizer.name')
                    ->label('Капитан')->columnSpanFull(),
                Select::make('status')
                    ->label('Статус')
                    ->options([
            'pending' => 'На рассмотрении',
            'approved' => 'Одобрена',
            'rejected' => 'Отклонена',
            'withdrawn' => 'Отозвана',
        ])
                    ->default('pending')
                    ->required()->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('team.name')
                    ->label('Команда')
                    ->searchable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable(),

                TextColumn::make('team.organizer.name')
                    ->label('Капитан')
                    ->searchable(),
                TextColumn::make('submitted_at')
                    ->label('Дата')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На рассмотрении',
                    'approved' => 'Одобрена',
                    'rejected' => 'Отклонена',
                    'withdrawn' => 'Отозвана',
                    default => $state,
                })
                ->color(fn (string $state): string => match ($state) {
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'withdrawn' => 'gray',
                    default => 'gray',
                }),

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
            'index' => ManageRegattaEntries::route('/'),
        ];
    }
}
