<?php

namespace App\Filament\User\Resources\RegattaResults\Pages;

use App\Filament\User\Resources\RegattaResults\RegattaResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRegattaResults extends ManageRecords
{
    protected static string $resource = RegattaResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }
}
