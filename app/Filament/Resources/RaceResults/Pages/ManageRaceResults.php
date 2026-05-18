<?php

namespace App\Filament\Resources\RaceResults\Pages;

use App\Filament\Resources\RaceResults\RaceResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRaceResults extends ManageRecords
{
    protected static string $resource = RaceResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
