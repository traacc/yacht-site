<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\CrewJoinRequests\Pages;

use App\Filament\User\Resources\CrewJoinRequests\CrewJoinRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCrewJoinRequests extends ManageRecords
{
    protected static string $resource = CrewJoinRequestResource::class;
}
