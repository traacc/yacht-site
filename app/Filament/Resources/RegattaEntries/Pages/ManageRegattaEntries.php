<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('documentSettings')
                ->label('Типы документов')
                ->icon('heroicon-o-document-text')
                ->color('white')
                ->url(fn () => \App\Filament\Resources\YachtDocumentTypeResource::getUrl()),
            CreateAction::make()
            ->modalHeading('Новая заявка на регату')
                ->createAnother(false)
                ->using(function (array $data, string $model): RegattaEntry {
                    $requiredDocs = $data['required_documents'] ?? [];
                    $crew = $data['crew'] ?? [];
                    unset($data['required_documents'], $data['crew']);

                    /** @var RegattaEntry $record */
                    $record = $model::create($data);

                    app(SyncDocumentFilesAction::class)->execute($record, $requiredDocs);
                    RegattaEntryResource::syncCrew($record, $crew);

                    return $record;
                }),
        ];
    }
}
