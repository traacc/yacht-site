<?php

declare(strict_types=1);

namespace App\Filament\Resources\PendingRegattaEntries\Pages;

use App\Filament\Resources\PendingRegattaEntries\PendingRegattaEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePendingRegattaEntries extends ManageRecords
{
    protected static string $resource = PendingRegattaEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
