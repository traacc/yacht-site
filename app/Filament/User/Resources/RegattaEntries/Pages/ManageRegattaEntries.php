<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\User\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\YachtDocumentType;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\UniqueConstraintViolationException;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    /**
     * Возвращает список обязательных документов для заявки.
     *
     * Если передан $regattaId и у регаты настроены собственные обязательные документы
     * (entry_required_documents не null и не пустой) — возвращает их.
     * Иначе возвращает глобальные настройки.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public static function getRequiredDocuments(?string $regattaId = null): array
    {
        if ($regattaId !== null) {
            $regatta = Regatta::find($regattaId);

            if ($regatta && ! empty($regatta->entry_required_documents)) {
                return YachtDocumentType::cachedConfigurable()
                    ->filter(fn (YachtDocumentType $t) => in_array($t->key, $regatta->entry_required_documents, true))
                    ->map(fn (YachtDocumentType $t) => ['doc_type' => $t->key, 'title' => $t->label])
                    ->values()
                    ->all();
            }
        }

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
