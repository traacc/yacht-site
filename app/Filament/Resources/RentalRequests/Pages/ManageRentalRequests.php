<?php

declare(strict_types=1);

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Filament\Resources\RentalRequests\RentalRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRentalRequests extends ManageRecords
{
    protected static string $resource = RentalRequestResource::class;
}
