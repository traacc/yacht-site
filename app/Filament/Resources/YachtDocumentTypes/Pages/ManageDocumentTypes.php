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
    protected function beforeDelete(): void
    {
        /** @var \App\Models\YachtDocumentType $record */
        foreach ($this->getSelectedTableRecords() as $record) {
            if ($record->isUsedInDocuments()) {
                \Filament\Notifications\Notification::make()
                    ->title("Тип «{$record->label}» используется в {$record->usageCount()} документах и не может быть удалён.")
                    ->danger()
                    ->send();

                $this->halt();
            }
        }
    }
}