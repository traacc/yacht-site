<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageServiceRequests extends ManageRecords
{
    protected static string $resource = ServiceRequestResource::class;
}
