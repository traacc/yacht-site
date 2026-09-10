<?php

declare(strict_types=1);

namespace App\Filament\Resources\PasswordResetRequests\Pages;

use App\Filament\Resources\PasswordResetRequests\PasswordResetRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePasswordResetRequests extends ManageRecords
{
    protected static string $resource = PasswordResetRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
