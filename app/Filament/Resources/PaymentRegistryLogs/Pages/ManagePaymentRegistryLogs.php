<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentRegistryLogs\Pages;

use App\Filament\Resources\PaymentRegistryLogs\PaymentRegistryLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentRegistryLogs extends ManageRecords
{
    protected static string $resource = PaymentRegistryLogResource::class;

    /** Журнал только для чтения — создавать записи вручную нельзя. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
