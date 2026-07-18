<?php

namespace App\Filament\Resources\RaceResults;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Concerns\ScopesToOwnedRegattas;
use App\Filament\Resources\RaceResults\Pages\ManageRaceResults;
use App\Models\RaceResult;
use App\Models\RegattaEntry;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RaceResultResource extends Resource
{
    use RestrictsAccessByRole;
    use ScopesToOwnedRegattas;

    protected static ?string $model = RaceResult::class;

    /** До регаты идём через заявку. */
    protected static function regattaRelationPath(): ?string
    {
        return 'regattaEntry.regatta';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 10;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getModelLabel(): string
    {
        return 'Результат гонки'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результат гонок'; // Название во множественном числе
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToOwnedRegattas(parent::getEloquentQuery());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_entry_id')
                    ->label('Заявка')
                    ->relationship(
                        name: 'regattaEntry',
                        modifyQueryUsing: fn (Builder $query) => $query->whereHas(
                            'regatta',
                            fn (Builder $q) => $q->visibleForUser(),
                        ),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (RegattaEntry $record): string => trim(
                            ($record->regatta?->name ?? '—')
                            .' — '.($record->team?->name ?? '—')
                            .' / '.($record->yacht?->name ?? '—')
                        )
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('event_id', null))
                    ->required(),
                Select::make('event_id')
                    ->label('Гонка')
                    ->relationship(
                        name: 'race',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, Get $get): void {
                            $regattaId = ($entryId = $get('regatta_entry_id'))
                                ? RegattaEntry::whereKey($entryId)->value('regatta_id')
                                : null;

                            $query->where('regatta_id', $regattaId);
                        },
                    )
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => ! $get('regatta_entry_id'))
                    ->required(),
                TextInput::make('position')
                    ->numeric(),
                TextInput::make('points')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                // TextInput::make('penalty_code'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('race.name')
                    ->label('Гонка')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regattaEntry.team.name')
                    ->label('Команда')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regattaEntry.yacht.name')
                    ->label('Яхта')
                    ->searchable()
                    ->sortable(),
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
