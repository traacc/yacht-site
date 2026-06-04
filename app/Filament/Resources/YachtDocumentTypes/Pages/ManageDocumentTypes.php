<?php

declare(strict_types=1);

namespace App\Filament\Resources\YachtDocumentTypes\Pages;

use App\Filament\Resources\YachtDocumentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentTypes extends ManageRecords
{
    protected static string $resource = YachtDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->modalHeading('Новый тип документа')
                ->label('Добавить документ')
                ->createAnother(false),
        ];
    }

    /**
     * Перед удалением проверяем, не используется ли тип в документах.
     */

}