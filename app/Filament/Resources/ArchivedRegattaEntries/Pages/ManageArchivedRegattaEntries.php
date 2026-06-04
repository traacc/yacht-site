<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArchivedRegattaEntries\Pages;

use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\Resources\ArchivedRegattaEntries\ArchivedRegattaEntryResource;
use App\Models\Regatta;
use Filament\Resources\Pages\ManageRecords;

class ManageArchivedRegattaEntries extends ManageRecords
{
    protected static string $resource = ArchivedRegattaEntryResource::class;

    /**
     * Возвращает список документов для заявки с флагом обязательности.
     *
     * Если передан $regattaId и у регаты настроены собственные документы — возвращает их.
     * Иначе — глобальные настройки.
     *
     * @return array<int, array{doc_type: string, title: string, is_required: bool}>
     */
    public static function getRequiredDocuments(?string $regattaId = null): array
    {
        if ($regattaId !== null) {
            $regatta = Regatta::find($regattaId);

            if ($regatta && ! empty($regatta->entry_required_documents)) {
                return $regatta->getEntryDocuments();
            }
        }

        return app(UpdateRegattaEntryRequiredDocumentsAction::class)->getRequiredList();
    }
}
