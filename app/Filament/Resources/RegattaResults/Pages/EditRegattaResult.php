<?php

namespace App\Filament\Resources\RegattaResults\Pages;

use App\Actions\Regatta\SyncRegattaRaceCountAction;
use App\Actions\RegattaResult\GenerateRegattaResultPdfAction;
use App\Actions\RegattaResult\ImportRegattaResultItemsAction;
use App\Actions\RegattaResult\ImportRgdResultItemsAction;
use App\Exports\RegattaResultExport;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaEvents;
use App\Models\RegattaResult;
use App\Services\Rgd\RgdParser;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Отдельная страница редактирования результата регаты.
 *
 * Модалка для этой формы не подходит: таблица ввода результатов содержит по две
 * колонки на каждую гонку регаты и на широких регатах не помещается в диалог.
 * Здесь же собрано всё, что нужно секретарю по ходу регаты: заявки (вкладка),
 * число и названия гонок, импорт и экспорт протоколов.
 */
class EditRegattaResult extends EditRecord
{
    protected static string $resource = RegattaResultResource::class;

    /**
     * Дегидрированные данные формы. EditRecord нигде их не сохраняет, а хук
     * afterSave() их не получает — перехватываем в mutateFormDataBeforeSave(),
     * иначе виртуальные поля таблицы (race_*, race_event_ids) до сохранения
     * результатов гонок не доедут.
     *
     * @var array<string, mixed>
     */
    protected array $savedFormData = [];

    public function getTitle(): string
    {
        return 'Результаты: '.($this->getRecord()->regatta?->name ?? 'регата не выбрана');
    }

    /**
     * Хлебные крошки скрыты: заголовок страницы уже называет регату,
     * а навигация к списку есть в меню панели.
     *
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Заявки — вкладкой рядом с результатами, а не блоком под формой:
     * таблица результатов должна открываться первой и целиком.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Результаты';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->raceCountAction(),
            $this->raceNamesAction(),
            ActionGroup::make([
                $this->importCsvAction(),
                $this->importRgdAction(),
                $this->exportExcelAction(),
                $this->exportPdfAction(),
            ])
                ->label('Импорт / экспорт')
                ->icon(Heroicon::ArrowsUpDown)
                ->button()
                ->color('white'),
            DeleteAction::make(),
        ];
    }

    // ──────────────────────────────────────────────
    // Гонки регаты
    // ──────────────────────────────────────────────

    /**
     * Количество гонок: по умолчанию — сколько уже заведено, а для новой регаты
     * число из её карточки (regattas.races_count). Недостающие гонки создаются
     * с номерами по порядку, лишние удаляются с конца.
     */
    protected function raceCountAction(): Action
    {
        return Action::make('raceCount')
            ->label(fn (): string => 'Гонок: '.$this->raceCount())
            ->icon(Heroicon::Flag)
            ->color('white')
            ->modalHeading('Количество гонок регаты')
            ->modalDescription('Недостающие гонки создаются с номерами по порядку. Лишние удаляются с конца — гонки с уже введёнными результатами сохраняются.')
            ->modalSubmitActionLabel('Применить')
            ->fillForm(fn (): array => ['count' => $this->raceCount() ?: (int) ($this->getRecord()->regatta?->races_count ?? 1)])
            ->schema([
                TextInput::make('count')
                    ->label('Количество гонок')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(50)
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var RegattaResult $record */
                $record = $this->getRecord();

                if ($record->regatta === null) {
                    Notification::make()->title('У результата не выбрана регата')->warning()->send();

                    return;
                }

                $summary = app(SyncRegattaRaceCountAction::class)->execute($record->regatta, (int) $data['count']);

                $body = [];
                if ($summary['created'] > 0) {
                    $body[] = 'создано: '.$summary['created'];
                }
                if ($summary['deleted'] > 0) {
                    $body[] = 'удалено: '.$summary['deleted'];
                }
                if ($summary['kept'] !== []) {
                    $body[] = 'не удалены (есть результаты): '.implode(', ', $summary['kept']);
                }

                Notification::make()
                    ->title('Гонок в регате: '.$this->raceCount())
                    ->body($body === [] ? null : implode('; ', $body))
                    ->when($summary['kept'] === [], fn (Notification $n) => $n->success())
                    ->when($summary['kept'] !== [], fn (Notification $n) => $n->warning())
                    ->send();

                $this->redirectToSelf();
            });
    }

