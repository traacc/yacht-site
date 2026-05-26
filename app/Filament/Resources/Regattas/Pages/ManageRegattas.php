<?php

declare(strict_types=1);

namespace App\Filament\Resources\Regattas\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Regatta\UpdateRegattaRequiredDocumentsAction;
use App\Filament\Resources\Regattas\RegattaResource;
use App\Models\Regatta;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattas extends ManageRecords
{
    protected static string $resource = RegattaResource::class;

    /**
     * Возвращает динамический список обязательных документов из настроек.
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public static function getRequiredDocuments(): array
    {
        return app(UpdateRegattaRequiredDocumentsAction::class)->getRequiredList();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('documentSettings')
                ->label('Обязательные документы')
                ->icon('heroicon-o-document-text')
                ->color('white')
                ->url(fn () => \App\Filament\Pages\RegattaDocumentSettings::getUrl()),
            CreateAction::make()
                ->createAnother(false)
                ->using(function (array $data, string $model): Regatta {
                    $requiredDocs = $data['required_documents'] ?? [];
                    $extraDocs    = $data['extra_documents'] ?? [];
                    unset($data['required_documents'], $data['extra_documents']);

                    /** @var Regatta $record */
                    $record = $model::create($data);

                    $sync = app(SyncDocumentFilesAction::class);
                    $sync->execute($record, $requiredDocs);
                    $sync->execute($record, $extraDocs);

                    return $record;
                }),
        ];
    }
}
