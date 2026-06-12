<?php

namespace App\Filament\Resources\RegattaResults\Pages;

use App\Actions\RegattaResult\GenerateRegattaResultPdfAction;
use App\Actions\RegattaResult\ImportRegattaResultItemsAction;
use App\Exports\RegattaResultExport;
use App\Filament\Resources\RegattaResults\RegattaResultResource;
use App\Models\RegattaResult;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ManageRegattaResults extends ManageRecords
{
    protected static string $resource = RegattaResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->createAnother(false)->modalHeading('Новые результаты регаты'),
            
            Action::make('import_csv_new')
                ->label('Импорт из CSV')
                ->icon(Heroicon::ArrowUpTray)
                ->color('white')
                ->form([
                    Select::make('regatta_id')
                        ->label('Регата')
                        ->relationship('regatta', 'name')
                        ->required()
                        ->model(RegattaResult::class),

                    Select::make('result_type')
                        ->label('Тип результата')
                        ->options([
                            'preliminary' => 'Предварительный',
                            'final'       => 'Финальный',
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
                    $path    = Storage::disk('local')->path($data['csv_file']);
                    $content = file_get_contents($path);
                    Storage::disk('local')->delete($data['csv_file']);

                    $result = RegattaResult::create([
                        'regatta_id'  => $data['regatta_id'],
                        'result_type' => $data['result_type'],
                        'source'      => 'imported',
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

                    $body = "Импортировано: {$importResult['imported']}, пропущено: {$importResult['skipped']}";
                    if (! empty($importResult['errors'])) {
                        $body .= "\n\nОшибки:\n" . implode("\n", $importResult['errors']);
                    }

                    Notification::make()
                        ->title('Импорт завершён')
                        ->body($body)
                        ->when(empty($importResult['errors']), fn($n) => $n->success())
                        ->when(! empty($importResult['errors']), fn($n) => $n->warning())
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
                                    $r->id => ($r->regatta?->name ?? '—') . ' • ' . match ($r->result_type) {
                                        'preliminary' => 'Предварительный',
                                        'final'       => 'Финальный',
                                        default       => $r->result_type,
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
                                    $r->id => ($r->regatta?->name ?? '—') . ' • ' . match ($r->result_type) {
                                        'preliminary' => 'Предварительный',
                                        'final'       => 'Финальный',
                                        default       => $r->result_type,
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
}
