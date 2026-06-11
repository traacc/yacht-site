<?php

namespace App\Filament\Resources\RegattaResults;

use App\Actions\RegattaResult\ImportRegattaResultItemsAction;
use App\Filament\Resources\RegattaResults\Pages\ManageRegattaResults;
use App\Models\RaceResult;
use App\Models\RegattaEntry;
use App\Models\RegattaEvents;
use App\Models\RegattaResult;
use App\Models\Team;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

use Illuminate\Database\Eloquent\Builder;

class RegattaResultResource extends Resource
{
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
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('date_end', 'desc'),
                    )
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        // Перезагружаем блок «Гонки регаты» под выбранную регату —
                        // relationship-репитер сам по себе не реактивен.
                        $races = [];
                        foreach (self::raceEventsForRegatta($state) as $event) {
                            $races[(string) $event->getKey()] = [
                                'name'           => $event->name,
                                'event_datetime' => $event->event_datetime?->format('Y-m-d H:i:s'),
                            ];
                        }
                        $set('regattaRaces', $races);
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

                self::racesManagerSchema()
                    ->columnSpanFull(),

                Repeater::make('items')
                    ->label('Результаты участников')
                    ->relationship('items')
                    ->hintAction(self::fillItemsFromEntriesAction())
                    ->schema([
                        Select::make('team_id')
                            ->label('Команда')
                            ->relationship('team', 'name')
                            ->required()
                            ->columnSpan(2),

                        Select::make('yacht_id')
                            ->label('Яхта')
                            ->relationship('yacht', 'name')
                            ->nullable()
                            ->columnSpan(2),

                        TextInput::make('total_points')
                            ->label('Очки')
                            ->numeric()
                            ->required()
                            ->default(0.0),

                        TextInput::make('final_position')
                            ->label('Место')
                            ->numeric()
                            ->nullable(),
                    ])
                    ->columns(6)
                    ->addActionLabel('Добавить участника')
                    ->columnSpanFull(),
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

                $entries = RegattaEntry::query()
                    ->where('regatta_id', $regattaId)
                    ->whereNotIn('status', ['rejected', 'withdrawn'])
                    ->get();

                // Сбрасываем текущие строки и заполняем заново по заявкам.
                $items = [];
                foreach ($entries as $entry) {
                    $items[(string) Str::uuid()] = [
                        'team_id'        => $entry->team_id,
                        'yacht_id'       => $entry->yacht_id,
                        'total_points'   => 0.0,
                        'final_position' => null,
                    ];
                }

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
            TableColumn::make('Команда'),
            TableColumn::make('Яхта')->markAsRequired(false),
            TableColumn::make('Участники')->markAsRequired(false),
            TableColumn::make('Очки'),
            TableColumn::make('Место')->markAsRequired(false),
        ];
        foreach ($raceEvents as $race) {
            $columns[] = TableColumn::make($race->name . ' · место')->markAsRequired(false);
            $columns[] = TableColumn::make($race->name . ' · очки')->markAsRequired(false);
        }

        $fields = [
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

            Select::make('yacht_id')
                ->label('Яхта')
                ->relationship('yacht', 'name')
                ->searchable()
                ->preload()
                ->nullable(),

            Placeholder::make('crew_list')
                ->label('Участники')
                ->content(fn (Get $get): HtmlString | string => self::crewListFor(
                    $regattaId,
                    $get('team_id'),
                    $get('yacht_id'),
                )),

            TextInput::make('total_points')
                ->label('Очки')
                ->numeric()
                ->required()
                ->default(0.0),

            TextInput::make('final_position')
                ->label('Место')
                ->numeric()
                ->nullable(),
        ];
        foreach ($raceEvents as $i => $race) {
            $fields[] = TextInput::make("race_{$i}_position")
                ->label($race->name . ' · место')
                ->numeric()
                ->nullable();
            $fields[] = TextInput::make("race_{$i}_points")
                ->label($race->name . ' · очки')
                ->numeric()
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
                            ->numeric(),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('regatta.season.year')
                    ->label('Сезон')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regatta.name')
                    ->label('Регата')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('regatta.dateRange')
                    ->label('Дата регаты')->getStateUsing(fn ($record) => $record->regatta?->dateRange()),
                TextColumn::make('result_type')
                    ->label('Тип результатов')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'preliminary' => 'Предварительный',
                        'final'       => 'Финальный',
                        default       => $state,
                    }),
                TextColumn::make('source')
                    ->label('Формат')
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'manual'   => 'Вручную',
                        'imported' => 'Импортирован',
                        default    => $state,
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->modalHeading('Редактировать результат регаты'),
                EditAction::make('edit_table')
                    ->label('Редактировать таблицей')
                    ->icon(Heroicon::TableCells)
                    ->modalHeading('Редактировать результат регаты (таблица)')
                    ->modalWidth('screen')
                    ->schema(fn (RegattaResult $record): array => [
                        self::racesManagerSchema(),
                        // Фиксируем соответствие колонок гонок их id (для корректного сохранения).
                        Hidden::make('race_event_ids')
                            ->dehydrated(true)
                            ->formatStateUsing(fn (): string => self::raceEventsFor($record)->pluck('id')->implode(',')),
                        self::itemsTableSchema($record),
                    ])
                    ->after(fn (RegattaResult $record, array $data) => self::saveRaceResults($record, $data)),
                Action::make('import_csv')
                    ->label('Импорт CSV')
                    ->icon(Heroicon::ArrowUpTray)
                    ->color('success')
                    ->form([
                        FileUpload::make('csv_file')
                            ->label('CSV-файл')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->disk('local')
                            ->directory('csv-imports')
                            ->required(),
                        Checkbox::make('replace')
                            ->label('Заменить существующие записи')
                            ->default(false)
                            ->helperText('Если включено — все текущие items будут удалены перед импортом'),
                    ])
                    ->action(function (RegattaResult $record, array $data): void {
                        $path    = Storage::disk('local')->path($data['csv_file']);
                        $content = file_get_contents($path);
                        Storage::disk('local')->delete($data['csv_file']);

                        try {
                            $result = app(ImportRegattaResultItemsAction::class)
                                ->execute($record, $content, (bool) ($data['replace'] ?? false));
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Ошибка импорта')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                            return;
                        }

                        $body = "Импортировано: {$result['imported']}, пропущено: {$result['skipped']}";
                        if (! empty($result['errors'])) {
                            $body .= "\n\nОшибки:\n" . implode("\n", $result['errors']);
                        }

                        Notification::make()
                            ->title('Импорт завершён')
                            ->body($body)
                            ->when(empty($result['errors']), fn($n) => $n->success())
                            ->when(! empty($result['errors']), fn($n) => $n->warning())
                            ->send();
                    }),
                DeleteAction::make(),
                Action::make('export_csv')
                    ->label('Экспорт CSV')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('gray')
                    ->action(function (RegattaResult $record): StreamedResponse {
                        $filename = sprintf(
                            'result_%s_%s.csv',
                            str($record->regatta?->name ?? $record->id)->slug(),
                            now()->format('Y-m-d'),
                        );

                        return response()->streamDownload(function () use ($record): void {
                            $handle = fopen('php://output', 'w');
                            fputs($handle, "\xEF\xBB\xBF"); // BOM для Excel
                            fputcsv($handle, ['Место', 'Команда', 'Яхта', 'Очки'], ';');

                            foreach ($record->items as $item) {
                                fputcsv($handle, [
                                    $item->final_position ?? '',
                                    $item->team?->name ?? '',
                                    $item->yacht?->name ?? '',
                                    $item->total_points,
                                ], ';');
                            }

                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                    }),
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
