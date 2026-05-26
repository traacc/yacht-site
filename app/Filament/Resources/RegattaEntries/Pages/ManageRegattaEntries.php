<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\YachtDocumentType;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaEntries extends ManageRecords
{
    protected static string $resource = RegattaEntryResource::class;

    /**
     * Возвращает список обязательных документов для заявки.
     *
     * Если передан $regattaId и у регаты настроены собственные документы — возвращает их.
     * Иначе — глобальные настройки.
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
            Action::make('documentSettings')
                ->label('Обязательные документы')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('white')
                ->url(fn () => \App\Filament\Pages\RegattaEntryDocumentSettings::getUrl()),
            CreateAction::make()
                ->createAnother(false)
                ->using(function (array $data, string $model): RegattaEntry {
                    $requiredDocs = $data['required_documents'] ?? [];
                    unset($data['required_documents']);

                    /** @var RegattaEntry $record */
                    $record = $model::create($data);

                    app(SyncDocumentFilesAction::class)->execute($record, $requiredDocs);

                    return $record;
                }),
        ];
    }
}
