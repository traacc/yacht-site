<?php

namespace App\Filament\Resources\RegattaResults\Pages;

use App\Actions\RegattaResult\GenerateRegattaResultPdfAction;
use App\Actions\RegattaResult\ImportRegattaResultItemsAction;
use App\Actions\RegattaResult\ImportRgdResultItemsAction;
use App\Exports\RegattaResultExport;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaResult;
use App\Services\Rgd\RgdParser;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListRegattaResults extends ListRecords
{
    protected static string $resource = RegattaResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->createAnother(false)
                ->modalHeading('Новые результаты регаты')
                // Участников на форме создания заполнить нечем (таблица ввода
                // строится по сохранённой записи), поэтому список формируем по
                // активным заявкам сразу после создания и открываем таблицу.
                ->after(function (RegattaResult $record): void {
                    $created = RegattaResultResource::createItemsFromEntries($record);

                    Notification::make()
                        ->title($created > 0
                            ? 'Добавлено участников по заявкам: '.$created
                            : 'Активных заявок на эту регату нет')
                        ->success()
                        ->send();
                })
                ->successRedirectUrl(fn (RegattaResult $record): string => RegattaResultResource::getUrl('edit', ['record' => $record])),

            Action::make('import_csv_new')
                ->label('Импорт из CSV')
                ->icon(Heroicon::ArrowUpTray)
                ->color('white')
                ->form([
                    Select::make('regatta_id')
                        ->label('Регата')
                        ->relationship(
                            name: 'regatta',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query->visibleForUser(),
                        )
                        ->required()
                        ->model(RegattaResult::class),

                    Select::make('result_type')
                        ->label('Тип результата')
                        ->options([
                            'preliminary' => 'Предварительный',
                            'final' => 'Финальный',
                        ])
                        ->required()
                        ->default('preliminary'),

                    FileUpload::make('csv_file')
                        ->label('CSV-файл')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('csv-imports')
                        ->required(),

                    Checkbox::make('replace')
                        ->label('Заменить существующие записи (если результат уже есть)')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['csv_file']);
                    $content = file_get_contents($path);
                    Storage::disk('local')->delete($data['csv_file']);

                    $result = RegattaResult::create([
                        'regatta_id' => $data['regatta_id'],
                        'result_type' => $data['result_type'],
                        'source' => 'imported',
                    ]);

                    try {
                        $importResult = app(ImportRegattaResultItemsAction::class)
                            ->execute($result, $content, (bool) ($data['replace'] ?? false));
                    } catch (\RuntimeException $e) {
                        $result->delete();
                        Notification::make()
                            ->title('Ошибка импорта')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    RegattaResultResource::recalculateRatings($result);

                    $body = "Импортировано: {$importResult['imported']}, пропущено: {$importResult['skipped']}";
                    if (! empty($importResult['errors'])) {
                        $body .= "\n\nОшибки:\n".implode("\n", $importResult['errors']);
                    }

                    Notification::make()
                        ->title('Импорт завершён')
                        ->body($body)
                        ->when(empty($importResult['errors']), fn ($n) => $n->success())
                        ->when(! empty($importResult['errors']), fn ($n) => $n->warning())
                        ->send();
                }),

            Action::make('import_rgd')
                ->label('Импорт из RGD')
                ->icon(Heroicon::ArrowUpTray)
                ->color('white')
                ->form([
                    Select::make('regatta_result_id')
                        ->label('Результат регаты')
                        ->options(function () {
                            return RegattaResult::with('regatta')
                                ->get()
                                ->mapWithKeys(fn (RegattaResult $r) => [
                                    $r->id => ($r->regatta?->name ?? '—').' • '.match ($r->result_type) {
                                        'preliminary' => 'Предварительный',
                                        'final' => 'Финальный',
                                        default => $r->result_type,
                                    },
                                ]);
                        })
                        ->searchable()
                        ->required(),

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
                    $path = Storage::disk('local')->path($data['rgd_file']);
                    $content = file_get_contents($path);
                    Storage::disk('local')->delete($data['rgd_file']);

                    $result = RegattaResult::findOrFail($data['regatta_result_id']);

                    try {
                        $summary = app(ImportRgdResultItemsAction::class)->execute(
                            $result,
                            $content,
                            $data['class'],
                            (bool) ($data['create_missing'] ?? false),
                            (bool) ($data['replace'] ?? false),
                        );
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Ошибка импорта')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    RegattaResultResource::recalculateRatings($result);

                    $body = "Импортировано строк: {$summary['imported']}, пропущено: {$summary['skipped']}"
                        ."\nСоздано яхт: {$summary['created_yachts']}, команд: {$summary['created_teams']}";
                    if (! empty($summary['errors'])) {
                        $body .= "\n\nОшибки:\n".implode("\n", $summary['errors']);
                    }

                    Notification::make()
                        ->title('Импорт из RGD завершён')
                        ->body($body)
                        ->when(empty($summary['errors']), fn ($n) => $n->success())
                        ->when(! empty($summary['errors']), fn ($n) => $n->warning())
                        ->send();
                }),

            Action::make('export_xlsx')
                ->label('Экспорт в Excel')
                ->icon(Heroicon::ArrowDownTray)
                ->color('white')
                ->form([
                    Select::make('regatta_result_id')
                        ->label('Результат регаты')
                        ->options(function () {
                            return RegattaResult::with('regatta')
                                ->get()
                                ->mapWithKeys(fn (RegattaResult $r) => [
                                    $r->id => ($r->regatta?->name ?? '—').' • '.match ($r->result_type) {
                                        'preliminary' => 'Предварительный',
                                        'final' => 'Финальный',
                                        default => $r->result_type,
                                    },
                                ]);
                        })
                        ->searchable()
                        ->required(),
                ])
                ->modalHeading('Экспорт результатов в Excel')
                ->modalSubmitActionLabel('Скачать Excel')
                ->action(function (array $data) {
                    $regattaResult = RegattaResult::findOrFail($data['regatta_result_id']);
                    $filename = sprintf('results_%s_%s.xlsx', $regattaResult->regatta?->name ?? 'regatta', $regattaResult->result_type);

                    return (new RegattaResultExport($regattaResult))->download($filename);
                }),

            Action::make('export_pdf')
                ->label('Экспорт в PDF')
                ->icon(Heroicon::ArrowDownTray)
                ->color('white')
                ->form([
                    Select::make('regatta_result_id')
                        ->label('Результат регаты')
                        ->options(function () {
                            return RegattaResult::with('regatta')
                                ->get()
                                ->mapWithKeys(fn (RegattaResult $r) => [
                                    $r->id => ($r->regatta?->name ?? '—').' • '.match ($r->result_type) {
                                        'preliminary' => 'Предварительный',
                                        'final' => 'Финальный',
                                        default => $r->result_type,
                                    },
                                ]);
                        })
                        ->searchable()
                        ->required(),
                ])
                ->modalHeading('Экспорт результатов в PDF')
                ->modalSubmitActionLabel('Скачать PDF')
                ->action(function (array $data) {
                    $regattaResult = RegattaResult::findOrFail($data['regatta_result_id']);

                    return app(GenerateRegattaResultPdfAction::class)->execute($regattaResult);
                }),
        ];
    }

    /**
     * Список зачётных классов из загруженного RGD (для реактивного селекта).
     * Состояние FileUpload — либо временный файл (до отправки), либо путь на диске.
     *
     * @param  mixed  $state
     * @return array<string, string>
     */
    protected function rgdClasses($state): array
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
