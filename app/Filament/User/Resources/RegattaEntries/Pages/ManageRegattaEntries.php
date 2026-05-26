<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\RegattaEntry;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\UniqueConstraintViolationException;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    /**
     * Возвращает динамический список обязательных документов из настроек.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public static function getRequiredDocuments(): array
    {
        return app(UpdateRegattaEntryRequiredDocumentsAction::class)->getRequiredList();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->using(function (array $data, string $model): \Illuminate\Database\Eloquent\Model {
                    $docs = $data['required_documents'] ?? [];
                    unset($data['required_documents']);

                    $data['status'] = 'approved';

                    try {
                        /** @var RegattaEntry $record */
                        $record = $model::create($data);
                    } catch (UniqueConstraintViolationException) {
                        Notification::make()
                            ->title('Заявка уже существует')
                            ->body('Эта команда уже подала заявку на выбранную регату.')
                            ->danger()
                            ->send();

                        $this->halt();
                    }

                    app(SyncDocumentFilesAction::class)->execute($record, $docs);

                    return $record;
                })
                ->createAnother(false)
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Готово!')
                        ->body('Ваша заявка успешно подана, ожидайте подтверждения'),
                ),
        ];
    }
}
