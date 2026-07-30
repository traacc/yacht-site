<?php

declare(strict_types=1);

namespace App\Filament\Resources\RepairRequests\Pages;

use App\Filament\Resources\RepairRequests\RepairRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRepairRequests extends ManageRecords
{
    protected static string $resource = RepairRequestResource::class;
}
