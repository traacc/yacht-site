<?php

namespace App\Filament\User\Resources\RegattaEntries;

use App\Filament\User\Resources\RegattaEntries\Pages\ManageRegattaEntries;
use App\Models\RegattaEntry;
use App\Models\User;
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
use Illuminate\Database\Eloquent\Builder;

class RegattaEntryResource extends Resource
{
    protected static ?string $model = RegattaEntry::class;

    protected static string|BackedEnum|null $navigationIcon = 'cup';

    public static function getModelLabel(): string
    {
        return 'Заявка на соревнование'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Заявки на соревнования'; // Название во множественном числе
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->whereHas('team', fn (Builder $q) => $q->visibleForUser($user));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_id')
                    ->relationship('regatta', 'name')->label('Регата')
                    ->required(),
                Select::make('team_id')
                    ->relationship('team', 'name', modifyQueryUsing: fn (Builder $query) => $query->visibleForUser(auth()->user()))->label('Команда')
                    ->required(),
                Select::make('yacht_id')
                    ->relationship('yacht', 'name')->label('Яхта'),
                DateTimePicker::make('submitted_at')->maxDate(now()->addMonths(3)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('regatta.name')->label('Регата')
                    ->searchable(),
                TextColumn::make('team.name')->label('Команда')
                    ->searchable(),
                TextColumn::make('yacht.name')->label('Яхта')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()->label('Статус')->formatStateUsing(fn (string $state): string => match ($state) {
                    'pending' => 'На проверке',
                    'approved' => 'Активная',
                    'rejected' => 'Отклонена',
                    default => $state,
                })->color(fn (string $state): string => match ($state) {
                    'pending' => 'gray',
                    'approved' => 'success',
                    'rejected' => 'danger',
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
            ])->stackedOnMobile()
            ->filters([
                //
            ])->emptyStateHeading('Записей пока нет')
            ->recordActions([
                //EditAction::make(),
                //DeleteAction::make(),
            ])
            ->toolbarActions([
                /*
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                */
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattaEntries::route('/'),
        ];
    }
}
