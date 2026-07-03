<?php

namespace App\Filament\Resources\RegattaResults;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaResult\ImportRegattaResultItemsAction;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Filament\Resources\RegattaResults\Pages\ManageRegattaResults;
use App\Models\RaceResult;
use App\Models\RegattaEntry;
use App\Models\RegattaEvents;
use App\Models\RegattaResult;
use App\Models\RegattaResultItem;
use App\Models\Team;
use App\Models\Yacht;
use App\Services\RatingCalculator;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Illuminate\Database\Eloquent\Builder;

class RegattaResultResource extends Resource
{
    use \App\Filament\Concerns\RestrictsAccessByRole;

    protected static ?string $model = RegattaResult::class;

    protected static string|BackedEnum|null $navigationIcon = 'cup';

    protected static ?int $navigationSort = 2;


    public static function getModelLabel(): string
    {
        return 'Результат регаты';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Результаты регат';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('regatta_id')
                    ->label('Регата')
                    ->relationship(
                        name: 'regatta',
                        titleAttribute:'name',
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('date_end', 'asc'),
                    )
                    
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        // Перезагружаем блок «Гонки регаты» под выбранную регату —
                        // relationship-репитер сам по себе не реактивен.
                        // Ключ ОБЯЗАН быть в формате "record-{id}" — именно так Filament
                        // помечает существующие записи relationship-репитера (см.
                        // Repeater::getCachedExistingRecords). С сырым id гонки считаются
                        // новыми и при сохранении дублируются.
                        $races = [];
                        foreach (self::raceEventsForRegatta($state) as $event) {
                            $races["record-{$event->getKey()}"] = [
                                'name'           => $event->name,
                                'event_datetime' => $event->event_datetime?->format('Y-m-d H:i:s'),
                            ];
                        }
                        $set('regattaRaces', $races);

                        // Заполняем список участников по активным заявкам на регату.
                        $set('items', self::itemsFromEntries($state));
                    })
                    ->columnSpanFull(),

                Select::make('result_type')
                    ->label('Тип результата')
                    ->options([
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                    ])
                    ->required()
                    ->default('preliminary'),

                FileUpload::make('pdf_path')
                    ->label('PDF файл')
                    ->directory('documents')
                    ->disk('public')
                    ->preserveFilenames(),

                Select::make('source')
                    ->label('Источник')
                    ->options([
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                    ])
                    ->required()
                    ->default('manual'),

                Actions::make([
                    self::createEntryAction(),
                ])->columnSpanFull(),

                self::racesManagerSchema()
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('Результаты участников')
                    ->relationship('items')
                    ->hintAction(self::fillItemsFromEntriesAction())

                    ->extraAttributes(['class' => 'regatta-result-list'])

                    ->schema([
                        Select::make('yacht_id')
                            ->label('Яхта')
                            // Только яхты из активных заявок на выбранную регату.
                            ->options(fn (Get $get): array => self::entryYachtOptions($get('../../regatta_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                // Команда подставляется автоматически по заявке выбранной яхты.
                                $regattaId = $get('../../regatta_id');

                                if (blank($state) || blank($regattaId)) {
                                    return;
                                }

                                $teamId = RegattaEntry::query()
                                    ->where('regatta_id', $regattaId)
                                    ->where('yacht_id', $state)
                                    ->whereNotIn('status', ['rejected', 'withdrawn'])
                                    ->value('team_id');

                                if (filled($teamId)) {
                                    $set('team_id', $teamId);
                                }
                            })
                            ->disabled()
                            ->dehydrated()
                            ->nullable()
                            ->columnSpan(2),

                        Select::make('team_id')
                            ->label('Команда')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(2),

                        /*
                        TextInput::make('not_participate')
                            ->label('Не участвовали'),
                        */

                        // TextInput::make('total_points')
                        //     ->label('Очки')
                        //     //->numeric()
                        //     ->required()
                        //     ->default(0.0),

                        // TextInput::make('final_position')
                        //     ->label('Место')
                        //     ->rule(static function () {
                        //         return static function (string $attribute, $value, \Closure $fail): void {
                        //             $value = trim((string) $value);

                        //             if ($value === '0' || str_contains($value, '-')) {
                        //                 $fail('Недопустимое значение');
                        //             }
                        //         };
                        //     })
                        //     ->nullable(),
                    ])
                    ->defaultItems(0)
                    ->columns(6)
                    ->addActionLabel('Добавить участника')
                    ->columnSpanFull(),
            ])->extraAttributes([
            // Этот атрибут заставит блок становиться полупрозрачным во время сетевых запросов формы
            'wire:loading.class' => 'opacity-50 pointer-events-none transition-opacity duration-200'
        ]);
    }

    /**
     * Кнопка «Заполнить по заявкам»: полностью пересоздаёт список участников
     * по заявкам (RegattaEntry) на выбранную регату. Текущие строки сбрасываются.
     * Берём активные заявки (не отклонённые и не отозванные).
     */
    protected static function fillItemsFromEntriesAction(): Action
    {
        return Action::make('fillFromEntries')
            ->label('Заполнить по заявкам')
            ->icon(Heroicon::UserGroup)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Заполнить по заявкам')
            ->modalDescription('Текущий список участников будет очищен и заполнен заново по заявкам на регату.')
            ->action(function (Get $get, Set $set): void {
                $regattaId = $get('regatta_id');

                if (blank($regattaId)) {
                    Notification::make()
                        ->title('Сначала выберите регату')
                        ->warning()
                        ->send();

                    return;
                }

                // Сбрасываем текущие строки и заполняем заново по заявкам.
                $items = self::itemsFromEntries($regattaId);

                $set('items', $items);

                Notification::make()
                    ->title($items !== []
                        ? 'Добавлено участников: ' . count($items)
                        : 'Активных заявок на эту регату нет')
                    ->success()
                    ->send();
            });
    }

    /**
     * Кнопка «Добавить заявку»: открывает окно с полной формой заявки
     * (RegattaEntry) — экипаж и документы. Регата подставляется из текущей
     * формы результата. Создание выполняется тем же путём, что и на странице
     * заявок (синхронизация экипажа и документов).
     *
     * Если регата известна заранее (например, в таблице редактирования по
     * записи), её id передаётся явно; иначе берётся из поля основной формы.
     */
    protected static function createEntryAction(?string $regattaId = null): Action
    {
        return Action::make('createEntry')
            ->label('Добавить заявку')
            ->icon(Heroicon::PlusCircle)
            ->color('primary')
            ->modalHeading('Новая заявка на регату')
            ->modalWidth('4xl')
            ->modalSubmitActionLabel('Создать')
            // Форма заявки использует relationship-поля (team/yacht/regatta),
            // поэтому модель формы экшена — RegattaEntry, а не RegattaResult.
            ->model(RegattaEntry::class)
            ->schema(fn (Schema $schema): Schema => RegattaEntryResource::form($schema))
            // Подставляем регату: явно переданную или из поля основной формы.
            // fillForm с явными данными не применяет default() компонентов,
            // поэтому набор документов формируем здесь же — иначе обязательные
            // документы требуются валидацией, но в форме нет полей загрузки.
            ->fillForm(function (Get $get) use ($regattaId): array {
                $resolvedRegattaId = $regattaId ?? $get('regatta_id');

                return [
                    'regatta_id'         => $resolvedRegattaId,
                    'required_documents' => RegattaEntryResource::defaultRequiredDocuments($resolvedRegattaId),
                ];
            })
            ->action(function (array $data): void {
                $requiredDocs = $data['required_documents'] ?? [];
                $crew         = $data['crew'] ?? [];
                unset($data['required_documents'], $data['crew']);

                // Проверка дубликата: та же команда уже подала заявку на эту регату.
                $conflict = RegattaEntry::query()
                    ->where('regatta_id', $data['regatta_id'])
                    ->where('team_id', $data['team_id'])
                    ->first();

                if ($conflict) {
                    Notification::make()
                        ->title('Заявка уже существует')
                        ->body('Эта команда уже подала заявку на эту регату.')
                        ->danger()
                        ->send();

                    return;
                }

                $record = RegattaEntry::create($data);

                app(SyncDocumentFilesAction::class)->execute($record, $requiredDocs);
                RegattaEntryResource::syncCrew($record, $crew);

                Notification::make()
                    ->title('Заявка создана')
                    ->success()
                    ->send();
            });
    }

    /**
     * Строки участников по активным заявкам (RegattaEntry) на регату.
     * Берём активные заявки (не отклонённые и не отозванные).
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function itemsFromEntries(?string $regattaId): array
    {
        if (blank($regattaId)) {
            return [];
        }

        $entries = RegattaEntry::query()
            ->where('regatta_id', $regattaId)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->get();

        $items = [];
        foreach ($entries as $entry) {
            $items[(string) Str::uuid()] = [
                'team_id'        => $entry->team_id,
                'yacht_id'       => $entry->yacht_id,
                'total_points'   => 0.0,
                'final_position' => null,
            ];
        }

        return $items;
    }

    /**
     * Яхты из активных заявок (RegattaEntry) на регату — для выбора в результатах.
     *
     * @return array<string, string>  [yacht_id => name]
     */
    protected static function entryYachtOptions(?string $regattaId): array
    {
        if (blank($regattaId)) {
            return [];
        }

        $yachtIds = RegattaEntry::query()
            ->where('regatta_id', $regattaId)
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->whereNotNull('yacht_id')
            ->pluck('yacht_id')
            ->unique();

        if ($yachtIds->isEmpty()) {
            return [];
        }

        return Yacht::query()
            ->whereIn('id', $yachtIds)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Yacht $yacht): array => [
                $yacht->id => trim(($yacht->name ?? '') . ($yacht->vfps_number ? " ({$yacht->vfps_number})" : '')),
            ])
            ->all();
    }

    /**
     * Гонки (события типа race) регаты по порядку.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\RegattaEvents>
     */
    protected static function raceEventsFor(?RegattaResult $record): Collection
    {
        return self::raceEventsForRegatta($record?->regatta_id);
    }

    /**
     * Гонки (события типа race) указанной регаты по порядку.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\RegattaEvents>
     */
    protected static function raceEventsForRegatta(?string $regattaId): Collection
    {
        if (blank($regattaId)) {
            return collect();
        }

        return RegattaEvents::query()
            ->where('regatta_id', $regattaId)
            ->orderBy('event_datetime')
            ->orderBy('name')
            ->get()
            ->values();
    }

    /**
     * Управление гонками регаты (добавить / изменить / удалить) прямо из окна таблицы.
     * Сохраняется через relationship regattaRaces (RegattaEvents).
     */
    public static function racesManagerSchema(): Section
    {
        return Section::make('Гонки регаты')
            ->description('Колонки с результатами обновятся после повторного открытия таблицы.')
            ->collapsible()
            ->collapsed()
            ->schema([
                Repeater::make('regattaRaces')
                    ->hiddenLabel()
                    ->relationship('regattaRaces')
                    ->table([
                        TableColumn::make('Название'),
                        TableColumn::make('Дата и время')->markAsRequired(false),
                    ])
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required(),

                        DateTimePicker::make('event_datetime')
                            ->label('Дата и время')
                            ->seconds(false)
                            ->nullable(),
                    ])
                    // На создании результата кэш «существующих гонок» строится во время
                    // валидации, когда у новой записи ещё нет regatta_id (он проставляется
                    // только после создания). С пустым кэшом Filament не находит уже
                    // существующие гонки регаты и плодит дубли. Сбрасываем кэш перед
                    // сохранением, чтобы он пересобрался по сохранённой записи и
                    // сопоставил гонки по ключам record-{id}.
                    ->saveRelationshipsUsing(function (Repeater $component): void {
                        $component->clearCachedExistingRecords();
                        $component->saveToRelationship();
                    })
                    ->defaultItems(0)
                    ->addActionLabel('Добавить гонку')
                    ->deleteAction(fn ($action) => $action->requiresConfirmation())
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Список участников (экипаж заявки) команды для отображения в таблице.
     */
    protected static function crewListFor(?string $regattaId, ?string $teamId, ?string $yachtId): HtmlString | string
    {
        if (blank($regattaId) || blank($teamId)) {
            return '—';
        }

        $entry = RegattaEntry::query()
            ->where('regatta_id', $regattaId)
            ->where('team_id', $teamId)
            ->when(filled($yachtId), fn ($q) => $q->where('yacht_id', $yachtId))
            ->first();

        if (! $entry) {
            return '—';
        }

        $rows = $entry->crew()
            ->with('teamMember.user')
            ->get()
            ->sortByDesc(fn ($crew): int => $crew->role === 'captain' ? 1 : 0)
            ->map(function ($crew): string {
                $user = $crew->teamMember?->user;
                $name = $user?->name;
                $name = $name !== '' ? $name : '—';

                $role = match ($crew->role) {
                    'captain' => ' (рулевой)',
                    'reserve' => ' (зап)',
                    default   => '',
                };

                return e($name) . $role;
            })
            ->implode('<br>');

        return $rows !== '' ? new HtmlString($rows) : '—';
    }

    /**
     * Альтернативный вариант редактирования участников — компактная таблица.
     * Для каждой гонки регаты добавляются колонки «место» и «очки» — они
     * сохраняются в race_results через заявку команды (RegattaEntry).
     */
    public static function itemsTableSchema(RegattaResult $record): Repeater
    {
        $regattaId  = $record->regatta_id;
        $raceEvents = self::raceEventsFor($record);

        // Признак «поле заблокировано»: тумблер «Заблокировать заполненные поля»
        // вверху формы включён И в поле уже есть значение. Пустые поля остаются
        // доступными — можно дозаполнять новые результаты, не рискуя задеть старые.
        $isLocked = fn (string $field): \Closure =>
            fn (Get $get): bool => (bool) $get('../../lock_filled') && filled($get($field));

        $columns = [
            TableColumn::make('Яхта')->markAsRequired(false),
            TableColumn::make('Команда'),
            //TableColumn::make('Не участвовали')->markAsRequired(false),
            TableColumn::make('Участники')->markAsRequired(false),
        ];
        foreach ($raceEvents as $race) {
            $columns[] = TableColumn::make(new HtmlString(e($race->name) . ' · место<br>очки'))->markAsRequired(false);
        }
        // Итог (место / очки) — в конце, после колонок гонок.
        $columns[] = TableColumn::make(new HtmlString('Место<br>Очки'))->markAsRequired(false);

        $fields = [
            Select::make('yacht_id')
                ->label('Яхта')
                ->relationship('yacht', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => trim(($record->name ?? '') . ($record->vfps_number ? " ({$record->vfps_number})" : '')))
                ->searchable(['name', 'vfps_number'])
                ->preload()
                ->nullable(),
            
            Select::make('team_id')
                ->label('Команда')
                ->relationship('team', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) use ($regattaId): void {
                    if (blank($state) || blank($regattaId)) {
                        return;
                    }

                    $yachtId = RegattaEntry::query()
                        ->where('regatta_id', $regattaId)
                        ->where('team_id', $state)
                        ->value('yacht_id');

                    if (filled($yachtId)) {
                        $set('yacht_id', $yachtId);
                    }
                }),
                /*
                TextInput::make('not_participate')
                    ->label('Не участвовали'),
                */

            Placeholder::make('crew_list')
                ->label('Участники')
                ->content(fn (Get $get): HtmlString | string => self::crewListFor(
                    $regattaId,
                    $get('team_id'),
                    $get('yacht_id'),
                )),
        ];
        foreach ($raceEvents as $i => $race) {
            $fields[] = Group::make([
                TextInput::make("race_{$i}_position")
                    ->label($race->name . ' · место')
                    //->numeric()
                    // Запрещаем одиночный ноль и любой минус, а также одинаковые
                    // места у разных участников в пределах одной гонки.
                    ->rule(function (Get $get) use ($i) {
                        return function (string $attribute, $value, \Closure $fail) use ($get, $i): void {
                            $value = trim((string) $value);

                            if ($value === '0' || str_contains($value, '-')) {
                                $fail('Недопустимое значение');

                                return;
                            }

                            // Уникальность проверяем только для числовых мест —
                            // нечисловые статусы (DNF, DSQ, DNS, прочерк) могут
                            // повторяться у нескольких участников.
                            if ($value === '' || ! is_numeric($value)) {
                                return;
                            }

                            // $get('../../items') — всё состояние репитера (все строки).
                            $occurrences = 0;
                            foreach ($get('../../items') ?? [] as $row) {
                                if (trim((string) ($row["race_{$i}_position"] ?? '')) === $value) {
                                    $occurrences++;
                                }
                            }

                            if ($occurrences > 1) {
                                $fail('Это место уже занято в этой гонке');
                            }
                        };
                    })
                    // Очки подставляем сразу после ввода места по тем же правилам,
                    // что и при сохранении (deriveRacePoints): числовое место → те же
                    // очки, буквенный статус (DNF/DSQ…) → число лодок + 1, скобки —
                    // сброшенная гонка. Существующие очки игнорируем (передаём null),
                    // чтобы правка места пересчитывала очки.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) use ($i): void {
                        // Число лодок — участники с командой (как в saveRaceResults).
                        $boatCount = collect($get('../../items') ?? [])
                            ->filter(fn ($row): bool => filled($row['team_id'] ?? null))
                            ->count();

                        $set("race_{$i}_points", self::deriveRacePoints($state, null, $boatCount));
                    })
                    // Заблокированное поле оставляем dehydrated, чтобы уже введённое
                    // значение не потерялось при сохранении формы.
                    ->disabled($isLocked("race_{$i}_position"))
                    ->dehydrated(true)
                    ->nullable(),
                TextInput::make("race_{$i}_points")
                    ->label($race->name . ' · очки')
                    //->numeric()
                    ->disabled($isLocked("race_{$i}_points"))
                    ->dehydrated(true)
                    ->nullable(),
            ]);
        }

        // Итог (место / очки) — в конце, после полей гонок.
        $fields[] = Group::make([
            // По умолчанию итог считается автоматически из результатов гонок
            // (см. recomputeItemTotals). Если пользователь правит поле вручную —
            // ставим флаг *_overridden, и авторасчёт это значение больше не трогает.
            // Очистка поля снимает флаг и возвращает авторасчёт.
            TextInput::make('final_position')
                ->label('Место')
                // Пометка ручного ввода: жёлтый хинт «Вручную» + рамка, иначе серый «Авто».
                ->hint(fn (Get $get): string => $get('final_position_overridden') ? 'Вручную' : 'Авто')
                ->hintColor(fn (Get $get): string => $get('final_position_overridden') ? 'warning' : 'gray')
                ->extraInputAttributes(fn (Get $get): array => $get('final_position_overridden')
                    ? ['class' => 'ring-1 ring-amber-400']
                    : [])
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set) => $set('final_position_overridden', filled($state)))
                ->suffixAction(self::resetToAutoAction('final_position', 'final_position_overridden'))
                ->disabled($isLocked('final_position'))
                ->rule(function (Get $get) {
                    return function (string $attribute, $value, \Closure $fail) use ($get): void {
                        $value = trim((string) $value);

                        if ($value === '0' || str_contains($value, '-')) {
                            $fail('Недопустимое значение');

                            return;
                        }

                        // Уникальность итогового места проверяем только для мест,
                        // введённых вручную: авторасчёт намеренно допускает ничьи
                        // (равные суммы очков → одно место, 1-2-2-4, см.
                        // recomputeItemTotals). Нечисловые значения не проверяем.
                        if (! $get('final_position_overridden') || $value === '' || ! is_numeric($value)) {
                            return;
                        }

                        // $get('../../items') — всё состояние репитера (все строки).
                        $occurrences = 0;
                        foreach ($get('../../items') ?? [] as $row) {
                            if (trim((string) ($row['final_position'] ?? '')) === $value) {
                                $occurrences++;
                            }
                        }

                        if ($occurrences > 1) {
                            $fail('Это итоговое место уже занято');
                        }
                    };
                })
                ->dehydrated(),

            Hidden::make('final_position_overridden')
                ->dehydrated(),

            TextInput::make('total_points')
                ->label('Очки')
                ->hint(fn (Get $get): string => $get('total_points_overridden') ? 'Вручную' : 'Авто')
                ->hintColor(fn (Get $get): string => $get('total_points_overridden') ? 'warning' : 'gray')
                ->extraInputAttributes(fn (Get $get): array => $get('total_points_overridden')
                    ? ['class' => 'ring-1 ring-amber-400']
                    : [])
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, Set $set) => $set('total_points_overridden', filled($state)))
                ->suffixAction(self::resetToAutoAction('total_points', 'total_points_overridden'))
                ->disabled($isLocked('total_points'))
                // Колонка NOT NULL: relationship-репитер сохраняет строки до пересчёта,
                // поэтому пустое значение приводим к 0 (сумму проставит recomputeItemTotals).
                // Запятую в десятичной дроби приводим к точке (ввод в русской раскладке).
                ->dehydrateStateUsing(fn ($state) => filled($state) ? self::normalizePoints($state) : 0)
                ->dehydrated(),

            Hidden::make('total_points_overridden')
                ->dehydrated(),
        ]);

        return Repeater::make('items')
            ->label('Результаты участников')
            ->relationship('items')
            // Горизонтальный скролл — при многих гонках таблица шире экрана (см. theme.css).
            ->extraAttributes(['class' => 'fi-scrollable-table-repeater'])
            ->table($columns)
            ->schema($fields)
            // ->relationship() ставит dehydrated(false); возвращаем true, чтобы строки
            // (включая виртуальные поля race_*) попали в $data хука ->after() для сохранения
            // результатов гонок. Ключ items не входит в $fillable RegattaResult — игнорируется.
            ->dehydrated(true)
            // Подгружаем существующие результаты гонок в виртуальные поля строки.
            ->mutateRelationshipDataBeforeFillUsing(function (array $data) use ($regattaId, $raceEvents): array {
                $entryId = RegattaEntry::query()
                    ->where('regatta_id', $regattaId)
                    ->where('team_id', $data['team_id'] ?? null)
                    ->when(filled($data['yacht_id'] ?? null), fn ($q) => $q->where('yacht_id', $data['yacht_id']))
                    ->value('id');

                $results = $entryId
                    ? RaceResult::query()->where('regatta_entry_id', $entryId)->get()->keyBy('event_id')
                    : collect();

                foreach ($raceEvents as $i => $race) {
                    $result = $results->get($race->id);
                    $data["race_{$i}_position"] = $result?->position;
                    $data["race_{$i}_points"]   = $result?->points;
                }

                return $data;
            })
            ->defaultItems(0)
            ->addActionLabel('Добавить участника')
            ->columnSpanFull();
    }

    /**
     * Сохраняет виртуальные поля результатов гонок из таблицы в race_results.
     * Команды без заявки (RegattaEntry) пропускаются — выводится уведомление.
     *
     * @param  array<string, mixed>  $data  Данные формы edit_table
     */
    public static function saveRaceResults(RegattaResult $record, array $data): void
    {
        // Точное соответствие индекс колонки → id гонки, зафиксированное при открытии
        // таблицы. Это исключает рассинхрон, если в этом же сохранении гонки
        // добавили/переименовали/переупорядочили в блоке «Гонки регаты».
        $eventIds = array_values(array_filter(
            explode(',', (string) ($data['race_event_ids'] ?? '')),
        ));

        if ($eventIds === []) {
            return;
        }

        // Гонки, которые ещё существуют (могли удалить в блоке «Гонки регаты»).
        $existingEventIds = RegattaEvents::whereIn('id', $eventIds)->pluck('id')->flip();

        // Число лодок — участники с командой. Нужно для очков за буквенные
        // статусы (DNF, DSQ…): такие очки = число лодок + 1.
        $boatCount = collect($data['items'] ?? [])
            ->filter(fn ($row): bool => filled($row['team_id'] ?? null))
            ->count();

        $skipped = [];

        foreach (($data['items'] ?? []) as $row) {
            $teamId  = $row['team_id'] ?? null;
            $yachtId = $row['yacht_id'] ?? null;

            if (blank($teamId)) {
                continue;
            }

            $entry = RegattaEntry::query()
                ->where('regatta_id', $record->regatta_id)
                ->where('team_id', $teamId)
                ->when(filled($yachtId), fn ($q) => $q->where('yacht_id', $yachtId))
                ->first();

            if (! $entry) {
                $skipped[] = Team::whereKey($teamId)->value('name') ?? $teamId;
                continue;
            }

            foreach ($eventIds as $i => $eventId) {
                if (! $existingEventIds->has($eventId)) {
                    continue;
                }

                $position = $row["race_{$i}_position"] ?? null;
                $points   = $row["race_{$i}_points"] ?? null;

                if (blank($position) && blank($points)) {
                    continue;
                }

                // Выводим очки из места по правилам малых очков (см. deriveRacePoints).
                $points = self::deriveRacePoints($position, $points, $boatCount);

                RaceResult::updateOrCreate(
                    ['event_id' => $eventId, 'regatta_entry_id' => $entry->id],
                    [
                        'position' => filled($position) ? $position : null,
                        'points'   => filled($points) ? $points : 0,
                    ],
                );
            }
        }

        if ($skipped !== []) {
            Notification::make()
                ->title('Часть результатов гонок не сохранена')
                ->body('Нет заявки на регату для команд: ' . implode(', ', array_unique($skipped)))
                ->warning()
                ->send();
        }
    }

    /**
     * Пересчитывает итоговые очки и места участников по результатам гонок.
     *
     * Система малых очков без отбросов: total_points — сумма points по всем
     * гонкам регаты, final_position — ранг по возрастанию суммы (равные суммы
     * получают одно и то же место, например 1-2-2-4). Участникам без единого
     * результата гонки место не присваивается (final_position = null), чтобы
     * они не оказались на первом месте с нулём очков.
     *
     * Поля с флагом *_overridden заданы вручную — авторасчёт их не трогает,
     * но ручная сумма по-прежнему участвует в ранжировании остальных.
     */
    public static function recomputeItemTotals(RegattaResult $record): void
    {
        // load (не loadMissing) — нужны свежие значения и флаги после сохранения формы.
        $record->load('items');

        $eventIds = self::raceEventsForRegatta($record->regatta_id)->pluck('id');

        // 1) Сумма очков по каждому участнику + признак участия (есть ли гонки).
        $ranked = collect();

        foreach ($record->items as $item) {
            $entryId = blank($item->team_id) ? null : RegattaEntry::query()
                ->where('regatta_id', $record->regatta_id)
                ->where('team_id', $item->team_id)
                ->when(filled($item->yacht_id), fn ($q) => $q->where('yacht_id', $item->yacht_id))
                ->value('id');

            $results = $entryId
                ? RaceResult::query()
                    ->where('regatta_entry_id', $entryId)
                    ->whereIn('event_id', $eventIds)
                    ->get()
                : collect();

            // В очки гонки можно вписать нечисловое значение (DNF, DSQ, прочерк) —
            // такие результаты в сумму не идут. Ручную сумму не перезаписываем.
            if (! $item->total_points_overridden) {
                $item->total_points = (float) $results
                    ->filter(fn (RaceResult $r) => is_numeric($r->points))
                    ->sum(fn (RaceResult $r) => (float) $r->points);
            }

            if ($results->isNotEmpty()) {
                $ranked->push($item);
            } elseif (! $item->final_position_overridden) {
                $item->final_position = null;
            }
        }

        // 2) Места по возрастанию суммы; равные суммы — одинаковое место.
        // Ручные места оставляем как есть.
        foreach ($ranked as $item) {
            if ($item->final_position_overridden) {
                continue;
            }

            $item->final_position = $ranked
                ->filter(fn (RegattaResultItem $other) => (float) $other->total_points < (float) $item->total_points)
                ->count() + 1;
        }

        foreach ($record->items as $item) {
            $item->save();
        }
    }

    /**
     * Пересчитывает командный и личный рейтинг сезона по результатам регаты.
     * Вызывается после любого изменения результатов (правка таблицей, импорт),
     * чтобы рейтинги всегда соответствовали актуальным местам и очкам.
     */
    public static function recalculateRatings(RegattaResult $record): void
    {
        $record->loadMissing('regatta.season');

        if ($record->regatta?->season === null) {
            return;
        }

        app(RatingCalculator::class)->recalculateAfterRegatta($record->regatta);
    }

    /**
     * Нормализует ввод очков: запятую как десятичный разделитель приводит к точке
     * (привычный ввод в русской раскладке: «2,5» → «2.5»). Нечисловые значения
     * (DNF, DSQ, прочерк) возвращаются без изменений.
     */
    public static function normalizePoints(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $normalized = str_replace(',', '.', trim($value));

        return is_numeric($normalized) ? $normalized : $value;
    }

    /**
     * Выводит очки гонки из места по правилам малых очков:
     *  - очки не введены → очки = числовому месту;
     *  - в месте любые буквы (DNF, DSQ, DNS…) → очки = число лодок + 1;
     *  - место/текст в скобках (сброшенная гонка) → очки тоже в скобках
     *    и не идут в общий зачёт: recomputeItemTotals суммирует только числовые
     *    очки, а «(5)» нечисловое.
     *
     * Введённые вручную очки сохраняются как есть (числовые — округляются до
     * десятых); скобки у очков (как и скобки места) помечают сброшенную гонку.
     */
    public static function deriveRacePoints(mixed $position, mixed $points, int $boatCount): mixed
    {
        $position = trim((string) $position);
        $points   = self::normalizePoints($points);
        $points   = is_string($points) ? trim($points) : $points;

        // Скобки в месте — признак «сброшенной» гонки (результат вне зачёта).
        $isDiscard = str_starts_with($position, '(') && str_ends_with($position, ')');
        $inner     = $isDiscard ? trim(substr($position, 1, -1)) : $position;

        if (filled($points)) {
            // Очки заданы вручную — снимаем внешние скобки, но сам факт скобок
            // тоже считаем признаком сброшенной гонки (наравне со скобками места).
            $value = (string) $points;
            if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
                $value     = trim(substr($value, 1, -1));
                $isDiscard = true;
            }
        } elseif ($inner === '') {
            return null;
        } elseif (is_numeric($inner)) {
            // Очки не введены — берём числовое место.
            $value = $inner;
        } else {
            // Любые буквы (DNF, DSQ…) — очки = число лодок + 1.
            $value = (string) ($boatCount + 1);
        }

        // Числовые очки округляем до десятых; нечисловые (DNF…) — как есть.
        if (is_numeric($value)) {
            $value = (string) round((float) $value, 1);
        }

        return $isDiscard ? "({$value})" : $value;
    }

    /**
     * Кнопка-суффикс «сбросить к авторасчёту»: очищает поле и снимает флаг
     * ручного ввода. Видна только когда поле переопределено вручную.
     * Само авто-значение подставится при сохранении (recomputeItemTotals).
     */
    protected static function resetToAutoAction(string $field, string $flagField): Action
    {
        return Action::make("reset_{$field}")
            ->label('Сбросить к авторасчёту')
            ->icon(Heroicon::ArrowPath)
            ->color('gray')
            ->visible(fn (Get $get): bool => (bool) $get($flagField))
            ->action(function (Set $set) use ($field, $flagField): void {
                $set($field, null);
                $set($flagField, false);
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('regatta.name')
                    ->label('Регата'),
                TextEntry::make('result_type')
                    ->label('Тип')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                        default       => $state,
                    }),
                TextEntry::make('source')
                    ->label('Источник')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                        default    => $state,
                    }),
                TextEntry::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-'),

                RepeatableEntry::make('items')
                    ->label('Результаты участников')
                    ->schema([
                        TextEntry::make('final_position')
                            ->label('Место')
                            ->placeholder('-'),
                        TextEntry::make('team.name')
                            ->label('Команда'),
                        TextEntry::make('yacht.name')
                            ->label('Яхта')
                            ->placeholder('-'),
                        TextEntry::make('total_points')
                            ->label('Очки')
                            //->numeric(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('result_type')
                    ->label('Тип результатов')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                        default       => $state,
                    }),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлён')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->stackedOnMobile()->emptyStateHeading('Записей пока нет')
            ->filters([
                SelectFilter::make('season')
                    ->label('Сезон')
                    ->options(fn (): array => \App\Models\Season::query()
                        ->orderByDesc('year')
                        ->pluck('year', 'id')
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas(
                            'regatta',
                            fn (Builder $q) => $q->where('season_id', $data['value']),
                        );
                    }),
            ])
            ->recordActions([
                EditAction::make()->modalHeading('Редактировать результат регаты'),
                EditAction::make('edit_table')
                    ->label('Редактировать таблицей')
                    ->icon(Heroicon::TableCells)
                    ->modalHeading('Редактировать результат регаты (таблица)')
                    ->modalWidth('screen')
                    ->schema(fn (RegattaResult $record): array => [
                        Actions::make([
                            self::createEntryAction($record->regatta_id),
                        ]),
                        self::racesManagerSchema(),
                        // Защита от случайной правки: при включении все поля таблицы,
                        // где уже есть значение, становятся недоступны для ввода.
                        // Пустые поля остаются доступными для новых результатов.
                        Toggle::make('lock_filled')
                            ->label('Заблокировать заполненные поля')
                            ->helperText('Уже введённые значения нельзя изменить; пустые поля остаются доступными.')
                            ->default(false)
                            ->live()
                            ->dehydrated(false),
                        // Фиксируем соответствие колонок гонок их id (для корректного сохранения).
                        Hidden::make('race_event_ids')
                            ->dehydrated(true)
                            ->formatStateUsing(fn (): string => self::raceEventsFor($record)->pluck('id')->implode(',')),
                        self::itemsTableSchema($record),
                    ])
                    ->after(function (RegattaResult $record, array $data): void {
                        self::saveRaceResults($record, $data);
                        self::recomputeItemTotals($record);
                        self::recalculateRatings($record);
                    }),
                Action::make('publish')
                    ->label('Опубликовать')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn (RegattaResult $record): bool => ! $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (RegattaResult $record): void {
                        $record->update(['is_published' => true]);

                        Notification::make()
                            ->title('Результат опубликован')
                            ->success()
                            ->send();
                    }),
                Action::make('unpublish')
                    ->label('Снять с публикации')
                    ->icon(Heroicon::XCircle)
                    ->color('warning')
                    ->visible(fn (RegattaResult $record): bool => $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (RegattaResult $record): void {
                        $record->update(['is_published' => false]);

                        Notification::make()
                            ->title('Результат снят с публикации')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Actions\BulkAction::make('export_csv_bulk')
                        ->label('Экспорт CSV')
                        ->icon(Heroicon::ArrowDownTray)
                        ->action(function (Collection $records): StreamedResponse {
                            $filename = sprintf('results_export_%s.csv', now()->format('Y-m-d'));

                            return response()->streamDownload(function () use ($records): void {
                                $handle = fopen('php://output', 'w');
                                fputs($handle, "\xEF\xBB\xBF"); // BOM для Excel
                                fputcsv($handle, ['Регата', 'Тип', 'Место', 'Команда', 'Яхта', 'Очки'], ';');

                                foreach ($records as $result) {
                                    $result->load('items.team', 'items.yacht', 'regatta');
                                    $typeName = match ($result->result_type) {
                                        'preliminary' => 'Предварительный',
                                        'final'       => 'Финальный',
                                        default       => $result->result_type,
                                    };

                                    foreach ($result->items as $item) {
                                        fputcsv($handle, [
                                            $result->regatta?->name ?? '',
                                            $typeName,
                                            $item->final_position ?? '',
                                            $item->team?->name ?? '',
                                            $item->yacht?->name ?? '',
                                            $item->total_points,
                                        ], ';');
                                    }
                                }

                                fclose($handle);
                            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                        }),
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
