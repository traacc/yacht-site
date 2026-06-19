<?php

declare(strict_types=1);

namespace App\Filament\Resources\YachtOwnershipTransfers\Pages;

use App\Filament\Resources\YachtOwnershipTransfers\YachtOwnershipTransferResource;
use Filament\Resources\Pages\ManageRecords;

class ManageYachtOwnershipTransfers extends ManageRecords
{
    protected static string $resource = YachtOwnershipTransferResource::class;
}
