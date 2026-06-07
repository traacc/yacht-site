<?php

declare(strict_types=1);

namespace App\Filament\Resources\Yachts\Pages;

use App\Actions\Document\SyncDocumentFilesAction;
use App\Actions\Yacht\UpdateYachtRequiredDocumentsAction;
use App\Filament\Resources\Yachts\YachtResource;
use App\Filament\Resources\RegattaEntries\YachtDocumentTypeResource;
use App\Models\Yacht;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

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
                ->url(fn () => \App\Filament\Resources\YachtDocumentTypeResource::getUrl()),
            Action::make('documentSettings')
                ->label('Обязательные документы')
                ->icon('heroicon-o-document-check')
                ->color('white')
                ->url(fn () => \App\Filament\Pages\YachtDocumentSettings::getUrl()),
            CreateAction::make()
                ->createAnother(false)
                ->modalHeading('Новая яхта')
                ->using(function (array $data, string $model): Yacht {
                    $requiredDocs = $data['required_documents'] ?? [];
                    $extraDocs    = $data['extra_documents'] ?? [];
                    unset($data['required_documents'], $data['extra_documents']);

                    /** @var Yacht $record */
                    $record = $model::create($data);

                    $sync = app(SyncDocumentFilesAction::class);
                    $sync->execute($record, $requiredDocs);
                    $sync->execute($record, $extraDocs);

                    return $record;
                }),
        ];
    }
}
