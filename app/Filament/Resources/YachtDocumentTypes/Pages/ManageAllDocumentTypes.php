<?php

declare(strict_types=1);

namespace App\Filament\Resources\YachtDocumentTypes\Pages;

use App\Filament\Resources\DocumentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAllDocumentTypes extends ManageRecords
{
    protected static string $resource = DocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новый тип документа')
                ->label('Добавить тип документа')
                ->createAnother(false),
        ];
    }
}
