<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\OwnershipTransfers\Pages;

use App\Filament\User\Resources\OwnershipTransfers\OwnershipTransferResource;
use App\Models\YachtOwnershipTransfer;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageOwnershipTransfers extends ManageRecords
{
    protected static string $resource = OwnershipTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Запросить передачу яхты')
                ->modalHeading('Запросить передачу яхты')
                ->createAnother(false)
                ->using(fn (array $data): YachtOwnershipTransfer => OwnershipTransferResource::createTransfer($data))
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Заявка отправлена')
                        ->body('Администратор рассмотрит вашу заявку на передачу яхты.'),
                ),
        ];
    }
}
