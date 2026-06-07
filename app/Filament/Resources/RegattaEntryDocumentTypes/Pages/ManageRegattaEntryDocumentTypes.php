<?php

declare(strict_types=1);

namespace App\Filament\Resources\RegattaEntryDocumentTypes\Pages;

use App\Filament\Resources\RegattaEntryDocumentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaEntryDocumentTypes extends ManageRecords
{
    protected static string $resource = RegattaEntryDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новый тип документа заявки')
                ->label('Добавить тип документа')
                ->createAnother(false),
        ];
    }
}
