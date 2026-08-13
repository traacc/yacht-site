<?php

declare(strict_types=1);

namespace App\Filament\Resources\Regattas;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Regatta\ReplicateRegattaAction;
use App\Enums\RegattaStatus;
use App\Enums\RegattaType;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Filament\Concerns\ScopesToOwnedRegattas;
use App\Filament\Resources\Regattas\Pages\ManageRegattas;
use App\Models\Regatta;
use App\Models\Season;
use App\Models\Series;
use App\Models\YachtDocumentType;
use App\Services\RegattaService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Kpebedko22\FilamentYandexMap\DTOs\Buttons\ButtonData;
use Kpebedko22\FilamentYandexMap\DTOs\Buttons\ButtonOptions;
use Kpebedko22\FilamentYandexMap\Enums\Buttons\ButtonFloat;
use Kpebedko22\FilamentYandexMap\Enums\YandexMapMode;
use Kpebedko22\FilamentYandexMap\Forms\Components\YandexMap;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Str;
use UnitEnum;

class RegattaResource extends Resource
{
    use RestrictsAccessByRole;
    use ScopesToOwnedRegattas;

    protected static ?string $model = Regatta::class;

    /** Ресурс самой регаты — связи до неё нет. */
    protected static function regattaRelationPath(): ?string
    {
        return null;
    }

