<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntries\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Filament\Resources\RegattaEntries\RegattaEntryResource;
use App\Models\RegattaEntry;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

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
