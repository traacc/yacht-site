<?php

declare(strict_types=1);

namespace App\Filament\Resources\Yachts\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Yacht\UpdateYachtRequiredDocumentsAction;
use App\Exports\YachtExport;
use App\Filament\Resources\YachtDocumentTypeResource;
use App\Filament\Resources\Yachts\YachtResource;
use App\Imports\YachtImport;
use App\Models\Yacht;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageYachts extends ManageRecords
{
    protected static string $resource = YachtResource::class;

    /**
     * Возвращает динамический список обязательных документов из настроек.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public static function getRequiredDocuments(): array
    {
        return app(UpdateYachtRequiredDocumentsAction::class)->getRequiredList();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('documentSettings')
                ->label('Документы')
                ->icon('heroicon-o-document-text')
                ->color('white')
                ->url(fn () => YachtDocumentTypeResource::getUrl()),
            Action::make('export')
                ->label('Экспорт в Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('white')
                ->action(fn () => (new YachtExport)->download(
                    'yachts_'.now()->format('Y-m-d').'.xlsx'
                )),
            Action::make('import')
                ->label('Импорт из Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('white')
                ->modalHeading('Импорт яхт из Excel')
                ->modalDescription('Загрузите файл .xlsx по шаблону. Колонки: Тип яхты, №, Название яхты, Г.в., Владелец, Место регистрации.')
                ->modalSubmitActionLabel('Импортировать')
                ->schema([
                    FileUpload::make('file')
                        ->label('Файл Excel (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->storeFiles(false)
                        ->required()
                        ->helperText(new HtmlString(
                            '<a href="'.asset('files/CARTER%2030_list.xlsx').'" target="_blank" class="text-primary-600 underline">Скачать шаблон</a>'
                        )),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'] ?? null;

                    if (is_array($file)) {
                        $file = reset($file);
                    }

                    if (! $file instanceof TemporaryUploadedFile) {
                        Notification::make()
                            ->title('Не удалось прочитать загруженный файл')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $import = (new YachtImport)->import($file->getRealPath());
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Ошибка импорта')
                            ->body('Не удалось обработать файл: '.$e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $body = "Создано: {$import->created}, обновлено: {$import->updated}";

                    if ($import->skipped > 0) {
                        $body .= ", пропущено: {$import->skipped}";
                    }

                    $body .= ". Владельцев сопоставлено: {$import->matchedOwners}";

                    Notification::make()
                        ->title('Импорт завершён')
                        ->body($body)
                        ->success()
                        ->send();
                }),
            /*
            Action::make('documentSettings')
                ->label('Обязательные документы')
                ->icon('heroicon-o-document-check')
                ->color('white')
                ->url(fn () => \App\Filament\Pages\YachtDocumentSettings::getUrl()),
            */
            CreateAction::make()
                ->createAnother(false)
                ->modalHeading('Новая яхта')
                ->using(function (array $data, string $model): Yacht {
                    $requiredDocs = $data['required_documents'] ?? [];
                    $extraDocs = $data['extra_documents'] ?? [];
                    $selectedYachtId = $data['selected_yacht_id'] ?? null;
                    unset($data['required_documents'], $data['extra_documents'], $data['yacht_search'], $data['selected_yacht_id']);

                    if ($selectedYachtId) {
                        /** @var Yacht $record */
                        $record = Yacht::query()
                            ->withoutGlobalScope(\App\Models\Scopes\OwnedScope::class)
                            ->findOrFail($selectedYachtId);
                        $record->update($data);
                    } else {
                        /** @var Yacht $record */
                        $record = $model::create($data);
                    }

                    $sync = app(SyncDocumentFilesAction::class);
                    $sync->execute($record, $requiredDocs);
                    $sync->execute($record, $extraDocs);

                    return $record;
                }),
        ];
    }
}