    /**
     * Названия гонок — необязательная правка: по умолчанию гонки называются
     * номерами. Добавление и удаление здесь намеренно недоступны, количеством
     * управляет отдельная кнопка.
     */
    protected function raceNamesAction(): Action
    {
        return Action::make('raceNames')
            ->label('Названия гонок')
            ->icon(Heroicon::PencilSquare)
            ->color('white')
            ->modalHeading('Названия гонок регаты')
            ->modalSubmitActionLabel('Сохранить')
            ->visible(fn (): bool => $this->raceCount() > 0)
            ->fillForm(fn (): array => [
                'races' => $this->races()
                    ->map(fn (RegattaEvents $race): array => [
                        'id' => $race->id,
                        'name' => $race->name,
                        'event_datetime' => $race->event_datetime?->format('Y-m-d H:i:s'),
                    ])
                    ->all(),
            ])
            ->schema([
                Repeater::make('races')
                    ->hiddenLabel()
                    ->table([
                        TableColumn::make('Название'),
                        TableColumn::make('Дата и время')->markAsRequired(false),
                    ])
                    ->schema([
                        Hidden::make('id'),
                        TextInput::make('name')
                            ->label('Название')
                            ->required(),
                        DateTimePicker::make('event_datetime')
                            ->label('Дата и время')
                            ->seconds(false)
                            ->nullable(),
                    ])
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false),
            ])
            ->action(function (array $data): void {
                $races = $this->races()->keyBy('id');
                $updated = 0;

                foreach ($data['races'] ?? [] as $row) {
                    $race = $races->get($row['id'] ?? null);

                    if (! $race) {
                        continue;
                    }

                    $race->fill([
                        'name' => $row['name'],
                        'event_datetime' => $row['event_datetime'] ?: null,
                    ]);

                    if ($race->isDirty()) {
                        $race->save();
                        $updated++;
                    }
                }

                Notification::make()
                    ->title($updated > 0 ? 'Гонок изменено: '.$updated : 'Изменений нет')
                    ->success()
                    ->send();

                if ($updated > 0) {
                    $this->redirectToSelf();
                }
            });
    }

    // ──────────────────────────────────────────────
    // Импорт / экспорт
    // ──────────────────────────────────────────────

    protected function importCsvAction(): Action
    {
        return Action::make('importCsv')
            ->label('Импорт из CSV')
            ->icon(Heroicon::ArrowUpTray)
            ->schema([
                FileUpload::make('csv_file')
                    ->label('CSV-файл')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                    ->disk('local')
                    ->directory('csv-imports')
                    ->required(),

                Checkbox::make('replace')
                    ->label('Заменить существующие строки итогов')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                /** @var RegattaResult $record */
                $record = $this->getRecord();

                $path = Storage::disk('local')->path($data['csv_file']);
                $content = file_get_contents($path);
                Storage::disk('local')->delete($data['csv_file']);

                try {
                    $summary = app(ImportRegattaResultItemsAction::class)
                        ->execute($record, $content, (bool) ($data['replace'] ?? false));
                } catch (\RuntimeException $e) {
                    Notification::make()->title('Ошибка импорта')->body($e->getMessage())->danger()->send();

                    return;
                }

                RegattaResultResource::afterSave($record);

                $body = "Импортировано: {$summary['imported']}, пропущено: {$summary['skipped']}";
                if (! empty($summary['errors'])) {
                    $body .= "\n\nОшибки:\n".implode("\n", $summary['errors']);
                }

                Notification::make()
                    ->title('Импорт завершён')
                    ->body($body)
                    ->when(empty($summary['errors']), fn (Notification $n) => $n->success())
                    ->when(! empty($summary['errors']), fn (Notification $n) => $n->warning())
                    ->send();

                $this->redirectToSelf();
            });
    }

    protected function importRgdAction(): Action
    {
        return Action::make('importRgd')
            ->label('Импорт из RGD')
            ->icon(Heroicon::ArrowUpTray)
            ->schema([
                // Без acceptedFileTypes: у .rgd нет стандартного MIME (finfo отдаёт
                // application/x-wine-extension-ini), поэтому mime-фильтр не давал выбрать
                // и не проходил валидацию. Содержимое проверяет парсер (класс-секции).
                FileUpload::make('rgd_file')
                    ->label('RGD-файл (.rgd)')
                    ->disk('local')
                    ->directory('rgd-imports')
                    ->storeFiles(true)
                    ->live()
                    ->required(),

                // Классы читаем прямо из загруженного файла (Windows-1251 → парсер).
                Select::make('class')
                    ->label('Зачётный класс')
                    ->options(fn (Get $get): array => $this->rgdClasses($get('rgd_file')))
                    ->visible(fn (Get $get): bool => filled($get('rgd_file')))
                    ->required(),

                Checkbox::make('create_missing')
                    ->label('Создавать недостающие яхты и команды')
                    ->default(false),

                Checkbox::make('replace')
                    ->label('Заменить существующие строки итогов')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                /** @var RegattaResult $record */
                $record = $this->getRecord();

                $path = Storage::disk('local')->path($data['rgd_file']);
                $content = file_get_contents($path);
                Storage::disk('local')->delete($data['rgd_file']);

                try {
                    $summary = app(ImportRgdResultItemsAction::class)->execute(
                        $record,
                        $content,
                        $data['class'],
                        (bool) ($data['create_missing'] ?? false),
                        (bool) ($data['replace'] ?? false),
                    );
                } catch (\RuntimeException $e) {
                    Notification::make()->title('Ошибка импорта')->body($e->getMessage())->danger()->send();

                    return;
                }

                RegattaResultResource::afterSave($record);

                $body = "Импортировано строк: {$summary['imported']}, пропущено: {$summary['skipped']}"
                    ."\nСоздано яхт: {$summary['created_yachts']}, команд: {$summary['created_teams']}";
                if (! empty($summary['errors'])) {
                    $body .= "\n\nОшибки:\n".implode("\n", $summary['errors']);
                }

                Notification::make()
                    ->title('Импорт из RGD завершён')
                    ->body($body)
                    ->when(empty($summary['errors']), fn (Notification $n) => $n->success())
                    ->when(! empty($summary['errors']), fn (Notification $n) => $n->warning())
                    ->send();

                $this->redirectToSelf();
            });
    }

    protected function exportExcelAction(): Action
    {
        return Action::make('exportExcel')
            ->label('Экспорт в Excel')
            ->icon(Heroicon::ArrowDownTray)
            ->action(function () {
                /** @var RegattaResult $record */
                $record = $this->getRecord();
                $filename = sprintf('results_%s_%s.xlsx', $record->regatta?->name ?? 'regatta', $record->result_type);

                return (new RegattaResultExport($record))->download($filename);
            });
    }

    protected function exportPdfAction(): Action
    {
        return Action::make('exportPdf')
            ->label('Экспорт в PDF')
            ->icon(Heroicon::ArrowDownTray)
            ->action(fn () => app(GenerateRegattaResultPdfAction::class)->execute($this->getRecord()));
    }

    // ──────────────────────────────────────────────
    // Сохранение
    // ──────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->savedFormData = $data;

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var RegattaResult $record */
        $record = $this->getRecord();

        RegattaResultResource::afterSave($record, $this->savedFormData);

        // Итоговые очки и места пересчитаны в БД уже после дегидрации формы —
        // без перезаполнения страница показывала бы старые значения. Тумблер
        // блокировки не является атрибутом модели и при fill() сбросился бы
        // к default(false) — возвращаем выбор пользователя.
        $lockFilled = $this->data['lock_filled'] ?? false;

        $this->fillForm();

        $this->data['lock_filled'] = $lockFilled;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Гонки регаты по порядку — тому же, в котором идут колонки таблицы.
     *
     * @return Collection<int, RegattaEvents>
     */
    protected function races(): Collection
    {
        $regattaId = $this->getRecord()->regatta_id;

        return blank($regattaId)
            ? collect()
            : SyncRegattaRaceCountAction::orderedRaces($regattaId);
    }

    protected function raceCount(): int
    {
        return $this->races()->count();
    }

    /**
     * Колонки таблицы результатов строятся при открытии страницы, поэтому после
     * изменения состава гонок или строк итогов страницу нужно загрузить заново —
     * перезаполнения формы (fillForm) здесь недостаточно.
     */
    protected function redirectToSelf(): void
    {
        $this->redirect(RegattaResultResource::getUrl('edit', ['record' => $this->getRecord()]));
    }

    /**
     * Список зачётных классов из загруженного RGD (для реактивного селекта).
     * Состояние FileUpload — либо временный файл (до отправки), либо путь на диске.
     *
     * @return array<string, string>
     */
    protected function rgdClasses(mixed $state): array
    {
        if (blank($state)) {
            return [];
        }

        $file = is_array($state) ? reset($state) : $state;
        $content = null;

        if (is_object($file) && method_exists($file, 'get')) {
            $content = $file->get();                       // TemporaryUploadedFile
        } elseif (is_string($file) && Storage::disk('local')->exists($file)) {
            $content = Storage::disk('local')->get($file); // уже сохранён на диск
        }

        if ($content === null) {
            return [];
        }

        $classes = app(RgdParser::class)->classes($content);

        return array_combine($classes, $classes) ?: [];
    }
}
