<?php

declare(strict_types=1);

namespace App\Filament\User\Resources\RentalRequests\Pages;

use App\Filament\User\Resources\RentalRequests\RentalRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRentalRequests extends ManageRecords
{
    protected static string $resource = RentalRequestResource::class;
}
