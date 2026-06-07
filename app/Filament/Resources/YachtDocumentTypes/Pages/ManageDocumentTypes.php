<?php

declare(strict_types=1);

namespace App\Filament\Resources\YachtDocumentTypes\Pages;

use App\Actions\Yacht\UpdateYachtRequiredDocumentsAction;
use App\Filament\Resources\YachtDocumentTypeResource;
use App\Models\YachtDocumentType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentTypes extends ManageRecords
{
    protected static string $resource = YachtDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новый тип документа яхты')
                ->label('Добавить тип документа')
                ->createAnother(false)
                ->after(function (YachtDocumentType $record): void {
                    // Новый тип документа яхты по умолчанию делаем обязательным.
                    if (! $record->is_configurable) {
                        return;
                    }

                    $action = app(UpdateYachtRequiredDocumentsAction::class);
                    $required = $action->get();
                    $required[$record->key] = true;
                    $action->save($required);
                }),
        ];
    }

    /**
     * Перед удалением проверяем, не используется ли тип в документах.
     */

}