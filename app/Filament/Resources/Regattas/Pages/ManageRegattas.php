<?php

namespace App\Filament\Resources\Regattas\Pages;

use App\Filament\Resources\Regattas\RegattaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattas extends ManageRecords
{
    protected static string $resource = RegattaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
