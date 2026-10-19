<?php

declare(strict_types=1);

namespace App\Filament\Resources\Seasons;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Resources\Seasons\Pages\ManageSeasons;
use App\Models\Season;
use App\Services\SeasonContext;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Справочник сезонов.
 *
 * Сезон — основной срез данных: регаты, заявки, результаты, серии и рейтинги
 * в админке и на сайте фильтруются по нему (SeasonContext, FiltersBySeason).
 * Удалить можно только пустой сезон: серии и рейтинги удалились бы каскадом.
 */
class SeasonResource extends Resource
{
    use RestrictsAccessByRole;

    protected static ?string $model = Season::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 6;

    protected static string|UnitEnum|null $navigationGroup = 'Регаты';

    public static function getModelLabel(): string
    {
        return 'Сезон';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Сезоны';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->label('Год')
                    ->required()
                    ->integer()
                    ->minValue(2000)
                    ->maxValue(2099)
                    ->unique(ignoreRecord: true)
                    ->live(onBlur: true)
                    // Сезон по умолчанию — календарный год; даты можно сузить вручную.
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if (blank($state) || ! ctype_digit($state)) {
                            return;
                        }

                        if (blank($get('start_date'))) {
                            $set('start_date', Carbon::create((int) $state)->startOfYear()->toDateString());
                        }

                        if (blank($get('end_date'))) {
                            $set('end_date', Carbon::create((int) $state)->endOfYear()->toDateString());
                        }
                    })
                    ->columnSpanFull(),
                DatePicker::make('start_date')
                    ->label('Дата начала')
                    ->displayFormat('d.m.Y')
                    ->native(false)
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Дата окончания')
                    ->displayFormat('d.m.Y')
                    ->native(false)
                    ->afterOrEqual('start_date')
                    ->required(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['regattas', 'series', 'teamRatings', 'personalRatings']))
            ->columns([
                TextColumn::make('year')
                    ->label('Год')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_current')
                    ->label('Текущий')
                    ->state(fn (Season $record): bool => $record->is(app(SeasonContext::class)->current()))
                    ->boolean()
                    ->falseIcon(null),
                TextColumn::make('start_date')
                    ->label('Начало')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_date')
                    ->label('Окончание')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('regattas_count')
                    ->label('Регат')
                    ->sortable(),
                TextColumn::make('series_count')
                    ->label('Серий')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('team_ratings_count')
                    ->label('Команд в рейтинге')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('personal_ratings_count')
                    ->label('Участников в рейтинге')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Сезонов пока нет')
            ->defaultSort('year', 'desc')
            ->filters([
                Filter::make('in_use')
                    ->label('Только сезоны с данными')
                    ->query(fn (Builder $query): Builder => $query->inUse()),
            ])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать сезон'),
                DeleteAction::make()
                    ->visible(fn (Season $record): bool => ! $record->isInUse()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSeasons::route('/'),
        ];
    }
}
