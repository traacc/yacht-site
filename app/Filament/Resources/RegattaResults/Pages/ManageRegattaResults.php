<?php

namespace App\Filament\Resources\RegattaResults\Pages;

use App\Actions\RegattaResult\ImportRegattaResultItemsAction;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

            Action::make('export_csv_header')
                ->label('Экспорт в CSV')
                ->icon(Heroicon::ArrowDownTray)
                ->color('white')
                ->form([
                    Select::make('regatta_id')
                        ->label('Регата')
                        ->relationship('regatta', 'name')
                        ->placeholder('Все регаты')
                        ->model(RegattaResult::class),

                    /*
                    Select::make('result_type')
                        ->label('Тип результата')
                        ->options([
                            'preliminary' => 'Предварительный',
                            'final'       => 'Финальный',
                        ])
                        ->placeholder('Все типы'),
                    */
                ])
                ->modalHeading('Экспорт результатов в CSV')
                ->modalSubmitActionLabel('Скачать CSV')
                ->action(function (array $data): StreamedResponse {
                    $query = RegattaResult::with('items.team', 'items.yacht', 'regatta');

                    if (! empty($data['regatta_id'])) {
                        $query->where('regatta_id', $data['regatta_id']);
                    }

                    if (! empty($data['result_type'])) {
                        $query->where('result_type', $data['result_type']);
                    }

                    $records  = $query->get();
                    $filename = sprintf('results_export_%s.csv', now()->format('Y-m-d'));

                    return response()->streamDownload(function () use ($records): void {
                        $handle = fopen('php://output', 'w');
                        fputs($handle, "\xEF\xBB\xBF"); // BOM для Excel
                        fputcsv($handle, ['Регата', 'Тип', 'Место', 'Команда', 'Яхта', 'Очки'], ';');

                        foreach ($records as $result) {
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
        ];
    }
}
