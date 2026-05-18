<?php

namespace App\Filament\Resources\RegattaResults\Pages;

use App\Filament\Resources\RegattaResults\RegattaResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaResults extends ManageRecords
{
    protected static string $resource = RegattaResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