    protected static string|BackedEnum|null $navigationIcon = 'regatta';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = 'Регаты';

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
        $maxFiles = (int) config('documents.max_files_per_type', 10);
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
                Select::make('type')
                    ->label('Тип соревнования')
                    ->options(RegattaType::options())
                    ->default(RegattaType::Club->value)
                    ->required()
                    ->live()
                    ->helperText('Тип задаёт способ подачи заявок и цвет регаты в календаре.')
                    ->columnSpanFull(),
                TextInput::make('external_id')
                    ->label('ID (внешний)')
                    ->helperText('Номер регаты для судейской программы. Генерируется автоматически.')
                    ->readOnly()
                    ->dehydrated(false)
                    ->visible(fn (): bool => (bool) auth()->user()?->isDeveloperAdmin())
                    ->formatStateUsing(fn (?Regatta $record) => $record?->external_id ?? '—'),
                Select::make('season_id')
                    ->label('Сезон')
                    ->relationship('season', 'year',
                        modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('year'), )
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('year')
                            ->label('Год')
                            ->required()
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2099),
                        DatePicker::make('start_date')
                            ->label('Дата начала сезона')
                            ->displayFormat('d.m.Y')
                            ->native(false)
                            ->required(),
                        DatePicker::make('end_date')
                            ->label('Дата окончания сезона')
                            ->displayFormat('d.m.Y')
                            ->native(false)
                            ->required(),
                    ])
                    ->createOptionUsing(fn (array $data): string => Season::create($data)->id)
                    ->required(),
                // ── Создание серии из этапов ──────────────
                // Альтернативный способ: заполнить регату-«шаблон» один раз
                // и задать список дат — по каждой дате создаётся регата серии
                // с данными из этой формы. Доступно только при создании.
                Section::make('Создать как серию')
                    ->description('Заполните данные регаты один раз и добавьте даты этапов — по каждому этапу будет создана отдельная регата серии с этими данными.')
                    ->columnSpanFull()
                    ->collapsible()
                    // Серии глобальны, админ-разработчик ими управлять не может —
                    // он создаёт только одиночные регаты.
                    ->hidden(fn (string $operation): bool => $operation !== 'create' || static::hidesSeries())
                    ->schema([
                        Toggle::make('create_as_series')
                            ->label('Создать серию из этапов')
                            ->helperText('Вместо одной регаты будет создано несколько — по одной на каждый этап. Остальные поля формы копируются в каждый этап.')
                            ->live()
                            ->default(false),
                        TextInput::make('series_name')
                            ->label('Название серии')
                            ->placeholder('Например: Кубок весны 2026')
                            ->visible(fn (Get $get): bool => (bool) $get('create_as_series'))
                            ->required(fn (Get $get): bool => (bool) $get('create_as_series')),
                        Repeater::make('series_stages')
                            ->label('Этапы серии')
                            ->helperText('Каждый этап станет отдельной регатой. Название формируется автоматически: «<название> — Этап N». Расписание сдвигается на смещение дат этапа.')
                            ->visible(fn (Get $get): bool => (bool) $get('create_as_series'))
                            ->defaultItems(2)
                            ->minItems(2)
                            ->addActionLabel('Добавить этап')
                            ->columns(2)
                            ->itemLabel(fn (array $state, int $index): string => 'Этап '.($index + 1))
                            ->schema([
                                DatePicker::make('date_start')
                                    ->label('Дата начала')
                                    ->displayFormat('d.m.Y')
                                    ->native(false)
                                    ->required(),
                                DatePicker::make('date_end')
                                    ->label('Дата окончания')
                                    ->displayFormat('d.m.Y')
                                    ->native(false)
                                    ->required(),
                                TimePicker::make('time_start')
                                    ->label('Время начала')
                                    ->displayFormat('H:i')
                                    ->seconds(false),
                                TimePicker::make('time_end')
                                    ->label('Время окончания')
                                    ->displayFormat('H:i')
                                    ->seconds(false),
                            ]),
                    ]),
                Select::make('series_id')
                    ->label('Серия')
                    ->relationship('series', 'name')
                    ->searchable()
                    ->preload()
                    ->hidden(fn (Get $get): bool => (bool) $get('create_as_series') || static::hidesSeries())
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Название')
                            ->required(),
                        Select::make('season_id')
                            ->label('Сезон')
                            ->relationship('season', 'year',
                                modifyQueryUsing: fn (Builder $query) => $query->orderByDesc('year'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Textarea::make('description')
                            ->label('Описание'),
                    ])
                    ->createOptionUsing(fn (array $data): string => Series::create($data)->id),
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
                    ->native(false)
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100))
                    // В режиме серии даты задаются в этапах.
                    ->hidden(fn (Get $get): bool => (bool) $get('create_as_series'))
                    ->required(fn (Get $get): bool => ! (bool) $get('create_as_series')),
                DatePicker::make('date_end')
                    ->label('Дата окончания')
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100))
                    ->displayFormat('d.m.Y')
                    ->native(false)
                    ->hidden(fn (Get $get): bool => (bool) $get('create_as_series'))
                    ->required(fn (Get $get): bool => ! (bool) $get('create_as_series')),

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
                Select::make('discards_count')
                    ->label('Выброс худших результатов')
                    ->options([
                        0 => 'Без выброса',
                        1 => '1 худший результат',
                        2 => '2 худших результата',
                    ])
                    // Стандарт для новых регат — один выброс после 6 гонок;
                    // существующие регаты остаются «без выброса» (дефолт БД).
                    ->default(1)
                    ->required()
                    ->live(),
                TextInput::make('discard_1_after_races')
                    ->label('Выброс 1 результата после N гонок')
                    ->numeric()
                    ->minValue(1)
                    ->default(6)
                    ->visible(fn (Get $get): bool => (int) $get('discards_count') >= 1)
                    ->required(fn (Get $get): bool => (int) $get('discards_count') >= 1),
                TextInput::make('discard_2_after_races')
                    ->label('Выброс 2 результатов после N гонок')
                    ->numeric()
                    ->minValue(2)
                    ->default(9)
                    ->gte('discard_1_after_races')
                    ->visible(fn (Get $get): bool => (int) $get('discards_count') === 2)
                    ->required(fn (Get $get): bool => (int) $get('discards_count') === 2),
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
                    ->imageEditor()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
                    ->disk('public')
                    ->directory('regattas/covers')
                    ->visibility('public'),

                Textarea::make('prizes')
                    ->label('Призы')
                    ->placeholder('Описание призового фонда')
                    ->columnSpanFull(),

                // ── Сборы за участие ──────────────────────
                Section::make('Сборы за участие')
                    ->description('Если оплата сборов обязательна, участник увидит сумму при подаче заявки и сможет отметить, оплатил ли он сбор.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Toggle::make('entry_fee_required')
                            ->label('Требуется оплата сборов')
                            ->live()
                            ->default(false),
                        TextInput::make('entry_fee_amount')
                            ->label('Сумма сбора')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('₽')
                            ->visible(fn (Get $get): bool => (bool) $get('entry_fee_required'))
                            ->required(fn (Get $get): bool => (bool) $get('entry_fee_required')),
                    ]),

                // ── Регулярная регата: продажа мест ───────
                Section::make('Места на лодках ассоциации')
                    ->description('Ассоциация выставляет лодки и продаёт места на них. Продолжительность в гоночных днях задаётся выше.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Get $get): bool => $get('type') === RegattaType::Regular->value)
                    ->schema([
                        TextInput::make('seat_price')
                            ->label('Стоимость места')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('₽'),
                        TextInput::make('boat_price')
                            ->label('Стоимость лодки целиком')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->suffix('₽'),
                        TextInput::make('race_hours_per_day')
                            ->label('Часов в течение гоночного дня')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->step(0.5),
                    ]),

                // ── Выездная регата: размер экипажа ───────
                Section::make('Экипаж выездной регаты')
                    ->description('Количество человек в экипаже зависит от характеристик лодок, участвующих в регате.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->visible(fn (Get $get): bool => $get('type') === RegattaType::Travel->value)
                    ->schema([
                        TextInput::make('crew_size_limit')
                            ->label('Человек в экипаже')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(30)
                            ->helperText('Больше этого числа участников в заявку не примут. Пусто — без ограничения.'),
                    ]),

                Hidden::make('regatta_status')
                    ->default(RegattaStatus::Upcoming->value)
                    ->required(),

                Toggle::make('is_postponed')
                    ->label('Перенесена')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (bool $state, $set, $get) {
                        if ($state) {
                            $set('regatta_status', RegattaStatus::Postponed->value);
                            $set('is_cancelled', false);
                        } elseif (! $get('is_cancelled')) {
                            $set('regatta_status', RegattaStatus::Upcoming->value);
                        }
                    }),

                Toggle::make('is_cancelled')
                    ->label('Отменена')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function (bool $state, $set, $get) {
                        if ($state) {
                            $set('regatta_status', RegattaStatus::Cancelled->value);
                            $set('is_postponed', false);
                        } elseif (! $get('is_postponed')) {
                            $set('regatta_status', RegattaStatus::Upcoming->value);
                        }
                    }),

                DatePicker::make('postponed_to_date')
                    ->label('Дата переноса')
                    ->displayFormat('d.m.Y')
                    ->native(false)
                    ->minDate(now()->subYears(100))
                    ->maxDate(now()->addYears(100))
                    ->visible(fn (Get $get): bool => (bool) $get('is_postponed'))
                    // Нужно заполнить либо дату переноса, либо пояснение к нему.
                    ->rules([
                        fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            if ((bool) $get('is_postponed') && blank($value) && blank($get('postponed_note'))) {
                                $fail('Укажите дату переноса или пояснение к переносу.');
                            }
                        },
                    ]),

                Textarea::make('postponed_note')
                    ->label('Пояснение к переносу')
                    ->helperText('Если новая дата пока не известна, укажите пояснение — оно будет показано вместо даты.')
                    ->placeholder('Например: дата будет объявлена позже')
                    ->visible(fn (Get $get): bool => (bool) $get('is_postponed')),

                Repeater::make('schedule_events')
                    ->relationship('scheduleEvents')
                    ->label('Расписание регаты')
                    ->helperText('Мероприятия регаты: регистрация, открытие, брифинги и т.п. Гонки задаются в результатах регаты.')
                    ->hintAction(
                        Action::make('duplicateAllPlusDay')
                            ->label('Дублировать весь список на следующий день')
                            ->icon('heroicon-m-document-duplicate')
                            ->color('gray')
                            ->visible(fn (Repeater $component): bool => filled($component->getRawState()))
                            ->action(function (Repeater $component): void {
                                $items = $component->getRawState();

                                $newItems = $items;
                                foreach ($items as $item) {
                                    if (! empty($item['event_datetime'])) {
                                        $item['event_datetime'] = Carbon::parse($item['event_datetime'])
                                            ->addDay()
                                            ->format('Y-m-d H:i');
                                    }

                                    if ($newUuid = $component->generateUuid()) {
                                        $newItems[$newUuid] = $item;
                                    } else {
                                        $newItems[] = $item;
                                    }
                                }

                                $component->rawState($newItems);
                                $component->collapsed(false, shouldMakeComponentCollapsible: false);
                                $component->callAfterStateUpdated();
                                $component->partiallyRender();
                            }),
                    )
                    ->defaultItems(0)
                    ->reorderable()
                    ->orderColumn('sort_order')
                    ->schema([
                        TextInput::make('name')
                            ->label('Событие')
                            ->required(),
                        DateTimePicker::make('event_datetime')
                            ->label('Время')
                            ->seconds(false)
                            ->displayFormat('d.m.Y H:i')
                            ->format('Y-m-d H:i'),
                        TextInput::make('description')
                            ->label('Описание'),
                    ])
                    ->itemLabel(fn (array $state, int $index): ?string => (! empty($state['event_datetime']) && ! empty($state['name']))
                        ? ($index + 1).". {$state['event_datetime']} — {$state['name']}"
                        : ($index + 1).'. Новое событие')
                    ->extraItemActions([
                        Action::make('duplicatePlusDay')
                            ->label('Заполнить на следующий день')
                            ->icon('heroicon-m-document-duplicate')
                            ->color('gray')
                            ->action(function (array $arguments, Repeater $component): void {
                                $items = $component->getRawState();
                                $source = $items[$arguments['item']];

                                if (! empty($source['event_datetime'])) {
                                    $source['event_datetime'] = Carbon::parse($source['event_datetime'])
                                        ->addDay()
                                        ->format('Y-m-d H:i');
                                }

                                $newUuid = $component->generateUuid();

                                // Insert the duplicate right after the source item.
                                $newItems = [];
                                foreach ($items as $key => $value) {
                                    $newItems[$key] = $value;
                                    if ($key === $arguments['item']) {
                                        if ($newUuid) {
                                            $newItems[$newUuid] = $source;
                                        } else {
                                            $newItems[] = $source;
                                        }
                                    }
                                }

                                $component->rawState($newItems);
                                $component->collapsed(false, shouldMakeComponentCollapsible: false);
                                $component->callAfterStateUpdated();
                                $component->partiallyRender();
                            }),
                    ])
                    ->columns(3)
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
                            'title' => $doc['title'],
                            'files' => [],
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
                                    $fail('Загрузите следующие обязательные документы: '.implode(', ', $missing).'.');
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
                            ->helperText('Можно загрузить до '.$maxFiles.' файлов'),
                    ]),

                // ── Документы регаты (для отображения пользователям) ──
                Repeater::make('extra_documents')
                    ->label('Документы регаты')
                    ->columnSpanFull()
                    ->addActionLabel('Добавить документ')
                    ->collapsible()
                    ->hidden()
                    ->defaultItems(3)
                    ->itemLabel(fn (array $state): ?string => static::resolveDocumentLabel($state))
                    ->schema([
                        Select::make('doc_type')
                            ->label('Тип')
                            // Тип «Прочее» (other) управляется отдельным полем other_files ниже,
                            // поэтому исключаем его отсюда, чтобы поля не затирали файлы друг друга.
                            ->options(fn () => collect(YachtDocumentType::options())->except('other')->all())
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
                                    ->append('.'.$file->getClientOriginalExtension()),
                            )
                            ->acceptedFileTypes($acceptedTypes)
                            ->maxSize(20480)
                            ->maxFiles($maxFiles)
                            ->downloadable()
                            ->helperText('Можно загрузить до '.$maxFiles.' файлов'),
                    ]),

                // ── Прочие документы: загрузка пачкой (как в галерее) ──
                FileUpload::make('other_files')
                    ->label('Документы регаты')
                    ->helperText('Загрузите файлы пачкой — каждый сохранится как отдельный документ типа «Прочее». Название берётся из имени файла.')
                    ->columnSpanFull()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->panelLayout('grid')
                    ->directory('documents')
                    ->disk('public')
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => (string) Str::of($file->getClientOriginalName())
                            ->beforeLast('.')
                            ->slug()
                            ->append('.'.$file->getClientOriginalExtension()),
                    )
                    ->acceptedFileTypes($acceptedTypes)
                    ->maxSize(20480)
                    ->downloadable(),

                // ── Документы для подачи заявок ──
                Section::make('Документы для заявок')
                    ->description('Документы, которые участник должен приложить при подаче заявки. Если ничего не добавлено — применяются глобальные настройки.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        CheckboxList::make('entry_doc_selected')
                            ->label('Документы')
                            ->options(fn () => YachtDocumentType::cachedConfigurable()
                                ->pluck('label', 'key')
                                ->all())
                            ->descriptions(fn () => YachtDocumentType::cachedConfigurable()
                                ->whereNotNull('description')
                                ->pluck('description', 'key')
                                ->all())
                            ->columns(2)
                            ->bulkToggleable()
                            ->live()
                            ->helperText('Отметьте документы, которые участник должен приложить при подаче заявки.'),
                        CheckboxList::make('entry_doc_required')
                            ->label('Обязательные к загрузке')
                            ->options(fn (Get $get) => YachtDocumentType::cachedConfigurable()
                                ->whereIn('key', (array) $get('entry_doc_selected'))
                                ->pluck('label', 'key')
                                ->all())
                            ->columns(2)
                            ->bulkToggleable()
                            ->visible(fn (Get $get): bool => filled($get('entry_doc_selected')))
                            ->helperText('Отметьте, какие из выбранных документов обязательны к загрузке. Остальные участник прикладывает по желанию.'),
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
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()->sortable()->toggleable(),
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
                // external_id нужен только для сопоставления с судейской программой
                TextColumn::make('external_id')
                    ->label('ID (внешний)')
                    ->copyable()
                    ->sortable()->toggleable()
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('external_id', $search))
                    ->visible(fn (): bool => (bool) auth()->user()?->isDeveloperAdmin()),
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
                            ->native(false)
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
                SelectFilter::make('type')
                    ->label('Тип соревнования')
                    ->options(RegattaType::options()),
                SelectFilter::make('regatta_status')
                    ->label('Статус')
                    ->options([
                        RegattaStatus::Upcoming->value => RegattaStatus::Upcoming->getLabel(),
                        RegattaStatus::Postponed->value => RegattaStatus::Postponed->getLabel(),
                        RegattaStatus::Cancelled->value => RegattaStatus::Cancelled->getLabel(),
                        RegattaStatus::Finished->value => RegattaStatus::Finished->getLabel(),
                    ]),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->deferFilters(false)
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать регату')
                    ->mountUsing(function (Schema $form, Regatta $record): void {
                        $sync = app(SyncDocumentFilesAction::class);
                        $requiredDocs = ManageRegattas::getRequiredDocuments();
                        $requiredDocTypes = array_column($requiredDocs, 'doc_type');

                        $data = $record->toArray();
                        $data['required_documents'] = $sync->load($record, $requiredDocs);
                        // Тип «Прочее» (other) выводится в отдельном поле other_files, а не в Repeater.
                        $data['extra_documents'] = $sync->loadExtra($record, array_merge($requiredDocTypes, ['other']));
                        $data['other_files'] = $sync->loadFlat($record, 'other');

                        $data['is_postponed'] = ($data['regatta_status'] ?? null) === RegattaStatus::Postponed->value;
                        $data['is_cancelled'] = ($data['regatta_status'] ?? null) === RegattaStatus::Cancelled->value;

                        $entryDocs = static::splitEntryRequiredDocuments($record->entry_required_documents);
                        $data['entry_doc_selected'] = $entryDocs['selected'];
                        $data['entry_doc_required'] = $entryDocs['required'];

                        $form->fill($data);
                    })
                    ->using(function (Regatta $record, array $data): Regatta {
                        $requiredDocs = $data['required_documents'] ?? [];
                        $extraDocs = $data['extra_documents'] ?? [];
                        $otherFiles = $data['other_files'] ?? [];
                        $data['entry_required_documents'] = static::assembleEntryRequiredDocuments(
                            $data['entry_doc_selected'] ?? [],
                            $data['entry_doc_required'] ?? [],
                        );
                        unset($data['required_documents'], $data['extra_documents'], $data['other_files'], $data['is_postponed'], $data['is_cancelled'], $data['entry_doc_selected'], $data['entry_doc_required']);

                        $postponedToDate = $data['postponed_to_date'] ?? null;
                        $newStatus = $data['regatta_status'] ?? null;

                        // Если статус postponed и указана дата — вызываем RegattaService
                        if (static::statusEquals($newStatus, RegattaStatus::Postponed) && $postponedToDate) {
                            $service = app(RegattaService::class);
                            $service->postpone($record, Carbon::parse($postponedToDate));

                            $sync = app(SyncDocumentFilesAction::class);
                            $sync->execute($record, $requiredDocs);
                            $sync->execute($record, $extraDocs);
                            $sync->executeFlat($record, 'other', $otherFiles);

                            $requiredDocTypes = array_column(ManageRegattas::getRequiredDocuments(), 'doc_type');
                            $activeExtraTypes = array_merge(array_filter(array_column($extraDocs, 'doc_type')), ['other']);
                            $sync->pruneOrphanedDocTypes($record, $requiredDocTypes, $activeExtraTypes);

                            return $record->fresh();
                        }

                        $record->update($data);

                        $sync = app(SyncDocumentFilesAction::class);
                        $sync->execute($record, $requiredDocs);
                        $sync->execute($record, $extraDocs);
                        $sync->executeFlat($record, 'other', $otherFiles);

                        $requiredDocTypes = array_column(ManageRegattas::getRequiredDocuments(), 'doc_type');
                        $activeExtraTypes = array_merge(array_filter(array_column($extraDocs, 'doc_type')), ['other']);
                        $sync->pruneOrphanedDocTypes($record, $requiredDocTypes, $activeExtraTypes);

                        return $record;
                    }),
                Action::make('replicate')
                    ->label('Дублировать')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Дублировать регату')
                    ->modalDescription('Будет создана копия регаты с расписанием и документами. После создания откроется окно редактирования.')
                    ->modalSubmitActionLabel('Дублировать')
                    ->action(function (Regatta $record, $livewire): void {
                        $replica = app(ReplicateRegattaAction::class)->execute($record);

                        // Открываем окно редактирования созданной копии.
                        // Контекст table+recordKey нужен, чтобы Filament резолвил
                        // именно табличный record-экшен, а не экшен страницы.
                        $livewire->replaceMountedAction('edit', context: [
                            'table' => true,
                            'recordKey' => $replica->getKey(),
                        ]);
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

    public static function getEloquentQuery(): Builder
    {
        return static::scopeToOwnedRegattas(parent::getEloquentQuery());
    }

    /**
     * Скрывать ли работу с сериями: они глобальны и роли «Админ-разработчик»
     * недоступны для управления.
     */
    public static function hidesSeries(): bool
    {
        return (bool) auth()->user()?->isDeveloperAdmin();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        // visibleForUser обязателен: без него чужая регата открывается
        // по прямой ссылке ?tableAction=edit&tableActionRecord=<uuid>.
        return static::scopeToOwnedRegattas(
            parent::getRecordRouteBindingEloquentQuery()
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
        );
    }

    /**
     * Сравнивает значение статуса (строка или enum) с целевым enum-кейсом.
     */
    public static function statusEquals(mixed $status, RegattaStatus $target): bool
    {
        if ($status instanceof RegattaStatus) {
            return $status === $target;
        }

        return is_string($status) && $status === $target->value;
    }

    /**
     * Разбирает хранимый список документов заявки на два набора ключей
     * для двух CheckboxList: выбранные документы и обязательные к загрузке.
     *
     * Поддерживает старый плоский формат ['key1', 'key2'] (все обязательные)
     * и новый [{doc_type, is_required}].
     *
     * @return array{selected: array<int, string>, required: array<int, string>}
     */
    public static function splitEntryRequiredDocuments(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return ['selected' => [], 'required' => []];
        }

        // Старый плоский формат: все выбранные считаются обязательными.
        if (isset($raw[0]) && is_string($raw[0])) {
            $keys = array_values(array_filter($raw, 'is_string'));

            return ['selected' => $keys, 'required' => $keys];
        }

        $selected = [];
        $required = [];
        foreach ($raw as $item) {
            $key = $item['doc_type'] ?? null;
            if (! is_string($key) || $key === '') {
                continue;
            }
            $selected[] = $key;
            if ($item['is_required'] ?? false) {
                $required[] = $key;
            }
        }

        return ['selected' => $selected, 'required' => $required];
    }

    /**
     * Собирает хранимый формат [{doc_type, is_required}] из двух наборов ключей.
     *
     * @return array<int, array{doc_type: string, is_required: bool}>
     */
    public static function assembleEntryRequiredDocuments(mixed $selected, mixed $required): array
    {
        $selectedKeys = array_values(array_unique(array_filter((array) $selected, 'is_string')));
        $requiredKeys = array_filter((array) $required, 'is_string');

        return array_map(
            fn (string $key): array => [
                'doc_type' => $key,
                'is_required' => in_array($key, $requiredKeys, true),
            ],
            $selectedKeys,
        );
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

        $model = YachtDocumentType::cachedAll()
            ->first(fn (YachtDocumentType $t) => $t->key === $docType);

        return $model?->label ?? ($state['title'] ?? null);
    }
}
