<?php

declare(strict_types=1);

namespace App\Filament\Resources\CrewJoinRequests\Pages;

use App\Filament\Resources\CrewJoinRequests\CrewJoinRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCrewJoinRequests extends ManageRecords
{
    protected static string $resource = CrewJoinRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
