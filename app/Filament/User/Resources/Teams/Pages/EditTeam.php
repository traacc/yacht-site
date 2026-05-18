<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\Teams\Pages;

use App\Filament\User\Resources\Teams\TeamResource;
use Filament\Resources\Pages\EditRecord;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}
