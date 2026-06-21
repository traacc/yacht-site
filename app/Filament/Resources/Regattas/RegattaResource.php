<?php

declare(strict_types=1);

namespace App\Filament\Resources\Regattas;

use App\Filament\Resources\Regattas\Pages\ManageRegattas;
use App\Models\Regatta;
use App\Services\RegattaService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Kpebedko22\FilamentYandexMap\Forms\Components\YandexMap;
use Kpebedko22\FilamentYandexMap\Enums\YandexMapMode;
use Kpebedko22\FilamentYandexMap\DTOs\Buttons\{ButtonData, ButtonOptions};
use Kpebedko22\FilamentYandexMap\Enums\Buttons\{ButtonFloat, ButtonSize};

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Str;

class RegattaResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = Regatta::class;

    protected static string|BackedEnum|null $navigationIcon = 'regatta';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return 'Регата';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Регаты';
    }

    public static function form(Schema $schema): Schema
    {
        $maxFiles      = (int) config('documents.max_files_per_type', 10);
        $acceptedTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        return $schema
            ->components([
                Textarea::make('name')
                    ->label('Название')
                    ->placeholder('Введите название регаты')
                    ->required(),
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year',
                    modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('year'),)
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('year')
                            ->label('Год')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2099),
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Дата начала сезона')
                            ->displayFormat('d.m.Y')
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('Дата окончания сезона')
                            ->displayFormat('d.m.Y')
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data): string => \App\Models\Season::create($data)->id)
                    ->required(),
                Select::make('series_id')
                    ->label('Серия')
                    ->relationship('series', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->label('Название')
                            ->required(),
                        Forms\Components\Select::make('season_id')
                            ->label('Сезон')
                            ->relationship('season', 'year',
                                modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('year'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->label('Описание'),
                    ])
                    ->createOptionUsing(fn (array $data): string => \App\Models\Series::create($data)->id),
                TextInput::make('level_coefficient')
                    ->label('Коэффициент соревнований')
                    ->placeholder('Введите коэффициент соревнований')
                    ->required()
                    ->numeric()
                    ->default(1.0)
                    ->columnSpanFull(),
                DatePicker::make('date_start')
                    ->label('Дата начала')
                    ->displayFormat('d.m.Y')
                    ->minDate(now()->subYears(100)) 
                    ->maxDate(now()->addYears(100))
                    ->required(),
                DatePicker::make('date_end')
                    ->label('Дата окончания')
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100))
                    ->displayFormat('d.m.Y')
                    ->required(),

                TimePicker::make('time_start')
                    ->label('Время начала')
                    ->displayFormat('H:i')
                    ->seconds(false),

                TimePicker::make('time_end')
                    ->label('Время окончания')
                    ->displayFormat('H:i')
                    ->seconds(false),

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
                TextInput::make('water_area')
                    ->label('Акватория')
                    ->placeholder('Введите акваторию')
                    ->columnSpanFull(),

                
                YandexMap::make('coordinates')
                    ->label('Выберите на карте')
                    ->columnSpanFull()
                    ->mode(YandexMapMode::Placemark)
                    ->apiKey('ffd9d711-109d-415d-bf73-e1a935512160')
                    ->lang('ru_RU')
                    ->center([55.7558, 37.6173])
                    ->zoom(10)
                    ->height('450px')
                    ->formatStateUsing(function ($state) {
                        if (is_string($state) && str_contains($state, ',')) {
                            [$lat, $lng] = explode(',', $state, 2);
                            return [
                                'lat' => (float) trim($lat),
                                'lng' => (float) trim($lng),
                            ];
                        }
                        return $state;
                    })
                    ->drawBtnParameters(
                        new ButtonData('Поставить'),
                        new ButtonOptions(float: ButtonFloat::Right),
                    )
                    ->dehydrateStateUsing(function ($state) {
                        if (is_array($state) && isset($state['lat'], $state['lng'])) {
                            return "{$state['lat']},{$state['lng']}";
                        }
                        return $state;
                    }),

                TextInput::make('short_description')
                    ->label('Краткое описание')
                    ->placeholder('Краткое описание')
                    ->columnSpanFull(),
                RichEditor::make('description')
                    ->label('О регате')
                    ->placeholder('Описание о регате')
                    ->columnSpanFull(),
                FileUpload::make('background_image')
                    ->label('Загрузить обложку')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->disk('public')
                    ->directory('regattas/covers')
                    ->visibility('public'),

                Textarea::make('prizes')
                    ->label('Призы')
                    ->placeholder('Описание призового фонда')
                    ->columnSpanFull(),
                Hidden::make('regatta_status')
                    ->default(\App\Enums\RegattaStatus::Upcoming->value)
                    ->required(),

                Toggle::make('is_postponed')
                    ->label('Перенесена')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (bool $state, $set, $get) {
                        if ($state) {
                            $set('regatta_status', \App\Enums\RegattaStatus::Postponed->value);
                            $set('is_cancelled', false);
                        } elseif (! $get('is_cancelled')) {
                            $set('regatta_status', \App\Enums\RegattaStatus::Upcoming->value);
                        }
                    }),

                Toggle::make('is_cancelled')
                    ->label('Отменена')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (bool $state, $set, $get) {
                        if ($state) {
                            $set('regatta_status', \App\Enums\RegattaStatus::Cancelled->value);
                            $set('is_postponed', false);
                        } elseif (! $get('is_postponed')) {
                            $set('regatta_status', \App\Enums\RegattaStatus::Upcoming->value);
                        }
                    }),

                DatePicker::make('postponed_to_date')
                    ->label('Дата переноса')
                    ->displayFormat('d.m.Y')
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100))
                    ->visible(fn (Get $get): bool => (bool) $get('is_postponed'))
                    ->required(fn (Get $get): bool => (bool) $get('is_postponed')),

                Repeater::make('regatta_events')
                    ->relationship('races')
                    ->label('Расписание регаты')
                    ->defaultItems(0)
                    ->reorderable()
                    ->schema([
                        TextInput::make('name')
                            ->label('Событие')
                            ->required(),
                        Select::make('event_type')
                            ->label('Тип')
                            ->options([
                                'schedule' => 'Расписание',
                                'race'     => 'Гонка',
                            ])
                            ->default('schedule')
                            ->required(),
                        DateTimePicker::make('event_datetime')
                            ->label('Время')
                            ->displayFormat('d.m.Y H:i')
                            ->format('Y-m-d H:i:s'),
                        TextInput::make('description')
                            ->label('Описание'),
                    ])
                    ->itemLabel(fn (array $state, int $index): ?string => (! empty($state['event_datetime']) && ! empty($state['name']))
                        ? ($index + 1) . ". {$state['event_datetime']} — {$state['name']}"
                        : ($index + 1) . '. Новое событие')
                    ->columns(4)
                    ->columnSpanFull()
                    ->addActionLabel('Добавить пункт расписания')
                    ->collapsible(),

                // ── Обязательные документы ──────────────────
                Repeater::make('required_documents')
                    ->label('​')
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->default(fn () => array_map(
                        fn (array $doc) => [
                            'doc_type' => $doc['doc_type'],
                            'title'    => $doc['title'],
                            'files'    => [],
                        ],
                        ManageRegattas::getRequiredDocuments(),
                    ))
                    ->columns(1)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->rules([
                        function (): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                $requiredDocs = ManageRegattas::getRequiredDocuments();
                                $missing = [];

                                foreach ($requiredDocs as $required) {
                                    $docType = $required['doc_type'];
                                    $item = collect((array) $value)->first(
                                        fn (array $doc): bool => ($doc['doc_type'] ?? '') === $docType
                                    );

                                    $files = array_filter((array) ($item['files'] ?? []));

                                    if ($item === null || $files === []) {
                                        $missing[] = $required['title'];
                                    }
                                }

                                if ($missing !== []) {
                                    $fail('Загрузите следующие обязательные документы: ' . implode(', ', $missing) . '.');
                                }
                            };
                        },
                    ])
                    ->schema([
                        Hidden::make('doc_type'),
                        Hidden::make('title'),
                        FileUpload::make('files')
                            ->label('Файлы')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('documents')
                            ->disk('public')
                            ->acceptedFileTypes($acceptedTypes)
                            ->maxSize(20480)
                            ->maxFiles($maxFiles)
                            ->downloadable()
                            ->helperText('Можно загрузить до ' . $maxFiles . ' файлов'),
                    ]),

                // ── Документы регаты (для отображения пользователям) ──
                Repeater::make('extra_documents')
                    ->label('Документы регаты')
                    ->columnSpanFull()
                    ->addActionLabel('Добавить документ')
                    ->collapsible()
                    ->defaultItems(3)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->schema([
                        Select::make('doc_type')
                            ->label('Тип')
                            ->options(fn () => \App\Models\YachtDocumentType::options())
                            ->default('other')
                            ->required(),
                        TextInput::make('title')
                            ->label('Название')
                            ->placeholder('Название документа')
                            ->required(),
                        FileUpload::make('files')
                            ->label('Файлы')
                            ->multiple()
                            ->reorderable()
                            ->appendFiles()
                            ->directory('documents')
                            ->disk('public')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => (string) Str::of($file->getClientOriginalName())
                                    ->beforeLast('.')
                                    ->slug() // Превратит "Отчёт 2026" в "otcet-2026"
                                    ->append('.' . $file->getClientOriginalExtension()),
                            )
                            ->acceptedFileTypes($acceptedTypes)
                            ->maxSize(20480)
                            ->maxFiles($maxFiles)
                            ->downloadable()
                            ->helperText('Можно загрузить до ' . $maxFiles . ' файлов'),
                    ]),
                // ── Документы для подачи заявок ──
                Section::make('Документы для заявок')
                    ->description('Документы, которые участник должен приложить при подаче заявки. Если ничего не добавлено — применяются глобальные настройки.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Repeater::make('entry_required_documents')
                            ->label('Документы')
                            ->defaultItems(0)
                            ->columns(2)
                            ->addActionLabel('Добавить документ')
                            ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                            ->schema([
                                Select::make('doc_type')
                                    ->label('Тип документа')
                                    ->options(fn () => \App\Models\YachtDocumentType::cachedConfigurable()
                                        ->pluck('label', 'key')
                                        ->all())
                                    ->required()
                                    ->distinct(),
                                Checkbox::make('is_required')
                                    ->label('Обязательный')
                                    ->default(false),
                            ])
                            ->collapsible()
                            ->helperText('Добавьте типы документов для заявки. По умолчанию документ необязателен.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Регата')
                    ->searchable()->sortable(),
                TextColumn::make('season.year')
                    ->label('Сезон')
                    ->searchable()->sortable()->toggleable(),
                TextColumn::make('series.name')
                    ->label('Серия')
                    ->searchable()->sortable()->toggleable(),
                TextColumn::make('date')
                    ->label('Дата')->sortable(['date_start', 'date_end'])
                    ->getStateUsing(fn (Regatta $regatta): string => $regatta->dateRange())->toggleable(),
                TextColumn::make('water_area')
                    ->label('Акватория')->sortable()->toggleable(),
                TextColumn::make('regatta_status')
                    ->label('Статус')
                    ->badge()->sortable()->toggleable(),
            ])
            ->stackedOnMobile()
            ->emptyStateHeading('Записей пока нет')
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
                            fn (Builder $query, $date) => $query
                                ->whereDate('date_start', '<=', $date)
                                ->whereDate('date_end', '>=', $date),
                        );
                    }),
                SelectFilter::make('regatta_status')
                    ->label('Статус')
                    ->options([
                        \App\Enums\RegattaStatus::Upcoming->value  => \App\Enums\RegattaStatus::Upcoming->getLabel(),
                        \App\Enums\RegattaStatus::Postponed->value => \App\Enums\RegattaStatus::Postponed->getLabel(),
                        \App\Enums\RegattaStatus::Cancelled->value => \App\Enums\RegattaStatus::Cancelled->getLabel(),
                        \App\Enums\RegattaStatus::Finished->value  => \App\Enums\RegattaStatus::Finished->getLabel(),
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать регату')
                    ->mountUsing(function (Schema $form, Regatta $record): void {
                        $sync         = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $requiredDocs = ManageRegattas::getRequiredDocuments();
                        $requiredDocTypes = array_column($requiredDocs, 'doc_type');

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);
                        $data['extra_documents']    = $sync->loadExtra($record, $requiredDocTypes);

                        $data['is_postponed'] = ($data['regatta_status'] ?? null) === \App\Enums\RegattaStatus::Postponed->value;
                        $data['is_cancelled'] = ($data['regatta_status'] ?? null) === \App\Enums\RegattaStatus::Cancelled->value;

                        $form->fill($data);
                    })
                    ->using(function (Regatta $record, array $data): Regatta {
                        $requiredDocs = $data['required_documents'] ?? [];
                        $extraDocs    = $data['extra_documents'] ?? [];
                        unset($data['required_documents'], $data['extra_documents'], $data['is_postponed'], $data['is_cancelled']);

                        $postponedToDate = $data['postponed_to_date'] ?? null;
                        $newStatus       = $data['regatta_status'] ?? null;

                        // Если статус postponed и указана дата — вызываем RegattaService
                        if (static::statusEquals($newStatus, \App\Enums\RegattaStatus::Postponed) && $postponedToDate) {
                            $service = app(RegattaService::class);
                            $service->postpone($record, Carbon::parse($postponedToDate));

                            $sync = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                            $sync->execute($record, $requiredDocs);
                            $sync->execute($record, $extraDocs);

                            $requiredDocTypes = array_column(ManageRegattas::getRequiredDocuments(), 'doc_type');
                            $activeExtraTypes = array_filter(array_column($extraDocs, 'doc_type'));
                            $sync->pruneOrphanedDocTypes($record, $requiredDocTypes, $activeExtraTypes);

                            return $record->fresh();
                        }

                        $record->update($data);

                        $sync = app(\App\Actions\Document\SyncDocumentFilesAction::class);
                        $sync->execute($record, $requiredDocs);
                        $sync->execute($record, $extraDocs);

                        $requiredDocTypes = array_column(ManageRegattas::getRequiredDocuments(), 'doc_type');
                        $activeExtraTypes = array_filter(array_column($extraDocs, 'doc_type'));
                        $sync->pruneOrphanedDocTypes($record, $requiredDocTypes, $activeExtraTypes);

                        return $record;
                    }),
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

    /**
     * Сравнивает значение статуса (строка или enum) с целевым enum-кейсом.
     */
    public static function statusEquals(mixed $status, \App\Enums\RegattaStatus $target): bool
    {
        if ($status instanceof \App\Enums\RegattaStatus) {
            return $status === $target;
        }

        return is_string($status) && $status === $target->value;
    }

    /**
     * Определяет читаемую метку для документа в Repeater.
     */
    public static function resolveDocumentLabel(array $state): ?string
    {
        $docType = $state['doc_type'] ?? null;

        if ($docType === null) {
            return $state['title'] ?? null;
        }

        $model = \App\Models\YachtDocumentType::cachedAll()
            ->first(fn (\App\Models\YachtDocumentType $t) => $t->key === $docType);

        return $model?->label ?? ($state['title'] ?? null);
    }
}
