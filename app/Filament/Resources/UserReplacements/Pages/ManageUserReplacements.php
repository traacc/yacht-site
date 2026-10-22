<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserReplacements\Pages;

use App\Filament\Resources\UserReplacements\UserReplacementResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUserReplacements extends ManageRecords
{
    protected static string $resource = UserReplacementResource::class;

    /** Журнал только для чтения — записи создаёт замена в разделе «Пользователи». */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
