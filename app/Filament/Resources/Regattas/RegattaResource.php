<?php

namespace App\Filament\Resources\Regattas;

use App\Filament\Resources\Regattas\Pages\ManageRegattas;
use App\Models\Regatta;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Forms\Components\Repeater;

class RegattaResource extends Resource
{
    protected static ?string $model = Regatta::class;

    protected static string|BackedEnum|null $navigationIcon = 'regatta';

    public static function getModelLabel(): string
    {
        return 'Регата'; // Название в единственном числе
    }

    public static function getPluralModelLabel(): string
    {
        return 'Регаты'; // Название во множественном числе
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->placeholder('Введите название регаты')
                    ->required(),
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year')
                    ->required(),
                TextInput::make('level_coefficient')
                    ->label('Коэффициент соревнований')
                    ->placeholder('Введите коэффициент соревнований')
                    ->required()
                    ->numeric()
                    ->default(1.0)->columnSpanFull(),
                DatePicker::make('date_start')
                    ->label('Дата начала')
                    ->displayFormat('d.m.Y')
                    ->required(),
                DatePicker::make('date_end')
                    ->label('Дата окончания')
                    ->displayFormat('d.m.Y')
                    ->required(),

                TextInput::make('race_days_count')
                    ->label('Количество гоночных дней')
                    ->placeholder('1')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('races_count')
                    ->label('Количество гонок')
                    ->placeholder('1')
                    ->required()
                    ->numeric()
                    ->default(1),

                TextInput::make('location')
                    ->label('Локацию')
                    ->placeholder('Выберите локацию'),
                TextInput::make('water_area')
                    ->label('Акватория')
                    ->placeholder('Введите акваторию'),
                Textarea::make('description')
                    ->label('О регате')
                    ->placeholder('Описание о регате')
                    ->columnSpanFull(),
                FileUpload::make('background_image')
                    ->label('Загрузить обложку'),

                Textarea::make('map_html')
                    ->label('Карта (HTML)')
                    ->placeholder('HTML-код карты')
                    ->columnSpanFull(),
                Textarea::make('prizes')
                    ->label('Призы')
                    ->placeholder('Описание призового фонда')
                    ->columnSpanFull(),
                Textarea::make('regulations')
                    ->label('Регламент')
                    ->placeholder('Описание регламента')
                    ->columnSpanFull(),

                Repeater::make('regatta_events') // Имя отношения из модели Regatta
                ->relationship('races') // Указываем Filament автоматически управлять связью
                ->label('Расписание регаты')
                ->schema([
                    TextInput::make('event_number')
                        ->label('№')
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(fn () => 1),
                    TextInput::make('name')
                        ->label('Событие')
                        ->required(),
                    DateTimePicker::make('event_datetime')
                        ->label('Время')
                        ->required(),
                    TextInput::make('description')
                        ->label('Описание'),

                ])->itemLabel(fn (array $state): ?string => (!empty($state['event_datetime']) && !empty($state['name']))
            ? "#{$state['event_number']} {$state['event_datetime']} — {$state['name']}"
            : 'Новое событие')
                ->orderColumn('event_number')
                ->columns(4) // Разместим поля ввода внутри карточки в 4 колонки для компактности
                ->columnSpanFull()
                ->addActionLabel('Добавить пункт расписания') // Текст на кнопке добавления
                ->collapsible(), // Позволит сворачивать пункты, чтобы не занимали много места

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Регата')
                    ->searchable(),
                TextColumn::make('season.year')
                    ->label('Сезон')->searchable(),
                TextColumn::make('date')
                    ->label('Дата')
                    ->getStateUsing(function (Regatta $regatta): string {
                        // Доступ к текущей строке (модели) через $record
                        return $regatta->dateRange();
                    }),
                TextColumn::make('water_area')
                    ->label('Акватория'),
                TextColumn::make('status')
                    ->label('Статус')->badge()
                ->getStateUsing(function (Regatta $regatta): string {
                    if($regatta->startsInLessThanMonth())
                        return 'closest';
                    else if ($regatta->isUpcoming()) {
                        return 'planned';
                    } else if ($regatta->isFinished()) {
                        return 'completed';
                    }
                })
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'closest' => 'Ближайшая',
                    'planned' => 'Планируемая',
                    'completed' => 'Завершена',
                    default => $state,
                })->color(fn (string $state): string => match ($state) {
                    'planned' => 'warning',
                    'completed' => 'success',
                    'closest' => 'danger',
                    default => 'gray',
                }),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                Filter::make('created_at_day')
                    ->label('Дата')
                    ->schema([
                        DatePicker::make('date')
                            ->label('Выберите дату')
                            ->displayFormat('d.m.Y')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'],
                            fn (Builder $query, $date) => $query->whereDate('date_start', '<=', $date)
                                     ->whereDate('date_end', '>=', $date),
                        );
                    }),
            SelectFilter::make('status')
                ->label('Статус') // Красивое название для пользователя
                ->options([
                    'closest' => 'Ближайшая',
                    'planned' => 'Планируемая',
                    'completed' => 'Завершена',
                        ])->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['value'],
                        function (Builder $query, $value) {
                            // Здесь вы переносите логику ваших методов из модели Regatta в SQL-запросы
                            match ($value) {
                                'closest' => $query->where('date_start', '<=', now()->addMonth())->where('date_start', '>', now()),
                                'planned' => $query->where('date_start', '>', now()->addMonth()),
                                'completed' => $query->where('date_end', '<', now()),
                                default => $query,
                            };
                        }
                    );
                }),
            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3)->deferFilters(false)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegattas::route('/'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
