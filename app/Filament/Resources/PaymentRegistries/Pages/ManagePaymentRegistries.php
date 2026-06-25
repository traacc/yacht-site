<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentRegistries\Pages;

use App\Filament\Resources\PaymentRegistries\PaymentRegistryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentRegistries extends ManageRecords
{
    protected static string $resource = PaymentRegistryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modalHeading('Новый реестр платежей')
                ->createAnother(false),
        ];
    }
}
