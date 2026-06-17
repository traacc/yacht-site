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
use App\Models\Team;
use App\Models\Yacht;
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
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
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

                        TextInput::make('total_points')
                            ->label('Очки')
                            //->numeric()
                            ->required()
                            ->default(0.0),

                        TextInput::make('final_position')
                            ->label('Место')
                            ->rule(static function () {
                                return static function (string $attribute, $value, \Closure $fail): void {
                                    $value = trim((string) $value);

                                    if ($value === '0' || str_contains($value, '-')) {
                                        $fail('Недопустимое значение');
                                    }
                                };
                            })
                            ->nullable(),
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
            ->where('event_type', 'race')
            ->orderBy('event_datetime')
            ->orderBy('name')
            ->get()
            ->values();
    }

    /**
     * Управление гонками регаты (добавить / изменить / удалить) прямо из окна таблицы.
     * Сохраняется через relationship regattaRaces (RegattaEvents типа race).
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
                    // Новые события создаём именно как гонку (по умолчанию в БД — schedule).
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        $data['event_type'] = 'race';

                        return $data;
                    })
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
            ->map(function ($crew): string {
                $user = $crew->teamMember?->user;
                $name = $user?->name;
                $name = $name !== '' ? $name : '—';

                $role = match ($crew->role) {
                    'captain' => ' (кап)',
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

        $columns = [
            TableColumn::make('Яхта')->markAsRequired(false),
            TableColumn::make('Команда'),
            //TableColumn::make('Не участвовали')->markAsRequired(false),
            TableColumn::make('Участники')->markAsRequired(false),
            TableColumn::make('Очки'),
            TableColumn::make('Место')->markAsRequired(false),
        ];
        foreach ($raceEvents as $race) {
            $columns[] = TableColumn::make($race->name . ' · место')->markAsRequired(false);
            $columns[] = TableColumn::make($race->name . ' · очки')->markAsRequired(false);
        }

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

            TextInput::make('total_points')
                ->label('Очки')
                //->numeric()
                ->required()
                ->default(0.0),

            TextInput::make('final_position')
                ->label('Место')
                ->rule(static function () {
                    return static function (string $attribute, $value, \Closure $fail): void {
                        $value = trim((string) $value);

                        if ($value === '0' || str_contains($value, '-')) {
                            $fail('Недопустимое значение');
                        }
                    };
                })
                ->nullable(),
        ];
        foreach ($raceEvents as $i => $race) {
            $fields[] = TextInput::make("race_{$i}_position")
                ->label($race->name . ' · место')
                //->numeric()
                // Запрещаем одиночный ноль и любой минус.
                ->rule(static function () {
                    return static function (string $attribute, $value, \Closure $fail): void {
                        $value = trim((string) $value);

                        if ($value === '0' || str_contains($value, '-')) {
                            $fail('Недопустимое значение');
                        }
                    };
                })
                ->nullable();
            $fields[] = TextInput::make("race_{$i}_points")
                ->label($race->name . ' · очки')
                //->numeric()
                ->nullable();
        }

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
                        // Фиксируем соответствие колонок гонок их id (для корректного сохранения).
                        Hidden::make('race_event_ids')
                            ->dehydrated(true)
                            ->formatStateUsing(fn (): string => self::raceEventsFor($record)->pluck('id')->implode(',')),
                        self::itemsTableSchema($record),
                    ])
                    ->after(fn (RegattaResult $record, array $data) => self::saveRaceResults($record, $data)),
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
