<?php

declare(strict_types=1);

namespace App\Filament\Resources\GiftCertificates\Pages;

use App\Filament\Resources\GiftCertificates\GiftCertificateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGiftCertificates extends ManageRecords
{
    protected static string $resource = GiftCertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить сертификат'),
        ];
    }
}
