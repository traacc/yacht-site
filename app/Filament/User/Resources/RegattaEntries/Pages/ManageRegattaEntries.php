<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\RecalculateEntryDocumentsCompleteAction;
use App\Enums\RegattaEntrySource;
use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\RegattaEntry;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    /**
     * Возвращает список документов для заявки с флагом обязательности.
     *
     * Если передан $regattaId и у регаты настроены собственные документы
     * (entry_required_documents не null и не пустой) — возвращает их.
     * Иначе возвращает глобальные настройки (все считаются обязательными).
     *
     * @return array<int, array{doc_type: string, title: string, is_required: bool}>
     */
    public static function getRequiredDocuments(?string $regattaId = null): array
    {
        return app(RecalculateEntryDocumentsCompleteAction::class)->requiredDocuments($regattaId);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Подать заявку')
                ->using(function (array $data, string $model): Model {
                    $docs = $data['required_documents'] ?? [];
                    $crew = $data['crew'] ?? [];
                    unset($data['required_documents'], $data['crew']);

                    $data['status'] = 'pending';
                    $data['source'] = RegattaEntrySource::PersonalCabinet->value;

                    // Проверка дубликата до записи в БД
                    /** @var RegattaEntry $model */
                    $exists = $model::where('regatta_id', $data['regatta_id'])
                        ->where('team_id', $data['team_id'])
                        ->exists();

                    if ($exists) {
                        Notification::make()
                            ->title('Заявка уже существует')
                            ->body('Эта команда уже подала заявку на выбранную регату.')
                            ->danger()
                            ->send();

                        $this->halt();
                    }

                    /** @var RegattaEntry $record */
                    $record = $model::create($data);

                    // execute() попутно проставит documents_complete по факту сохранённых файлов.
                    app(SyncDocumentFilesAction::class)->execute($record, $docs);
                    RegattaEntryResource::syncCrew($record, $crew);

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
